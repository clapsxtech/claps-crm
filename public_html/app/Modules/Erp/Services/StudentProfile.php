<?php

namespace App\Modules\Erp\Services;

use Illuminate\Support\Facades\DB;

/** Read-only, unit-scoped projections. Never authorize or post money from this snapshot. */
class StudentProfile
{
    public function read(int $unitId, int $studentId): array
    {
        $student = DB::table('erp_students')->where('unit_id', $unitId)->where('id', $studentId)->first();
        abort_unless($student, 404);
        $sheetProfile = DB::table('erp_student_profiles')->where('unit_id', $unitId)->where('student_id', $studentId)->first();

        $admissionHandoffs = DB::table('erp_leads')->where('unit_id', $unitId)->where('student_id', $studentId)->select('id', 'source', 'joined_on', 'learning_requirement', 'concern', 'about_student', 'about_parent')->orderByDesc('id')->get();

        $assignments = $this->parties('erp_enrollments', $unitId, $studentId)
            ->select('p.id', 'p.starts_on', 'p.ends_on', 'p.status', 't.name as teacher_name', 'subject.name as subject_name')
            ->orderByDesc('p.id')->paginate(10, ['*'], 'assignments_page')->withQueryString();
        $agreements = $this->parties('erp_enrollments', $unitId, $studentId)
            ->join('erp_agreements as a', function ($join) { $join->on('a.enrollment_id', '=', 'p.id')->on('a.unit_id', '=', 'p.unit_id'); })
            ->select('a.id', 'a.weekday', 'a.start_time', 'a.duration_minutes', 'a.timezone', 'a.starts_on', 'a.ends_on', 'a.status', 't.name as teacher_name', 'subject.name as subject_name')
            ->orderByDesc('a.id')->paginate(10, ['*'], 'agreements_page')->withQueryString();
        $lessons = $this->parties('erp_lessons', $unitId, $studentId)
            ->select('p.id', 'p.starts_at', 'p.ends_at', 'p.status', 'p.kind', 't.name as teacher_name', 'subject.name as subject_name')
            ->orderByDesc('p.starts_at')->orderByDesc('p.id')->paginate(10, ['*'], 'lessons_page')->withQueryString();
        $attendance = $this->parties('erp_lessons', $unitId, $studentId)
            ->join('erp_attendance as a', function ($join) { $join->on('a.lesson_id', '=', 'p.id')->on('a.unit_id', '=', 'p.unit_id'); })
            ->select('a.id', 'a.actual_minutes', 'a.status', 'p.starts_at', 't.name as teacher_name', 'subject.name as subject_name')
            ->orderByDesc('p.starts_at')->orderByDesc('a.id')->paginate(10, ['*'], 'attendance_page')->withQueryString();

        // Student balances and receipt history deliberately exclude all teacher-owned entries.
        $ledgerQuery = $this->studentRows('erp_ledger', $unitId, $studentId)->whereNull('teacher_id');
        $balances = (clone $ledgerQuery)->select('currency')->selectRaw('SUM(amount_paise) AS balance_paise')->groupBy('currency')->orderBy('currency')->get();
        $ledger = $ledgerQuery->select('id', 'kind', 'amount_paise', 'currency', 'reference', 'reverses_id', 'occurred_at')
            ->orderByDesc('id')->paginate(15, ['*'], 'ledger_page')->withQueryString();
        $requests = $this->studentRows('erp_payment_requests', $unitId, $studentId)
            ->orderByDesc('id')->paginate(10, ['*'], 'requests_page')->withQueryString();
        $requestSummaries = (new Reporting())->requestSummaries($unitId, $requests->pluck('id')->all());
        $followups = $this->studentRows('erp_followups', $unitId, $studentId)
            ->orderByDesc('id')->paginate(10, ['*'], 'followups_page')->withQueryString();
        $tasks = $this->studentRows('erp_tasks', $unitId, $studentId)
            ->orderByDesc('id')->paginate(10, ['*'], 'tasks_page')->withQueryString();
        $labels = ['assigned_to' => DB::table('users')
            ->where('business_id', config('erp.business_id'))
            ->whereIn('id', $tasks->pluck('assigned_to')->filter()->all())
            ->whereIn('id', DB::table('erp_memberships')->where('unit_id', $unitId)->select('user_id'))
            ->pluck('name', 'id')->all()];
        return compact('student', 'sheetProfile', 'admissionHandoffs', 'assignments', 'agreements', 'lessons', 'attendance', 'balances', 'ledger', 'requests', 'requestSummaries', 'followups', 'tasks', 'labels');
    }

    private function studentRows(string $table, int $unitId, int $studentId)
    {
        return DB::table($table)->where('unit_id', $unitId)->where('student_id', $studentId);
    }

    private function parties(string $table, int $unitId, int $studentId)
    {
        return DB::table($table.' as p')->where('p.unit_id', $unitId)->where('p.student_id', $studentId)
            ->join('erp_teachers as t', 't.id', '=', 'p.teacher_id')
            ->join('erp_subjects as subject', 'subject.id', '=', 'p.subject_id')
            ->whereExists(function ($query) {
                $query->selectRaw('1')->from('erp_teacher_units as pool')->whereColumn('pool.teacher_id', 'p.teacher_id')->whereColumn('pool.unit_id', 'p.unit_id');
            });
    }
}
