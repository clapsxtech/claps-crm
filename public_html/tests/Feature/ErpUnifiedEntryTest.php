<?php
namespace Tests\Feature;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ErpUnifiedEntryTest extends TestCase
{
    public function test_root_is_the_workspace_and_old_entry_points_redirect_without_loops(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/erp')->assertStatus(301)->assertRedirect('/');
        $this->get('/erp/')->assertStatus(301)->assertRedirect('/');
        $this->get('/legacy')->assertStatus(301)->assertRedirect('/tools');
        $this->get('/home')->assertStatus(301)->assertRedirect('/');
        $admin=$this->createAdmin();
        $unit=DB::table('erp_units')->insertGetId(['code'=>'ENTRY','name'=>'Entry Unit','timezone'=>'UTC']);
        DB::table('erp_memberships')->insert(['unit_id'=>$unit,'user_id'=>$admin->id,'role'=>'admin']);
        $this->actingAs($admin)->get('/')->assertOk()->assertSee('Your workspace')->assertSee('Entry Unit')->assertSee('Records & administration', false)->assertDontSee('Existing application');
        $this->assertSame(url('/'),route('erp.index'));
        $this->get('/tools')->assertOk()->assertSee('Daily operations')->assertSee('User accounts')->assertSee('Academics');
    }

    public function test_legacy_directory_keeps_site_administration_out_of_member_navigation(): void
    {
        $this->actingAs($this->createMember())->get('/tools')->assertOk()->assertDontSee('User accounts')->assertDontSee('Application settings');
        $this->get('/admin/users')->assertForbidden();
        $this->actingAs($this->createMember(['business_id'=>2]))->get('/tools')->assertForbidden();
        $this->get('/')->assertForbidden();
    }
}
