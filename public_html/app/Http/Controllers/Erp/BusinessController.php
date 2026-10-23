<?php
namespace App\Http\Controllers\Erp;
use App\Http\Controllers\Controller;
use App\Modules\Erp\Services\{Access,People,MoneyOperations,Operations};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class BusinessController extends Controller
{
    public function __construct() {}
    private function pages(){return ['dashboard'=>'Overview','people'=>'Students & teachers','timetable'=>'Timetable','attendance'=>'Attendance','money'=>'Money & receipts','followups'=>'Follow-ups','staff'=>'Team access','migration'=>'Migration & exports'];}
    public function page(Request $request,int $unit,string $page='dashboard')
    {
        (new Access())->unit($request->user(),$unit,false);abort_unless(isset($this->pages()[$page]),404);
        $business=DB::table('erp_units')->find($unit);$pages=$this->pages();$data=[];$money=new MoneyOperations();
        $request->validate(['q'=>'nullable|string|max:100']);$search=trim((string)$request->query('q',''));
        if(in_array($page,['dashboard','people','money','followups'])){
            $balances=DB::table('erp_ledger')->where('unit_id',$unit)->whereNotNull('student_id')->select('student_id',DB::raw('SUM(amount_paise) AS balance'))->groupBy('student_id');
            $students=DB::table('erp_students as s')->leftJoinSub($balances,'b',function($j){$j->on('s.id','=','b.student_id');})->where('s.unit_id',$unit)->select('s.*',DB::raw('COALESCE(b.balance,0) AS balance_paise'));
            if($search!=='')$students->where(function($q)use($search){$q->where('s.name','like','%'.$search.'%')->orWhere('s.code','like','%'.$search.'%');});
            if($page==='money'||$request->query('filter')==='due')$students->whereRaw('COALESCE(b.balance,0)<0');
            if($page==='followups'){ $month=now($business->timezone)->startOfMonth(); $priority=$request->query('priority'); if($priority==='super')$students->where('s.registered_on','>=',$month->toDateString()); elseif($priority==='high')$students->whereBetween('s.registered_on',[$month->copy()->subMonth()->toDateString(),$month->copy()->subDay()->toDateString()]); elseif($priority==='medium')$students->where('s.registered_on','<',$month->copy()->subMonth()->toDateString()); }
            $data['students']=$students->orderBy('s.name')->paginate(25,['*'],'students_page')->withQueryString();
            foreach($data['students'] as $student)$student->priority=(new People())->priority($student->registered_on,$business->timezone);
        }
        if(in_array($page,['people','money'])){
            $data['teachers']=DB::table('erp_teachers as t')->join('erp_teacher_units as u','u.teacher_id','=','t.id')->where('u.unit_id',$unit)->select('t.*')->orderBy('t.name')->paginate(25,['*'],'teachers_page')->withQueryString();
            $statements=(new \App\Modules\Erp\Services\Reporting())->teacherStatements($unit,$data['teachers']->pluck('id')->all());foreach($data['teachers'] as $teacher){foreach($statements[$teacher->id] as $key=>$value)$teacher->$key=$value;}
        }
        $tables=['dashboard'=>['tasks','duplicate_reviews'],'money'=>['payment_requests','ledger'],'followups'=>['tasks','task_items','followups'],'staff'=>['memberships'],'migration'=>['migration_history'],'attendance'=>['attendance','duplicate_reviews'],'timetable'=>['lessons','agreements','exceptions']];
        foreach($tables[$page]??[] as $table){$query=DB::table('erp_'.$table)->where('unit_id',$unit);if($page==='dashboard'&&$table==='tasks')$query->where('status','!=','completed');if($page==='dashboard'&&$table==='duplicate_reviews')$query->where('status','pending');$data[$table]=$query->orderByDesc('id')->paginate(25,['*'],$table.'_page')->withQueryString();}
        if(isset($data['payment_requests'])){$summaries=(new \App\Modules\Erp\Services\Reporting())->requestSummaries($unit,$data['payment_requests']->pluck('id')->all());foreach($data['payment_requests'] as $row)foreach($summaries[$row->id] as $key=>$value)$row->$key=$value;}
        if($page==='followups'){ $latest=DB::table('erp_followups')->where('unit_id',$unit)->select(DB::raw('MAX(id) AS id'))->groupBy('student_id');$data['followup_due']=DB::table('erp_followups')->where('unit_id',$unit)->whereIn('id',$latest)->whereNotNull('next_due_on')->where('next_due_on','<=',now($business->timezone)->toDateString())->orderBy('next_due_on')->paginate(25,['*'],'followup_due_page')->withQueryString(); }
        if($page==='migration')$reconciliation=(new \App\Modules\Erp\Services\Reporting())->reconciliation($unit);else $reconciliation=null;
        $metrics=['Active students'=>DB::table('erp_students')->where('unit_id',$unit)->where('status','active')->count(),'Scheduled lessons'=>DB::table('erp_lessons')->where('unit_id',$unit)->where('status','scheduled')->count(),'Pending teacher approval'=>DB::table('erp_attendance')->where('unit_id',$unit)->where('status','submitted')->count(),'Duplicate reviews'=>DB::table('erp_duplicate_reviews')->where('unit_id',$unit)->where('status','pending')->count()];
        $forms=config('erp_forms.'.$page,[]);
        return view('erp.business',compact('business','page','pages','data','metrics','forms','reconciliation'));
    }
    public function mutate(Request $request,int $unit,string $action)
    {
        (new Access())->unit($request->user(),$unit);$map=[
            'student-update'=>[Operations::class,'updateStudent'],'task-update'=>[Operations::class,'updateTask'],'task-item'=>[Operations::class,'addTaskItem'],'task-item-complete'=>[Operations::class,'completeTaskItem'],'followup'=>[Operations::class,'recordFollowup'],'membership-grant'=>[Operations::class,'grantMembership'],'membership-revoke'=>[Operations::class,'revokeMembership'],'teacher-link'=>[Operations::class,'linkTeacherUser'],'teacher-unlink'=>[Operations::class,'unlinkTeacherUser'],
            'salary-paid'=>[MoneyOperations::class,'recordTeacherPayment'],'salary-reverse'=>[MoneyOperations::class,'reverseTeacherPayment'],'receipt-reverse'=>[MoneyOperations::class,'reverseReceipt'],'receipt-correct'=>[MoneyOperations::class,'correctReceipt'],'request-create'=>[MoneyOperations::class,'createPaymentRequest'],'request-cancel'=>[MoneyOperations::class,'cancelPaymentRequest'],'request-allocate'=>[MoneyOperations::class,'allocateReceipt']];
        abort_unless(isset($map[$action]),404);[$class,$method]=$map[$action];$payload=$request->except('_token','return_page');if(in_array($action,['student-update','task-update'])){
            $clearable=$action==='student-update'?['guardian_contact','meeting_url']:['notes','student_id','assigned_to','due_on'];
            $clear=$request->input('clear_fields',[]);$request->validate(['clear_fields'=>'sometimes|array','clear_fields.*'=>'string|in:'.implode(',',$clearable)]);
            $payload=array_filter($payload,function($v){return $v!==null&&$v!=='';});unset($payload['clear_fields']);
            foreach($clear as $field)$payload[$field]=null;
        }$result=(new $class())->$method($unit,(int)$request->user()->id,$payload);
        if($request->expectsJson())return response()->json(['result'=>$result]);$page=$request->input('return_page','dashboard');if(!isset($this->pages()[$page]))$page='dashboard';return redirect()->route('erp.business',[$unit,$page])->with('status','Recorded and audited. No message or money transfer was sent.');
    }
    public function lookup(Request $request,int $unit)
    {
        (new Access())->unit($request->user(),$unit,false);$v=$request->validate(['type'=>'required|string','q'=>'nullable|string|max:100']);$q=trim($v['q']??'');$type=$v['type'];
        if($type==='students')$query=DB::table('erp_students')->where('unit_id',$unit)->select('id','name','code');
        elseif($type==='teachers')$query=DB::table('erp_teachers')->whereIn('id',DB::table('erp_teacher_units')->where('unit_id',$unit)->select('teacher_id'))->select('id','name','code');
        elseif($type==='users')$query=DB::table('users')->where('business_id',config('erp.business_id'))->where('status',1)->whereNull('deleted_at')->select('id','name',DB::raw("'Existing site account' AS code"));
        elseif($type==='members')$query=DB::table('users')->whereIn('id',DB::table('erp_memberships')->where('unit_id',$unit)->select('user_id'))->where('business_id',config('erp.business_id'))->where('status',1)->whereNull('deleted_at')->select('id','name',DB::raw("'Unit staff' AS code"));
        elseif($type==='revocable_members'){
            $query=DB::table('erp_memberships as m')->leftJoin('users as u','u.id','=','m.user_id')->where('m.unit_id',$unit);
            if($q!=='')$query->where(function($w)use($q){$w->where('u.name','like','%'.$q.'%')->orWhere('m.user_id',$q);});
            return response()->json($query->select('m.user_id','m.role','u.name','u.status','u.deleted_at')->orderBy('m.user_id')->limit(30)->get()->map(function($r){return ['id'=>$r->user_id,'label'=>($r->name??'Unavailable account').' · '.$r->role.((!$r->status||$r->deleted_at)?' (inactive)':'').' (#'.$r->user_id.')'];}));
        }
        elseif($type==='subjects'){
            $query=DB::table('erp_subjects')->whereIn('id',DB::table('erp_eligibilities')->whereIn('teacher_id',DB::table('erp_teacher_units')->where('unit_id',$unit)->select('teacher_id'))->select('subject_id'));
            if($q!=='')$query->where('name','like','%'.$q.'%');return response()->json($query->orderBy('name')->limit(30)->get()->map(function($r){return ['id'=>$r->id,'label'=>$r->name.' (#'.$r->id.')'];}));
        }
        elseif(in_array($type,['enrollments','lessons','agreements','attendance','reviews'])){
            $table=$type==='reviews'?'duplicate_reviews':$type;$query=DB::table('erp_'.$table.' as p')->where('p.unit_id',$unit);
            if($type==='agreements')$query->join('erp_enrollments as e','e.id','=','p.enrollment_id');
            elseif($type==='attendance')$query->join('erp_lessons as e','e.id','=','p.lesson_id');
            elseif($type==='reviews')$query->join('erp_attendance as a','a.id','=','p.attendance_id')->join('erp_lessons as e','e.id','=','a.lesson_id');
            $alias=in_array($type,['enrollments','lessons'])?'p':'e';
            $query->join('erp_students as s','s.id','=',$alias.'.student_id')->join('erp_teachers as t','t.id','=',$alias.'.teacher_id')->where('s.unit_id',$unit);
            if($q!=='')$query->where(function($w)use($q){$w->where('s.name','like','%'.$q.'%')->orWhere('s.code','like','%'.$q.'%')->orWhere('t.name','like','%'.$q.'%')->orWhere('p.id',$q);});
            $extra=$type==='lessons'?['p.starts_at']:($type==='agreements'?['p.weekday','p.start_time']:[]);
            return response()->json($query->select(array_merge(['p.id','p.status','s.name as student','t.name as teacher'],$extra))->orderByDesc('p.id')->limit(30)->get()->map(function($r){return ['id'=>$r->id,'label'=>$r->student.' / '.$r->teacher.' · '.($r->starts_at??$r->start_time??'').' · '.$r->status.' (#'.$r->id.')'];}));
        }
        else{ $map=['tasks'=>['erp_tasks','title'],'items'=>['erp_task_items','title'],'requests'=>['erp_payment_requests','reference'],'payments'=>['erp_ledger','reference'],'salary'=>['erp_ledger','reference']];abort_unless(isset($map[$type]),422);[$table,$column]=$map[$type];$query=DB::table($table)->where('unit_id',$unit)->select('id',$column.' AS name');if($type==='payments')$query->where('kind','payment');if($type==='salary')$query->where('kind','teacher_payment');if($q!=='')$query->where($column,'like','%'.$q.'%');return response()->json($query->orderByDesc('id')->limit(30)->get()->map(function($r){return ['id'=>$r->id,'label'=>$r->name.' (#'.$r->id.')'];})); }
        if($q!=='')$query->where(function($w)use($q,$type){$w->where('name','like','%'.$q.'%');if(in_array($type,['students','teachers']))$w->orWhere('code','like','%'.$q.'%');});
        return response()->json($query->orderBy('name')->limit(30)->get()->map(function($r){return ['id'=>$r->id,'label'=>$r->name.' · '.$r->code.' (#'.$r->id.')'];}));
    }
    public function requestPrint(Request $request,int $unit,int $id)
    {
        (new Access())->unit($request->user(),$unit,false);$paymentRequest=DB::table('erp_payment_requests')->where('unit_id',$unit)->where('id',$id)->first();abort_unless($paymentRequest,404);$student=DB::table('erp_students')->where('unit_id',$unit)->where('id',$paymentRequest->student_id)->first();$business=DB::table('erp_units')->find($unit);$summary=(new \App\Modules\Erp\Services\Reporting())->requestSummaries($unit,[$id])[$id];return view('erp.payment-request',compact('paymentRequest','student','business','summary'));
    }
    public function export(Request $request,int $unit,string $dataset)
    {
        (new Access())->unit($request->user(),$unit,false);$report=new \App\Modules\Erp\Services\Reporting();$query=$report->rows($unit,$dataset);$columns=$report->datasets()[$dataset];
        return response()->streamDownload(function()use($query,$columns,$report){$out=fopen('php://output','w');fputcsv($out,$columns);foreach($query->cursor() as $row)fputcsv($out,array_map(function($v)use($report){return $report->safeCsvValue($v);},(array)$row));fclose($out);},'erp-unit-'.$unit.'-'.$dataset.'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
    public function reconciliation(Request $request,int $unit)
    {
        (new Access())->unit($request->user(),$unit,false);return response()->json((new \App\Modules\Erp\Services\Reporting())->reconciliation($unit));
    }

}
