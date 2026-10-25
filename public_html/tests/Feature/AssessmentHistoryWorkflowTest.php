<?php
namespace Tests\Feature;

use App\Models\Marklist;
use App\Models\MarklistReport;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssessmentHistoryWorkflowTest extends TestCase
{
    public function test_saved_result_is_immediately_reportable_without_sheets_and_retries_do_not_duplicate(): void
    {
        $this->createMaster(); $subject = $this->createSubject();
        $payload = ['teacher_name'=>'QA Teacher','teacher_code'=>'T-QA','student_name'=>'QA Student','student_code'=>'S-QA',
            'password'=>'01/01/1990','subject'=>$subject->id,'chapter'=>'Mobile test','total_mark'=>'20','mark_scored'=>'0',
            'examination_date'=>'2026-10-25','mode_of_exam'=>'Written (live)','remark'=>null,'submission_key'=>(string) Str::uuid()];
        $this->postJson('/upload-result', array_merge($payload,['mark_scored'=>'21']))->assertStatus(422);
        $this->postJson('/upload-result', $payload)->assertOk()->assertJsonPath('success',true);
        $this->postJson('/upload-result', $payload)->assertOk();
        $this->assertDatabaseCount('marklists',1);
        $this->actingAs($this->createAdmin());
        $access=['teacher_name'=>'QA Teacher','dob'=>'01/01/1990','student_name'=>'all'];
        $this->postJson('/get-students-mark',$access)->assertOk()->assertJsonPath('students.0.student_name','QA Student');
        $this->postJson('/markreport',$access)->assertOk()->assertJsonPath('recordsFiltered',1)
            ->assertJsonPath('data.0.mark_scored','0')->assertJsonPath('data.0.subject','QA Mathematics');
        $this->postJson('/markreport',array_merge($access,['fromdate'=>'2026-10-26']))->assertOk()->assertJsonPath('recordsFiltered',0);
        $this->postJson('/markreport',array_merge($access,['student_name'=>'Unassigned']))->assertOk()->assertJsonPath('recordsFiltered',0);
        $this->postJson('/markreport',array_merge($access,['dob'=>'wrong']))->assertOk();
    }

    public function test_live_and_imported_results_are_combined_scoped_and_exact_copies_are_not_counted_twice(): void
    {
        $this->createMaster();$subject=$this->createSubject();$this->actingAs($this->createAdmin());
        $fields=['teacher_name'=>'QA Teacher','teacher_code'=>'T-QA','student_name'=>'QA Student','student_code'=>'S-QA',
            'chapter'=>'Algebra','mark_scored'=>'8','total_mark'=>'10','mode_of_exam'=>'Written (live)',
            'examination_date'=>'2026-10-25','remark'=>null];
        $saved=Marklist::create($fields+['subject'=>$subject->id]);
        MarklistReport::create($fields+['subject'=>'QA Mathematics','date_submitted'=>$saved->created_at->format('Y-m-d H:i:s')]);
        MarklistReport::create(array_merge($fields,['chapter'=>'Older result','subject'=>'History','date_submitted'=>'2026-09-01 12:00:00']));
        Marklist::create(array_merge($fields,['subject'=>$subject->id,'teacher_code'=>'OTHER','student_name'=>'Private student']));
        $this->postJson('/markreport',['teacher_name'=>'QA Teacher','dob'=>'01/01/1990'])->assertOk()
            ->assertJsonPath('recordsFiltered',3)->assertJsonCount(3,'data')->assertSee('Private student');
    }
}
