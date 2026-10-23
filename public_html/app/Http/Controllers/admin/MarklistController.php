<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Marklist;
use App\Models\Students;
use App\Models\Subject;
use App\Models\Teacher;
use Google\Client;
use Google\Service\Sheets;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Google_Service_Sheets_ValueRange;
use Yajra\DataTables\Facades\DataTables;

class MarklistController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Marklist::select('marklists.id', 'marklists.teacher_name', 'students.student_name', 'marklists.teacher_code','teachers.teacher_name as teacher_name' ,'subjects.name as subject_name', 'marklists.chapter', 'marklists.total_mark',
        'marklists.mark_scored', 'marklists.examination_date', 'marklists.mode_of_exam', 'marklists.remark')
    ->leftJoin('subjects', 'marklists.subject', '=', 'subjects.id')
    ->leftJoin('students', 'marklists.student_name', '=', 'students.id')
    ->leftJoin('teachers', 'marklists.teacher_name', '=', 'teachers.id')
    ->orderBy('marklists.updated_at', 'DESC')
    ->get();
    return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    $button = '<button type="button" data-href="' . route('marklist.edit', $row->id) . '" data-keyboard="false" data-backdrop="static" class="btn btn-xs btn-primary edit_button" data-container=".modal_class"><i class="fa fa-edit"></i></button>&nbsp';
                    $button .= '<button data-href="' . route('delete', ['marklist', $row->id]) . '" class="btn btn-xs btn-danger btn-modal"><i class="fa fa-trash"></i></button>';
                    return $button;
                })
                ->filter(function ($instance) use ($request) {
                    if (!empty($request->get('search'))) {
                        $instance->where(function ($w) use ($request) {
                            $search = $request->get('search');
                            $w->Where('name', 'LIKE', "%$search%");
                        });
                    }
                })
                ->rawColumns(['action'])
                ->make(true);
        }
        return view('admin.marklist.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
    $students = Students::select('id', 'student_name')
    ->where('status', 'OnGoing ✅')
    ->orderBy('student_name', 'asc') 
    ->get();
    $teachers = Teacher::select('id', 'teacher_name')
    ->where('status', 'Available✅')
    ->orderBy('teacher_name', 'asc') 
    ->get();
        $subject = Subject::select('id','name')->get();
        return view('admin.marklist.create')->with(compact('subject','students','teachers'));
    }
    public function getTeacherInfo(Request $request)
    {
        $teacherId = $request->input('teacher_id');
        $teacher = Teacher::find($teacherId);
    
        if ($teacher) {
            return response()->json(['teacher_code' => $teacher->teacher_code]);
        } else {
            return response()->json(['teacher_code' => 'Teacher not found']);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
     {
       
         $rules = [
            'teacher_name'           => 'required',
            'teacher_code'           => 'required',
 
         ];
         $messages = [
            'teacher_code.required' => 'Teacher Code Required',
        ];
         $validator = Validator::make(request()->all(), $rules, $messages);
         if ($validator->fails()) {
             return response()->json(['success' => false, 'msg' => $validator->errors()->first()]);
         }
         try {
            $marklist = new Marklist();
            $marklist->teacher_code       = $request->teacher_code;
            $marklist->teacher_name       = $request->teacher_name;
            $marklist->student_name       = $request->student_name;
            $marklist->subject		      = $request->subject;
            $marklist->chapter            = $request->chapter;
            $marklist->total_mark         = $request->total_mark;
            $marklist->mark_scored        = $request->mark_scored;
            $marklist->mode_of_exam       = $request->mode_of_exam;
            $marklist->examination_date   = $request->examination_date;
            $marklist->remark             = $request->remark;
            $marklist->save();
            $createdId = $marklist->id;
            $subjectId = $request->input('subject');
            $selectedSubject = Subject::findOrFail($subjectId);
            $studentId =$request->input('student_name');
            $selectedStudent = Students::findOrFail($studentId);
            $teacherId =$request->input('teacher_name');
            $selectedTeacher = Teacher::findOrFail($teacherId);
            $client = new Client();
            $client->setApplicationName('example-app');
            $client->setScopes([Sheets::SPREADSHEETS]);
            $client->setAuthConfig(storage_path('credentials.json'));
    
            $service = new Sheets($client);
    
            $spreadsheetId = '13ydSEHAwYqTFCd1DE_IUXOa457jVcPdSY7vPk-3faNs';
            $sheetName = 'MARKLIST';
            $values = [
                [
                    $createdId,
                    $marklist->teacher_code,
                    $selectedTeacher->teacher_name,
                    $selectedSubject->name,
                    $selectedStudent->student_name,
                    $marklist->chapter,
                    $marklist->total_mark,
                    $marklist->mark_scored,
                    $marklist->mode_of_exam,
                    $marklist->examination_date,
                    $marklist->remark,
                ],
            ];
    
            $range = $sheetName . '!A1:J1'; // Adjust the range as needed
    
            $body = new Google_Service_Sheets_ValueRange([
                'values' => $values,
            ]);
    
            $params = [
                'valueInputOption' => 'RAW',
            ];
    
            $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
    
            return response()->json(['success' => true, 'msg' => 'Successfully created']);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['success' => false, 'msg' => 'Unable to create the mark-list record.'], 500);
        }
    }
     public function store1(Request $request)
     {
       
         $rules = [
            'teacher_name'           => 'required',
            'teacher_code'           => 'required',
 
         ];
         $messages = [
            'teacher_code.required' => 'Teacher Code Required',
        ];
         $validator = Validator::make(request()->all(), $rules, $messages);
         if ($validator->fails()) {
             return response()->json(['success' => false, 'msg' => $validator->errors()->first()]);
         }
         try {
            $marklist = new Marklist();
            $marklist->teacher_code       = $request->teacher_code;
            $marklist->teacher_name       = $request->teacher_name;
            $marklist->student_name       = $request->student_name;
            $marklist->subject		      = $request->subject;
            $marklist->chapter            = $request->chapter;
            $marklist->total_mark         = $request->total_mark;
            $marklist->mark_scored        = $request->mark_scored;
            $marklist->mode_of_exam       = $request->mode_of_exam;
            $marklist->examination_date   = $request->examination_date;
            $marklist->remark             = $request->remark;
            $marklist->save();
            $subjectId = $request->input('subject');
            $selectedSubject = Subject::findOrFail($subjectId);
            $studentId =$request->input('student_name');
            $selectedStudent = Students::findOrFail($studentId);

            $client = new Client();
            $newMarklistId = $marklist->id;
        $client->setApplicationName('example-app');
        $client->setScopes([Sheets::SPREADSHEETS]);
        $client->setAuthConfig(storage_path('credentials.json'));

        $service = new Sheets($client);

        $spreadsheetId = '13ydSEHAwYqTFCd1DE_IUXOa457jVcPdSY7vPk-3faNs';
        $sheetName = 'MARKLIST';
        $values = [
            [
                $marklist->teacher_code,
                $marklist->teacher_name,
                $marklist->student_name,
                $marklist->name,
                $marklist->chapter,
                $marklist->total_mark,
                $marklist->mark_scored,
                $marklist->mode_of_exam,
                $marklist->examination_date,
                $marklist->remark,
            ],
        ];

        $values = [
            [
                $newMarklistId,
                $marklist->teacher_code,
                $marklist->teacher_name,
                $selectedStudent->student_name,
                $selectedSubject->name,
                $marklist->chapter,
                $marklist->total_mark,
                $marklist->mark_scored,
                $marklist->mode_of_exam,
                $marklist->examination_date,
                $marklist->remark,
            ],
        ];

        $range = $sheetName;

        $body = new \Google\Service\Sheets\ValueRange(['values' => $values]);
        $params = [
            'valueInputOption' => 'RAW',
        ];

        $result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);

        return ['success' => true, 'msg' => 'successfully created and added to Google Sheet'];
    } catch (\Exception $e) {
        report($e);
        return ['success' => false, 'msg' => 'Unable to create the mark-list record.'];
    }
     }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (request()->ajax()) {
            $students = Students::select('id', 'student_name')
            ->where('status', 'OnGoing ✅')->get();
            $subject = Subject::select('id','name')->get();
            $marklist = Marklist::find($id);
            return view('admin.marklist.edit')->with(compact('marklist','students','subject'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (request()->ajax()) {
            $rules = [
            'teacher_name'           => 'required',
            'teacher_code'           => 'required',

            ];
            $messages = [
                'teacher_code.required' => 'Teacher Code Required',
            ];
            $validator = Validator::make(request()->all(), $rules, $messages);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'msg' => $validator->errors()->first()]);
            }
            try {
                $marklist = Marklist::findOrFail($id);
                $marklist->teacher_code       = $request->teacher_code;
                $marklist->teacher_name       = $request->teacher_name;
                $marklist->student_name       = $request->student_name;
                $marklist->subject		      = $request->subject;
                $marklist->chapter            = $request->chapter;
                $marklist->total_mark         = $request->total_mark;
                $marklist->mark_scored        = $request->mark_scored;
                $marklist->mode_of_exam       = $request->mode_of_exam;
                $marklist->examination_date   = $request->examination_date;
                $marklist->remark             = $request->remark;
                $marklist->save();
                return ['success' => true,'msg' =>'successfully updated'];
            } catch (\Exception $e) {
                report($e);
                return ['success' => false, 'msg' => 'Unable to update the mark-list record.'];
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (request()->ajax()) {
            try {
                $departments = Marklist::findOrFail($id);
                $departments->delete();
                return response()->json(['success' => true,'msg' =>'successfully deleted']);
            } catch (\Exception $e) {
                report($e);
                return response()->json(['success' => false,'msg' => 'Unable to delete the mark-list record.'], 500);
            }
        }
    }
}
