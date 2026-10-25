<?php
namespace Tests\Feature;

use App\Modules\Erp\Services\StudentProfile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Synthetic fixtures only; no source import, integration or live ledger writes. */
class ErpStudentProfileTest extends TestCase
{
    private $unit; private $other; private $admin; private $student; private $peer; private $teacher; private $enrollment; private $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createAdmin();
        $this->unit = DB::table('erp_units')->insertGetId(['code'=>'PROFILE-A','name'=>'Profile unit','timezone'=>'Asia/Kolkata']);
        $this->other = DB::table('erp_units')->insertGetId(['code'=>'PROFILE-B','name'=>'Other private unit','timezone'=>'UTC']);
        DB::table('erp_memberships')->insert(['unit_id'=>$this->unit,'user_id'=>$this->admin->id,'role'=>'admin']);
        $this->student = $this->student('PROFILE-STUDENT', 'Synthetic Profile Student');
        $this->peer = $this->student('PROFILE-PEER', 'Private Peer Student');
        $this->teacher = DB::table('erp_teachers')->insertGetId(['code'=>'PROFILE-TEACHER','name'=>'Synthetic Profile Teacher','timezone'=>'UTC','status'=>'active']);
        DB::table('erp_teacher_units')->insert(['teacher_id'=>$this->teacher,'unit_id'=>$this->unit]);
        $subject = DB::table('erp_subjects')->insertGetId(['name'=>'Profile Mathematics']);
        $this->enrollment = DB::table('erp_enrollments')->insertGetId(['unit_id'=>$this->unit,'student_id'=>$this->student,'teacher_id'=>$this->teacher,'subject_id'=>$subject,'starts_on'=>'2026-01-01','status'=>'active','student_rate_paise'=>12029,'teacher_rate_paise'=>6025,'currency'=>'INR']);
        DB::table('erp_agreements')->insert(['unit_id'=>$this->unit,'enrollment_id'=>$this->enrollment,'weekday'=>1,'start_time'=>'10:00','duration_minutes'=>60,'timezone'=>'UTC','starts_on'=>'2026-01-01','status'=>'confirmed']);
        $this->lesson = DB::table('erp_lessons')->insertGetId(['unit_id'=>$this->unit,'enrollment_id'=>$this->enrollment,'student_id'=>$this->student,'teacher_id'=>$this->teacher,'subject_id'=>$subject,'starts_at'=>'2026-10-20 10:00:00','ends_at'=>'2026-10-20 11:00:00','status'=>'completed','kind'=>'regular','student_rate_paise'=>12029,'teacher_rate_paise'=>6025,'currency'=>'INR']);
        DB::table('erp_attendance')->insert(['unit_id'=>$this->unit,'lesson_id'=>$this->lesson,'actual_minutes'=>60,'status'=>'submitted','submitted_by'=>$this->admin->id]);
        $this->actingAs($this->admin);
    }

    private function student(string $code, string $name, ?int $unit = null): int
    {
        return DB::table('erp_students')->insertGetId(['unit_id'=>$unit ?? $this->unit,'code'=>$code,'name'=>$name,'registered_on'=>'2026-01-01','timezone'=>'UTC','status'=>'active','grade'=>'8','syllabus'=>'Synthetic']);
    }
    private function url(array $query = []): string
    {
        return route('erp.student-profile', array_merge([$this->unit,$this->student], $query));
    }
    private function entry(string $reference, int $amount, array $extra = []): int
    {
        return DB::table('erp_ledger')->insertGetId(array_merge(['unit_id'=>$this->unit,'student_id'=>$this->student,'kind'=>'payment','amount_paise'=>$amount,'currency'=>'INR','reference'=>$reference,'event_key'=>$reference,'occurred_at'=>'2026-10-20 10:00:00','created_by'=>$this->admin->id],$extra));
    }

    public function test_unified_profile_is_named_read_only_and_keeps_financial_boundaries(): void
    {
        $payment = $this->entry('PROFILE-RECEIPT', 20000);
        $this->entry('PROFILE-CHARGE', -12029, ['kind'=>'lesson_charge']);
        $this->entry('PRIVATE-TEACHER-PAY', 6025, ['kind'=>'teacher_accrual','student_id'=>null,'teacher_id'=>$this->teacher]);
        DB::table('erp_followups')->insert(['unit_id'=>$this->unit,'student_id'=>$this->student,'contact_channel'=>'call','note'=>'Synthetic logged conversation','outcome'=>'Call next week','next_due_on'=>'2026-10-28','created_by'=>$this->admin->id]);
        DB::table('erp_tasks')->insert(['unit_id'=>$this->unit,'student_id'=>$this->student,'assigned_to'=>$this->admin->id,'title'=>'Synthetic student task','status'=>'open','created_by'=>$this->admin->id]);
        $before = [];
        foreach (['erp_ledger','erp_audit','erp_attendance','erp_tasks','erp_followups'] as $table) $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        $this->get($this->url())->assertOk()->assertSee('Synthetic Profile Student')->assertSee('Synthetic Profile Teacher')->assertSee('Profile Mathematics')->assertSee('2026-10-20 15:30')->assertSee('INR 79.71')->assertSee('Credit')->assertSee('submitted')->assertSee('Synthetic logged conversation')->assertSee('Synthetic student task')->assertSee(route('erp.receipt',[$this->unit,$payment]),false)->assertDontSee('PRIVATE-TEACHER-PAY')->assertDontSee('teacher_rate_paise')->assertDontSee('Private Peer Student');
        $this->get($this->url())->assertOk();
        foreach ($before as $table=>$snapshot) $this->assertSame($snapshot, DB::table($table)->orderBy('id')->get()->toJson(), $table.' was changed by a read');
    }

    public function test_access_requires_active_site_user_and_explicit_unit_admin_membership(): void
    {
        $member = $this->createMember();
        foreach (['sales','teacher'] as $role) {
            DB::table('erp_memberships')->updateOrInsert(['unit_id'=>$this->unit,'user_id'=>$member->id],['role'=>$role]);
            $this->actingAs($member)->get($this->url())->assertForbidden();
        }
        $this->actingAs($this->admin)->get(route('erp.student-profile',[$this->other,$this->student]))->assertNotFound();
        DB::table('erp_memberships')->insert(['unit_id'=>$this->other,'user_id'=>$this->admin->id,'role'=>'admin']);
        $this->get(route('erp.student-profile',[$this->other,$this->student]))->assertNotFound();
        $this->get(route('erp.student-profile',[$this->unit,999999]))->assertNotFound();
        $this->admin->forceFill(['status'=>0])->save();
        $this->get($this->url())->assertForbidden();
        $this->app['auth']->guard()->logout();
        $this->get($this->url())->assertRedirect(route('login'));
    }

    public function test_cross_unit_and_other_student_rows_are_excluded_even_with_corrupt_references(): void
    {
        $this->entry('PRIVATE-OTHER-UNIT', 88888, ['unit_id'=>$this->other]);
        $this->entry('PRIVATE-OTHER-STUDENT', 77777, ['student_id'=>$this->peer]);
        $this->entry('PRIVATE-MIXED-OWNER', 66666, ['teacher_id'=>$this->teacher]);
        foreach ([[$this->other,$this->student],[$this->unit,$this->peer]] as [$unit,$student]) {
            DB::table('erp_followups')->insert(['unit_id'=>$unit,'student_id'=>$student,'contact_channel'=>'call','note'=>'PRIVATE FOLLOWUP','outcome'=>'Private outcome','created_by'=>$this->admin->id]);
            DB::table('erp_tasks')->insert(['unit_id'=>$unit,'student_id'=>$student,'title'=>'PRIVATE TASK','status'=>'open','created_by'=>$this->admin->id]);
            DB::table('erp_payment_requests')->insert(['unit_id'=>$unit,'student_id'=>$student,'reference'=>'PRIVATE-REQUEST-'.$unit.'-'.$student,'amount_paise'=>88888,'requested_on'=>'2026-10-20','created_by'=>$this->admin->id]);
        }
        DB::table('erp_agreements')->where('enrollment_id',$this->enrollment)->update(['unit_id'=>$this->other]);
        DB::table('erp_attendance')->where('lesson_id',$this->lesson)->update(['unit_id'=>$this->other]);
        $profile = (new StudentProfile())->read($this->unit,$this->student);
        foreach (['ledger','balances','followups','tasks','requests','agreements','attendance'] as $key) $this->assertCount(0,$profile[$key]);
        $this->get($this->url())->assertOk()->assertDontSee('PRIVATE-')->assertDontSee('PRIVATE FOLLOWUP')->assertDontSee('PRIVATE TASK');
        DB::table('erp_teacher_units')->where('teacher_id',$this->teacher)->update(['unit_id'=>$this->other]);
        $profile = (new StudentProfile())->read($this->unit,$this->student);
        $this->assertCount(0,$profile['assignments']); $this->assertCount(0,$profile['lessons']);
    }

    public function test_reversals_requests_and_multiple_currencies_do_not_create_false_credit(): void
    {
        $payment = $this->entry('PROFILE-ORIGINAL', 10000);
        $this->entry('PROFILE-REVERSAL', -10000, ['kind'=>'reversal','reverses_id'=>$payment]);
        $this->entry('PROFILE-DUE', -12029, ['kind'=>'opening_balance']);
        $this->entry('PROFILE-OTHER-CURRENCY', 999, ['currency'=>'USD']);
        $request = DB::table('erp_payment_requests')->insertGetId(['unit_id'=>$this->unit,'student_id'=>$this->student,'reference'=>'PROFILE-REQUEST','amount_paise'=>10000,'requested_on'=>'2026-10-20','created_by'=>$this->admin->id]);
        DB::table('erp_request_allocations')->insert(['unit_id'=>$this->unit,'request_id'=>$request,'payment_id'=>$payment,'amount_paise'=>10000]);
        $profile = (new StudentProfile())->read($this->unit,$this->student);
        $this->assertSame(-12029,(int)$profile['balances']->firstWhere('currency','INR')->balance_paise);
        $this->assertSame(999,(int)$profile['balances']->firstWhere('currency','USD')->balance_paise);
        $this->assertSame(10000,$profile['requestSummaries'][$request]['outstanding_paise']);
        $this->get($this->url())->assertOk()->assertSee('INR 120.29')->assertSee('Due')->assertSee('USD 9.99')->assertSee('Reverses entry #'.$payment)->assertSee(route('erp.request.print',[$this->unit,$request]),false)->assertSee('A request is not a receipt');
    }

    public function test_pagination_is_independent_and_balance_covers_all_history(): void
    {
        for ($i=1;$i<=17;$i++) $this->entry('PROFILE-PAGINATION-'.$i,100);
        for ($i=1;$i<=12;$i++) DB::table('erp_followups')->insert(['unit_id'=>$this->unit,'student_id'=>$this->student,'contact_channel'=>'call','note'=>'Follow-up '.$i,'outcome'=>'Synthetic','created_by'=>$this->admin->id]);
        $this->get($this->url())->assertOk()->assertSee('INR 17.00')->assertViewHas('ledger',fn($rows)=>$rows->count()===15 && $rows->total()===17)->assertViewHas('followups',fn($rows)=>$rows->count()===10);
        $this->get($this->url(['ledger_page'=>2,'followups_page'=>2]))->assertOk()->assertSee('INR 17.00')->assertViewHas('ledger',fn($rows)=>$rows->count()===2)->assertViewHas('followups',fn($rows)=>$rows->count()===2);
        foreach (['0','-1','nope','1000001'] as $page) $this->getJson($this->url(['ledger_page'=>$page]))->assertUnprocessable();
        $this->get($this->url(['ledger_page'=>100]))->assertOk()->assertSee('No receipt or ledger history recorded.')->assertSee('INR 17.00');
    }

    public function test_empty_sections_and_untrusted_text_render_safely_without_mutation_forms(): void
    {
        DB::table('erp_students')->where('id',$this->student)->update(['name'=>'<script>alert(1)</script>','meeting_url'=>'javascript:alert(2)']);
        $response = $this->get($this->url())->assertOk()->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>',false)->assertDontSee('href="javascript:',false)->assertSee('No follow-ups recorded.')->assertSee('No student tasks recorded.')->assertSee('No payment requests recorded.');
        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getContent());
        // The shared shell offers sign-out; the student profile itself remains read-only.
        $this->assertSame(0, (new \DOMXPath($dom))->query('//main//form')->length);
    }

    public function test_task_assignee_names_require_membership_in_the_profile_unit(): void
    {
        $outside = $this->createMember(['name'=>'PRIVATE OUTSIDE ASSIGNEE']);
        DB::table('erp_memberships')->insert(['unit_id'=>$this->other,'user_id'=>$outside->id,'role'=>'sales']);
        DB::table('erp_tasks')->insert(['unit_id'=>$this->unit,'student_id'=>$this->student,'assigned_to'=>$outside->id,'title'=>'Synthetic malformed task','status'=>'open','created_by'=>$this->admin->id]);
        $this->get($this->url())->assertOk()->assertSee('Synthetic malformed task')->assertSee('Unassigned or unavailable account')->assertDontSee('PRIVATE OUTSIDE ASSIGNEE');
    }
}
