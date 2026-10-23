<?php
namespace Tests\Feature;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ErpOwnTasksHttpTest extends TestCase
{
    private $sales; private $teacher; private $unit; private $other; private $own; private $theirs; private $cross; private $item; private $otherItem;
    protected function setUp(): void {
        parent::setUp(); $this->sales=$this->createMember(['name'=>'Synthetic Sales']); $this->teacher=$this->createMember(['name'=>'Synthetic Teacher']);
        $this->unit=DB::table('erp_units')->insertGetId(['code'=>'OWN-AA','name'=>'Synthetic unit']);
        $this->other=DB::table('erp_units')->insertGetId(['code'=>'OWN-BB','name'=>'Other unit']);
        DB::table('erp_memberships')->insert([['unit_id'=>$this->unit,'user_id'=>$this->sales->id,'role'=>'sales'],['unit_id'=>$this->unit,'user_id'=>$this->teacher->id,'role'=>'teacher']]);
        $this->own=DB::table('erp_tasks')->insertGetId(['unit_id'=>$this->unit,'assigned_to'=>$this->sales->id,'title'=>'My sales task','notes'=>'Assigned task instructions','status'=>'in_progress','created_by'=>$this->sales->id]);
        $this->theirs=DB::table('erp_tasks')->insertGetId(['unit_id'=>$this->unit,'assigned_to'=>$this->teacher->id,'title'=>'Other assignee secret','notes'=>'Other task notes','status'=>'open','created_by'=>$this->sales->id]);
        $this->cross=DB::table('erp_tasks')->insertGetId(['unit_id'=>$this->other,'assigned_to'=>$this->sales->id,'title'=>'Unauthorized unit task','status'=>'open','created_by'=>$this->sales->id]);
        $this->item=DB::table('erp_task_items')->insertGetId(['unit_id'=>$this->unit,'task_id'=>$this->own,'title'=>'My checklist item']);
        $this->otherItem=DB::table('erp_task_items')->insertGetId(['unit_id'=>$this->unit,'task_id'=>$this->theirs,'title'=>'Other assignee checklist']);
        DB::table('erp_task_items')->insert(['unit_id'=>$this->other,'task_id'=>$this->own,'title'=>'Corrupt cross-unit checklist']);
        DB::table('erp_students')->insert(['unit_id'=>$this->unit,'code'=>'HIDDEN-STUDENT','name'=>'Sensitive student name','registered_on'=>'2026-01-01','timezone'=>'UTC','status'=>'active','grade'=>'8','syllabus'=>'Test','guardian_contact'=>'PRIVATE-GUARDIAN-CONTACT']);
        DB::table('erp_ledger')->insert(['unit_id'=>$this->unit,'kind'=>'payment','amount_paise'=>99988,'currency'=>'INR','reference'=>'PRIVATE-FINANCE-REFERENCE','event_key'=>'own-http-private','occurred_at'=>now(),'created_by'=>$this->sales->id]);
    }
    public function test_sales_sees_only_own_tasks_without_people_finance_or_other_assignment_data(): void {
        $this->actingAs($this->sales)->get(route('erp.tasks.mine'))->assertOk()->assertSee('My sales task')->assertSee('My checklist item')->assertSee('Assigned task instructions')->assertDontSee('Other assignee secret')->assertDontSee('Other assignee checklist')->assertDontSee('Unauthorized unit task')->assertDontSee('Corrupt cross-unit checklist')->assertDontSee('Sensitive student name')->assertDontSee('PRIVATE-GUARDIAN-CONTACT')->assertDontSee('PRIVATE-FINANCE-REFERENCE')->assertDontSee('99988');
    }
    public function test_own_checklist_completion_gates_task_completion_and_is_audited_idempotently(): void {
        $this->actingAs($this->sales);
        $this->from(route('erp.tasks.mine'))->post(route('erp.tasks.complete',$this->own))->assertRedirect(route('erp.tasks.mine'))->assertSessionHasErrors();
        $this->post(route('erp.tasks.item.complete',[$this->own,$this->item]))->assertRedirect(route('erp.tasks.mine'))->assertSessionHasNoErrors();
        $this->post(route('erp.tasks.item.complete',[$this->own,$this->item]))->assertRedirect(route('erp.tasks.mine'));
        $this->post(route('erp.tasks.complete',$this->own))->assertRedirect(route('erp.tasks.mine'));
        $this->post(route('erp.tasks.complete',$this->own))->assertRedirect(route('erp.tasks.mine'));
        $this->assertDatabaseHas('erp_tasks',['id'=>$this->own,'status'=>'completed']);
        $this->assertDatabaseHas('erp_task_items',['id'=>$this->item,'completed_by'=>$this->sales->id]);
        $this->assertSame(1,DB::table('erp_audit')->where('action','task.item_completed')->where('entity_id',$this->item)->count());
        $this->assertSame(1,DB::table('erp_audit')->where('action','task.completed')->where('entity_id',$this->own)->count());
    }
    public function test_other_assignee_crossunit_and_swapped_checklist_ids_are_forbidden(): void {
        $this->actingAs($this->sales);
        $this->post(route('erp.tasks.complete',$this->theirs))->assertForbidden();
        $this->post(route('erp.tasks.complete',$this->cross))->assertForbidden();
        $this->post(route('erp.tasks.item.complete',[$this->own,$this->otherItem]))->assertForbidden();
        $this->post(route('erp.tasks.item.complete',[$this->theirs,$this->otherItem]))->assertForbidden();
        DB::table('erp_tasks')->where('id',$this->own)->update(['assigned_to'=>$this->teacher->id]);
        $this->post(route('erp.tasks.item.complete',[$this->own,$this->item]))->assertForbidden();
        $this->assertSame(0,DB::table('erp_task_items')->whereNotNull('completed_at')->count());
    }
    public function test_teacher_can_complete_own_task_but_membership_revocation_blocks_access(): void {
        $this->actingAs($this->teacher)->get(route('erp.tasks.mine'))->assertOk()->assertSee('Other assignee secret')->assertDontSee('My sales task');
        $this->post(route('erp.tasks.item.complete',[$this->theirs,$this->otherItem]))->assertRedirect(route('erp.tasks.mine'));
        $this->post(route('erp.tasks.complete',$this->theirs))->assertRedirect(route('erp.tasks.mine'));
        DB::table('erp_memberships')->where('user_id',$this->teacher->id)->delete();
        $this->get(route('erp.tasks.mine'))->assertForbidden(); $this->post(route('erp.tasks.complete',$this->theirs))->assertForbidden();
    }
    public function test_deleted_wrong_site_and_guest_access_is_denied(): void {
        $this->sales->forceFill(['deleted_at'=>now()])->save(); $this->actingAs($this->sales)->get(route('erp.tasks.mine'))->assertForbidden();
        $this->post(route('erp.tasks.complete',$this->own))->assertForbidden();
        $this->sales->forceFill(['deleted_at'=>null,'business_id'=>2])->save(); $this->actingAs($this->sales)->get(route('erp.tasks.mine'))->assertForbidden();
        $this->app['auth']->guard()->logout(); $this->get(route('erp.tasks.mine'))->assertRedirect(route('login'));
        $this->post(route('erp.tasks.complete',$this->own))->assertRedirect(route('login'));
    }
    public function test_admin_own_task_portal_remains_assignment_only(): void {
        $admin=$this->createAdmin(); DB::table('erp_memberships')->insert(['unit_id'=>$this->unit,'user_id'=>$admin->id,'role'=>'admin']);
        $task=DB::table('erp_tasks')->insertGetId(['unit_id'=>$this->unit,'assigned_to'=>$admin->id,'title'=>'My administrator assignment','status'=>'open','created_by'=>$admin->id]);
        $this->actingAs($admin)->get(route('erp.tasks.mine'))->assertOk()->assertSee('My administrator assignment')->assertDontSee('My sales task')->assertDontSee('Other assignee secret')->assertDontSee('PRIVATE-FINANCE-REFERENCE');
        $this->post(route('erp.tasks.complete',$this->own))->assertForbidden();
        $this->post(route('erp.tasks.complete',$task))->assertRedirect(route('erp.tasks.mine'));
        $this->assertDatabaseHas('erp_tasks',['id'=>$task,'status'=>'completed']);
    }

}
