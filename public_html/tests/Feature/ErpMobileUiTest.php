<?php
namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ErpMobileUiTest extends TestCase
{
    private function preview(string $name, $response): void
    {
        // Optional synthetic HTML for browser checks; never reads production records.
        if ($dir = getenv('ERP_MOBILE_PREVIEW_DIR')) {
            if (!is_dir($dir)) mkdir($dir, 0700, true);
            file_put_contents($dir.'/'.$name.'.html', $response->getContent());
        }
    }

    public function test_sales_hr_and_admin_mobile_navigation_respects_existing_access(): void
    {
        $admin = $this->createAdmin(['name'=>'Workspace Admin']);
        $unit = DB::table('erp_units')->insertGetId(['code'=>'LEARN', 'name'=>'Claps Learning', 'timezone'=>'Asia/Kolkata']);
        $sales = $this->createMember(['name'=>'Sales Counsellor']);
        $manager = $this->createMember(['name'=>'Recruitment Manager']);
        DB::table('erp_memberships')->insert([
            ['unit_id'=>$unit,'user_id'=>$sales->id,'role'=>'sales_followups'],
            ['unit_id'=>$unit,'user_id'=>$manager->id,'role'=>'team_manager'],
        ]);
        $this->actingAs($sales)->postJson(route('erp.business.mutate',[$unit,'admission-create-lead']), [
            'name'=>'Ananya — Mathematics enquiry','phone'=>'+91 90000 12345','enquiry_date'=>'2026-10-26',
            'grade'=>'Grade 8','subject'=>'Mathematics','source'=>'Referral',
        ])->assertOk();
        $salesPage = $this->get(route('erp.business',[$unit,'admissions']));
        $salesPage->assertOk()->assertSee('Your pipeline')->assertSee('tel:+919000012345',false);
        preg_match('/<nav class="mobile-navigation[^"]*"[\s\S]*?<\/nav>/', $salesPage->getContent(), $matches);
        $this->assertStringContainsString('Pipeline', $matches[0]);
        $this->assertStringNotContainsString('Accounts', $matches[0]);
        $this->assertStringNotContainsString('Attendance', $matches[0]);
        $this->assertStringContainsString('view=new', $salesPage->getContent());
        $this->preview('sales', $salesPage);

        $this->actingAs($manager)->postJson(route('erp.business.mutate',[$unit,'teacher-candidate-create']), [
            'name'=>'Meera Nair','phone'=>'9000012346','email'=>'meera@example.invalid',
            'qualification'=>'MSc Mathematics, BEd','subjects'=>'Mathematics, Science',
        ])->assertOk();
        $hr = $this->get(route('erp.business',[$unit,'teacher-recruitment']));
        $hr->assertOk()->assertSee('New application')->assertSee('mobile-records')->assertSee('More filters');
        $this->preview('hr', $hr);
        $selected = $this->get(route('erp.business',[$unit,'teacher-recruitment','candidate'=>DB::table('erp_teacher_candidates')->value('id')]));
        $selected->assertOk();
        $this->preview('hr-profile', $selected);
        $this->get(route('erp.administration'))->assertForbidden();

        $adminPage = $this->actingAs($admin)->get(route('erp.administration'));
        $adminPage->assertOk()->assertSee('id="action-onboard-staff"',false)->assertSee('href="#accounts"',false);
        $this->preview('admin', $adminPage);
        $operations = $this->get(route('erp.operations'));
        $operations->assertOk();
        $this->preview('operations', $operations);
    }
}
