<?php
namespace App\Modules\Erp\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Bounded, read-only calendar projection. Scheduling and finance remain in their own services. */
class Timetable
{
    public function week(int $unitId, string $timezone, array $filters): array
    {
        $start = CarbonImmutable::parse($filters['date'] ?? now($timezone)->toDateString(), $timezone)->startOfWeek()->startOfDay();
        $end = $start->addWeek();
        $query = DB::table('erp_lessons as l')
            ->join('erp_students as s', function ($join) { $join->on('s.id', '=', 'l.student_id')->on('s.unit_id', '=', 'l.unit_id'); })
            ->join('erp_teachers as t', 't.id', '=', 'l.teacher_id')
            ->join('erp_subjects as subject', 'subject.id', '=', 'l.subject_id')
            ->whereExists(function ($q) { $q->selectRaw('1')->from('erp_teacher_units as pool')->whereColumn('pool.teacher_id', 'l.teacher_id')->whereColumn('pool.unit_id', 'l.unit_id'); })
            ->where('l.unit_id', $unitId)
            ->where('l.starts_at', '<', $end->utc()->format('Y-m-d H:i:s'))
            ->where('l.ends_at', '>', $start->utc()->format('Y-m-d H:i:s'));
        foreach (['student' => 's', 'teacher' => 't'] as $filter => $alias) {
            $value = trim($filters[$filter] ?? '');
            if ($value !== '') $query->where(function ($q) use ($alias, $value) {
                $q->where($alias.'.name', 'like', '%'.$value.'%')->orWhere($alias.'.code', 'like', '%'.$value.'%');
            });
        }
        if (!empty($filters['status'])) $query->where('l.status', $filters['status']);
        $lessons = $query->select('l.*', 's.name as student_name', 's.code as student_code', 't.name as teacher_name', 't.code as teacher_code', 'subject.name as subject_name')
            ->orderBy('l.starts_at')->orderBy('l.id')->limit(251)->get();
        $truncated = $lessons->count() > 250;
        $lessons = $lessons->take(250);
        foreach ($lessons as $lesson) {
            $lesson->local_start = CarbonImmutable::parse($lesson->starts_at, 'UTC')->setTimezone($timezone);
            $lesson->local_end = CarbonImmutable::parse($lesson->ends_at, 'UTC')->setTimezone($timezone);
        }
        $days = [];
        for ($date = $start; $date->lt($end); $date = $date->addDay()) {
            $days[$date->toDateString()] = ['date' => $date, 'lessons' => $lessons->filter(function ($lesson) use ($date) {
                return $lesson->local_start->lt($date->addDay()) && $lesson->local_end->gt($date);
            })];
        }
        return ['start' => $start, 'end' => $end, 'days' => $days, 'count' => $lessons->count(), 'truncated' => $truncated, 'filters' => $filters];
    }
}
