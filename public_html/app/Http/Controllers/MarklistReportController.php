<?php

namespace App\Http\Controllers;

use App\Models\MarklistReport;
use App\Modules\Academics\Services\AssessmentHistory;
use App\Models\Master;
use App\Support\TableSearch;
use App\Modules\IdentityAccess\Services\ReportAccess;
use App\Modules\IdentityAccess\Services\SiteAccessPolicy;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Sheets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class MarklistReportController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = app(AssessmentHistory::class)->query()->select('id', 'student_name', 'student_code', 'teacher_name', 'teacher_code', 'subject', 'chapter', 'mark_scored', 'total_mark', 'mode_of_exam', 'examination_date', 'remark', 'date_submitted')->orderBy('date_submitted', 'desc');
            return DataTables::of($data)->addIndexColumn()
                ->filter(function ($query) use ($request) {
                    $search = TableSearch::term($request);
                    if (is_string($search) && $search !== '') {
                        $query->where(function ($query) use ($search) {
                            $query->where('student_name', 'like', '%'.$search.'%')
                                ->orWhere('teacher_name', 'like', '%'.$search.'%')
                                ->orWhere('subject', 'like', '%'.$search.'%');
                        });
                    }
                })->make(true);
        }
        return view('admin.mark_report_teachers.index');
    }

    public function syncMarklistReportData()
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
                config('integrations.sheets.spreadsheet_id'), 'MARKLIST'
            )->getValues();
            $this->replaceSnapshot($values ?: []);
            return response()->json(['message' => 'Data synced successfully', 'last_synced' => now()->format('Y-m-d H:i:s')]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Spreadsheet snapshot synchronization failed.', ['exception_type' => get_class($exception)]);
            return response()->json(['error' => 'Unable to synchronize mark-list data. Existing data has been preserved.'], 500);
        }
    }

    public function MarklistReport(Request $request, ReportAccess $access, SiteAccessPolicy $siteAccess)
    {
        $siteAccess->assertSiteMember($request->user());
        if ($request->ajax() || $request->isMethod('post')) {
            abort_unless($request->isMethod('post'), 405);
            // Credentials are checked before even a count of report rows is returned.
            $data = $access->scopeTeacherReport(app(AssessmentHistory::class)->query(), $request)
                ->select('id', 'teacher_name', 'student_name', 'subject', 'chapter', 'examination_date', 'mark_scored', 'total_mark', 'date_submitted')
                ->orderBy('date_submitted', 'desc');
            $request->validate([
                'fromdate' => ['nullable', 'date_format:Y-m-d'],
                'filter_date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fromdate'],
            ]);
            if ($request->filled('fromdate')) {
                $data->where('examination_date', '>=', $request->input('fromdate'));
            }
            if ($request->filled('filter_date_to')) {
                $data->where('examination_date', '<=', $request->input('filter_date_to'));
            }
            return DataTables::of($data)->addIndexColumn()->escapeColumns([])->make(true)
                ->header('Cache-Control', 'no-store, private');
        }
        $teachers = Master::select('teacher_name')->distinct()->orderBy('teacher_name')->get();
        $isAdministrator = $siteAccess->isAdministrator($request->user());
        return response()->view('markreport', compact('teachers', 'isAdministrator'))->header('Cache-Control', 'no-store, private');
    }

    public function getStudentData(Request $request, ReportAccess $access)
    {
        abort_unless($request->isMethod('post'), 405);
        $students = $access->scopeTeacherReport(app(AssessmentHistory::class)->query(), $request)
            ->select('student_name')->distinct()->orderBy('student_name')->get();
        return response()->json(['students' => $students])->header('Cache-Control', 'no-store, private');
    }

    private function replaceSnapshot(array $values): void
    {
        $records = $this->marklistRecords($values);
        DB::transaction(function () use ($records) {
            MarklistReport::query()->delete();
            foreach (array_chunk($records, 100) as $chunk) {
                MarklistReport::insert($chunk);
            }
        });
    }

    private function marklistRecords(array $values): array
    {
        // Canonical result submissions contain 12 fields. A legacy thirteenth
        // credential field is deliberately ignored and never copied or exported.
        $columns = ['teacher_name', 'teacher_code', 'student_name', 'student_code', 'subject', 'chapter', 'mark_scored', 'total_mark', 'mode_of_exam', 'examination_date', 'remark', 'date_submitted'];
        $records = [];
        foreach (array_slice($values, 1) as $row) {
            if (!is_array($row) || count($row) < 12 || count($row) > 13) {
                throw ValidationException::withMessages(['spreadsheet' => 'The mark-list contains an invalid row. No data was changed.']);
            }
            $record = [];
            foreach ($columns as $index => $column) {
                $value = $row[$index] ?? null;
                if ($value !== null && (!is_scalar($value) || mb_strlen((string) $value) > 255)) {
                    throw ValidationException::withMessages(['spreadsheet' => 'The mark-list contains an invalid cell. No data was changed.']);
                }
                $record[$column] = $value === null ? null : trim((string) $value);
            }
            foreach (['teacher_name', 'teacher_code', 'student_name', 'student_code', 'subject'] as $field) {
                if (empty($record[$field])) {
                    throw ValidationException::withMessages(['spreadsheet' => 'Each mark-list row must identify its teacher, student and subject. No data was changed.']);
                }
            }
            if (!is_numeric($record['mark_scored']) || !is_numeric($record['total_mark'])
                || (float) $record['mark_scored'] < 0 || (float) $record['total_mark'] <= 0
                || (float) $record['mark_scored'] > (float) $record['total_mark']) {
                throw ValidationException::withMessages(['spreadsheet' => 'The mark-list contains invalid marks. No data was changed.']);
            }
            $record['examination_date'] = $this->parseDate($record['examination_date'], ['Y-m-d', 'd/m/Y'], 'Y-m-d');
            $record['date_submitted'] = $this->parseDate($record['date_submitted'], ['Y-m-d H:i:s', 'd/m/Y H:i:s', 'Y-m-d', 'd/m/Y'], 'Y-m-d H:i:s');
            $record['created_at'] = now();
            $record['updated_at'] = now();
            $records[] = $record;
        }
        if (!$records) {
            throw ValidationException::withMessages(['spreadsheet' => 'The mark-list contains no importable rows. No data was changed.']);
        }
        return $records;
    }

    private function parseDate(?string $value, array $formats, string $output): string
    {
        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, (string) $value);
                if ($date && $date->format($format) === $value) {
                    return $date->format($output);
                }
            } catch (\Throwable $exception) {
                // Try the next explicitly supported spreadsheet format.
            }
        }
        throw ValidationException::withMessages(['spreadsheet' => 'The mark-list contains an invalid date. No data was changed.']);
    }
}
