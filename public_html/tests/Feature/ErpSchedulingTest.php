<?php

namespace Tests\Feature;

use App\Modules\Erp\Services\Scheduling;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\DB;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

/** Synthetic fixtures only; no legacy migrations, external services or persistent database. */
class ErpSchedulingTest extends TestCase
{
    private $service;
    protected function setUp(): void {
        parent::setUp();
        $container = new Container(); Container::setInstance($container);
        $capsule = new Capsule($container); $capsule->addConnection(['driver'=>'sqlite','database'=>':memory:','prefix'=>'']); $capsule->setAsGlobal();
        $container->instance('db',$capsule->getDatabaseManager());
        $container->instance('validator',new Factory(new Translator(new ArrayLoader(),'en'),$container));
        Facade::clearResolvedInstances(); Facade::setFacadeApplication($container);
        $container->instance('db.schema',$capsule->schema());
        require_once __DIR__.'/../../database/migrations/2026_10_23_130000_create_erp_core_tables.php';
        (new \CreateErpCoreTables())->up();
        require_once __DIR__.'/../../database/migrations/2026_10_23_140000_add_request_reference_to_erp_lessons.php';
        (new \AddRequestReferenceToErpLessons())->up();
        DB::table('erp_units')->insert(['id'=>1,'code'=>'TEST','name'=>'Synthetic unit','timezone'=>'Asia/Kolkata']);
        DB::table('erp_students')->insert(['id'=>1,'unit_id'=>1,'registered_on'=>'2027-01-01','code'=>'SYN-S1','name'=>'Synthetic Student','timezone'=>'Asia/Kolkata','status'=>'active','grade'=>'8','syllabus'=>'CBSE']);
        DB::table('erp_teachers')->insert(['id'=>1,'code'=>'SYN-T1','name'=>'Synthetic Teacher','timezone'=>'Asia/Kolkata','status'=>'active']);
        DB::table('erp_teacher_units')->insert(['teacher_id'=>1,'unit_id'=>1]);
        DB::table('erp_subjects')->insert(['id'=>1,'name'=>'Synthetic Mathematics']);
        DB::table('erp_eligibilities')->insert(['teacher_id'=>1,'subject_id'=>1,'grade'=>'8','syllabus'=>'CBSE']);
        $this->service=new Scheduling();
    }
    protected function tearDown(): void { DB::disconnect(); Facade::clearResolvedInstances(); parent::tearDown(); }
    private function assignment(array $extra=[]) { return $this->service->assign(1,1,array_merge(['student_id'=>1,'teacher_id'=>1,'subject_id'=>1,'starts_on'=>'2027-01-01','student_rate_paise'=>70000,'teacher_rate_paise'=>40000],$extra)); }
    private function windows($unit=1,$student=1) { foreach (range(1,7) as $day) foreach (['student_id'=>$student,'teacher_id'=>1] as $field=>$id) $this->service->availability($unit,1,[$field=>$id,'weekday'=>$day,'start_time'=>'08:00','end_time'=>'22:00','timezone'=>'Asia/Kolkata','starts_on'=>'2027-01-01']); }
    private function agreement($e, array $extra=[]) { return $this->service->proposeAgreement((int)$e->unit_id,1,array_merge(['enrollment_id'=>$e->id,'weekday'=>1,'start_time'=>'10:00','duration_minutes'=>60,'timezone'=>'Asia/Kolkata','starts_on'=>'2027-01-01'],$extra)); }
    private function confirm($a,$unit=1) { return $this->service->confirmAgreement($unit,1,['agreement_id'=>$a->id,'teacher_confirmed'=>true,'student_confirmed'=>true,'confirmation_channel'=>'whatsapp','confirmation_note'=>'Synthetic bilateral confirmation recorded by administrator.']); }
    private function generated() { $e=$this->assignment(); $this->windows(); $a=$this->confirm($this->agreement($e)); return [$a,$this->service->materialize(1,1,['agreement_id'=>$a->id,'from'=>'2027-01-01','through'=>'2027-01-31'])]; }
    private function rejects(callable $operation) { try { $operation(); $this->fail('Expected validation failure.'); } catch(ValidationException $e) { $this->assertNotEmpty($e->errors()); } }

    public function test_assignment_requires_eligibility_membership_and_nonoverlapping_effective_dates(): void {
        DB::table('erp_eligibilities')->delete(); $this->rejects(fn()=> $this->assignment());
        DB::table('erp_eligibilities')->insert(['teacher_id'=>1,'subject_id'=>1,'grade'=>'8','syllabus'=>'CBSE']);
        DB::table('erp_teacher_units')->delete(); $this->rejects(fn()=> $this->assignment());
        DB::table('erp_teacher_units')->insert(['teacher_id'=>1,'unit_id'=>1]);
        $this->assignment(['ends_on'=>'2027-01-31']); $this->rejects(fn()=> $this->assignment(['starts_on'=>'2027-01-31']));
        $new=$this->assignment(['starts_on'=>'2027-02-01','student_rate_paise'=>90000]); $this->assertSame(90000,(int)$new->student_rate_paise);
        $this->assertSame(70000,(int)DB::table('erp_enrollments')->orderBy('id')->value('student_rate_paise'));
    }
    public function test_corrupt_legacy_multiunit_teacher_pool_is_rejected(): void {
        // Legacy schema intentionally lacks the new single-pool unique constraint.
        Capsule::schema()->drop('erp_teacher_units');
        Capsule::schema()->create('erp_teacher_units',function($t) { $t->increments('id'); $t->integer('teacher_id'); $t->integer('unit_id'); });
        DB::table('erp_teacher_units')->insert([['teacher_id'=>1,'unit_id'=>1],['teacher_id'=>1,'unit_id'=>2]]);
        $this->rejects(fn()=> $this->assignment());
        $this->assertSame(0,DB::table('erp_enrollments')->count());
    }
    public function test_confirmation_requires_bilateral_evidence_and_both_availabilities(): void {
        $a=$this->agreement($this->assignment());
        $this->rejects(fn()=> $this->service->materialize(1,1,['agreement_id'=>$a->id,'from'=>'2027-01-01','through'=>'2027-01-31']));
        $this->rejects(fn()=> $this->confirm($a));
        $this->windows();
        $this->rejects(fn()=> $this->service->confirmAgreement(1,1,['agreement_id'=>$a->id,'teacher_confirmed'=>true,'student_confirmed'=>false,'confirmation_channel'=>'whatsapp','confirmation_note'=>'Unilateral only']));
        $confirmed=$this->confirm($a); $this->assertSame('confirmed',$confirmed->status); $this->assertNotNull($confirmed->teacher_confirmed_at); $this->assertNotNull($confirmed->student_confirmed_at);
    }
    public function test_generation_is_bounded_idempotent_and_freezes_rates(): void {
        [$a,$lessons]=$this->generated(); $this->assertCount(4,$lessons); $this->assertSame('2027-01-04 04:30:00',$lessons[0]->starts_at); $this->assertSame(70000,(int)$lessons[0]->student_rate_paise);
        $again=$this->service->materialize(1,1,['agreement_id'=>$a->id,'from'=>'2027-01-01','through'=>'2027-01-31']); $this->assertCount(0,$again);
        $this->rejects(fn()=> $this->service->materialize(1,1,['agreement_id'=>$a->id,'from'=>'2027-01-01','through'=>'2029-01-01']));
    }
    public function test_reschedule_chain_preserves_original_and_cancel_never_creates_finance(): void {
        [$a,$lessons]=$this->generated(); $original=$lessons[0];
        $replacement=$this->service->reschedule(1,1,['lesson_id'=>$original->id,'date'=>'2027-01-05','start_time'=>'11:00','timezone'=>'Asia/Kolkata','teacher_confirmed'=>true,'student_confirmed'=>true,'confirmation_channel'=>'call','confirmation_note'=>'Synthetic bilateral agreement to new time','reason'=>'Synthetic family request']);
        $this->assertSame($original->id,(int)$replacement->replaces_lesson_id); $this->assertSame('rescheduled',DB::table('erp_lessons')->where('id',$original->id)->value('status'));
        $this->assertSame($original->starts_at,DB::table('erp_lessons')->where('id',$original->id)->value('starts_at'));
        $next=$this->service->reschedule(1,1,['lesson_id'=>$replacement->id,'date'=>'2027-01-06','start_time'=>'11:00','timezone'=>'Asia/Kolkata','teacher_confirmed'=>true,'student_confirmed'=>true,'confirmation_channel'=>'call','confirmation_note'=>'Synthetic bilateral agreement to new time','reason'=>'Synthetic second request']);
        $this->assertSame($replacement->id,(int)$next->replaces_lesson_id);
        $this->service->cancel(1,1,['lesson_id'=>$next->id,'reason'=>'Synthetic cancellation']);
        $this->assertSame(0,DB::table('erp_ledger')->count());
        $this->assertCount(0,$this->service->materialize(1,1,['agreement_id'=>$a->id,'from'=>'2027-01-01','through'=>'2027-01-31']));
        $this->rejects(fn()=> $this->service->cancel(1,1,['lesson_id'=>$original->id,'reason'=>'Already replaced']));
    }
    public function test_cross_unit_confirmed_agreement_prevents_teacher_overlap_before_materialization(): void {
        $this->windows(); $first=$this->confirm($this->agreement($this->assignment()));
        DB::table('erp_units')->insert(['id'=>2,'code'=>'TEST2','name'=>'Second synthetic unit']);
        DB::table('erp_students')->insert(['id'=>2,'unit_id'=>2,'registered_on'=>'2027-01-01','code'=>'SYN-S2','name'=>'Second synthetic student','timezone'=>'Asia/Kolkata','status'=>'active','grade'=>'8','syllabus'=>'CBSE']);
        // Simulate historical commitments surviving a legacy pool reassignment.
        DB::table('erp_teacher_units')->where('teacher_id',1)->update(['unit_id'=>2]); $this->windows(2,2);
        $e=$this->service->assign(2,1,['student_id'=>2,'teacher_id'=>1,'subject_id'=>1,'starts_on'=>'2027-01-01','student_rate_paise'=>80000,'teacher_rate_paise'=>45000]);
        $overlap=$this->agreement($e,['start_time'=>'10:30']); $this->rejects(fn()=> $this->confirm($overlap,2));
        $adjacent=$this->agreement($e,['start_time'=>'11:00']); $this->assertSame('confirmed',$this->confirm($adjacent,2)->status);
        $this->rejects(fn()=> $this->service->materialize(2,1,['agreement_id'=>$first->id,'from'=>'2027-01-01','through'=>'2027-01-31']));
    }
    public function test_materialization_rechecks_global_dated_overlap_and_rolls_back_batch(): void {
        $this->windows(); $e=$this->assignment(); $a=$this->confirm($this->agreement($e));
        DB::table('erp_lessons')->insert(['unit_id'=>2,'enrollment_id'=>999,'student_id'=>999,'teacher_id'=>1,'subject_id'=>1,'starts_at'=>'2027-01-11 04:45:00','ends_at'=>'2027-01-11 05:45:00','status'=>'scheduled','kind'=>'extra','student_rate_paise'=>1,'teacher_rate_paise'=>1,'currency'=>'INR']);
        $this->rejects(fn()=> $this->service->materialize(1,1,['agreement_id'=>$a->id,'from'=>'2027-01-01','through'=>'2027-01-31']));
        $this->assertSame(0,DB::table('erp_lessons')->where('unit_id',1)->count());
    }
    public function test_same_student_cannot_have_overlapping_subject_agreements(): void {
        $this->windows(); $this->confirm($this->agreement($this->assignment()));
        DB::table('erp_subjects')->insert(['id'=>2,'name'=>'Synthetic Science']);
        DB::table('erp_eligibilities')->insert(['teacher_id'=>1,'subject_id'=>2,'grade'=>'8','syllabus'=>'CBSE']);
        $other=$this->assignment(['subject_id'=>2]); $a=$this->agreement($other);
        $this->rejects(fn()=> $this->confirm($a));
    }
    public function test_availability_must_cover_whole_duration_and_date_range(): void {
        $e=$this->assignment(); $this->windows(); $a=$this->agreement($e,['start_time'=>'21:30']); $this->rejects(fn()=> $this->confirm($a));
        $this->assertSame('proposed',DB::table('erp_agreements')->where('id',$a->id)->value('status'));
    }
    public function test_reschedule_requires_renewed_bilateral_consent_and_audits_it(): void {
        [$a,$lessons]=$this->generated();
        $payload=['lesson_id'=>$lessons[0]->id,'date'=>'2027-01-05','start_time'=>'11:00','timezone'=>'Asia/Kolkata','reason'=>'Synthetic request'];
        $this->rejects(fn()=> $this->service->reschedule(1,1,$payload));
        $payload+=['teacher_confirmed'=>true,'student_confirmed'=>false,'confirmation_channel'=>'call','confirmation_note'=>'Only teacher confirmed'];
        $this->rejects(fn()=> $this->service->reschedule(1,1,$payload));
        $this->assertSame('scheduled',DB::table('erp_lessons')->where('id',$lessons[0]->id)->value('status'));
        $payload['student_confirmed']=true; $payload['confirmation_note']='Both confirmed new date and time';
        $replacement=$this->service->reschedule(1,1,$payload);
        $audit=json_decode(DB::table('erp_audit')->where('action','lesson.rescheduled')->value('payload'),true);
        $this->assertTrue($audit['teacher_confirmed']); $this->assertTrue($audit['student_confirmed']);
        $this->assertSame($replacement->starts_at,$audit['replacement_starts_at']);
    }
    public function test_assignment_closure_cannot_extend_existing_end_or_overlap_successor(): void {
        $first=$this->assignment(['ends_on'=>'2027-01-31']);
        $this->assignment(['starts_on'=>'2027-02-01']);
        $this->rejects(fn()=> $this->service->endAssignment(1,1,['enrollment_id'=>$first->id,'ends_on'=>'2027-02-10']));
        $this->assertSame('2027-01-31',DB::table('erp_enrollments')->where('id',$first->id)->value('ends_on'));
    }
    public function test_reschedule_may_shift_original_occurrence_but_not_overlap_next_week(): void {
        [$a,$lessons]=$this->generated();
        $replacement=$this->service->reschedule(1,1,['lesson_id'=>$lessons[0]->id,'date'=>'2027-01-04','start_time'=>'10:15','timezone'=>'Asia/Kolkata','teacher_confirmed'=>true,'student_confirmed'=>true,'confirmation_channel'=>'call','confirmation_note'=>'Synthetic bilateral agreement to new time','reason'=>'Shift fifteen minutes']);
        $this->assertSame('2027-01-04 04:45:00',$replacement->starts_at);
        $this->rejects(fn()=> $this->service->reschedule(1,1,['lesson_id'=>$replacement->id,'date'=>'2027-01-11','start_time'=>'10:15','timezone'=>'Asia/Kolkata','teacher_confirmed'=>true,'student_confirmed'=>true,'confirmation_channel'=>'call','confirmation_note'=>'Synthetic bilateral agreement to new time','reason'=>'Overlaps another occurrence']));
    }
    public function test_date_exception_blocks_generation_in_person_timezone_and_is_idempotent(): void {
        $this->windows(); $a=$this->confirm($this->agreement($this->assignment()));
        DB::table('erp_teachers')->where('id',1)->update(['timezone'=>'America/Los_Angeles']);
        // Monday 10:00 India is Sunday evening for this teacher.
        $p=['teacher_id'=>1,'date'=>'2027-01-03','reason'=>'Synthetic local holiday'];
        $first=$this->service->exception(1,1,$p); $this->assertSame($first->id,$this->service->exception(1,1,$p)->id);
        $this->rejects(fn()=> $this->service->materialize(1,1,['agreement_id'=>$a->id,'from'=>'2027-01-04','through'=>'2027-01-04']));
        $this->assertSame(0,DB::table('erp_lessons')->count());
        $this->rejects(fn()=> $this->service->exception(2,1,['student_id'=>1,'date'=>'2027-01-04','reason'=>'Wrong unit']));
    }
    public function test_exception_requires_explicit_resolution_of_existing_lessons(): void {
        [$a,$lessons]=$this->generated();
        $this->rejects(fn()=> $this->service->exception(1,1,['student_id'=>1,'date'=>'2027-01-04','reason'=>'Holiday']));
        $this->assertSame('scheduled',DB::table('erp_lessons')->where('id',$lessons[0]->id)->value('status'));
        $this->service->cancel(1,1,['lesson_id'=>$lessons[0]->id,'reason'=>'Holiday requested']);
        $exception=$this->service->exception(1,1,['student_id'=>1,'date'=>'2027-01-04','reason'=>'Holiday']);
        $this->assertSame('2027-01-04',$exception->date);
        $this->assertSame(0,DB::table('erp_ledger')->count());
    }
    public function test_recurring_end_and_version_preserve_lessons_and_require_resolution(): void {
        [$a,$lessons]=$this->generated();
        $this->rejects(fn()=> $this->service->endAgreement(1,1,['agreement_id'=>$a->id,'ends_on'=>'2027-01-15']));
        foreach (array_slice($lessons,2) as $l) $this->service->cancel(1,1,['lesson_id'=>$l->id,'reason'=>'Replace recurring version']);
        $ended=$this->service->endAgreement(1,1,['agreement_id'=>$a->id,'ends_on'=>'2027-01-15']);
        $this->assertSame('2027-01-15',$ended->ends_on);
        $this->rejects(fn()=> $this->service->endAgreement(1,1,['agreement_id'=>$a->id,'ends_on'=>'2027-02-01']));
        $e=DB::table('erp_enrollments')->where('id',$a->enrollment_id)->first();
        $this->rejects(fn()=> $this->agreement($e,['replaces_agreement_id'=>$a->id,'starts_on'=>'2027-01-15']));
        $version=$this->agreement($e,['replaces_agreement_id'=>$a->id,'starts_on'=>'2027-01-16','start_time'=>'12:00']);
        $this->assertSame($a->id,(int)$version->replaces_agreement_id); $this->confirm($version);
        $this->rejects(fn()=> $this->agreement($e,['replaces_agreement_id'=>$a->id,'starts_on'=>'2027-01-16']));
        $new=$this->service->materialize(1,1,['agreement_id'=>$version->id,'from'=>'2027-01-01','through'=>'2027-01-31']); $this->assertCount(2,$new);
        $this->assertSame('scheduled',DB::table('erp_lessons')->where('id',$lessons[0]->id)->value('status'));
        $this->assertSame($lessons[0]->starts_at,DB::table('erp_lessons')->where('id',$lessons[0]->id)->value('starts_at'));
    }
    public function test_recurring_end_checks_rescheduled_descendants_not_only_original_rows(): void {
        [$a,$lessons]=$this->generated();
        $replacement=$this->service->reschedule(1,1,['lesson_id'=>$lessons[0]->id,'date'=>'2027-01-20','start_time'=>'11:00','timezone'=>'Asia/Kolkata','teacher_confirmed'=>true,'student_confirmed'=>true,'confirmation_channel'=>'call','confirmation_note'=>'Both agree to replacement date','reason'=>'Move first occurrence']);
        foreach (array_slice($lessons,2) as $l) $this->service->cancel(1,1,['lesson_id'=>$l->id,'reason'=>'Close recurring series']);
        $this->rejects(fn()=> $this->service->endAgreement(1,1,['agreement_id'=>$a->id,'ends_on'=>'2027-01-15']));
        $this->service->cancel(1,1,['lesson_id'=>$replacement->id,'reason'=>'Resolve replacement before closure']);
        $this->assertSame('2027-01-15',$this->service->endAgreement(1,1,['agreement_id'=>$a->id,'ends_on'=>'2027-01-15'])->ends_on);
    }
    public function test_assignment_shortening_is_revalidated_by_propose_and_confirm(): void {
        $e=$this->assignment(); $this->windows(); $a=$this->agreement($e,['starts_on'=>'2027-02-01']);
        $this->service->endAssignment(1,1,['enrollment_id'=>$e->id,'ends_on'=>'2027-01-31']);
        $this->rejects(fn()=> $this->confirm($a));
        $this->rejects(fn()=> $this->agreement($e,['starts_on'=>'2027-02-01']));
        $this->assertSame('proposed',DB::table('erp_agreements')->where('id',$a->id)->value('status'));
    }
    public function test_shortened_assignment_bounds_generation_and_replacement_dates(): void {
        $e=$this->assignment(); $this->windows(); $a=$this->confirm($this->agreement($e));
        $this->service->endAssignment(1,1,['enrollment_id'=>$e->id,'ends_on'=>'2027-01-15']);
        $lessons=$this->service->materialize(1,1,['agreement_id'=>$a->id,'from'=>'2027-01-01','through'=>'2027-01-31']);
        $this->assertCount(2,$lessons);
        $this->rejects(fn()=> $this->service->reschedule(1,1,['lesson_id'=>$lessons[0]->id,'date'=>'2027-01-20','start_time'=>'11:00','timezone'=>'Asia/Kolkata','teacher_confirmed'=>true,'student_confirmed'=>true,'confirmation_channel'=>'call','confirmation_note'=>'Both agreed','reason'=>'Outside effective dates']));
    }
    private function extraPayload($enrollment, array $overrides=[]): array {
        return array_merge(['enrollment_id'=>$enrollment->id,'date'=>'2027-01-05','start_time'=>'11:00','timezone'=>'Asia/Kolkata','duration_minutes'=>45,'kind'=>'extra','teacher_confirmed'=>true,'student_confirmed'=>true,'confirmation_channel'=>'call','confirmation_note'=>'Synthetic bilateral consent for this one-off time','reference'=>'SYN-ONE-OFF-1'],$overrides);
    }
    public function test_one_off_extra_freezes_rates_and_replay_is_payload_bound(): void {
        $e=$this->assignment(); $this->windows(); $p=$this->extraPayload($e);
        $l=$this->service->scheduleExtra(1,1,$p);
        $this->assertSame('extra',$l->kind); $this->assertSame('2027-01-05 05:30:00',$l->starts_at); $this->assertSame('2027-01-05 06:15:00',$l->ends_at);
        $this->assertSame(70000,(int)$l->student_rate_paise); $this->assertSame(40000,(int)$l->teacher_rate_paise);
        DB::table('erp_enrollments')->where('id',$e->id)->update(['student_rate_paise'=>99999]);
        $this->assertSame($l->id,$this->service->scheduleExtra(1,1,$p)->id);
        foreach (['duration_minutes'=>60,'kind'=>'demo','confirmation_note'=>'Different evidence','timezone'=>'UTC'] as $key=>$value) $this->rejects(fn()=> $this->service->scheduleExtra(1,1,array_merge($p,[$key=>$value])));
        $this->service->cancel(1,1,['lesson_id'=>$l->id,'reason'=>'Synthetic cancellation']);
        $this->assertSame('cancelled',$this->service->scheduleExtra(1,1,$p)->status);
        $this->assertSame(1,DB::table('erp_lessons')->count()); $this->assertSame(0,DB::table('erp_ledger')->count());
    }
    public function test_one_off_demo_is_explicit_audited_and_requires_bilateral_consent(): void {
        $e=$this->assignment(); $this->windows(); $p=$this->extraPayload($e,['kind'=>'demo']);
        $this->rejects(fn()=> $this->service->scheduleExtra(1,1,array_merge($p,['student_confirmed'=>false])));
        $demo=$this->service->scheduleExtra(1,1,$p); $this->assertSame('demo',$demo->kind);
        $audit=json_decode(DB::table('erp_audit')->where('action','lesson.one_off_scheduled')->value('payload'),true);
        $this->assertTrue($audit['demo_free_and_unpaid']); $this->assertTrue($audit['request']['student_confirmed']);
        $this->assertSame(0,DB::table('erp_attendance')->count()); $this->assertSame(0,DB::table('erp_ledger')->count());
    }
    public function test_one_off_rejects_scope_dates_missing_availability_holidays_and_overlaps(): void {
        $e=$this->assignment(['ends_on'=>'2027-01-31']); $p=$this->extraPayload($e);
        $this->rejects(fn()=> $this->service->scheduleExtra(2,1,$p));
        $this->rejects(fn()=> $this->service->scheduleExtra(1,1,$p));
        $this->windows();
        $this->rejects(fn()=> $this->service->scheduleExtra(1,1,array_merge($p,['date'=>'2027-02-01'])));
        $this->service->exception(1,1,['teacher_id'=>1,'date'=>'2027-01-05','reason'=>'Synthetic holiday']);
        $this->rejects(fn()=> $this->service->scheduleExtra(1,1,$p));
        $a=$this->confirm($this->agreement($e,['ends_on'=>'2027-01-31']));
        $this->rejects(fn()=> $this->service->scheduleExtra(1,1,array_merge($p,['date'=>'2027-01-04','start_time'=>'10:30'])));
        $valid=array_merge($p,['date'=>'2027-01-06']); $this->service->scheduleExtra(1,1,$valid);
        $this->rejects(fn()=> $this->service->scheduleExtra(1,1,array_merge($valid,['reference'=>'SYN-OVERLAP-OTHER'])));
        $this->assertSame(1,DB::table('erp_lessons')->count());
    }
    public function test_dst_nonexistent_and_ambiguous_times_are_rejected(): void {
        $this->rejects(fn()=> $this->service->localToUtc('2027-03-14','02:30','America/New_York'));
        $this->rejects(fn()=> $this->service->localToUtc('2027-11-07','01:30','America/New_York'));
        $this->assertSame('2027-03-14 07:30:00',$this->service->localToUtc('2027-03-14','03:30','America/New_York'));
        $this->assertSame('2027-01-01 04:30:00',$this->service->localToUtc('2027-01-01','10:00','Asia/Kolkata'));
    }
}
