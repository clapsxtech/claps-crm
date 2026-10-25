<?php
namespace Tests\Feature;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class MigrationCenterTest extends TestCase
{
    private function source(): array
    {
        return ['rows'=>[
            ['type'=>'student','external_id'=>'M-S1','unit_code'=>'M1','name'=>'Migration Student','timezone'=>'UTC','registered_on'=>'2026-01-01','grade'=>'8','syllabus'=>'Synthetic','status'=>'active'],
            ['type'=>'opening_balance','external_id'=>'M-B1','unit_code'=>'M1','owner_type'=>'student','owner_id'=>'M-S1','amount_paise'=>-10000,'currency'=>'INR','as_of'=>'2026-10-01'],
        ],'references'=>['schema_version'=>2,'target_database'=>DB::connection()->getDatabaseName(),'unit_codes'=>['M1'],'subject_codes'=>[],'subject_ids'=>[],'subject_names'=>[],'cutoff_date'=>'2026-10-01','source_manifest'=>['source_id'=>'M-SOURCE','exported_at'=>'2026-10-01T00:00:00Z'],'teacher_eligibilities'=>[]]];
    }
    private function upload(array $data): UploadedFile {return UploadedFile::fake()->createWithContent('source.json',json_encode($data));}
    public function test_preview_and_password_confirmed_exact_snapshot_import(): void
    {
        $this->actingAs($this->createAdmin());DB::table('erp_units')->insert(['code'=>'M1','name'=>'Migration','timezone'=>'UTC']);$data=$this->source();
        $response=$this->postJson(route('erp.migration.preview'),['source'=>$this->upload($data)])->assertOk()->assertJsonPath('valid',true);
        $this->assertDatabaseCount('erp_students',0);
        $approval=['digest'=>$response->json('digest'),'cutoff'=>'2026-10-01','backup_reference'=>'Synthetic verified backup','current_password'=>'Local-Testing-Only-2026','confirm_import'=>1];
        $changed=$data;$changed['rows'][0]['name']='Tampered';
        $this->postJson(route('erp.migration.execute'),$approval+['source'=>$this->upload($changed)])->assertUnprocessable();
        $this->assertDatabaseCount('erp_students',0);
        $this->postJson(route('erp.migration.execute'),array_merge($approval,['source'=>$this->upload($data),'current_password'=>'wrong']))->assertUnprocessable();
        $this->postJson(route('erp.migration.execute'),$approval+['source'=>$this->upload($data)])->assertOk();
        $this->assertDatabaseCount('erp_students',1);$this->assertDatabaseCount('erp_ledger',1);
        $this->assertDatabaseHas('erp_audit',['action'=>'migration.approved_import']);
        $this->assertFalse((bool)config('erp.migration_import_enabled'));
        $this->postJson(route('erp.migration.execute'),$approval+['source'=>$this->upload($data)])->assertUnprocessable();
    }
    public function test_unpreviewed_expired_and_non_admin_imports_are_denied(): void
    {
        $admin=$this->createAdmin();DB::table('erp_units')->insert(['code'=>'M1','name'=>'Migration','timezone'=>'UTC']);$data=$this->source();
        $this->actingAs($admin)->postJson(route('erp.migration.execute'),['source'=>$this->upload($data),'digest'=>str_repeat('a',64),'cutoff'=>'2026-10-01','backup_reference'=>'Backup','current_password'=>'Local-Testing-Only-2026','confirm_import'=>1])->assertUnprocessable();
        $this->postJson(route('erp.migration.preview'),['source'=>UploadedFile::fake()->createWithContent('bad.json','not JSON')])->assertUnprocessable();
        $this->actingAs($this->createMember())->postJson(route('erp.migration.preview'),['source'=>$this->upload($data)])->assertForbidden();
        $this->assertDatabaseCount('erp_students',0);
    }
}
