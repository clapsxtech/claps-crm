<?php
namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** HTTP route, middleware, view and persistence tests with synthetic fixtures only. */
class ErpTeacherHttpTest extends TestCase
{
    private $teacherUser;
    private $ownLesson;
    private $otherLesson;
    private $teacher;
    private $unit;
    protected function setUp(): void {
        parent::setUp();
        $this->teacherUser=$this->createMember(['name'=>'Synthetic Teacher Login']);
        $this->unit=DB::table('erp_units')->insertGetId(['code'=>'HTTP-TEACH-AA','name'=>'Synthetic AA']);
        $otherUnit=DB::table('erp_units')->insertGetId(['code'=>'HTTP-TEACH-BB','name'=>'Synthetic BB']);
        $this->teacher=DB::table('erp_teachers')->insertGetId(['user_id'=>$this->teacherUser->id,'code'=>'HTTP-TEACH-T1','name'=>'Linked Synthetic Teacher','timezone'=>'Asia/Kolkata','status'=>'active']);
        $otherTeacher=DB::table('erp_teachers')->insertGetId(['code'=>'HTTP-TEACH-T2','name'=>'Other Synthetic Teacher','timezone'=>'UTC','status'=>'active']);
        DB::table('erp_teacher_units')->insert([['teacher_id'=>$this->teacher,'unit_id'=>$this->unit],['teacher_id'=>$otherTeacher,'unit_id'=>$otherUnit]]);
        DB::table('erp_memberships')->insert(['user_id'=>$this->teacherUser->id,'unit_id'=>$this->unit,'role'=>'teacher']);
        $subject=DB::table('erp_subjects')->insertGetId(['name'=>'Synthetic HTTP Maths']);
        $date=now('UTC')->subDays(2)->toDateString();
        foreach ([[$this->unit,$this->teacher,'Owned Synthetic Student'],[$otherUnit,$otherTeacher,'Hidden Other Student']] as $i=>$values) {
            [$unit,$teacher,$name]=$values;
            $student=DB::table('erp_students')->insertGetId(['unit_id'=>$unit,'code'=>'HTTP-TEACH-S'.$i,'name'=>$name,'registered_on'=>$date,'timezone'=>'UTC','status'=>'active','grade'=>'8','syllabus'=>'Test','guardian_contact'=>'PRIVATE-GUARDIAN-SECRET-'.$i,'meeting_url'=>'https://example.invalid/private-meeting-'.$i]);
            $e=DB::table('erp_enrollments')->insertGetId(['unit_id'=>$unit,'student_id'=>$student,'teacher_id'=>$teacher,'subject_id'=>$subject,'starts_on'=>$date,'student_rate_paise'=>98765,'teacher_rate_paise'=>54321,'currency'=>'INR','status'=>'active']);
            $lesson=DB::table('erp_lessons')->insertGetId(['unit_id'=>$unit,'enrollment_id'=>$e,'student_id'=>$student,'teacher_id'=>$teacher,'subject_id'=>$subject,'starts_at'=>$date.' 10:00:00','ends_at'=>$date.' 11:00:00','status'=>'scheduled','kind'=>'regular','student_rate_paise'=>98765,'teacher_rate_paise'=>54321,'currency'=>'INR']);
            if ($i===0) $this->ownLesson=$lesson; else $this->otherLesson=$lesson;
        }
    }
    public function test_guest_is_redirected_to_login_for_listing_and_submission(): void {
        $this->get('/erp/teacher')->assertRedirect(route('login'));
        $this->post('/erp/teacher/attendance',['lesson_id'=>$this->ownLesson,'actual_minutes'=>60])->assertRedirect(route('login'));
        $this->assertSame(0,DB::table('erp_attendance')->count());
    }
    public function test_teacher_view_renders_only_own_lessons_and_posts_attendance(): void {
        $this->actingAs($this->teacherUser);
        $this->get('/erp/teacher?section=attendance')->assertOk()->assertSee('My lessons and attendance')->assertSee('Owned Synthetic Student')->assertSee('Asia/Kolkata')->assertSee('Submit attendance')->assertDontSee('Hidden Other Student')->assertDontSee('PRIVATE-GUARDIAN-SECRET')->assertDontSee('private-meeting')->assertDontSee('98765')->assertDontSee('54321');
        $this->post('/erp/teacher/attendance',['lesson_id'=>$this->ownLesson,'actual_minutes'=>45,'unit_id'=>999,'teacher_id'=>999])->assertRedirect(route('erp.teacher.index'))->assertSessionHas('status');
        $this->assertDatabaseHas('erp_attendance',['lesson_id'=>$this->ownLesson,'unit_id'=>$this->unit,'actual_minutes'=>45,'submitted_by'=>$this->teacherUser->id,'status'=>'submitted']);
        $this->assertSame(0,DB::table('erp_ledger')->count());
        $this->get('/erp/teacher?section=attendance')->assertOk()->assertSee('45 minutes')->assertSee('submitted')->assertDontSee('Submit attendance');
    }
    public function test_forged_crossunit_and_other_teacher_ids_are_forbidden(): void {
        $this->actingAs($this->teacherUser);
        $this->post('/erp/teacher/attendance',['lesson_id'=>$this->otherLesson,'actual_minutes'=>60])->assertForbidden();
        DB::table('erp_lessons')->where('id',$this->otherLesson)->update(['teacher_id'=>$this->teacher]);
        $this->post('/erp/teacher/attendance',['lesson_id'=>$this->otherLesson,'actual_minutes'=>60])->assertForbidden();
        $this->assertSame(0,DB::table('erp_attendance')->count());
    }
    public function test_deleted_user_and_unlinked_site_user_cannot_enter_teacher_portal(): void {
        $this->teacherUser->forceFill(['deleted_at'=>now()])->save();
        $this->actingAs($this->teacherUser);
        $this->get('/erp/teacher')->assertForbidden();
        $this->post('/erp/teacher/attendance',['lesson_id'=>$this->ownLesson,'actual_minutes'=>60])->assertForbidden();
        $unlinked=$this->createMember(['name'=>'Linked Synthetic Teacher']);
        $this->actingAs($unlinked)->get('/erp/teacher')->assertForbidden();
        $this->assertSame(0,DB::table('erp_attendance')->count());
    }

    private function resultPayload(array $overrides=[]): array {
        return array_merge(['enrollment_id'=>DB::table('erp_lessons')->where('id',$this->ownLesson)->value('enrollment_id'),
            'assessment'=>'Single-login test','assessed_on'=>now()->toDateString(),'score'=>'0','maximum'=>'20','notes'=>'Progress note',
            'submission_key'=>(string)\Illuminate\Support\Str::uuid()],$overrides);
    }
    public function test_teacher_login_lands_on_personal_dashboard_and_old_links_redirect(): void {
        $this->post('/login',['email'=>$this->teacherUser->email,'password'=>'Local-Testing-Only-2026'])->assertRedirect(route('erp.teacher.index'));
        $this->get('/')->assertRedirect(route('erp.teacher.index',['section'=>'overview']));
        foreach(['/timetableReport'=>'timetable','/attendance'=>'attendance','/resultform'=>'results','/attendanceReport'=>'reports','/markreport'=>'reports','/student-attendance-report'=>'reports'] as $path=>$section)
            $this->get($path)->assertRedirect(route('erp.teacher.index',['section'=>$section]));
    }
    public function test_all_dashboard_sections_are_private_and_do_not_request_teacher_credentials(): void {
        $this->actingAs($this->teacherUser);
        foreach(['overview','timetable','students','attendance','results','reports'] as $section) {
            $response=$this->get('/erp/teacher?section='.$section)->assertOk()->assertSee('My teaching workspace')
                ->assertDontSee('Hidden Other Student')->assertDontSee('PRIVATE-GUARDIAN-SECRET')->assertDontSee('98765')->assertDontSee('54321')
                ->assertDontSee('name="password"',false)->assertDontSee('name="teacher_name"',false);
            $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        }
    }
    public function test_teacher_can_record_zero_score_and_replay_without_duplicate_and_see_result_in_reports(): void {
        $this->actingAs($this->teacherUser);$payload=$this->resultPayload();
        $this->post('/erp/teacher/results',$payload)->assertRedirect(route('erp.teacher.index',['section'=>'results']));
        $this->post('/erp/teacher/results',$payload)->assertRedirect();
        $this->assertDatabaseCount('erp_marks',1);
        $this->assertDatabaseHas('erp_marks',['score'=>0,'maximum'=>20,'created_by'=>$this->teacherUser->id,'unit_id'=>$this->unit]);
        $this->get('/erp/teacher?section=reports')->assertOk()->assertSee('Single-login test')->assertSee('Progress note');
        $this->post('/erp/teacher/results',array_merge($payload,['score'=>'1']))->assertStatus(409);
    }
    public function test_teacher_cannot_write_another_assignment_or_forge_identity(): void {
        $this->actingAs($this->teacherUser);
        $payload=$this->resultPayload(['enrollment_id'=>DB::table('erp_lessons')->where('id',$this->otherLesson)->value('enrollment_id'),'teacher_id'=>$this->teacher,'unit_id'=>$this->unit]);
        $this->post('/erp/teacher/results',$payload)->assertForbidden();$this->assertDatabaseCount('erp_marks',0);
    }
    public function test_invalid_scores_preserve_input_and_inactive_assignments_reject_results(): void {
        $this->actingAs($this->teacherUser);$payload=$this->resultPayload(['score'=>'21']);
        $this->from('/erp/teacher?section=results')->post('/erp/teacher/results',$payload)->assertSessionHasErrors('score')->assertSessionHasInput('assessment','Single-login test');
        DB::table('erp_enrollments')->where('id',$payload['enrollment_id'])->update(['status'=>'ended']);
        $this->post('/erp/teacher/results',$this->resultPayload())->assertStatus(422);$this->assertDatabaseCount('erp_marks',0);
    }
    public function test_account_unlink_revokes_results_and_dashboard_immediately(): void {
        $this->actingAs($this->teacherUser);DB::table('erp_teachers')->where('id',$this->teacher)->update(['user_id'=>null]);
        $this->get('/erp/teacher')->assertForbidden();$this->post('/erp/teacher/results',$this->resultPayload())->assertForbidden();
    }
    public function test_report_date_filters_and_pagination_keep_teacher_scope(): void {
        $this->actingAs($this->teacherUser);
        for($i=0;$i<26;$i++) $this->post('/erp/teacher/results',$this->resultPayload(['assessment'=>'Assessment '.$i]))->assertRedirect();
        $this->get('/erp/teacher?section=results')->assertOk()->assertSee('Assessment 25')->assertDontSee('>Assessment 0<',false);
        $this->get('/erp/teacher?section=reports&results_page=2')->assertOk()->assertSee('Assessment 0');
        $this->get('/erp/teacher?section=reports&from=2020-01-01&through=2020-01-31')->assertOk()->assertDontSee('Single-login test')->assertSee('No results in this date range');
    }

    public function test_filters_use_teacher_timezone_and_reject_other_students(): void {
        $this->actingAs($this->teacherUser);
        DB::table('erp_lessons')->where('id',$this->ownLesson)->update(['starts_at'=>'2026-10-24 20:00:00','ends_at'=>'2026-10-24 21:00:00']);
        $this->get('/erp/teacher?section=timetable&from=2026-10-25&through=2026-10-25')->assertOk()->assertSee('Owned Synthetic Student');
        $this->get('/erp/teacher?section=timetable&from=2026-10-24&through=2026-10-24')->assertOk()->assertSee('No lessons in this date range.');
        $otherStudent=DB::table('erp_lessons')->where('id',$this->otherLesson)->value('student_id');
        $this->get('/erp/teacher?section=reports&student='.$otherStudent)->assertForbidden();
        $ownStudent=DB::table('erp_lessons')->where('id',$this->ownLesson)->value('student_id');
        $this->get('/erp/teacher?section=reports&student='.$ownStudent)->assertOk()->assertDontSee('Hidden Other Student');
    }

    public function test_assessment_today_uses_the_teacher_local_date(): void {
        \Carbon\CarbonImmutable::setTestNow(\Carbon\CarbonImmutable::parse('2026-10-25 20:30:00','UTC'));
        try {
            $this->actingAs($this->teacherUser);
            $this->post('/erp/teacher/results',$this->resultPayload(['assessed_on'=>'2026-10-26']))->assertRedirect();
            $this->postJson('/erp/teacher/results',$this->resultPayload(['assessed_on'=>'2026-10-27']))->assertUnprocessable()->assertJsonValidationErrors('assessed_on');
        } finally { \Carbon\CarbonImmutable::setTestNow(); }
    }
}
