<?php
namespace Tests\Feature;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ErpAcademicsTest extends TestCase
{
    private array $own;
    private array $other;
    private $admin;
    protected function setUp(): void
    {
        parent::setUp();
        $this->admin=$this->createAdmin();
        $this->own=$this->fixture('OWN');$this->other=$this->fixture('PRIVATE');
        DB::table('erp_memberships')->insert(['unit_id'=>$this->own['unit'],'user_id'=>$this->admin->id,'role'=>'admin']);
        $this->actingAs($this->admin);
    }
    private function fixture(string $code): array
    {
        $unit=DB::table('erp_units')->insertGetId(['code'=>$code,'name'=>$code.' unit','timezone'=>'UTC']);
        $student=DB::table('erp_students')->insertGetId(['unit_id'=>$unit,'code'=>$code.'-student','name'=>$code.' student','registered_on'=>'2026-01-01','timezone'=>'UTC','grade'=>'8','syllabus'=>'Test']);
        $teacher=DB::table('erp_teachers')->insertGetId(['code'=>$code.'-teacher','name'=>$code.' teacher','timezone'=>'UTC']);
        DB::table('erp_teacher_units')->insert(['teacher_id'=>$teacher,'unit_id'=>$unit]);
        $subject=DB::table('erp_subjects')->insertGetId(['name'=>$code.' subject']);
        DB::table('erp_eligibilities')->insert(['teacher_id'=>$teacher,'subject_id'=>$subject,'grade'=>'8','syllabus'=>'Test']);
        $enrollment=DB::table('erp_enrollments')->insertGetId(['unit_id'=>$unit,'student_id'=>$student,'teacher_id'=>$teacher,'subject_id'=>$subject,'starts_on'=>'2026-01-01','student_rate_paise'=>10000,'teacher_rate_paise'=>8000]);
        return compact('unit','student','teacher','subject','enrollment');
    }
    private function page(): string { return route('erp.business',[$this->own['unit'],'academics']); }
    private function record(array $fixture, string $assessment='Algebra', string $date='2026-01-15'): int
    {
        return DB::table('erp_marks')->insertGetId(['unit_id'=>$fixture['unit'],'enrollment_id'=>$fixture['enrollment'],'assessment'=>$assessment,'assessed_on'=>$date,'score'=>8,'maximum'=>10,'notes'=>'=untrusted formula','created_by'=>$this->admin->id]);
    }
    public function test_named_results_and_subjects_are_scoped_even_for_malformed_cross_unit_records(): void
    {
        $this->record($this->own);$this->record($this->other,'PRIVATE assessment');
        $malformed=$this->own;$malformed['enrollment']=$this->other['enrollment'];$this->record($malformed,'PRIVATE malformed');
        $this->get($this->page())->assertOk()->assertSee('OWN student')->assertSee('OWN teacher')->assertSee('OWN subject')->assertSee('80.0%')->assertDontSee('PRIVATE assessment')->assertDontSee('PRIVATE malformed')->assertSee('Save assessment');
        $this->get(route('erp.business',[$this->other['unit'],'academics']))->assertOk();
        $this->get(route('erp.academics.export',$this->other['unit']))->assertOk();
        $this->actingAs($this->createMember())->get($this->page())->assertForbidden();
    }
    public function test_recording_accepts_zero_and_returns_to_academics_without_financial_writes(): void
    {
        $payload=['return_page'=>'academics','enrollment_id'=>$this->own['enrollment'],'assessment'=>'Zero score','assessed_on'=>'2026-01-15','score'=>0,'maximum'=>20];
        $this->post(route('erp.mutate',[$this->own['unit'],'mark']),$payload)->assertRedirect($this->page())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('erp_marks',['assessment'=>'Zero score','score'=>0,'unit_id'=>$this->own['unit']]);
        $this->assertDatabaseCount('erp_ledger',0);
        $payload['enrollment_id']=$this->other['enrollment'];
        $this->postJson(route('erp.mutate',[$this->own['unit'],'mark']),$payload)->assertUnprocessable();
        $payload['enrollment_id']=$this->own['enrollment'];$payload['score']=21;
        $this->postJson(route('erp.mutate',[$this->own['unit'],'mark']),$payload)->assertUnprocessable();
        $this->assertDatabaseCount('erp_marks',1);
    }
    public function test_filter_and_csv_export_share_scope_names_and_formula_protection(): void
    {
        $this->record($this->own,'Algebra','2026-01-15');$this->record($this->own,'Geometry','2026-02-15');$this->record($this->other);
        $filters=['q'=>'Algebra','from'=>'2026-01-01','through'=>'2026-01-31'];
        $this->get($this->page().'?'.http_build_query($filters))->assertOk()->assertSee('Algebra')->assertDontSee('Geometry')->assertViewHas('results',fn($rows)=>$rows->total()===1);
        $response=$this->get(route('erp.academics.export',['unit'=>$this->own['unit']]+$filters))->assertOk();
        $csv=$response->streamedContent();
        $this->assertStringContainsString('OWN student',$csv);$this->assertStringContainsString('OWN teacher',$csv);$this->assertStringContainsString('80.00',$csv);$this->assertStringContainsString("'=untrusted formula",$csv);$this->assertStringNotContainsString('PRIVATE',$csv);$this->assertStringNotContainsString('Geometry',$csv);
        $this->getJson($this->page().'?from=2026-02-01&through=2026-01-01')->assertUnprocessable();
    }
    public function test_eligibility_reuses_audited_workflow_and_returns_to_the_new_screen(): void
    {
        $payload=['return_page'=>'academics','teacher_id'=>$this->own['teacher'],'subject'=>'Science','grade'=>'8','syllabus'=>'Test'];
        $this->post(route('erp.mutate',[$this->own['unit'],'eligibility']),$payload)->assertRedirect($this->page())->assertSessionHasNoErrors();
        $this->get($this->page())->assertSee('Science');
        $payload['teacher_id']=$this->other['teacher'];
        $this->postJson(route('erp.mutate',[$this->own['unit'],'eligibility']),$payload)->assertUnprocessable();
    }
}
