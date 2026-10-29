<?php

namespace App\Modules\Erp\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Preview-first importer for read-only exports of the operational Sheets.
 *
 * Only stable identity columns are imported automatically. Formula-heavy
 * presentation tabs (timetable summaries, due dashboards and invoice
 * templates) are reported as sources but are deliberately not treated as
 * canonical transactions.
 */
class SheetCsvImporter
{
    private const STUDENT_FIELDS = [
        'band' => 0,
        'name' => 1,
        'status' => 2,
        'code' => 3,
        'hour_rate' => 4,
        'section' => 5,
        'hours' => 7,
        'whatsapp' => 13,
        'country' => 14,
        'source' => 15,
        'gmeet' => 16,
        'father' => 18,
        'mother' => 19,
        'address' => 20,
        'city' => 21,
        'pin' => 22,
        'student_contact' => 23,
        'contact' => 24,
        'email' => 25,
        'school' => 26,
        'grade' => 27,
        'syllabus' => 28,
        'medium' => 29,
        'preferred_time' => 30,
        'network' => 31,
        'submitted_at' => 32,
        'demo_status' => 38,
    ];

    private const TEACHER_FIELDS = [
        'code' => 2,
        'name' => 3,
        'status' => 6,
        'category' => 8,
        'phone' => 14,
    ];

    public function preview(string $directory, ?int $unitId = null, ?string $band = null): array
    {
        $band = $band ? strtoupper(trim($band)) : null;
        $parsed = $this->parse($directory, $band);
        return [
            'valid' => $parsed['issues'] === [],
            'mode' => 'preview',
            'directory' => $directory,
            'unit_id' => $unitId,
            'band' => $band,
            'sources' => $parsed['sources'],
            'snapshot_digest' => hash('sha256', json_encode($parsed['sources'], JSON_THROW_ON_ERROR)),
            'counts' => [
                'students' => count($parsed['students']),
                'teachers' => count($parsed['teachers']),
                'tasks' => $parsed['task_count'],
                'task_templates' => count($parsed['templates']),
            ],
            'issues' => $parsed['issues'],
            'not_imported_automatically' => [
                'master-2025.csv' => 'The exported first tab is a monthly attendance and hours summary; it has no one-row-per-lesson identity for safe posting.',
                'tt-2025.csv' => 'The exported first tab is a rendered timetable summary; it has no stable lesson row identity.',
                'duplicate.csv' => 'Duplicate review rows need canonical lesson/enrollment matching before attendance posting.',
                'invoice-generator.csv' => 'The exported first tab is an invoice template, not a payment ledger.',
            ],
        ];
    }

    public function execute(string $directory, int $unitId, int $actorId, bool $overwrite = false, ?string $band = null): array
    {
        $band = $band ? strtoupper(trim($band)) : null;
        $parsed = $this->parse($directory, $band);
        if ($parsed['issues'] !== []) {
            throw new InvalidArgumentException('The CSV snapshot has blocking validation issues. Preview it before execution.');
        }
        $unit = DB::table('erp_units')->where('id', $unitId)->first();
        if (!$unit) throw new InvalidArgumentException('The target business unit does not exist.');
        if (!UserUnitAccess::canManage($unitId, $actorId)) throw new InvalidArgumentException('The actor must be an administrator or team manager for the target unit.');

        // Reject known identity conflicts before committing any resumable chunk.
        // Recheck under each chunk's transaction to cover changes after preflight.
        foreach ($parsed['students'] as $student) $this->studentTarget($student, $unitId);
        foreach ($parsed['teachers'] as $teacher) $this->teacherTarget($teacher, $unitId);

        $created = ['students' => 0, 'student_profiles' => 0, 'teachers' => 0, 'teacher_profiles' => 0, 'tasks' => 0, 'task_items' => 0, 'task_templates' => 0];
        $studentIds = [];

        // D1 accepts at most 900 mutation statements per atomic commit. Keep
        // every phase below that ceiling so a full snapshot is restartable.
        foreach (array_chunk($parsed['students'], 200) as $chunk) {
            $result = DB::transaction(fn () => $this->importStudentChunk($chunk, $unit, $unitId, $overwrite));
            $this->mergeCounts($created, $result['counts']);
            $studentIds += $result['ids'];
        }

        foreach (array_chunk($parsed['teachers'], 200) as $chunk) {
            $result = DB::transaction(fn () => $this->importTeacherChunk($chunk, $unit, $unitId, $overwrite));
            $this->mergeCounts($created, $result);
        }

        foreach (array_chunk($parsed['templates'], 200) as $chunk) {
            $result = DB::transaction(fn () => $this->importTemplateChunk($chunk, $unitId, $actorId, $overwrite));
            $this->mergeCounts($created, $result);
        }

        $runtimeIssues = [];
        $taskChunk = [];
        $taskWeight = 0;
        foreach ($this->tasks($directory, $runtimeIssues, $band) as $task) {
            $weight = 1 + count($task['checklist']);
            if ($taskChunk !== [] && $taskWeight + $weight > 600) {
                $result = DB::transaction(fn () => $this->importTaskChunk($taskChunk, $unitId, $actorId, $studentIds, $overwrite));
                $this->mergeCounts($created, $result);
                $taskChunk = [];
                $taskWeight = 0;
            }
            $taskChunk[] = $task;
            $taskWeight += $weight;
        }
        if ($taskChunk !== []) {
            $result = DB::transaction(fn () => $this->importTaskChunk($taskChunk, $unitId, $actorId, $studentIds, $overwrite));
            $this->mergeCounts($created, $result);
        }

        DB::transaction(fn () => Audit::record($unitId, $actorId, 'sheets.snapshot_imported', 'sheets_snapshot', null, ['counts' => $created, 'overwrite' => $overwrite]));
        return ['valid' => true, 'mode' => 'execute', 'unit_id' => $unitId, 'writes' => array_sum($created), 'counts' => $created];
    }

    private function importStudentChunk(array $students, object $unit, int $unitId, bool $overwrite): array
    {
        $counts = ['students' => 0, 'student_profiles' => 0];
        $ids = [];
        foreach ($students as $student) {
            $existing = $this->studentTarget($student, $unitId);
            $values = ['name' => $student['name'], 'registered_on' => $student['registered_on'] ?: now($unit->timezone)->toDateString(), 'timezone' => $unit->timezone, 'status' => $student['status'], 'grade' => $student['grade'] ?: 'Not specified', 'syllabus' => $student['syllabus'] ?: 'Not specified', 'guardian_contact' => $student['guardian_contact'], 'updated_at' => now()];
            if ($existing) {
                $studentId = (int) $existing->id;
                if ($overwrite) DB::table('erp_students')->where('id', $studentId)->update($values);
            } else {
                $studentId = (int) DB::table('erp_students')->insertGetId($values + ['unit_id' => $unitId, 'code' => $student['code'], 'created_at' => now()]);
                ++$counts['students'];
            }
            $ids[$student['code']] = $studentId;
            $profile = $this->studentProfileValues($student, $unitId, $studentId);
            if (DB::table('erp_student_profiles')->where('student_id', $studentId)->exists()) {
                if ($overwrite) DB::table('erp_student_profiles')->where('student_id', $studentId)->update($profile + ['updated_at' => now()]);
            } else {
                DB::table('erp_student_profiles')->insert($profile + ['created_at' => now(), 'updated_at' => now()]);
                ++$counts['student_profiles'];
            }
        }
        return ['counts' => $counts, 'ids' => $ids];
    }

    private function importTeacherChunk(array $teachers, object $unit, int $unitId, bool $overwrite): array
    {
        $counts = ['teachers' => 0, 'teacher_profiles' => 0];
        foreach ($teachers as $teacher) {
            $existing = $this->teacherTarget($teacher, $unitId);
            $values = ['name' => $teacher['name'], 'status' => $teacher['status'], 'timezone' => $unit->timezone, 'updated_at' => now()];
            if ($existing) {
                $teacherId = (int) $existing->id;
                if ($overwrite) DB::table('erp_teachers')->where('id', $teacherId)->update($values);
            } else {
                $teacherId = (int) DB::table('erp_teachers')->insertGetId($values + ['code' => $teacher['code'], 'created_at' => now()]);
                ++$counts['teachers'];
            }
            if (!DB::table('erp_teacher_units')->where('teacher_id', $teacherId)->first()) DB::table('erp_teacher_units')->insert(['teacher_id' => $teacherId, 'unit_id' => $unitId, 'created_at' => now(), 'updated_at' => now()]);
            $profile = ['unit_id' => $unitId, 'teacher_id' => $teacherId, 'whatsapp_number' => $teacher['phone'], 'category' => $teacher['category'], 'teacher_type' => null, 'medium' => null, 'source_external_id' => $teacher['code'], 'updated_at' => now()];
            if (DB::table('erp_teacher_profiles')->where('teacher_id', $teacherId)->exists()) {
                if ($overwrite) DB::table('erp_teacher_profiles')->where('teacher_id', $teacherId)->update($profile);
            } else {
                DB::table('erp_teacher_profiles')->insert($profile + ['created_at' => now()]);
                ++$counts['teacher_profiles'];
            }
        }
        return $counts;
    }

    private function studentTarget(array $student, int $unitId): ?object
    {
        $existing = DB::table('erp_students')->where('code', $student['code'])->lockForUpdate()->first();
        if (!$existing) return null;
        $profile = DB::table('erp_student_profiles')->where('student_id', $existing->id)->first();
        if ((int) $existing->unit_id !== $unitId || ($profile && (int) $profile->unit_id !== $unitId)
            || $this->identityName($existing->name) !== $this->identityName($student['name'])) {
            throw new InvalidArgumentException('Student code '.$student['code'].' conflicts with an existing identity or team. Reconcile it before import; overwrite cannot transfer ownership.');
        }
        return $existing;
    }

    private function teacherTarget(array $teacher, int $unitId): ?object
    {
        $existing = DB::table('erp_teachers')->where('code', $teacher['code'])->lockForUpdate()->first();
        if (!$existing) return null;
        $membership = DB::table('erp_teacher_units')->where('teacher_id', $existing->id)->first();
        $profile = DB::table('erp_teacher_profiles')->where('teacher_id', $existing->id)->first();
        if (($membership && (int) $membership->unit_id !== $unitId) || ($profile && (int) $profile->unit_id !== $unitId)
            || $this->identityName($existing->name) !== $this->identityName($teacher['name'])) {
            throw new InvalidArgumentException('Teacher code '.$teacher['code'].' conflicts with an existing identity or team. Reconcile it before import; overwrite cannot transfer ownership.');
        }
        return $existing;
    }

    private function identityName(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }

    private function importTemplateChunk(array $templates, int $unitId, int $actorId, bool $overwrite): array
    {
        $counts = ['task_templates' => 0];
        foreach ($templates as $template) {
            $values = ['description' => 'Imported from operational Sheets task history.', 'default_points' => $template['default_points'], 'checklist_definition' => json_encode($template['checklist'], JSON_THROW_ON_ERROR), 'active' => true, 'updated_at' => now()];
            $existing = DB::table('erp_task_templates')->where('unit_id', $unitId)->where('department', $template['department'])->where('name', $template['name'])->first();
            if ($existing) {
                if ($overwrite) DB::table('erp_task_templates')->where('id', $existing->id)->update($values);
            } else {
                DB::table('erp_task_templates')->insert($values + ['unit_id' => $unitId, 'department' => $template['department'], 'name' => $template['name'], 'created_by' => $actorId, 'created_at' => now()]);
                ++$counts['task_templates'];
            }
        }
        return $counts;
    }

    private function importTaskChunk(array $tasks, int $unitId, int $actorId, array $studentIds, bool $overwrite): array
    {
        $counts = ['tasks' => 0, 'task_items' => 0];
        foreach ($tasks as $task) {
            $existing = DB::table('erp_tasks')->where('unit_id', $unitId)->where('source', 'sheets')->where('source_external_id', $task['source_external_id'])->first();
            if ($existing) {
                if ($overwrite) DB::table('erp_tasks')->where('id', $existing->id)->update($this->taskValues($task, $unitId, $studentIds) + ['updated_at' => now()]);
                continue;
            }
            $taskId = DB::table('erp_tasks')->insertGetId($this->taskValues($task, $unitId, $studentIds) + ['created_by' => $actorId, 'source' => 'sheets', 'source_external_id' => $task['source_external_id'], 'created_at' => now(), 'updated_at' => now()]);
            ++$counts['tasks'];
            foreach ($task['checklist'] as $index => $item) {
                DB::table('erp_task_items')->insert(['unit_id' => $unitId, 'task_id' => $taskId, 'title' => $item['title'], 'item_type' => 'checklist', 'points' => 0, 'required' => false, 'completed_at' => $item['complete'] ? ($task['completed_at'] ? $task['completed_at'].' 00:00:00' : now()) : null, 'completed_by' => null, 'source_external_id' => $task['source_external_id'].'-'.$index, 'created_at' => now(), 'updated_at' => now()]);
                ++$counts['task_items'];
            }
        }
        return $counts;
    }

    private function mergeCounts(array &$total, array $part): void
    {
        foreach ($part as $key => $value) $total[$key] = ($total[$key] ?? 0) + (int) $value;
    }

    private function parse(string $directory, ?string $band = null): array
    {
        if (!is_dir($directory)) throw new InvalidArgumentException('A readable local CSV directory is required.');
        $sources = [];
        foreach (['student-registration.csv', 'exam-results.csv', 'task-manager.csv', 'master-2025.csv', 'tt-2025.csv', 'duplicate.csv', 'invoice-generator.csv'] as $file) {
            $path = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file;
            if (is_file($path) && is_readable($path)) $sources[$file] = ['bytes' => filesize($path), 'sha256' => hash_file('sha256', $path)];
        }
        $issues = [];
        if (!isset($sources['student-registration.csv'])) $issues[] = ['field' => 'student-registration.csv', 'code' => 'required_student_source'];
        $students = $this->students($directory, $issues, $band);
        $teachers = $this->teachers($directory, $issues, $band);
        $taskScan = $this->scanTasks($directory, $issues, $band);
        return [
            'sources' => $sources,
            'issues' => $issues,
            'students' => $students,
            'teachers' => $teachers,
            'task_count' => $taskScan['count'],
            'templates' => $taskScan['templates'],
        ];
    }

    private function students(string $directory, array &$issues, ?string $band = null): array
    {
        $path = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'student-registration.csv';
        if (!is_file($path)) return [];
        $seen = [];
        $result = [];
        foreach ($this->rows($path) as $row) {
            $code = $this->value($row, self::STUDENT_FIELDS['code']);
            if (!preg_match('/^CCA\d+$/i', $code)) continue;
            $rowBand = strtoupper($this->value($row, self::STUDENT_FIELDS['band']));
            if ($band !== null && $rowBand !== $band) continue;
            $code = strtoupper($code);
            if (isset($seen[$code])) { $issues[] = ['field' => 'student-registration.csv', 'code' => 'duplicate_student_code', 'value' => $code]; continue; }
            $seen[$code] = true;
            $name = $this->value($row, self::STUDENT_FIELDS['name']);
            if ($name === '') { $issues[] = ['field' => 'student-registration.csv', 'code' => 'student_name_missing', 'value' => $code]; continue; }
            $result[] = [
                'code' => $code,
                'band' => $rowBand,
                'name' => $name,
                'status' => $this->studentStatus($this->value($row, self::STUDENT_FIELDS['status'])),
                'grade' => $this->value($row, self::STUDENT_FIELDS['grade']),
                'syllabus' => $this->value($row, self::STUDENT_FIELDS['syllabus']),
                'section' => $this->value($row, self::STUDENT_FIELDS['section']),
                'hour_rate_paise' => $this->paise($this->value($row, self::STUDENT_FIELDS['hour_rate'])),
                'whatsapp' => $this->phone($this->value($row, self::STUDENT_FIELDS['whatsapp'])),
                'country' => $this->value($row, self::STUDENT_FIELDS['country']),
                'source' => $this->value($row, self::STUDENT_FIELDS['source']),
                'gmeet' => $this->value($row, self::STUDENT_FIELDS['gmeet']),
                'father' => $this->value($row, self::STUDENT_FIELDS['father']),
                'mother' => $this->value($row, self::STUDENT_FIELDS['mother']),
                'address' => $this->value($row, self::STUDENT_FIELDS['address']),
                'city' => $this->value($row, self::STUDENT_FIELDS['city']),
                'pin' => $this->value($row, self::STUDENT_FIELDS['pin']),
                'student_contact' => $this->phone($this->value($row, self::STUDENT_FIELDS['student_contact'])),
                'contact' => $this->phone($this->value($row, self::STUDENT_FIELDS['contact'])),
                'email' => $this->value($row, self::STUDENT_FIELDS['email']),
                'school' => $this->value($row, self::STUDENT_FIELDS['school']),
                'medium' => $this->value($row, self::STUDENT_FIELDS['medium']),
                'preferred_time' => $this->value($row, self::STUDENT_FIELDS['preferred_time']),
                'network' => $this->value($row, self::STUDENT_FIELDS['network']),
                'registered_on' => $this->date($this->value($row, self::STUDENT_FIELDS['submitted_at'])),
                'demo_status' => $this->value($row, self::STUDENT_FIELDS['demo_status']),
                'guardian_contact' => $this->phone($this->value($row, self::STUDENT_FIELDS['contact'])) ?: $this->phone($this->value($row, self::STUDENT_FIELDS['whatsapp'])),
            ];
        }
        return $result;
    }

    private function teachers(string $directory, array &$issues, ?string $band = null): array
    {
        $masterPath = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'master-2025.csv';
        $path = is_file($masterPath) ? $masterPath : rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'exam-results.csv';
        if (!is_file($path)) return [];
        $master = basename($path) === 'master-2025.csv';
        $result = [];
        foreach ($this->rows($path) as $row) {
            $code = strtoupper($this->value($row, self::TEACHER_FIELDS['code']));
            if (!preg_match('/^CT\d+$/i', $code)) continue;
            $rowBand = $master ? strtoupper($this->value($row, 8)) : '';
            if ($band !== null && $rowBand !== $band) continue;
            $name = $this->value($row, self::TEACHER_FIELDS['name']);
            if ($name === '') continue;
            $result[$code] = [
                'code' => $code,
                'name' => $name,
                'status' => $this->teacherStatus($this->value($row, self::TEACHER_FIELDS['status'])),
                'category' => $master ? null : $this->value($row, self::TEACHER_FIELDS['category']),
                'band' => $rowBand,
                'phone' => $this->phone($this->value($row, self::TEACHER_FIELDS['phone'])),
            ];
        }
        return array_values($result);
    }

    private function tasks(string $directory, array &$issues, ?string $band = null): iterable
    {
        if ($band !== null && $band !== 'AA') return;
        $path = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'task-manager.csv';
        if (!is_file($path)) return;
        foreach ($this->rows($path) as $row) {
            $date = $this->date($this->value($row, 0));
            $type = $this->value($row, 3);
            $description = $this->value($row, 6) ?: $this->value($row, 1);
            if (!$date || ($type === '' && $description === '')) continue;
            $completedAt = $this->date($this->value($row, 10));
            $checklist = [];
            for ($i = 12; $i <= 26; $i += 2) {
                $title = $this->value($row, $i);
                if ($title !== '') $checklist[] = ['title' => $title, 'complete' => in_array(strtoupper($this->value($row, $i + 1)), ['TRUE', 'YES', '1'], true)];
            }
            $external = hash('sha256', implode('|', [$date, $this->value($row, 1), $type, $this->value($row, 5), $description, $completedAt]));
            yield [
                'date' => $date,
                'type' => $type ?: 'Imported task',
                'description' => $description ?: 'Imported task',
                'assigned_label' => $this->value($row, 5),
                'follow_up_on' => $this->date($this->value($row, 7)),
                'hours_or_count' => $this->value($row, 8),
                'points' => (float) ($this->value($row, 9) ?: 0),
                'completed_at' => $completedAt,
                'status' => str_contains(strtolower($this->value($row, 11)), 'completed') ? 'completed' : 'open',
                'checklist' => $checklist,
                'source_external_id' => $external,
            ];
        }
        return;
    }

    private function scanTasks(string $directory, array &$issues, ?string $band = null): array
    {
        $templates = [];
        $count = 0;
        foreach ($this->tasks($directory, $issues, $band) as $task) {
            ++$count;
            $key = 'AA|'.$task['type'];
            if (!isset($templates[$key])) $templates[$key] = ['department' => 'AA', 'name' => $task['type'], 'default_points' => $task['points'], 'checklist' => []];
            $templates[$key]['default_points'] = $templates[$key]['default_points'] ?: $task['points'];
            foreach ($task['checklist'] as $item) $templates[$key]['checklist'][$item['title']] = true;
        }
        foreach ($templates as &$template) $template['checklist'] = array_map(fn ($title) => ['title' => $title], array_keys($template['checklist']));
        return ['count' => $count, 'templates' => array_values($templates)];
    }

    private function taskValues(array $task, int $unitId, array $studentIds): array
    {
        preg_match('/CCA\d+/i', $task['description'], $match);
        $studentId = isset($match[0]) ? ($studentIds[strtoupper($match[0])] ?? null) : null;
        return [
            'unit_id' => $unitId,
            'student_id' => $studentId,
            'assigned_to' => null,
            'title' => $task['type'],
            'notes' => trim($task['description'].' · Source owner: '.$task['assigned_label']),
            'due_on' => $task['follow_up_on'] ?: $task['date'],
            'status' => $task['status'],
            'department' => 'AA',
            'task_type' => $task['type'],
            'points' => $task['points'],
            'completed_at' => $task['completed_at'] ? $task['completed_at'].' 00:00:00' : null,
        ];
    }

    private function studentProfileValues(array $student, int $unitId, int $studentId): array
    {
        return [
            'unit_id' => $unitId,
            'student_id' => $studentId,
            'whatsapp_number' => $student['whatsapp'],
            'student_contact' => $student['student_contact'],
            'country' => $student['country'],
            'source' => $student['source'],
            'section' => $student['section'],
            'hour_rate_paise' => $student['hour_rate_paise'],
            'email' => $student['email'],
            'father_name' => $student['father'],
            'mother_name' => $student['mother'],
            'address' => $student['address'],
            'city' => $student['city'],
            'pin_code' => $student['pin'],
            'school' => $student['school'],
            'preferred_medium' => $student['medium'],
            'preferred_time' => $student['preferred_time'],
            'network_connectivity' => $student['network'],
            'gmeet_link' => $student['gmeet'],
            'demo_status' => $student['demo_status'],
            'source_external_id' => $student['code'],
        ];
    }

    private function rows(string $path): iterable
    {
        $file = new \SplFileObject($path, 'r');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::DROP_NEW_LINE | \SplFileObject::SKIP_EMPTY);
        $first = true;
        foreach ($file as $row) {
            if (!is_array($row) || $row === [null]) continue;
            if ($first) { $first = false; continue; }
            yield $row;
        }
    }

    private function value(array $row, int $index): string
    {
        return trim((string) ($row[$index] ?? ''));
    }

    private function phone(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        return $digits === '' ? null : substr($digits, -40);
    }

    private function paise(string $value): int
    {
        $value = str_replace([',', '₹', 'INR', ' '], '', $value);
        return is_numeric($value) ? (int) round(((float) $value) * 100) : 0;
    }

    private function date(string $value): ?string
    {
        if ($value === '') return null;
        foreach (['Y-m-d', 'd/m/Y', 'd/m/Y H:i:s', 'm/d/Y', 'Y-m-d H:i:s'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, $value);
                if ($date && $date->format('Y-m-d') !== '1970-01-01') return $date->format('Y-m-d');
            } catch (\Throwable) {
                // Try the next known Sheets format.
            }
        }
        return null;
    }

    private function studentStatus(string $value): string
    {
        $value = strtolower($value);
        return match (true) {
            str_contains($value, 'ongoing'), str_contains($value, 'completed') => 'active',
            str_contains($value, 'paused') => 'paused',
            str_contains($value, 'loading'), str_contains($value, 'not starting'), str_contains($value, 'demo') => 'pending',
            default => 'stopped',
        };
    }

    private function teacherStatus(string $value): string
    {
        return str_contains(strtolower($value), 'available') ? 'active' : 'inactive';
    }
}

/** Small, dependency-free unit access check for the importer command. */
final class UserUnitAccess
{
    public static function canManage(int $unitId, int $actorId): bool
    {
        $user = \App\Models\User::find($actorId);
        if (!$user || !(new \App\Modules\IdentityAccess\Services\SiteAccessPolicy())->isAdministrator($user)) {
            return in_array((string) DB::table('erp_memberships')->where('unit_id', $unitId)->where('user_id', $actorId)->value('role'), ['admin', 'staff', 'team_manager'], true);
        }
        return true;
    }
}
