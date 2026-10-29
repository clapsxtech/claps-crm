<?php
namespace Tests\Feature;

use App\Modules\Erp\Services\SalesPipeline;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ErpSalesCrmTest extends TestCase
{
    private int $unit;
    private $sales;
    private $colleague;
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-26 20:00:00','UTC'));
        $this->unit = DB::table('erp_units')->insertGetId(['code'=>'CRM','name'=>'Claps Learning','timezone'=>'Asia/Kolkata']);
        $this->sales = $this->createMember(['name'=>'Asha Menon']);
        $this->colleague = $this->createMember(['name'=>'Priya Nair']);
        foreach ([$this->sales,$this->colleague] as $member) DB::table('erp_memberships')->insert(['unit_id'=>$this->unit,'user_id'=>$member->id,'role'=>'sales_followups']);
        $this->actingAs($this->sales);
    }
    private function lead(string $name, array $values = []): int
    {
        return DB::table('erp_leads')->insertGetId(array_merge(['unit_id'=>$this->unit,'name'=>$name,'phone'=>'919876543210','country'=>'IN','enquiry_date'=>'2026-10-26','grade'=>'Grade 8','subject'=>'Mathematics','source'=>'Meta Ads','assigned_to'=>$this->sales->id,'created_by'=>$this->sales->id,'created_at'=>now(),'updated_at'=>now()],$values));
    }
    private function demo(int $lead, array $values = []): int
    {
        return DB::table('erp_demos')->insertGetId(array_merge(['unit_id'=>$this->unit,'lead_id'=>$lead,'scheduled_on'=>'2026-10-28','scheduled_time'=>'17:30','created_by'=>$this->sales->id,'status'=>'SCHEDULED'],$values));
    }
    private function page(array $query = [])
    {
        return $this->get(route('erp.business',array_merge([$this->unit,'admissions'],$query)));
    }
    private function action(string $action, array $data)
    {
        return $this->post(route('erp.business.mutate',[$this->unit,'admission-'.$action]),$data);
    }
    private function preview(string $name, $response): void
    {
        if ($dir=getenv('ERP_MOBILE_PREVIEW_DIR')) {
            if (!is_dir($dir)) mkdir($dir,0700,true);
            file_put_contents($dir.'/'.$name.'.html',$response->getContent());
        }
    }
    public function test_pipeline_counts_filters_and_detail_use_the_same_scoped_stage_definition(): void
    {
        $new=$this->lead('Ananya Sharma',['is_hot'=>1]);
        $contacted=$this->lead('Rohan Nair',['next_follow_up_on'=>'2026-10-27']);
        $scheduled=$this->lead('Diya Menon',['next_follow_up_on'=>'2026-10-26','interest_status'=>'Interested']);
        $attended=$this->lead('Arjun Kumar',['next_follow_up_on'=>'2026-10-27']);
        $joined=$this->lead('Sara Joseph',['status'=>'JOINED','joined_on'=>'2026-10-26']);
        $lost=$this->lead('Aditya Rao',['status'=>'LOST','lost_reason'=>'Timing did not work']);
        $hidden=$this->lead('Private other counsellor',['assigned_to'=>$this->colleague->id]);
        $this->action('followup',['lead_id'=>$contacted,'channel'=>'call','outcome'=>'Interested','note'=>'Parent would like a demo after school.','next_due_on'=>'2026-10-27'])->assertSessionHasNoErrors();
        $this->demo($scheduled); $this->demo($attended,['status'=>'ATTENDED']);
        $this->demo($joined); $this->demo($lost); $this->demo($hidden);
        $expected=['new'=>1,'contacted'=>1,'demo_scheduled'=>1,'demo_attended'=>1,'joined'=>1,'lost'=>1];
        $page=$this->page(); $page->assertOk()->assertViewHas('stageCounts',$expected)->assertDontSee('Private other counsellor');
        $this->preview('crm-pipeline',$page);
        foreach (['new'=>$new,'contacted'=>$contacted,'demo_scheduled'=>$scheduled,'demo_attended'=>$attended,'joined'=>$joined,'lost'=>$lost] as $stage=>$id) {
            $this->page(['stage'=>$stage])->assertOk()->assertViewHas('leads',fn($rows)=>$rows->total()===1 && (int)$rows->first()->id===$id);
        }
        $this->page(['hot'=>1])->assertOk()->assertViewHas('leads',fn($rows)=>$rows->total()===1 && (int)$rows->first()->id===$new);
        $detail=$this->page(['lead_id'=>$contacted,'stage'=>'lost','q'=>'not matching']);
        $detail->assertOk()->assertViewHas('leads',null)->assertSee('Parent would like a demo after school.')->assertDontSee('Ananya Sharma')->assertSee('tel:+919876543210',false)->assertSee('https://wa.me/919876543210',false);
        $this->preview('crm-detail',$detail);
        $this->page(['lead_id'=>$hidden])->assertNotFound();
        $this->preview('crm-new',$this->page(['view'=>'new'])->assertOk());
        $this->preview('crm-reports',$this->page(['view'=>'reports'])->assertOk());
        $this->preview('crm-demos',$this->page(['view'=>'demos'])->assertOk());
    }
    public function test_today_uses_unit_date_and_reminders_are_scoped_and_paginated(): void
    {
        $lead=$this->lead('Today conversation',['next_follow_up_on'=>'2026-10-27']);
        $this->lead('Yesterday conversation',['next_follow_up_on'=>'2026-10-26']);
        $this->lead('Unscheduled conversation');
        $hidden=$this->lead('Hidden reminders',['assigned_to'=>$this->colleague->id]);
        foreach ([$lead,$hidden] as $id) foreach (range(1,21) as $i) DB::table('erp_lead_reminders')->insert(['unit_id'=>$this->unit,'lead_id'=>$id,'due_on'=>'2026-10-27','due_time'=>'10:00','text'=>'Check parent feedback '.$i,'created_by'=>$this->sales->id]);
        $future=DB::table('erp_lead_reminders')->insertGetId(['unit_id'=>$this->unit,'lead_id'=>$lead,'due_on'=>'2026-10-30','text'=>'Future reminder','created_by'=>$this->sales->id]);
        $page=$this->page(['view'=>'today']);
        $page->assertOk()->assertViewHas('leads',fn($r)=>$r->total()===1 && (int)$r->first()->id===$lead)->assertViewHas('reminders',fn($r)=>$r->total()===21 && $r->count()===20)->assertDontSee('Hidden reminders')->assertDontSee('Future reminder');
        $this->preview('crm-today',$page);
        $page=$this->page(['view'=>'reminders','reminders_page'=>2]);
        $page->assertOk()->assertViewHas('reminders',fn($r)=>$r->total()===22 && $r->count()===2)->assertSee('Future reminder');
        $this->preview('crm-reminders',$page);
        $this->action('complete-reminder',['reminder_id'=>$future,'return_view'=>'reminders'])->assertRedirect(route('erp.business',[$this->unit,'admissions','view'=>'reminders']));
        $this->assertDatabaseHas('erp_lead_reminders',['id'=>$future,'is_completed'=>1]);
    }
    public function test_demos_include_leads_beyond_the_first_pipeline_page_without_leaking_other_owners(): void
    {
        $old=$this->lead('Old lead with upcoming demo');
        foreach(range(1,25) as $i) $this->lead('Newer lead '.$i);
        $this->demo($old);
        $hidden=$this->lead('Private demo',['assigned_to'=>$this->colleague->id]); $this->demo($hidden);
        $this->page()->assertOk()->assertViewHas('leads',fn($r)=>!$r->contains('id',$old));
        $this->page(['view'=>'demos'])->assertOk()->assertSee('Old lead with upcoming demo')->assertDontSee('Private demo')->assertViewHas('demos',fn($r)=>$r->total()===1);
    }
    public function test_recipient_can_accept_transfer_before_accessing_the_lead_and_sender_loses_access(): void
    {
        $lead=$this->lead('Incoming enquiry');
        $this->action('transfer',['lead_id'=>$lead,'to_user_id'=>$this->colleague->id])->assertSessionHasNoErrors();
        $transfer=DB::table('erp_lead_transfers')->value('id');
        $this->action('resolve-transfer',['transfer_id'=>$transfer,'status'=>'accepted'])->assertForbidden();
        $this->actingAs($this->colleague);
        $this->page(['lead_id'=>$lead])->assertNotFound();
        $page=$this->page(['view'=>'transfers']);
        $page->assertOk()->assertSee('Incoming enquiry')->assertSee('Accept')->assertDontSee('919876543210');
        $this->preview('crm-transfers',$page);
        $this->action('resolve-transfer',['transfer_id'=>$transfer,'status'=>'accepted','return_view'=>'transfers'])->assertRedirect(route('erp.business',[$this->unit,'admissions','view'=>'transfers']));
        $this->page(['lead_id'=>$lead])->assertOk();
        $this->actingAs($this->sales); $this->page(['lead_id'=>$lead])->assertNotFound();
    }
    public function test_mobile_save_returns_to_the_record_and_contact_numbers_are_not_guessed(): void
    {
        $this->action('create-lead',['name'=>'New mobile enquiry','phone'=>'9000012345','country'=>'IN','return_view'=>'detail'])->assertSessionHasNoErrors()->assertRedirect(route('erp.business',[$this->unit,'admissions','view'=>'pipeline','lead_id'=>DB::table('erp_leads')->value('id')]));
        $lead=DB::table('erp_leads')->first();
        $this->assertSame('https://wa.me/919000012345',SalesPipeline::contact($lead)['whatsapp']);
        $this->assertNull(SalesPipeline::contact((object)['phone'=>'9000012345','country'=>null])['whatsapp']);
        $this->assertSame('https://wa.me/447700900123',SalesPipeline::contact((object)['phone'=>'+44 7700 900123','country'=>'GB'])['whatsapp']);
        $this->action('followup',['lead_id'=>$lead->id,'channel'=>'whatsapp','outcome'=>'Interested','note'=>'Discussed course','next_due_on'=>'2026-10-28','return_view'=>'detail'])->assertRedirect(route('erp.business',[$this->unit,'admissions','view'=>'pipeline','lead_id'=>$lead->id]));
        $this->page(['view'=>'unsupported'])->assertSessionHasErrors('view');
    }
}
