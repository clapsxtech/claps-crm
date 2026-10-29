<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Modules\Erp\Services\{Access,TeacherRecruitment,TeamPermissions};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherRecruitmentController extends Controller
{
    public function index(Request $request,int $unit)
    {
        (new Access())->unit($request->user(),$unit,false);
        abort_unless((new TeamPermissions())->allows($request->user(),$unit,'page','teacher-recruitment'),403);
        $filters=$request->validate(['q'=>'nullable|string|max:100','stage'=>'nullable|in:'.implode(',',TeacherRecruitment::STAGES),'subject'=>'nullable|string|max:100','syllabus'=>'nullable|string|max:100','medium'=>'nullable|string|max:100','availability'=>'nullable|string|max:100','candidate'=>'nullable|integer|min:1']);
        $business=DB::table('erp_units')->find($unit);
        $manage=(new TeacherRecruitment())->canManage($request->user(),$unit);
        $query=DB::table('erp_teacher_candidates as c')->leftJoin('erp_teachers as t','t.id','=','c.teacher_id')->where('c.business_id',config('erp.business_id'));
        // The registered directory is shared for class/demo discovery. Applications
        // and review conflicts are available only to managers.
        if (!$manage) $query->whereIn('c.stage',['REGISTERED','APPROVED']);
        if(empty($filters['stage']))$query->whereNull('c.merged_into_id');
        if (!empty($filters['stage'])) $query->where('c.stage',$filters['stage']);
        if (!empty($filters['q'])) $query->where(function($q)use($filters){foreach(['c.name','c.phone','c.teacher_code'] as $field)$q->orWhere($field,'like','%'.$filters['q'].'%');});
        foreach (['subject'=>'subjects','syllabus'=>'syllabuses','medium'=>'medium','availability'=>'availability'] as $filter=>$field) if (!empty($filters[$filter])) $query->where('c.'.$field,'like','%'.$filters[$filter].'%');
        $candidates=$query->select('c.*','t.status as operational_status')->orderBy('c.name')->paginate(25)->withQueryString();
        $selected=null;$activities=collect();$sources=collect();$duplicates=collect();
        if ($manage && !empty($filters['candidate'])) {
            $selected=(new TeacherRecruitment())->candidate($filters['candidate']);
            if($selected->phone || $selected->teacher_code)$duplicates=DB::table('erp_teacher_candidates')->where('business_id',config('erp.business_id'))->whereNull('merged_into_id')->where('id','!=',$selected->id)->where(function($q)use($selected){if($selected->phone)$q->orWhere('phone',$selected->phone);if($selected->teacher_code)$q->orWhere('teacher_code',$selected->teacher_code);})->limit(20)->get(['id','name','phone','teacher_code','stage']);
            $ids=DB::table('erp_teacher_candidates')->where('business_id',config('erp.business_id'))->where('merged_into_id',$selected->id)->pluck('id')->push($selected->id)->all();
            $activities=DB::table('erp_teacher_candidate_activities')->whereIn('candidate_id',$ids)->orderByDesc('id')->limit(30)->get();
            $sources=DB::table('erp_teacher_candidate_sources')->whereIn('candidate_id',$ids)->select('kind','source_key','payload','created_at')->limit(30)->get();
        }
        return view('erp.teacher-recruitment',compact('business','manage','candidates','selected','activities','sources','duplicates'));
    }

    public function mutate(Request $request,int $unit,string $action)
    {
        abort_unless(in_array($action,['teacher-candidate-create','teacher-candidate-update','teacher-candidate-contact','teacher-candidate-register','teacher-candidate-approve','teacher-candidate-resolve'],true),404);
        $result=$action==='teacher-candidate-resolve'
            ? (new \App\Modules\Erp\Services\TeacherDataReview())->resolve($unit,$request->user(),$request->except('_token'))
            : (new TeacherRecruitment())->mutate($unit,$request->user(),$action,$request->except('_token'));
        if ($request->expectsJson()) return response()->json(['result'=>$result]);
        return redirect()->route('erp.business',[$unit,'teacher-recruitment','candidate'=>$result->id])->with('status','Teacher profile saved.');
    }
}
