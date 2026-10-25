<?php

namespace App\Modules\Academics\Services;

use App\Models\MarklistReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Live Cloudflare submissions plus historical imports, without a Sheets round trip. */
class AssessmentHistory
{
    public function query(): Builder
    {
        $fields = ['teacher_name', 'teacher_code', 'student_name', 'student_code', 'chapter',
            'mark_scored', 'total_mark', 'mode_of_exam', 'examination_date', 'remark'];
        $local = DB::table('marklists as marks')->leftJoin('subjects', 'subjects.id', '=', 'marks.subject')
            ->select('marks.id');
        foreach ($fields as $field) $local->addSelect('marks.'.$field);
        $local->selectRaw("COALESCE(subjects.name, '') as subject, marks.created_at as date_submitted");

        // A historical sync can contain a copy of an already saved local result.
        // Compare the canonical report fields; local data wins that exact match.
        $historical = DB::table('marklist_reports as history')->select('history.id');
        foreach ($fields as $field) $historical->addSelect('history.'.$field);
        $historical->addSelect('history.subject', 'history.date_submitted')
            ->whereNotExists(function ($query) use ($local, $fields) {
                $query->selectRaw('1')->fromSub(clone $local, 'saved');
                foreach (array_merge($fields, ['subject', 'date_submitted']) as $field) {
                    if (in_array($field, ['mark_scored', 'total_mark'], true)) {
                        $query->whereRaw("CAST(saved.$field AS DECIMAL(20,6)) = CAST(history.$field AS DECIMAL(20,6))");
                    } else {
                        $query->whereRaw("COALESCE(saved.$field, '') = COALESCE(history.$field, '')");
                    }
                }
            });
        return MarklistReport::query()->fromSub($local->unionAll($historical), 'marklist_reports');
    }
}
