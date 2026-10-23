<?php

namespace App\Modules\Erp\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Records completed external transactions only. Never transfers money or issues refunds. */
class MoneyOperations
{
    public function recordTeacherPayment(int $unitId, int $actorId, array $payload)
    {
        $amount = $this->amount($payload['amount_paise'] ?? null);
        $reference = $this->reference($payload['reference'] ?? null);
        $reason = $this->reason($payload['reason'] ?? null);
        $paidOn = $this->date($payload['paid_on'] ?? null, 'paid_on');
        if (!in_array($payload['already_paid'] ?? null, [true, 1, '1'], true)) $this->fail('already_paid', 'Explicitly confirm that this payment has already been made outside the app.');
        return DB::transaction(function () use ($unitId, $actorId, $payload, $amount, $reference, $reason, $paidOn) {
            $teacherId = (int) ($payload['teacher_id'] ?? 0);
            $this->teacher($unitId, $teacherId);
            $this->notFuture($unitId, $paidOn, 'paid_on');
            $key = 'teacher-payment:'.hash('sha256', $reference);
            $existing = DB::table('erp_ledger')->where('reference', $reference)->orWhere('event_key', $key)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->unit_id !== $unitId || (int) $existing->teacher_id !== $teacherId || $existing->kind !== 'teacher_payment' || (int) $existing->amount_paise !== -$amount || $existing->currency !== 'INR' || substr($existing->occurred_at, 0, 10) !== $paidOn) $this->fail('reference', 'Reference already identifies a different transaction.');
                return $this->withPayable($existing, $unitId, $teacherId);
            }
            $payable = $this->payable($unitId, $teacherId);
            if ($amount > $payable) $this->fail('amount_paise', 'This already-paid amount exceeds the recorded net payable. Recording an advance or overpayment is unsupported and needs review; this does not mean the external payment did not happen.');
            $id = $this->entry($unitId, $actorId, ['teacher_id' => $teacherId, 'kind' => 'teacher_payment', 'amount_paise' => -$amount, 'reference' => $reference, 'event_key' => $key, 'occurred_at' => $paidOn.' 00:00:00']);
            Audit::record($unitId, $actorId, 'teacher_payment.recorded', 'ledger', $id, ['reason' => $reason, 'paid_on' => $paidOn, 'already_paid' => true, 'amount_paise' => $amount, 'money_transferred_by_app' => false]);
            return $this->withPayable(DB::table('erp_ledger')->where('id', $id)->first(), $unitId, $teacherId);
        });
    }

    public function reverseTeacherPayment(int $unitId, int $actorId, array $payload)
    {
        $reason = $this->reason($payload['reason'] ?? null);
        return DB::transaction(function () use ($unitId, $actorId, $payload, $reason) {
            $entry = $this->lockedEntry($unitId, (int) ($payload['entry_id'] ?? 0), 'teacher_payment');
            $reversal = $this->reverse($unitId, $actorId, $entry, $reason, 'teacher_payment.reversed');
            return $this->withPayable($reversal, $unitId, (int) $entry->teacher_id);
        });
    }

    public function reverseReceipt(int $unitId, int $actorId, array $payload)
    {
        $reason = $this->reason($payload['reason'] ?? null);
        return DB::transaction(function () use ($unitId, $actorId, $payload, $reason) {
            $entry = $this->lockedEntry($unitId, (int) ($payload['entry_id'] ?? 0), 'payment');
            return $this->reverse($unitId, $actorId, $entry, $reason, 'receipt.reversed');
        });
    }

    public function correctReceipt(int $unitId, int $actorId, array $payload)
    {
        $amount = $this->amount($payload['amount_paise'] ?? null);
        $reference = $this->reference($payload['new_reference'] ?? null);
        $reason = $this->reason($payload['reason'] ?? null);
        return DB::transaction(function () use ($unitId, $actorId, $payload, $amount, $reference, $reason) {
            $original = $this->lockedEntry($unitId, (int) ($payload['entry_id'] ?? 0), 'payment');
            if ($reference === $original->reference) $this->fail('new_reference', 'A new receipt reference is required; the original receipt remains immutable.');
            $key = 'receipt-correction:'.$original->id.':'.hash('sha256', $reference);
            $existing = DB::table('erp_ledger')->where('reference', $reference)->orWhere('event_key', $key)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->event_key !== $key || (int) $existing->unit_id !== $unitId || (int) $existing->student_id !== (int) $original->student_id || (int) $existing->amount_paise !== $amount || $existing->kind !== 'payment' || $existing->currency !== 'INR') $this->fail('new_reference', 'Reference already identifies a different correction or payment.');
                return $existing;
            }
            if (DB::table('erp_ledger')->where('reverses_id', $original->id)->lockForUpdate()->first()) $this->fail('entry_id', 'This receipt has already been reversed or corrected.');
            $this->reverse($unitId, $actorId, $original, $reason, 'receipt.reversed_for_correction');
            $id = $this->entry($unitId, $actorId, ['student_id' => $original->student_id, 'kind' => 'payment', 'amount_paise' => $amount, 'reference' => $reference, 'event_key' => $key, 'occurred_at' => $original->occurred_at]);
            Audit::record($unitId, $actorId, 'receipt.corrected', 'ledger', $original->id, ['replacement_id' => $id, 'new_reference' => $reference, 'old_amount_paise' => (int) $original->amount_paise, 'amount_paise' => $amount, 'reason' => $reason, 'refund_issued' => false]);
            return DB::table('erp_ledger')->where('id', $id)->first();
        });
    }

    public function createPaymentRequest(int $unitId, int $actorId, array $payload)
    {
        $amount = $this->amount($payload['amount_paise'] ?? null);
        $reference = $this->reference($payload['reference'] ?? null);
        $requestedOn = $this->date($payload['requested_on'] ?? null, 'requested_on');
        $dueOn = empty($payload['due_on']) ? null : $this->date($payload['due_on'], 'due_on');
        if ($dueOn && $dueOn < $requestedOn) $this->fail('due_on', 'Due date cannot precede the request date.');
        $notes = isset($payload['notes']) ? trim((string) $payload['notes']) : null;
        if ($notes !== null && strlen($notes) > 4000) $this->fail('notes', 'Notes must be at most 4000 characters.');
        return DB::transaction(function () use ($unitId, $actorId, $payload, $amount, $reference, $requestedOn, $dueOn, $notes) {
            $studentId = (int) ($payload['student_id'] ?? 0);
            $this->student($unitId, $studentId);
            $this->notFuture($unitId, $requestedOn, 'requested_on');
            $existing = DB::table('erp_payment_requests')->where('reference', $reference)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->unit_id !== $unitId || (int) $existing->student_id !== $studentId || (int) $existing->amount_paise !== $amount || $existing->requested_on !== $requestedOn || $existing->due_on !== $dueOn || $existing->notes !== $notes) $this->fail('reference', 'Reference already identifies a different payment request.');
                return $existing;
            }
            $now = now()->format('Y-m-d H:i:s');
            $id = DB::table('erp_payment_requests')->insertGetId(['unit_id' => $unitId, 'student_id' => $studentId, 'reference' => $reference, 'amount_paise' => $amount, 'status' => 'pending', 'requested_on' => $requestedOn, 'due_on' => $dueOn, 'notes' => $notes, 'created_by' => $actorId, 'created_at' => $now, 'updated_at' => $now]);
            Audit::record($unitId, $actorId, 'payment_request.created', 'payment_request', $id, ['reference' => $reference, 'amount_paise' => $amount, 'receipt_recorded' => false, 'message_sent' => false]);
            return DB::table('erp_payment_requests')->where('id', $id)->first();
        });
    }

    public function allocateReceipt(int $unitId, int $actorId, array $payload)
    {
        $amount = $this->amount($payload['amount_paise'] ?? null);
        return DB::transaction(function () use ($unitId, $actorId, $payload, $amount) {
            $request = DB::table('erp_payment_requests')->where('unit_id', $unitId)->where('id', $payload['request_id'] ?? 0)->first();
            if (!$request) $this->fail('request_id', 'Payment request does not belong to this unit.');
            $this->student($unitId, (int) $request->student_id);
            $request = DB::table('erp_payment_requests')->where('unit_id', $unitId)->where('id', $request->id)->lockForUpdate()->first();
            if ($request->status !== 'pending') $this->fail('request_id', 'Cancelled payment requests cannot receive allocations.');
            $payment = DB::table('erp_ledger')->where('unit_id', $unitId)->where('id', $payload['payment_id'] ?? 0)->where('student_id', $request->student_id)->where('kind', 'payment')->lockForUpdate()->first();
            if (!$payment || (int) $payment->amount_paise <= 0 || $payment->currency !== 'INR' || DB::table('erp_ledger')->where('reverses_id', $payment->id)->lockForUpdate()->first()) $this->fail('payment_id', 'An active INR receipt for this request’s student and unit is required.');
            $existing = DB::table('erp_request_allocations')->where('unit_id', $unitId)->where('request_id', $request->id)->where('payment_id', $payment->id)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->amount_paise !== $amount) $this->fail('amount_paise', 'This receipt/request pair already has a different immutable allocation.');
                return $existing;
            }
            $allocated = (int) DB::table('erp_request_allocations')->where('unit_id', $unitId)->where('payment_id', $payment->id)->lockForUpdate()->get()->sum('amount_paise');
            if ($allocated + $amount > (int) $payment->amount_paise) $this->fail('amount_paise', 'Allocation exceeds the receipt’s unallocated amount.');
            $coverage = $this->coverage($unitId, $request);
            if ($coverage + $amount > (int) $request->amount_paise) $this->fail('amount_paise', 'Allocation exceeds the request’s outstanding amount.');
            $now = now()->format('Y-m-d H:i:s');
            $id = DB::table('erp_request_allocations')->insertGetId(['unit_id' => $unitId, 'request_id' => $request->id, 'payment_id' => $payment->id, 'amount_paise' => $amount, 'created_at' => $now, 'updated_at' => $now]);
            Audit::record($unitId, $actorId, 'payment_request.receipt_allocated', 'payment_request', $request->id, ['allocation_id' => $id, 'payment_id' => $payment->id, 'amount_paise' => $amount]);
            return DB::table('erp_request_allocations')->where('id', $id)->first();
        });
    }

    public function requestCoverage(int $unitId, int $requestId): array
    {
        return DB::transaction(function () use ($unitId, $requestId) {
            $request = DB::table('erp_payment_requests')->where('unit_id', $unitId)->where('id', $requestId)->first();
            if (!$request) $this->fail('request_id', 'Payment request does not belong to this unit.');
            $this->student($unitId, (int) $request->student_id);
            $request = DB::table('erp_payment_requests')->where('unit_id', $unitId)->where('id', $requestId)->lockForUpdate()->first();
            $covered = $this->coverage($unitId, $request);
            return ['requested_paise' => (int) $request->amount_paise, 'covered_paise' => $covered, 'outstanding_paise' => max(0, (int) $request->amount_paise - $covered), 'status' => $request->status === 'cancelled' ? 'cancelled' : ($covered >= (int) $request->amount_paise ? 'fulfilled' : 'pending')];
        });
    }

    public function requestSummary(int $unitId, int $requestId): array
    {
        return $this->requestCoverage($unitId, $requestId);
    }

    public function teacherStatement(int $unitId, int $teacherId): array
    {
        return DB::transaction(function () use ($unitId, $teacherId) {
            $this->teacher($unitId, $teacherId);
            $entries = DB::table('erp_ledger')->where('unit_id', $unitId)->where('teacher_id', $teacherId)->orderBy('id')->lockForUpdate()->get();
            $byId = $entries->keyBy('id');
            $earned = 0; $paid = 0;
            foreach ($entries as $entry) {
                $kind = $entry->kind;
                if ($kind === 'reversal' && $entry->reverses_id && isset($byId[$entry->reverses_id])) $kind = $byId[$entry->reverses_id]->kind;
                if ($kind === 'teacher_accrual') $earned += (int) $entry->amount_paise;
                if ($kind === 'teacher_payment') $paid -= (int) $entry->amount_paise;
            }
            $net = (int) $entries->sum('amount_paise');
            return ['earned_paise' => $earned, 'paid_paise' => $paid, 'other_adjustments_paise' => $net - $earned + $paid, 'net_payable_paise' => $net];
        });
    }

    public function cancelPaymentRequest(int $unitId, int $actorId, array $payload)
    {
        $reason = $this->reason($payload['reason'] ?? null);
        return DB::transaction(function () use ($unitId, $actorId, $payload, $reason) {
            $request = DB::table('erp_payment_requests')->where('unit_id', $unitId)->where('id', $payload['request_id'] ?? 0)->first();
            if (!$request) $this->fail('request_id', 'Payment request does not belong to this unit.');
            $this->student($unitId, (int) $request->student_id);
            $request = DB::table('erp_payment_requests')->where('unit_id', $unitId)->where('id', $request->id)->lockForUpdate()->first();
            if ($request->status === 'cancelled') return $request;
            if ($request->status !== 'pending') $this->fail('request_id', 'Only pending payment requests can be cancelled.');
            $covered = $this->coverage($unitId, $request);
            if ($covered >= (int) $request->amount_paise) $this->fail('request_id', 'A fully covered request cannot be cancelled.');
            DB::table('erp_payment_requests')->where('id', $request->id)->update(['status' => 'cancelled', 'updated_at' => now()->format('Y-m-d H:i:s')]);
            Audit::record($unitId, $actorId, 'payment_request.cancelled', 'payment_request', $request->id, ['reason' => $reason, 'covered_paise' => $covered, 'cancelled_remainder_paise' => (int) $request->amount_paise - $covered]);
            return DB::table('erp_payment_requests')->where('id', $request->id)->first();
        });
    }

    private function coverage(int $unitId, $request): int
    {
        $allocations = DB::table('erp_request_allocations')->where('unit_id', $unitId)->where('request_id', $request->id)->orderBy('id')->lockForUpdate()->get();
        $covered = 0;
        foreach ($allocations as $allocation) {
            $payment = DB::table('erp_ledger')->where('unit_id', $unitId)->where('student_id', $request->student_id)->where('id', $allocation->payment_id)->where('kind', 'payment')->lockForUpdate()->first();
            if ($payment && !DB::table('erp_ledger')->where('reverses_id', $payment->id)->lockForUpdate()->first()) $covered += (int) $allocation->amount_paise;
        }
        return $covered;
    }

    private function lockedEntry(int $unitId, int $id, string $kind)
    {
        $entry = DB::table('erp_ledger')->where('unit_id', $unitId)->where('id', $id)->first();
        if (!$entry || $entry->kind !== $kind) $this->fail('entry_id', 'The requested transaction does not belong to this unit or has the wrong type.');
        if ($kind === 'teacher_payment') $this->teacher($unitId, (int) $entry->teacher_id);
        else $this->student($unitId, (int) $entry->student_id);
        return DB::table('erp_ledger')->where('unit_id', $unitId)->where('id', $id)->where('kind', $kind)->lockForUpdate()->first();
    }

    private function reverse(int $unitId, int $actorId, $entry, string $reason, string $action)
    {
        $existing = DB::table('erp_ledger')->where('reverses_id', $entry->id)->lockForUpdate()->first();
        if ($existing) return $existing;
        $key = 'reversal:'.$entry->id;
        $id = $this->entry($unitId, $actorId, ['student_id' => $entry->student_id, 'teacher_id' => $entry->teacher_id, 'kind' => 'reversal', 'amount_paise' => -(int) $entry->amount_paise, 'reference' => 'ERP:'.$key, 'event_key' => $key, 'reverses_id' => $entry->id]);
        Audit::record($unitId, $actorId, $action, 'ledger', $id, ['reverses_id' => $entry->id, 'reason' => $reason, 'money_transferred_by_app' => false]);
        return DB::table('erp_ledger')->where('id', $id)->first();
    }

    private function student(int $unitId, int $id): void
    {
        if (!DB::table('erp_students')->where('unit_id', $unitId)->where('id', $id)->lockForUpdate()->first()) $this->fail('student_id', 'Student does not belong to this unit.');
    }

    private function teacher(int $unitId, int $id): void
    {
        if (!DB::table('erp_teachers')->where('id', $id)->lockForUpdate()->first() || !DB::table('erp_teacher_units')->where('unit_id', $unitId)->where('teacher_id', $id)->lockForUpdate()->first()) $this->fail('teacher_id', 'Teacher does not belong to this unit.');
    }

    private function payable(int $unitId, int $teacherId): int
    {
        return (int) DB::table('erp_ledger')->where('unit_id', $unitId)->where('teacher_id', $teacherId)->orderBy('id')->lockForUpdate()->get()->sum('amount_paise');
    }

    private function withPayable($entry, int $unitId, int $teacherId)
    {
        $entry->net_payable_paise = $this->payable($unitId, $teacherId);
        return $entry;
    }

    private function entry(int $unitId, int $actorId, array $payload): int
    {
        $now = now()->format('Y-m-d H:i:s');
        return DB::table('erp_ledger')->insertGetId(array_merge(['unit_id' => $unitId, 'student_id' => null, 'teacher_id' => null, 'attendance_id' => null, 'currency' => 'INR', 'reverses_id' => null, 'occurred_at' => $now, 'created_by' => $actorId, 'created_at' => $now, 'updated_at' => $now], $payload));
    }

    private function amount($value): int
    {
        if (is_bool($value) || filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1 || (int) $value > 1000000000) $this->fail('amount_paise', 'A positive integer amount in paise is required.');
        return (int) $value;
    }

    private function reference($value): string
    {
        if (!is_string($value) || trim($value) === '' || strlen(trim($value)) > 120 || strpos(trim($value), 'ERP:') === 0) $this->fail('reference', 'Use a stable nonempty reference of at most 120 characters, without the reserved ERP: prefix.');
        return trim($value);
    }

    private function reason($value): string
    {
        if (!is_string($value) || trim($value) === '' || strlen(trim($value)) > 4000) $this->fail('reason', 'An audit reason of at most 4000 characters is required.');
        return trim($value);
    }

    private function date($value, string $field): string
    {
        if (!is_string($value) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D', $value)) $this->fail($field, 'Use a valid YYYY-MM-DD date.');
        try { $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC'); }
        catch (\Throwable $e) { $this->fail($field, 'Use a valid YYYY-MM-DD date.'); }
        if (!$date || $date->format('Y-m-d') !== $value) $this->fail($field, 'Use a valid YYYY-MM-DD date.');
        return $value;
    }

    private function notFuture(int $unitId, string $date, string $field): void
    {
        $unit = DB::table('erp_units')->where('id', $unitId)->first();
        if (!$unit || $date > CarbonImmutable::now($unit->timezone)->toDateString()) $this->fail($field, 'An already-recorded transaction cannot have a future date.');
    }

    private function fail(string $field, string $message): void { throw ValidationException::withMessages([$field => $message]); }
}
