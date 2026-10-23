<?php

namespace App\Modules\Erp\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Finance
{
    public function payment(int $unitId, int $actorId, array $payload)
    {
        $amount = $this->positiveInteger($payload['amount_paise'] ?? null, 'amount_paise');
        $reference = trim((string) ($payload['reference'] ?? ''));
        if ($reference === '' || strlen($reference) > 120) {
            $this->fail('reference', 'A stable receipt reference of at most 120 characters is required.');
        }
        if (($payload['currency'] ?? 'INR') !== 'INR') {
            $this->fail('currency', 'Only INR receipts are supported.');
        }
        return DB::transaction(function () use ($unitId, $actorId, $payload, $amount, $reference) {
            $student = DB::table('erp_students')->where('unit_id', $unitId)->where('id', $payload['student_id'] ?? 0)->lockForUpdate()->first();
            if (!$student) $this->fail('student_id', 'Student does not belong to this unit.');
            $key = 'payment:'.hash('sha256', $reference);
            $existing = DB::table('erp_ledger')->where('reference', $reference)->orWhere('event_key', $key)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->unit_id !== $unitId || (int) $existing->student_id !== (int) $student->id || (int) $existing->amount_paise !== $amount || $existing->currency !== 'INR' || $existing->kind !== 'payment') {
                    $this->fail('reference', 'This receipt reference already identifies a different transaction.');
                }
                return $existing;
            }
            $id = $this->entry($unitId, $actorId, ['student_id' => $student->id, 'kind' => 'payment', 'amount_paise' => $amount, 'reference' => $reference, 'event_key' => $key]);
            Audit::record($unitId, $actorId, 'payment.recorded', 'ledger', $id, ['reference' => $reference, 'amount_paise' => $amount]);
            return DB::table('erp_ledger')->find($id);
        });
    }

    public function balance(int $unitId, int $studentId): int
    {
        if (!DB::table('erp_students')->where('unit_id', $unitId)->where('id', $studentId)->exists()) $this->fail('student_id', 'Student does not belong to this unit.');
        return (int) DB::table('erp_ledger')->where('unit_id', $unitId)->where('student_id', $studentId)->sum('amount_paise');
    }

    // Internal posting API. Locks and validation are repeated so callers cannot bypass the import gate.
    public function postAttendance(int $unitId, int $actorId, int $attendanceId, ?int $batchId = null): bool
    {
        return DB::transaction(function () use ($unitId, $actorId, $attendanceId, $batchId) {
            if (!$batchId) $this->fail('batch', 'An explicit administrator import batch is required.');
            $batch = DB::table('erp_attendance_batches')->where('unit_id', $unitId)->where('id', $batchId)->lockForUpdate()->first();
            if (!$batch) $this->fail('batch', 'Import batch does not belong to this unit.');
            $snapshot = collect(json_decode($batch->snapshot, true, 512, JSON_THROW_ON_ERROR))->firstWhere('attendance_id', $attendanceId);
            if (!$snapshot) $this->fail('batch', 'Attendance is not included in this import snapshot.');
            $attendance = $this->lockedAttendance($unitId, $attendanceId);
            if (!$attendance || !in_array($attendance->status, ['submitted', 'approved'], true)) $this->fail('attendance', 'Submitted or approved attendance in this unit is required.');
            $lesson = DB::table('erp_lessons')->where('unit_id', $unitId)->where('id', $attendance->lesson_id)->lockForUpdate()->first();
            if (!$lesson) $this->fail('lesson', 'Lesson does not belong to this unit.');
            if (!DB::table('erp_students')->where('unit_id', $unitId)->where('id', $lesson->student_id)->lockForUpdate()->first() || !DB::table('erp_teacher_units')->where('unit_id', $unitId)->where('teacher_id', $lesson->teacher_id)->lockForUpdate()->first()) $this->fail('lesson', 'Lesson parties must belong to this unit.');
            if (!DB::table('erp_enrollments')->where('unit_id', $unitId)->where('id', $lesson->enrollment_id)->where('student_id', $lesson->student_id)->where('teacher_id', $lesson->teacher_id)->where('subject_id', $lesson->subject_id)->lockForUpdate()->first()) $this->fail('lesson', 'Lesson enrollment and parties do not match this unit.');
            if (config('erp.finance_policy', 'unconfigured') !== 'admin_import_delivered_only') $this->fail('policy', 'Administrator-import billing policy must be explicitly configured.');
            if ($lesson->status !== 'completed') $this->fail('lesson', 'Only delivered lessons can be charged.');
            $this->assertAfterMigrationCutoff($unitId, $lesson);
            if ($lesson->kind === 'demo') return false;
            if ((int) $snapshot['actual_minutes'] !== (int) $attendance->actual_minutes || (int) $snapshot['lesson_id'] !== (int) $lesson->id || (int) $snapshot['student_rate_paise'] !== (int) $lesson->student_rate_paise || $snapshot['currency'] !== $lesson->currency) $this->fail('batch', 'Attendance changed after this import snapshot.');
            $minutes = $this->positiveInteger($attendance->actual_minutes, 'actual_minutes');
            if ($lesson->currency !== 'INR' || $lesson->student_rate_paise < 0 || $lesson->teacher_rate_paise < 0) $this->fail('rates', 'Valid frozen INR lesson rates are required.');
            $ledger = DB::table('erp_ledger')->where('unit_id', $unitId)->where('attendance_id', $attendanceId)->orderBy('id')->lockForUpdate()->get();
            $active = $this->activeEntries($ledger)->where('kind', 'lesson_charge')->count();
            if ($active) {
                if ($active !== 1) $this->fail('ledger', 'Incomplete attendance posting requires investigation.');
                return true;
            }
            $generation = $ledger->where('kind', 'lesson_charge')->count() + 1;
            if ((int) ($snapshot['posting_generation'] ?? 0) !== $generation) $this->fail('batch', 'A new administrator import is required after correction.');
            // Rates are explicitly hourly. Integer arithmetic rounds to the nearest paise.
            foreach (['lesson_charge' => ['student_id', $lesson->student_id, $lesson->student_rate_paise, -1]] as $kind => $values) {
                $key = 'attendance:'.$attendanceId.':'.$generation.':'.$kind;
                $amount = intdiv(((int) $values[2]) * $minutes + 30, 60) * $values[3];
                $this->entry($unitId, $actorId, [$values[0] => $values[1], 'attendance_id' => $attendanceId, 'kind' => $kind, 'amount_paise' => $amount, 'reference' => 'ERP:'.$key, 'event_key' => $key]);
            }
            Audit::record($unitId, $actorId, 'attendance.posted', 'attendance', $attendanceId, ['generation' => $generation, 'actual_minutes' => $minutes, 'policy' => 'admin_import_delivered_only', 'batch_id' => $batchId]);
            return true;
        });
    }

    /** Approval accrues an amount owed; this never disburses salary or moves money. */
    public function postTeacherAttendance(int $unitId, int $actorId, int $attendanceId): bool
    {
        return DB::transaction(function () use ($unitId, $actorId, $attendanceId) {
            $attendance = $this->lockedAttendance($unitId, $attendanceId);
            if ($attendance->status !== 'approved') $this->fail('attendance', 'Administrator-approved attendance is required for teacher accrual.');
            $lesson = DB::table('erp_lessons')->where('unit_id', $unitId)->where('id', $attendance->lesson_id)->lockForUpdate()->first();
            if (!$lesson || $lesson->status !== 'completed') $this->fail('lesson', 'Only attended lessons accrue teacher pay; cancellations do not.');
            if (!DB::table('erp_teacher_units')->where('unit_id', $unitId)->where('teacher_id', $lesson->teacher_id)->lockForUpdate()->first() || !DB::table('erp_enrollments')->where('unit_id', $unitId)->where('id', $lesson->enrollment_id)->where('student_id', $lesson->student_id)->where('teacher_id', $lesson->teacher_id)->where('subject_id', $lesson->subject_id)->lockForUpdate()->first()) $this->fail('lesson', 'Lesson parties and enrollment must belong to this unit.');
            $this->assertAfterMigrationCutoff($unitId, $lesson);
            if ($lesson->kind === 'demo' || config('erp.payroll_policy', 'unconfigured') !== 'approved_attended_only') return false;
            if ($lesson->currency !== 'INR' || filter_var($lesson->teacher_rate_paise, FILTER_VALIDATE_INT) === false || $lesson->teacher_rate_paise < 0 || $lesson->teacher_rate_paise > 100000000) $this->fail('rates', 'A valid frozen INR hourly teacher rate is required.');
            $minutes = $this->positiveInteger($attendance->actual_minutes, 'actual_minutes');
            $ledger = DB::table('erp_ledger')->where('unit_id', $unitId)->where('attendance_id', $attendanceId)->orderBy('id')->lockForUpdate()->get();
            $active = $this->activeEntries($ledger)->where('kind', 'teacher_accrual')->count();
            if ($active > 1) $this->fail('ledger', 'Multiple active teacher accruals require investigation.');
            if ($active === 1) return true;
            $generation = $ledger->where('kind', 'teacher_accrual')->count() + 1;
            $key = 'attendance:'.$attendanceId.':'.$generation.':teacher_accrual';
            $amount = intdiv(((int) $lesson->teacher_rate_paise) * $minutes + 30, 60);
            $this->entry($unitId, $actorId, ['teacher_id' => $lesson->teacher_id, 'attendance_id' => $attendanceId, 'kind' => 'teacher_accrual', 'amount_paise' => $amount, 'reference' => 'ERP:'.$key, 'event_key' => $key]);
            Audit::record($unitId, $actorId, 'attendance.teacher_accrued', 'attendance', $attendanceId, ['generation' => $generation, 'actual_minutes' => $minutes, 'rate_paise' => (int) $lesson->teacher_rate_paise, 'amount_paise' => $amount, 'policy' => 'approved_attended_only']);
            return true;
        });
    }

    /** Immutable migration provenance defines the UTC boundary, not a configurable default.
     * Call only after locking the lesson's teacher/student parents. Reversals deliberately
     * do not call this guard, so an erroneous legacy posting can still be compensated.
     */
    public function assertAfterMigrationCutoff(int $unitId, $lesson): void
    {
        if ((int) $lesson->unit_id !== $unitId) $this->fail('lesson', 'Lesson does not belong to this unit.');
        $mappings = collect();
        foreach ([['student', 'erp_students', $lesson->student_id], ['teacher', 'erp_teachers', $lesson->teacher_id], ['assignment', 'erp_enrollments', $lesson->enrollment_id]] as [$type, $table, $id]) {
            $mappings = $mappings->concat(DB::table('erp_migration_mappings')->where('target_type', $table)->where('target_id', $id)->where('entity_type', $type)->orderBy('id')->sharedLock()->get());
        }
        if ($mappings->where('entity_type', 'teacher')->isNotEmpty()) {
            $units = DB::table('erp_teacher_units')->where('teacher_id', $lesson->teacher_id)->orderBy('id')->lockForUpdate()->get();
            if ($units->count() !== 1 || (int) $units->first()->unit_id !== $unitId) $this->fail('migration', 'Ambiguous imported teacher unit provenance requires review.');
        }
        $openings = collect();
        foreach (['student_id' => $lesson->student_id, 'teacher_id' => $lesson->teacher_id] as $owner => $id) {
            $openings = $openings->concat(DB::table('erp_ledger')->where($owner, $id)->where('unit_id', $unitId)->where('kind', 'opening_balance')->orderBy('id')->sharedLock()->get());
        }
        if ($openings->isNotEmpty()) {
            $mappings = $mappings->concat(DB::table('erp_migration_mappings')->where('entity_type', 'opening_balance')->where('target_type', 'erp_ledger')->whereIn('target_id', $openings->pluck('id'))->orderBy('id')->sharedLock()->get());
        }
        if ($mappings->isEmpty()) return;
        $batches = DB::table('erp_migration_batches')->whereIn('id', $mappings->pluck('batch_id')->unique())->orderBy('id')->sharedLock()->get()->keyBy('id');
        foreach ($mappings as $mapping) {
            $batch = $batches->get($mapping->batch_id);
            if (!$batch || $batch->source_id !== $mapping->source_id || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $batch->cutoff)) $this->fail('migration', 'Migration provenance requires investigation before live attendance posting.');
            if ($lesson->starts_at < $batch->cutoff.' 00:00:00') $this->fail('migration', 'Pre-cutoff attendance belongs in nonfinancial migration history, not live billing or teacher accrual.');
        }
    }

    public function attendancePostingStatus(int $unitId, int $attendanceId): array
    {
        return DB::transaction(function () use ($unitId, $attendanceId) {
            $this->lockedAttendance($unitId, $attendanceId);
            $active = $this->activeEntries(DB::table('erp_ledger')->where('unit_id', $unitId)->where('attendance_id', $attendanceId)->orderBy('id')->lockForUpdate()->get());
            return ['student_financial_posted' => $active->where('kind', 'lesson_charge')->isNotEmpty(), 'teacher_financial_posted' => $active->where('kind', 'teacher_accrual')->isNotEmpty()];
        });
    }

    public function reverseAttendance(int $unitId, int $actorId, int $attendanceId, string $reason): void
    {
        if (trim($reason) === '') $this->fail('reason', 'A correction reason is required.');
        DB::transaction(function () use ($unitId, $actorId, $attendanceId, $reason) {
            $this->lockedAttendance($unitId, $attendanceId);
            $ledger = DB::table('erp_ledger')->where('unit_id', $unitId)->where('attendance_id', $attendanceId)->orderBy('id')->lockForUpdate()->get();
            $entries = $this->activeEntries($ledger);
            foreach ($entries as $entry) {
                $key = 'reversal:'.$entry->id;
                $id = $this->entry($unitId, $actorId, ['student_id' => $entry->student_id, 'teacher_id' => $entry->teacher_id, 'attendance_id' => $attendanceId, 'kind' => 'reversal', 'amount_paise' => -$entry->amount_paise, 'reference' => 'ERP:'.$key, 'event_key' => $key, 'reverses_id' => $entry->id]);
                Audit::record($unitId, $actorId, 'ledger.reversed', 'ledger', $id, ['reverses_id' => $entry->id, 'reason' => $reason]);
            }
        });
    }

    private function lockedAttendance(int $unitId, int $attendanceId)
    {
        $attendance = DB::table('erp_attendance')->where('unit_id', $unitId)->where('id', $attendanceId)->first();
        if (!$attendance) $this->fail('attendance', 'Attendance does not belong to this unit.');
        $lesson = DB::table('erp_lessons')->where('unit_id', $unitId)->where('id', $attendance->lesson_id)->first();
        if (!$lesson) $this->fail('lesson', 'Lesson does not belong to this unit.');
        DB::table('erp_teachers')->where('id', $lesson->teacher_id)->lockForUpdate()->first();
        if (!DB::table('erp_students')->where('unit_id', $unitId)->where('id', $lesson->student_id)->lockForUpdate()->first()) $this->fail('lesson', 'Lesson student does not belong to this unit.');
        DB::table('erp_lessons')->where('unit_id', $unitId)->where('id', $lesson->id)->lockForUpdate()->first();
        return DB::table('erp_attendance')->where('unit_id', $unitId)->where('id', $attendanceId)->lockForUpdate()->first();
    }

    private function activeEntries($ledger)
    {
        $reversed = $ledger->pluck('reverses_id')->filter()->map(function ($id) { return (int) $id; })->all();
        return $ledger->filter(function ($entry) use ($reversed) {
            return in_array($entry->kind, ['lesson_charge', 'teacher_accrual'], true) && !in_array((int) $entry->id, $reversed, true);
        });
    }

    private function entry(int $unitId, int $actorId, array $data): int
    {
        $now = now()->format('Y-m-d H:i:s');
        return DB::table('erp_ledger')->insertGetId(array_merge(['unit_id' => $unitId, 'student_id' => null, 'teacher_id' => null, 'attendance_id' => null, 'currency' => 'INR', 'reverses_id' => null, 'occurred_at' => $now, 'created_by' => $actorId, 'created_at' => $now, 'updated_at' => $now], $data));
    }

    private function positiveInteger($value, string $field): int
    {
        if (is_bool($value) || filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1 || (int) $value > 1000000000) $this->fail($field, 'A positive whole number within the supported range is required.');
        return (int) $value;
    }

    private function fail(string $field, string $message): void { throw ValidationException::withMessages([$field => $message]); }
}
