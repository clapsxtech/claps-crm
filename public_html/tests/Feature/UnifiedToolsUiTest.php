<?php
namespace Tests\Feature;
use Tests\TestCase;

class UnifiedToolsUiTest extends TestCase
{
    public function test_all_record_and_administration_pages_use_the_workspace_shell(): void
    {
        \Illuminate\Support\Facades\DB::table('settings')->insert(['name'=>'ClapsLearn','phone'=>'','support_mail'=>'support@example.invalid','admin_mail'=>'admin@example.invalid','career_mail'=>'career@example.invalid']);
        $this->actingAs($this->createAdmin());
        foreach (['topics','subjects','marklist','master','students','teachers','monthlyreport','workreport','marklistreport','workhour','duereport','timetable','studentTimetable','users','roles','roles/create','settings'] as $path) {
            $this->get('/admin/'.$path)->assertOk()->assertSee('id="app-navigation"',false)
                ->assertSee('Records & administration',false)->assertDontSee('class="main-sidebar',false);
        }
        $this->get('/legacy')->assertRedirect('/tools');
        $this->get('/tools')->assertOk()->assertSee('Monthly reports')->assertSee('Topics');
    }

    public function test_teacher_routes_share_navigation_without_bypassing_authentication(): void
    {
        foreach (['timetableReport','attendance','resultform'] as $path) {
            $this->get('/'.$path)->assertOk()->assertSee('Teacher workspace')->assertSee('aria-label="Teacher tools"',false);
        }
        foreach (['attendanceReport','markreport','student-attendance-report'] as $path) $this->get('/'.$path)->assertRedirect('/login');
        $this->actingAs($this->createAdmin());
        foreach (['timetableReport','attendance','attendanceReport','resultform','markreport','student-attendance-report'] as $path) {
            $this->get('/'.$path)->assertOk()->assertSee('aria-label="Teacher tools"',false)->assertSee('meta name="csrf-token"',false);
        }
    }

    public function test_nonadministrators_cannot_access_the_redesigned_admin_routes(): void
    {
        $this->actingAs($this->createMember());
        foreach (['topics','marklistreport','monthlyreport','users','roles','settings'] as $path) $this->get('/admin/'.$path)->assertForbidden();
    }
}
