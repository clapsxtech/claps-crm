<?php
namespace Tests\Feature;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class ErpTuitionHttpTest extends TestCase
{
    public function test_private_screen_renders_and_rejects_other_roles_and_units(): void
    {
        $admin=$this->createAdmin();
        $unit=DB::table('erp_units')->insertGetId(['code'=>'TUITION','name'=>'Tuition team','timezone'=>'UTC']);
        $other=DB::table('erp_units')->insertGetId(['code'=>'TUITION-OTHER','name'=>'Other team','timezone'=>'UTC']);
        $this->actingAs($admin)->get(route('erp.tuition',$unit))->assertOk()->assertSee('Teacher recruitment');
        $student=DB::table('erp_students')->insertGetId(['unit_id'=>$unit,'code'=>'PARENT-TEST','name'=>'Statement Student','registered_on'=>'2026-01-01','timezone'=>'Asia/Kolkata','status'=>'active','grade'=>'8','syllabus'=>'State']);
        $teacher=DB::table('erp_teachers')->insertGetId(['code'=>'PARENT-TEACHER','name'=>'Statement Teacher','timezone'=>'UTC','status'=>'active']);
        $subject=DB::table('erp_subjects')->insertGetId(['name'=>'Maths']);
        $enrollment=DB::table('erp_enrollments')->insertGetId(['unit_id'=>$unit,'student_id'=>$student,'teacher_id'=>$teacher,'subject_id'=>$subject,'starts_on'=>'2026-01-01','student_rate_paise'=>12000,'teacher_rate_paise'=>6000,'currency'=>'INR','status'=>'active']);
        $lesson=DB::table('erp_lessons')->insertGetId(['unit_id'=>$unit,'enrollment_id'=>$enrollment,'student_id'=>$student,'teacher_id'=>$teacher,'subject_id'=>$subject,'starts_at'=>'2026-10-20 10:00:00','ends_at'=>'2026-10-20 11:00:00','kind'=>'regular','status'=>'completed','student_rate_paise'=>12000,'teacher_rate_paise'=>6000,'currency'=>'INR']);
        $attendance=DB::table('erp_attendance')->insertGetId(['unit_id'=>$unit,'lesson_id'=>$lesson,'actual_minutes'=>45,'actual_starts_at'=>'2026-10-20 10:00:00','status'=>'submitted','submitted_by'=>$admin->id,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('erp_internal_notes')->insert(['unit_id'=>$unit,'lesson_id'=>$lesson,'created_by'=>$admin->id,'kind'=>'internal','note'=>'Private staff observation','created_at'=>now(),'updated_at'=>now()]);
        $this->get(route('erp.tuition',$unit))->assertOk()->assertSee('Statement Student')->assertSee('Private staff observation');
        $this->get(route('erp.tuition.statement',[$unit,'student_id'=>$student,'month'=>'2026-10']))->assertOk()->assertSee('Statement Teacher')->assertSee('90.00')->assertDontSee('Private staff observation');
        $this->get(route('erp.tuition.statement',[$other,'student_id'=>$student,'month'=>'2026-10']))->assertNotFound();
        $snapshot=\App\Modules\Erp\Services\TuitionWorkflows::reviewSnapshot(DB::table('erp_attendance')->find($attendance));
        DB::table('erp_attendance')->where('id',$attendance)->update(['actual_minutes'=>30]);
        $this->postJson(route('erp.tuition.mutate',[$unit,'monthly-review']),['attendance_ids'=>[$attendance],'snapshots'=>[$attendance=>$snapshot],'deadline_at'=>now('UTC')->addDays(2)->format('Y-m-d H:i:s'),'note'=>'Shared statement'])->assertUnprocessable();
        $this->assertSame('unshared',DB::table('erp_attendance')->find($attendance)->parent_review_status);
        $candidate=['name'=>'Private Applicant','contact'=>'applicant@example.invalid','qualification'=>'MSc','subjects'=>'Maths','levels'=>'High school'];
        $this->postJson(route('erp.tuition.mutate',[$unit,'candidate']),$candidate)->assertOk();
        $this->get(route('erp.tuition',$unit))->assertOk()->assertSee('Private Applicant');
        $this->get(route('erp.tuition',$other))->assertOk()->assertDontSee('Private Applicant');
        foreach(['teacher','read_only','finance','academics','sales_followups'] as $role) {
            $user=$this->createMember(); DB::table('erp_memberships')->insert(['unit_id'=>$unit,'user_id'=>$user->id,'role'=>$role]);
            $this->actingAs($user)->get(route('erp.tuition',$unit))->assertForbidden();
            $this->postJson(route('erp.tuition.mutate',[$unit,'candidate']),$candidate)->assertForbidden();
        }
        $manager=$this->createMember(); DB::table('erp_memberships')->insert(['unit_id'=>$unit,'user_id'=>$manager->id,'role'=>'team_manager']);
        $this->actingAs($manager)->get(route('erp.tuition',$unit))->assertOk();
        $this->get(route('erp.tuition',$other))->assertForbidden();
        $this->assertSame(1,DB::table('erp_candidates')->count());
    }
}
