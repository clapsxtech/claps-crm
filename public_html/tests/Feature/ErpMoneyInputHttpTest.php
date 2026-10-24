<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** INR browser fields are presentation only: synthetic requests still persist integer paise. */
class ErpMoneyInputHttpTest extends TestCase
{
    private $unit;
    private $admin;
    private $student;
    private $teacher;
    private $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createAdmin();
        $this->unit = DB::table('erp_units')->insertGetId(['code' => 'INR-UNIT', 'name' => 'Synthetic Rupee Unit', 'timezone' => 'UTC']);
        DB::table('erp_memberships')->insert(['unit_id' => $this->unit, 'user_id' => $this->admin->id, 'role' => 'admin']);
        $this->student = DB::table('erp_students')->insertGetId(['unit_id' => $this->unit, 'code' => 'INR-STUDENT', 'name' => 'Synthetic Rupee Student', 'registered_on' => '2026-01-01', 'timezone' => 'UTC', 'status' => 'active', 'grade' => '8', 'syllabus' => 'Synthetic']);
        $this->teacher = DB::table('erp_teachers')->insertGetId(['code' => 'INR-TEACHER', 'name' => 'Synthetic Rupee Teacher', 'timezone' => 'UTC', 'status' => 'active']);
        DB::table('erp_teacher_units')->insert(['unit_id' => $this->unit, 'teacher_id' => $this->teacher]);
        $this->subject = DB::table('erp_subjects')->insertGetId(['name' => 'Synthetic Rupee Subject']);
        DB::table('erp_eligibilities')->insert(['teacher_id' => $this->teacher, 'subject_id' => $this->subject, 'grade' => '8', 'syllabus' => 'Synthetic']);
        $this->actingAs($this->admin);
    }

    public function test_rupee_receipt_posts_exactly_and_retries_match_legacy_paise_payloads(): void
    {
        $url = route('erp.mutate', [$this->unit, 'payment']);
        $payload = ['student_id' => $this->student, 'amount_inr' => '1234.29', 'reference' => 'INR-RECEIPT'];
        $this->post($url, $payload + ['return_page' => 'money'])->assertRedirect(route('erp.business', [$this->unit, 'money']))->assertSessionHasNoErrors();
        $entry = DB::table('erp_ledger')->where('reference', 'INR-RECEIPT')->first();
        $this->assertSame(123429, (int) $entry->amount_paise);
        unset($payload['amount_inr']);
        $this->postJson($url, $payload + ['amount_paise' => 123429])->assertOk()->assertJsonPath('result.id', $entry->id);
        $this->postJson($url, $payload + ['amount_inr' => '1234.29'])->assertOk()->assertJsonPath('result.id', $entry->id);
        $this->postJson($url, $payload + ['amount_inr' => '1234.30'])->assertUnprocessable();
        $this->assertSame(1, DB::table('erp_ledger')->count());
        $this->assertSame(1, DB::table('erp_audit')->where('action', 'payment.recorded')->count());
        $this->get(route('erp.receipt', [$this->unit, $entry->id]))->assertOk()->assertSee('Received: INR 1,234.29');
        $this->get(route('erp.workspace', $this->unit))->assertOk()->assertSee('INR 1,234.29');
    }

    public function test_assignment_forms_accept_decimal_and_zero_rates_without_changing_storage(): void
    {
        $payload = $this->assignment() + ['student_rate_inr' => '120.29', 'teacher_rate_inr' => '0.00'];
        $this->post(route('erp.mutate', [$this->unit, 'assign']), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('erp_enrollments', ['unit_id' => $this->unit, 'student_rate_paise' => 12029, 'teacher_rate_paise' => 0, 'currency' => 'INR']);
        $this->get(route('erp.workspace', $this->unit))->assertOk()->assertSee('Student hourly rate (INR)')->assertSee('INR 120.29')->assertSee('INR 0.00')->assertDontSee('name="student_rate_paise"', false)->assertDontSee('name="teacher_rate_paise"', false);
        $this->assertSame(0, DB::table('erp_ledger')->count());
    }

    public function test_rupee_requests_allocations_corrections_and_settlements_preserve_bookkeeping_contract(): void
    {
        $requestPayload = ['student_id' => $this->student, 'amount_inr' => '200.25', 'reference' => 'INR-REQUEST', 'requested_on' => now()->toDateString()];
        $this->business('request-create', $requestPayload)->assertRedirect()->assertSessionHasNoErrors();
        $request = DB::table('erp_payment_requests')->where('reference', 'INR-REQUEST')->first();
        $this->assertSame(20025, (int) $request->amount_paise);
        $this->assertSame(0, DB::table('erp_ledger')->count());
        $this->post(route('erp.mutate', [$this->unit, 'payment']), ['student_id' => $this->student, 'amount_inr' => '200.25', 'reference' => 'INR-ALLOCATABLE'])->assertRedirect()->assertSessionHasNoErrors();
        $receipt = DB::table('erp_ledger')->where('reference', 'INR-ALLOCATABLE')->first();
        $this->business('request-allocate', ['request_id' => $request->id, 'payment_id' => $receipt->id, 'amount_inr' => '100.25'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('erp_request_allocations', ['request_id' => $request->id, 'amount_paise' => 10025]);
        $this->get(route('erp.request.print', [$this->unit, $request->id]))->assertOk()->assertSee('Requested: INR 200.25')->assertSee('Covered by active received payments: INR 100.25')->assertSee('Outstanding request: INR 100.00');
        $this->business('receipt-correct', ['entry_id' => $receipt->id, 'amount_inr' => '150.29', 'new_reference' => 'INR-CORRECTED', 'reason' => 'Synthetic amount correction'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('erp_ledger', ['reference' => 'INR-CORRECTED', 'amount_paise' => 15029]);
        $this->get(route('erp.request.print', [$this->unit, $request->id]))->assertOk()->assertSee('Outstanding request: INR 200.25');
        DB::table('erp_ledger')->insert(['unit_id' => $this->unit, 'teacher_id' => $this->teacher, 'kind' => 'teacher_accrual', 'amount_paise' => 10025, 'currency' => 'INR', 'reference' => 'INR-EARNED', 'event_key' => 'inr-earned', 'occurred_at' => now(), 'created_by' => $this->admin->id]);
        $settlement = ['teacher_id' => $this->teacher, 'amount_inr' => '40.25', 'reference' => 'INR-PAID', 'paid_on' => now()->toDateString(), 'reason' => 'Synthetic recorded external settlement'];
        $this->business('salary-paid', $settlement)->assertSessionHasErrors('already_paid');
        $this->assertSame(0, DB::table('erp_ledger')->where('kind', 'teacher_payment')->count());
        $this->business('salary-paid', $settlement + ['already_paid' => '1'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('erp_ledger', ['reference' => 'INR-PAID', 'amount_paise' => -4025]);
        $settlement['reference'] = 'INR-OVERPAYMENT';
        $settlement['amount_inr'] = '60.01';
        $this->business('salary-paid', $settlement + ['already_paid' => '1'])->assertSessionHasErrors('amount_paise');
        $this->assertSame(1, DB::table('erp_ledger')->where('kind', 'teacher_payment')->count());
        $this->get(route('erp.business', [$this->unit, 'money']))->assertOk()->assertSee('earned (INR)')->assertSee('paid (INR)')->assertSee('net payable (INR)')->assertSee('INR 100.25')->assertSee('INR 40.25')->assertSee('INR 60.00');
        $this->get(route('erp.workspace', $this->unit))->assertOk()->assertSee('These are earned amounts, not proof of payment')->assertSee('approved earnings (INR)');
    }

    public function test_invalid_rupees_and_mixed_units_cannot_write_ledger_audit_or_assignments(): void
    {
        foreach (['0', '-1', '+1', '1e2', '1,000.00', '1.001', '01.00', '10000000.01', str_repeat('9', 100), true, false, [], ['10'], 0.25, null] as $invalid) {
            $this->postJson(route('erp.mutate', [$this->unit, 'payment']), ['student_id' => $this->student, 'amount_inr' => $invalid, 'reference' => 'INR-INVALID'])->assertUnprocessable()->assertJsonValidationErrors('amount_inr');
            $this->postJson(route('erp.business.mutate', [$this->unit, 'request-create']), ['student_id' => $this->student, 'amount_inr' => $invalid, 'reference' => 'INR-INVALID-REQUEST', 'requested_on' => now()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('amount_inr');
        }
        foreach ([100, null] as $paise) {
            $this->postJson(route('erp.mutate', [$this->unit, 'payment']), ['student_id' => $this->student, 'amount_inr' => '1.00', 'amount_paise' => $paise, 'reference' => 'INR-MIXED'])->assertUnprocessable()->assertJsonValidationErrors('amount_inr');
        }
        foreach ([['student_rate_inr' => '0.00', 'teacher_rate_paise' => 0], ['student_rate_inr' => '1000000.01', 'teacher_rate_inr' => '0.00'], ['student_rate_inr' => '-0.01', 'teacher_rate_inr' => '0.00']] as $rates) {
            $this->postJson(route('erp.mutate', [$this->unit, 'assign']), $this->assignment() + $rates)->assertUnprocessable()->assertJsonValidationErrors('student_rate_inr');
        }
        $this->assertSame(0, DB::table('erp_ledger')->count());
        $this->assertSame(0, DB::table('erp_audit')->count());
        $this->assertSame(0, DB::table('erp_payment_requests')->count());
        $this->assertSame(0, DB::table('erp_enrollments')->count());
    }

    public function test_browser_money_fields_and_signed_displays_are_inr_but_exports_stay_paise(): void
    {
        DB::table('erp_ledger')->insert(['unit_id' => $this->unit, 'student_id' => $this->student, 'kind' => 'opening_balance', 'amount_paise' => -123429, 'currency' => 'INR', 'reference' => 'INR-DUE', 'event_key' => 'inr-due', 'occurred_at' => now(), 'created_by' => $this->admin->id]);
        $money = $this->get(route('erp.business', [$this->unit, 'money']))->assertOk()->assertSee('Amount (INR)')->assertSee('name="amount_inr"', false)->assertDontSee('name="amount_paise"', false)->assertSee('balance (INR)')->assertSee('INR -1,234.29');
        $this->assertSame(5, substr_count($money->getContent(), 'name="amount_inr"'));
        $this->get(route('erp.workspace', $this->unit))->assertOk()->assertSee('INR -1,234.29')->assertDontSee('<th>amount paise</th>', false)->assertDontSee('name="amount_paise"', false);
        $csv = $this->get(route('erp.export', [$this->unit, 'ledger']))->assertOk()->streamedContent();
        $this->assertStringContainsString('amount_paise', $csv);
        $this->assertStringContainsString('-123429', $csv);
        $this->assertStringNotContainsString('INR -1,234.29', $csv);
    }

    private function assignment(): array
    {
        return ['student_id' => $this->student, 'teacher_id' => $this->teacher, 'subject_id' => $this->subject, 'starts_on' => '2026-01-01'];
    }

    private function business(string $action, array $payload)
    {
        return $this->from(route('erp.business', [$this->unit, 'money']))->post(route('erp.business.mutate', [$this->unit, $action]), $payload + ['return_page' => 'money']);
    }
}
