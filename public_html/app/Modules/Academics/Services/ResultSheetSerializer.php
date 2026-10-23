<?php

namespace App\Modules\Academics\Services;

use App\Models\Marklist;
use App\Models\Performance;
use Illuminate\Support\Carbon;

class ResultSheetSerializer
{
    /** Canonical MARKLIST v1: the existing public 12-column order, no DOB. */
    public function marklist(Marklist $record, array $names): array
    {
        return [
            'schema' => 'marklist.v1',
            'range' => 'MARKLIST!A:L',
            'values' => [[
                $names['teacher_name'], $record->teacher_code,
                $names['student_name'], $record->student_code,
                $names['subject'], $record->chapter,
                $record->mark_scored, $record->total_mark,
                $record->mode_of_exam,
                Carbon::parse($record->examination_date)->format('d/m/Y'),
                $record->remark,
                $record->created_at->format('d/m/Y H:i:s'),
            ]],
        ];
    }

    public function performance(Performance $record): array
    {
        return [
            'schema' => 'performance.v1',
            'range' => 'REVIEW!A:G',
            'values' => [[
                $record->created_at->format('d/m/Y H:i:s'),
                $record->teacher_name, $record->teacher_code,
                $record->student_name, $record->student_code,
                $record->topic, $record->remark,
            ]],
        ];
    }
}
