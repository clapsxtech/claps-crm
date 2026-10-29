<?php

namespace App\Modules\Erp\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TeacherDataReview
{
    public function resolve(int $unit,User $actor,array $payload): object
    {
        (new Access())->unit($actor,$unit);
        abort_unless((new TeacherRecruitment())->canManage($actor,$unit),403);
        $v=Validator::make($payload,[
            'candidate_id'=>'required|integer|min:1','decision'=>'required|in:correct,merge',
            'target_id'=>'required_if:decision,merge|nullable|integer|min:1',
            'name'=>'required_if:decision,correct|nullable|string|max:255',
            'phone'=>'required_if:decision,correct|nullable|string|max:40',
            'teacher_code'=>'nullable|string|max:100','note'=>'required|string|max:4000',
        ])->validate();
        return DB::transaction(function()use($v,$unit,$actor){
            $c=DB::table('erp_teacher_candidates')->where('business_id',config('erp.business_id'))->where('id',$v['candidate_id'])->lockForUpdate()->first();abort_unless($c,404);
            abort_if($c->merged_into_id,422,'This profile is already merged. Open its surviving profile.');
            $before=['name'=>$c->name,'phone'=>$c->phone,'teacher_code'=>$c->teacher_code,'teacher_id'=>$c->teacher_id,'stage'=>$c->stage];
            if($v['decision']==='merge'){
                $target=DB::table('erp_teacher_candidates')->where('business_id',config('erp.business_id'))->where('id',$v['target_id'])->lockForUpdate()->first();abort_unless($target,404);
                abort_if($target->id===$c->id || $target->merged_into_id,422,'Choose a different, surviving profile.');
                abort_if(DB::table('erp_teacher_candidates')->where('merged_into_id',$c->id)->exists(),422,'This profile already owns merged history. Merge the other profile into this one instead.');
                abort_if($c->teacher_id && $target->teacher_id && (int)$c->teacher_id!==(int)$target->teacher_id,422,'Distinct operational teachers cannot be merged here.');
                abort_if($c->teacher_id && TeacherRecruitment::name($c->name)!==TeacherRecruitment::name($target->name),422,'Correct the identity before linking an operational teacher to a different name.');
                if($c->teacher_id && !$target->teacher_id) DB::table('erp_teacher_candidates')->where('id',$target->id)->update(['teacher_id'=>$c->teacher_id,'teacher_code'=>$c->teacher_code,'updated_at'=>now()]);
                if($c->registered_at && !$target->registered_at) DB::table('erp_teacher_candidates')->where('id',$target->id)->update(['registered_at'=>$c->registered_at,'stage'=>$target->stage==='APPLIED'?'REGISTERED':$target->stage,'updated_at'=>now()]);
                DB::table('erp_teacher_candidates')->where('id',$c->id)->update(['merged_into_id'=>$target->id,'teacher_id'=>null,'stage'=>'MERGED','reviewed_by'=>$actor->id,'reviewed_at'=>now(),'updated_at'=>now()]);
                $id=$target->id;
            }else{
                $phone=TeacherRecruitment::phone($v['phone']);$name=trim($v['name']);
                if(!$phone || !TeacherRecruitment::name($name))throw ValidationException::withMessages(['phone'=>'Enter a valid name and phone number.']);
                $identity=hash('sha256',$phone.':'.TeacherRecruitment::name($name));
                if(DB::table('erp_teacher_candidates')->where('business_id',$c->business_id)->where('identity_key',$identity)->where('id','!=',$c->id)->exists())throw ValidationException::withMessages(['phone'=>'That identity already exists. Review and merge the profiles instead.']);
                $code=trim($v['teacher_code']??'') ?: null;
                $teacher=$c->teacher_id?DB::table('erp_teachers')->find($c->teacher_id):($code?DB::table('erp_teachers')->where('code',$code)->first():null);
                if($teacher){
                    abort_unless($teacher->code===$code && TeacherRecruitment::name($teacher->name)===TeacherRecruitment::name($name),422,'The corrected identity must match the existing teacher code and name.');
                    abort_if(DB::table('erp_teacher_candidates')->where('teacher_id',$teacher->id)->whereNull('merged_into_id')->where('id','!=',$c->id)->exists(),422,'This operational teacher is already linked. Merge the duplicate profile instead.');
                }
                DB::table('erp_teacher_candidates')->where('id',$c->id)->update(['name'=>$name,'phone'=>$phone,'identity_key'=>$identity,'teacher_code'=>$code,'teacher_id'=>$teacher?->id,
                    'stage'=>$c->stage==='REVIEW'?($c->registered_at?'REGISTERED':'APPLIED'):$c->stage,'reviewed_at'=>now(),'reviewed_by'=>$actor->id,'updated_at'=>now()]);
                $id=$c->id;
            }
            DB::table('erp_teacher_candidate_activities')->insert(['candidate_id'=>$id,'actor_id'=>$actor->id,'kind'=>'identity_'.$v['decision'],'note'=>trim($v['note']),'created_at'=>now(),'updated_at'=>now()]);
            Audit::record($unit,$actor->id,'teacher.identity_'.$v['decision'],'teacher_candidate',$c->id,['before'=>$before,'surviving_candidate_id'=>$id,'note'=>$v['note']]);
            return (new TeacherRecruitment())->candidate($id);
        });
    }
}
