<?php
namespace App\Http\Controllers\Erp;
use App\Http\Controllers\Controller;
use App\Modules\Erp\Services\{Access,TeamPermissions,TuitionWorkflows};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TuitionController extends Controller
{
    public function __construct() {}
    private function authorizeTeam(Request $request,int $unit,bool $write): void
    {
        (new Access())->unit($request->user(),$unit,$write);
        abort_unless(in_array((new TeamPermissions())->role($request->user(),$unit),['superadmin','admin','team_manager'],true),403,'Private operations are restricted to administrators and team managers.');
    }
    public function index(Request $request,int $unit)
    {
        $this->authorizeTeam($request,$unit,false);
        $business=DB::table('erp_units')->find($unit);
        $lessons=DB::table('erp_lessons as l')->join('erp_students as s','s.id','=','l.student_id')->join('erp_teachers as t','t.id','=','l.teacher_id')->where('l.unit_id',$unit)->orderByDesc('l.starts_at')->limit(200)->get(['l.*','s.name as student_name','t.name as teacher_name']);
        $pending=DB::table('erp_attendance as a')->join('erp_lessons as l','l.id','=','a.lesson_id')->join('erp_students as s','s.id','=','l.student_id')->where('a.unit_id',$unit)->where('a.status','submitted')->orderBy('a.created_at')->paginate(30,['a.*','s.name as student_name','l.starts_at','l.ends_at']);
        $shortfalls=DB::table('erp_attendance as a')->join('erp_lessons as l','l.id','=','a.lesson_id')->join('erp_students as s','s.id','=','l.student_id')->where('a.unit_id',$unit)->where('a.status','!=','rejected')->where('l.kind','!=','demo')->orderByDesc('a.id')->limit(200)->get(['a.actual_minutes','l.id','l.starts_at','l.ends_at','s.name'])->filter(fn($r)=>$r->actual_minutes < \Carbon\Carbon::parse($r->starts_at)->diffInMinutes(\Carbon\Carbon::parse($r->ends_at)));
        $notes=DB::table('erp_internal_notes as n')->join('users as u','u.id','=','n.created_by')->where('n.unit_id',$unit)->orderByDesc('n.id')->limit(100)->get(['n.*','u.name as actor_name']);
        $candidates=DB::table('erp_candidates')->where('unit_id',$unit)->orderByDesc('id')->limit(200)->get();
        $students=DB::table('erp_students')->where('unit_id',$unit)->orderBy('name')->get(['id','name','code']);
        $interviews=DB::table('erp_candidate_interviews')->where('unit_id',$unit)->orderByDesc('id')->limit(200)->get();
        return view('erp.tuition',compact('business','lessons','pending','notes','candidates','interviews','shortfalls','students'));
    }
    public function statement(Request $request,int $unit)
    {
        $this->authorizeTeam($request,$unit,false);
        $v=$request->validate(['student_id'=>'required|integer|min:1','month'=>'required|date_format:Y-m']);
        $student=DB::table('erp_students')->where('unit_id',$unit)->where('id',$v['student_id'])->first(); abort_unless($student,404);
        $business=DB::table('erp_units')->find($unit);
        $start=\Carbon\CarbonImmutable::createFromFormat('!Y-m',$v['month'],$student->timezone)->startOfMonth();
        $rows=DB::table('erp_attendance as a')->join('erp_lessons as l','l.id','=','a.lesson_id')->join('erp_subjects as s','s.id','=','l.subject_id')->join('erp_teachers as t','t.id','=','l.teacher_id')
            ->where('a.unit_id',$unit)->where('l.unit_id',$unit)->where('l.student_id',$student->id)->where('a.status','!=','rejected')
            ->whereRaw('COALESCE(a.actual_starts_at,l.starts_at) >= ?',[$start->utc()->format('Y-m-d H:i:s')])->whereRaw('COALESCE(a.actual_starts_at,l.starts_at) < ?',[$start->addMonth()->utc()->format('Y-m-d H:i:s')])
            ->orderByRaw('COALESCE(a.actual_starts_at,l.starts_at)')->get(['a.*','l.starts_at','l.kind','l.student_rate_paise','s.name as subject_name','t.name as teacher_name']);
        $month=$v['month']; return view('erp.parent-statement',compact('business','student','rows','month'));
    }
    public function mutate(Request $request,int $unit,string $operation)
    {
        $this->authorizeTeam($request,$unit,true);
        if ($operation==='monthly-review') {
            $v=$request->validate(['attendance_ids'=>'required|array|min:1|max:200','attendance_ids.*'=>'required|integer|min:1','snapshots'=>'required|array','snapshots.*'=>'required|string|size:64','deadline_at'=>'required|date_format:Y-m-d H:i:s','note'=>'required|string|max:4000']);
            DB::transaction(function()use($unit,$request,$v){
                $ids=array_unique($v['attendance_ids']); sort($ids,SORT_NUMERIC);
                foreach($ids as $id) {
                    abort_unless(isset($v['snapshots'][$id]),422,'Prepare the statement again.');
                    (new TuitionWorkflows())->parentReview($unit,(int)$request->user()->id,['attendance_id'=>$id,'decision'=>'share','deadline_at'=>$v['deadline_at'],'note'=>$v['note'],'snapshot'=>$v['snapshots'][$id]]);
                }
            });
            return redirect()->route('erp.tuition',$unit)->with('status','Statement sharing recorded for the selected attendance entries.');
        }
        $method=['note'=>'note','parent-review'=>'parentReview','candidate'=>'candidate','interview'=>'interview'][$operation]??null;
        abort_unless($method,404);
        $row=(new TuitionWorkflows())->$method($unit,(int)$request->user()->id,$request->except('_token'));
        if ($request->expectsJson()) return response()->json(['result'=>$row]);
        return redirect()->route('erp.tuition',$unit)->with('status','Saved with your name and time in the audit history.');
    }
}
