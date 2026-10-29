<?php
namespace Tests\Feature;

use App\Modules\Erp\Services\{Attendance,TuitionWorkflows};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ErpTuitionWorkflowsTest extends ErpFinanceAttendanceTest
{
    public function test_parent_deadline_dispute_and_correction_gate_earnings(): void
    {
        config(['erp.parent_review_required'=>true,'erp.payroll_policy'=>'approved_attended_only']);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-25 10:00:00','UTC'));
        $attendance=(new Attendance())->submit(1,1,['lesson_id'=>1,'actual_minutes'=>45]);
        $this->assertBlocked(fn()=>(new Attendance())->approve(1,1,$attendance->id));
        $workflow=new TuitionWorkflows();
        $workflow->parentReview(1,1,['attendance_id'=>$attendance->id,'decision'=>'share','deadline_at'=>'2026-10-26 10:00:00','note'=>'October statement sent in parent WhatsApp chat']);
        $this->assertBlocked(fn()=>(new Attendance())->approve(1,1,$attendance->id));
        $workflow->parentReview(1,1,['attendance_id'=>$attendance->id,'decision'=>'disputed','note'=>'Parent says only 30 minutes']);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-27 10:00:00','UTC'));
        $this->assertBlocked(fn()=>(new Attendance())->approve(1,1,$attendance->id));
        $workflow->parentReview(1,1,['attendance_id'=>$attendance->id,'decision'=>'accepted','note'=>'Parent checked and confirmed 45 minutes']);
        $approved=(new Attendance())->approve(1,1,$attendance->id);
        $this->assertTrue($approved->teacher_financial_posted);
        $corrected=(new Attendance())->correct(1,1,$attendance->id,['actual_minutes'=>30,'reason'=>'Parent sent corrected duration']);
        $this->assertSame('unshared',$corrected->parent_review_status);
        $this->assertBlocked(fn()=>(new Attendance())->approve(1,1,$attendance->id));
    }
    public function test_silence_after_shared_deadline_allows_approval(): void
    {
        config(['erp.parent_review_required'=>true]);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-25 10:00:00','UTC'));
        $a=(new Attendance())->submit(1,1,['lesson_id'=>1,'actual_minutes'=>60]);
        (new TuitionWorkflows())->parentReview(1,1,['attendance_id'=>$a->id,'decision'=>'share','deadline_at'=>'2026-10-26 10:00:00','note'=>'Statement sent']);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-26 10:00:00','UTC'));
        $this->assertSame('approved',(new Attendance())->approve(1,1,$a->id)->status);
    }
    public function test_shifted_teaching_is_linked_and_conflicting_retry_is_rejected(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-25 10:00:00','UTC'));
        $payload=['lesson_id'=>1,'actual_minutes'=>75,'actual_starts_at'=>'2026-10-21 12:00:00','change_reason'=>'Parent requested a later class and 15 extra minutes'];
        $a=(new Attendance())->submit(1,1,$payload);
        $this->assertSame('2026-10-21 12:00:00',$a->actual_starts_at);
        $this->assertEquals(1,$a->lesson_id);
        $this->assertEquals(1,$a->submitted_late);
        $this->assertBlocked(fn()=>(new Attendance())->submit(1,1,array_replace($payload,['actual_starts_at'=>'2026-10-22 12:00:00'])));
        $this->assertSame(1,DB::table('erp_attendance')->count());
    }
    public function test_private_notes_demo_outcomes_and_candidate_interviews_are_scoped_and_audited(): void
    {
        $w=new TuitionWorkflows();
        $w->note(1,1,['lesson_id'=>1,'kind'=>'internal','note'=>'Called teacher after late arrival']);
        $this->assertBlocked(fn()=>$w->note(2,1,['lesson_id'=>1,'kind'=>'internal','note'=>'Wrong team']));
        $this->assertBlocked(fn()=>$w->note(1,1,['lesson_id'=>1,'kind'=>'demo','outcome'=>'continue']));
        DB::table('erp_lessons')->where('id',1)->update(['kind'=>'demo']);
        $w->note(1,1,['lesson_id'=>1,'kind'=>'demo','outcome'=>'try_another']);
        $c=$w->candidate(1,1,['name'=>'Applicant','contact'=>'candidate@example.invalid','qualification'=>'MSc','subjects'=>'Maths','levels'=>'High school']);
        $this->assertBlocked(fn()=>$w->interview(2,1,['candidate_id'=>$c->id,'score'=>85,'decision'=>'ready']));
        $w->interview(1,1,['candidate_id'=>$c->id,'score'=>85,'decision'=>'ready','strengths'=>'Clear explanations']);
        $this->assertSame('ready',DB::table('erp_candidates')->find($c->id)->status);
        $this->assertSame(2,DB::table('erp_internal_notes')->count());
        $this->assertSame(4,DB::table('erp_audit')->count());
    }
    private function assertBlocked(callable $call): void
    {
        try { $call(); $this->fail('Expected workflow validation'); } catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
    }
}
