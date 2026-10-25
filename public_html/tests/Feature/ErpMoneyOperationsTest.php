<?php
namespace Tests\Feature;

use App\Modules\Erp\Services\Finance;
use App\Modules\Erp\Services\MoneyOperations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ErpMoneyOperationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAdmin();
        DB::table('erp_units')->insert([['id'=>1,'code'=>'MONEY-A','name'=>'Synthetic A','timezone'=>'UTC'],['id'=>2,'code'=>'MONEY-B','name'=>'Synthetic B','timezone'=>'UTC']]);
        foreach ([1,2] as $id) DB::table('erp_students')->insert(['id'=>$id,'unit_id'=>1,'code'=>'MONEY-S'.$id,'name'=>'Synthetic Student '.$id,'registered_on'=>'2026-01-01','timezone'=>'UTC','status'=>'active','grade'=>'8','syllabus'=>'Synthetic']);
        DB::table('erp_teachers')->insert(['id'=>1,'code'=>'MONEY-T','name'=>'Synthetic Teacher','timezone'=>'UTC','status'=>'active']);
        DB::table('erp_teacher_units')->insert(['unit_id'=>1,'teacher_id'=>1]);
        DB::table('erp_ledger')->insert(['unit_id'=>1,'teacher_id'=>1,'kind'=>'teacher_accrual','amount_paise'=>10000,'currency'=>'INR','reference'=>'SYN-ACCRUAL','event_key'=>'synthetic-accrual','occurred_at'=>now(),'created_by'=>1,'created_at'=>now(),'updated_at'=>now()]);
    }
    private function invalid(callable $operation): void
    {
        try { $operation(); $this->fail('Expected validation rejection.'); }
        catch (ValidationException $expected) { $this->assertNotEmpty($expected->errors()); }
    }
    private function settlement(array $overrides=[]): array { return array_merge(['teacher_id'=>1,'amount_paise'=>4000,'reference'=>'SYN-SETTLEMENT','paid_on'=>now('UTC')->toDateString(),'reason'=>'Already paid by bank outside this app','already_paid'=>true],$overrides); }
    private function receipt(string $reference='SYN-RECEIPT', int $amount=10000, int $student=1) { return (new Finance())->payment(1,1,['student_id'=>$student,'amount_paise'=>$amount,'reference'=>$reference]); }
    private function request(string $reference='SYN-REQUEST', int $amount=10000, int $student=1) { return (new MoneyOperations())->createPaymentRequest(1,1,['student_id'=>$student,'amount_paise'=>$amount,'reference'=>$reference,'requested_on'=>now('UTC')->toDateString(),'due_on'=>now('UTC')->addDays(7)->toDateString(),'notes'=>'Synthetic request, not sent']); }

    public function test_already_paid_settlement_replay_and_audited_reversal(): void
    {
        $service=new MoneyOperations();
        $paid=$service->recordTeacherPayment(1,1,$this->settlement());
        $this->assertSame(-4000,(int)$paid->amount_paise);
        $this->assertSame(6000,$paid->net_payable_paise);
        $this->assertEquals($paid->id,$service->recordTeacherPayment(1,1,$this->settlement())->id);
        $this->invalid(function()use($service){$service->recordTeacherPayment(1,1,$this->settlement(['amount_paise'=>4001]));});
        $reversal=$service->reverseTeacherPayment(1,1,['entry_id'=>$paid->id,'reason'=>'Duplicate external payment record']);
        $this->assertSame(4000,(int)$reversal->amount_paise);
        $this->assertSame(10000,$reversal->net_payable_paise);
        $this->assertEquals($reversal->id,$service->reverseTeacherPayment(1,1,['entry_id'=>$paid->id,'reason'=>'Duplicate external payment record'])->id);
        $this->assertDatabaseHas('erp_ledger',['id'=>$paid->id,'amount_paise'=>-4000,'kind'=>'teacher_payment']);
        $this->assertSame(1,DB::table('erp_audit')->where('action','teacher_payment.recorded')->count());
    }

    public function test_settlement_rejects_advance_unconfirmed_future_and_cross_unit(): void
    {
        $service=new MoneyOperations();
        foreach ([['amount_paise'=>10001],['already_paid'=>false],['paid_on'=>now('UTC')->addDay()->toDateString()],['amount_paise'=>-1]] as $change) $this->invalid(function()use($service,$change){$service->recordTeacherPayment(1,1,$this->settlement($change));});
        $this->invalid(function()use($service){$service->recordTeacherPayment(2,1,$this->settlement());});
        $this->assertSame(0,DB::table('erp_ledger')->where('kind','teacher_payment')->count());
    }

    public function test_receipt_correction_is_atomic_immutable_and_exactly_once(): void
    {
        $service=new MoneyOperations();$old=$this->receipt();
        $payload=['entry_id'=>$old->id,'amount_paise'=>15000,'new_reference'=>'SYN-CORRECTED','reason'=>'Corrected recorded amount'];
        $new=$service->correctReceipt(1,1,$payload);
        $this->assertSame(15000,(new Finance())->balance(1,1));
        $this->assertEquals($new->id,$service->correctReceipt(1,1,$payload)->id);
        $this->assertDatabaseHas('erp_ledger',['id'=>$old->id,'amount_paise'=>10000,'reference'=>'SYN-RECEIPT']);
        $this->invalid(function()use($service,$payload){$service->correctReceipt(1,1,array_merge($payload,['amount_paise'=>16000]));});
        $this->invalid(function()use($service,$payload){$service->correctReceipt(1,1,array_merge($payload,['new_reference'=>'ANOTHER-CORRECTION']));});
        $this->assertSame(1,DB::table('erp_ledger')->where('reverses_id',$old->id)->count());
        $this->assertSame(1,DB::table('erp_audit')->where('action','receipt.corrected')->count());
        $service->reverseReceipt(1,1,['entry_id'=>$new->id,'reason'=>'Erroneous receipt record, no refund issued']);
        $this->assertSame(0,(new Finance())->balance(1,1));
    }

    public function test_invalid_correction_reference_preserves_original_receipt(): void
    {
        $service=new MoneyOperations();$old=$this->receipt();$this->receipt('EXISTING',5000);
        $this->invalid(function()use($service,$old){$service->correctReceipt(1,1,['entry_id'=>$old->id,'amount_paise'=>15000,'new_reference'=>'EXISTING','reason'=>'Bad reference']);});
        $this->assertSame(15000,(new Finance())->balance(1,1));
        $this->assertSame(0,DB::table('erp_ledger')->where('reverses_id',$old->id)->count());
        $this->invalid(function()use($service,$old){$service->reverseReceipt(2,1,['entry_id'=>$old->id,'reason'=>'Wrong unit']);});
        $this->invalid(function()use($service,$old){$service->reverseTeacherPayment(1,1,['entry_id'=>$old->id,'reason'=>'Wrong type']);});
    }

    public function test_requests_are_not_receipts_and_cancel_preserves_audit(): void
    {
        $service=new MoneyOperations();$request=$this->request();
        $this->assertEquals($request->id,$this->request()->id);
        $this->assertSame(0,(new Finance())->balance(1,1));
        $this->assertSame('pending',$service->requestCoverage(1,$request->id)['status']);
        $service->cancelPaymentRequest(1,1,['request_id'=>$request->id,'reason'=>'No longer needed']);
        $service->cancelPaymentRequest(1,1,['request_id'=>$request->id,'reason'=>'No longer needed']);
        $this->assertSame('cancelled',$service->requestCoverage(1,$request->id)['status']);
        $this->assertSame(1,DB::table('erp_audit')->where('action','payment_request.cancelled')->count());
        $this->assertSame(0,DB::table('erp_ledger')->where('kind','payment')->count());
    }

    public function test_allocations_cannot_double_spend_receipt_and_reversal_reopens_request(): void
    {
        $service=new MoneyOperations();$request=$this->request();$receipt=$this->receipt();
        $allocation=['request_id'=>$request->id,'payment_id'=>$receipt->id,'amount_paise'=>10000];
        $first=$service->allocateReceipt(1,1,$allocation);
        $this->assertEquals($first->id,$service->allocateReceipt(1,1,$allocation)->id);
        $this->assertSame('fulfilled',$service->requestCoverage(1,$request->id)['status']);
        $this->invalid(function()use($service,$request){$service->cancelPaymentRequest(1,1,['request_id'=>$request->id,'reason'=>'Cannot cancel covered request']);});
        $this->invalid(function()use($service,$allocation){$service->allocateReceipt(1,1,array_merge($allocation,['amount_paise'=>9999]));});
        $second=$this->request('SECOND-REQUEST');
        $this->invalid(function()use($service,$second,$receipt){$service->allocateReceipt(1,1,['request_id'=>$second->id,'payment_id'=>$receipt->id,'amount_paise'=>1]);});
        $service->reverseReceipt(1,1,['entry_id'=>$receipt->id,'reason'=>'Erroneous external receipt record']);
        $coverage=$service->requestCoverage(1,$request->id);
        $this->assertSame('pending',$coverage['status']);$this->assertSame(0,$coverage['covered_paise']);$this->assertSame(10000,$coverage['outstanding_paise']);
        $this->assertSame(1,DB::table('erp_request_allocations')->count());
        $this->invalid(function()use($service,$allocation){$service->allocateReceipt(1,1,$allocation);});
        $replacement=$this->receipt('REPLACEMENT');
        $service->allocateReceipt(1,1,['request_id'=>$request->id,'payment_id'=>$replacement->id,'amount_paise'=>10000]);
        $this->assertSame('fulfilled',$service->requestCoverage(1,$request->id)['status']);
    }

    public function test_partial_cancellation_and_allocation_student_unit_guards(): void
    {
        $service=new MoneyOperations();$request=$this->request();$receipt=$this->receipt();
        $service->allocateReceipt(1,1,['request_id'=>$request->id,'payment_id'=>$receipt->id,'amount_paise'=>4000]);
        $service->cancelPaymentRequest(1,1,['request_id'=>$request->id,'reason'=>'Cancel unpaid remainder']);
        $coverage=$service->requestCoverage(1,$request->id);$this->assertSame('cancelled',$coverage['status']);$this->assertSame(4000,$coverage['covered_paise']);
        $this->assertSame(1,DB::table('erp_request_allocations')->count());
        $other=$this->request('OTHER-STUDENT',10000,2);
        $this->invalid(function()use($service,$other,$receipt){$service->allocateReceipt(1,1,['request_id'=>$other->id,'payment_id'=>$receipt->id,'amount_paise'=>1000]);});
        $this->invalid(function()use($service,$request,$receipt){$service->allocateReceipt(2,1,['request_id'=>$request->id,'payment_id'=>$receipt->id,'amount_paise'=>1000]);});
        $this->invalid(function()use($service,$request){$service->requestCoverage(2,$request->id);});
    }
    public function test_teacher_statement_preserves_negative_net_after_later_accrual_correction(): void
    {
        $service=new MoneyOperations();$service->recordTeacherPayment(1,1,$this->settlement());
        $this->assertSame(['earned_paise'=>10000,'paid_paise'=>4000,'other_adjustments_paise'=>0,'net_payable_paise'=>6000],$service->teacherStatement(1,1));
        $earned=DB::table('erp_ledger')->where('reference','SYN-ACCRUAL')->first();
        DB::table('erp_ledger')->insert(['unit_id'=>1,'teacher_id'=>1,'kind'=>'reversal','amount_paise'=>-10000,'currency'=>'INR','reference'=>'SYN-REVERSE-EARNING','event_key'=>'synthetic-reverse-earning','reverses_id'=>$earned->id,'occurred_at'=>now(),'created_by'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $this->assertSame(['earned_paise'=>0,'paid_paise'=>4000,'other_adjustments_paise'=>0,'net_payable_paise'=>-4000],$service->teacherStatement(1,1));
        $this->invalid(function()use($service){$service->recordTeacherPayment(1,1,$this->settlement(['reference'=>'NO-ADVANCE','amount_paise'=>1]));});
        $this->invalid(function()use($service){$service->teacherStatement(2,1);});
        DB::table('erp_ledger')->insert(['unit_id'=>1,'teacher_id'=>1,'kind'=>'opening_balance','amount_paise'=>2000,'currency'=>'INR','reference'=>'SYN-ADJUSTMENT','event_key'=>'synthetic-adjustment','occurred_at'=>now(),'created_by'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $this->assertSame(['earned_paise'=>0,'paid_paise'=>4000,'other_adjustments_paise'=>2000,'net_payable_paise'=>-2000],$service->teacherStatement(1,1));
    }

    public function test_correction_replacement_failure_rolls_back_reversal_and_audit(): void
    {
        $old=$this->receipt();
        // Inject a one-shot failure inside the transaction without MySQL implicit-commit DDL.
        $armed = true;
        DB::listen(function ($query) use (&$armed) {
            $sql = str_replace(['`', '"'], '', strtolower($query->sql));
            if ($armed && strpos($sql, 'insert into erp_ledger ') === 0 && in_array('FAIL-REPLACEMENT', $query->bindings, true)) {
                $armed = false;
                throw new \Illuminate\Database\QueryException($query->connectionName, $query->sql, $query->bindings, new \RuntimeException('Synthetic replacement insert failure'));
            }
        });
        try {
            (new MoneyOperations())->correctReceipt(1,1,['entry_id'=>$old->id,'amount_paise'=>15000,'new_reference'=>'FAIL-REPLACEMENT','reason'=>'Synthetic rollback test']);
            $this->fail('Expected database failure.');
        } catch (\Illuminate\Database\QueryException $expected) {
            $this->assertSame(10000,(new Finance())->balance(1,1));
            $this->assertSame(0,DB::table('erp_ledger')->where('reverses_id',$old->id)->count());
            $this->assertSame(0,DB::table('erp_audit')->where('action','receipt.reversed_for_correction')->count());
        } finally { $armed = false; }
    }

    public function test_request_summary_after_corrected_receipt_requires_new_allocation(): void
    {
        $service=new MoneyOperations();$request=$this->request('COVERAGE',5000);$receipt=$this->receipt();
        $this->invalid(function()use($service,$request,$receipt){$service->allocateReceipt(1,1,['request_id'=>$request->id,'payment_id'=>$receipt->id,'amount_paise'=>5001]);});
        $service->allocateReceipt(1,1,['request_id'=>$request->id,'payment_id'=>$receipt->id,'amount_paise'=>5000]);
        $corrected=$service->correctReceipt(1,1,['entry_id'=>$receipt->id,'amount_paise'=>6000,'new_reference'=>'CORRECTED-COVERAGE','reason'=>'Corrected original receipt']);
        $this->assertSame(['requested_paise'=>5000,'covered_paise'=>0,'outstanding_paise'=>5000,'status'=>'pending'],$service->requestSummary(1,$request->id));
        $service->allocateReceipt(1,1,['request_id'=>$request->id,'payment_id'=>$corrected->id,'amount_paise'=>5000]);
        $this->assertSame(['requested_paise'=>5000,'covered_paise'=>5000,'outstanding_paise'=>0,'status'=>'fulfilled'],$service->requestSummary(1,$request->id));
        $this->assertSame(2,DB::table('erp_request_allocations')->count());
    }

}
