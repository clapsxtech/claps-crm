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
        foreach($data['students'] as $student){$student->priority=(new People())->priority($student->registered_on,$business->timezone);$student->balance_paise=(new Finance())->balance($unit,$student->id);}
        $teacherIds=DB::table('erp_teacher_units')->where('unit_id',$unit)->select('teacher_id');
        $teachers=DB::table('erp_teachers')->whereIn('id',$teacherIds);
        if($search!=='')$teachers->where(function($q)use($search){$q->where('name','like','%'.$search.'%')->orWhere('code','like','%'.$search.'%');});
        $data['teachers']=$teachers->orderBy('name')->paginate(50,['*'],'teachers_page')->withQueryString();
        $subjects=DB::table('erp_subjects')->whereIn('id',DB::table('erp_eligibilities')->whereIn('teacher_id',$teacherIds)->select('subject_id'));
        if($search!=='')$subjects->where('name','like','%'.$search.'%');
        $data['subjects']=$subjects->orderBy('name')->paginate(50,['*'],'subjects_page')->withQueryString();
        return view('erp.workspace',compact('business','data'));
    }
    public function mutate(Request $request,int $unit,string $action)
    {
        (new Access())->unit($request->user(),$unit); $actor=(int)$request->user()->id; $p=$request->except('_token');
        $map=['student'=>[People::class,'student'],'teacher'=>[People::class,'teacher'],'eligibility'=>[People::class,'eligibility'],'task'=>[People::class,'task'],'complete-task'=>[People::class,'completeTask'],'mark'=>[People::class,'mark'],'assign'=>[Scheduling::class,'assign'],'end-assignment'=>[Scheduling::class,'endAssignment'],'availability'=>[Scheduling::class,'availability'],'propose'=>[Scheduling::class,'proposeAgreement'],'confirm'=>[Scheduling::class,'confirmAgreement'],'materialize'=>[Scheduling::class,'materialize'],'reschedule'=>[Scheduling::class,'reschedule'],'cancel'=>[Scheduling::class,'cancel'],'exception'=>[Scheduling::class,'exception'],'end-agreement'=>[Scheduling::class,'endAgreement'],'attendance'=>[Attendance::class,'submit'],'payment'=>[Finance::class,'payment']];
        if($action==='import-batch'){ $p['attendance_ids']=is_array($request->input('attendance_ids'))?$request->input('attendance_ids'):array_filter(array_map('trim',explode(',',(string)$request->input('attendance_ids')))); $result=(new Attendance())->importBatch($unit,$actor,$p); }
        elseif($action==='approve')$result=(new Attendance())->approve($unit,$actor,(int)$request->input('attendance_id'));
        elseif($action==='correct')$result=(new Attendance())->correct($unit,$actor,(int)$request->input('attendance_id'),$p);
        elseif($action==='duplicate')$result=(new Attendance())->resolveDuplicate($unit,$actor,(int)$request->input('review_id'),$p);
        else{abort_unless(isset($map[$action]),404);[$class,$method]=$map[$action];$result=(new $class())->$method($unit,$actor,$p);}
        if($request->expectsJson())return response()->json(['result'=>$result]);
        $message='Saved and audited.';
        if($action==='approve'&&isset($result->financial_posted)&&!$result->financial_posted)$message='Attendance reviewed. Review alone never posts charges; use the explicit admin import batch.';
        return redirect()->route('erp.workspace',$unit)->with('status',$message);
    }
    public function receipt(Request $request,int $unit,int $entry)
    {
        (new Access())->unit($request->user(),$unit,false);
        $receipt=DB::table('erp_ledger')->where('unit_id',$unit)->where('id',$entry)->where('kind','payment')->first();abort_unless($receipt,404);
        $student=DB::table('erp_students')->where('unit_id',$unit)->where('id',$receipt->student_id)->first();$business=DB::table('erp_units')->find($unit);
        return view('erp.receipt',compact('receipt','student','business'));
    }
}
