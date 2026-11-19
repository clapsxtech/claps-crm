<?php

namespace Tests\Feature;

use App\Modules\Erp\Services\{Admissions,TeacherDirectory};
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ErpSalesTeacherDirectoryTest extends TestCase
{
    private $admin;
    private $sales;
    private int $unit;
    private int $subject;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-19 08:00:00');CarbonImmutable::setTestNow('2026-11-19 08:00:00');
        $this->admin = $this->createAdmin();
        $this->sales = $this->createMember(['id' => 31]);
        $this->unit = DB::table('erp_units')->insertGetId(['code' => 'DIR-AA', 'name' => 'Claps Learn', 'timezone' => 'Asia/Kolkata']);
        DB::table('erp_memberships')->insert(['unit_id' => $this->unit, 'user_id' => $this->sales->id, 'role' => 'sales_followups']);
        $this->subject = DB::table('erp_subjects')->insertGetId(['name' => 'Directory Maths']);
    }

    protected function tearDown(): void { Carbon::setTestNow();CarbonImmutable::setTestNow();parent::tearDown(); }

    private function teacher(string $code, string $name, string $created, array $extra = []): int
    {
        $id = DB::table('erp_teachers')->insertGetId(['code' => $code, 'name' => $name, 'timezone' => 'Asia/Kolkata', 'status' => 'active', 'created_at' => $created, 'updated_at' => $created] + $extra);
        DB::table('erp_teacher_units')->insert(['unit_id' => $this->unit, 'teacher_id' => $id]);
        return $id;
    }

    public function test_enquiry_can_be_created_without_student_name_but_registration_needs_one(): void
    {
        $this->actingAs($this->sales)->post(route('erp.business.mutate', [$this->unit, 'admission-create-lead']), ['phone' => '9000077001', 'country' => 'IN', 'return_view' => 'detail'])->assertRedirect();
        $lead = DB::table('erp_leads')->where('unit_id', $this->unit)->first();
        $this->assertSame('', $lead->name);
        $this->get(route('erp.business', [$this->unit, 'admissions', 'view' => 'pipeline']))->assertOk()->assertSee('Student name pending');
        $this->get(route('erp.business', [$this->unit, 'admissions', 'view' => 'new']))->assertOk()->assertSee('Student name (optional)');

        $this->postJson(route('erp.business.mutate', [$this->unit, 'admission-convert']), ['lead_id' => $lead->id])->assertStatus(422)->assertJsonValidationErrors('student_name');
        $this->postJson(route('erp.business.mutate', [$this->unit, 'admission-convert']), ['lead_id' => $lead->id, 'student_name' => 'Named Later'])->assertOk();
        $this->assertSame('Named Later', DB::table('erp_students')->where('unit_id', $this->unit)->value('name'));
        $this->assertSame('Named Later', DB::table('erp_leads')->where('id', $lead->id)->value('name'));

        $page = $this->get(route('erp.business', [$this->unit, 'admissions', 'lead_id' => $lead->id]))->assertOk()->assertSee('Share joined details on WhatsApp');
        $this->assertStringContainsString('https://wa.me/?text='.rawurlencode('NEW ADMISSION'), $page->getContent());
    }

    public function test_demo_cards_offer_parent_and_teacher_contact_with_demo_details(): void
    {
        $teacher = $this->teacher('CT7', 'Demo Teacher', '2026-01-01 00:00:00');
        DB::table('erp_teacher_candidates')->insert(['business_id' => 1, 'identity_key' => 'demo-teacher', 'name' => 'Demo Teacher', 'phone' => '9876500001', 'teacher_id' => $teacher, 'stage' => 'APPROVED', 'created_at' => now(), 'updated_at' => now()]);
        $lead = (new Admissions())->createLead($this->unit, $this->sales->id, ['name' => 'Demo Learner', 'phone' => '9000077002', 'country' => 'IN']);
        DB::table('erp_demos')->insert(['unit_id' => $this->unit, 'lead_id' => $lead->id, 'teacher_id' => $teacher, 'subject_id' => $this->subject, 'scheduled_on' => '2026-11-20', 'scheduled_time' => '17:30', 'status' => 'SCHEDULED', 'meet_link' => 'https://meet.example/abc', 'created_by' => $this->sales->id, 'created_at' => now(), 'updated_at' => now()]);

        $html = $this->actingAs($this->sales)->get(route('erp.business', [$this->unit, 'admissions', 'view' => 'demos']))->assertOk()
            ->assertSee('crm-demo-teacher', false)->assertSee('Demo Teacher')->getContent();
        $this->assertStringContainsString('tel:+919000077002', $html);
        $this->assertStringContainsString('tel:+919876500001', $html);
        $this->assertStringContainsString('https://wa.me/919000077002?text='.rawurlencode('Dear Parent,'), $html);
        $this->assertStringContainsString(rawurlencode('Meeting link: https://meet.example/abc'), $html);
        $this->assertStringContainsString('https://wa.me/919876500001?text='.rawurlencode('Hi Demo Teacher,'), $html);
    }

    public function test_sales_sees_teacher_directory_filtered_by_level_and_category_in_code_order(): void
    {
        $a = $this->teacher('CT10', 'Aaron', '2026-03-01 00:00:00', ['levels' => 'LP,UP', 'category' => 'Standard']);
        $z = $this->teacher('CT9', 'Zara', '2026-05-01 00:00:00', ['levels' => 'HS', 'category' => 'Advanced']);
        $m = $this->teacher('CT100', 'Mini', '2026-04-01 00:00:00', ['levels' => 'UP', 'category' => 'Advanced']);
        $student = DB::table('erp_students')->insertGetId(['unit_id' => $this->unit, 'code' => 'DIR-S1', 'name' => 'Directory Student', 'timezone' => 'Asia/Kolkata', 'status' => 'active', 'grade' => '8', 'syllabus' => 'CBSE', 'registered_on' => '2026-11-01']);
        $enrollment = DB::table('erp_enrollments')->insertGetId(['unit_id' => $this->unit, 'student_id' => $student, 'teacher_id' => $z, 'subject_id' => $this->subject, 'starts_on' => '2026-11-01', 'student_rate_paise' => 0, 'teacher_rate_paise' => 0]);
        foreach ([[1, 90], [3, 60]] as [$day, $minutes]) DB::table('erp_agreements')->insert(['unit_id' => $this->unit, 'enrollment_id' => $enrollment, 'weekday' => $day, 'start_time' => '10:00', 'duration_minutes' => $minutes, 'timezone' => 'Asia/Kolkata', 'starts_on' => '2026-11-01', 'status' => 'confirmed']);

        $this->actingAs($this->sales);
        $list = fn (array $q) => $this->get(route('erp.business', [$this->unit, 'admissions', 'view' => 'teachers'] + $q))->assertOk()->viewData('teacherDirectory');
        $this->assertSame(['CT9', 'CT10', 'CT100'], $list([])->pluck('code')->all());
        $this->assertSame(['CT9', 'CT100', 'CT10'], $list(['sort' => 'registered_desc'])->pluck('code')->all());
        $this->assertSame(['CT10', 'CT100'], $list(['level' => 'UP'])->pluck('code')->all());
        $this->assertSame(['CT9', 'CT100'], $list(['category' => 'Advanced'])->pluck('code')->all());
        $this->assertSame(['CT100'], $list(['category' => 'Advanced', 'level' => 'UP'])->pluck('code')->all());
        $this->assertSame('2.5', $list(['teacher_q' => 'CT9'])->first()->tt_hours);
        $this->get(route('erp.business', [$this->unit, 'admissions', 'view' => 'teachers']))->assertSee('TT hrs')->assertSee('Send profile')->assertDontSee('Set level &amp; category', false);

        // Sales cannot classify; academics and managers can.
        $this->postJson(route('erp.business.mutate', [$this->unit, 'teacher-classify']), ['teacher_id' => $a, 'levels' => ['HS'], 'category' => 'Advanced'])->assertForbidden();
        $this->actingAs($this->admin)->postJson(route('erp.business.mutate', [$this->unit, 'teacher-classify']), ['teacher_id' => $a, 'levels' => ['HS', 'LP'], 'category' => 'Advanced'])->assertOk();
        $this->assertSame(['LP,HS', 'Advanced'], array_values((array) DB::table('erp_teachers')->where('id', $a)->first(['levels', 'category'])));
        $this->postJson(route('erp.business.mutate', [$this->unit, 'teacher-classify']), ['teacher_id' => $a, 'levels' => ['KG']])->assertStatus(422);
    }

    public function test_teacher_profile_from_an_enquiry_goes_to_that_parent(): void
    {
        $this->teacher('CT1', 'Profile Teacher', '2026-01-01 00:00:00', ['levels' => 'HS', 'category' => 'Advanced']);
        $lead = (new Admissions())->createLead($this->unit, $this->sales->id, ['name' => 'Profile Learner', 'phone' => '9000077003', 'country' => 'IN']);
        $html = $this->actingAs($this->sales)->get(route('erp.business', [$this->unit, 'admissions', 'view' => 'teachers', 'for_lead' => $lead->id]))->assertOk()->assertSee('Profile Learner')->getContent();
        $this->assertStringContainsString('https://wa.me/919000077003?text='.rawurlencode("Teacher profile — Claps Learn\n\nTeacher: Profile Teacher\nCode: CT1\nCategory: Advanced\nLevels: HS"), $html);
    }
}
