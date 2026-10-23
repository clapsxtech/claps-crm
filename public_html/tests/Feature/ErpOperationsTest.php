<?php
namespace Tests\Feature;

use App\Modules\Erp\Services\Operations;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ErpOperationsTest extends TestCase
{
    private $service; private $admin; private $staff; private $unit; private $other; private $student; private $task;
    protected function setUp(): void {
        parent::setUp(); $this->service=new Operations(); $this->admin=$this->createAdmin(); $this->staff=$this->createMember();
        $this->unit=DB::table('erp_units')->insertGetId(['code'=>'OPS-AA','name'=>'Synthetic AA','timezone'=>'UTC']);
        $this->other=DB::table('erp_units')->insertGetId(['code'=>'OPS-BB','name'=>'Synthetic BB','timezone'=>'UTC']);
        DB::table('erp_memberships')->insert(['unit_id'=>$this->unit,'user_id'=>$this->admin->id,'role'=>'admin']);
        $this->student=DB::table('erp_students')->insertGetId(['unit_id'=>$this->unit,'code'=>'OPS-S1','name'=>'Synthetic Student','registered_on'=>'2026-01-01','timezone'=>'UTC','status'=>'active','grade'=>'8','syllabus'=>'Synthetic']);
        $this->task=DB::table('erp_tasks')->insertGetId(['unit_id'=>$this->unit,'student_id'=>$this->student,'title'=>'Synthetic follow-up','status'=>'open','created_by'=>$this->admin->id]);
    }
    private function invalid(callable $call): void { try { $call(); $this->fail('Expected domain validation failure.'); } catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); } }
    private function denied(callable $call): void { try { $call(); $this->fail('Expected authorization failure.'); } catch (AuthorizationException $e) { $this->assertNotEmpty($e->getMessage()); } }
    public function test_profile_updates_are_audited_scoped_and_do_not_delete(): void {
        $row=$this->service->updateStudent($this->unit,$this->admin->id,['student_id'=>$this->student,'name'=>'Updated Synthetic Student','guardian_contact'=>'Synthetic guardian','meeting_url'=>'https://example.invalid/lesson','status'=>'paused']);
        $this->assertSame('paused',$row->status); $this->assertSame('Synthetic guardian',$row->guardian_contact);
        $audit=json_decode(DB::table('erp_audit')->where('action','student.updated')->value('payload'),true); $this->assertSame('active',$audit['changes']['status']['from']);
        $this->assertSame(1,DB::table('erp_students')->count());
        $this->denied(fn()=> $this->service->updateStudent($this->other,$this->admin->id,['student_id'=>$this->student,'status'=>'stopped']));
        DB::table('erp_memberships')->insert(['unit_id'=>$this->other,'user_id'=>$this->admin->id,'role'=>'admin']);
        $this->invalid(fn()=> $this->service->updateStudent($this->other,$this->admin->id,['student_id'=>$this->student,'status'=>'stopped']));
        $this->invalid(fn()=> $this->service->updateStudent($this->unit,$this->admin->id,['student_id'=>$this->student,'meeting_url'=>'javascript:alert(1)']));
    }
    public function test_profile_scheduling_history_and_future_lesson_guards(): void {
        DB::table('erp_availability')->insert(['unit_id'=>$this->unit,'student_id'=>$this->student,'weekday'=>1,'start_time'=>'10:00','end_time'=>'11:00','timezone'=>'UTC','starts_on'=>'2026-01-01']);
        $this->invalid(fn()=> $this->service->updateStudent($this->unit,$this->admin->id,['student_id'=>$this->student,'timezone'=>'Asia/Kolkata']));
        DB::table('erp_lessons')->insert(['unit_id'=>$this->unit,'student_id'=>$this->student,'teacher_id'=>1,'enrollment_id'=>1,'subject_id'=>1,'starts_at'=>now()->addDay(),'ends_at'=>now()->addDay()->addHour(),'status'=>'scheduled','kind'=>'regular','student_rate_paise'=>1,'teacher_rate_paise'=>1,'currency'=>'INR']);
        $this->invalid(fn()=> $this->service->updateStudent($this->unit,$this->admin->id,['student_id'=>$this->student,'status'=>'stopped']));
        $this->assertSame('active',DB::table('erp_students')->where('id',$this->student)->value('status'));
    }
    public function test_task_assignment_checklists_and_completion_are_connected(): void {
        $this->service->grantMembership($this->unit,$this->admin->id,['user_id'=>$this->staff->id,'role'=>'sales']);
        $task=$this->service->updateTask($this->unit,$this->admin->id,['task_id'=>$this->task,'assigned_to'=>$this->staff->id,'due_on'=>'2026-11-01','notes'=>'Synthetic follow-up details','status'=>'in_progress']);
        $this->assertSame('in_progress',$task->status); $this->assertSame($this->staff->id,(int)$task->assigned_to);
        $item=$this->service->addTaskItem($this->unit,$this->admin->id,['task_id'=>$this->task,'title'=>'Record parent response']);
        $this->invalid(fn()=> $this->service->updateTask($this->unit,$this->admin->id,['task_id'=>$this->task,'status'=>'completed']));
        $done=$this->service->completeTaskItem($this->unit,$this->admin->id,['item_id'=>$item->id]); $this->assertSame($this->admin->id,(int)$done->completed_by);
        $this->service->completeTaskItem($this->unit,$this->admin->id,['item_id'=>$item->id]);
        $this->assertSame(1,DB::table('erp_audit')->where('action','task.item_completed')->count());
        $this->assertSame('completed',$this->service->updateTask($this->unit,$this->admin->id,['task_id'=>$this->task,'status'=>'completed'])->status);
        $this->invalid(fn()=> $this->service->addTaskItem($this->unit,$this->admin->id,['task_id'=>$this->task,'title'=>'Cannot add to closed task']));
        $this->service->updateTask($this->unit,$this->admin->id,['task_id'=>$this->task,'status'=>'open','assigned_to'=>null]);
        $this->assertNull(DB::table('erp_tasks')->where('id',$this->task)->value('assigned_to'));
    }
    public function test_nonmember_task_assignee_and_crossunit_checklist_are_rejected(): void {
        $this->invalid(fn()=> $this->service->updateTask($this->unit,$this->admin->id,['task_id'=>$this->task,'assigned_to'=>$this->staff->id]));
        DB::table('erp_memberships')->insert(['unit_id'=>$this->other,'user_id'=>$this->admin->id,'role'=>'admin']);
        $item=$this->service->addTaskItem($this->unit,$this->admin->id,['task_id'=>$this->task,'title'=>'Synthetic item']);
        $this->invalid(fn()=> $this->service->completeTaskItem($this->other,$this->admin->id,['item_id'=>$item->id]));
        $this->assertNull(DB::table('erp_task_items')->where('id',$item->id)->value('completed_at'));
    }
    public function test_followup_history_is_recorded_without_outbound_or_financial_effects(): void {
        $row=$this->service->recordFollowup($this->unit,$this->admin->id,['student_id'=>$this->student,'contact_channel'=>'whatsapp','note'=>'Synthetic administrator-recorded conversation','outcome'=>'Parent requested a call','next_due_on'=>'2026-11-01']);
        $this->assertSame('whatsapp',$row->contact_channel); $this->assertSame($this->admin->id,(int)$row->created_by);
        $audit=json_decode(DB::table('erp_audit')->where('action','followup.recorded')->value('payload'),true); $this->assertFalse($audit['outbound_sent']);
        $this->assertSame(0,DB::table('erp_ledger')->count());
    }
    public function test_membership_changes_require_current_admin_and_active_site_target(): void {
        $this->denied(fn()=> $this->service->grantMembership($this->unit,$this->staff->id,['user_id'=>$this->staff->id,'role'=>'admin']));
        $outside=$this->createMember(['business_id'=>2]);
        $this->denied(fn()=> $this->service->grantMembership($this->unit,$this->admin->id,['user_id'=>$outside->id,'role'=>'sales']));
        $outside->forceFill(['business_id'=>1,'status'=>0])->save();
        $this->denied(fn()=> $this->service->grantMembership($this->unit,$this->admin->id,['user_id'=>$outside->id,'role'=>'sales']));
        $this->service->grantMembership($this->unit,$this->admin->id,['user_id'=>$this->staff->id,'role'=>'sales']);
        $this->denied(fn()=> $this->service->grantMembership($this->unit,$this->staff->id,['user_id'=>$this->staff->id,'role'=>'admin']));
        $this->assertSame('sales',DB::table('erp_memberships')->where('user_id',$this->staff->id)->value('role'));
    }
    public function test_self_removal_and_last_admin_preserved_and_other_staff_can_be_revoked(): void {
        $this->invalid(fn()=> $this->service->revokeMembership($this->unit,$this->admin->id,['user_id'=>$this->admin->id]));
        $this->invalid(fn()=> $this->service->grantMembership($this->unit,$this->admin->id,['user_id'=>$this->admin->id,'role'=>'sales']));
        $this->service->grantMembership($this->unit,$this->admin->id,['user_id'=>$this->staff->id,'role'=>'admin']);
        $this->service->grantMembership($this->unit,$this->admin->id,['user_id'=>$this->staff->id,'role'=>'sales']);
        $result=$this->service->revokeMembership($this->unit,$this->admin->id,['user_id'=>$this->staff->id]); $this->assertTrue($result['revoked']);
        $this->assertSame(1,DB::table('erp_memberships')->where('unit_id',$this->unit)->where('role','admin')->count());
        $this->assertDatabaseHas('erp_audit',['action'=>'membership.revoked','actor_id'=>$this->admin->id]);
    }
    public function test_teacher_pool_and_active_account_link_prevent_unsafe_membership_changes(): void {
        DB::table('erp_memberships')->insert(['unit_id'=>$this->other,'user_id'=>$this->admin->id,'role'=>'admin']);
        $this->service->grantMembership($this->unit,$this->admin->id,['user_id'=>$this->staff->id,'role'=>'teacher']);
        $this->invalid(fn()=> $this->service->grantMembership($this->other,$this->admin->id,['user_id'=>$this->staff->id,'role'=>'teacher']));
        $this->invalid(fn()=> $this->service->grantMembership($this->other,$this->admin->id,['user_id'=>$this->staff->id,'role'=>'sales']));
        $teacher=DB::table('erp_teachers')->insertGetId(['user_id'=>$this->staff->id,'code'=>'OPS-T','name'=>'Linked teacher','timezone'=>'UTC','status'=>'active']);
        DB::table('erp_teacher_units')->insert(['teacher_id'=>$teacher,'unit_id'=>$this->unit]);
        $this->invalid(fn()=> $this->service->grantMembership($this->unit,$this->admin->id,['user_id'=>$this->staff->id,'role'=>'admin']));
        $this->invalid(fn()=> $this->service->revokeMembership($this->unit,$this->admin->id,['user_id'=>$this->staff->id]));
        $this->assertSame('teacher',DB::table('erp_memberships')->where('user_id',$this->staff->id)->value('role'));
    }
    public function test_existing_teacher_link_requires_explicit_membership_and_cannot_replace_identity(): void {
        $teacher=DB::table('erp_teachers')->insertGetId(['code'=>'OPS-LINK','name'=>'Synthetic teacher','timezone'=>'UTC','status'=>'active']);
        DB::table('erp_teacher_units')->insert(['teacher_id'=>$teacher,'unit_id'=>$this->unit]);
        $payload=['teacher_id'=>$teacher,'user_id'=>$this->staff->id];
        $this->invalid(fn()=> $this->service->linkTeacherUser($this->unit,$this->admin->id,$payload));
        $this->service->grantMembership($this->unit,$this->admin->id,['user_id'=>$this->staff->id,'role'=>'teacher']);
        $linked=$this->service->linkTeacherUser($this->unit,$this->admin->id,$payload); $this->assertSame($this->staff->id,(int)$linked->user_id);
        $this->service->linkTeacherUser($this->unit,$this->admin->id,$payload);
        $this->assertSame(1,DB::table('erp_audit')->where('action','teacher.account_linked')->count());
        $another=$this->createMember(); $this->service->grantMembership($this->unit,$this->admin->id,['user_id'=>$another->id,'role'=>'teacher']);
        $this->invalid(fn()=> $this->service->linkTeacherUser($this->unit,$this->admin->id,['teacher_id'=>$teacher,'user_id'=>$another->id]));
        $second=DB::table('erp_teachers')->insertGetId(['code'=>'OPS-LINK2','name'=>'Second teacher','timezone'=>'UTC','status'=>'active']);
        DB::table('erp_teacher_units')->insert(['teacher_id'=>$second,'unit_id'=>$this->unit]);
        $this->invalid(fn()=> $this->service->linkTeacherUser($this->unit,$this->admin->id,['teacher_id'=>$second,'user_id'=>$this->staff->id]));
    }

    public function test_revocation_can_clean_up_inactive_deleted_and_outside_site_accounts(): void {
        foreach ([['status'=>0],['deleted_at'=>now()],['business_id'=>2]] as $changes) {
            $user=$this->createMember();
            $this->service->grantMembership($this->unit,$this->admin->id,['user_id'=>$user->id,'role'=>'sales']);
            $user->forceFill($changes)->save();
            $this->assertTrue($this->service->revokeMembership($this->unit,$this->admin->id,['user_id'=>$user->id])['revoked']);
            $this->assertSame(0,DB::table('erp_memberships')->where('unit_id',$this->unit)->where('user_id',$user->id)->count());
            $this->denied(fn()=> $this->service->grantMembership($this->unit,$this->admin->id,['user_id'=>$user->id,'role'=>'sales']));
        }
    }
    public function test_explicit_teacher_unlink_requires_reason_but_preserves_future_lessons(): void {
        $this->service->grantMembership($this->unit,$this->admin->id,['user_id'=>$this->staff->id,'role'=>'teacher']);
        $teacher=DB::table('erp_teachers')->insertGetId(['user_id'=>$this->staff->id,'code'=>'OPS-UNLINK','name'=>'Synthetic teacher','timezone'=>'UTC','status'=>'active']);
        DB::table('erp_teacher_units')->insert(['teacher_id'=>$teacher,'unit_id'=>$this->unit]);
        $this->invalid(fn()=> $this->service->unlinkTeacherUser($this->unit,$this->admin->id,['teacher_id'=>$teacher,'reason'=>'']));
        $lesson=DB::table('erp_lessons')->insertGetId(['unit_id'=>$this->unit,'student_id'=>$this->student,'teacher_id'=>$teacher,'enrollment_id'=>1,'subject_id'=>1,'starts_at'=>now()->addDay(),'ends_at'=>now()->addDay()->addHour(),'status'=>'scheduled','kind'=>'regular','student_rate_paise'=>1,'teacher_rate_paise'=>1,'currency'=>'INR']);
        $unlinked=$this->service->unlinkTeacherUser($this->unit,$this->admin->id,['teacher_id'=>$teacher,'reason'=>'Revoke departed staff account']);
        $this->assertNull($unlinked->user_id); $this->assertSame(1,DB::table('erp_teachers')->where('id',$teacher)->count());
        $this->assertSame('scheduled',DB::table('erp_lessons')->where('id',$lesson)->value('status'));
        $this->assertSame($teacher,(int)DB::table('erp_lessons')->where('id',$lesson)->value('teacher_id'));
        $audit=json_decode(DB::table('erp_audit')->where('action','teacher.account_unlinked')->value('payload'),true);
        $this->assertSame($this->staff->id,$audit['previous_user_id']); $this->assertSame('Revoke departed staff account',$audit['reason']);
        $this->staff->forceFill(['status'=>0])->save();
        $this->assertTrue($this->service->revokeMembership($this->unit,$this->admin->id,['user_id'=>$this->staff->id])['revoked']);
    }
    public function test_legacy_task_completion_route_cannot_bypass_checklist_guard(): void {
        $item=$this->service->addTaskItem($this->unit,$this->admin->id,['task_id'=>$this->task,'title'=>'Required checklist work']);
        $this->actingAs($this->admin)->postJson('/erp/units/'.$this->unit.'/complete-task',['task_id'=>$this->task])->assertUnprocessable();
        $this->assertSame('open',DB::table('erp_tasks')->where('id',$this->task)->value('status'));
        $this->service->completeTaskItem($this->unit,$this->admin->id,['item_id'=>$item->id]);
        $this->postJson('/erp/units/'.$this->unit.'/complete-task',['task_id'=>$this->task])->assertOk();
        $this->postJson('/erp/units/'.$this->unit.'/complete-task',['task_id'=>$this->task])->assertOk();
        $this->assertSame(1,DB::table('erp_audit')->where('action','task.completed')->where('entity_id',$this->task)->count());
    }

}
