<?php
namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ErpWorkQueuesTest extends TestCase
{
    private $admin;
    private $unit;
    private $other;
    private $student;
    private $teacher;
    private $subject;
    private $enrollment;

    protected function setUp(): void
    {
        parent::setUp();
        config(['erp.finance_policy'=>'admin_import_delivered_only','erp.payroll_policy'=>'approved_attended_only']);
        $this->admin=$this->createAdmin();
        $this->unit=DB::table('erp_units')->insertGetId(['code'=>'QUEUE-AA','name'=>'Queue AA','timezone'=>'Asia/Kolkata']);
        $this->other=DB::table('erp_units')->insertGetId(['code'=>'QUEUE-BB','name'=>'Queue BB','timezone'=>'UTC']);
        DB::table('erp_memberships')->insert(['unit_id'=>$this->unit,'user_id'=>$this->admin->id,'role'=>'admin']);
        $this->student=DB::table('erp_students')->insertGetId(['unit_id'=>$this->unit,'code'=>'QUEUE-S','name'=>'Named Queue Student','registered_on'=>'2026-01-01','timezone'=>'UTC','status'=>'active','grade'=>'8','syllabus'=>'Synthetic']);
        $this->teacher=DB::table('erp_teachers')->insertGetId(['code'=>'QUEUE-T','name'=>'Named Queue Teacher','timezone'=>'UTC','status'=>'active']);
        DB::table('erp_teacher_units')->insert(['unit_id'=>$this->unit,'teacher_id'=>$this->teacher]);
        $this->subject=DB::table('erp_subjects')->insertGetId(['name'=>'Queue Mathematics']);
        $this->enrollment=DB::table('erp_enrollments')->insertGetId(['unit_id'=>$this->unit,'student_id'=>$this->student,'teacher_id'=>$this->teacher,'subject_id'=>$this->subject,'starts_on'=>'2026-01-01','status'=>'active','student_rate_paise'=>10000,'teacher_rate_paise'=>6000,'currency'=>'INR']);
        $this->actingAs($this->admin);
    }
    private function attendance(array $lesson=[],array $attendance=[]): int
    {
        $id=DB::table('erp_lessons')->insertGetId(array_merge(['unit_id'=>$this->unit,'enrollment_id'=>$this->enrollment,'student_id'=>$this->student,'teacher_id'=>$this->teacher,'subject_id'=>$this->subject,'starts_at'=>'2026-01-05 10:00:00','ends_at'=>'2026-01-05 11:00:00','kind'=>'regular','status'=>'completed','student_rate_paise'=>10000,'teacher_rate_paise'=>6000,'currency'=>'INR'],$lesson));
        return DB::table('erp_attendance')->insertGetId(array_merge(['unit_id'=>$this->unit,'lesson_id'=>$id,'actual_minutes'=>60,'status'=>'submitted','submitted_by'=>$this->admin->id],$attendance));
    }
    private function page(string $page='attendance') {return $this->get(route('erp.business',[$this->unit,$page]));}
    private function ids($response,string $queue): array {return $response->viewData('queues')[$queue]->pluck('id')->map(fn($id)=>(int)$id)->all();}
    private function action(string $action,array $payload) {return $this->post(route('erp.mutate',[$this->unit,$action]),$payload+['return_page'=>'attendance']);}

    public function test_named_action_queues_keep_student_import_separate_from_teacher_approval(): void
    {
        $id=$this->attendance();$demo=$this->attendance(['kind'=>'demo']);
        foreach(['dashboard','attendance'] as $page){
            $response=$this->page($page)->assertOk()->assertSee('Named Queue Student')->assertSee('Named Queue Teacher')->assertSee('Queue Mathematics')->assertSee('15:30 +05:30');
            $this->assertSame([$id],$this->ids($response,'student_import'));
            $this->assertSame([$id,$demo],$this->ids($response,'teacher_approval'));
            $response->assertSee('Import this class for student charge')->assertSee('Approve attended teacher earnings')->assertSee('Approve free, unpaid demo');
        }
        $this->assertSame(0,DB::table('erp_ledger')->count());$this->assertSame(0,DB::table('erp_audit')->count());
    }
    public function test_queue_posts_revalidate_and_replays_do_not_double_post_either_stream(): void
    {
        $id=$this->attendance();
        $payload=['attendance_ids'=>[$id],'reference'=>'SYN-QUEUE-IMPORT'];
        $this->action('import-batch',$payload)->assertRedirect(route('erp.business',[$this->unit,'attendance']))->assertSessionHasNoErrors();
        $response=$this->page()->assertOk();$this->assertSame([],$this->ids($response,'student_import'));$this->assertSame([$id],$this->ids($response,'teacher_approval'));
        $this->assertSame(1,DB::table('erp_ledger')->where('kind','lesson_charge')->count());$this->assertSame(0,DB::table('erp_ledger')->where('kind','teacher_accrual')->count());
        $this->action('import-batch',$payload)->assertSessionHasNoErrors();
        $this->action('approve',['attendance_id'=>$id])->assertRedirect(route('erp.business',[$this->unit,'attendance']))->assertSessionHasNoErrors();
        $this->action('approve',['attendance_id'=>$id])->assertSessionHasNoErrors();
        $response=$this->page()->assertOk();$this->assertSame([],$this->ids($response,'student_import'));$this->assertSame([],$this->ids($response,'teacher_approval'));
        $this->assertSame(2,DB::table('erp_ledger')->count());
        $this->action('correct',['attendance_id'=>$id,'actual_minutes'=>30,'reason'=>'Synthetic corrected minutes'])->assertSessionHasNoErrors();
        $response=$this->page()->assertOk();$this->assertSame([$id],$this->ids($response,'student_import'));$this->assertSame([$id],$this->ids($response,'teacher_approval'));
        $this->assertSame(0,(int)DB::table('erp_ledger')->sum('amount_paise'));
    }
    public function test_pending_duplicates_still_import_but_have_no_approval_button_and_post_is_rejected(): void
    {
        $one=$this->attendance();$two=$this->attendance();
        DB::table('erp_duplicate_reviews')->insert(['unit_id'=>$this->unit,'attendance_id'=>$one,'other_attendance_id'=>$two,'status'=>'pending','channel'=>'gallabox']);
        $response=$this->page()->assertOk()->assertSee('Resolve duplicate review before teacher approval')->assertDontSee('Approve attended teacher earnings');
        $this->assertSame([$one,$two],$this->ids($response,'student_import'));
        $this->action('approve',['attendance_id'=>$one])->assertSessionHasErrors();
        $this->action('import-batch',['attendance_ids'=>[$one,$two],'reference'=>'SYN-QUEUE-DUPLICATES'])->assertSessionHasNoErrors();
        $this->assertSame(2,DB::table('erp_ledger')->where('kind','lesson_charge')->count());$this->assertSame(0,DB::table('erp_ledger')->where('kind','teacher_accrual')->count());
    }
    public function test_rejected_cancelled_and_foreign_rows_do_not_leak_and_access_requires_unit_admin(): void
    {
        $own=$this->attendance();$this->attendance([],['status'=>'rejected']);$this->attendance(['status'=>'cancelled']);
        $this->attendance(['unit_id'=>$this->other],['unit_id'=>$this->other]);
        $response=$this->page()->assertOk();$this->assertSame([$own],$this->ids($response,'student_import'));$this->assertSame([$own],$this->ids($response,'teacher_approval'));
        $this->get(route('erp.business',[$this->other,'attendance']))->assertForbidden();
        $teacherUser=$this->createMember();DB::table('erp_memberships')->insert(['unit_id'=>$this->unit,'user_id'=>$teacherUser->id,'role'=>'teacher']);
        $this->actingAs($teacherUser);$this->page()->assertForbidden();$this->action('approve',['attendance_id'=>$own])->assertForbidden();
    }
    public function test_queues_are_independently_paginated_and_escape_names(): void
    {
        DB::table('erp_students')->where('id',$this->student)->update(['name'=>'<script>unsafe()</script>']);
        for($i=0;$i<27;$i++)$this->attendance();
        $response=$this->page()->assertOk()->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;',false)->assertDontSee('<script>unsafe()</script>',false);
        $this->assertCount(25,$this->ids($response,'student_import'));$this->assertSame(27,$response->viewData('queues')['student_import']->total());
        $response=$this->get(route('erp.business',[$this->unit,'attendance','import_queue_page'=>2]))->assertOk();
        $this->assertCount(2,$this->ids($response,'student_import'));$this->assertCount(25,$this->ids($response,'teacher_approval'));
    }
    public function test_approved_but_unaccrued_classes_remain_visible_for_explicit_review(): void
    {
        $id=$this->attendance([],['status'=>'approved']);
        $this->attendance(['kind'=>'demo'],['status'=>'approved']);
        $response=$this->page()->assertOk()->assertSee('Recheck approved teacher accrual');
        $this->assertSame([$id],$this->ids($response,'teacher_approval'));
        $this->action('approve',['attendance_id'=>$id])->assertSessionHasNoErrors();
        $this->assertSame(1,DB::table('erp_ledger')->where('kind','teacher_accrual')->count());
        $this->assertSame(0,DB::table('erp_ledger')->where('kind','lesson_charge')->count());
        $this->assertSame([],$this->ids($this->page()->assertOk(),'teacher_approval'));
    }
    public function test_generic_registers_resolve_party_names_without_cross_unit_name_leaks(): void
    {
        $id=$this->attendance();
        $response=$this->page()->assertOk();
        $lesson=DB::table('erp_attendance')->where('id',$id)->value('lesson_id');
        $this->assertSame('Named Queue Student / Named Queue Teacher · Queue Mathematics',$response->viewData('labels')['lesson_id'][$lesson]);
        DB::table('erp_ledger')->insert(['unit_id'=>$this->unit,'student_id'=>$this->student,'teacher_id'=>null,'kind'=>'opening_balance','amount_paise'=>-10000,'currency'=>'INR','event_key'=>'syn-queue-opening','reference'=>'SYN-QUEUE-OPEN','occurred_at'=>'2026-01-01 00:00:00','created_by'=>$this->admin->id]);
        $response=$this->page('money')->assertOk()->assertSee('Named Queue Student · QUEUE-S');
        $this->assertSame('Named Queue Student · QUEUE-S',$response->viewData('labels')['student_id'][$this->student]);
    }

    public function test_named_tasks_offer_completion_only_after_checklist_and_revalidate_on_post(): void
    {
        $task=DB::table('erp_tasks')->insertGetId(['unit_id'=>$this->unit,'student_id'=>$this->student,'assigned_to'=>$this->admin->id,'title'=>'Synthetic actionable task','status'=>'open','created_by'=>$this->admin->id]);
        $item=DB::table('erp_task_items')->insertGetId(['unit_id'=>$this->unit,'task_id'=>$task,'title'=>'Synthetic checklist item']);
        $response=$this->page('dashboard')->assertOk()->assertSee('1 checklist items remaining')->assertDontSee('>Complete task</button>',false);
        $this->assertSame($this->admin->name,$response->viewData('labels')['assigned_to'][$this->admin->id]);
        $this->post(route('erp.business.mutate',[$this->unit,'task-update']),['task_id'=>$task,'status'=>'completed','return_page'=>'dashboard'])->assertSessionHasErrors();
        $this->assertDatabaseHas('erp_tasks',['id'=>$task,'status'=>'open']);
        $this->page('followups')->assertOk()->assertSee('Complete checklist item');
        $this->post(route('erp.business.mutate',[$this->unit,'task-item-complete']),['item_id'=>$item,'return_page'=>'followups'])->assertSessionHasNoErrors();
        $this->page('dashboard')->assertOk()->assertSee('>Complete task</button>',false);
        $this->post(route('erp.business.mutate',[$this->unit,'task-update']),['task_id'=>$task,'status'=>'completed','return_page'=>'dashboard'])->assertRedirect(route('erp.business',[$this->unit,'dashboard']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('erp_tasks',['id'=>$task,'status'=>'completed']);
        $this->page('dashboard')->assertOk()->assertDontSee('Synthetic actionable task');
    }

}
