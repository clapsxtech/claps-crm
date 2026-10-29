<?php
namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Modules\Erp\Services\Attendance;
use App\Modules\IdentityAccess\Services\SiteAccessPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Auth\Access\AuthorizationException;

class TeacherController extends Controller
{
    // ERP views do not read legacy settings or trigger legacy application data access.
    public function __construct() {}
    private function identity(Request $request): array
    {
        $user=$request->user();
        (new SiteAccessPolicy())->assertSiteMember($user);
        // Explicit administrator-created user linkage only. Never infer identity from a name/email.
        $teacher=DB::table('erp_teachers')->where('user_id',$user->id)->where('status','active')->first(['id','code','name','timezone']);
        if (!$teacher) throw new AuthorizationException('An active, explicitly linked teacher account is required.');
        $pools=DB::table('erp_teacher_units')->where('teacher_id',$teacher->id)->get();
        if ($pools->count() !== 1) throw new AuthorizationException('Teacher must belong to exactly one unit pool.');
        $unitId=(int)$pools->first()->unit_id;
        if (DB::table('erp_units')->where('id',$unitId)->whereNotNull('archived_at')->exists()) throw new AuthorizationException('This team is archived.');
        if (!DB::table('erp_memberships')->where('user_id',$user->id)->where('unit_id',$unitId)->where('role','teacher')->exists()) throw new AuthorizationException('Explicit teacher membership in this unit is required.');
        return [$teacher,$unitId,(int)$user->id];
    }

    public function entry(Request $request)
    {
        if(DB::table('erp_teachers')->where('user_id',$request->user()->id)->exists() || DB::table('erp_memberships')->where('user_id',$request->user()->id)->where('role','teacher')->exists()) return redirect()->route('erp.teacher.index');
        $units=(new \App\Modules\Erp\Services\Access())->units($request->user());
        if($units->isEmpty()) return redirect()->route('erp.teacher.index');
        return view('erp.teacher-setup',compact('units'));
    }

    public function index(Request $request)
    {
        [$teacher,$unitId]=$this->identity($request);
        $filters=Validator::make($request->only(['from','through','student']),['from'=>'nullable|date_format:Y-m-d','through'=>'nullable|date_format:Y-m-d','student'=>'nullable|integer|min:1'])->validate();
        $from=$filters['from'] ?? \Carbon\CarbonImmutable::now($teacher->timezone)->subDays(30)->toDateString();
        $through=$filters['through'] ?? \Carbon\CarbonImmutable::now($teacher->timezone)->addDays(90)->toDateString();
        if ($through < $from || (strtotime($through)-strtotime($from)) > 366*86400) throw \Illuminate\Validation\ValidationException::withMessages(['through'=>'Choose a date range of at most 366 days.']);
        $students=DB::table('erp_enrollments as e')->join('erp_students as s',function($join){$join->on('s.id','=','e.student_id')->on('s.unit_id','=','e.unit_id');})->where('e.unit_id',$unitId)->where('e.teacher_id',$teacher->id)->select('s.id','s.name','s.code')->distinct()->orderBy('s.name')->get();
        $studentId=$filters['student'] ?? null;
        if($studentId && !$students->contains('id',(int)$studentId)) throw new AuthorizationException('Choose one of your own students.');
        $lessons=DB::table('erp_lessons as l')
            ->join('erp_students as s',function($join) { $join->on('s.id','=','l.student_id')->on('s.unit_id','=','l.unit_id'); })
            ->join('erp_subjects as subject','subject.id','=','l.subject_id')
            ->leftJoin('erp_attendance as a',function($join) { $join->on('a.lesson_id','=','l.id')->on('a.unit_id','=','l.unit_id'); })
            ->where('l.unit_id',$unitId)->where('l.teacher_id',$teacher->id)->when($studentId,fn($query)=>$query->where('l.student_id',$studentId))
            ->where('l.starts_at','>=',\Carbon\CarbonImmutable::parse($from,$teacher->timezone)->startOfDay()->utc()->format('Y-m-d H:i:s'))->where('l.starts_at','<=',\Carbon\CarbonImmutable::parse($through,$teacher->timezone)->endOfDay()->utc()->format('Y-m-d H:i:s'))
            ->orderBy('l.starts_at')->limit(501)
            ->get(['l.id','l.starts_at','l.ends_at','l.status','l.kind','s.name as student_name','s.code as student_code','subject.name as subject_name','a.id as attendance_id','a.status as attendance_status','a.actual_minutes']);
        $truncated=$lessons->count()>500; $lessons=$lessons->take(500);
        $assignments=DB::table('erp_enrollments as e')->join('erp_students as s',function($join){$join->on('s.id','=','e.student_id')->on('s.unit_id','=','e.unit_id');})
            ->join('erp_subjects as subject','subject.id','=','e.subject_id')->where('e.teacher_id',$teacher->id)->where('e.unit_id',$unitId)
            ->where('e.status','active')->where('s.status','active')->orderBy('s.name')
            ->get(['e.id','s.name','s.code','s.grade','s.syllabus','subject.name as subject_name']);
        $resultQuery=DB::table('erp_marks as m')->join('erp_enrollments as e',function($join){$join->on('e.id','=','m.enrollment_id')->on('e.unit_id','=','m.unit_id');})
            ->join('erp_students as s',function($join){$join->on('s.id','=','e.student_id')->on('s.unit_id','=','e.unit_id');})
            ->join('erp_subjects as subject','subject.id','=','e.subject_id')->where('e.teacher_id',$teacher->id)->where('m.unit_id',$unitId)
            ->when($studentId,fn($query)=>$query->where('e.student_id',$studentId))->whereBetween('m.assessed_on',[$from,$through])->orderByDesc('m.assessed_on')->orderByDesc('m.id')
            ;
        $page=max(1,(int)$request->query('results_page',1));
        $results=new \Illuminate\Pagination\LengthAwarePaginator((clone $resultQuery)->forPage($page,25)->get(['m.id','m.assessment','m.assessed_on','m.score','m.maximum','m.notes','s.name as student_name','subject.name as subject_name']),$resultQuery->count(),25,$page,['path'=>'/erp/teacher','pageName'=>'results_page','query'=>$request->query()]);
        $section=Validator::make($request->only('section'),['section'=>'nullable|in:overview,timetable,students,attendance,results,reports'])->validate()['section'] ?? 'overview';
        return view('erp.teacher',compact('teacher','lessons','from','through','truncated','assignments','results','section','students','studentId'));
    }

    public function result(Request $request)
    {
        [$teacher,$unitId,$actorId]=$this->identity($request);
        $v=$request->validate(['enrollment_id'=>'required|integer|min:1','assessment'=>'required|string|max:255','assessed_on'=>'required|date_format:Y-m-d|before_or_equal:'.\Carbon\CarbonImmutable::now($teacher->timezone)->toDateString(),'score'=>'required|integer|min:0|lte:maximum','maximum'=>'required|integer|min:1|max:1000000000','notes'=>'nullable|string|max:4000','submission_key'=>'required|uuid']);
        foreach(['enrollment_id','score','maximum'] as $field) $v[$field]=(int)$v[$field];
        $key=strtolower($v['submission_key']);unset($v['submission_key']);
        $hash=hash('sha256',json_encode([$unitId,$actorId,$teacher->id,$v]));
        DB::transaction(function()use($request,$teacher,$unitId,$actorId,$v,$key,$hash){
            DB::table('erp_teachers')->where('id',$teacher->id)->lockForUpdate()->first();
            [$current,$currentUnit]=$this->identity($request);
            abort_unless($current->id===$teacher->id && $currentUnit===$unitId,403);
            $assignment=DB::table('erp_enrollments')->where('id',$v['enrollment_id'])->where('unit_id',$unitId)->where('teacher_id',$teacher->id)->lockForUpdate()->first();
            abort_unless($assignment,403,'Choose one of your own assignments.');
            $saved=DB::table('erp_marks')->where('submission_key',$key)->first();
            if($saved){abort_unless(hash_equals((string)$saved->submission_hash,$hash),409,'This submission was already used with different details. Reload before creating a new result.');return;}
            abort_unless($assignment->status==='active' && DB::table('erp_students')->where('id',$assignment->student_id)->where('unit_id',$unitId)->where('status','active')->exists(),422,'This assignment is no longer active.');
            $record=(new \App\Modules\Erp\Services\People())->mark($unitId,$actorId,$v);
            DB::table('erp_marks')->where('id',$record['id'])->update(['submission_key'=>$key,'submission_hash'=>$hash]);
        },3);
        return redirect()->route('erp.teacher.index',['section'=>'results'])->with('status','Assessment result saved.');
    }

    public function submit(Request $request)
    {
        [$teacher,$unitId,$actorId]=$this->identity($request);
        $payload=Validator::make($request->only(['lesson_id','actual_minutes','actual_starts_at','change_reason']),['actual_starts_at'=>'nullable|date_format:Y-m-d\\TH:i','change_reason'=>'nullable|string|max:4000','lesson_id'=>'required|integer|min:1','actual_minutes'=>'required|integer|min:1|max:480'])->validate();
        if (!empty($payload['actual_starts_at'])) $payload['actual_starts_at']=\Carbon\CarbonImmutable::createFromFormat('Y-m-d\\TH:i',$payload['actual_starts_at'],$teacher->timezone)->utc()->format('Y-m-d H:i:s');
        DB::transaction(function() use ($unitId,$actorId,$teacher,$payload,$request) {
            // Stable teacher lock also serializes scheduling and explicit pool changes.
            DB::table('erp_teachers')->where('id',$teacher->id)->lockForUpdate()->first();
            [$current,$currentUnit]=$this->identity($request);
            if ($current->id !== $teacher->id || $currentUnit !== $unitId || !DB::table('erp_lessons')->where('id',$payload['lesson_id'])->where('unit_id',$unitId)->where('teacher_id',$teacher->id)->exists()) throw new AuthorizationException('Only your own dated lessons in your unit can receive your attendance.');
            (new Attendance())->submit($unitId,$actorId,$payload);
        }, 3);
        return redirect()->route('erp.teacher.index')->with('status','Attendance submitted for administrator review.');
    }
}
