<?php
namespace App\Modules\Attendance\Services;

use App\Models\WorkHour;
use App\Modules\Integrations\Services\SheetSnapshotReader as Reader;
use App\Modules\Integrations\Services\ReplaceSnapshot;
use Illuminate\Support\Facades\DB;

/** Compatibility import only: refuse to discard any locally submitted ledger row. */
class ImportAttendanceSnapshot
{
    public function replace(array $values): void
    {
        $records = [];
        foreach (Reader::rows($values, 16) as $row) {
            $record = [
                'teacher_name' => $row[0], 'teacher_code' => $row[1],
                'student_name' => $row[2], 'student_code' => $row[3],
                'subject' => $row[4], 'date' => Reader::date($row[5]), 'date_submitted' => $row[6],
                'chapter' => $row[7], 'topic' => $row[8], 'hour_taken' => Reader::decimal($row[9]),
                'homework_status' => $row[10], 'dob' => null,
                'hour_payment' => Reader::decimal($row[12]), 'profit_per_hour' => Reader::decimal($row[13], true),
                'payment_per_hour' => Reader::decimal($row[14]), 'total_amount' => Reader::decimal($row[15]),
            ];
            Reader::requireFields($record, ['teacher_name', 'teacher_code', 'student_name', 'student_code', 'subject', 'chapter', 'topic', 'date_submitted']);
            if ((float) $record['hour_taken'] <= 0 || (float) $record['hour_taken'] > 24 || !in_array($record['homework_status'], ['yes', 'no'], true)) Reader::invalid();
            try { new \DateTimeImmutable($record['date_submitted']); } catch (\Throwable $e) { Reader::invalid(); }
            $records[] = $record;
        }
        DB::transaction(function () use ($records) {
            // Lock existing rows before checking; do not silently erase retry keys.
            $existing = WorkHour::query()->lockForUpdate()->get(['id', 'submission_key']);
            abort_if($existing->contains(fn ($row) => $row->submission_key !== null), 409,
                'Attendance contains local submissions. Reconciliation is required before snapshot replacement.');
            app(ReplaceSnapshot::class)->replace(WorkHour::class, $records);
        });
    }
}
