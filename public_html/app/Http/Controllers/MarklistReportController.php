<?php

namespace App\Http\Controllers;

use App\Models\MarklistReport;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Sheets;
use Yajra\DataTables\Facades\DataTables;
class MarklistReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    { 
        // ::all();
            //   $this->syncMarklistReportData();
        if ($request->ajax()) {
            $data = MarklistReport::select('id','student_name','student_code','teacher_name','teacher_code','subject','chapter','mark_scored','total_mark','mode_of_exam',
            'examination_date','remark','date_submitted')->orderBy('date_submitted', 'desc'); 
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
        return view('admin.mark_report_teachers.index');
    }
    public function syncMarklistReportData()
    {
        try {
            $client = new Client();
            $client->setApplicationName('example-app');
            $client->setScopes([Sheets::SPREADSHEETS]);
            $client->setAuthConfig(storage_path('credentials.json'));
    
            $service = new Sheets($client);
    
            $spreadsheetId = '13ydSEHAwYqTFCd1DE_IUXOa457jVcPdSY7vPk-3faNs';
            $sheetName = 'MARKLIST';
    
            $response = $service->spreadsheets_values->get($spreadsheetId, $sheetName);
            $values = $response->getValues();
    
            if ($values) {
                MarklistReport::truncate();
        
                $dataRows = array_slice($values, 1);
            
                foreach ($dataRows as $row) {
                    if (count($row) >= 12) {
                        $insertMaster = [
                            'teacher_name' => $row[0],
                            'teacher_code' => $row[1],
                            'student_name' => $row[2],
                            'student_code' => $row[3],
                            'subject' => $row[4],
                            'chapter' => $row[5],
                            'mark_scored' => $row[6],
                            'total_mark' => $row[7],
                            'mode_of_exam' => $row[8],
                            'examination_date' => $row[9],
                            'remark' => $row[10],
                            'date_submitted' => $row[11],
                            'dob' => $row[12],
    
    
                        ];
            
                        MarklistReport::create($insertMaster);
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
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function MarklistReport(Request $request)
    {
        if ($request->ajax()) {

            $data = MarklistReport::select('id', 'teacher_name', 'student_name', 'dob','subject','chapter','examination_date','mark_scored','total_mark','date_submitted')->orderBy('date_submitted', 'desc'); 
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
            if ($request->has('fromdate')) {
                $data->where('date', '>=', $request->input('fromdate'));
            }
            
            // Check for filter_date_to and add similar logic
            if ($request->has('filter_date_to')) {
                $data->where('date', '<=', $request->input('filter_date_to'));
            }
            
            // if ($request->has('filter_date_to')) {
            //     $data->where('date', '<=', $request->input('filter_date_to'));
            // }
    
            return DataTables::of($data)
                ->addIndexColumn()
                ->make(true);
        }
    
        $teachers = MarklistReport::select('teacher_name')
            ->distinct()
            ->get();
       
        return view('markreport', compact('teachers'));
    }

    public function getStudentData(Request $request)
    {
     
    
        $teacherName = $request->input('teacher_name');
        $password = $request->input('dob');
        $teacher = MarklistReport::where('teacher_name', $teacherName)
            ->where('dob', $password)
            ->first();
            if ($teacher) {
                $students = MarklistReport::where('teacher_name', $teacherName)
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
}
