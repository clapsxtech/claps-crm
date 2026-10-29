<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ErpAdmissionsTest extends TestCase
{
    private int $unit;
    private $admin;
    private $sales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createAdmin();
        $this->sales = $this->createMember(['id' => 22, 'name' => 'Admissions Counselor']);
        $this->unit = DB::table('erp_units')->insertGetId(['code' => 'ADM-AA', 'name' => 'Admissions unit', 'timezone' => 'UTC']);
        DB::table('erp_memberships')->insert([
            ['unit_id' => $this->unit, 'user_id' => $this->admin->id, 'role' => 'admin'],
            ['unit_id' => $this->unit, 'user_id' => $this->sales->id, 'role' => 'sales_followups'],
        ]);
        $teacher = DB::table('erp_teachers')->insertGetId(['code' => 'ADM-T1', 'name' => 'Admissions Teacher', 'timezone' => 'UTC', 'status' => 'active']);
        DB::table('erp_teacher_units')->insert(['teacher_id' => $teacher, 'unit_id' => $this->unit]);
        DB::table('erp_subjects')->insert(['name' => 'Admissions Maths']);
    }

    public function test_admissions_page_and_conversion_share_canonical_student_and_ledger(): void
    {
        $this->actingAs($this->admin);
        $this->get(route('erp.business', [$this->unit, 'admissions']))
            ->assertOk()->assertSee('Admissions &amp; CRM', false)->assertSee('New enquiry');

        $this->post(route('erp.business.mutate', [$this->unit, 'admission-create-lead']), [
            'name' => 'Enquiry Student', 'phone' => '+91 90000 11111', 'grade' => 'Class 8',
            'subject' => 'Admissions Maths', 'syllabus' => 'CBSE', 'source' => 'Meta Ads',
            'enquiry_date' => '2026-10-26',
        ])->assertRedirect(route('erp.business', [$this->unit, 'admissions']))->assertSessionHasNoErrors();

        $lead = DB::table('erp_leads')->first();
        $this->assertSame('919000011111', $lead->phone);
        $this->assertSame(1, DB::table('erp_leads')->count());

        // A repeated enquiry updates the existing pipeline record instead of
        // creating a second contact.
        $this->postJson(route('erp.business.mutate', [$this->unit, 'admission-create-lead']), [
            'name' => 'Enquiry Student', 'phone' => '90000 11111', 'enquiry_date' => '2026-10-27', 'existing_lead_id' => $lead->id,
        ])->assertOk();
        $this->assertSame(1, DB::table('erp_leads')->count());
        $this->assertSame(1, DB::table('erp_lead_activities')->where('kind', 're_enquiry')->count());

        $this->postJson(route('erp.business.mutate', [$this->unit, 'admission-convert']), [
            'lead_id' => $lead->id, 'joined_on' => '2026-10-26', 'payment_amount_inr' => '999.50', 'payment_reference' => 'ADM-RECEIPT-1',
        ])->assertOk()->assertJsonPath('result.name', 'Enquiry Student');

        $converted = DB::table('erp_leads')->where('id', $lead->id)->first();
        $this->assertSame('JOINED', $converted->status);
        $this->assertNotNull($converted->student_id);
        $this->assertDatabaseHas('erp_students', ['id' => $converted->student_id, 'name' => 'Enquiry Student', 'grade' => 'Class 8']);
        $this->assertDatabaseHas('erp_ledger', ['student_id' => $converted->student_id, 'kind' => 'payment', 'amount_paise' => 99950, 'reference' => 'ADM-RECEIPT-1']);
        $this->assertDatabaseHas('erp_audit', ['action' => 'lead.converted', 'entity_id' => $lead->id]);
    }

    public function test_sales_followups_member_sees_only_owned_leads_and_can_schedule_and_follow_up(): void
    {
        $this->actingAs($this->sales);
        $this->postJson(route('erp.business.mutate', [$this->unit, 'admission-create-lead']), [
            'name' => 'Counselor Lead', 'phone' => '9000099999', 'enquiry_date' => '2026-10-26',
        ])->assertOk();
        $lead = DB::table('erp_leads')->first();
        $this->postJson(route('erp.business.mutate', [$this->unit, 'admission-schedule-demo']), [
            'lead_id' => $lead->id, 'scheduled_on' => '2026-10-29', 'scheduled_time' => '17:30',
        ])->assertOk();
        $this->postJson(route('erp.business.mutate', [$this->unit, 'admission-followup']), [
            'lead_id' => $lead->id, 'channel' => 'whatsapp', 'outcome' => 'Interested', 'note' => 'Sent course details', 'next_due_on' => '2026-10-30',
        ])->assertOk();
        $this->assertDatabaseHas('erp_demos', ['lead_id' => $lead->id, 'status' => 'SCHEDULED']);
        $this->assertDatabaseHas('erp_lead_activities', ['lead_id' => $lead->id, 'kind' => 'contact_attempt']);
        $this->get(route('erp.business', [$this->unit, 'admissions']))->assertOk()->assertSee('Counselor Lead');
    }

    public function test_external_crm_identity_is_deduplicated(): void
    {
        $this->actingAs($this->admin);
        $payload = ['name' => 'Imported Enquiry', 'phone' => '9000012345', 'external_id' => 'crm-lead-42', 'enquiry_date' => '2026-10-26'];
        $this->postJson(route('erp.business.mutate', [$this->unit, 'admission-create-lead']), $payload)->assertOk();
        $this->postJson(route('erp.business.mutate', [$this->unit, 'admission-create-lead']), $payload + ['name' => 'Imported Enquiry (retry)'])->assertOk();
        $this->assertSame(1, DB::table('erp_leads')->where('external_id', 'crm-lead-42')->count());
        $this->assertSame(1, DB::table('erp_lead_activities')->where('kind', 're_enquiry')->count());
    }
    public function test_shared_phone_requires_choice_and_preserves_attribution(): void
    {
        $this->actingAs($this->sales);
        $url = route('erp.business.mutate', [$this->unit, 'admission-create-lead']);
        $payload = ['name'=>'First child','phone'=>'919000012345','source'=>'Original campaign','enquiry_date'=>'2026-10-01'];
        $this->postJson($url, $payload)->assertOk();
        $first = DB::table('erp_leads')->first();
        $this->postJson($url, array_replace($payload, ['name'=>'Sibling']))->assertUnprocessable()->assertJsonValidationErrors('existing_lead_id');
        $this->postJson($url, array_replace($payload, ['name'=>'Sibling','create_separate'=>true]))->assertOk();
        $this->assertSame(2, DB::table('erp_leads')->count());
        $this->postJson($url, array_replace($payload, ['existing_lead_id'=>$first->id,'source'=>'Later campaign','enquiry_date'=>'2026-10-26']))->assertOk();
        $this->assertDatabaseHas('erp_leads', ['id'=>$first->id,'source'=>'Original campaign','enquiry_date'=>'2026-10-01','assigned_to'=>$this->sales->id]);
        $this->get(route('erp.business', [$this->unit,'admissions','lead_id'=>$first->id]))->assertOk()->assertSee('Later campaign')->assertSee('Activity history');
    }

    public function test_followup_queue_and_history_respect_owner_and_local_date(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-26 20:00:00', 'UTC'));
        DB::table('erp_units')->where('id',$this->unit)->update(['timezone'=>'Asia/Kolkata']);
        $this->actingAs($this->sales);
        $url = route('erp.business.mutate', [$this->unit,'admission-create-lead']);
        foreach (['Due today'=>'2026-10-27','Overdue child'=>'2026-10-26','No action child'=>null] as $name=>$due) {
            $this->postJson($url, ['name'=>$name,'phone'=>'919000012345','create_separate'=>true])->assertOk();
            $id = DB::table('erp_leads')->where('name',$name)->value('id');
            if ($due) $this->postJson(route('erp.business.mutate',[$this->unit,'admission-followup']), ['lead_id'=>$id,'channel'=>'call','outcome'=>'No reply','note'=>'Called parent','next_due_on'=>$due])->assertOk();
        }
        $page = fn ($filter) => route('erp.business',[$this->unit,'admissions','followup'=>$filter]);
        $this->get($page('today'))->assertOk()->assertSee('Due today')->assertDontSee('Overdue child');
        $this->get($page('overdue'))->assertOk()->assertSee('Overdue child')->assertViewHas('leads', fn ($leads) => !$leads->contains('name', 'Due today'));
        $this->get($page('unscheduled'))->assertOk()->assertSee('No action child')->assertDontSee('Overdue child');
        $id = DB::table('erp_leads')->where('name','Due today')->value('id');
        DB::table('erp_leads')->where('id',$id)->update(['assigned_to'=>$this->admin->id]);
        $this->get(route('erp.business',[$this->unit,'admissions','lead_id'=>$id]))->assertNotFound();
        $this->postJson($url,['name'=>'Repeat','phone'=>'919000012345','existing_lead_id'=>$id])->assertForbidden();
        $this->travelBack();
    }

    public function test_demo_progress_is_separate_from_contact_outcome(): void
    {
        $this->actingAs($this->sales);
        $this->postJson(route('erp.business.mutate',[$this->unit,'admission-create-lead']),['name'=>'Demo child','phone'=>'9000012345'])->assertOk();
        $id = DB::table('erp_leads')->value('id');
        $this->postJson(route('erp.business.mutate',[$this->unit,'admission-followup']),['lead_id'=>$id,'channel'=>'call','outcome'=>'Discussed price','note'=>'Parent called','interest_status'=>'Fees issue'])->assertOk();
        $this->postJson(route('erp.business.mutate',[$this->unit,'admission-schedule-demo']),['lead_id'=>$id,'scheduled_on'=>'2026-10-29','scheduled_time'=>'17:30'])->assertOk();
        $page = route('erp.business',[$this->unit,'admissions']);
        $this->get($page)->assertOk()->assertSee('Demo scheduled')->assertSee('Fees issue');
        DB::table('erp_demos')->where('lead_id',$id)->update(['status'=>'ATTENDED']);
        $this->get($page)->assertOk()->assertSee('Demo attended');
        DB::table('erp_demos')->where('lead_id',$id)->update(['status'=>'CANCELLED']);
        $this->get($page)->assertOk()->assertSee('Contacted');
    }

}
