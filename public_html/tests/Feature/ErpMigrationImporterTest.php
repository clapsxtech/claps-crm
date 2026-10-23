<?php
namespace Tests\Feature;

use App\Console\Commands\ImportErpMigration;
use App\Modules\Erp\Services\MigrationImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class ErpMigrationImporterTest extends TestCase
{
    private $actor;
    private $unit;
    private $subject;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['erp.migration_import_enabled' => true]);
        $this->actor = $this->createAdmin();
        $this->unit = DB::table('erp_units')->insertGetId(['code'=>'SYN-AA','name'=>'Synthetic import unit','timezone'=>'UTC']);
        $this->subject = DB::table('erp_subjects')->insertGetId(['name'=>'Synthetic Import Mathematics']);
        DB::table('erp_memberships')->insert(['user_id'=>$this->actor->id,'unit_id'=>$this->unit,'role'=>'admin']);
        $this->service = new MigrationImporter();
    }

    private function data(): array
    {
        return ['rows'=>[
            ['type'=>'student','external_id'=>'SYN-S1','unit_code'=>'SYN-AA','name'=>'Synthetic Import Student','timezone'=>'UTC','registered_on'=>'2026-01-01','grade'=>'8','syllabus'=>'Synthetic','status'=>'active'],
            ['type'=>'teacher','external_id'=>'SYN-T1','unit_codes'=>['SYN-AA'],'name'=>'Synthetic Import Teacher','timezone'=>'UTC','status'=>'active'],
            ['type'=>'assignment','external_id'=>'SYN-A1','unit_code'=>'SYN-AA','student_id'=>'SYN-S1','teacher_id'=>'SYN-T1','subject_code'=>'MATH','starts_on'=>'2026-01-01','student_rate_paise'=>12000,'teacher_rate_paise'=>6000,'currency'=>'INR','status'=>'active'],
            ['type'=>'opening_balance','external_id'=>'SYN-B1','unit_code'=>'SYN-AA','owner_type'=>'student','owner_id'=>'SYN-S1','amount_paise'=>-25000,'currency'=>'INR','as_of'=>'2026-10-01'],
            ['type'=>'historical_attendance','external_id'=>'SYN-H1','unit_code'=>'SYN-AA','assignment_id'=>'SYN-A1','starts_at'=>'2026-09-30T10:00:00Z','actual_minutes'=>60,'status'=>'delivered','financial_effect'=>'none'],
        ],'references'=>['schema_version'=>1,'target_database'=>DB::connection()->getDatabaseName(),'unit_codes'=>['SYN-AA'],'subject_codes'=>['MATH'],'subject_ids'=>['MATH'=>(int)$this->subject],'subject_names'=>['MATH'=>'Synthetic Import Mathematics'],'cutoff_date'=>'2026-10-01','source_manifest'=>['source_id'=>'SYN-SOURCE','exported_at'=>'2026-10-01T00:00:00Z'],'teacher_eligibilities'=>[['external_id'=>'SYN-E1','teacher_id'=>'SYN-T1','subject_code'=>'MATH','grade'=>'8','syllabus'=>'Synthetic']]]];
    }

    private function execute(array $data): array
    {
        $preview = $this->service->preview($data['rows'],$data['references']);
        $this->assertTrue($preview['valid'], json_encode($preview));
        return $this->service->execute($data['rows'],$data['references'],$preview['digest'],$data['references']['cutoff_date'],$this->actor->id);
    }

    private function rejected(callable $call): void
    {
        try { $call(); $this->fail('Expected import rejection.'); }
        catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
    }

    public function test_dry_run_digest_exact_replay_and_archival_history_never_charge(): void
    {
        $data=$this->data();
        $preview=$this->service->preview($data['rows'],$data['references']);
        $this->assertTrue($preview['valid']);
        $this->assertFalse($preview['can_import']);
        $this->assertSame(0,DB::table('erp_students')->count());
        $first=$this->execute($data);
        $this->assertFalse($first['replayed']);
        $this->assertSame(-25000,$first['totals']['student_balance_paise']);
        $second=$this->execute($data);
        $this->assertTrue($second['replayed']);
        $this->assertSame($first['batch_id'],$second['batch_id']);
        $this->assertSame(0,$second['writes']);
        $this->assertSame(1,DB::table('erp_migration_batches')->count());
        $this->assertSame(6,DB::table('erp_migration_mappings')->count());
        $this->assertSame(1,DB::table('erp_migration_history')->count());
        $this->assertSame(0,DB::table('erp_lessons')->count());
        $this->assertSame(0,DB::table('erp_attendance')->count());
        $this->assertSame(1,DB::table('erp_ledger')->count());
        $this->assertDatabaseHas('erp_ledger',['kind'=>'opening_balance','amount_paise'=>-25000,'teacher_id'=>null]);
        $this->assertSame(0,DB::table('erp_ledger')->whereIn('kind',['teacher_accrual','lesson_charge'])->count());
        $this->assertDatabaseHas('erp_enrollments',['student_rate_paise'=>12000,'teacher_rate_paise'=>6000]);
    }

    public function test_digest_cutoff_target_schema_and_payroll_opening_fail_closed(): void
    {
        $data=$this->data(); $preview=$this->service->preview($data['rows'],$data['references']);
        $changed=$data; $changed['rows'][3]['amount_paise']=-24000;
        $this->rejected(fn()=> $this->service->execute($changed['rows'],$changed['references'],$preview['digest'],'2026-10-01',$this->actor->id));
        $this->rejected(fn()=> $this->service->execute($data['rows'],$data['references'],$preview['digest'],'2026-10-02',$this->actor->id));
        foreach (['schema_version'=>2,'target_database'=>'not_the_configured_database','teacher_eligibilities'=>[]] as $key=>$value) {
            $bad=$data; $bad['references'][$key]=$value;
            $this->assertFalse($this->service->preview($bad['rows'],$bad['references'])['valid']);
        }
        $bad=$data; $bad['rows'][3]['owner_type']='teacher'; $bad['rows'][3]['owner_id']='SYN-T1';
        $this->assertFalse($this->service->preview($bad['rows'],$bad['references'])['valid']);
        $this->assertSame(0,DB::table('erp_migration_batches')->count());
    }

    public function test_changed_source_batch_and_unmanaged_stable_codes_are_never_overwritten(): void
    {
        $data=$this->data(); $this->execute($data);
        $changed=$data; $changed['rows'][0]['name']='Changed Synthetic Name';
        $this->assertFalse($this->service->preview($changed['rows'],$changed['references'])['valid']);
        $changed['references']['source_manifest']['source_id']='SYN-SECOND-SOURCE';
        $this->assertFalse($this->service->preview($changed['rows'],$changed['references'])['valid']);
        $this->assertDatabaseHas('erp_students',['code'=>'SYN-S1','name'=>'Synthetic Import Student']);
        $this->assertSame(1,DB::table('erp_ledger')->count());
    }

    public function test_execute_requires_enabled_config_and_active_admin_in_every_unit(): void
    {
        $data=$this->data(); $preview=$this->service->preview($data['rows'],$data['references']);
        config(['erp.migration_import_enabled'=>false]);
        $this->rejected(fn()=> $this->service->execute($data['rows'],$data['references'],$preview['digest'],'2026-10-01',$this->actor->id));
        config(['erp.migration_import_enabled'=>true]);
        DB::table('erp_memberships')->where('user_id',$this->actor->id)->update(['role'=>'teacher']);
        try { $this->service->execute($data['rows'],$data['references'],$preview['digest'],'2026-10-01',$this->actor->id); $this->fail('Forbidden actor accepted.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
        $this->assertSame(0,DB::table('erp_migration_batches')->count());
    }

    public function test_mid_import_database_failure_rolls_back_everything(): void
    {
        $data=$this->data(); $preview=$this->service->preview($data['rows'],$data['references']);
        $armed=true;
        DB::listen(function($event) use (&$armed) {
            if ($armed && stripos($event->sql,'insert')===0 && strpos($event->sql,'erp_migration_history')!==false) {
                $armed=false; throw new \RuntimeException('Synthetic history insert failure');
            }
        });
        try { $this->service->execute($data['rows'],$data['references'],$preview['digest'],'2026-10-01',$this->actor->id); $this->fail('Expected injected failure.'); }
        catch (\RuntimeException $e) { $this->assertSame('Synthetic history insert failure',$e->getMessage()); }
        foreach (['erp_migration_batches','erp_migration_mappings','erp_migration_history','erp_students','erp_teachers','erp_enrollments','erp_ledger'] as $table) $this->assertSame(0,DB::table($table)->count(),$table);
    }

    public function test_command_defaults_to_preview_and_needs_explicit_execute_gates(): void
    {
        $file=tempnam(sys_get_temp_dir(),'synthetic-import-'); file_put_contents($file,json_encode($this->data()));
        try {
            $command=new ImportErpMigration(); $command->setLaravel($this->app); $tester=new CommandTester($command);
            $this->assertSame(0,$tester->execute(['file'=>$file]));
            $output=json_decode($tester->getDisplay(),true);
            $this->assertTrue($output['dry_run']);
            $this->assertStringNotContainsString('Synthetic Import Student',$tester->getDisplay());
            $this->assertSame(0,DB::table('erp_migration_batches')->count());
            $this->assertSame(1,$tester->execute(['file'=>$file,'--execute'=>true]));
            $this->assertSame(0,DB::table('erp_migration_batches')->count());
            $this->assertSame(0,$tester->execute(['file'=>$file,'--execute'=>true,'--confirm-digest'=>$output['digest'],'--cutoff'=>'2026-10-01','--target-database'=>DB::connection()->getDatabaseName(),'--actor'=>(string)$this->actor->id]));
            $this->assertSame(1,DB::table('erp_migration_batches')->count());
            $this->assertSame(1,$tester->execute(['file'=>'https://example.invalid/private.json']));
        } finally { unlink($file); }
    }
    public function test_canonical_object_key_order_and_commit_time_conflict_revalidation(): void
    {
        $data=$this->data(); $first=$this->service->preview($data['rows'],$data['references']);
        $reordered=$data; $reordered['references']=array_reverse($reordered['references'],true);
        $reordered['rows'][0]=array_reverse($reordered['rows'][0],true);
        $this->assertSame($first['digest'],$this->service->preview($reordered['rows'],$reordered['references'])['digest']);
        DB::table('erp_students')->insert(['unit_id'=>$this->unit,'code'=>'SYN-S1','name'=>'Existing unmanaged person','timezone'=>'UTC','registered_on'=>'2026-01-01','grade'=>'8','syllabus'=>'Synthetic','status'=>'active']);
        $this->rejected(fn()=> $this->service->execute($data['rows'],$data['references'],$first['digest'],'2026-10-01',$this->actor->id));
        $this->assertSame(0,DB::table('erp_migration_batches')->count());
        $this->assertSame(0,DB::table('erp_migration_mappings')->count());
        $this->assertDatabaseHas('erp_students',['name'=>'Existing unmanaged person']);
    }

    public function test_later_cutoff_cannot_add_another_opening_to_existing_student_ledger(): void
    {
        $data=$this->data(); $this->execute($data);
        $next=$data; $next['references']['cutoff_date']='2026-10-02'; $next['rows'][3]['as_of']='2026-10-02'; $next['rows'][3]['external_id']='SYN-B2';
        $preview=$this->service->preview($next['rows'],$next['references']);
        $this->assertFalse($preview['valid']);
        $this->assertSame('opening_balance_requires_empty_student_ledger',$preview['issues'][0]['code']);
        $this->assertSame(1,DB::table('erp_migration_batches')->count());
        $this->assertSame(1,DB::table('erp_ledger')->count());
        $this->assertSame(-25000,(int)DB::table('erp_ledger')->sum('amount_paise'));
    }

    public function test_missing_second_unit_membership_and_deleted_actor_are_denied(): void
    {
        $data=$this->data();
        $second=DB::table('erp_units')->insertGetId(['code'=>'SYN-BB','name'=>'Synthetic separate unit','timezone'=>'UTC']);
        $data['references']['unit_codes'][]='SYN-BB';
        $data['rows'][]=array_merge($data['rows'][0],['external_id'=>'SYN-S2','unit_code'=>'SYN-BB']);
        $data['rows'][]=array_merge($data['rows'][3],['external_id'=>'SYN-B2','owner_id'=>'SYN-S2','unit_code'=>'SYN-BB','amount_paise'=>0]);
        $preview=$this->service->preview($data['rows'],$data['references']);
        $this->assertTrue($preview['valid']);
        try { $this->service->execute($data['rows'],$data['references'],$preview['digest'],'2026-10-01',$this->actor->id); $this->fail('Cross-unit actor accepted.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
        DB::table('erp_memberships')->insert(['user_id'=>$this->actor->id,'unit_id'=>$second,'role'=>'admin']);
        $this->actor->forceFill(['deleted_at'=>now()])->save();
        try { $this->service->execute($data['rows'],$data['references'],$preview['digest'],'2026-10-01',$this->actor->id); $this->fail('Deleted actor accepted.'); }
        catch (\Illuminate\Auth\Access\AuthorizationException $e) { $this->assertNotEmpty($e->getMessage()); }
        $this->assertSame(0,DB::table('erp_migration_batches')->count());
    }

    public function test_explicit_status_and_zero_opening_are_preserved_and_missing_values_block(): void
    {
        $data=$this->data();
        foreach ([0,1,2] as $index) {
            $bad=$data; unset($bad['rows'][$index]['status']);
            $this->assertFalse($this->service->preview($bad['rows'],$bad['references'])['valid']);
        }
        $bad=$data; array_splice($bad['rows'],3,1);
        $this->assertSame('explicit_student_opening_balance_required',$this->service->preview($bad['rows'],$bad['references'])['issues'][0]['code']);
        $data['rows'][0]['status']='paused'; $data['rows'][1]['status']='inactive';
        $data['rows'][2]['status']='ended'; $data['rows'][2]['ends_on']='2026-09-30'; $data['rows'][3]['amount_paise']=0;
        $this->execute($data);
        $this->assertDatabaseHas('erp_students',['code'=>'SYN-S1','status'=>'paused']);
        $this->assertDatabaseHas('erp_teachers',['code'=>'SYN-T1','status'=>'inactive']);
        $this->assertDatabaseHas('erp_enrollments',['status'=>'ended','ends_on'=>'2026-09-30']);
        $this->assertDatabaseHas('erp_ledger',['kind'=>'opening_balance','amount_paise'=>0]);
    }

    public function test_imported_rates_obey_operational_maximum_not_database_integer_limit(): void
    {
        foreach (['student_rate_paise','teacher_rate_paise'] as $field) {
            $data=$this->data(); $data['rows'][2][$field]=100000001;
            $preview=$this->service->preview($data['rows'],$data['references']);
            $this->assertFalse($preview['valid']);
            $this->assertSame('rate_exceeds_operational_limit',$preview['issues'][0]['code']);
            $data['rows'][2][$field]=100000000;
            $this->assertTrue($this->service->preview($data['rows'],$data['references'])['valid']);
        }
        $this->assertSame(0,DB::table('erp_migration_batches')->count());
    }

}
