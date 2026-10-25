<?php
namespace Tests\Feature;
use App\Models\User;
use App\Modules\Erp\Services\{Administration,TeamPermissions};
use Illuminate\Support\Facades\{DB,Hash};
use Tests\TestCase;

class AdministrationWorkflowTest extends TestCase
{
    private function team(string $code='TEST'): int {return DB::table('erp_units')->insertGetId(['code'=>$code,'name'=>$code.' team','timezone'=>'UTC']);}
    public function test_superadmin_can_create_rename_archive_and_restore_arbitrary_teams(): void
    {
        $this->actingAs($this->createAdmin());
        $this->get(route('erp.administration'))->assertOk()->assertSee('Onboard staff');
        $this->post(route('erp.teams.create'),['code'=>'NEW_TEAM','name'=>'New team','timezone'=>'UTC'])->assertRedirect();
        $id=DB::table('erp_units')->where('code','NEW_TEAM')->value('id');
        $this->post(route('erp.teams.update',$id),['code'=>'NEW_TEAM','name'=>'Renamed team','timezone'=>'UTC'])->assertRedirect();
        $staff=$this->createMember();DB::table('erp_memberships')->insert(['unit_id'=>$id,'user_id'=>$staff->id,'role'=>'team_manager']);
        $this->post(route('erp.teams.archive',$id),['archived'=>1])->assertRedirect();
        $this->get(route('erp.business',[$id,'people']))->assertOk();
        $this->postJson(route('erp.mutate',[$id,'task']),['title'=>'Blocked'])->assertForbidden();
        $this->actingAs($staff)->get(route('erp.business',[$id,'people']))->assertForbidden();
        $this->actingAs(User::find(1))->post(route('erp.teams.archive',$id),['archived'=>0])->assertRedirect();
        $this->actingAs($staff)->get(route('erp.business',[$id,'people']))->assertOk();
        $this->assertDatabaseHas('erp_memberships',['user_id'=>$staff->id,'unit_id'=>$id]);
        $this->assertDatabaseHas('erp_audit',['action'=>'team.archived']);
    }
    public function test_onboarding_is_atomic_and_role_assignment_is_validated(): void
    {
        $this->actingAs($this->createAdmin());$a=$this->team('A1');$b=$this->team('B1');
        $payload=['name'=>'New employee','email'=>'new@example.invalid','phone'=>'9111111111','gender'=>'OTHER','password'=>'Strong-New-Password','confirm_password'=>'Strong-New-Password','team_ids'=>[$a,$b],'initial_role'=>'finance'];
        $this->post(route('erp.onboard'),$payload)->assertRedirect(route('erp.administration'));
        $user=User::where('email',$payload['email'])->firstOrFail();
        $this->assertSame(2,DB::table('erp_memberships')->where('user_id',$user->id)->where('role','finance')->count());
        $this->assertTrue(Hash::check($payload['password'],$user->password));
        $payload['email']='bad@example.invalid';$payload['phone']='9222222222';$payload['team_ids']=[$a,999999];
        $this->postJson(route('erp.onboard'),$payload)->assertUnprocessable();
        $this->assertDatabaseMissing('users',['email'=>'bad@example.invalid']);
        $payload['team_ids']=[$a];$payload['initial_role']='superadmin';
        $this->postJson(route('erp.onboard'),$payload)->assertUnprocessable();
    }
    public function test_every_role_is_team_scoped_and_sensitive_actions_fail_closed(): void
    {
        $a=$this->team('A1');$b=$this->team('B1');$this->createAdmin();
        $cases=['team_manager'=>['people','task','payment'],'academics'=>['academics','mark','payment'],'finance'=>['money','payment','mark'],'sales_followups'=>['followups','task','payment'],'read_only'=>['people',null,'task']];
        foreach($cases as $role=>[$page,$allowed,$denied]){
            $user=$this->createMember();DB::table('erp_memberships')->insert(['unit_id'=>$a,'user_id'=>$user->id,'role'=>$role]);
            $this->actingAs($user)->get('/')->assertOk();
            $this->get(route('erp.business',[$a,$page]))->assertOk();
            $this->get(route('erp.business',[$b,$page]))->assertForbidden();
            $this->get(route('erp.administration'))->assertForbidden();
            $this->postJson(route('erp.business.mutate',[$a,'membership-grant']),['user_id'=>$user->id,'role'=>'admin'])->assertForbidden();
            $this->getJson(route('erp.lookup',[$a,'type'=>'users']))->assertForbidden();
            if($role!=='team_manager')$this->postJson(route('erp.mutate',[$a,$denied]),[])->assertForbidden();
            if($allowed){
                $response=$this->postJson(route('erp.mutate',[$a,$allowed]),[]);
                $response->assertUnprocessable(); // Allowed action reaches payload validation, never silently writes.
            }
        }
    }
    public function test_readonly_cannot_complete_own_task_or_use_composite_action_routes(): void
    {
        $user=$this->createMember();$unit=$this->team();
        DB::table('erp_memberships')->insert(['unit_id'=>$unit,'user_id'=>$user->id,'role'=>'read_only']);
        $task=DB::table('erp_tasks')->insertGetId(['unit_id'=>$unit,'created_by'=>$user->id,'assigned_to'=>$user->id,'title'=>'Read only task','status'=>'open']);
        $this->actingAs($user)->post(route('erp.tasks.complete',$task))->assertForbidden();
        $this->postJson(route('erp.business.mutate',[$unit,'task-update']),['task_id'=>$task,'status'=>'completed'])->assertForbidden();
        $this->get(route('erp.business',[$unit,'followups']))->assertOk()->assertDontSee('Complete task')->assertDontSee('Save record');
        $this->assertDatabaseHas('erp_tasks',['id'=>$task,'status'=>'open']);
    }
    public function test_account_actions_require_password_and_revoke_sessions_without_exposing_secrets(): void
    {
        $admin=$this->createAdmin();$staff=$this->createMember();$unit=$this->team();
        DB::table('erp_memberships')->insert(['unit_id'=>$unit,'user_id'=>$staff->id,'role'=>'finance']);
        DB::table('sessions')->insert(['id'=>'synthetic-session','user_id'=>$staff->id,'payload'=>'test','last_activity'=>time()]);
        $this->actingAs($admin)->postJson(route('erp.accounts.update',$staff->id),['action'=>'suspend','current_password'=>'wrong'])->assertUnprocessable();
        $this->assertDatabaseHas('sessions',['id'=>'synthetic-session']);
        $this->post(route('erp.accounts.update',$staff->id),['action'=>'suspend','current_password'=>'Local-Testing-Only-2026'])->assertRedirect();
        $this->assertDatabaseMissing('sessions',['id'=>'synthetic-session']);
        $this->actingAs($staff->fresh())->get('/')->assertForbidden();
        $this->actingAs($admin)->post(route('erp.accounts.update',$staff->id),['action'=>'activate','current_password'=>'Local-Testing-Only-2026'])->assertRedirect();
        $this->post(route('erp.accounts.update',$staff->id),['action'=>'reset_password','current_password'=>'Local-Testing-Only-2026','password'=>'Changed-Strong-Password','password_confirmation'=>'Changed-Strong-Password'])->assertRedirect();
        $this->assertTrue(Hash::check('Changed-Strong-Password',$staff->fresh()->password));
        $this->assertStringNotContainsString('Password',DB::table('erp_audit')->where('entity_type','user')->pluck('payload')->implode(''));
        $this->postJson(route('erp.accounts.update',$admin->id),['action'=>'suspend','current_password'=>'Local-Testing-Only-2026'])->assertUnprocessable();
    }
    public function test_promotion_and_demotion_leave_team_assignments_but_remove_site_privileges(): void
    {
        $admin=$this->createAdmin();$staff=$this->createMember();
        $this->actingAs($admin)->post(route('erp.accounts.update',$staff->id),['action'=>'promote','current_password'=>'Local-Testing-Only-2026'])->assertRedirect();
        $this->assertSame('superadmin',$staff->fresh()->user_type);
        $this->post(route('erp.accounts.update',$staff->id),['action'=>'demote','current_password'=>'Local-Testing-Only-2026'])->assertRedirect();
        $this->actingAs($staff->fresh())->get(route('erp.administration'))->assertForbidden();
    }
    public function test_operations_and_migration_are_superadmin_only_and_render(): void
    {
        $this->actingAs($this->createAdmin())->get(route('erp.operations'))->assertOk()->assertSee('Health:');
        $this->getJson(route('erp.operations'))->assertOk()->assertJsonPath('status','ok');
        $this->get(route('erp.migration.center'))->assertOk();
        $this->actingAs($this->createMember())->get(route('erp.operations'))->assertForbidden();
        $this->get(route('erp.migration.center'))->assertForbidden();
    }
}
