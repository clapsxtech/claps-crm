<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\TeacherReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Google\Client;
use Google\Service\Sheets;
use Yajra\DataTables\Facades\DataTables;

class TeacherReportController extends Controller
{
   
    public function index(Request $request)
    { 
        // ::all();
        if ($request->ajax()) {
            $data = TeacherReport::select('id','student_name','student_code','teacher_name','teacher_code','subject','date','date_submitted','chapter','topic','hour_taken','homework_status')->orderBy('date_submitted', 'desc'); 
            return DataTables::of($data)
                    ->addIndexColumn()
                    ->addColumn('action', function ($row) {
                        $button = '<button type="button" data-href="'.route('subjects.edit',$row->id).'" class="btn btn-xs btn-primary edit_button" data-container=".modal_class"><i class="fa fa-edit"></i></button>&nbsp';
                        $button .='<button data-href="'.route('delete',['subjects',$row->id]).'" class="btn btn-xs btn-primary btn-modal"><i class="fa fa-trash"></i></button>';
                        return $button;
                    })
                    ->filter(function ($instance) use ($request) {
                        if (!empty($request->get('search'))) {
                             $instance->where(function($w) use($request){
                                $search = $request->get('search');
                                $w->Where('name', 'LIKE', "%$search%");
                            });
                        }
                    })
                    ->rawColumns(['action'])
                    ->make(true);
        }
        return view('admin.report_teacherhour.index');
    }

    public function syncTeacherReportData()
    {
        $client = new Client();
        $client->setApplicationName('example-app');
        $client->setScopes([Sheets::SPREADSHEETS]);
        $client->setAuthConfig(storage_path('credentials.json'));

        $service = new Sheets($client);

        $spreadsheetId = '13ydSEHAwYqTFCd1DE_IUXOa457jVcPdSY7vPk-3faNs';
        $sheetName = 'WORKHOUR';

        $response = $service->spreadsheets_values->get($spreadsheetId, $sheetName);
        $values = $response->getValues();

        if ($values) {
            TeacherReport::truncate();
    
            $dataRows = array_slice($values, 1);
        
            foreach ($dataRows as $row) {
                if (count($row) >= 11) {
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


                    ];
        
                    TeacherReport::create($insertMaster);
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

            $data = TeacherReport::select('id', 'teacher_name', 'student_name', 'dob','subject','topic','date','hour_taken','date_submitted')->orderBy('date', 'desc'); 
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
    
        $teachers = TeacherReport::select('teacher_name')
            ->distinct()
            ->get();
       
        return view('hourreport', compact('teachers'));
    }

    public function getStudentData(Request $request)
    {
     
    
        $teacherName = $request->input('teacher_name');
        $password = $request->input('dob');
        $teacher = TeacherReport::where('teacher_name', $teacherName)
            ->where('dob', $password)
            ->first();
            if ($teacher) {
                $students = TeacherReport::where('teacher_name', $teacherName)
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
    public function studentsAttendanceReport(Request $request)
    {
        if ($request->ajax()) {

            $data = TeacherReport::select('id', 'teacher_name', 'student_name','student_code','subject','topic','date','hour_taken','date_submitted')->orderBy('date', 'desc'); 
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
    
        $students = TeacherReport::select('student_name')
            ->distinct()
            ->get();
       
        return view('hourreportstudents', compact('students'));
    }
    public function getTeachersData(Request $request)
{
    $studentName = $request->input('student_name');
    $studentCode = $request->input('student_code');

    $matchingStudent = TeacherReport::where('student_name', $studentName)
        ->where('student_code', $studentCode)
        ->first();

    if ($matchingStudent) {
        $teachers = TeacherReport::where('student_name', $studentName)
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
    


}