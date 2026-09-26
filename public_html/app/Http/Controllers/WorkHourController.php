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
use Google_Service_Sheets_ValueRange;
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
                $query->whereRaw('MONTH(date_submitted) = MONTH(date)');
            } elseif ($status === 'false') {
                $query->whereRaw('MONTH(date_submitted) != MONTH(date)');
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
                    $search = $request->get('search');
                    $query->where('teacher_name', 'LIKE', "%$search%")
                          ->orWhere('teacher_code', 'LIKE', "%$search%")
                          ->orWhere('student_name', 'LIKE', "%$search%")
                          ->orWhere('student_code', 'LIKE', "%$search%")
                          ->orWhere('subject', 'LIKE', "%$search%")
                          ->orWhere('date', 'LIKE', "%$search%");
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
        $teacherName = $request->input('teacher_name');
        $teacher = Master::where('teacher_name', $teacherName)->first();

        if ($teacher) {
            $students = Master::where('teacher_name', $teacherName)->get();
            $studentData = [];

            foreach ($students as $student) {
                $studentData[] = [
                    'id' => $student->id,
                    'student_name' => $student->student_name,
                    'student_code' => $student->student_code,
                    'hour_payment' => $student->hour_payment,
                    'profit_per_hour' => $student->profit_per_hour,
                    'payment_per_hour' => $student->payment_per_hour,


                ];
            }

            return response()->json(['students' => $studentData, 'teacher_code' => $teacher->teacher_code]);
        } else {
            return response()->json(['message' => 'Teacher not found']);
        }
    }
    public function getStudentedit(Request $request)
    {
        $teacherName = $request->input('teacher_name');
        $teacher = Master::where('teacher_name', $teacherName)->first();

        if ($teacher) {
            $students = Master::where('teacher_name', $teacherName)->get();
            $studentData = [];

            foreach ($students as $student) {
                $studentData[] = [
                    'id' => $student->id,
                    'student_name' => $student->student_name,
                    'student_code' => $student->student_code,
                    'hour_payment' => $student->hour_payment,
                    'profit_per_hour' => $student->profit_per_hour,
                    'payment_per_hour' => $student->payment_per_hour,

                ];
            }

            return response()->json(['students' => $studentData, 'teacher_code' => $teacher->teacher_code]);
        } else {
            return response()->json(['message' => 'Teacher not found']);
        }
    }
    public function store(Request $request)
    {
        $rules = [
            'teacher_name' => ['required', 'regex:/^[a-zA-Z0-9\s,.-]+$/'],
            'teacher_code' => 'required',
            'student_name' => 'required',
            'student_code' => 'required',
            'subject' => 'required',
            'date' => 'required',
            'chapter' => 'required',
            'topic' => 'required',
            'hour_taken' => 'required',
            'homework_status' => 'required',
            'homework_status' => 'required|in:yes,no',
        ];

        $messages = [
            'teacher_code.required' => 'Teacher Code is required',
            'homework_status.required' => 'Mark Confirmation is required',
            'homework_status.in' => 'Mark Confirmation must be either "yes" or "no"',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'msg' => $validator->errors()->first()]);
        }

        try {
            $subjectId = $request->input('subject');
            $selectedSubject = Subject::findOrFail($subjectId);
            $workhour = new WorkHour();
            $workhour->teacher_name = $request->teacher_name;
            $workhour->teacher_code = $request->teacher_code;
            $workhour->student_name = $request->student_name;
            $workhour->student_code = $request->student_code;
            $workhour->subject      = $selectedSubject->name;
            $workhour->date         = $request->date;
            $workhour->chapter      = $request->chapter;
            $workhour->topic        = $request->topic;
            $workhour->hour_payment        = $request->hour_payment;
            $workhour->profit_per_hour        = $request->profit_per_hour;
            $workhour->payment_per_hour        = $request->payment_per_hour;
            $workhour->total_amount        = $request->total_amount;
            $workhour->date_submitted    = Carbon::now();
            $workhour->dob          = $request->password;
            $workhour->hour_taken   = $request->hour_taken;
            $workhour->homework_status = $request->homework_status;
            $workhour->created_at = Carbon::now();
            $workhour->updated_at = Carbon::now();
            $teacherDob = Master::where('teacher_code', $request->teacher_code)->value('teacher_dob');
            if ($teacherDob !== $request->password) {
                $response = ['success' => false, 'msg' => 'Passwords do not match.'];
                return response()->json($response);
            }

            $workhour->save();

            return response()->json(['success' => true, 'msg' => 'Successfully created']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'msg' => $e->getMessage()]);
        }
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
        if (request()->ajax()) {
            $rules = [
                'teacher_name' => 'required',
                'teacher_code' => 'required',
                'student_names' => 'required',
                'student_code' => 'required',
                'subject' => 'required',
                'date' => 'required',
                'chapter' => 'required',
                'topic' => 'required',
                'hour_taken' => 'required',
                'homework_status' => 'required',
                'homework_status' => 'required|in:yes,no',
            ];
            $messages = [
                'name.required'    => 'Name Required',
            ];
            $validator = Validator::make(request()->all(), $rules, $messages);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'msg' => $validator->errors()->first()]);
            }
            try {
                $workhour = WorkHour::findOrFail($id);
                $workhour->teacher_code = $request->teacher_code;
                $workhour->student_name = $request->student_names;
                $workhour->student_code = $request->student_code;
                $workhour->subject      = $request->subject;
                $workhour->date         = $request->date;
                $workhour->chapter      = $request->chapter;
                $workhour->topic        = $request->topic;
                $workhour->hour_taken   = $request->hour_taken;
                $workhour->total_amount = $request->total_amount;
                $workhour->date_submitted   = $request->date_submitted;
                $workhour->hour_payment = $request->hour_payment;
                $workhour->payment_per_hour = $request->payment_per_hour;
                $workhour->profit_per_hour = $request->profit_per_hour;

                $workhour->homework_status = $request->homework_status;
                $workhour->updated_at = Carbon::now();
                $workhour->save();
                return ['success' => true, 'msg' => 'successfully updated'];
            } catch (\Exception $e) {
                return ['success' => false, 'msg' => $e->getMessage()];
            }
        }
    }

    public function syncWorkHoureReportData()
    {
        $client = new Client();
        $client->setApplicationName('example-app');
        $client->setScopes([Sheets::SPREADSHEETS]);
        $client->setAuthConfig(storage_path('credentials.json'));

        $service = new Sheets($client);

        $spreadsheetId = '13ydSEHAwYqTFCd1DE_IUXOa457jVcPdSY7vPk-3faNs';
        $sheetName = 'TEST';

        $response = $service->spreadsheets_values->get($spreadsheetId, $sheetName);
        $values = $response->getValues();

        if ($values) {
            WorkHour::truncate();

            $dataRows = array_slice($values, 1);

            foreach ($dataRows as $row) {
                if (count($row) >= 15) {
                    $dateString = $row[5];
                    $date = Carbon::createFromFormat('d/m/Y', $dateString)->format('Y-m-d');
                    $insertMaster = [
                        'teacher_name' => $row[0],
                        'teacher_code' => $row[1],
                        'student_name' => $row[2],
                        'student_code' => $row[3],
                        'subject' => $row[4],
                        'date' => $date,
                        'date_submitted' => $row[6],
                        'chapter' => $row[7],
                        'topic' => $row[8],
                        'hour_taken' => $row[9],
                        'homework_status' => $row[10],
                        'dob' => $row[11],
                        'hour_payment' => $row[12],
                        'profit_per_hour' => $row[13],
                        'payment_per_hour' => $row[14],
                        'total_amount' => $row[15],

                    ];

                    WorkHour::create($insertMaster);
                }
            }
            $timestamp = Carbon::now();

            return response()->json([
                'message' => 'Data synced successfully',
                'last_synced' => $timestamp->format('Y-m-d H:i:s'),
            ]);
        } else {
            return response()->json(['message' => 'Data not found']);
        }
    }
    public function attendanceReport(Request $request)
    {
        if ($request->ajax()) {

            $data = WorkHour::select('id', 'teacher_name', 'student_name', 'dob', 'subject', 'topic', 'date', 'hour_taken', 'date_submitted')->orderBy('date', 'desc');
            if ($request->has('student_name')) {
                if ($request->input('student_name') !== "all") {
                    $data->where('student_name', $request->input('student_name'));
                }
            }
            if ($request->has('teacher_name')) {
                $data->where('teacher_name', $request->input('teacher_name'));
            }
            if ($request->has('dob')) {
                $data->where('dob', $request->input('dob'));
            }
            if (!empty($request->get('start_date'))) {
                $data->whereDate('date', '>=', $request->get('start_date'));
            }
            if (!empty($request->get('end_date'))) {
                $data->whereDate('date', '<=', $request->get('end_date'));
            }
            return DataTables::of($data)
                ->addColumn('date', function ($row) {
                    return date('d-m-Y', strtotime($row->date));
                })
                ->addIndexColumn()
                ->make(true);
        }

        $teachers = WorkHour::select('teacher_name')
            ->distinct()
            ->get();

        return view('hourreport', compact('teachers'));
    }

    public function getStudentData(Request $request)
    {


        $teacherName = $request->input('teacher_name');
        $password = $request->input('dob');
        $teacher = WorkHour::where('teacher_name', $teacherName)
            ->where('dob', $password)
            ->first();
        if ($teacher) {
            $students = WorkHour::where('teacher_name', $teacherName)
                ->where('dob', $password)
                ->select('student_name')
                ->distinct()
                ->get();
            $studentData = [];
            foreach ($students as $student) {
                $studentData[] = [
                    'student_name' => $student->student_name,
                ];
            }

            return response()->json(['students' => $studentData]);
        } else {
            return response()->json(['message' => 'Student not found']);
        }
    }
    public function getStudent(Request $request)
    {
        $teacherName = $request->input('teacher_name');

        if ($teacherName === 'all') {
            $students = WorkHour::select('student_name')
                ->distinct()
                ->get();
        } else {
            $students = WorkHour::where('teacher_name', $teacherName)
                ->select('student_name')
                ->distinct()
                ->get();
        }

        $studentData = [];

        foreach ($students as $student) {
            $studentData[] = [
                'student_name' => $student->student_name,
            ];
        }

        return response()->json(['students' => $studentData]);
    }
    public function getTeachers(Request $request)
    {
        $studentName = $request->input('student_name');


        if ($studentName === 'all') {
            $teachers = WorkHour::select('student_name')
                ->distinct()
                ->get();
        } else {
            $teachers = WorkHour::where('student_name', $studentName)
                ->select('teacher_name')
                ->distinct()
                ->get();
        }

        $teachersData = [];

        foreach ($teachers as $teacher) {
            $teachersData[] = [
                'teacher_name' => $teacher->teacher_name,
            ];
        }

        return response()->json(['teachers' => $teachers]);
    }



    public function destroy($id)
    {
        if (request()->ajax()) {
            try {
                $workhours = WorkHour::findOrFail($id);
                $workhours->delete();
                return response()->json(['success' => true, 'msg' => 'successfully deleted']);
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'msg' => $e->getMessage()]);
            }
        }
    }
    public function studentsAttendanceReport(Request $request)
    {
        if ($request->ajax()) {

            $data = WorkHour::select('id', 'teacher_name', 'student_name', 'student_code', 'subject', 'topic', 'date', 'hour_taken', 'date_submitted')->orderBy('date', 'desc');
            if ($request->has('teacher_name')) {
                if ($request->input('teacher_name') !== "all") {
                    $data->where('teacher_name', $request->input('teacher_name'));
                }
            }
            if ($request->has('student_name')) {
                $data->where('student_name', $request->input('student_name'));
            }
            if ($request->has('student_code')) {
                $data->where('student_code', $request->input('student_code'));
            }
            if (!empty($request->get('start_date'))) {
                $data->whereDate('date', '>=', $request->get('start_date'));
            }
            if (!empty($request->get('end_date'))) {
                $data->whereDate('date', '<=', $request->get('end_date'));
            }
            return DataTables::of($data)
                ->addColumn('date', function ($row) {
                    return date('d-m-Y', strtotime($row->date));
                })
                ->addIndexColumn()
                ->make(true);
        }

        $students = WorkHour::select('student_name')
            ->distinct()
            ->get();

        return view('hourreportstudents', compact('students'));
    }
    public function getTeachersData(Request $request)
    {
        $studentName = $request->input('student_name');
        $studentCode = $request->input('student_code');

        $matchingStudent = WorkHour::where('student_name', $studentName)
            ->where('student_code', $studentCode)
            ->first();

        if ($matchingStudent) {
            $teachers = WorkHour::where('student_name', $studentName)
                ->where('student_code', $studentCode)
                ->select('teacher_name')
                ->distinct()
                ->get();
            $teachersData = [];

            foreach ($teachers as $teacher) {
                $teachersData[] = [
                    'teacher_name' => $teacher->teacher_name,
                ];
            }

            return response()->json(['teachers' => $teachersData]);
        } else {
            return response()->json(['message' => 'Student not found']);
        }
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

            fputcsv($file, ['ID', 'Teacher Name', 'Teacher Code', 'Student Name', 'Student Code', 'Date', 'Work Hour']);

            foreach ($workHours as $workHour) {
                fputcsv($file, [
                    $workHour->id,
                    $workHour->teacher_name,
                    $workHour->teacher_code,
                    $workHour->student_name,
                    $workHour->student_code,
                    $workHour->date,
                    $workHour->hour_taken,

                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
