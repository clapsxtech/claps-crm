<?php
namespace Tests\Feature;

use App\Console\Commands\ImportErpMigration;
use App\Modules\Erp\Services\Attendance;
use App\Modules\Erp\Services\Finance;
use App\Modules\Erp\Services\MigrationImporter;
use App\Modules\Erp\Services\MoneyOperations;
use App\Modules\Erp\Services\Reporting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class ErpMigrationOpeningDuesTest extends TestCase
{
    private $actor;
    private $unit;
    private $subject;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['erp.migration_import_enabled'=>true, 'integrations.sheets.enabled'=>false, 'google.service.enable'=>false]);
        $this->actor = $this->createAdmin();
        $this->unit = DB::table('erp_units')->insertGetId(['code'=>'SYN-DUES','name'=>'Synthetic dues unit','timezone'=>'UTC']);
        $this->subject = DB::table('erp_subjects')->insertGetId(['name'=>'Synthetic Dues Mathematics']);
        DB::table('erp_memberships')->insert(['user_id'=>$this->actor->id,'unit_id'=>$this->unit,'role'=>'admin']);
        $this->service = new MigrationImporter();
    }

    private function data(int $amount = 150000): array
    {
        return ['rows'=>[
            ['type'=>'student','external_id'=>'SYN-S1','unit_code'=>'SYN-DUES','name'=>'Synthetic Private Student','timezone'=>'UTC','registered_on'=>'2026-01-01','grade'=>'8','syllabus'=>'Synthetic','status'=>'active'],
            ['type'=>'teacher','external_id'=>'SYN-T1','unit_codes'=>['SYN-DUES'],'name'=>'Synthetic Private Teacher','timezone'=>'UTC','status'=>'active'],
            ['type'=>'assignment','external_id'=>'SYN-A1','unit_code'=>'SYN-DUES','student_id'=>'SYN-S1','teacher_id'=>'SYN-T1','subject_code'=>'MATH','starts_on'=>'2026-01-01','student_rate_paise'=>12000,'teacher_rate_paise'=>6000,'currency'=>'INR','status'=>'active'],
            ['type'=>'opening_balance','external_id'=>'SYN-SB1','unit_code'=>'SYN-DUES','owner_type'=>'student','owner_id'=>'SYN-S1','amount_paise'=>-25000,'currency'=>'INR','as_of'=>'2026-10-01'],
            ['type'=>'opening_balance','external_id'=>'SYN-TB1','unit_code'=>'SYN-DUES','owner_type'=>'teacher','owner_id'=>'SYN-T1','amount_paise'=>$amount,'currency'=>'INR','as_of'=>'2026-10-01'],
            ['type'=>'historical_attendance','external_id'=>'SYN-H1','unit_code'=>'SYN-DUES','assignment_id'=>'SYN-A1','starts_at'=>'2026-09-30T10:00:00Z','actual_minutes'=>60,'status'=>'delivered','financial_effect'=>'none'],
        ], 'references'=>['schema_version'=>2,'target_database'=>DB::connection()->getDatabaseName(),'unit_codes'=>['SYN-DUES'],'subject_codes'=>['MATH'],'subject_ids'=>['MATH'=>(int)$this->subject],'subject_names'=>['MATH'=>'Synthetic Dues Mathematics'],'cutoff_date'=>'2026-10-01','source_manifest'=>['source_id'=>'SYN-DUES-SOURCE','exported_at'=>'2026-10-01T00:00:00Z'],'teacher_eligibilities'=>[['external_id'=>'SYN-E1','teacher_id'=>'SYN-T1','subject_code'=>'MATH','grade'=>'8','syllabus'=>'Synthetic']]]];
    }

    private function digest(array $data): string
    {
        $preview = $this->service->preview($data['rows'], $data['references']);
        $this->assertTrue($preview['valid'], json_encode($preview));
        return $preview['digest'];
    }

    private function execute(array $data): array
    {
        return $this->service->execute($data['rows'], $data['references'], $this->digest($data), '2026-10-01', $this->actor->id);
    }

    private function invalid(callable $operation, ?string $code = null): void
    {
        try { $operation(); $this->fail('Expected validation failure.'); }
        catch (ValidationException $e) { if ($code) $this->assertSame($code, $e->errors()['migration'][0]); else $this->assertNotEmpty($e->errors()); }
    }

    private function emptyImport(): void
    {
        foreach (['erp_students','erp_teachers','erp_teacher_units','erp_eligibilities','erp_enrollments','erp_ledger','erp_migration_batches','erp_migration_mappings','erp_migration_history','erp_audit','erp_lessons','erp_attendance','integration_outbox'] as $table) $this->assertSame(0, DB::table($table)->count(), $table);
    }

    public function test_positive_teacher_due_is_an_immutable_snapshot_not_earnings_payment_or_history_replay(): void
    {
        $data = $this->data();
        $this->digest($data); $this->emptyImport();
        $first = $this->execute($data);
        $this->assertTrue($first['reconciliation']['reconciled']);
        $this->assertSame(150000, $first['totals']['teacher_payable_paise']);
        $this->assertSame(-25000, $first['totals']['student_balance_paise']);
        $this->assertSame(0, $first['totals']['historical_financial_effect_paise']);
        $this->assertSame(60, $first['reconciliation']['historical_minutes']);
        $this->assertSame(7, $first['reconciliation']['mapped_records']);
        $teacher = (int) DB::table('erp_teachers')->value('id');
        $entry = DB::table('erp_ledger')->where('teacher_id', $teacher)->first();
        $this->assertSame('opening_balance', $entry->kind);
        $this->assertNull($entry->student_id); $this->assertNull($entry->attendance_id);
        $this->assertSame('2026-10-01 00:00:00', $entry->occurred_at);
        $expected = ['earned_paise'=>0,'paid_paise'=>0,'other_adjustments_paise'=>150000,'net_payable_paise'=>150000];
        $this->assertSame($expected, (new MoneyOperations())->teacherStatement($this->unit, $teacher));
        $this->assertSame($expected, (new Reporting())->teacherStatements($this->unit, [$teacher])[$teacher]);
        $this->assertSame(0, DB::table('erp_ledger')->whereIn('kind', ['teacher_payment','teacher_accrual','lesson_charge','payment'])->count());
        foreach (['erp_lessons','erp_attendance','integration_outbox'] as $table) $this->assertSame(0, DB::table($table)->count());
        $second = $this->execute($data);
        $this->assertTrue($second['replayed']); $this->assertSame(0, $second['writes']);
        $this->assertSame($first['batch_id'], $second['batch_id']);
        $this->assertSame(1, DB::table('erp_audit')->where('action','migration.imported')->count());
    }

    public function test_zero_teacher_dues_are_explicit_and_v1_never_silently_opens_teacher_payroll(): void
    {
        $data = $this->data(0);
        $missing = $data; array_splice($missing['rows'],4,1);
        $this->assertSame('explicit_teacher_opening_balance_required', $this->service->preview($missing['rows'],$missing['references'])['issues'][0]['code']);
        $v1 = $data; $v1['references']['schema_version'] = 1;
        $this->assertSame('teacher_opening_requires_schema_version_2', $this->service->preview($v1['rows'],$v1['references'])['issues'][0]['code']);
        $missing['references']['schema_version'] = 1;
        $this->assertTrue($this->service->preview($missing['rows'],$missing['references'])['valid']);
        $this->execute($data);
        $this->assertDatabaseHas('erp_ledger',['kind'=>'opening_balance','student_id'=>null,'amount_paise'=>0]);
    }

    public function test_owner_identity_is_scoped_by_type_so_teacher_opening_cannot_satisfy_student_zero(): void
    {
        $data = $this->data();
        $data['rows'][1]['external_id'] = 'SYN-S1';
        $data['rows'][2]['teacher_id'] = 'SYN-S1';
        $data['rows'][4]['owner_id'] = 'SYN-S1';
        $data['references']['teacher_eligibilities'][0]['teacher_id'] = 'SYN-S1';
        $this->assertTrue($this->service->preview($data['rows'],$data['references'])['valid']);
        array_splice($data['rows'],3,1);
        $this->assertSame('explicit_student_opening_balance_required', $this->service->preview($data['rows'],$data['references'])['issues'][0]['code']);
    }

    public function test_teacher_advance_precision_currency_limits_and_cutoff_fail_closed(): void
    {
        foreach ([-1,1000000001,150000.0,'150000',true,null] as $amount) {
            $data=$this->data(); $data['rows'][4]['amount_paise']=$amount;
            $preview=$this->service->preview($data['rows'],$data['references']);
            $this->assertFalse($preview['valid']); $this->assertNull($preview['totals']);
            if ($amount === -1) $this->assertContains('teacher_advance_requires_review', array_column($preview['issues'],'code'));
        }
        foreach (['currency'=>'USD','as_of'=>'2026-10-02','owner_id'=>'MISSING','unit_code'=>'OTHER'] as $field=>$value) {
            $data=$this->data(); $data['rows'][4][$field]=$value;
            $this->assertFalse($this->service->preview($data['rows'],$data['references'])['valid']);
        }
        $data=$this->data(1000000000);
        $this->assertTrue($this->service->preview($data['rows'],$data['references'])['valid']);
        $data['rows'][]=array_merge($data['rows'][4],['external_id'=>'SYN-DUPLICATE']);
        $this->assertContains('duplicate_opening_balance',array_column($this->service->preview($data['rows'],$data['references'])['issues'],'code'));
        $this->emptyImport();
    }

    public function test_changed_teacher_amount_digest_and_source_identity_do_not_overwrite(): void
    {
        $data=$this->data(); $digest=$this->digest($data);
        $changed=$data; ++$changed['rows'][4]['amount_paise'];
        $this->invalid(fn()=> $this->service->execute($changed['rows'],$changed['references'],$digest,'2026-10-01',$this->actor->id),'digest_mismatch');
        $this->execute($data);
        $this->assertFalse($this->service->preview($changed['rows'],$changed['references'])['valid']);
        $changed['references']['source_manifest']['source_id']='SYN-DIFFERENT-SOURCE';
        $this->assertFalse($this->service->preview($changed['rows'],$changed['references'])['valid']);
        $this->assertSame(150000,(int)DB::table('erp_ledger')->whereNotNull('teacher_id')->sum('amount_paise'));
    }

    public function test_teacher_opening_cannot_be_reintroduced_at_a_later_cutoff_or_on_existing_ledger(): void
    {
        $data=$this->data(); $data['rows']=[$data['rows'][1],$data['rows'][4]]; $data['references']['teacher_eligibilities']=[];
        $this->execute($data);
        $data['references']['cutoff_date']='2026-10-02'; $data['rows'][1]['as_of']='2026-10-02'; $data['rows'][1]['external_id']='SYN-TB2';
        $this->assertSame('opening_balance_requires_empty_teacher_ledger',$this->service->preview($data['rows'],$data['references'])['issues'][0]['code']);
        $this->assertSame(1, DB::table('erp_ledger')->count());
    }

    public function test_rehearsal_reconciles_and_rolls_back_all_successful_writes(): void
    {
        $data=$this->data(); $level=DB::connection()->transactionLevel();
        $result=$this->service->rehearse($data['rows'],$data['references'],$this->digest($data),'2026-10-01',$this->actor->id);
        $this->assertTrue($result['rehearsed']); $this->assertTrue($result['rolled_back']); $this->assertTrue($result['reconciliation']['reconciled']);
        $this->assertGreaterThan(0,$result['attempted_writes']); $this->assertSame(0,$result['writes']);
        $this->assertArrayNotHasKey('batch_id',$result); $this->assertArrayNotHasKey('batch_id',$result['reconciliation']);
        $this->assertSame($level,DB::connection()->transactionLevel()); $this->emptyImport();
        $this->assertDatabaseHas('erp_units',['id'=>$this->unit,'code'=>'SYN-DUES']);
        $this->assertDatabaseHas('erp_memberships',['user_id'=>$this->actor->id,'unit_id'=>$this->unit,'role'=>'admin']);
        $this->assertFalse($this->service->reconcile($data['rows'],$data['references'])['reconciled']);
        $this->assertFalse($this->execute($data)['replayed']);
    }

    public function test_rehearsal_rolls_back_an_injected_failure_after_teacher_opening(): void
    {
        $data=$this->data(); $digest=$this->digest($data); $level=DB::connection()->transactionLevel(); $armed=true;
        DB::listen(function ($event) use (&$armed) { if ($armed && stripos($event->sql,'insert')===0 && strpos($event->sql,'erp_migration_history')!==false) { $armed=false; throw new \RuntimeException('Synthetic rehearsal failure'); } });
        try { $this->service->rehearse($data['rows'],$data['references'],$digest,'2026-10-01',$this->actor->id); $this->fail('Expected synthetic failure.'); }
        catch (\RuntimeException $e) { $this->assertSame('Synthetic rehearsal failure',$e->getMessage()); }
        $this->assertSame($level,DB::connection()->transactionLevel()); $this->emptyImport();
    }

    public function test_rehearsal_keeps_production_integrations_and_import_gates_closed(): void
    {
        $data=$this->data(); $digest=$this->digest($data);
        $this->app['env']='production';
        try { $this->invalid(fn()=> $this->service->rehearse($data['rows'],$data['references'],$digest,'2026-10-01',$this->actor->id),'production_rehearsal_forbidden'); }
        finally { $this->app['env']='testing'; }
        config(['integrations.sheets.enabled'=>true]);
        $this->invalid(fn()=> $this->service->rehearse($data['rows'],$data['references'],$digest,'2026-10-01',$this->actor->id),'rehearsal_requires_disabled_integrations');
        config(['integrations.sheets.enabled'=>false,'erp.migration_import_enabled'=>false]);
        $this->invalid(fn()=> $this->service->rehearse($data['rows'],$data['references'],$digest,'2026-10-01',$this->actor->id),'import_disabled');
        $this->emptyImport();
    }

    public function test_reconciliation_detects_mapping_provenance_target_and_summary_drift_without_repair(): void
    {
        $data=$this->data(); $digest=$this->digest($data); $this->execute($data);
        $mutations=[['erp_ledger','amount_paise',149999],['erp_ledger','kind','teacher_accrual'],['erp_migration_history','financial_effect','post'],['erp_migration_history','actual_minutes',30],['erp_enrollments','teacher_rate_paise',6500],['erp_eligibilities','grade','9'],['erp_migration_mappings','row_digest',str_repeat('a',64)],['erp_migration_batches','totals','{}'],['erp_migration_batches','manifest','{}']];
        foreach ($mutations as [$table,$field,$value]) {
            $id=DB::table($table)->value('id'); $before=DB::table($table)->where('id',$id)->value($field);
            DB::table($table)->where('id',$id)->update([$field=>$value]);
            $report=$this->service->reconcile($data['rows'],$data['references']);
            $this->assertFalse($report['reconciled'], $table.'.'.$field); $this->assertSame(0,$report['writes']);
            $this->invalid(fn()=> $this->service->execute($data['rows'],$data['references'],$digest,'2026-10-01',$this->actor->id));
            $this->assertEquals($value,DB::table($table)->where('id',$id)->value($field));
            DB::table($table)->where('id',$id)->update([$field=>$before]);
            $this->assertTrue($this->service->reconcile($data['rows'],$data['references'])['reconciled']);
        }
    }

    public function test_reconciliation_rejects_missing_target_wrong_teacher_owner_and_extra_provenance(): void
    {
        $data=$this->data(); $this->execute($data);
        $teacherOpening=DB::table('erp_ledger')->whereNotNull('teacher_id')->first();
        foreach (['teacher_id'=>999999,'amount_paise'=>149999,'student_id'=>999999,'occurred_at'=>'2026-10-02 00:00:00','created_by'=>999999] as $field=>$value) {
            DB::table('erp_ledger')->where('id',$teacherOpening->id)->update([$field=>$value]);
            $this->assertFalse($this->service->reconcile($data['rows'],$data['references'])['reconciled'], $field);
            DB::table('erp_ledger')->where('id',$teacherOpening->id)->update([$field=>$teacherOpening->$field]);
        }
        $history=DB::table('erp_migration_history')->first();
        DB::table('erp_migration_history')->where('id',$history->id)->delete();
        $this->assertSame('mapped_target_missing',$this->service->reconcile($data['rows'],$data['references'])['issues'][0]['code']);
        DB::table('erp_migration_history')->insert((array)$history);
        $mapping=(array)DB::table('erp_migration_mappings')->first(); unset($mapping['id']); $mapping['external_id']='SYN-UNEXPECTED';
        $extra=DB::table('erp_migration_mappings')->insertGetId($mapping);
        $this->assertSame('unexpected_batch_mapping',$this->service->reconcile($data['rows'],$data['references'])['issues'][0]['code']);
        DB::table('erp_migration_mappings')->where('id',$extra)->delete();
        $this->assertTrue($this->service->reconcile($data['rows'],$data['references'])['reconciled']);
    }

    public function test_reversed_opening_is_flagged_and_replay_never_recreates_it(): void
    {
        $data=$this->data(); $digest=$this->digest($data); $this->execute($data);
        $opening=DB::table('erp_ledger')->whereNotNull('teacher_id')->first();
        DB::table('erp_ledger')->insert(['unit_id'=>$this->unit,'teacher_id'=>$opening->teacher_id,'kind'=>'reversal','amount_paise'=>-150000,'currency'=>'INR','reference'=>'SYN-UNSUPPORTED-REVERSAL','event_key'=>'synthetic:reversal','reverses_id'=>$opening->id,'occurred_at'=>'2026-10-02 00:00:00','created_by'=>$this->actor->id]);
        $this->assertSame('opening_snapshot_has_reversal_requires_review',$this->service->reconcile($data['rows'],$data['references'])['issues'][0]['code']);
        $this->invalid(fn()=> $this->service->execute($data['rows'],$data['references'],$digest,'2026-10-01',$this->actor->id));
        $this->assertSame(3,DB::table('erp_ledger')->count());
        $this->assertSame(0,(int)DB::table('erp_ledger')->whereNotNull('teacher_id')->sum('amount_paise'));
    }

    public function test_teacher_preview_and_execute_revalidate_commit_time_destination_conflict(): void
    {
        $data=$this->data(); $digest=$this->digest($data);
        DB::table('erp_teachers')->insert(['code'=>'SYN-T1','name'=>'Existing unmanaged teacher','timezone'=>'UTC','status'=>'active']);
        $this->invalid(fn()=> $this->service->execute($data['rows'],$data['references'],$digest,'2026-10-01',$this->actor->id),'unmanaged_stable_code_conflict');
        $this->assertSame(0,DB::table('erp_ledger')->count());
        $this->assertSame(0,DB::table('erp_students')->count());
        $this->assertSame(0,DB::table('erp_migration_batches')->count());
        $this->assertDatabaseHas('erp_teachers',['name'=>'Existing unmanaged teacher']);
    }

    public function test_reconciliation_does_not_confuse_later_payments_with_imported_opening_snapshot(): void
    {
        $data=$this->data(); $this->execute($data); $teacher=(int)DB::table('erp_teachers')->value('id');
        (new MoneyOperations())->recordTeacherPayment($this->unit,$this->actor->id,['teacher_id'=>$teacher,'amount_paise'=>50000,'reference'=>'SYN-PAID-ELSEWHERE','reason'=>'Synthetic completed external payment','paid_on'=>'2026-10-02','already_paid'=>true]);
        $report=$this->service->reconcile($data['rows'],$data['references']);
        $this->assertTrue($report['reconciled']); $this->assertSame(150000,$report['totals']['teacher_payable_paise']);
        $this->assertSame(100000,(new MoneyOperations())->teacherStatement($this->unit,$teacher)['net_payable_paise']);
        $this->assertSame(0,$this->execute($data)['writes']);
    }

    public function test_commit_rolls_back_when_post_write_reconciliation_detects_tampering(): void
    {
        $data=$this->data(); $digest=$this->digest($data); $armed=true;
        DB::listen(function ($event) use (&$armed) { if ($armed && stripos($event->sql,'insert')===0 && strpos($event->sql,'erp_audit')!==false) { $armed=false; DB::table('erp_ledger')->whereNotNull('teacher_id')->update(['amount_paise'=>1]); } });
        $this->invalid(fn()=> $this->service->execute($data['rows'],$data['references'],$digest,'2026-10-01',$this->actor->id),'mapped_target_drift');
        $this->emptyImport();
    }

    public function test_teacher_opening_provenance_alone_preserves_pre_cutoff_financial_guard(): void
    {
        config(['erp.payroll_policy'=>'approved_attended_only']);
        $data=$this->data(); $this->execute($data);
        // Isolate the opening's own guard, without relying on the other three mapping kinds.
        DB::table('erp_migration_mappings')->whereIn('entity_type',['student','teacher','assignment'])->delete();
        $assignment=DB::table('erp_enrollments')->first();
        $lesson=DB::table('erp_lessons')->insertGetId(['unit_id'=>$this->unit,'student_id'=>$assignment->student_id,'teacher_id'=>$assignment->teacher_id,'enrollment_id'=>$assignment->id,'subject_id'=>$this->subject,'starts_at'=>'2026-09-30 23:59:59','ends_at'=>'2026-10-01 00:59:59','status'=>'completed','kind'=>'regular','student_rate_paise'=>12000,'teacher_rate_paise'=>6000,'currency'=>'INR']);
        $this->invalid(fn()=> (new Attendance())->submit($this->unit,$this->actor->id,['lesson_id'=>$lesson,'actual_minutes'=>60]));
        $attendance=DB::table('erp_attendance')->insertGetId(['unit_id'=>$this->unit,'lesson_id'=>$lesson,'actual_minutes'=>60,'status'=>'approved','submitted_by'=>$this->actor->id]);
        $this->invalid(fn()=> (new Finance())->postTeacherAttendance($this->unit,$this->actor->id,$attendance));
        $this->assertSame(2,DB::table('erp_ledger')->count());
        $this->assertSame(0,DB::table('erp_ledger')->whereIn('kind',['teacher_accrual','lesson_charge'])->count());
    }

    public function test_cli_rehearsal_and_reconciliation_are_explicit_private_and_mutually_exclusive(): void
    {
        $data=$this->data(); $file=tempnam(sys_get_temp_dir(),'synthetic-dues-'); file_put_contents($file,json_encode($data));
        try {
            $command=new ImportErpMigration(); $command->setLaravel($this->app); $tester=new CommandTester($command);
            $this->assertSame(1,$tester->execute(['file'=>$file,'--reconcile'=>true]));
            $this->assertSame(1,$tester->execute(['file'=>$file,'--rehearse'=>true,'--execute'=>true]));
            $this->assertSame(1,$tester->execute(['file'=>$file,'--rehearse'=>true,'--allow-production'=>true]));
            $this->assertSame(1,$tester->execute(['file'=>$file,'--rehearse'=>true]));
            $options=['file'=>$file,'--rehearse'=>true,'--confirm-digest'=>$this->digest($data),'--cutoff'=>'2026-10-01','--target-database'=>DB::connection()->getDatabaseName(),'--actor'=>(string)$this->actor->id];
            $this->assertSame(0,$tester->execute($options));
            $output=json_decode($tester->getDisplay(),true); $this->assertTrue($output['rolled_back']); $this->assertSame(0,$output['writes']); $this->emptyImport();
            $this->execute($data);
            $this->assertSame(0,$tester->execute(['file'=>$file,'--reconcile'=>true]));
            $output=json_decode($tester->getDisplay(),true); $this->assertTrue($output['reconciled']); $this->assertSame(0,$output['writes']);
            foreach (['Synthetic Private Student','Synthetic Private Teacher','SYN-S1','SYN-T1'] as $private) $this->assertStringNotContainsString($private,$tester->getDisplay());
        } finally { unlink($file); }
    }
}
