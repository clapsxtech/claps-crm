<?php

namespace App\Http\Controllers;

use App\Models\Master;
use App\Models\TimeTable;
use App\Modules\People\Services\TeacherIdentityVerifier;
use Google\Client;
use Google\Service\Sheets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TimeTableController extends Controller
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function index(Request $request)
    {
        $timeTables = TimeTable::all();
        $teachers = Master::select('teacher_name')->distinct()->get();
        return view('admin.timetable.index', compact('timeTables', 'teachers'));
    }

    public function syncTimeTableData()
    {
        if (!config('integrations.sheets.enabled', false)
            || !config('integrations.sheets.spreadsheet_id')
            || !is_readable((string) config('integrations.sheets.credentials_path'))) {
            return response()->json(['error' => 'Spreadsheet synchronization is not configured. Existing data has been preserved.'], 503);
        }
        try {
            $client = new Client();
            $client->setApplicationName('example-app');
            $client->setScopes([Sheets::SPREADSHEETS_READONLY]);
            $client->setAuthConfig(config('integrations.sheets.credentials_path'));
            $service = new Sheets($client);
            $values = $service->spreadsheets_values->get(
                config('integrations.sheets.spreadsheet_id'), 'TT'
            )->getValues();
            $this->replaceSnapshot($values ?: []);

            return response()->json(['message' => 'Data synced successfully', 'last_synced' => now()->format('Y-m-d H:i:s')]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Spreadsheet snapshot synchronization failed.', ['exception_type' => get_class($exception)]);
            return response()->json(['error' => 'Unable to synchronize timetable data. Existing data has been preserved.'], 500);
        }
    }

    public function timetableReport()
    {
        // Never embed schedules, meeting links or a directory in an unverified page.
        return response()->view('timetable')->header('Cache-Control', 'no-store, private');
    }

    public function getTeacherDOB(Request $request, TeacherIdentityVerifier $identities)
    {
        abort_unless($request->isMethod('post'), 405);
        // The timetable schema has names, not codes, so ambiguous names must fail closed.
        $teacher = $identities->verifyTeacher($request->only('teacher_name', 'dob', 'password'));
        $students = Master::where('teacher_name', $teacher->teacher_name)
            ->where('teacher_code', $teacher->teacher_code)->pluck('student_name');
        $timeTables = TimeTable::whereIn('student_name', $students)
            ->where(function ($query) use ($teacher) {
                foreach (self::DAYS as $day) {
                    $query->orWhere($day.'_teacher', $teacher->teacher_name);
                }
            })->get();

        $days = array_fill_keys(self::DAYS, []);
        foreach ($timeTables as $timeTable) {
            foreach (self::DAYS as $day) {
                if ($timeTable->{$day.'_teacher'} !== $teacher->teacher_name || !$timeTable->{$day.'_time'}) {
                    continue;
                }
                $days[$day][] = [
                    'time' => $timeTable->{$day.'_time'},
                    'subject' => $timeTable->{$day.'_subject'},
                    'hour' => $timeTable->{$day.'_hour'},
                    'student_name' => $timeTable->student_name,
                    'link' => $this->safeMeetingLink($timeTable->link),
                ];
            }
        }

        return response()->json(['message' => 'Teacher found', 'days' => $days])
            ->header('Cache-Control', 'no-store, private');
    }

    public function studentTimetable(Request $request)
    {
        $timeTables = TimeTable::all();
        $students = Master::select('student_name')->distinct()->get();
        return view('admin.timetable.student-timetable', compact('timeTables', 'students'));
    }

    private function safeMeetingLink(?string $link): ?string
    {
        $link = trim((string) $link);
        if ($link === '') {
            return null;
        }
        if (!preg_match('~^https?://~i', $link)) {
            if (strpos($link, ':') !== false || strpos($link, '//') === 0) {
                return null;
            }
            $link = 'https://'.$link;
        }
        return filter_var($link, FILTER_VALIDATE_URL) ? $link : null;
    }

    private function replaceSnapshot(array $values): void
    {
        $records = $this->timetableRecords($values);
        DB::transaction(function () use ($records) {
            TimeTable::query()->delete();
            foreach (array_chunk($records, 100) as $chunk) {
                TimeTable::insert($chunk);
            }
        });
    }

    private function timetableRecords(array $values): array
    {
        $columns = ['student_name', 'section', 'class_syllabus'];
        foreach (self::DAYS as $day) {
            foreach (['time', 'subject', 'teacher', 'hour', 'number'] as $field) {
                $columns[] = $day.'_'.$field;
            }
        }
        $columns = array_merge($columns, ['link', 'student_number']);
        $records = [];
        foreach (array_slice($values, 1) as $row) {
            if (!is_array($row) || count($row) < 3 || count($row) > count($columns)) {
                throw ValidationException::withMessages(['spreadsheet' => 'The timetable contains an invalid row. No data was changed.']);
            }
            $record = [];
            foreach ($columns as $index => $column) {
                $value = $row[$index] ?? null;
                if ($value !== null && (!is_scalar($value) || mb_strlen((string) $value) > 255)) {
                    throw ValidationException::withMessages(['spreadsheet' => 'The timetable contains an invalid cell. No data was changed.']);
                }
                $record[$column] = $value === null ? null : trim((string) $value);
            }
            if (empty($record['student_name'])) {
                throw ValidationException::withMessages(['spreadsheet' => 'Each timetable row must identify a student. No data was changed.']);
            }
            $hasSchedule = false;
            foreach (self::DAYS as $day) {
                $hasTime = !empty($record[$day.'_time']);
                $hasTeacher = !empty($record[$day.'_teacher']);
                if ($hasTime !== $hasTeacher) {
                    throw ValidationException::withMessages(['spreadsheet' => 'Each lesson must identify its time and teacher. No data was changed.']);
                }
                $hasSchedule = $hasSchedule || $hasTime;
            }
            if (!$hasSchedule) {
                throw ValidationException::withMessages(['spreadsheet' => 'The timetable contains a row without lessons. No data was changed.']);
            }
            $record['created_at'] = now();
            $record['updated_at'] = now();
            $records[] = $record;
        }
        if (!$records) {
            throw ValidationException::withMessages(['spreadsheet' => 'The timetable contains no importable rows. No data was changed.']);
        }
        return $records;
    }
}
