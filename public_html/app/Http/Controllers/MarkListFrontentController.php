<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\Marklist;
use App\Models\Master;
use App\Models\Subject;
use Illuminate\Http\Request;
use Google\Client;
use Google\Service\Sheets;
use Google_Service_Sheets_ValueRange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;
class MarkListFrontentController extends Controller
{
    // public function index(Request $request)
    // {
    //     if ($request->ajax()) {
    //         $data = WorkHour::select('id','teacher_name','teacher_code',
    //         'student_name','student_code','subject','date','chapter',
    //         'topic','topic','hour_taken','mark_confirmation','created_at'); 
    //         return DataTables::of($data)
    //                 ->addIndexColumn()
    //                 ->addColumn('action', function ($row) {
    //                     $button = '<button type="button" data-href="'.route('subjects.edit',$row->id).'" class="btn btn-xs btn-primary edit_button" data-container=".modal_class"><i class="fa fa-edit"></i></button>&nbsp';
    //                     $button .='<button data-href="'.route('delete',['subjects',$row->id]).'" class="btn btn-xs btn-primary btn-modal"><i class="fa fa-trash"></i></button>';
    //                     return $button;
    //                 })
    //                 ->addColumn('status', function ($row) {
    //                     if($row->status==1){
    //                        return 'ACTIVE';
    //                     }elseif($row->status==0){
    //                         return 'IN ACTIVE';
    //                     }
    //                 })
    //                 ->filter(function ($instance) use ($request) {
    //                     if (!empty($request->get('search'))) {
    //                          $instance->where(function($w) use($request){
    //                             $search = $request->get('search');
    //                             $w->Where('name', 'LIKE', "%$search%");
    //                         });
    //                     }
    //                 })
    //                 ->rawColumns(['status','action'])
    //                 ->make(true);
    //     }
    //     return view('admin.workhour.index');
    // }

    public function create()
    {
       $teachers = Master::select('teacher_name')
    ->distinct()
    ->orderBy('teacher_name', 'asc')
    ->get();
        $subject = Subject::select('id','name')->get();
        return view('admin.workhour.create')->with(compact('teachers','subject'));
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
       'teacher_name' => 'required',
       'teacher_code' => 'required',
       'student_name' => 'required',
       'student_code' => 'required',
       'subject' => 'required',
       'examination_date' => 'required',
       'chapter' => 'required',
       'total_mark' => 'required',
       'mark_scored' => 'required',
       'remark' => 'required',

    ];

    $messages = [
       'teacher_code.required' => 'Teacher Code is required',
   ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
        return response()->json(['success' => false, 'msg' => $validator->errors()->first()]);
    }

    try {
       $subjectId = $request->input('subject');
       $selectedSubject = Subject::findOrFail($subjectId);
       $marklist = new Marklist();
       $marklist->teacher_name = $request->teacher_name;
       $marklist->teacher_code = $request->teacher_code;
       $marklist->student_name = $request->student_name;
       $marklist->student_code = $request->student_code;
       $marklist->subject      = $selectedSubject->name;
       $marklist->chapter            = $request->chapter;
       $marklist->mark_scored        = $request->mark_scored;
       $marklist->total_mark         = $request->total_mark;
       $marklist->mode_of_exam       = $request->mode_of_exam;
       $marklist->examination_date   = $request->examination_date;
       $marklist->remark             = $request->remark;
       $marklist->created_at = Carbon::now();
       $marklist->updated_at = Carbon::now();
       $teacherDob = Master::where('teacher_code', $request->teacher_code)->value('teacher_dob');
       if ($teacherDob !== $request->password) {
        $response = ['success' => false, 'msg' => 'Passwords do not match.'];
        return response()->json($response);
       }
    
       $marklist->save();
       $client = new Client();
       $client->setApplicationName('example-app');
       $client->setScopes([Sheets::SPREADSHEETS]);
       $client->setAuthConfig(storage_path('credentials.json'));

       $service = new Sheets($client);

       $spreadsheetId = '13ydSEHAwYqTFCd1DE_IUXOa457jVcPdSY7vPk-3faNs';
       $sheetName = 'MARKLIST';
       $values = [
           [
               $marklist->teacher_name,
               $marklist->teacher_code,
               $marklist->student_name,
               $marklist->student_code,
               $marklist->subject,
                $marklist->chapter,
                $marklist->mark_scored,
                $marklist->total_mark,
                $marklist->mode_of_exam,
                Carbon::createFromFormat('Y-m-d', $marklist->examination_date, )->format('d/m/Y'),
                $marklist->remark,
                Carbon::createFromFormat('Y-m-d H:i:s', $marklist->created_at)->format('d/m/Y H:i:s'),

           ],
       ];

       $range = $sheetName . '!A1:l1'; // Adjust the range as needed

       $body = new Google_Service_Sheets_ValueRange([
           'values' => $values,
       ]);

       $params = [
           'valueInputOption' => 'RAW',
       ];

       $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);

       return response()->json(['success' => true, 'msg' => 'Successfully created']);
   } catch (\Exception $e) {
       return response()->json(['success' => false, 'msg' => $e->getMessage()]);
   }
}
  public function frontetMarkUploadCreate()
    {
        $teachers = Master::select('teacher_name')
       ->distinct()
       ->orderBy('teacher_name', 'asc')
       ->get();
        $subject = Subject::select('id','name')->get();
        return view('marklistform')->with(compact('teachers','subject'));
    }
    
}
