<?php

namespace App\Modules\Erp\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TeacherAllocation
{
    public const MEDIA=['English','Malayalam','English + Malayalam','Hindi','Arabic','Tamil'];
    private function fail(string $message): never { throw ValidationException::withMessages(['allocation'=>$message]); }
    public function authorize(int $unit,User $actor,string $action=''): void
    {
        (new Access())->unit($actor,$unit,$action!=='');
        abort_unless((new TeamPermissions())->allows($actor,$unit,$action?'action':'page',$action?:'allocation'),403);
    }
    public function context(int $unit,User $actor,array $p): object
    {
        $this->authorize($unit,$actor);
        if((bool)($p['student_id']??null)===(bool)($p['lead_id']??null))$this->fail('Choose exactly one student or enquiry.');
        if(!empty($p['student_id'])){
            $row=DB::table('erp_students')->where('unit_id',$unit)->where('id',$p['student_id'])->first();
            abort_unless($row,404);
            return (object)['student_id'=>(int)$row->id,'lead_id'=>null,'name'=>$row->name,'grade'=>$row->grade,'syllabus'=>$row->syllabus,'timezone'=>$row->timezone,'status'=>$row->status];
        }
        $row=$this->lead($unit,$actor,(int)$p['lead_id']);
        return (object)['student_id'=>$row->student_id?(int)$row->student_id:null,'lead_id'=>(int)$row->id,'name'=>$row->name,'grade'=>$row->grade,'syllabus'=>$row->syllabus,'timezone'=>DB::table('erp_units')->where('id',$unit)->value('timezone'),'status'=>$row->status];
    }
    private function lead(int $unit,User $actor,int $id,bool $lock=false): object
    {
        $q=DB::table('erp_leads')->where('unit_id',$unit)->where('id',$id);
        if(in_array((new TeamPermissions())->role($actor,$unit),['sales_followups','academics'],true))$q->where(function($q)use($actor){$q->where('assigned_to',$actor->id)->orWhere(fn($q)=>$q->whereNull('assigned_to')->where('created_by',$actor->id));});
        if($lock)$q->lockForUpdate();$lead=$q->first();abort_unless($lead,404);return $lead;
    }
    public function slot(array $p,object $context): array
    {
        $v=Validator::make($p,[
            'subject_id'=>'required|integer|min:1','date'=>'required|date_format:Y-m-d|after_or_equal:2000-01-03|before_or_equal:2099-12-27',
            'time'=>['required','regex:/^([01]\d|2[0-3]):[0-5]\d$/'],'timezone'=>'required|timezone',
            'duration_minutes'=>'required|integer|min:5|max:180','medium'=>'required|in:'.implode(',',self::MEDIA),
            'grade'=>'nullable|string|max:100','syllabus'=>'nullable|string|max:100',
        ])->validate();
        abort_unless(DB::table('erp_subjects')->where('id',$v['subject_id'])->exists(),422,'Choose a valid subject.');
        foreach(['grade','syllabus'] as $field){
            if(!empty($context->$field) && isset($v[$field]) && trim($v[$field])!==$context->$field)$this->fail('The '.$field.' differs from the student/enquiry record. Correct that record first.');
            $v[$field]=$context->$field ?: trim($v[$field]??'');
            if(!$v[$field] || $v[$field]==='Not specified')$this->fail('Confirm the '.$field.' before matching.');
        }
        $v['subject_id']=(int)$v['subject_id'];$v['duration_minutes']=(int)$v['duration_minutes'];
        $v['starts_at']=(new Scheduling())->localToUtc($v['date'],$v['time'],$v['timezone']);
        $v['ends_at']=CarbonImmutable::parse($v['starts_at'],'UTC')->addMinutes($v['duration_minutes'])->format('Y-m-d H:i:s');
        return $v;
    }
    private function teacher(int $unit,int $id): object
    {
        $row=DB::table('erp_teachers')->where('id',$id)->lockForUpdate()->first();
        abort_unless($row && DB::table('erp_teacher_units')->where('unit_id',$unit)->where('teacher_id',$id)->exists(),422,'Teacher not in this unit.');
        return $row;
    }
    private function checkTeacher(int $unit,int $id,array $slot,object $context): void
    {
        $this->teacher($unit,$id);
        if($context->student_id){
            $student=DB::table('erp_students')->where('unit_id',$unit)->where('id',$context->student_id)->lockForUpdate()->first();
            if(!$student || $student->grade!==$slot['grade'] || $student->syllabus!==$slot['syllabus'])$this->fail('Student details changed. Refresh the match before booking.');
        }
        $candidate=DB::table('erp_teacher_candidates')->where('teacher_id',$id)->whereNull('merged_into_id')->first();
        if($candidate && !in_array($candidate->stage,['REGISTERED','APPROVED'],true))$this->fail('Resolve the teacher identity and registration first.');
        $profile=DB::table('erp_teacher_matching_profiles')->where('teacher_id',$id)->first();
        if(!$profile || !in_array($slot['medium'],json_decode($profile->mediums,true)?:[],true))$this->fail('Teaching medium has not been confirmed for this teacher.');
        if(!DB::table('erp_eligibilities')->where('teacher_id',$id)->where('subject_id',$slot['subject_id'])->where('grade',$slot['grade'])->where('syllabus',$slot['syllabus'])->exists())$this->fail('Subject, class and syllabus eligibility need confirmation.');
        (new Scheduling())->checkMatchedSlot($unit,$id,$context->student_id,$slot['starts_at'],$slot['ends_at']);
    }
    public function matches(int $unit,User $actor,array $p): array
    {
        $context=$this->context($unit,$actor,$p);$slot=$this->slot($p,$context);
        $q=DB::table('erp_teachers as t')->join('erp_teacher_units as u','u.teacher_id','=','t.id')
            ->leftJoin('erp_teacher_matching_profiles as m','m.teacher_id','=','t.id')->where('u.unit_id',$unit)->where('t.status','active');
        if(empty($p['include_unverified']))$q->whereExists(function($e)use($slot){$e->selectRaw('1')->from('erp_eligibilities')->whereColumn('teacher_id','t.id')->where('subject_id',$slot['subject_id'])->where('grade',$slot['grade'])->where('syllabus',$slot['syllabus']);});
        if(!empty($p['teacher_q']))$q->where(fn($w)=>$w->where('t.name','like','%'.$p['teacher_q'].'%')->orWhere('t.code','like','%'.$p['teacher_q'].'%'));
        $teachers=$q->select('t.id','t.code','t.name','t.timezone','m.verified_at')->orderBy('t.name')->paginate(25,['*'],'matches_page')->withQueryString();
        $ids=$teachers->pluck('id')->all();$start=$slot['starts_at'];$weekEnd=CarbonImmutable::parse($start,'UTC')->addDays(7)->format('Y-m-d H:i:s');
        $loads=DB::table('erp_lessons')->whereIn('teacher_id',$ids)->where('status','scheduled')->where('starts_at','<',$weekEnd)->where('ends_at','>',$start)->get(['teacher_id','starts_at','ends_at'])->groupBy('teacher_id');
        foreach($teachers as $teacher){
            $teacher->blockers=[];
            try{$this->checkTeacher($unit,$teacher->id,$slot,$context);}catch(ValidationException $e){$teacher->blockers=array_values(array_unique(array_merge(...array_values($e->errors()))));}
            $teacher->hours=round(collect($loads[$teacher->id]??[])->sum(fn($l)=>max(0,strtotime(min($l->ends_at,$weekEnd).' UTC')-strtotime(max($l->starts_at,$start).' UTC')))/3600,1);
            $teacher->local_time=CarbonImmutable::parse($start,'UTC')->setTimezone($teacher->timezone)->format('D d M H:i');
        }
        return compact('context','slot','teachers');
    }
    public function qualify(int $unit,User $actor,array $p): object
    {
        $this->authorize($unit,$actor,'allocation-qualify');
        $v=Validator::make($p,['teacher_id'=>'required|integer|min:1','subject_id'=>'required|integer|min:1','grade'=>'required|string|max:100','syllabus'=>'required|string|max:100','mediums'=>'required|array|min:1|max:6','mediums.*'=>'required|in:'.implode(',',self::MEDIA),'note'=>'required|string|max:4000'])->validate();
        return DB::transaction(function()use($unit,$actor,$v){
            $this->teacher($unit,$v['teacher_id']);
            $subject=DB::table('erp_subjects')->find($v['subject_id']);abort_unless($subject,422);
            $candidate=DB::table('erp_teacher_candidates')->where('teacher_id',$v['teacher_id'])->whereNull('merged_into_id')->first();
            if($candidate && !in_array($candidate->stage,['REGISTERED','APPROVED'],true))$this->fail('Resolve identity and registration in Teacher recruitment first.');
            (new People())->eligibility($unit,$actor->id,['teacher_id'=>$v['teacher_id'],'subject'=>$subject->name,'grade'=>$v['grade'],'syllabus'=>$v['syllabus']]);
            DB::table('erp_teacher_matching_profiles')->updateOrInsert(['teacher_id'=>$v['teacher_id']],['mediums'=>json_encode(array_values(array_unique($v['mediums']))),'verified_at'=>now(),'verified_by'=>$actor->id,'verification_note'=>$v['note'],'created_at'=>now(),'updated_at'=>now()]);
            Audit::record($unit,$actor->id,'teacher.matching_verified','teacher',$v['teacher_id'],['subject_id'=>$v['subject_id'],'grade'=>$v['grade'],'syllabus'=>$v['syllabus'],'mediums'=>$v['mediums']]);
            return DB::table('erp_teacher_matching_profiles')->where('teacher_id',$v['teacher_id'])->first();
        });
    }
    public function window(int $unit,User $actor,array $p): object
    {
        $this->authorize($unit,$actor,'allocation-window');
        $v=Validator::make($p,['teacher_id'=>'nullable|integer|min:1','student_id'=>'nullable|integer|min:1','weekday'=>'required|integer|min:1|max:7','start_time'=>['required','regex:/^([01]\d|2[0-3]):[0-5]\d$/'],'end_time'=>['required','regex:/^([01]\d|2[0-3]):[0-5]\d$/'],'timezone'=>'required|timezone','starts_on'=>'required|date_format:Y-m-d','ends_on'=>'required|date_format:Y-m-d|after_or_equal:starts_on','note'=>'required|string|max:4000'])->validate();
        return DB::transaction(function()use($unit,$actor,$v){
            $data=$v;unset($data['note']);
            // Existing scheduling validation remains authoritative, including exact one-party selection.
            $row=(new Scheduling())->availability($unit,$actor->id,$data);
            Audit::record($unit,$actor->id,'matching.availability_confirmed','availability',$row->id,['note'=>$v['note']]);return $row;
        });
    }
    private function booking(array $p): array
    {
        $v=Validator::make($p,['teacher_id'=>'required|integer|min:1','reference'=>'required|string|max:120','teacher_confirmed'=>'required|accepted','student_confirmed'=>'required|accepted','confirmation_channel'=>'required|in:whatsapp,call','confirmation_note'=>'required|string|max:4000','meet_link'=>'nullable|url:http,https|max:500'])->validate();
        $v['teacher_id']=(int)$v['teacher_id'];$v['teacher_confirmed']=true;$v['student_confirmed']=true;
        $v['confirmation_note']=trim($v['confirmation_note']);if(!$v['confirmation_note'])$this->fail('Confirmation evidence is required.');
        return $v;
    }
    private function fingerprint(string $kind,array $slot,array $v,array $extra=[]): string
    {
        ksort($v);ksort($extra);return hash('sha256',json_encode([$kind,$slot,$v,$extra],JSON_THROW_ON_ERROR));
    }
    private function replay(int $unit,string $kind,array $v,string $hash): ?object
    {
        $r=DB::table('erp_allocation_requests')->where('reference',$v['reference'])->lockForUpdate()->first();
        if($r && ((int)$r->unit_id!==$unit || $r->kind!==$kind || !hash_equals($r->fingerprint,$hash)))$this->fail('This request reference was already used with different details. Refresh the form for a new request.');
        return $r;
    }
    public function bookDemo(int $unit,User $actor,array $p): object
    {
        $this->authorize($unit,$actor,'allocation-demo');$context=$this->context($unit,$actor,$p);
        if(!$context->lead_id)$this->fail('Choose an admissions enquiry for a demo. Existing students can be allocated directly.');
        $slot=$this->slot($p,$context);$v=$this->booking($p);$hash=$this->fingerprint('demo',$slot,$v,['lead_id'=>$context->lead_id]);
        return DB::transaction(function()use($unit,$actor,$context,$slot,$v,$hash){
            $this->teacher($unit,$v['teacher_id']);
            $lead=$this->lead($unit,$actor,$context->lead_id,true);
            if($r=$this->replay($unit,'demo',$v,$hash))return DB::table('erp_demos')->find($r->demo_id);
            if($lead->status==='LOST')$this->fail('Reopen the enquiry before booking.');
            foreach(['grade','syllabus'] as $field)if($lead->$field && $lead->$field!==$slot[$field])$this->fail('Enquiry details changed. Refresh the match before booking.');
            if($slot['starts_at']<=CarbonImmutable::now('UTC')->format('Y-m-d H:i:s'))$this->fail('Book a future slot.');
            $this->checkTeacher($unit,$v['teacher_id'],$slot,$context);
            // The enquiry has no canonical student yet. Its selected slot is
            // explicitly confirmed by the parent, rather than inferred from text.
            $existing=DB::table('erp_demos')->where('unit_id',$unit)->where('lead_id',$lead->id)->whereIn('status',['SCHEDULED','RESCHEDULED'])->where('starts_at','<',$slot['ends_at'])->where('ends_at','>',$slot['starts_at'])->lockForUpdate()->first();
            if($existing)$this->fail('This enquiry already has a demo at that time.');
            $local=CarbonImmutable::parse($slot['starts_at'],'UTC')->setTimezone(DB::table('erp_units')->where('id',$unit)->value('timezone'));
            if(DB::table('erp_demos')->where('unit_id',$unit)->where('lead_id',$lead->id)->whereNull('starts_at')->whereIn('status',['SCHEDULED','RESCHEDULED'])->where('scheduled_on',$local->toDateString())->lockForUpdate()->first())$this->fail('Review or cancel this enquiry\'s legacy demo before rebooking that date.');
            $demo=(new Admissions())->scheduleDemo($unit,$actor->id,['lead_id'=>$lead->id,'teacher_id'=>$v['teacher_id'],'subject_id'=>$slot['subject_id'],'scheduled_on'=>$local->toDateString(),'scheduled_time'=>$local->format('H:i'),'meet_link'=>$v['meet_link']??null,'scheduling_notes'=>$v['confirmation_note']],true);
            DB::table('erp_demos')->where('id',$demo->id)->update(['starts_at'=>$slot['starts_at'],'ends_at'=>$slot['ends_at'],'timezone'=>$slot['timezone'],'duration_minutes'=>$slot['duration_minutes'],'matching_medium'=>$slot['medium'],'matching_grade'=>$slot['grade'],'matching_syllabus'=>$slot['syllabus'],'updated_at'=>now()]);
            DB::table('erp_leads')->where('id',$lead->id)->update(['grade'=>$slot['grade'],'syllabus'=>$slot['syllabus'],'updated_at'=>now()]);
            DB::table('erp_allocation_requests')->insert(['unit_id'=>$unit,'reference'=>$v['reference'],'fingerprint'=>$hash,'kind'=>'demo','teacher_id'=>$v['teacher_id'],'demo_id'=>$demo->id,'created_by'=>$actor->id,'created_at'=>now(),'updated_at'=>now()]);
            Audit::record($unit,$actor->id,'matching.demo_reserved','demo',$demo->id,['starts_at'=>$slot['starts_at'],'ends_at'=>$slot['ends_at'],'medium'=>$slot['medium']]);
            return DB::table('erp_demos')->find($demo->id);
        });
    }
    public function feedback(int $unit,User $actor,array $p): object
    {
        $this->authorize($unit,$actor,'allocation-feedback');
        $v=Validator::make($p,['demo_id'=>'required|integer|min:1','outcome'=>'required|in:CONTINUE,TRY_ANOTHER,AWAITING_FEEDBACK','note'=>'required|string|max:4000'])->validate();
        return DB::transaction(function()use($unit,$actor,$v){
            $demo=DB::table('erp_demos')->where('unit_id',$unit)->where('id',$v['demo_id'])->first();abort_unless($demo,404);
            $this->lead($unit,$actor,$demo->lead_id,true);
            $demo=DB::table('erp_demos')->where('unit_id',$unit)->where('id',$v['demo_id'])->lockForUpdate()->first();
            if(!$demo->ends_at || $demo->ends_at>CarbonImmutable::now('UTC')->format('Y-m-d H:i:s') || in_array($demo->status,['CANCELLED','NO_SHOW'],true))$this->fail('Record feedback only after an attended, timed demo.');
            if($demo->enrollment_id)$this->fail('This demo was already allocated. Use class follow-up for later feedback.');
            DB::table('erp_demos')->where('id',$demo->id)->update(['status'=>'ATTENDED','completed_at'=>$demo->completed_at??now(),'feedback_outcome'=>$v['outcome'],'feedback_note'=>$v['note'],'updated_at'=>now()]);
            DB::table('erp_lead_activities')->insert(['unit_id'=>$unit,'lead_id'=>$demo->lead_id,'actor_id'=>$actor->id,'kind'=>'demo_feedback','summary'=>$v['note'],'outcome'=>$v['outcome'],'metadata'=>json_encode(['demo_id'=>$demo->id]),'occurred_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
            Audit::record($unit,$actor->id,'matching.demo_feedback','demo',$demo->id,['outcome'=>$v['outcome'],'note'=>$v['note']]);return DB::table('erp_demos')->find($demo->id);
        });
    }
    public function regular(int $unit,User $actor,array $p): object
    {
        $this->authorize($unit,$actor,'allocation-regular');$context=$this->context($unit,$actor,$p);$slot=$this->slot($p,$context);$v=$this->booking($p);
        $extra=Validator::make($p,['ends_on'=>'required|date_format:Y-m-d|after_or_equal:date','student_rate_paise'=>'required|integer|min:0|max:100000000','teacher_rate_paise'=>'required|integer|min:0|max:100000000','demo_id'=>'nullable|integer|min:1'])->validate();
        foreach(['student_rate_paise','teacher_rate_paise'] as $k)$extra[$k]=(int)$extra[$k];
        if(CarbonImmutable::parse($slot['date'])->diffInDays(CarbonImmutable::parse($extra['ends_on']))>366)$this->fail('Allocate at most 366 days at a time.');
        $hash=$this->fingerprint('regular',$slot,$v,$extra+['lead_id'=>$context->lead_id,'student_id'=>$context->lead_id?null:$context->student_id]);
        return DB::transaction(function()use($unit,$actor,$context,$slot,$v,$extra,$hash){
            $this->teacher($unit,$v['teacher_id']);
            if($context->lead_id)$this->lead($unit,$actor,$context->lead_id,true);
            if($r=$this->replay($unit,'regular',$v,$hash))return $r;
            if($slot['starts_at']<=CarbonImmutable::now('UTC')->format('Y-m-d H:i:s'))$this->fail('Start regular classes in a future slot.');
            $demo=null;
            if($context->lead_id){
                $demo=DB::table('erp_demos')->where('unit_id',$unit)->where('id',$extra['demo_id']??0)->where('lead_id',$context->lead_id)->lockForUpdate()->first();
                abort_unless($demo,422,'Choose the successful demo for this enquiry.');
                if($demo->feedback_outcome!=='CONTINUE' || $demo->status!=='ATTENDED' || (int)$demo->teacher_id!==$v['teacher_id'] || (int)$demo->subject_id!==$slot['subject_id'] || $demo->enrollment_id)$this->fail('Use an attended demo with Continue feedback for the same teacher and subject.');
                if($slot['starts_at']<$demo->ends_at)$this->fail('Regular classes must start after the demo.');
            }elseif(!empty($extra['demo_id']))$this->fail('A demo handoff must be opened from its enquiry.');
            $this->checkTeacher($unit,$v['teacher_id'],$slot,(object)['student_id'=>null]);
            $student=$context->student_id?DB::table('erp_students')->where('unit_id',$unit)->where('id',$context->student_id)->first():null;
            if(!$student && $context->lead_id)$student=(new Admissions())->convertLead($unit,$actor->id,['lead_id'=>$context->lead_id,'joined_on'=>now(DB::table('erp_units')->where('id',$unit)->value('timezone'))->toDateString()]);
            abort_unless($student,422,'Choose an active student.');
            if($student->grade!==$slot['grade'] || $student->syllabus!==$slot['syllabus'])$this->fail('Student details changed. Refresh the match before allocating.');
            $localStart=CarbonImmutable::parse($slot['starts_at'],'UTC')->setTimezone($slot['timezone']);
            $localEnd=CarbonImmutable::parse($slot['ends_at'],'UTC')->setTimezone($slot['timezone']);
            if($localStart->toDateString()!==$localEnd->toDateString())$this->fail('Split classes crossing local midnight before allocation.');
            // This window records the explicit recurring parent confirmation on
            // this form. Teacher windows must already exist and cover each date.
            (new Scheduling())->availability($unit,$actor->id,['student_id'=>$student->id,'weekday'=>(int)$localStart->format('N'),'start_time'=>$slot['time'],'end_time'=>$localEnd->format('H:i'),'timezone'=>$slot['timezone'],'starts_on'=>$slot['date'],'ends_on'=>$extra['ends_on']]);
            $enrollment=(new Scheduling())->assign($unit,$actor->id,['student_id'=>$student->id,'teacher_id'=>$v['teacher_id'],'subject_id'=>$slot['subject_id'],'starts_on'=>$slot['date'],'ends_on'=>$extra['ends_on'],'student_rate_paise'=>$extra['student_rate_paise'],'teacher_rate_paise'=>$extra['teacher_rate_paise']]);
            $agreement=(new Scheduling())->proposeAgreement($unit,$actor->id,['enrollment_id'=>$enrollment->id,'weekday'=>(int)$localStart->format('N'),'start_time'=>$slot['time'],'duration_minutes'=>$slot['duration_minutes'],'timezone'=>$slot['timezone'],'starts_on'=>$slot['date'],'ends_on'=>$extra['ends_on']]);
            (new Scheduling())->confirmAgreement($unit,$actor->id,['agreement_id'=>$agreement->id,'teacher_confirmed'=>true,'student_confirmed'=>true,'confirmation_channel'=>$v['confirmation_channel'],'confirmation_note'=>$v['confirmation_note']]);
            (new Scheduling())->materialize($unit,$actor->id,['agreement_id'=>$agreement->id,'from'=>$slot['date'],'through'=>$extra['ends_on']]);
            if($demo)DB::table('erp_demos')->where('id',$demo->id)->update(['enrollment_id'=>$enrollment->id,'agreement_id'=>$agreement->id,'student_id'=>$student->id,'status'=>'JOINED','updated_at'=>now()]);
            $id=DB::table('erp_allocation_requests')->insertGetId(['unit_id'=>$unit,'reference'=>$v['reference'],'fingerprint'=>$hash,'kind'=>'regular','teacher_id'=>$v['teacher_id'],'demo_id'=>$demo?->id,'enrollment_id'=>$enrollment->id,'agreement_id'=>$agreement->id,'created_by'=>$actor->id,'created_at'=>now(),'updated_at'=>now()]);
            Audit::record($unit,$actor->id,'matching.regular_allocated','enrollment',$enrollment->id,['agreement_id'=>$agreement->id,'demo_id'=>$demo?->id]);return DB::table('erp_allocation_requests')->find($id);
        });
    }
}
