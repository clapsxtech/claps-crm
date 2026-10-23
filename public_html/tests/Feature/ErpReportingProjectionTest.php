<?php
namespace Tests\Feature;
use App\Modules\Erp\Services\Reporting;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class ErpReportingProjectionTest extends TestCase
{
    private function entry(array $values): int {
        static $n=0;$n++;
        return DB::table('erp_ledger')->insertGetId($values+['unit_id'=>1,'currency'=>'INR','reference'=>'READ-'.$n,'event_key'=>'read-'.$n,'occurred_at'=>now(),'created_by'=>1]);
    }
    protected function setUp(): void {
        parent::setUp();$this->createAdmin();
        DB::table('erp_units')->insert([['id'=>1,'code'=>'READ-A','name'=>'Read A','timezone'=>'UTC'],['id'=>2,'code'=>'READ-B','name'=>'Read B','timezone'=>'UTC']]);
        DB::table('erp_students')->insert(['id'=>1,'unit_id'=>1,'code'=>'READ-S','name'=>'Read Student','registered_on'=>'2026-01-01','timezone'=>'UTC','status'=>'active','grade'=>'8','syllabus'=>'Synthetic']);
        foreach([1,2] as $id){DB::table('erp_teachers')->insert(['id'=>$id,'code'=>'READ-T'.$id,'name'=>'Read Teacher','timezone'=>'UTC','status'=>'active']);DB::table('erp_teacher_units')->insert(['unit_id'=>$id,'teacher_id'=>$id]);}
    }
    public function test_bulk_teacher_projection_preserves_reversals_openings_and_uses_one_nonlocking_query(): void {
        $earned=$this->entry(['teacher_id'=>1,'kind'=>'teacher_accrual','amount_paise'=>10000]);
        $paid=$this->entry(['teacher_id'=>1,'kind'=>'teacher_payment','amount_paise'=>-4000]);
        $this->entry(['teacher_id'=>1,'kind'=>'reversal','reverses_id'=>$paid,'amount_paise'=>4000]);
        $this->entry(['teacher_id'=>1,'kind'=>'opening_balance','amount_paise'=>2000]);
        $this->entry(['teacher_id'=>2,'unit_id'=>2,'kind'=>'teacher_accrual','amount_paise'=>99000]);
        DB::enableQueryLog();DB::flushQueryLog();$result=(new Reporting())->teacherStatements(1,[1,2]);$queries=DB::getQueryLog();DB::disableQueryLog();
        $this->assertSame(['earned_paise'=>10000,'paid_paise'=>0,'other_adjustments_paise'=>2000,'net_payable_paise'=>12000],$result[1]);
        $this->assertSame(0,$result[2]['net_payable_paise']);$this->assertCount(1,$queries);$this->assertStringNotContainsString('for update',strtolower($queries[0]['query']));
        $this->entry(['teacher_id'=>1,'kind'=>'reversal','reverses_id'=>$earned,'amount_paise'=>-10000]);
        $this->assertSame(0,(new Reporting())->teacherStatements(1,[1])[1]['earned_paise']);
    }
    public function test_bulk_requests_ignore_reversed_receipts_preserve_cancellation_and_scope_in_one_query(): void {
        foreach([1,2,3] as $id)DB::table('erp_payment_requests')->insert(['id'=>$id,'unit_id'=>$id===3?2:1,'student_id'=>1,'reference'=>'READ-REQUEST-'.$id,'amount_paise'=>10000,'status'=>$id===2?'cancelled':'pending','requested_on'=>'2026-01-01','created_by'=>1]);
        $receipt=$this->entry(['student_id'=>1,'kind'=>'payment','amount_paise'=>10000]);
        DB::table('erp_request_allocations')->insert(['unit_id'=>1,'request_id'=>1,'payment_id'=>$receipt,'amount_paise'=>10000]);
        $this->assertSame('fulfilled',(new Reporting())->requestSummaries(1,[1])[1]['status']);
        $this->entry(['student_id'=>1,'kind'=>'reversal','reverses_id'=>$receipt,'amount_paise'=>-10000]);
        $partial=$this->entry(['student_id'=>1,'kind'=>'payment','amount_paise'=>3000]);DB::table('erp_request_allocations')->insert(['unit_id'=>1,'request_id'=>2,'payment_id'=>$partial,'amount_paise'=>3000]);
        DB::enableQueryLog();DB::flushQueryLog();$result=(new Reporting())->requestSummaries(1,[1,2,3]);$queries=DB::getQueryLog();DB::disableQueryLog();
        $this->assertSame(['requested_paise'=>10000,'covered_paise'=>0,'outstanding_paise'=>10000,'status'=>'pending'],$result[1]);$this->assertSame('cancelled',$result[2]['status']);$this->assertSame(3000,$result[2]['covered_paise']);$this->assertSame(7000,$result[2]['outstanding_paise']);$this->assertArrayNotHasKey(3,$result);$this->assertCount(1,$queries);$this->assertStringNotContainsString('for update',strtolower($queries[0]['query']));
    }
}
