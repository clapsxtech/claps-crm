<?php
namespace App\Modules\Erp\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Private operational history; no teacher/parent exports read these tables. */
class TuitionWorkflows
{
    public function assertParentReviewed(object $attendance): void
    {
        if (!config('erp.parent_review_required', true)) return;
        // Preserve already approved historical records. Corrections reset approval and review.
        if ($attendance->status === 'approved' && !$attendance->parent_shared_at) return;
        if ($attendance->parent_review_status === 'accepted') return;
        if ($attendance->parent_review_status === 'pending' && $attendance->parent_shared_at && $attendance->parent_deadline_at && Carbon::parse($attendance->parent_deadline_at, 'UTC')->lte(now())) return;
        throw ValidationException::withMessages(['parent_review'=>'Share the attendance with the parent and wait until the objection deadline, or record explicit acceptance. Resolve disputed entries first.']);
    }

    public function note(int $unit, int $actor, array $payload)
    {
        $v=Validator::make($payload,['lesson_id'=>'required|integer|min:1','kind'=>'required|in:internal,demo','outcome'=>'nullable|in:continue,try_another,awaiting_feedback','note'=>'nullable|string|max:4000'])->validate();
        return DB::transaction(function()use($unit,$actor,$v){
            $lesson=DB::table('erp_lessons')->where('unit_id',$unit)->where('id',$v['lesson_id'])->lockForUpdate()->first();
            if (!$lesson) $this->fail('Choose a lesson in this team.');
            if ($v['kind']==='demo' && ($lesson->kind!=='demo' || empty($v['outcome']))) $this->fail('Choose a demo lesson and its outcome.');
            if ($v['kind']==='internal' && !trim($v['note']??'')) $this->fail('Enter a short internal note.');
            if ($v['kind']==='internal') $v['outcome']=null;
            return $this->insert('internal_notes',$unit,$actor,$v,'tuition.note_added');
        });
    }

    public function parentReview(int $unit,int $actor,array $payload)
    {
        $v=Validator::make($payload,['attendance_id'=>'required|integer|min:1','decision'=>'required|in:share,accepted,disputed','deadline_at'=>'nullable|date_format:Y-m-d H:i:s','note'=>'required|string|max:4000','snapshot'=>'nullable|string|size:64'])->validate();
        return DB::transaction(function()use($unit,$actor,$v){
            $row=DB::table('erp_attendance')->where('unit_id',$unit)->where('id',$v['attendance_id'])->lockForUpdate()->first();
            if (!$row || $row->status!=='submitted') $this->fail('Only submitted attendance can enter parent review. Correct approved attendance first.');
            if (isset($v['snapshot']) && !hash_equals(self::reviewSnapshot($row),$v['snapshot'])) $this->fail('Attendance changed after preparing the statement. Prepare and share the corrected statement.');
            if (!trim($v['note'])) $this->fail('Record the sharing evidence, parent reply, or resolution.');
            $changes=['parent_review_status'=>$v['decision']==='share'?'pending':$v['decision'],'parent_review_note'=>$v['note'],'updated_at'=>now('UTC')];
            if ($v['decision']==='share') {
                if (empty($v['deadline_at']) || Carbon::parse($v['deadline_at'],'UTC')->lte(now())) $this->fail('Choose the parent objection deadline in UTC.');
                if ($row->parent_review_status==='disputed') $this->fail('Resolve the dispute explicitly or correct attendance before sharing again.');
                $changes['parent_shared_at']=now('UTC'); $changes['parent_deadline_at']=$v['deadline_at'];
            } elseif (!$row->parent_shared_at) $this->fail('Record when the statement was shared before recording the reply.');
            DB::table('erp_attendance')->where('unit_id',$unit)->where('id',$row->id)->update($changes);
            Audit::record($unit,$actor,'attendance.parent_review','attendance',$row->id,['before'=>(array)$row,'changes'=>$changes]);
            return DB::table('erp_attendance')->find($row->id);
        });
    }

    public function candidate(int $unit,int $actor,array $payload)
    {
        $v=Validator::make($payload,['name'=>'required|string|max:255','contact'=>'required|string|max:255','qualification'=>'required|string|max:2000','subjects'=>'required|string|max:2000','levels'=>'required|string|max:1000','languages'=>'nullable|string|max:1000','availability'=>'nullable|string|max:2000'])->validate();
        foreach (['name','contact','qualification','subjects','levels'] as $field) if (!trim($v[$field])) $this->fail('Complete the required candidate details.');
        return DB::transaction(fn()=>$this->insert('candidates',$unit,$actor,$v,'hr.candidate_added'));
    }

    public function interview(int $unit,int $actor,array $payload)
    {
        $v=Validator::make($payload,['candidate_id'=>'required|integer|min:1','score'=>'required|integer|min:0|max:100','recording_url'=>'nullable|url:https|max:2000','strengths'=>'nullable|string|max:4000','concerns'=>'nullable|string|max:4000','decision'=>'required|in:ready,hold,rejected'])->validate();
        return DB::transaction(function()use($unit,$actor,$v){
            $candidate=DB::table('erp_candidates')->where('unit_id',$unit)->where('id',$v['candidate_id'])->lockForUpdate()->first();
            if (!$candidate) $this->fail('Choose a candidate in this team.');
            $row=$this->insert('candidate_interviews',$unit,$actor,$v,'hr.interview_recorded');
            DB::table('erp_candidates')->where('unit_id',$unit)->where('id',$candidate->id)->update(['status'=>$v['decision'],'updated_at'=>now('UTC')]);
            return $row;
        });
    }

    public static function reviewSnapshot(object $row): string
    {
        return hash('sha256',json_encode([(int)$row->id,(int)$row->actual_minutes,$row->actual_starts_at,$row->status,$row->updated_at]));
    }

    private function insert(string $table,int $unit,int $actor,array $v,string $action)
    {
        $id=DB::table('erp_'.$table)->insertGetId($v+['unit_id'=>$unit,'created_by'=>$actor,'created_at'=>now(),'updated_at'=>now('UTC')]);
        Audit::record($unit,$actor,$action,$table,$id,$v);
        return DB::table('erp_'.$table)->find($id);
    }
    private function fail(string $message): never { throw ValidationException::withMessages(['workflow'=>$message]); }
}
