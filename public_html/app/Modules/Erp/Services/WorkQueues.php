<?php
namespace App\Modules\Erp\Services;

use Illuminate\Support\Facades\DB;

/** Read-only work lists, never authority for a financial mutation. Services revalidate on POST. */
class WorkQueues
{
    public function attendance(int $unitId): array
    {
        $base = DB::table('erp_attendance as a')
            ->join('erp_lessons as l', function ($j) { $j->on('l.id', '=', 'a.lesson_id')->on('l.unit_id', '=', 'a.unit_id'); })
            ->join('erp_students as s', function ($j) { $j->on('s.id', '=', 'l.student_id')->on('s.unit_id', '=', 'l.unit_id'); })
            ->join('erp_teachers as t', 't.id', '=', 'l.teacher_id')
            ->join('erp_subjects as subject', 'subject.id', '=', 'l.subject_id')
            ->whereExists(function ($q) { $q->selectRaw('1')->from('erp_teacher_units as pool')->whereColumn('pool.teacher_id', 't.id')->whereColumn('pool.unit_id', 'a.unit_id'); })
            ->where('a.unit_id', $unitId)->where('l.status', 'completed')->whereIn('a.status', ['submitted', 'approved'])
            ->select('a.id', 'a.lesson_id', 'a.actual_minutes', 'a.status', 'l.starts_at', 'l.kind', 's.name as student_name', 's.code as student_code', 't.name as teacher_name', 'subject.name as subject_name')
            ->selectSub(function ($q) use ($unitId) {
                $q->from('erp_duplicate_reviews as d')->selectRaw('COUNT(*)')->where('d.unit_id', $unitId)->where('d.status', 'pending')
                    ->where(function ($q) { $q->whereColumn('d.attendance_id', 'a.id')->orWhereColumn('d.other_attendance_id', 'a.id'); });
            }, 'pending_reviews');
        $student = clone $base;
        $student->where('l.kind', '!=', 'demo')->whereNotExists(function ($q) use ($unitId) {
            $q->selectRaw('1')->from('erp_ledger as charge')->where('charge.unit_id', $unitId)->whereColumn('charge.attendance_id', 'a.id')->where('charge.kind', 'lesson_charge')
                ->whereNotExists(function ($q) { $q->selectRaw('1')->from('erp_ledger as reversal')->whereColumn('reversal.reverses_id', 'charge.id'); });
        });
        $teacher = clone $base;
        $teacher->where(function ($q) use ($unitId) {
            $q->where('a.status', 'submitted')->orWhere(function ($q) use ($unitId) {
                $q->where('a.status', 'approved')->where('l.kind', '!=', 'demo')->whereNotExists(function ($q) use ($unitId) {
                    $q->selectRaw('1')->from('erp_ledger as accrual')->where('accrual.unit_id', $unitId)->whereColumn('accrual.attendance_id', 'a.id')->where('accrual.kind', 'teacher_accrual')
                        ->whereNotExists(function ($q) { $q->selectRaw('1')->from('erp_ledger as reversal')->whereColumn('reversal.reverses_id', 'accrual.id'); });
                });
            });
        });
        return [
            'student_import' => $student->orderBy('l.starts_at')->orderBy('a.id')->paginate(25, ['*'], 'import_queue_page')->withQueryString(),
            'teacher_approval' => $teacher->orderBy('l.starts_at')->orderBy('a.id')->paginate(25, ['*'], 'approval_queue_page')->withQueryString(),
        ];
    }
}
