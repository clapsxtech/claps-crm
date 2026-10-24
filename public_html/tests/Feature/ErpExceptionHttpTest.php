<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** HTTP request, authorization and rendered-register coverage using only synthetic records. */
class ErpExceptionHttpTest extends TestCase
{
    private $unit;
    private $otherUnit;
    private $student;
    private $teacher;
    private $otherStudent;
    private $otherTeacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unit=DB::table('erp_units')->insertGetId(['code'=>'EX-AA','name'=>'Exception AA','timezone'=>'UTC']);
        $this->otherUnit=DB::table('erp_units')->insertGetId(['code'=>'EX-BB','name'=>'Exception BB','timezone'=>'UTC']);
        $admin=$this->createAdmin();
        DB::table('erp_memberships')->insert(['user_id'=>$admin->id,'unit_id'=>$this->unit,'role'=>'admin']);
        $this->actingAs($admin);
        foreach ([$this->unit,$this->otherUnit] as $index=>$unit) {
            $student=DB::table('erp_students')->insertGetId(['unit_id'=>$unit,'code'=>'EX-S'.$index,'name'=>'Exception student '.$index,'registered_on'=>'2026-01-01','timezone'=>'Asia/Kolkata','status'=>'active','grade'=>'8','syllabus'=>'Synthetic']);
            $teacher=DB::table('erp_teachers')->insertGetId(['code'=>'EX-T'.$index,'name'=>'Exception teacher '.$index,'timezone'=>'Asia/Kolkata','status'=>'active']);
            DB::table('erp_teacher_units')->insert(['teacher_id'=>$teacher,'unit_id'=>$unit]);
            if ($index===0) { $this->student=$student; $this->teacher=$teacher; }
            else { $this->otherStudent=$student; $this->otherTeacher=$teacher; }
        }
    }

    private function payload(array $overrides=[]): array
    {
        return array_merge(['student_id'=>$this->student,'teacher_id'=>'','date'=>'2027-01-05','start_time'=>'11:00','end_time'=>'12:00','timezone'=>'Asia/Kolkata','reason'=>'Synthetic clinic appointment'],$overrides);
    }

    public function test_partial_request_is_idempotent_and_invalid_or_changed_retries_do_not_write(): void
    {
        $url='/erp/units/'.$this->unit.'/exception';
        $saved=$this->postJson($url,$this->payload())->assertOk()->json('result');
        $this->assertSame('2027-01-05 05:30:00',$saved['starts_at']);
        $this->assertSame('2027-01-05 06:30:00',$saved['ends_at']);
        $this->assertSame('Asia/Kolkata',$saved['timezone']);
        $this->assertSame($saved['id'],$this->postJson($url,$this->payload())->assertOk()->json('result.id'));
        foreach ([['reason'=>'Changed reason'],['start_time'=>'11:30'],['end_time'=>''],['timezone'=>'']] as $change) {
            $this->postJson($url,$this->payload($change))->assertUnprocessable()->assertJsonValidationErrors('schedule');
        }
        $this->assertSame(1,DB::table('erp_exceptions')->count());
        $this->assertSame(1,DB::table('erp_audit')->where('action','availability.exception_created')->count());
        $this->assertSame(0,DB::table('erp_lessons')->count());
        $this->assertSame(0,DB::table('erp_ledger')->count());
    }

    public function test_blank_time_form_remains_profile_local_full_day_and_explicit_partial_form_redirects(): void
    {
        $url='/erp/units/'.$this->unit.'/exception';
        $this->post($url,$this->payload(['start_time'=>'','end_time'=>'','timezone'=>'']))->assertRedirect('/erp/units/'.$this->unit)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('erp_exceptions',['student_id'=>$this->student,'date'=>'2027-01-05','starts_at'=>'2027-01-04 18:30:00','ends_at'=>'2027-01-05 18:30:00','timezone'=>'Asia/Kolkata']);
        $this->post($url,$this->payload(['student_id'=>'','teacher_id'=>$this->teacher,'timezone'=>'UTC']))->assertRedirect('/erp/units/'.$this->unit)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('erp_exceptions',['teacher_id'=>$this->teacher,'starts_at'=>'2027-01-05 11:00:00','ends_at'=>'2027-01-05 12:00:00','timezone'=>'UTC']);
    }

    public function test_workspace_and_business_registers_show_local_intervals_snapshots_and_legacy_days(): void
    {
        $url='/erp/units/'.$this->unit.'/exception';
        $this->postJson($url,$this->payload())->assertOk();
        $this->postJson($url,$this->payload(['student_id'=>'','teacher_id'=>$this->teacher,'start_time'=>'23:00','end_time'=>'00:00']))->assertOk();
        DB::table('erp_exceptions')->insert(['unit_id'=>$this->unit,'student_id'=>$this->student,'date'=>'2027-01-06','reason'=>'Synthetic legacy holiday']);
        $workspace=$this->get('/erp/units/'.$this->unit)->assertOk();
        $workspace->assertSee('Record full-day or timed unavailability')->assertSee('00:00 means next midnight')->assertSee('Timezone (required for timed exceptions)');
        foreach ([$workspace,$this->get('/erp/units/'.$this->unit.'/business/timetable')->assertOk()] as $response) {
            $response->assertSee('11:00–12:00')->assertSee('23:00–00:00')->assertSee('(next day)')->assertSee('Asia/Kolkata');
            $response->assertSee('2027-01-05 05:30:00')->assertSee('2027-01-05 06:30:00')->assertSee('participant timezone (legacy)');
        }
        $this->assertSame(0,DB::table('erp_lessons')->count()); $this->assertSame(0,DB::table('erp_ledger')->count());
    }

    public function test_exception_http_scope_rejects_other_unit_people_and_non_admin_mutation(): void
    {
        $url='/erp/units/'.$this->unit.'/exception';
        $this->postJson('/erp/units/'.$this->otherUnit.'/exception',$this->payload())->assertForbidden();
        $this->postJson($url,$this->payload(['student_id'=>$this->otherStudent]))->assertUnprocessable();
        $this->postJson($url,$this->payload(['student_id'=>'','teacher_id'=>$this->otherTeacher]))->assertUnprocessable();
        $this->postJson($url,$this->payload(['teacher_id'=>$this->teacher]))->assertUnprocessable();
        $staff=$this->createMember();
        DB::table('erp_memberships')->insert(['user_id'=>$staff->id,'unit_id'=>$this->unit,'role'=>'teacher']);
        $this->actingAs($staff)->postJson($url,$this->payload())->assertForbidden();
        $this->assertSame(0,DB::table('erp_exceptions')->count());
        $this->assertSame(0,DB::table('erp_audit')->count());
        $this->assertSame(0,DB::table('erp_ledger')->count());
    }
}
