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
        $teacher=DB::table('erp_teachers')->where('user_id',$user->id)->where('status','active')->first(['id','name','timezone']);
        if (!$teacher) throw new AuthorizationException('An active, explicitly linked teacher account is required.');
        $pools=DB::table('erp_teacher_units')->where('teacher_id',$teacher->id)->get();
        if ($pools->count() !== 1) throw new AuthorizationException('Teacher must belong to exactly one unit pool.');
        $unitId=(int)$pools->first()->unit_id;
        if (!DB::table('erp_memberships')->where('user_id',$user->id)->where('unit_id',$unitId)->where('role','teacher')->exists()) throw new AuthorizationException('Explicit teacher membership in this unit is required.');
        return [$teacher,$unitId,(int)$user->id];
    }

    public function index(Request $request)
    {
        [$teacher,$unitId]=$this->identity($request);
        $filters=Validator::make($request->only(['from','through']),['from'=>'nullable|date_format:Y-m-d','through'=>'nullable|date_format:Y-m-d'])->validate();
        $from=$filters['from'] ?? gmdate('Y-m-d',time()-30*86400);
        $through=$filters['through'] ?? gmdate('Y-m-d',time()+90*86400);
        if ($through < $from || (strtotime($through)-strtotime($from)) > 366*86400) throw \Illuminate\Validation\ValidationException::withMessages(['through'=>'Choose a date range of at most 366 days.']);
        $lessons=DB::table('erp_lessons as l')
            ->join('erp_students as s',function($join) { $join->on('s.id','=','l.student_id')->on('s.unit_id','=','l.unit_id'); })
            ->join('erp_subjects as subject','subject.id','=','l.subject_id')
            ->leftJoin('erp_attendance as a',function($join) { $join->on('a.lesson_id','=','l.id')->on('a.unit_id','=','l.unit_id'); })
            ->where('l.unit_id',$unitId)->where('l.teacher_id',$teacher->id)
            ->where('l.starts_at','>=',$from.' 00:00:00')->where('l.starts_at','<=',$through.' 23:59:59')
            ->orderBy('l.starts_at')->limit(501)
            ->get(['l.id','l.starts_at','l.ends_at','l.status','l.kind','s.name as student_name','s.code as student_code','subject.name as subject_name','a.id as attendance_id','a.status as attendance_status','a.actual_minutes']);
        $truncated=$lessons->count()>500; $lessons=$lessons->take(500);
        return view('erp.teacher',compact('teacher','lessons','from','through','truncated'));
    }

    public function submit(Request $request)
    {
        [$teacher,$unitId,$actorId]=$this->identity($request);
        $payload=Validator::make($request->only(['lesson_id','actual_minutes']),['lesson_id'=>'required|integer|min:1','actual_minutes'=>'required|integer|min:1|max:480'])->validate();
        DB::transaction(function() use ($unitId,$actorId,$teacher,$payload,$request) {
            // Stable teacher lock also serializes scheduling and explicit pool changes.
            DB::table('erp_teachers')->where('id',$teacher->id)->lockForUpdate()->first();
            [$current,$currentUnit]=$this->identity($request);
            if ($current->id !== $teacher->id || $currentUnit !== $unitId || !DB::table('erp_lessons')->where('id',$payload['lesson_id'])->where('unit_id',$unitId)->where('teacher_id',$teacher->id)->exists()) throw new AuthorizationException('Only your own dated lessons in your unit can receive your attendance.');
            (new Attendance())->submit($unitId,$actorId,$payload);
        }, 3);
        return redirect()->route('erp.teacher.index')->with('status','Attendance submitted. Financial records remain unchanged until an administrator imports the attendance batch.');
    }
}
