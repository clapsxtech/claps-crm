<?php

namespace App\Modules\Erp\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Attendance
{
    public function submit(int $unitId, int $actorId, array $payload)
    {
        $minutes = $this->minutes($payload['actual_minutes'] ?? null);
        return DB::transaction(function () use ($unitId, $actorId, $payload, $minutes) {
            $lesson = $this->lesson($unitId, (int) ($payload['lesson_id'] ?? 0));
            // Match scheduling and teacher portal lock order before reading mutable state.
            DB::table('erp_teachers')->where('id', $lesson->teacher_id)->lockForUpdate()->first();
            $student = DB::table('erp_students')->where('unit_id', $unitId)->where('id', $lesson->student_id)->lockForUpdate()->first();
            $lesson = $this->lesson($unitId, $lesson->id, true);
            $existing = DB::table('erp_attendance')->where('unit_id', $unitId)->where('lesson_id', $lesson->id)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->actual_minutes !== $minutes) $this->fail('actual_minutes', 'Attendance exists. Use an audited correction instead.');
                return $existing;
            }
            if (Carbon::parse($lesson->ends_at, 'UTC')->isFuture()) $this->fail('lesson_id', 'Attendance can only be submitted after the lesson has ended.');
            if (!in_array($lesson->status, ['scheduled', 'completed'], true)) $this->fail('lesson_id', 'Only delivered lessons can receive attendance; cancellation and no-show billing is not configured.');
            $now = now()->format('Y-m-d H:i:s');
            $id = DB::table('erp_attendance')->insertGetId(['unit_id' => $unitId, 'lesson_id' => $lesson->id, 'actual_minutes' => $minutes, 'status' => 'submitted', 'submitted_by' => $actorId, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('erp_lessons')->where('unit_id', $unitId)->where('id', $lesson->id)->update(['status' => 'completed', 'updated_at' => $now]);
            $date = Carbon::parse($lesson->starts_at, 'UTC')->setTimezone($student->timezone)->toDateString();
            $others = DB::table('erp_attendance as a')->join('erp_lessons as l', 'l.id', '=', 'a.lesson_id')->where('a.unit_id', $unitId)->where('l.unit_id', $unitId)->where('l.student_id', $lesson->student_id)->where('l.teacher_id', $lesson->teacher_id)->where('a.id', '!=', $id)->where('a.status', '!=', 'rejected')->select('a.id', 'l.starts_at')->lockForUpdate()->get();
            foreach ($others as $other) {
                if (Carbon::parse($other->starts_at, 'UTC')->setTimezone($student->timezone)->toDateString() === $date) {
                    DB::table('erp_duplicate_reviews')->insert(['unit_id' => $unitId, 'attendance_id' => $id, 'other_attendance_id' => $other->id, 'status' => 'pending', 'channel' => 'gallabox', 'created_at' => $now, 'updated_at' => $now]);
                }
            }
            Audit::record($unitId, $actorId, 'attendance.submitted', 'attendance', $id, ['lesson_id' => $lesson->id, 'actual_minutes' => $minutes]);
            return DB::table('erp_attendance')->find($id);
        });
    }

    public function approve(int $unitId, int $actorId, int $attendanceId)
    {
        return DB::transaction(function () use ($unitId, $actorId, $attendanceId) {
            $attendance = $this->locked($unitId, $attendanceId);
            if ($attendance->status === 'rejected') $this->fail('attendance', 'Rejected attendance cannot be approved.');
            if (DB::table('erp_duplicate_reviews')->where('unit_id', $unitId)->where('status', 'pending')->where(function ($q) use ($attendanceId) { $q->where('attendance_id', $attendanceId)->orWhere('other_attendance_id', $attendanceId); })->lockForUpdate()->first()) $this->fail('attendance', 'Record the Gallabox teacher reply and resolve the duplicate candidate first.');
            $now = now()->format('Y-m-d H:i:s');
            if ($attendance->status !== 'approved') {
                DB::table('erp_attendance')->where('id', $attendanceId)->where('unit_id', $unitId)->update(['status' => 'approved', 'reviewed_by' => $actorId, 'reviewed_at' => $now, 'updated_at' => $now]);
                Audit::record($unitId, $actorId, 'attendance.approved', 'attendance', $attendanceId, ['policy' => config('erp.finance_policy', 'unconfigured')]);
            }
            $finance = new Finance();
            $finance->postTeacherAttendance($unitId, $actorId, $attendanceId);
            $status = $finance->attendancePostingStatus($unitId, $attendanceId);
            $result = DB::table('erp_attendance')->find($attendanceId);
            $result->student_financial_posted = $status['student_financial_posted'];
            $result->teacher_financial_posted = $status['teacher_financial_posted'];
            $result->financial_posted = $status['student_financial_posted'];
            return $result;
        });
    }

    public function importBatch(int $unitId, int $actorId, array $payload)
    {
        $ids = $payload['attendance_ids'] ?? [];
        $reference = trim((string) ($payload['reference'] ?? ''));
        if (!is_array($ids) || !$ids || count($ids) > 500 || $reference === '' || strlen($reference) > 120) $this->fail('batch', 'Provide 1–500 attendance IDs and a stable import reference of at most 120 characters.');
        foreach ($ids as $id) if (is_bool($id) || filter_var($id, FILTER_VALIDATE_INT) === false || (int) $id < 1) $this->fail('attendance_ids', 'Attendance IDs must be positive integers.');
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids, SORT_NUMERIC);
        return DB::transaction(function () use ($unitId, $actorId, $ids, $reference) {
            if (!DB::table('erp_units')->where('id', $unitId)->lockForUpdate()->first()) $this->fail('unit', 'Unknown unit.');
            $existing = DB::table('erp_attendance_batches')->where('reference', $reference)->lockForUpdate()->first();
            if ($existing) {
                $existingIds = array_column(json_decode($existing->snapshot, true, 512, JSON_THROW_ON_ERROR), 'attendance_id');
                sort($existingIds, SORT_NUMERIC);
                if ((int) $existing->unit_id !== $unitId || $existingIds !== $ids) $this->fail('reference', 'This import reference identifies a different batch.');
                return $existing;
            }
            if (config('erp.finance_policy', 'unconfigured') !== 'admin_import_delivered_only') $this->fail('policy', 'Administrator-import billing policy must be explicitly configured.');
            $records = DB::table('erp_attendance as a')->join('erp_lessons as l', 'l.id', '=', 'a.lesson_id')->where('a.unit_id', $unitId)->where('l.unit_id', $unitId)->whereIn('a.id', $ids)->select('a.id', 'l.student_id', 'l.teacher_id')->get();
            if ($records->count() !== count($ids)) $this->fail('attendance_ids', 'Every selected attendance row must belong to this unit.');
            $teacherIds = $records->pluck('teacher_id')->unique()->sort()->values()->all();
            DB::table('erp_teachers')->whereIn('id', $teacherIds)->orderBy('id')->lockForUpdate()->get();
            $studentIds = $records->pluck('student_id')->unique()->sort()->values()->all();
            DB::table('erp_students')->where('unit_id', $unitId)->whereIn('id', $studentIds)->orderBy('id')->lockForUpdate()->get();
            $snapshot = [];
            foreach ($ids as $id) {
                $attendance = $this->locked($unitId, $id);
                if (!in_array($attendance->status, ['submitted', 'approved'], true)) $this->fail('attendance_ids', 'Only submitted or approved attendance can be imported.');
                $lesson = $this->lesson($unitId, $attendance->lesson_id, true);
                if ($lesson->status !== 'completed') $this->fail('attendance_ids', 'Only delivered lessons can be imported.');
                $snapshot[] = ['attendance_id' => (int) $id, 'lesson_id' => (int) $lesson->id, 'actual_minutes' => (int) $attendance->actual_minutes, 'student_rate_paise' => (int) $lesson->student_rate_paise, 'currency' => $lesson->currency, 'posting_generation' => DB::table('erp_ledger')->where('attendance_id', $id)->where('kind', 'lesson_charge')->lockForUpdate()->get()->count() + 1];
            }
            $now = now()->format('Y-m-d H:i:s');
            $batchId = DB::table('erp_attendance_batches')->insertGetId(['unit_id' => $unitId, 'reference' => $reference, 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'imported_by' => $actorId, 'imported_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            foreach ($ids as $id) (new Finance())->postAttendance($unitId, $actorId, $id, $batchId);
            Audit::record($unitId, $actorId, 'attendance.batch_imported', 'attendance_batch', $batchId, ['reference' => $reference, 'snapshot' => $snapshot, 'payroll_posted' => false]);
            return DB::table('erp_attendance_batches')->find($batchId);
        });
    }

    public function resolveDuplicate(int $unitId, int $actorId, int $reviewId, array $payload)
    {
        $decision = $payload['decision'] ?? '';
        $reply = trim((string) ($payload['teacher_reply'] ?? ''));
        $reason = trim((string) ($payload['reason'] ?? ''));
        if (!in_array($decision, ['genuine', 'duplicate'], true) || $reply === '' || $reason === '' || ($payload['channel'] ?? 'gallabox') !== 'gallabox') $this->fail('review', 'A genuine/duplicate decision, Gallabox teacher reply, and reason are required.');
        $duplicateId = (int) ($payload['duplicate_attendance_id'] ?? 0);
        if ($decision === 'duplicate' && $duplicateId < 1) $this->fail('duplicate_attendance_id', 'Select the duplicate attendance row explicitly.');
        return DB::transaction(function () use ($unitId, $actorId, $reviewId, $decision, $reply, $reason, $duplicateId) {
            $review = DB::table('erp_duplicate_reviews')->where('unit_id', $unitId)->where('id', $reviewId)->first();
            if (!$review) $this->fail('review', 'Review does not belong to this unit.');
            if ($decision === 'duplicate' && !in_array($duplicateId, [(int) $review->attendance_id, (int) $review->other_attendance_id], true)) $this->fail('duplicate_attendance_id', 'The selected duplicate must be one of this review’s attendance rows.');
            $attendance = $this->locked($unitId, $review->attendance_id);
            $this->locked($unitId, $review->other_attendance_id);
            $review = DB::table('erp_duplicate_reviews')->where('unit_id', $unitId)->where('id', $reviewId)->lockForUpdate()->first();
            if ($review->status !== 'pending') {
                if ($review->status === $decision && $review->teacher_reply === $reply && $review->reason === $reason && ($decision !== 'duplicate' || DB::table('erp_attendance')->where('unit_id', $unitId)->where('id', $duplicateId)->lockForUpdate()->value('status') === 'rejected')) return $review;
                $this->fail('review', 'This review is already resolved.');
            }
            $now = now()->format('Y-m-d H:i:s');
            if ($decision === 'duplicate') {
                (new Finance())->reverseAttendance($unitId, $actorId, $duplicateId, $reason);
                DB::table('erp_attendance')->where('id', $duplicateId)->where('unit_id', $unitId)->update(['status' => 'rejected', 'reviewed_by' => $actorId, 'reviewed_at' => $now, 'updated_at' => $now]);
            }
            if ($decision === 'duplicate') {
                $related = DB::table('erp_duplicate_reviews')->where('unit_id', $unitId)->where('status', 'pending')->where('id', '!=', $reviewId)->where(function ($q) use ($duplicateId) { $q->where('attendance_id', $duplicateId)->orWhere('other_attendance_id', $duplicateId); })->lockForUpdate()->get();
                foreach ($related as $edge) {
                    DB::table('erp_duplicate_reviews')->where('unit_id', $unitId)->where('id', $edge->id)->update(['status' => 'duplicate', 'teacher_reply' => $reply, 'reason' => $reason, 'reviewed_by' => $actorId, 'reviewed_at' => $now, 'updated_at' => $now]);
                    Audit::record($unitId, $actorId, 'attendance.duplicate_edge_resolved', 'duplicate_review', $edge->id, ['duplicate_attendance_id' => $duplicateId, 'source_review_id' => $reviewId, 'reason' => $reason]);
                }
            }
            DB::table('erp_duplicate_reviews')->where('unit_id', $unitId)->where('id', $reviewId)->update(['status' => $decision, 'teacher_reply' => $reply, 'reason' => $reason, 'reviewed_by' => $actorId, 'reviewed_at' => $now, 'updated_at' => $now]);
            Audit::record($unitId, $actorId, 'attendance.duplicate_resolved', 'duplicate_review', $reviewId, ['decision' => $decision, 'duplicate_attendance_id' => $decision === 'duplicate' ? $duplicateId : null, 'teacher_reply' => $reply, 'reason' => $reason, 'channel' => 'gallabox']);
            return DB::table('erp_duplicate_reviews')->find($reviewId);
        });
    }

    public function correct(int $unitId, int $actorId, int $attendanceId, array $payload)
    {
        $minutes = $this->minutes($payload['actual_minutes'] ?? null);
        $reason = trim((string) ($payload['reason'] ?? ''));
        if ($reason === '') $this->fail('reason', 'An audit reason is required.');
        return DB::transaction(function () use ($unitId, $actorId, $attendanceId, $minutes, $reason) {
            $attendance = $this->locked($unitId, $attendanceId);
            if ($attendance->status === 'rejected') $this->fail('attendance', 'Rejected attendance cannot be corrected into a charge.');
            if ((int) $attendance->actual_minutes === $minutes) return $attendance;
            (new Finance())->reverseAttendance($unitId, $actorId, $attendanceId, $reason);
            DB::table('erp_attendance')->where('id', $attendanceId)->where('unit_id', $unitId)->update(['actual_minutes' => $minutes, 'status' => 'submitted', 'reviewed_by' => null, 'reviewed_at' => null, 'updated_at' => now()->format('Y-m-d H:i:s')]);
            Audit::record($unitId, $actorId, 'attendance.corrected', 'attendance', $attendanceId, ['old_minutes' => $attendance->actual_minutes, 'actual_minutes' => $minutes, 'reason' => $reason, 'requires_reimport' => true]);
            return DB::table('erp_attendance')->find($attendanceId);
        });
    }

    private function lesson(int $unitId, int $lessonId, bool $current = false)
    {
        $query = DB::table('erp_lessons')->where('unit_id', $unitId)->where('id', $lessonId);
        $lesson = ($current ? $query->lockForUpdate() : $query)->first();
        if (!$lesson) $this->fail('lesson_id', 'Lesson does not belong to this unit.');
        // Mutable integrity checks use locking current reads after parent serialization.
        if ($current) {
            if (!DB::table('erp_students')->where('unit_id', $unitId)->where('id', $lesson->student_id)->lockForUpdate()->first() || !DB::table('erp_teacher_units')->where('unit_id', $unitId)->where('teacher_id', $lesson->teacher_id)->lockForUpdate()->first()) $this->fail('lesson_id', 'Lesson and its parties must belong to this unit.');
            if (!DB::table('erp_enrollments')->where('unit_id', $unitId)->where('id', $lesson->enrollment_id)->where('student_id', $lesson->student_id)->where('teacher_id', $lesson->teacher_id)->where('subject_id', $lesson->subject_id)->lockForUpdate()->first()) $this->fail('lesson_id', 'Lesson enrollment and parties do not match this unit.');
        }
        return $lesson;
    }

    private function locked(int $unitId, int $attendanceId)
    {
        // Initial reads discover immutable parent IDs only, never decide mutable state.
        $attendance = DB::table('erp_attendance')->where('unit_id', $unitId)->where('id', $attendanceId)->first();
        if (!$attendance) $this->fail('attendance', 'Attendance does not belong to this unit.');
        $lesson = $this->lesson($unitId, $attendance->lesson_id);
        DB::table('erp_teachers')->where('id', $lesson->teacher_id)->lockForUpdate()->first();
        DB::table('erp_students')->where('unit_id', $unitId)->where('id', $lesson->student_id)->lockForUpdate()->first();
        $this->lesson($unitId, $lesson->id, true);
        return DB::table('erp_attendance')->where('unit_id', $unitId)->where('id', $attendanceId)->lockForUpdate()->first();
    }

    private function minutes($value): int
    {
        if (is_bool($value) || filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1 || (int) $value > 1440) $this->fail('actual_minutes', 'Actual duration must be a whole number from 1 to 1440 minutes.');
        return (int) $value;
    }

    private function fail(string $field, string $message): void { throw ValidationException::withMessages([$field => $message]); }
}
