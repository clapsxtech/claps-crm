<?php
namespace App\Modules\Erp\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\CarbonImmutable;
class People
{
    public function priority(string $registeredOn, string $timezone, $today = null): string
    {
        $now = $today ? CarbonImmutable::parse($today,$timezone) : CarbonImmutable::now($timezone);
        $joined = CarbonImmutable::parse($registeredOn,$timezone);
        if ($joined->greaterThan($now->endOfDay())) return 'Future registration';
        $months = ($now->year-$joined->year)*12+$now->month-$joined->month;
        return $months === 0 ? 'Super Priority' : ($months === 1 ? 'High Priority' : 'Medium Priority');
    }
    public function student(int $unitId,int $actorId,array $data)
    {
        $v=Validator::make($data,['code'=>'required|string|max:100|unique:erp_students,code','name'=>'required|string|max:255','registered_on'=>'required|date_format:Y-m-d','timezone'=>'required|timezone','grade'=>'required|string|max:100','syllabus'=>'required|string|max:100','guardian_contact'=>'nullable|string|max:255','meeting_url'=>'nullable|url|max:255'])->validate();
        $unitTimezone=DB::table('erp_units')->where('id',$unitId)->value('timezone');
        abort_unless($unitTimezone && $v['registered_on'] <= CarbonImmutable::now($unitTimezone)->toDateString(),422,'Registration cannot be in the future in this business unit.');
        return DB::transaction(function()use($unitId,$actorId,$v){$id=DB::table('erp_students')->insertGetId($v+['unit_id'=>$unitId,'status'=>'active','created_at'=>now(),'updated_at'=>now()]);Audit::record($unitId,$actorId,'student.registered','student',$id,['code'=>$v['code']]);return DB::table('erp_students')->find($id);});
    }
    public function teacher(int $unitId,int $actorId,array $data)
    {
        $v=Validator::make($data,['code'=>'required|string|max:100|unique:erp_teachers,code','name'=>'required|string|max:255','timezone'=>'required|timezone','user_id'=>'nullable|integer|unique:erp_teachers,user_id'])->validate();
        if(!empty($v['user_id'])) { $user=\App\Models\User::find($v['user_id']); (new \App\Modules\IdentityAccess\Services\SiteAccessPolicy())->assertSiteMember($user); abort_unless(DB::table('erp_memberships')->where('unit_id',$unitId)->where('user_id',$user->id)->where('role','teacher')->exists(),422,'Provision teacher membership before binding account.'); }
        return DB::transaction(function()use($unitId,$actorId,$v){$id=DB::table('erp_teachers')->insertGetId($v+['status'=>'active','created_at'=>now(),'updated_at'=>now()]);DB::table('erp_teacher_units')->insert(['teacher_id'=>$id,'unit_id'=>$unitId,'created_at'=>now(),'updated_at'=>now()]);Audit::record($unitId,$actorId,'teacher.registered','teacher',$id,['code'=>$v['code']]);return DB::table('erp_teachers')->find($id);});
    }
    public function eligibility(int $unitId,int $actorId,array $data)
    {
        $v=Validator::make($data,['teacher_id'=>'required|integer','subject'=>'required|string|max:100','grade'=>'required|string|max:100','syllabus'=>'required|string|max:100'])->validate();
        abort_unless(DB::table('erp_teacher_units')->where('unit_id',$unitId)->where('teacher_id',$v['teacher_id'])->exists(),422,'Teacher must belong to this unit.');
        return DB::transaction(function()use($unitId,$actorId,$v){$subject=DB::table('erp_subjects')->where('name',$v['subject'])->first();$sid=$subject?$subject->id:DB::table('erp_subjects')->insertGetId(['name'=>$v['subject'],'created_at'=>now(),'updated_at'=>now()]);$key=['teacher_id'=>$v['teacher_id'],'subject_id'=>$sid,'grade'=>$v['grade'],'syllabus'=>$v['syllabus']];DB::table('erp_eligibilities')->updateOrInsert($key,['created_at'=>now(),'updated_at'=>now()]);Audit::record($unitId,$actorId,'teacher.eligibility','teacher',$v['teacher_id'],['subject_id'=>$sid]);return $key;});
    }
    public function task(int $unitId,int $actorId,array $data)
    {
        $v=Validator::make($data,['title'=>'required|string|max:255','notes'=>'nullable|string|max:4000','due_on'=>'nullable|date_format:Y-m-d','student_id'=>'nullable|integer','assigned_to'=>'nullable|integer'])->validate();
        if(!empty($v['student_id']))abort_unless(DB::table('erp_students')->where('unit_id',$unitId)->where('id',$v['student_id'])->exists(),422,'Student outside this unit.');
        if(!empty($v['assigned_to']))abort_unless(DB::table('erp_memberships')->where('unit_id',$unitId)->where('user_id',$v['assigned_to'])->exists(),422,'Assignee outside this unit.');
        return DB::transaction(function()use($unitId,$actorId,$v){$id=DB::table('erp_tasks')->insertGetId($v+['unit_id'=>$unitId,'created_by'=>$actorId,'status'=>'open','created_at'=>now(),'updated_at'=>now()]);Audit::record($unitId,$actorId,'task.created','task',$id,[]);return ['id'=>$id];});
    }
    public function completeTask(int $unitId,int $actorId,array $data)
    {
        return DB::transaction(function()use($unitId,$actorId,$data){$task=DB::table('erp_tasks')->where('unit_id',$unitId)->where('id',$data['task_id']??0)->lockForUpdate()->first();abort_unless($task,404);if($task->status!=='completed'){DB::table('erp_tasks')->where('id',$task->id)->update(['status'=>'completed','updated_at'=>now()]);Audit::record($unitId,$actorId,'task.completed','task',$task->id,[]);}return ['id'=>$task->id];});
    }
    public function mark(int $unitId,int $actorId,array $data)
    {
        $v=Validator::make($data,['enrollment_id'=>'required|integer','assessment'=>'required|string|max:255','assessed_on'=>'required|date_format:Y-m-d','score'=>'required|integer|min:0','maximum'=>'required|integer|min:1','notes'=>'nullable|string|max:4000'])->validate();
        abort_unless($v['score']<=$v['maximum'],422,'Score exceeds maximum.');abort_unless(DB::table('erp_enrollments')->where('unit_id',$unitId)->where('id',$v['enrollment_id'])->exists(),422,'Assignment outside this unit.');
        return DB::transaction(function()use($unitId,$actorId,$v){$id=DB::table('erp_marks')->insertGetId($v+['unit_id'=>$unitId,'created_by'=>$actorId,'created_at'=>now(),'updated_at'=>now()]);Audit::record($unitId,$actorId,'assessment.recorded','mark',$id,[]);return ['id'=>$id];});
    }
}
