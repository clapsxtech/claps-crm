<?php
namespace App\Modules\Erp\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Services\SiteAccessPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Administrative, unit-scoped operations only. No account creation or outbound communication. */
class Operations
{
    private function invalid(string $message): void { throw ValidationException::withMessages(['operations'=>$message]); }
    private function now(): string { return gmdate('Y-m-d H:i:s'); }
    private function actor(int $unitId,int $actorId) {
        // Serialize unit administration and membership changes on a stable unit row first.
        $unit=DB::table('erp_units')->where('id',$unitId)->lockForUpdate()->first();
        if (!$unit) $this->invalid('Unknown business unit.');
        $user=User::where('id',$actorId)->lockForUpdate()->first();
        (new SiteAccessPolicy())->assertSiteMember($user);
        if (!DB::table('erp_memberships')->where('unit_id',$unitId)->where('user_id',$actorId)->where('role','admin')->lockForUpdate()->first()) throw new AuthorizationException('Current unit administrator membership required.');
        return $unit;
    }
    private function target(int $userId): User {
        $user=User::where('id',$userId)->lockForUpdate()->first();
        (new SiteAccessPolicy())->assertSiteMember($user); return $user;
    }
    private function row(string $table,int $id,int $unitId) {
        $row=DB::table($table)->where('id',$id)->where('unit_id',$unitId)->lockForUpdate()->first();
        if (!$row) $this->invalid('Record not found in this business unit.'); return $row;
    }
    private function audit(int $unitId,int $actorId,string $action,string $type,int $id,array $data=[]): void { Audit::record($unitId,$actorId,$action,$type,$id,$data); }
    private function changed($row,array $values): array {
        $changes=[]; foreach ($values as $key=>$value) if (($row->$key ?? null) !== $value) $changes[$key]=['from'=>$row->$key ?? null,'to'=>$value]; return $changes;
    }
    public function updateStudent(int $unitId,int $actorId,array $payload) {
        $v=Validator::make($payload,['student_id'=>'required|integer|min:1','name'=>'sometimes|required|string|max:255','registered_on'=>'sometimes|required|date_format:Y-m-d','timezone'=>'sometimes|required|timezone','status'=>'sometimes|required|in:active,paused,stopped','grade'=>'sometimes|required|string|max:100','syllabus'=>'sometimes|required|string|max:100','guardian_contact'=>'sometimes|nullable|string|max:255','meeting_url'=>'sometimes|nullable|url|max:255'])->validate();
        return DB::transaction(function() use ($unitId,$actorId,$v) {
            $unit=$this->actor($unitId,$actorId); $student=$this->row('erp_students',(int)$v['student_id'],$unitId); $values=$v; unset($values['student_id']);
            if (!$values) $this->invalid('Provide at least one profile field to update.');
            if (isset($values['registered_on']) && $values['registered_on']>CarbonImmutable::now($unit->timezone)->toDateString()) $this->invalid('Registration cannot be in the future in this unit.');
            if (isset($values['meeting_url']) && !preg_match('~^https?://~i',$values['meeting_url'])) $this->invalid('Meeting links must use HTTP or HTTPS.');
            if (isset($values['timezone']) && $values['timezone']!==$student->timezone) {
                foreach (['erp_enrollments','erp_availability','erp_exceptions'] as $table) if (DB::table($table)->where('student_id',$student->id)->lockForUpdate()->first()) $this->invalid('Timezone cannot change after scheduling records exist; preserve historical local-date interpretation.');
            }
            $grade=$values['grade'] ?? $student->grade; $syllabus=$values['syllabus'] ?? $student->syllabus;
            if ($grade!==$student->grade || $syllabus!==$student->syllabus) {
                $today=CarbonImmutable::now($student->timezone)->toDateString();
                $assignments=DB::table('erp_enrollments')->where('student_id',$student->id)->where('status','active')->where(function($q) use ($today) { $q->whereNull('ends_on')->orWhere('ends_on','>=',$today); })->lockForUpdate()->get();
                foreach ($assignments as $e) if (!DB::table('erp_eligibilities')->where('teacher_id',$e->teacher_id)->where('subject_id',$e->subject_id)->where('grade',$grade)->where('syllabus',$syllabus)->lockForUpdate()->first()) $this->invalid('An active assignment teacher is not eligible for the new grade and syllabus. Resolve the assignment first.');
            }
            if (isset($values['status']) && $values['status']!=='active' && DB::table('erp_lessons')->where('student_id',$student->id)->where('status','scheduled')->where('ends_at','>',$this->now())->lockForUpdate()->first()) $this->invalid('Explicitly cancel or resolve future scheduled lessons before pausing or stopping the student.');
            $changes=$this->changed($student,$values);
            if ($changes) { DB::table('erp_students')->where('id',$student->id)->update($values+['updated_at'=>$this->now()]); $this->audit($unitId,$actorId,'student.updated','student',$student->id,['changes'=>$changes]); }
            return $this->row('erp_students',$student->id,$unitId);
        });
    }
    public function updateTask(int $unitId,int $actorId,array $payload) {
        $v=Validator::make($payload,['task_id'=>'required|integer|min:1','title'=>'sometimes|required|string|max:255','notes'=>'sometimes|nullable|string|max:4000','student_id'=>'sometimes|nullable|integer|min:1','assigned_to'=>'sometimes|nullable|integer|min:1','due_on'=>'sometimes|nullable|date_format:Y-m-d','status'=>'sometimes|required|in:open,in_progress,completed'])->validate();
        return DB::transaction(function() use ($unitId,$actorId,$v) {
            $this->actor($unitId,$actorId); $task=$this->row('erp_tasks',(int)$v['task_id'],$unitId); $values=$v; unset($values['task_id']);
            if (!$values) $this->invalid('Provide at least one task field to update.');
            if (!empty($values['student_id'])) $this->row('erp_students',(int)$values['student_id'],$unitId);
            if (!empty($values['assigned_to'])) { $this->target((int)$values['assigned_to']); if (!DB::table('erp_memberships')->where('unit_id',$unitId)->where('user_id',$values['assigned_to'])->lockForUpdate()->first()) $this->invalid('Task assignee must be a current member of this unit.'); }
            if (($values['status'] ?? null)==='completed' && DB::table('erp_task_items')->where('unit_id',$unitId)->where('task_id',$task->id)->whereNull('completed_at')->lockForUpdate()->first()) $this->invalid('Complete every checklist item before completing this task.');
            $changes=$this->changed($task,$values);
            if ($changes) { DB::table('erp_tasks')->where('id',$task->id)->update($values+['updated_at'=>$this->now()]); $this->audit($unitId,$actorId,isset($changes['status']) && $values['status']==='completed' ? 'task.completed' : 'task.updated','task',$task->id,['changes'=>$changes]); }
            return $this->row('erp_tasks',$task->id,$unitId);
        });
    }
    public function addTaskItem(int $unitId,int $actorId,array $payload) {
        $v=Validator::make($payload,['task_id'=>'required|integer|min:1','title'=>'required|string|max:255'])->validate();
        return DB::transaction(function() use ($unitId,$actorId,$v) {
            $this->actor($unitId,$actorId); $task=$this->row('erp_tasks',(int)$v['task_id'],$unitId);
            if ($task->status==='completed') $this->invalid('Reopen the task before adding checklist items.');
            if (!trim($v['title'])) $this->invalid('Checklist title cannot be blank.');
            $id=DB::table('erp_task_items')->insertGetId(['unit_id'=>$unitId,'task_id'=>$task->id,'title'=>trim($v['title']),'created_at'=>$this->now(),'updated_at'=>$this->now()]);
            $this->audit($unitId,$actorId,'task.item_added','task_item',$id,['task_id'=>$task->id,'title'=>trim($v['title'])]); return $this->row('erp_task_items',$id,$unitId);
        });
    }
    public function completeTaskItem(int $unitId,int $actorId,array $payload) {
        $v=Validator::make($payload,['item_id'=>'required|integer|min:1'])->validate();
        return DB::transaction(function() use ($unitId,$actorId,$v) {
            $this->actor($unitId,$actorId); $item=$this->row('erp_task_items',(int)$v['item_id'],$unitId); $this->row('erp_tasks',$item->task_id,$unitId);
            if (!$item->completed_at) { DB::table('erp_task_items')->where('id',$item->id)->update(['completed_at'=>$this->now(),'completed_by'=>$actorId,'updated_at'=>$this->now()]); $this->audit($unitId,$actorId,'task.item_completed','task_item',$item->id,['task_id'=>$item->task_id]); }
            return $this->row('erp_task_items',$item->id,$unitId);
        });
    }
    public function recordFollowup(int $unitId,int $actorId,array $payload) {
        $v=Validator::make($payload,['student_id'=>'required|integer|min:1','contact_channel'=>'required|in:whatsapp,call','note'=>'required|string|max:4000','outcome'=>'required|string|max:1000','next_due_on'=>'nullable|date_format:Y-m-d'])->validate();
        return DB::transaction(function() use ($unitId,$actorId,$v) {
            $this->actor($unitId,$actorId); $this->row('erp_students',(int)$v['student_id'],$unitId);
            if (!trim($v['note']) || !trim($v['outcome'])) $this->invalid('Conversation note and outcome cannot be blank.');
            $id=DB::table('erp_followups')->insertGetId($v+['unit_id'=>$unitId,'created_by'=>$actorId,'created_at'=>$this->now(),'updated_at'=>$this->now()]);
            $this->audit($unitId,$actorId,'followup.recorded','followup',$id,['student_id'=>(int)$v['student_id'],'contact_channel'=>$v['contact_channel'],'next_due_on'=>$v['next_due_on'] ?? null,'outbound_sent'=>false]); return $this->row('erp_followups',$id,$unitId);
        });
    }
    private function protectTeacher(int $unitId,int $userId,?string $role): void {
        $links=DB::table('erp_teachers')->where('user_id',$userId)->lockForUpdate()->get();
        foreach ($links as $teacher) {
            if ($teacher->status!=='active' && $role!=='teacher') continue;
            $pools=DB::table('erp_teacher_units')->where('teacher_id',$teacher->id)->lockForUpdate()->get();
            if ($pools->count()!==1 || (int)$pools->first()->unit_id!==$unitId || $role!=='teacher') $this->invalid('An active teacher account link must be explicitly resolved before changing or removing its teacher membership.');
        }
        $others=DB::table('erp_memberships')->where('user_id',$userId)->where('unit_id','<>',$unitId)->lockForUpdate()->get();
        if ($role==='teacher' && $others->isNotEmpty()) $this->invalid('A teacher account cannot belong to more than one business unit.');
        if ($role!==null && $others->contains('role','teacher')) $this->invalid('This account already belongs to another teacher unit pool.');
    }
    public function grantMembership(int $unitId,int $actorId,array $payload) {
        $v=Validator::make($payload,['user_id'=>'required|integer|min:1','role'=>'required|in:admin,sales,teacher'])->validate();
        return DB::transaction(function() use ($unitId,$actorId,$v) {
            $this->actor($unitId,$actorId); $user=$this->target((int)$v['user_id']);
            $existing=DB::table('erp_memberships')->where('unit_id',$unitId)->where('user_id',$user->id)->lockForUpdate()->first();
            if ((int)$user->id===$actorId && (!$existing || $existing->role!==$v['role'])) $this->invalid('Administrators cannot change their own membership role.');
            $this->protectTeacher($unitId,(int)$user->id,$v['role']);
            if ($existing && $existing->role==='admin' && $v['role']!=='admin' && DB::table('erp_memberships')->where('unit_id',$unitId)->where('role','admin')->lockForUpdate()->get()->count()<=1) $this->invalid('The last unit administrator cannot be removed or demoted.');
            if ($existing && $existing->role===$v['role']) return $existing;
            $before=$existing ? $existing->role : null;
            if ($existing) { DB::table('erp_memberships')->where('id',$existing->id)->update(['role'=>$v['role'],'updated_at'=>$this->now()]); $id=$existing->id; }
            else $id=DB::table('erp_memberships')->insertGetId(['unit_id'=>$unitId,'user_id'=>$user->id,'role'=>$v['role'],'created_at'=>$this->now(),'updated_at'=>$this->now()]);
            $this->audit($unitId,$actorId,'membership.granted','membership',$id,['user_id'=>(int)$user->id,'previous_role'=>$before,'role'=>$v['role']]); return $this->row('erp_memberships',$id,$unitId);
        });
    }
    public function linkTeacherUser(int $unitId,int $actorId,array $payload) {
        $v=Validator::make($payload,['teacher_id'=>'required|integer|min:1','user_id'=>'required|integer|min:1'])->validate();
        return DB::transaction(function() use ($unitId,$actorId,$v) {
            $this->actor($unitId,$actorId); $user=$this->target((int)$v['user_id']);
            $teacher=DB::table('erp_teachers')->where('id',$v['teacher_id'])->lockForUpdate()->first();
            if (!$teacher || $teacher->status!=='active') $this->invalid('Choose an active teacher from this unit.');
            $pools=DB::table('erp_teacher_units')->where('teacher_id',$teacher->id)->lockForUpdate()->get();
            if ($pools->count()!==1 || (int)$pools->first()->unit_id!==$unitId) $this->invalid('Teacher must belong only to this unit pool.');
            $memberships=DB::table('erp_memberships')->where('user_id',$user->id)->lockForUpdate()->get();
            if ($memberships->count()!==1 || (int)$memberships->first()->unit_id!==$unitId || $memberships->first()->role!=='teacher') $this->invalid('Grant explicit teacher membership in this unit only before linking the account.');
            if ($teacher->user_id && (int)$teacher->user_id!==(int)$user->id) $this->invalid('An existing teacher account link cannot be replaced implicitly.');
            if (DB::table('erp_teachers')->where('user_id',$user->id)->where('id','<>',$teacher->id)->lockForUpdate()->first()) $this->invalid('This user is already linked to another teacher record.');
            if ((int)$teacher->user_id===(int)$user->id) return $teacher;
            DB::table('erp_teachers')->where('id',$teacher->id)->update(['user_id'=>$user->id,'updated_at'=>$this->now()]);
            $this->audit($unitId,$actorId,'teacher.account_linked','teacher',$teacher->id,['user_id'=>(int)$user->id]);
            return DB::table('erp_teachers')->where('id',$teacher->id)->lockForUpdate()->first();
        });
    }
    public function unlinkTeacherUser(int $unitId,int $actorId,array $payload) {
        $v=Validator::make($payload,['teacher_id'=>'required|integer|min:1','reason'=>'required|string|max:2000'])->validate();
        return DB::transaction(function() use ($unitId,$actorId,$v) {
            $this->actor($unitId,$actorId);
            $teacher=DB::table('erp_teachers')->where('id',$v['teacher_id'])->lockForUpdate()->first();
            if (!$teacher) $this->invalid('Teacher not found.');
            $pools=DB::table('erp_teacher_units')->where('teacher_id',$teacher->id)->lockForUpdate()->get();
            if ($pools->count()!==1 || (int)$pools->first()->unit_id!==$unitId) $this->invalid('Teacher must belong only to this unit pool.');
            $reason=trim($v['reason']); if ($reason==='') $this->invalid('An unlink reason is required.');
            if (!$teacher->user_id) return $teacher;
            $old=(int)$teacher->user_id;
            DB::table('erp_teachers')->where('id',$teacher->id)->update(['user_id'=>null,'updated_at'=>$this->now()]);
            $this->audit($unitId,$actorId,'teacher.account_unlinked','teacher',$teacher->id,['previous_user_id'=>$old,'reason'=>$reason,'lesson_schedule_preserved'=>true]);
            return DB::table('erp_teachers')->where('id',$teacher->id)->lockForUpdate()->first();
        });
    }
    private function ownedTask(int $actorId,int $taskId) {
        $snapshot=DB::table('erp_tasks')->where('id',$taskId)->first();
        if (!$snapshot) throw new AuthorizationException('Task access denied.');
        DB::table('erp_units')->where('id',$snapshot->unit_id)->lockForUpdate()->first();
        $user=User::where('id',$actorId)->lockForUpdate()->first(); (new SiteAccessPolicy())->assertSiteMember($user);
        $membership=DB::table('erp_memberships')->where('unit_id',$snapshot->unit_id)->where('user_id',$actorId)->whereIn('role',['admin','sales','teacher'])->lockForUpdate()->first();
        $task=DB::table('erp_tasks')->where('id',$taskId)->where('unit_id',$snapshot->unit_id)->lockForUpdate()->first();
        if (!$membership || !$task || (int)$task->assigned_to!==$actorId) throw new AuthorizationException('Only your own assigned tasks in a current staff unit are available.');
        return $task;
    }
    public function completeOwnTask(int $actorId,int $taskId) {
        return DB::transaction(function() use ($actorId,$taskId) {
            $task=$this->ownedTask($actorId,$taskId);
            if (DB::table('erp_task_items')->where('unit_id',$task->unit_id)->where('task_id',$task->id)->whereNull('completed_at')->lockForUpdate()->first()) $this->invalid('Complete every checklist item before completing this task.');
            if ($task->status!=='completed') { DB::table('erp_tasks')->where('id',$task->id)->update(['status'=>'completed','updated_at'=>$this->now()]); $this->audit($task->unit_id,$actorId,'task.completed','task',$task->id,['previous_status'=>$task->status,'own_task'=>true]); }
            return ['id'=>$task->id,'status'=>'completed'];
        });
    }
    public function completeOwnTaskItem(int $actorId,int $taskId,int $itemId) {
        return DB::transaction(function() use ($actorId,$taskId,$itemId) {
            $task=$this->ownedTask($actorId,$taskId);
            $item=DB::table('erp_task_items')->where('id',$itemId)->where('task_id',$taskId)->where('unit_id',$task->unit_id)->lockForUpdate()->first();
            if (!$item) throw new AuthorizationException('Checklist item access denied.');
            if (!$item->completed_at) { DB::table('erp_task_items')->where('id',$item->id)->update(['completed_at'=>$this->now(),'completed_by'=>$actorId,'updated_at'=>$this->now()]); $this->audit($task->unit_id,$actorId,'task.item_completed','task_item',$item->id,['task_id'=>$taskId,'own_task'=>true]); }
            return ['id'=>$itemId,'task_id'=>$taskId,'completed'=>true];
        });
    }
    public function revokeMembership(int $unitId,int $actorId,array $payload) {
        $v=Validator::make($payload,['user_id'=>'required|integer|min:1'])->validate();
        return DB::transaction(function() use ($unitId,$actorId,$v) {
            $this->actor($unitId,$actorId);
            // Revocation cleans up existing permissions even if the target account is disabled, deleted, moved, or missing.
            $userId=(int)$v['user_id'];
            User::where('id',$userId)->lockForUpdate()->first();
            if ($userId===$actorId) $this->invalid('Administrators cannot revoke their own membership.');
            $existing=DB::table('erp_memberships')->where('unit_id',$unitId)->where('user_id',$userId)->lockForUpdate()->first();
            if (!$existing) $this->invalid('This user has no membership in the unit.');
            $this->protectTeacher($unitId,$userId,null);
            if ($existing->role==='admin' && DB::table('erp_memberships')->where('unit_id',$unitId)->where('role','admin')->lockForUpdate()->get()->count()<=1) $this->invalid('The last unit administrator cannot be removed.');
            DB::table('erp_memberships')->where('id',$existing->id)->delete();
            $this->audit($unitId,$actorId,'membership.revoked','membership',$existing->id,['user_id'=>$userId,'previous_role'=>$existing->role]);
            return ['user_id'=>$userId,'unit_id'=>$unitId,'revoked'=>true];
        });
    }
}
