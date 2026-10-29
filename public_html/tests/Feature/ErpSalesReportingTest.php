<?php
namespace Tests\Feature;

use App\Modules\Erp\Services\{Admissions, SalesReporting};
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ErpSalesReportingTest extends TestCase
{
    private $admin;
    private $sales;
    private int $unit;
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-26 20:00:00', 'UTC'));
        $this->admin=$this->createAdmin();
        $this->sales=$this->createMember(['id'=>21,'name'=>'Reporting Counselor']);
        $this->unit=DB::table('erp_units')->insertGetId(['code'=>'REPORT','name'=>'Report team','timezone'=>'Asia/Kolkata']);
        DB::table('erp_memberships')->insert(['unit_id'=>$this->unit,'user_id'=>$this->sales->id,'role'=>'sales_followups']);
    }
    protected function tearDown(): void { $this->travelBack(); parent::tearDown(); }
    private function lead(string $name, int $owner, string $source='Campaign A'): object
    {
        return (new Admissions())->createLead($this->unit,$owner,['name'=>$name,'phone'=>'919000012345','create_separate'=>true,'source'=>$source,'enquiry_date'=>'2026-10-27']);
    }
    private function report($user, string $date='2026-10-27'): array
    {
        return (new SalesReporting())->read($this->unit,$user,['report_from'=>$date,'report_to'=>$date]);
    }
    public function test_local_date_actor_credit_cohort_counts_and_visibility(): void
    {
        $service=new Admissions();
        $lead=$this->lead('Visible child',$this->sales->id);
        $hidden=$this->lead('Hidden child',$this->admin->id,'Private campaign');
        foreach ([1,2] as $i) $service->scheduleDemo($this->unit,$this->sales->id,['lead_id'=>$lead->id,'scheduled_on'=>'2026-10-29','scheduled_time'=>'17:30']);
        $service->recordFollowup($this->unit,$this->sales->id,['lead_id'=>$lead->id,'channel'=>'call','note'=>'Parent called','outcome'=>'Interested','next_due_on'=>'2026-10-23']);
        $report=$this->report($this->sales);
        $this->assertCount(1,$report['campaigns']);
        $this->assertSame(1,(int)$report['campaigns'][0]->enquiries);
        $this->assertSame(1,(int)$report['campaigns'][0]->demo_leads);
        $this->assertSame(2,(int)$report['staff'][0]->demos);
        $this->assertSame(1,(int)$report['due']->older);
        $this->assertNull($report['collections']);
        $this->assertCount(0,$this->report($this->sales,'2026-10-26')['staff']);
        DB::table('erp_leads')->where('id',$lead->id)->update(['assigned_to'=>$this->admin->id]);
        $this->assertCount(0,$this->report($this->sales)['staff']);
        $adminReport=$this->report($this->admin);
        $this->assertSame(2,(int)$adminReport['staff']->firstWhere('actor_id',$this->sales->id)->demos);
        $this->actingAs($this->admin)->get(route('erp.business',[$this->unit,'admissions','report'=>1,'report_from'=>'2026-10-27','report_to'=>'2026-10-27']))->assertOk()->assertSee('Campaign outcomes')->assertSee('Reporting Counselor');
        $this->get(route('erp.business',[$this->unit,'admissions','report'=>1,'report_from'=>'2026-01-01','report_to'=>'2026-10-27']))->assertStatus(422);
    }
    public function test_receipts_are_not_multiplied_and_reversals_use_their_own_date(): void
    {
        $lead=$this->lead('Payer',$this->admin->id);
        $student=(new Admissions())->convertLead($this->unit,$this->admin->id,['lead_id'=>$lead->id,'payment_amount_paise'=>10000,'payment_reference'=>'REPORT-RECEIPT']);
        $payment=DB::table('erp_ledger')->where('kind','payment')->first();
        $second=$this->lead('Another enquiry',$this->admin->id);
        DB::table('erp_leads')->where('id',$second->id)->update(['student_id'=>$student->id]);
        $this->assertSame(10000,(int)$this->report($this->admin)['collections'][0]->net_paise);
        DB::table('erp_ledger')->insert(['unit_id'=>$this->unit,'student_id'=>$student->id,'kind'=>'reversal','amount_paise'=>-10000,'currency'=>'INR','reference'=>'REPORT-REV','event_key'=>'report-rev','reverses_id'=>$payment->id,'occurred_at'=>\Carbon\CarbonImmutable::parse('2026-10-27 20:00:00','UTC')->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),'created_by'=>$this->admin->id]);
        $this->assertSame(10000,(int)$this->report($this->admin)['collections'][0]->net_paise);
        $this->assertSame(-10000,(int)$this->report($this->admin,'2026-10-28')['collections'][0]->net_paise);
    }
    public function test_handoff_survives_conversion_and_is_visible_in_matching_and_student_profile(): void
    {
        $lead=$this->lead('Handoff child',$this->sales->id);
        $this->actingAs($this->sales)->postJson(route('erp.business.mutate',[$this->unit,'admission-update-lead']),['lead_id'=>$lead->id,'learning_requirement'=>'Practice fractions','concern'=>'Needs patient explanations'])->assertOk();
        $this->get(route('erp.business',[$this->unit,'allocation','lead_id'=>$lead->id]))->assertOk()->assertSee('Practice fractions')->assertSee('Needs patient explanations');
        $student=(new Admissions())->convertLead($this->unit,$this->sales->id,['lead_id'=>$lead->id]);
        $this->actingAs($this->admin)->get(route('erp.student-profile',[$this->unit,$student->id]))->assertOk()->assertSee('Practice fractions')->assertSee('Needs patient explanations');
        $this->assertDatabaseHas('erp_lead_activities',['lead_id'=>$lead->id,'kind'=>'edited']);
    }
}
