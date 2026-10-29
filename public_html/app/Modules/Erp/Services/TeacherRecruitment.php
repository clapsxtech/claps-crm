<?php

namespace App\Modules\Erp\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Recruitment is a shared site teacher pool, linked to canonical ERP teachers. */
class TeacherRecruitment
{
    public const STAGES = ['APPLIED','CONTACTED','SELECTED','REGISTERED','APPROVED','REJECTED','REVIEW','MERGED'];
    public static function phone(?string $value): ?string
    {
        $p = preg_replace('/\D/', '', $value ?? '');
        if (strlen($p) === 10) $p = '91'.$p;
        if (str_starts_with($p, '00')) $p = substr($p, 2);
        return strlen($p) >= 10 && strlen($p) <= 15 ? $p : null;
    }
    public static function name(string $value): string
    {
        return mb_strtolower(preg_replace('/[^\pL\pN]/u', '', $value));
    }
    public function canManage(User $user, int $unit): bool
    {
        return in_array((new TeamPermissions())->role($user, $unit), ['superadmin','admin','staff','team_manager'], true);
    }
    public function candidate(int $id)
    {
        return DB::table('erp_teacher_candidates')->where('business_id', config('erp.business_id'))->where('id', $id)->firstOrFail();
    }
    public function mutate(int $unit, User $actor, string $action, array $data)
    {
        (new Access())->unit($actor, $unit);
        abort_unless($this->canManage($actor, $unit), 403);
        $v = Validator::make($data, [
            'candidate_id' => 'nullable|integer|min:1', 'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:40', 'email' => 'nullable|email|max:255',
            'qualification' => 'nullable|string|max:4000', 'subjects' => 'nullable|string|max:4000',
            'grades' => 'nullable|string|max:4000', 'syllabuses' => 'nullable|string|max:4000',
            'medium' => 'nullable|string|max:1000', 'availability' => 'nullable|string|max:4000',
            'cv_url' => 'nullable|url:http,https|max:2000', 'note' => 'nullable|string|max:4000',
            'next_follow_up_on' => 'nullable|date_format:Y-m-d', 'teacher_code' => 'nullable|string|max:100',
            'stage' => 'nullable|in:APPLIED,CONTACTED,SELECTED,REJECTED',
        ])->validate();
        return DB::transaction(function () use ($unit, $actor, $action, $v) {
            if ($action === 'teacher-candidate-create') {
                if (empty($v['name']) || !self::phone($v['phone'] ?? null)) throw ValidationException::withMessages(['phone'=>'A name and valid contact number are required.']);
                $key = hash('sha256', self::phone($v['phone']).':'.self::name($v['name']));
                $existing = DB::table('erp_teacher_candidates')->where('business_id', config('erp.business_id'))->where('identity_key', $key)->first();
                if ($existing) return $existing->merged_into_id ? $this->candidate($existing->merged_into_id) : $existing;
                $id = DB::table('erp_teacher_candidates')->insertGetId([
                    'business_id'=>config('erp.business_id'), 'identity_key'=>$key, 'name'=>$v['name'],
                    'phone'=>self::phone($v['phone']), 'stage'=>'APPLIED', 'created_at'=>now(),'updated_at'=>now(),
                ]);
            } else $id = (int) ($v['candidate_id'] ?? 0);
            $c = $this->candidate($id);
            abort_if($c->merged_into_id,422,'This profile was merged. Open its surviving profile.');
            $update = [];
            if (in_array($action, ['teacher-candidate-create','teacher-candidate-update','teacher-candidate-register'], true)) {
                foreach (['email','qualification','subjects','grades','syllabuses','medium','availability','cv_url'] as $field) if (array_key_exists($field,$v)) $update[$field]=$v[$field];
                if ($action === 'teacher-candidate-update' && !empty($v['stage'])) {
                    abort_if($c->stage==='REVIEW',422,'Resolve this identity through data review before changing its stage.');
                    abort_if(in_array($c->stage,['REGISTERED','APPROVED'],true),422,'Registered profiles cannot return to the application queue.');
                    $update['stage']=$v['stage'];
                }
                if ($action === 'teacher-candidate-register') {
                    abort_unless(in_array($c->stage,['SELECTED','REGISTERED'],true),422,'Select the teacher before recording registration.');
                    $update += ['stage'=>'REGISTERED','registered_at'=>$c->registered_at ?? now()];
                }
            } elseif ($action === 'teacher-candidate-approve') {
                abort_unless(in_array($c->stage,['REGISTERED','APPROVED'],true),422,'Registration must be completed first.');
                $code = trim($v['teacher_code'] ?? $c->teacher_code ?? '');
                if (!$code) throw ValidationException::withMessages(['teacher_code'=>'Enter the canonical teacher code.']);
                $teacher = $c->teacher_id ? DB::table('erp_teachers')->find($c->teacher_id) : DB::table('erp_teachers')->where('code',$code)->first();
                if ($teacher) {
                    abort_unless($teacher->code === $code && self::name($teacher->name) === self::name($c->name),422,'Teacher identity differs. Resolve the match before approving.');
                    $other = DB::table('erp_teacher_candidates')->where('teacher_id',$teacher->id)->where('id','!=',$id)->exists();
                    abort_if($other,422,'Another recruitment profile already links to this teacher.');
                    // Approval of a profile never silently reactivates a stopped teacher.
                } else {
                    $tid=DB::table('erp_teachers')->insertGetId(['code'=>$code,'name'=>$c->name,'timezone'=>DB::table('erp_units')->where('id',$unit)->value('timezone'),'status'=>'active','created_at'=>now(),'updated_at'=>now()]);
                    $teacher=DB::table('erp_teachers')->find($tid);
                }
                $membership=DB::table('erp_teacher_units')->where('teacher_id',$teacher->id)->first();
                abort_if($membership && (int)$membership->unit_id !== $unit,422,'This teacher belongs to a different unit. Open that unit to approve the profile.');
                if (!$membership) DB::table('erp_teacher_units')->insert(['teacher_id'=>$teacher->id,'unit_id'=>$unit,'created_at'=>now(),'updated_at'=>now()]);
                $update=['teacher_id'=>$teacher->id,'teacher_code'=>$teacher->code,'stage'=>'APPROVED'];
            } elseif ($action !== 'teacher-candidate-contact') abort(404);
            if ($action === 'teacher-candidate-contact' && $c->stage === 'APPLIED') $update['stage']='CONTACTED';
            if ($update) DB::table('erp_teacher_candidates')->where('id',$id)->update($update+['updated_at'=>now()]);
            DB::table('erp_teacher_candidate_activities')->insert(['candidate_id'=>$id,'actor_id'=>$actor->id,'kind'=>$action,'note'=>$v['note'] ?? 'Profile updated','next_follow_up_on'=>$v['next_follow_up_on']??null,'created_at'=>now(),'updated_at'=>now()]);
            Audit::record($unit,$actor->id,$action,'teacher_candidate',$id,['teacher_id'=>$update['teacher_id']??$c->teacher_id]);
            return $this->candidate($id);
        });
    }
}
