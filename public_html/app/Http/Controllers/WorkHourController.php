<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Master;
use App\Models\Subject;
use App\Models\WorkHour;
use DateTime;
use Illuminate\Http\Request;
use Google\Client;
use Illuminate\Support\Facades\DB;
use Google\Service\Sheets;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class WorkHourController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            return $this->getWorkHoursData($request);
        }
    
        $teachers = WorkHour::select('teacher_name')->distinct()->get();
        $students = WorkHour::select('student_name')->distinct()->get();
    
        return view('admin.workhour.index')->with(compact('teachers', 'students'));
    }
    
    private function getWorkHoursData(Request $request)
    {
        $query = WorkHour::with(['master' => function ($query) {
            $query->select('student_code', 'team');
        }])->select(
            'id',
            'teacher_name',
            'teacher_code',
            'student_name',
            'student_code',
            'date_submitted',
            'subject',
            'date',
            'chapter',
            'topic',
            'hour_taken',
            'homework_status',
            'created_at',
            'hour_payment',
            'profit_per_hour',
            'payment_per_hour',
            'total_amount'
        )->orderBy('id', 'DESC');
    
        if ($request->has('student_name') && $request->input('student_name') !== "all") {
            $query->where('student_name', $request->input('student_name'));
        }
    
        if ($request->has('teacher_name') && $request->input('teacher_name') !== "all") {
            $query->where('teacher_name', $request->input('teacher_name'));
        }
    
        if (!empty($request->get('start_date'))) {
            $query->whereDate('date', '>=', $request->get('start_date'));
        }
        if (!empty($request->get('end_date'))) {
            $query->whereDate('date', '<=', $request->get('end_date'));
        }
    
        if (!empty($request->get('status'))) {
            $status = $request->get('status');
            if ($status === 'true') {
                $query->whereRaw('YEAR(date_submitted) = YEAR(date) AND MONTH(date_submitted) = MONTH(date)');
            } elseif ($status === 'false') {
                $query->whereRaw('(YEAR(date_submitted) != YEAR(date) OR MONTH(date_submitted) != MONTH(date))');
            }
        }
    
        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('team', function ($row) {
                return optional($row->master)->team; // Access the team through the master relationship
            })
            ->addColumn('action', function ($row) {
                $button = '<button type="button" data-href="' . route('workhour.edit', $row->id) . '" class="btn btn-xs btn-primary edit_button" data-container=".modal_class"><i class="fa fa-edit"></i></button>&nbsp';
                $button .= '<button data-href="' . route('delete', ['workhour', $row->id]) . '" class="btn btn-xs btn-primary btn-modal"><i class="fa fa-trash"></i></button>';
                return $button;
            })
            ->filter(function ($query) use ($request) {
                if (!empty($request->get('search'))) {
                    $search = \App\Support\TableSearch::term($request);
                    $query->where(function ($searchQuery) use ($search) {
                        $searchQuery->where('teacher_name', 'LIKE', "%$search%")
                            ->orWhere('teacher_code', 'LIKE', "%$search%")
                            ->orWhere('student_name', 'LIKE', "%$search%")
                            ->orWhere('student_code', 'LIKE', "%$search%")
                            ->orWhere('subject', 'LIKE', "%$search%")
                            ->orWhere('date', 'LIKE', "%$search%");
                    });
                }
            })
            ->addColumn('created_at', function ($row) {
                return $row->created_at->format('d-m-y H:i:s');
            })
            ->addColumn('status', function ($row) {
                $timestamp = $row->date_submitted;
                $addedDate = $row->date;
    
                $timestampDate = new DateTime($timestamp);
                $addedDateDate = new DateTime($addedDate);
    
                $timestampYear = $timestampDate->format('Y');
                $timestampMonth = $timestampDate->format('m');
                $addedDateYear = $addedDateDate->format('Y');
                $addedDateMonth = $addedDateDate->format('m');
    
                if ($timestampYear === $addedDateYear) {
                    return ($timestampMonth === $addedDateMonth) ? 'true' : 'false';
                } else {
                    return 'false';
                }
            })
            ->rawColumns(['status', 'action', 'created_at', 'team'])
            ->make(true);
    }
    



    public function create()
    {
        $teachers = Master::select('teacher_name')
            ->distinct()
            ->orderBy('teacher_name', 'asc')
            ->get();
        $subject = Subject::select('id', 'name')->get();
        return view('admin.workhour.create')->with(compact('teachers', 'subject'));
    }
    public function getStudentinfo(Request $request)
    {
        $policy = app(\App\Modules\IdentityAccess\Services\SiteAccessPolicy::class);
        if ($policy->isAdministrator($request->user())) {
            $request->validate(['teacher_name' => ['required', 'string', 'max:255']]);
            $matches = Master::where('teacher_name', $request->teacher_name)->get();
            abort_unless($matches->pluck('teacher_code')->unique()->count() === 1, 422, 'Choose an unambiguous teacher identity.');
            $teacher = $matches->first();
        } else {
            $teacher = app(\App\Modules\People\Services\TeacherIdentityVerifier::class)->verifyTeacher($request->all());
        }
        $students = Master::where('teacher_name', $teacher->teacher_name)
            ->where('teacher_code', $teacher->teacher_code)
            ->select('student_name', 'student_code')->distinct()->get();
        return response()->json(['students' => $students, 'teacher_code' => $teacher->teacher_code])->header('Cache-Control', 'no-store, private');
    }
    public function getStudentedit(Request $request)
    {
        return $this->getStudentinfo($request);
    }
    public function store(Request $request)
    {
        $record = app(\App\Modules\Attendance\Services\RecordWorkHour::class)->record($request);
        return response()->json(['success' => true, 'msg' => 'Successfully created', 'id' => $record->id]);
    }
    public function frontetHourUploadCreate()
    {
        $teachers = Master::select('teacher_name')
            ->distinct()
            ->orderBy('teacher_name', 'asc')
            ->get();
        $subject = Subject::select('id', 'name')->get();
        return view('teacherhourupload')->with(compact('teachers', 'subject'));
    }
    public function edit($id)
    {
        if (request()->ajax()) {
            $teachers = Master::select('teacher_name')
                ->distinct();
            $subject = Subject::select('id', 'name')->get();
            $workhours = WorkHour::find($id);
            return view('admin.workhour.edit')->with(compact('workhours', 'teachers', 'subject'));
        }
    }
    public function update(Request $request, $id)
    {
        $record = app(\App\Modules\Attendance\Services\UpdateWorkHour::class)->update($id, $request);
        return response()->json(['success' => true, 'msg' => 'Successfully updated', 'id' => $record->id]);
    }

    public function syncWorkHoureReportData()
    {
        // A snapshot cannot safely overwrite locally accepted submissions. This
        // guard does not choose the long-term DB-vs-Sheets source of truth.
        abort_if(WorkHour::whereNotNull('submission_key')->exists(), 409,
            'Attendance contains local submissions. Reconcile these records before replacing a spreadsheet snapshot.');
        $values = app(\App\Modules\Integrations\Services\SheetSnapshotReader::class)->read('TEST');
        app(\App\Modules\Attendance\Services\ImportAttendanceSnapshot::class)->replace($values);
        return response()->json(['message' => 'Data synced successfully', 'last_synced' => now()->format('Y-m-d H:i:s')]);
    }
    public function attendanceReport(Request $request)
    {
        app(\App\Modules\IdentityAccess\Services\SiteAccessPolicy::class)->assertSiteMember($request->user());
        if ($request->ajax() || $request->isMethod('post')) {
            abort_unless($request->isMethod('post'), 405, 'Use POST for report verification.');
            $data = WorkHour::select('id', 'teacher_name', 'teacher_code', 'student_name', 'student_code', 'subject', 'topic', 'date', 'hour_taken', 'date_submitted');
            app(\App\Modules\IdentityAccess\Services\ReportAccess::class)->scopeTeacherReport($data, $request);
            $request->validate(['start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date']]);
            if ($request->filled('start_date')) $data->whereDate('date', '>=', $request->start_date);
            if ($request->filled('end_date')) $data->whereDate('date', '<=', $request->end_date);
            return DataTables::of($data->orderBy('date', 'desc'))->addIndexColumn()->make(true)->header('Cache-Control', 'no-store, private');
        }
        $teachers = Master::select('teacher_name')->distinct()->orderBy('teacher_name')->get();
        $reportRoute = $request->route()->getName();
        return view('hourreport', compact('teachers', 'reportRoute'));
    }

    public function getStudentData(Request $request)
    {
        $query = WorkHour::query();
        app(\App\Modules\IdentityAccess\Services\ReportAccess::class)->scopeTeacherReport($query, $request);
        return response()->json(['students' => $query->select('student_name')->distinct()->get()])->header('Cache-Control', 'no-store, private');
    }
    public function getStudent(Request $request)
    {
        return $this->getStudentData($request);
    }
    public function getTeachers(Request $request)
    {
        $query = WorkHour::query();
        app(\App\Modules\IdentityAccess\Services\ReportAccess::class)->scopeTeacherReport($query, $request);
        return response()->json(['teachers' => $query->select('teacher_name')->distinct()->get()])->header('Cache-Control', 'no-store, private');
    }



    public function destroy($id)
    {
        if (request()->ajax()) {
            try {
                $workhours = WorkHour::findOrFail($id);
                $workhours->delete();
                return response()->json(['success' => true, 'msg' => 'successfully deleted']);
            } catch (\Exception $e) {
                report($e);
                return response()->json(['success' => false, 'msg' => 'Unable to delete the work-hour record.'], 500);
            }
        }
    }
    public function studentsAttendanceReport(Request $request)
    {
        // Student codes are identifiers, not authentication credentials. Until an
        // account-to-student mapping exists, use the same verified teacher scope.
        return $this->attendanceReport($request);
    }
    public function getTeachersData(Request $request)
    {
        return $this->getTeachers($request);
    }
    public function exportWorkHours(Request $request)
    {
        $query = WorkHour::query();

        if (!empty($request->get('start_date'))) {
            $query->whereDate('date', '>=', $request->get('start_date'));
        }

        if (!empty($request->get('end_date'))) {
            $query->whereDate('date', '<=', $request->get('end_date'));
        }

        $workHours = $query->get();

        $fileName = 'work_hours.csv';
        $headers = [
            "Content-Type" => "text/csv",
            "Content-Disposition" => "attachment; filename=\"$fileName\"",
        ];

        $callback = function () use ($workHours) {
            $file = fopen('php://output', 'w');

            $safeCell = static function ($value) {
                $value = (string) $value;
                return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
            };

            fputcsv($file, ['ID', 'Teacher Name', 'Teacher Code', 'Student Name', 'Student Code', 'Date', 'Work Hour']);

            foreach ($workHours as $workHour) {
                fputcsv($file, [
                    $safeCell($workHour->id),
                    $safeCell($workHour->teacher_name),
                    $safeCell($workHour->teacher_code),
                    $safeCell($workHour->student_name),
                    $safeCell($workHour->student_code),
                    $safeCell($workHour->date),
                    $safeCell($workHour->hour_taken),

                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
