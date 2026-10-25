<?php

namespace Tests\Feature;

use App\Modules\Erp\Services\Attendance;
use App\Modules\Erp\Services\Finance;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ErpMigrationCutoffTest extends TestCase
{
    // This fixture deliberately builds a separate SQLite schema in setUp(). Do not
    // begin a RefreshDatabase transaction on a PDO that setUp() then replaces.
    // Each test gets a newly purged in-memory connection and explicit migrations.
    public function refreshDatabase(): void
    {
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'erp.finance_policy' => 'admin_import_delivered_only', 'erp.payroll_policy' => 'unconfigured']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $table) { $table->increments('id'); });
        DB::table('users')->insert(['id' => 1]);
        foreach (glob(database_path('migrations/*erp*.php')) as $path) {
            $migration = require_once $path;
            if (is_object($migration)) $migration->up();
            else {
                $name = \Illuminate\Support\Str::studly(implode('_', array_slice(explode('_', basename($path, '.php')), 4)));
                (new $name())->up();
            }
        }
        DB::table('erp_units')->insert([['id' => 1, 'code' => 'TEST-A', 'name' => 'Synthetic A'], ['id' => 2, 'code' => 'TEST-B', 'name' => 'Synthetic B']]);
        DB::table('erp_students')->insert(['id' => 1, 'unit_id' => 1, 'code' => 'SYN-STUDENT', 'name' => 'Synthetic Student', 'registered_on' => '2026-01-01', 'timezone' => 'Asia/Kolkata', 'status' => 'active', 'grade' => '8', 'syllabus' => 'Test']);
        DB::table('erp_teachers')->insert(['id' => 1, 'code' => 'SYN-TEACHER', 'name' => 'Synthetic Teacher', 'timezone' => 'Asia/Kolkata', 'status' => 'active']);
        DB::table('erp_teacher_units')->insert(['teacher_id' => 1, 'unit_id' => 1]);
        DB::table('erp_subjects')->insert(['id' => 1, 'name' => 'Synthetic Subject']);
        DB::table('erp_enrollments')->insert(['id' => 1, 'unit_id' => 1, 'student_id' => 1, 'teacher_id' => 1, 'subject_id' => 1, 'starts_on' => '2026-01-01', 'student_rate_paise' => 12000, 'teacher_rate_paise' => 6000, 'currency' => 'INR', 'status' => 'active']);
        $this->lesson(1, '2026-10-20 10:00:00');
    }

    private function lesson(int $id, string $start, string $status = 'scheduled'): void
    {
        DB::table('erp_lessons')->insert(['id' => $id, 'unit_id' => 1, 'enrollment_id' => 1, 'student_id' => 1, 'teacher_id' => 1, 'subject_id' => 1, 'starts_at' => $start, 'ends_at' => \Carbon\Carbon::parse($start)->addHour()->format('Y-m-d H:i:s'), 'status' => $status, 'kind' => 'regular', 'student_rate_paise' => 12000, 'teacher_rate_paise' => 6000, 'currency' => 'INR']);
    }

    private function invalid(callable $operation): void
    {
        try { $operation(); $this->fail('Expected a domain validation failure.'); }
        catch (ValidationException $expected) { $this->assertNotEmpty($expected->errors()); }
    }

    private function provenance(string $type, int $target, string $cutoff = '2026-10-20'): void
    {
        $tables = ['student'=>'erp_students','teacher'=>'erp_teachers','assignment'=>'erp_enrollments','opening_balance'=>'erp_ledger'];
        $source = 'synthetic-'.$type.'-'.$cutoff;
        $batch = DB::table('erp_migration_batches')->insertGetId(['source_id'=>$source,'cutoff'=>$cutoff,'digest'=>hash('sha256',$source),'manifest'=>'{}','counts'=>'{}','totals'=>'{}','actor_id'=>1]);
        DB::table('erp_migration_mappings')->insert(['source_id'=>$source,'entity_type'=>$type,'external_id'=>'stable-'.$target,'target_type'=>$tables[$type],'target_id'=>$target,'row_digest'=>hash('sha256','row'),'batch_id'=>$batch]);
    }

    public function test_mapped_student_blocks_submission_and_crossing_midnight_without_mutation(): void
    {
        $this->provenance('student', 1);
        DB::table('erp_lessons')->where('id',1)->update(['starts_at'=>'2026-10-19 23:59:59','ends_at'=>'2026-10-20 00:59:59']);
        $this->invalid(fn() => (new Attendance())->submit(1,1,['lesson_id'=>1,'actual_minutes'=>60]));
        $this->assertSame(0,DB::table('erp_attendance')->count());
        $this->assertSame(0,DB::table('erp_ledger')->count());
        $this->assertSame('scheduled',DB::table('erp_lessons')->value('status'));
    }

    public function test_exact_utc_midnight_and_after_allow_both_streams(): void
    {
        config(['app.timezone'=>'Asia/Kolkata','erp.payroll_policy'=>'approved_attended_only']);
        $this->provenance('student',1);
        DB::table('erp_lessons')->where('id',1)->update(['starts_at'=>'2026-10-20 00:00:00','ends_at'=>'2026-10-20 01:00:00']);
        $service = new Attendance();
        $attendance = $service->submit(1,1,['lesson_id'=>1,'actual_minutes'=>60]);
        $service->approve(1,1,$attendance->id);
        $service->importBatch(1,1,['attendance_ids'=>[$attendance->id],'reference'=>'BOUNDARY']);
        $this->assertSame(-12000,(int)DB::table('erp_ledger')->where('kind','lesson_charge')->sum('amount_paise'));
        $this->assertSame(6000,(int)DB::table('erp_ledger')->where('kind','teacher_accrual')->sum('amount_paise'));
    }

    public function test_direct_posting_and_admin_approval_cannot_bypass_teacher_cutoff(): void
    {
        config(['erp.payroll_policy'=>'approved_attended_only']);
        $service = new Attendance();
        $attendance = $service->submit(1,1,['lesson_id'=>1,'actual_minutes'=>60]);
        $this->provenance('teacher',1,'2026-10-21');
        $this->invalid(fn()=>$service->approve(1,1,$attendance->id));
        $this->invalid(fn()=>$service->importBatch(1,1,['attendance_ids'=>[$attendance->id],'reference'=>'BLOCKED']));
        DB::table('erp_attendance')->where('id',$attendance->id)->update(['status'=>'approved']);
        $this->invalid(fn()=>(new Finance())->postTeacherAttendance(1,1,$attendance->id));
        $this->assertSame(0,DB::table('erp_ledger')->count());
        $this->assertSame(0,DB::table('erp_attendance_batches')->count());
    }

    public function test_later_opening_provenance_extends_boundary_and_reversal_correction_still_work(): void
    {
        config(['erp.payroll_policy'=>'approved_attended_only']);
        $service = new Attendance();
        $attendance=$service->submit(1,1,['lesson_id'=>1,'actual_minutes'=>60]);
        $service->approve(1,1,$attendance->id);
        $service->importBatch(1,1,['attendance_ids'=>[$attendance->id],'reference'=>'BEFORE-BOUNDARY']);
        $this->provenance('assignment',1,'2026-10-19');
        $opening=DB::table('erp_ledger')->insertGetId(['unit_id'=>1,'student_id'=>1,'kind'=>'opening_balance','amount_paise'=>0,'currency'=>'INR','reference'=>'MIGRATION:ZERO','event_key'=>'migration:zero','occurred_at'=>'2026-10-21 00:00:00','created_by'=>1]);
        $this->provenance('opening_balance',$opening,'2026-10-21');
        $service->correct(1,1,$attendance->id,['actual_minutes'=>30,'reason'=>'Correct synthetic error']);
        $this->assertSame(2,DB::table('erp_ledger')->where('kind','reversal')->count());
        $this->assertSame(0,(int)DB::table('erp_ledger')->sum('amount_paise'));
        $this->invalid(fn()=>$service->approve(1,1,$attendance->id));
        $this->invalid(fn()=>$service->importBatch(1,1,['attendance_ids'=>[$attendance->id],'reference'=>'NO-REPOST']));
    }

    public function test_other_destination_ids_and_unmapped_opening_do_not_create_cutoff(): void
    {
        $this->provenance('student',999,'2026-10-21');
        DB::table('erp_ledger')->insert(['unit_id'=>2,'student_id'=>1,'kind'=>'opening_balance','amount_paise'=>0,'currency'=>'INR','reference'=>'OTHER-UNIT','event_key'=>'other-unit','occurred_at'=>'2026-10-21 00:00:00','created_by'=>1]);
        config(['erp.payroll_policy'=>'approved_attended_only']);
        $service=new Attendance();
        $a=$service->submit(1,1,['lesson_id'=>1,'actual_minutes'=>60]);
        $service->approve(1,1,$a->id);
        $service->importBatch(1,1,['attendance_ids'=>[$a->id],'reference'=>'UNMAPPED']);
        $this->assertSame(2,DB::table('erp_ledger')->whereIn('kind',['teacher_accrual','lesson_charge'])->count());
    }
}
