<?php

namespace Tests\Feature;

use App\Modules\Erp\Services\{Admissions,Scheduling,TeacherAllocation};
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ErpTeacherAllocationTest extends TestCase
{
    private $admin;
    private $sales;
    private int $unit;
    private int $teacher;
    private int $subject;
    private int $lead;
    protected function setUp():void
    {
        parent::setUp();Carbon::setTestNow('2026-10-26 08:00:00');CarbonImmutable::setTestNow('2026-10-26 08:00:00');
        $this->admin=$this->createAdmin();$this->sales=$this->createMember(['id'=>21]);
        $this->unit=DB::table('erp_units')->insertGetId(['code'=>'MATCH-AA','name'=>'Matching unit','timezone'=>'Asia/Kolkata']);
        DB::table('erp_memberships')->insert(['unit_id'=>$this->unit,'user_id'=>$this->sales->id,'role'=>'sales_followups']);
        $this->teacher=DB::table('erp_teachers')->insertGetId(['code'=>'MATCH-T1','name'=>'Matching Teacher','timezone'=>'Asia/Qatar','status'=>'active']);
        DB::table('erp_teacher_units')->insert(['unit_id'=>$this->unit,'teacher_id'=>$this->teacher]);
        $this->subject=DB::table('erp_subjects')->insertGetId(['name'=>'Matching Maths']);
        $lead=(new Admissions())->createLead($this->unit,$this->sales->id,['name'=>'Matching Learner','phone'=>'9000012345','grade'=>'8','syllabus'=>'CBSE','enquiry_date'=>'2026-10-26']);$this->lead=$lead->id;
        $this->actingAs($this->admin);
    }
    protected function tearDown():void { Carbon::setTestNow();CarbonImmutable::setTestNow();parent::tearDown(); }
    private function url(string $action):string { return route('erp.business.mutate',[$this->unit,'allocation-'.$action]); }
    private function slot(array $extra=[]):array
    {
        return array_replace(['lead_id'=>$this->lead,'teacher_id'=>$this->teacher,'subject_id'=>$this->subject,'date'=>'2026-10-28','time'=>'10:00','timezone'=>'Asia/Qatar','duration_minutes'=>60,'medium'=>'English','grade'=>'8','syllabus'=>'CBSE','reference'=>'MATCH-DEMO-1','teacher_confirmed'=>1,'student_confirmed'=>1,'confirmation_channel'=>'whatsapp','confirmation_note'=>'Teacher and parent confirmed the slot.'],$extra);
    }
    private function prepare():void
    {
        $this->postJson($this->url('qualify'),['teacher_id'=>$this->teacher,'subject_id'=>$this->subject,'grade'=>'8','syllabus'=>'CBSE','mediums'=>['English'],'note'=>'Teaching details verified by call.'])->assertOk();
        $this->postJson($this->url('window'),['teacher_id'=>$this->teacher,'weekday'=>3,'start_time'=>'09:00','end_time'=>'13:00','timezone'=>'Asia/Qatar','starts_on'=>'2026-10-26','ends_on'=>'2026-12-31','note'=>'Teacher confirmed Wednesday availability.'])->assertOk();
    }
    public function test_matching_requires_structured_eligibility_and_media_and_shows_local_time():void
    {
        $this->get(route('erp.business',[$this->unit,'allocation']))->assertOk()->assertSee('Teacher matching &amp; allocation',false);
        $this->postJson($this->url('demo'),$this->slot())->assertStatus(422);
        $this->assertDatabaseCount('erp_demos',0);
        $this->prepare();
        $response=$this->get(route('erp.business',[$this->unit,'allocation']+$this->slot(['search'=>1])))->assertOk()->assertSee('Matching Teacher')->assertSee('Confirmed details match this slot.')->assertSee('10:00');
        $matching=$response->viewData('matching');
        $this->assertSame('2026-10-28 07:00:00',$matching['slot']['starts_at']);
        $this->assertSame([],$matching['teachers']->first()->blockers);
        $this->postJson($this->url('demo'),$this->slot(['medium'=>'Malayalam']))->assertStatus(422);
        $this->postJson($this->url('demo'),$this->slot(['time'=>'14:00']))->assertStatus(422);
    }
    public function test_demo_is_reserved_once_and_cross_request_conflicts_are_rechecked():void
    {
        $this->prepare();$payload=$this->slot();
        $this->actingAs($this->sales)->postJson($this->url('demo'),$payload)->assertOk()->assertJsonPath('result.starts_at','2026-10-28 07:00:00');
        $this->postJson($this->url('demo'),$payload)->assertOk();
        $this->postJson($this->url('demo'),$this->slot(['duration_minutes'=>90]))->assertStatus(422);
        $this->postJson($this->url('demo'),$this->slot(['reference'=>'MATCH-DEMO-2']))->assertStatus(422);
        $this->assertDatabaseCount('erp_demos',1);$this->assertDatabaseCount('erp_allocation_requests',1);$this->assertDatabaseCount('erp_students',0);$this->assertDatabaseCount('erp_ledger',0);
    }
    public function test_feedback_then_conversion_reuses_student_and_creates_confirmed_regular_lessons_once():void
    {
        $this->prepare();$this->postJson($this->url('demo'),$this->slot())->assertOk();$demo=DB::table('erp_demos')->first();
        $feedback=['demo_id'=>$demo->id,'outcome'=>'CONTINUE','note'=>'Parent attended and chose this teacher.'];
        $this->postJson($this->url('feedback'),$feedback)->assertStatus(422);
        Carbon::setTestNow('2026-10-29 08:00:00');CarbonImmutable::setTestNow('2026-10-29 08:00:00');
        $this->postJson($this->url('feedback'),$feedback)->assertOk();
        $this->assertDatabaseHas('erp_lead_activities',['lead_id'=>$this->lead,'kind'=>'demo_feedback','outcome'=>'CONTINUE']);
        $regular=$this->slot(['date'=>'2026-11-04','ends_on'=>'2026-11-25','reference'=>'MATCH-REGULAR-1','demo_id'=>$demo->id,'student_rate_inr'=>'250.50','teacher_rate_inr'=>'100.25']);
        $this->postJson($this->url('regular'),$regular)->assertOk();
        $this->postJson($this->url('regular'),$regular)->assertOk();
        $this->assertDatabaseCount('erp_students',1);$this->assertDatabaseCount('erp_enrollments',1);$this->assertDatabaseCount('erp_agreements',1);$this->assertDatabaseCount('erp_lessons',4);$this->assertDatabaseCount('erp_ledger',0);
        $student=DB::table('erp_leads')->where('id',$this->lead)->value('student_id');
        $this->assertDatabaseHas('erp_enrollments',['student_id'=>$student,'teacher_id'=>$this->teacher,'student_rate_paise'=>25050,'teacher_rate_paise'=>10025]);
        $this->assertDatabaseHas('erp_demos',['id'=>$demo->id,'status'=>'JOINED','student_id'=>$student]);
        $this->assertDatabaseHas('erp_agreements',['status'=>'confirmed']);
        $this->get(route('erp.business',[$this->unit,'allocation','lead_id'=>$this->lead]))->assertOk()->assertSee('Open allocated timetable');
    }
    public function test_failed_regular_handoff_rolls_back_conversion_and_all_scheduling_writes():void
    {
        $this->prepare();$this->postJson($this->url('demo'),$this->slot())->assertOk();$demo=DB::table('erp_demos')->first();
        Carbon::setTestNow('2026-10-29');CarbonImmutable::setTestNow('2026-10-29');
        $this->postJson($this->url('feedback'),['demo_id'=>$demo->id,'outcome'=>'CONTINUE','note'=>'Successful demo.'])->assertOk();
        // A later exception must prevent the whole recurring allocation, even
        // though the first occurrence is available and conversion would succeed.
        (new Scheduling())->exception($this->unit,$this->admin->id,['teacher_id'=>$this->teacher,'date'=>'2026-11-11','reason'=>'Teacher leave','timezone'=>'Asia/Qatar']);
        $this->postJson($this->url('regular'),$this->slot(['date'=>'2026-11-04','ends_on'=>'2026-11-25','reference'=>'MATCH-ROLLBACK','demo_id'=>$demo->id,'student_rate_paise'=>20000,'teacher_rate_paise'=>10000]))->assertStatus(422);
        foreach(['erp_students','erp_enrollments','erp_agreements','erp_lessons'] as $table)$this->assertDatabaseCount($table,0);
        $this->assertDatabaseHas('erp_leads',['id'=>$this->lead,'status'=>'LEAD','student_id'=>null]);
    }
    public function test_sales_and_read_only_permissions_and_cross_owner_lead_protection():void
    {
        $this->prepare();$other=(new Admissions())->createLead($this->unit,$this->admin->id,['name'=>'Private Enquiry','phone'=>'9000012346','enquiry_date'=>'2026-10-26']);
        $this->actingAs($this->sales)->get(route('erp.business',[$this->unit,'allocation','lead_id'=>$other->id]))->assertNotFound();
        $this->postJson($this->url('qualify'),['teacher_id'=>$this->teacher])->assertForbidden();
        $this->postJson($this->url('regular'),$this->slot())->assertForbidden();
        $readonly=$this->createMember(['id'=>23]);DB::table('erp_memberships')->insert(['unit_id'=>$this->unit,'user_id'=>$readonly->id,'role'=>'read_only']);
        $this->actingAs($readonly)->get(route('erp.business',[$this->unit,'allocation','lead_id'=>$this->lead]))->assertOk()->assertDontSee('Teacher hourly rate');
        $this->postJson($this->url('demo'),$this->slot())->assertForbidden();
    }
    public function test_matched_demo_cannot_be_rescheduled_or_converted_through_legacy_status_form():void
    {
        $this->prepare();$this->postJson($this->url('demo'),$this->slot())->assertOk();$demo=DB::table('erp_demos')->first();
        foreach([['scheduled_time'=>'11:00'],['status'=>'JOINED'],['status'=>'ATTENDED']] as $changes)$this->postJson(route('erp.business.mutate',[$this->unit,'admission-update-demo']),['demo_id'=>$demo->id]+$changes)->assertStatus(422);
        $this->postJson(route('erp.business.mutate',[$this->unit,'admission-update-demo']),['demo_id'=>$demo->id,'status'=>'CANCELLED'])->assertOk();
        $this->postJson($this->url('demo'),$this->slot(['reference'=>'AFTER-CANCELLATION']))->assertOk();
    }
}
