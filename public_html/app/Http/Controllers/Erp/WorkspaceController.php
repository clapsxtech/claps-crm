<?php
namespace App\Http\Controllers\Erp;
use App\Http\Controllers\Controller;
use App\Modules\Erp\Services\{Access,Audit,People,Scheduling,Attendance,Finance};
use App\Modules\IdentityAccess\Services\SiteAccessPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class WorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $units=(new Access())->units($request->user());
        return view('erp.index',compact('units'));
    }
    public function workspace(Request $request,int $unit)
    {
        (new Access())->unit($request->user(),$unit,false);
        $business=DB::table('erp_units')->find($unit); $data=[];
        $request->validate(['q'=>'nullable|string|max:100']);
        $search=trim((string)$request->query('q',''));
        $columns=['students'=>['code','name','grade','syllabus'],'enrollments'=>['student_id','teacher_id','subject_id','status'],'availability'=>['student_id','teacher_id','weekday'],'agreements'=>['enrollment_id','status','timezone'],'lessons'=>['student_id','teacher_id','subject_id','status'],'attendance'=>['lesson_id','status'],'duplicate_reviews'=>['attendance_id','other_attendance_id','status'],'ledger'=>['student_id','kind','reference'],'tasks'=>['title','status','student_id'],'marks'=>['assessment','enrollment_id'],'exceptions'=>['student_id','teacher_id','date','reason']];
        foreach($columns as $table=>$fields){
            $query=DB::table('erp_'.$table)->where('unit_id',$unit);
            if($search!=='')$query->where(function($q)use($fields,$search){foreach($fields as $field)$q->orWhere($field,'like','%'.$search.'%');});
            $data[$table]=$query->orderByDesc('id')->paginate(50,['*'],$table.'_page')->withQueryString();
        }
        $attendanceLedger=DB::table('erp_ledger')->where('unit_id',$unit)->whereIn('attendance_id',$data['attendance']->pluck('id'))->get()->groupBy('attendance_id');
        $lessonKinds=DB::table('erp_lessons')->where('unit_id',$unit)->whereIn('id',$data['attendance']->pluck('lesson_id'))->pluck('kind','id');
        foreach($data['attendance'] as $attendance){
            $entries=$attendanceLedger->get($attendance->id,collect()); $reversed=$entries->pluck('reverses_id')->filter()->all();
            $active=$entries->filter(function($entry)use($reversed){return !in_array($entry->id,$reversed)&&in_array($entry->kind,['lesson_charge','teacher_accrual']);});
            $demo=($lessonKinds[$attendance->lesson_id]??null)==='demo';
            $attendance->student_finance_state=$demo?'Free demo':($active->where('kind','lesson_charge')->isNotEmpty()?'Imported':($attendance->status==='rejected'?'Rejected / reversed':'Pending import'));
            $attendance->teacher_finance_state=$demo?'Unpaid demo':($active->where('kind','teacher_accrual')->isNotEmpty()?'Accrued':($attendance->status==='rejected'?'Rejected / reversed':($attendance->status==='approved'?'Approved, not accrued':'Pending approval')));
        }
        $balances=DB::table('erp_ledger')->where('unit_id',$unit)->whereIn('student_id',$data['students']->pluck('id'))->select('student_id')->selectRaw('SUM(amount_paise) AS balance')->groupBy('student_id')->pluck('balance','student_id');foreach($data['students'] as $student){$student->priority=(new People())->priority($student->registered_on,$business->timezone);$student->balance_paise=(int)($balances[$student->id]??0);}
        $teacherIds=DB::table('erp_teacher_units')->where('unit_id',$unit)->select('teacher_id');
        $teachers=DB::table('erp_teachers')->whereIn('id',$teacherIds);
        if($search!=='')$teachers->where(function($q)use($search){$q->where('name','like','%'.$search.'%')->orWhere('code','like','%'.$search.'%');});
        $data['teachers']=$teachers->orderBy('name')->paginate(50,['*'],'teachers_page')->withQueryString();
        $statements=(new \App\Modules\Erp\Services\Reporting())->teacherStatements($unit,$data['teachers']->pluck('id')->all());foreach($data['teachers'] as $teacher){$teacher->approved_accrual_paise=$statements[$teacher->id]['earned_paise'];}
        $subjects=DB::table('erp_subjects')->whereIn('id',DB::table('erp_eligibilities')->whereIn('teacher_id',$teacherIds)->select('subject_id'));
        if($search!=='')$subjects->where('name','like','%'.$search.'%');
        $data['subjects']=$subjects->orderBy('name')->paginate(50,['*'],'subjects_page')->withQueryString();
        return view('erp.workspace',compact('business','data'));
    }
    public function mutate(Request $request,int $unit,string $action)
    {
        (new Access())->unit($request->user(),$unit); $actor=(int)$request->user()->id; $p=$request->except('_token');
        $map=['student'=>[People::class,'student'],'teacher'=>[People::class,'teacher'],'eligibility'=>[People::class,'eligibility'],'task'=>[People::class,'task'],'complete-task'=>[People::class,'completeTask'],'mark'=>[People::class,'mark'],'assign'=>[Scheduling::class,'assign'],'end-assignment'=>[Scheduling::class,'endAssignment'],'availability'=>[Scheduling::class,'availability'],'propose'=>[Scheduling::class,'proposeAgreement'],'confirm'=>[Scheduling::class,'confirmAgreement'],'materialize'=>[Scheduling::class,'materialize'],'reschedule'=>[Scheduling::class,'reschedule'],'cancel'=>[Scheduling::class,'cancel'],'extra'=>[Scheduling::class,'scheduleExtra'],'exception'=>[Scheduling::class,'exception'],'end-agreement'=>[Scheduling::class,'endAgreement'],'attendance'=>[Attendance::class,'submit'],'payment'=>[Finance::class,'payment']];
        if($action==='import-batch'){ $p['attendance_ids']=is_array($request->input('attendance_ids'))?$request->input('attendance_ids'):array_filter(array_map('trim',explode(',',(string)$request->input('attendance_ids')))); $result=(new Attendance())->importBatch($unit,$actor,$p); }
        elseif($action==='approve')$result=(new Attendance())->approve($unit,$actor,(int)$request->input('attendance_id'));
        elseif($action==='correct')$result=(new Attendance())->correct($unit,$actor,(int)$request->input('attendance_id'),$p);
        elseif($action==='duplicate')$result=(new Attendance())->resolveDuplicate($unit,$actor,(int)$request->input('review_id'),$p);
        else{abort_unless(isset($map[$action]),404);[$class,$method]=$map[$action];$result=(new $class())->$method($unit,$actor,$p);}
        if($request->expectsJson())return response()->json(['result'=>$result]);
        $message='Saved and audited.';
        if($action==='approve')$message='Attendance approved. Teacher accrual: '.(!empty($result->teacher_financial_posted)?'posted':'none (demo or disabled policy)').'. Student charge: '.(!empty($result->student_financial_posted)?'already imported':'not imported').'. No money was transferred.';
        if(in_array($request->input('return_page'),['people','money','followups']))return redirect()->route('erp.business',[$unit,$request->input('return_page')])->with('status',$message);
        return redirect()->route('erp.workspace',$unit)->with('status',$message);
    }
    public function receipt(Request $request,int $unit,int $entry)
    {
        (new Access())->unit($request->user(),$unit,false);
        $receipt=DB::table('erp_ledger')->where('unit_id',$unit)->where('id',$entry)->where('kind','payment')->first();abort_unless($receipt,404);
        $student=DB::table('erp_students')->where('unit_id',$unit)->where('id',$receipt->student_id)->first();$business=DB::table('erp_units')->find($unit);
        $reversal=DB::table('erp_ledger')->where('unit_id',$unit)->where('reverses_id',$receipt->id)->first();
        $correction=DB::table('erp_audit')->where('unit_id',$unit)->where('entity_type','ledger')->where('entity_id',$receipt->id)->where('action','receipt.corrected')->orderByDesc('id')->first();$replacement=$correction?json_decode($correction->payload,true):null;
        return view('erp.receipt',compact('receipt','student','business','reversal','replacement')); 
    }
}
