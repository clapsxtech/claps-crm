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
        $this->get('/erp/teacher')->assertOk()->assertSee('My lessons and attendance')->assertSee('Owned Synthetic Student')->assertSee('Asia/Kolkata')->assertSee('Submit attendance')->assertDontSee('Hidden Other Student')->assertDontSee('PRIVATE-GUARDIAN-SECRET')->assertDontSee('private-meeting')->assertDontSee('98765')->assertDontSee('54321');
        $this->post('/erp/teacher/attendance',['lesson_id'=>$this->ownLesson,'actual_minutes'=>45,'unit_id'=>999,'teacher_id'=>999])->assertRedirect(route('erp.teacher.index'))->assertSessionHas('status');
        $this->assertDatabaseHas('erp_attendance',['lesson_id'=>$this->ownLesson,'unit_id'=>$this->unit,'actual_minutes'=>45,'submitted_by'=>$this->teacherUser->id,'status'=>'submitted']);
        $this->assertSame(0,DB::table('erp_ledger')->count());
        $this->get('/erp/teacher')->assertOk()->assertSee('45 minutes')->assertSee('submitted')->assertDontSee('Submit attendance');
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
}
