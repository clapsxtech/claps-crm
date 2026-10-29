<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Modules\Erp\Services\{Admissions,MoneyInput,TeacherAllocation,TeamPermissions};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherAllocationController extends Controller
{
    public function index(Request $request,int $unit)
    {
        $service=new TeacherAllocation();$service->authorize($unit,$request->user());
        $p=$request->validate(['student_id'=>'nullable|integer|min:1','lead_id'=>'nullable|integer|min:1','demo_id'=>'nullable|integer|min:1','subject_id'=>'nullable|integer|min:1','prepare_teacher_id'=>'nullable|integer|min:1','teacher_q'=>'nullable|string|max:100','q'=>'nullable|string|max:100','search'=>'nullable|boolean','include_unverified'=>'nullable|boolean','date'=>'nullable|date_format:Y-m-d','time'=>'nullable|string|max:5','timezone'=>'nullable|timezone','duration_minutes'=>'nullable|integer|min:5|max:180','medium'=>'nullable|string|max:100','grade'=>'nullable|string|max:100','syllabus'=>'nullable|string|max:100']);
        $business=DB::table('erp_units')->find($unit);$permissions=new TeamPermissions();
        $context=(!empty($p['student_id'])||!empty($p['lead_id']))?$service->context($unit,$request->user(),$p):null;
        $leads=(new Admissions())->visibleLeads($unit,$request->user()->id,$p['q']??null)->whereIn('l.status',['LEAD','JOINED'])->limit(30)->get(['l.id','l.name']);
        if($context?->lead_id && !$leads->contains('id',$context->lead_id))$leads->push((object)['id'=>$context->lead_id,'name'=>$context->name]);
        $subjects=DB::table('erp_subjects')->orderBy('name')->get(['id','name']);
        $matching=!empty($p['search'])?$service->matches($unit,$request->user(),$p):null;
        $demos=$context?->lead_id?DB::table('erp_demos as d')->leftJoin('erp_teachers as t','t.id','=','d.teacher_id')->where('d.unit_id',$unit)->where('d.lead_id',$context->lead_id)->whereNotNull('d.starts_at')->select('d.*','t.name as teacher_name')->orderByDesc('d.starts_at')->limit(30)->get():collect();
        $seedDemo=!empty($p['demo_id'])?$demos->firstWhere('id',(int)$p['demo_id']):null;
        $seed=$p+['subject_id'=>$seedDemo?->subject_id,'medium'=>$seedDemo?->matching_medium,'timezone'=>$seedDemo?->timezone??$context?->timezone??$business->timezone,'duration_minutes'=>$seedDemo?->duration_minutes??60,'grade'=>$context?->grade,'syllabus'=>$context?->syllabus];
        $handoff=$context ? DB::table('erp_leads')->where('unit_id',$unit)->where($context->lead_id ? 'id' : 'student_id',$context->lead_id ?: $context->student_id)->select('learning_requirement','concern','about_student','about_parent')->orderByDesc('id')->first() : null;
        $teacherDemoStats=$matching ? DB::table('erp_demos')->where('unit_id',$unit)->whereIn('teacher_id',$matching['teachers']->pluck('id')->all())
            ->select('teacher_id')->selectRaw("COUNT(*) AS booked, SUM(CASE WHEN status IN ('ATTENDED','JOINED') THEN 1 ELSE 0 END) AS attended, SUM(CASE WHEN enrollment_id IS NOT NULL THEN 1 ELSE 0 END) AS allocated")
            ->groupBy('teacher_id')->get()->keyBy('teacher_id') : collect();
        $prepared=null;$profile=null;$windows=collect();$eligibilities=collect();
        if(!empty($p['prepare_teacher_id']) && $permissions->allows($request->user(),$unit,'action','allocation-qualify')){
            $prepared=DB::table('erp_teachers as t')->join('erp_teacher_units as u','u.teacher_id','=','t.id')->where('u.unit_id',$unit)->where('t.id',$p['prepare_teacher_id'])->select('t.*')->first();abort_unless($prepared,404);
            $profile=DB::table('erp_teacher_matching_profiles')->where('teacher_id',$prepared->id)->first();
            $windows=DB::table('erp_availability')->where('unit_id',$unit)->where('teacher_id',$prepared->id)->orderBy('weekday')->get();
            $eligibilities=DB::table('erp_eligibilities as e')->join('erp_subjects as s','s.id','=','e.subject_id')->where('e.teacher_id',$prepared->id)->select('e.grade','e.syllabus','s.name')->get();
        }
        return view('erp.teacher-allocation',compact('business','permissions','context','leads','subjects','matching','demos','seed','prepared','profile','windows','eligibilities','handoff','teacherDemoStats'));
    }
    public function mutate(Request $request,int $unit,string $action)
    {
        $service=new TeacherAllocation();$p=$request->except('_token','return_page');
        $service->authorize($unit,$request->user(),$action);
        $method=match($action){'allocation-qualify'=>'qualify','allocation-window'=>'window','allocation-demo'=>'bookDemo','allocation-feedback'=>'feedback','allocation-regular'=>'regular',default=>null};abort_unless($method,404);
        if($action==='allocation-regular')$p=MoneyInput::forAction($p,'assign');
        $result=$service->$method($unit,$request->user(),$p);
        if($request->expectsJson())return response()->json(['result'=>$result]);
        $request->validate(['return_filters'=>'nullable|array','return_filters.*'=>'nullable|string|max:100']);
        $query=array_intersect_key($request->input('return_filters',[]),array_flip(['student_id','lead_id','demo_id','subject_id','date','time','timezone','duration_minutes','medium','grade','syllabus','teacher_q','search','include_unverified']));
        if(empty($query['student_id']) && empty($query['lead_id']))foreach(['student_id','lead_id'] as $key)if(!empty($p[$key]))$query[$key]=$p[$key];
        if(in_array($action,['allocation-qualify','allocation-window'],true)&&!empty($p['teacher_id']))$query['prepare_teacher_id']=$p['teacher_id'];
        if($action==='allocation-demo')$query['lead_id']=$result->lead_id;
        if($action==='allocation-feedback')$query['lead_id']=$result->lead_id;
        if($action==='allocation-regular')return redirect()->route('erp.business',[$unit,'timetable'])->with('status','Regular assignment confirmed and dated lessons created.');
        return redirect()->route('erp.business',[$unit,'allocation']+$query)->with('status','Allocation record saved and audited.');
    }
}
