<?php
namespace Tests\Feature;

use App\Modules\Erp\Services\TeacherRecruitment;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ErpTeacherDataReviewTest extends TestCase
{
    public function test_review_correction_and_merge_preserve_sources_and_cannot_merge_operational_teachers():void
    {
        $admin=$this->createAdmin();$unit=DB::table('erp_units')->insertGetId(['code'=>'REVIEW','name'=>'Review unit','timezone'=>'UTC']);
        $s=new TeacherRecruitment();
        $a=$s->mutate($unit,$admin,'teacher-candidate-create',['name'=>'First Identity','phone'=>'9000001234']);
        $b=$s->mutate($unit,$admin,'teacher-candidate-create',['name'=>'Second Identity','phone'=>'9000004321']);
        DB::table('erp_teacher_candidates')->where('id',$a->id)->update(['stage'=>'REVIEW']);
        DB::table('erp_teacher_candidate_sources')->insert(['candidate_id'=>$a->id,'kind'=>'application','source_key'=>'review-test','payload'=>'{"name":"Original application"}']);
        $url=route('erp.business.mutate',[$unit,'teacher-candidate-resolve']);$this->actingAs($admin);
        $this->postJson(route('erp.business.mutate',[$unit,'teacher-candidate-update']),['candidate_id'=>$a->id,'stage'=>'SELECTED'])->assertStatus(422);
        $this->postJson($url,['candidate_id'=>$a->id,'decision'=>'correct','name'=>'First Identity','phone'=>'9000099999','note'=>'Contact confirmed directly.'])->assertOk();
        $this->assertDatabaseHas('erp_teacher_candidates',['id'=>$a->id,'phone'=>'919000099999','stage'=>'APPLIED','reviewed_by'=>$admin->id]);
        $this->postJson($url,['candidate_id'=>$a->id,'decision'=>'merge','target_id'=>$b->id,'note'=>'Same person verified with both response records.'])->assertOk();
        $this->assertDatabaseHas('erp_teacher_candidates',['id'=>$a->id,'stage'=>'MERGED','merged_into_id'=>$b->id]);
        $this->assertDatabaseHas('erp_teacher_candidate_sources',['source_key'=>'review-test','candidate_id'=>$a->id]);
        $this->get(route('erp.business',[$unit,'teacher-recruitment','candidate'=>$b->id]))->assertOk()->assertSee('Original application');
        $this->postJson($url,['candidate_id'=>$a->id,'decision'=>'correct','name'=>'Changed','phone'=>'9000099999','note'=>'Retry'])->assertStatus(422);
        $c=$s->mutate($unit,$admin,'teacher-candidate-create',['name'=>'Third Identity','phone'=>'9000009876']);
        foreach([$b,$c] as $index=>$candidate){$teacher=DB::table('erp_teachers')->insertGetId(['code'=>'REVIEW-'.$index,'name'=>$candidate->name,'timezone'=>'UTC','status'=>'active']);DB::table('erp_teacher_candidates')->where('id',$candidate->id)->update(['teacher_id'=>$teacher]);}
        $this->postJson($url,['candidate_id'=>$c->id,'decision'=>'merge','target_id'=>$b->id,'note'=>'Should never merge two operational teachers.'])->assertStatus(422);
        $this->assertDatabaseCount('erp_teachers',2);
    }
}
