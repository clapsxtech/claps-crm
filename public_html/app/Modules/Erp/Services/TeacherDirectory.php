<?php

namespace App\Modules\Erp\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Teacher list for sales: filters by level and category, ordered by registration or teacher code, with weekly timetable hours. */
class TeacherDirectory
{
    public const LEVELS = ['LP' => 'LP (Lower Primary)', 'UP' => 'UP (Upper Primary)', 'HS' => 'HS (High School)'];
    public const CATEGORIES = ['Standard', 'Advanced'];
    public const SORTS = ['code' => 'CT code', 'registered_desc' => 'Registered (newest first)', 'registered_asc' => 'Registered (oldest first)'];

    public function filters(array $p): array
    {
        return Validator::make($p, [
            'teacher_q' => 'nullable|string|max:100',
            'level' => 'nullable|in:'.implode(',', array_keys(self::LEVELS)),
            'category' => 'nullable|in:'.implode(',', self::CATEGORIES),
            'subject_id' => 'nullable|integer|min:1',
            'sort' => 'nullable|in:'.implode(',', array_keys(self::SORTS)),
        ])->validate();
    }

    /** Weekly minutes from bilaterally confirmed recurring agreements that are active on the given date. */
    public static function weeklyMinutes(int $unit, string $date): \Illuminate\Database\Query\Builder
    {
        return DB::table('erp_agreements as a')->join('erp_enrollments as e', 'e.id', '=', 'a.enrollment_id')
            ->where('a.unit_id', $unit)->where('a.status', 'confirmed')->where('a.starts_on', '<=', $date)
            ->where(fn ($q) => $q->whereNull('a.ends_on')->orWhere('a.ends_on', '>=', $date))
            ->groupBy('e.teacher_id')->select('e.teacher_id')->selectRaw('SUM(a.duration_minutes) AS minutes');
    }

    public function list(int $unit, array $p, string $timezone)
    {
        $f = $this->filters($p);
        $today = CarbonImmutable::now($timezone)->toDateString();
        $q = DB::table('erp_teachers as t')->join('erp_teacher_units as u', 'u.teacher_id', '=', 't.id')
            // One row per teacher even if several unmerged recruitment records point to it.
            ->leftJoinSub(DB::table('erp_teacher_candidates')->whereNotNull('teacher_id')->whereNull('merged_into_id')->groupBy('teacher_id')
                ->select('teacher_id')->selectRaw('MAX(phone) AS phone, MAX(qualification) AS qualification, MAX(subjects) AS subjects, MIN(registered_at) AS registered_at'), 'c', 'c.teacher_id', '=', 't.id')
            ->leftJoin('erp_teacher_matching_profiles as m', 'm.teacher_id', '=', 't.id')
            ->leftJoinSub(self::weeklyMinutes($unit, $today), 'tt', 'tt.teacher_id', '=', 't.id')
            ->where('u.unit_id', $unit)->where('t.status', 'active')
            ->select('t.id', 't.code', 't.name', 't.timezone', 't.levels', 't.category', 't.created_at', 'c.phone', 'c.qualification', 'c.subjects as candidate_subjects', 'c.registered_at', 'm.mediums')
            ->selectRaw('COALESCE(tt.minutes, 0) AS tt_minutes')
            ->selectRaw('COALESCE(c.registered_at, t.created_at) AS registered_sort');
        if (!empty($f['teacher_q'])) $q->where(fn ($w) => $w->where('t.name', 'like', '%'.$f['teacher_q'].'%')->orWhere('t.code', 'like', '%'.$f['teacher_q'].'%'));
        // Level codes LP, UP and HS never overlap as substrings of the stored list.
        if (!empty($f['level'])) $q->where('t.levels', 'like', '%'.$f['level'].'%');
        if (!empty($f['category'])) $q->where('t.category', $f['category']);
        if (!empty($f['subject_id'])) $q->whereExists(fn ($e) => $e->selectRaw('1')->from('erp_eligibilities')->whereColumn('teacher_id', 't.id')->where('subject_id', $f['subject_id']));
        $sort = $f['sort'] ?? 'code';
        if ($sort === 'code') self::orderByCode($q);
        else $q->orderByRaw('COALESCE(c.registered_at, t.created_at) '.($sort === 'registered_asc' ? 'ASC' : 'DESC'))->orderByDesc('t.id');
        $teachers = $q->paginate(24, ['*'], 'teachers_page')->withQueryString();

        $ids = $teachers->pluck('id')->all();
        $eligibilities = DB::table('erp_eligibilities as e')->join('erp_subjects as s', 's.id', '=', 'e.subject_id')->whereIn('e.teacher_id', $ids ?: [0])
            ->orderBy('s.name')->get(['e.teacher_id', 's.name', 'e.grade', 'e.syllabus'])->groupBy('teacher_id');
        foreach ($teachers as $teacher) {
            $rows = $eligibilities[$teacher->id] ?? collect();
            $teacher->subjects = $rows->pluck('name')->unique()->implode(', ') ?: ($teacher->candidate_subjects ?: null);
            $teacher->classes = $rows->map(fn ($r) => $r->grade.' '.$r->syllabus)->unique()->implode(', ') ?: null;
            $teacher->mediums = implode(', ', json_decode($teacher->mediums ?? '[]', true) ?: []) ?: null;
            $teacher->level_list = array_values(array_filter(explode(',', (string) $teacher->levels)));
            $teacher->levels = $teacher->level_list ? implode(', ', $teacher->level_list) : null;
            $teacher->tt_hours = self::hours((int) $teacher->tt_minutes);
        }
        return $teachers;
    }

    public static function orderByCode($q, string $alias = 't'): void
    {
        // Natural order for codes such as CT9, CT10, CT100.
        $q->orderByRaw('LENGTH('.$alias.'.code)')->orderBy($alias.'.code');
    }

    public static function hours(int $minutes): string
    {
        $hours = round($minutes / 60, 1);
        return rtrim(rtrim(number_format($hours, 1, '.', ''), '0'), '.');
    }

    public function classify(int $unit, int $actorId, array $p): object
    {
        $v = Validator::make($p, [
            'teacher_id' => 'required|integer|min:1',
            'levels' => 'nullable|array|max:3', 'levels.*' => 'in:'.implode(',', array_keys(self::LEVELS)),
            'category' => 'nullable|in:'.implode(',', self::CATEGORIES),
        ])->validate();
        return DB::transaction(function () use ($unit, $actorId, $v) {
            abort_unless(DB::table('erp_teacher_units')->where('unit_id', $unit)->where('teacher_id', $v['teacher_id'])->exists(), 404);
            $levels = array_values(array_intersect(array_keys(self::LEVELS), $v['levels'] ?? []));
            $values = ['levels' => $levels ? implode(',', $levels) : null, 'category' => $v['category'] ?? null];
            DB::table('erp_teachers')->where('id', $v['teacher_id'])->update($values + ['updated_at' => now()]);
            Audit::record($unit, $actorId, 'teacher.classified', 'teacher', $v['teacher_id'], $values);
            return DB::table('erp_teachers')->find($v['teacher_id']);
        });
    }
}
