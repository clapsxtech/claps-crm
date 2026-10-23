<?php

namespace App\Modules\Erp\Services;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** All dates entered here are local dates; lesson timestamps and audit timestamps are UTC. */
class Scheduling
{
    private function fail($message) { throw ValidationException::withMessages(['schedule' => $message]); }
    private function now() { return gmdate('Y-m-d H:i:s'); }
    private function required(array $p, array $keys) { foreach ($keys as $key) if (!isset($p[$key]) || $p[$key] === '') $this->fail("{$key} is required."); }
    private function date($value) {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value, new DateTimeZone('UTC'));
        if (!$d || $d->format('Y-m-d') !== $value) $this->fail('Use a valid YYYY-MM-DD date.');
        return $value;
    }
    private function integer($value, $min, $max, $label) {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || $value < $min || $value > $max) $this->fail("Invalid {$label}.");
        return (int) $value;
    }
    private function zone($name) {
        if (!in_array($name, DateTimeZone::listIdentifiers(), true) && $name !== 'UTC') $this->fail('Use an IANA timezone.');
        return new DateTimeZone($name);
    }
    /** Reject both nonexistent and ambiguous DST wall-clock times rather than silently shifting them. */
    public function localToUtc(string $date, string $time, string $timezone): string {
        $this->date($date);
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $time)) $this->fail('Use HH:mm time.');
        $zone = $this->zone($timezone);
        $wall = strtotime($date.' '.$time.':00 UTC');
        $offsets = [];
        foreach ($zone->getTransitions($wall - 172800, $wall + 172800) ?: [] as $t) $offsets[$t['offset']] = true;
        $offsets[$zone->getOffset(new DateTimeImmutable('@'.$wall))] = true;
        $matches = [];
        foreach (array_keys($offsets) as $offset) {
            $candidate = (new DateTimeImmutable('@'.($wall - $offset)))->setTimezone($zone);
            if ($candidate->format('Y-m-d H:i') === $date.' '.$time) $matches[] = $wall - $offset;
        }
        if (count($matches) !== 1) $this->fail('This local time is nonexistent or ambiguous because of DST. Choose an unambiguous time.');
        return gmdate('Y-m-d H:i:s', $matches[0]);
    }
    private function row($table, $id, $unitId = null, bool $current = false) {
        $q = DB::table($table)->where('id', $id);
        if ($unitId !== null) $q->where('unit_id', $unitId);
        // FOR UPDATE is an InnoDB current read, unlike snapshot reads under REPEATABLE READ.
        if ($current) $q->lockForUpdate();
        $row = $q->first();
        if (!$row) $this->fail('Record not found in this unit.');
        return $row;
    }
    private function parents($unitId, $teacherId, $studentId) {
        // Same lock order in every scheduling mutation; teachers are global, not unit-scoped.
        $teacher = DB::table('erp_teachers')->where('id', $teacherId)->lockForUpdate()->first();
        $student = DB::table('erp_students')->where('id', $studentId)->lockForUpdate()->first();
        if (!$teacher || !$student || (int)$student->unit_id !== (int)$unitId) $this->fail('Student or teacher does not belong to this unit.');
        if (DB::table('erp_teacher_units')->where('teacher_id', $teacherId)->lockForUpdate()->get()->count() !== 1) $this->fail('Teacher must belong to exactly one unit pool. Correct the teacher membership before scheduling.');
        if (!DB::table('erp_teacher_units')->where('teacher_id', $teacherId)->where('unit_id', $unitId)->lockForUpdate()->first()) $this->fail('Teacher does not belong to this unit.');
        return [$teacher, $student];
    }
    private function insert($table, array $values) {
        $values['created_at'] = $values['updated_at'] = $this->now();
        return $this->row($table, DB::table($table)->insertGetId($values));
    }
    private function audit($unit, $actor, $action, $type, $id, array $payload = []) { Audit::record($unit, $actor, $action, $type, $id, $payload); }

    public function assign(int $unitId, int $actorId, array $p) {
        $this->required($p, ['student_id','teacher_id','subject_id','starts_on','student_rate_paise','teacher_rate_paise']);
        return DB::transaction(function () use ($unitId, $actorId, $p) {
            [$teacher, $student] = $this->parents($unitId, $p['teacher_id'], $p['student_id']);
            if ($teacher->status !== 'active' || $student->status !== 'active') $this->fail('Student and teacher must be active.');
            $start = $this->date($p['starts_on']); $end = isset($p['ends_on']) && $p['ends_on'] !== '' ? $this->date($p['ends_on']) : null;
            if ($end && $end < $start) $this->fail('End date precedes start date.');
            $this->row('erp_subjects', $p['subject_id']);
            if (!DB::table('erp_eligibilities')->where('teacher_id',$teacher->id)->where('subject_id',$p['subject_id'])->where('grade',$student->grade)->where('syllabus',$student->syllabus)->lockForUpdate()->first()) $this->fail('Teacher is not eligible for this subject, grade and syllabus.');
            $q = DB::table('erp_enrollments')->where('student_id',$student->id)->where('subject_id',$p['subject_id'])->where(function ($q) use ($start) { $q->whereNull('ends_on')->orWhere('ends_on','>=',$start); });
            if ($end) $q->where('starts_on','<=',$end);
            if ($q->lockForUpdate()->first()) $this->fail('An assignment already covers these dates for this student and subject.');
            $row = $this->insert('erp_enrollments', ['unit_id'=>$unitId,'student_id'=>$student->id,'teacher_id'=>$teacher->id,'subject_id'=>$p['subject_id'],'starts_on'=>$start,'ends_on'=>$end,'student_rate_paise'=>$this->integer($p['student_rate_paise'],0,100000000,'student rate'),'teacher_rate_paise'=>$this->integer($p['teacher_rate_paise'],0,100000000,'teacher rate'),'currency'=>'INR','status'=>'active']);
            $this->audit($unitId,$actorId,'assignment.created','enrollment',$row->id); return $row;
        });
    }
    /** Close prospectively, then create a new assignment for a teacher/rate change. Never mutate old rates. */
    public function endAssignment(int $unitId, int $actorId, array $p) {
        $this->required($p,['enrollment_id','ends_on']);
        return DB::transaction(function () use ($unitId,$actorId,$p) {
            $e = $this->row('erp_enrollments',$p['enrollment_id'],$unitId); $this->parents($unitId,$e->teacher_id,$e->student_id);
            $e = $this->row('erp_enrollments',$e->id,$unitId,true);
            $end = $this->date($p['ends_on']);
            if ($e->ends_on && $end > $e->ends_on) $this->fail('An ended assignment cannot be extended. Create a new effective-dated assignment.');
            if ($end < gmdate('Y-m-d') || $end < $e->starts_on) $this->fail('Close assignments prospectively, not in the past.');
            $cutoff = $this->localToUtc((new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d'),'00:00',$this->row('erp_students',$e->student_id,$unitId,true)->timezone);
            if (DB::table('erp_lessons')->where('enrollment_id',$e->id)->where('starts_at','>=',$cutoff)->whereIn('status',['scheduled','completed','no_show'])->lockForUpdate()->first()) $this->fail('Cancel or resolve later lessons before ending this assignment.');
            DB::table('erp_enrollments')->where('id',$e->id)->update(['ends_on'=>$end,'updated_at'=>$this->now()]);
            DB::table('erp_agreements')->where('enrollment_id',$e->id)->where(function($q) use ($end) { $q->whereNull('ends_on')->orWhere('ends_on','>',$end); })->update(['ends_on'=>$end,'updated_at'=>$this->now()]);
            $this->audit($unitId,$actorId,'assignment.ended','enrollment',$e->id,['ends_on'=>$end]); return $this->row('erp_enrollments',$e->id,$unitId);
        });
    }
    public function availability(int $unitId, int $actorId, array $p) {
        $this->required($p,['weekday','start_time','end_time','timezone','starts_on']);
        return DB::transaction(function () use ($unitId,$actorId,$p) {
            $studentId = !empty($p['student_id']) ? $p['student_id'] : null; $teacherId = !empty($p['teacher_id']) ? $p['teacher_id'] : null;
            if (($studentId === null) === ($teacherId === null)) $this->fail('Specify exactly one student or teacher.');
            if ($studentId) { $this->row('erp_students',$studentId,$unitId); DB::table('erp_students')->where('id',$studentId)->lockForUpdate()->first(); }
            if ($teacherId) { $this->row('erp_teachers',$teacherId); DB::table('erp_teachers')->where('id',$teacherId)->lockForUpdate()->first(); if (DB::table('erp_teacher_units')->where('teacher_id',$teacherId)->lockForUpdate()->get()->count() !== 1) $this->fail('Teacher must belong to exactly one unit pool.'); if (!DB::table('erp_teacher_units')->where('teacher_id',$teacherId)->where('unit_id',$unitId)->lockForUpdate()->first()) $this->fail('Teacher does not belong to this unit.'); }
            $start = $this->date($p['starts_on']); $end = !empty($p['ends_on']) ? $this->date($p['ends_on']) : null;
            $this->localToUtc($start,$p['start_time'],$p['timezone']); $this->localToUtc($start,$p['end_time'],$p['timezone']);
            if ($p['end_time'] <= $p['start_time'] || ($end && $end < $start)) $this->fail('Availability must end after it starts; split overnight windows.');
            $row = $this->insert('erp_availability',['unit_id'=>$unitId,'student_id'=>$studentId,'teacher_id'=>$teacherId,'weekday'=>$this->integer($p['weekday'],1,7,'weekday'),'start_time'=>$p['start_time'],'end_time'=>$p['end_time'],'timezone'=>$p['timezone'],'starts_on'=>$start,'ends_on'=>$end]);
            $this->audit($unitId,$actorId,'availability.created','availability',$row->id); return $row;
        });
    }
    /** Full local-date unavailability. Existing lessons must be explicitly resolved first. */
    public function exception(int $unitId, int $actorId, array $p) {
        $this->required($p,['date','reason']);
        return DB::transaction(function () use ($unitId,$actorId,$p) {
            $studentId=!empty($p['student_id']) ? $p['student_id'] : null; $teacherId=!empty($p['teacher_id']) ? $p['teacher_id'] : null;
            if (($studentId === null) === ($teacherId === null)) $this->fail('Specify exactly one student or teacher.');
            $field=$teacherId ? 'teacher_id' : 'student_id'; $id=$teacherId ?: $studentId;
            $person=DB::table($teacherId ? 'erp_teachers' : 'erp_students')->where('id',$id)->lockForUpdate()->first();
            if (!$person || ($studentId && (int)$person->unit_id !== $unitId)) $this->fail('Person not found in this unit.');
            if ($teacherId && (DB::table('erp_teacher_units')->where('teacher_id',$id)->lockForUpdate()->get()->count() !== 1 || !DB::table('erp_teacher_units')->where('teacher_id',$id)->where('unit_id',$unitId)->lockForUpdate()->first())) $this->fail('Teacher must belong only to this unit pool.');
            $day=$this->date($p['date']); $reason=trim($p['reason']); if ($reason === '') $this->fail('An exception reason is required.');
            $start=$this->localToUtc($day,'00:00',$person->timezone);
            $end=$this->localToUtc((new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d'),'00:00',$person->timezone);
            if (DB::table('erp_lessons')->where($field,$id)->where('status','scheduled')->where('starts_at','<',$end)->where('ends_at','>',$start)->lockForUpdate()->first()) $this->fail('Reschedule or cancel existing lessons on this local date before recording an exception.');
            $existing=DB::table('erp_exceptions')->where('unit_id',$unitId)->where($field,$id)->where('date',$day)->lockForUpdate()->first();
            if ($existing) { if ($existing->reason !== $reason) $this->fail('An exception already exists for this local date.'); return $existing; }
            $row=$this->insert('erp_exceptions',['unit_id'=>$unitId,'student_id'=>$studentId,'teacher_id'=>$teacherId,'date'=>$day,'reason'=>$reason]);
            $this->audit($unitId,$actorId,'availability.exception_created','exception',$row->id,['date'=>$day,'timezone'=>$person->timezone,'reason'=>$reason]); return $row;
        });
    }
    /** End a recurring version prospectively; rates and all historic lesson rows remain unchanged. */
    public function endAgreement(int $unitId, int $actorId, array $p) {
        $this->required($p,['agreement_id','ends_on']);
        return DB::transaction(function () use ($unitId,$actorId,$p) {
            $a=$this->row('erp_agreements',$p['agreement_id'],$unitId); $e=$this->row('erp_enrollments',$a->enrollment_id,$unitId);
            $this->parents($unitId,$e->teacher_id,$e->student_id); $e=$this->row('erp_enrollments',$e->id,$unitId,true); $a=$this->row('erp_agreements',$a->id,$unitId,true);
            $end=$this->date($p['ends_on']); $today=(new DateTimeImmutable('now',$this->zone($a->timezone)))->format('Y-m-d');
            if ($end < $a->starts_on || $end < $today || ($a->ends_on && $end > $a->ends_on)) $this->fail('A recurring agreement may only be shortened prospectively.');
            $cutoff=$this->localToUtc((new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d'),'00:00',$a->timezone);
            // Replacement lessons retain lineage through originals; also protect replacements of this agreement.
            $ids=DB::table('erp_lessons')->where('agreement_id',$a->id)->lockForUpdate()->pluck('id')->all(); $all=$ids;
            while ($ids) { $ids=DB::table('erp_lessons')->whereIn('replaces_lesson_id',$ids)->lockForUpdate()->pluck('id')->all(); $all=array_merge($all,$ids); }
            if ($all && DB::table('erp_lessons')->whereIn('id',$all)->whereIn('status',['scheduled','completed','no_show'])->where('starts_at','>=',$cutoff)->lockForUpdate()->first()) $this->fail('Resolve generated lessons and their replacements beyond the end date first.');
            DB::table('erp_agreements')->where('id',$a->id)->update(['ends_on'=>$end,'updated_at'=>$this->now()]);
            $this->audit($unitId,$actorId,'agreement.ended','agreement',$a->id,['ends_on'=>$end]); return $this->row('erp_agreements',$a->id,$unitId);
        });
    }
    public function proposeAgreement(int $unitId, int $actorId, array $p) {
        $this->required($p,['enrollment_id','weekday','start_time','duration_minutes','timezone','starts_on']);
        return DB::transaction(function () use ($unitId,$actorId,$p) {
            $e = $this->row('erp_enrollments',$p['enrollment_id'],$unitId); $this->parents($unitId,$e->teacher_id,$e->student_id);
            $e = $this->row('erp_enrollments',$e->id,$unitId,true);
            $start = $this->date($p['starts_on']); $end = !empty($p['ends_on']) ? $this->date($p['ends_on']) : $e->ends_on;
            if ($start < $e->starts_on || ($end && $end < $start) || ($e->ends_on && (!$end || $end > $e->ends_on))) $this->fail('Agreement must fit assignment dates.');
            $previousId=!empty($p['replaces_agreement_id']) ? $p['replaces_agreement_id'] : null;
            if ($previousId) {
                $previous=$this->row('erp_agreements',$previousId,$unitId,true);
                if ((int)$previous->enrollment_id !== (int)$e->id || !$previous->ends_on || $start <= $previous->ends_on) $this->fail('End the previous version first; the replacement must use the same assignment and start after its end.');
                if (DB::table('erp_agreements')->where('replaces_agreement_id',$previousId)->lockForUpdate()->first()) $this->fail('This agreement already has a replacement version.');
            }
            $this->localToUtc($start,$p['start_time'],$p['timezone']);
            $row = $this->insert('erp_agreements',['unit_id'=>$unitId,'enrollment_id'=>$e->id,'replaces_agreement_id'=>$previousId,'weekday'=>$this->integer($p['weekday'],1,7,'weekday'),'start_time'=>$p['start_time'],'duration_minutes'=>$this->integer($p['duration_minutes'],1,480,'duration'),'timezone'=>$p['timezone'],'starts_on'=>$start,'ends_on'=>$end,'status'=>'proposed']);
            $this->audit($unitId,$actorId,'agreement.proposed','agreement',$row->id); return $row;
        });
    }
    public function confirmAgreement(int $unitId, int $actorId, array $p) {
        $this->required($p,['agreement_id','confirmation_channel','teacher_confirmed','student_confirmed','confirmation_note']);
        return DB::transaction(function () use ($unitId,$actorId,$p) {
            $a = $this->row('erp_agreements',$p['agreement_id'],$unitId); $e = $this->row('erp_enrollments',$a->enrollment_id,$unitId); $this->parents($unitId,$e->teacher_id,$e->student_id);
            $e = $this->row('erp_enrollments',$e->id,$unitId,true);
            $a = $this->row('erp_agreements',$a->id,$unitId,true);
            if ($a->starts_on < $e->starts_on || ($e->ends_on && $a->starts_on > $e->ends_on)) $this->fail('Agreement no longer fits the current assignment dates.');
            if ($a->status !== 'proposed') $this->fail('Only proposed agreements can be confirmed.');
            if (!in_array($p['teacher_confirmed'],[true,1,'1'],true) || !in_array($p['student_confirmed'],[true,1,'1'],true) || !in_array($p['confirmation_channel'],['whatsapp','call'],true) || !trim($p['confirmation_note'])) $this->fail('Record bilateral WhatsApp/call confirmation and evidence.');
            $through = min($a->ends_on ?: '9999-12-31',(new DateTimeImmutable($a->starts_on))->modify('+370 days')->format('Y-m-d'));
            foreach ($this->occurrences($a,$a->starts_on,$through) as [$start,$end]) { $this->coverage($unitId,$e,$start,$end); $this->conflicts($e,$start,$end,null,$a->id); }
            // Compare future agreements too, including those whose start lies beyond the first horizon.
            foreach ($this->agreementsFor($e,$a->id) as $other) {
                $from = max($a->starts_on,$other->starts_on); $to = min($a->ends_on ?: '9999-12-31',$other->ends_on ?: '9999-12-31',(new DateTimeImmutable($from))->modify('+370 days')->format('Y-m-d'));
                foreach ($this->occurrences($a,$from,$to) as [$start,$end]) $this->agreementConflict($other,$start,$end);
            }
            DB::table('erp_agreements')->where('id',$a->id)->update(['status'=>'confirmed','teacher_confirmed_at'=>$this->now(),'student_confirmed_at'=>$this->now(),'confirmation_channel'=>$p['confirmation_channel'],'confirmation_note'=>trim($p['confirmation_note']),'confirmed_by'=>$actorId,'updated_at'=>$this->now()]);
            $this->audit($unitId,$actorId,'agreement.confirmed','agreement',$a->id,['channel'=>$p['confirmation_channel'],'note'=>trim($p['confirmation_note'])]); return $this->row('erp_agreements',$a->id,$unitId);
        });
    }
    private function occurrences($a,$from,$to) {
        $out=[]; $from=max($from,$a->starts_on); $to=min($to,$a->ends_on ?: '9999-12-31');
        for ($day=new DateTimeImmutable($from,new DateTimeZone('UTC')); $day->format('Y-m-d') <= $to; $day=$day->modify('+1 day')) {
            if ((int)$day->format('N') !== (int)$a->weekday) continue;
            $start=$this->localToUtc($day->format('Y-m-d'),$a->start_time,$a->timezone); $out[]=[$start,gmdate('Y-m-d H:i:s',strtotime($start.' UTC')+$a->duration_minutes*60)];
        } return $out;
    }
    private function coverage($unitId,$e,$start,$end) {
        foreach (['student_id'=>$e->student_id,'teacher_id'=>$e->teacher_id] as $field=>$id) {
            $person=$this->row($field === 'student_id' ? 'erp_students' : 'erp_teachers',$id,null,true);
            $zone=$this->zone($person->timezone);
            $first=(new DateTimeImmutable($start,new DateTimeZone('UTC')))->setTimezone($zone)->format('Y-m-d');
            $last=(new DateTimeImmutable($end,new DateTimeZone('UTC')))->modify('-1 second')->setTimezone($zone)->format('Y-m-d');
            if (DB::table('erp_exceptions')->where('unit_id',$unitId)->where($field,$id)->whereBetween('date',[$first,$last])->lockForUpdate()->first()) $this->fail('A date-specific holiday or unavailability exception blocks this lesson.');
            $covered=false;
            foreach (DB::table('erp_availability')->where('unit_id',$unitId)->where($field,$id)->lockForUpdate()->get() as $window) {
                $day=(new DateTimeImmutable($start,new DateTimeZone('UTC')))->setTimezone($this->zone($window->timezone))->format('Y-m-d');
                if ($day < $window->starts_on || ($window->ends_on && $day > $window->ends_on) || (int)(new DateTimeImmutable($day))->format('N') !== (int)$window->weekday) continue;
                $ws=$this->localToUtc($day,$window->start_time,$window->timezone); $we=$this->localToUtc($day,$window->end_time,$window->timezone);
                if ($start >= $ws && $end <= $we) { $covered=true; break; }
            }
            if (!$covered) $this->fail('The entire lesson must be covered by both student and teacher availability.');
        }
    }
    private function agreementsFor($e,$except=null) {
        $q=DB::table('erp_agreements as a')->join('erp_enrollments as e','e.id','=','a.enrollment_id')->where('a.status','confirmed')->where(function($q) use ($e) { $q->where('e.teacher_id',$e->teacher_id)->orWhere('e.student_id',$e->student_id); })->select('a.*');
        if ($except) $q->where('a.id','<>',$except); return $q->lockForUpdate()->get();
    }
    private function agreementConflict($a,$start,$end,$exemptOccurrence=null) {
        $local=(new DateTimeImmutable($start,new DateTimeZone('UTC')))->setTimezone($this->zone($a->timezone));
        foreach ($this->occurrences($a,$local->modify('-1 day')->format('Y-m-d'),$local->modify('+1 day')->format('Y-m-d')) as [$os,$oe]) if ((!$exemptOccurrence || (int)$a->id !== (int)$exemptOccurrence[0] || $os !== $exemptOccurrence[1]) && $os < $end && $oe > $start) $this->fail('A confirmed recurring agreement overlaps globally for this teacher or student.');
    }
    private function conflicts($e,$start,$end,$exceptLesson=null,$exceptAgreement=null,$exemptOccurrence=null) {
        $q=DB::table('erp_lessons')->whereIn('status',['scheduled','completed','no_show'])->where('starts_at','<',$end)->where('ends_at','>',$start)->where(function($q) use ($e) { $q->where('teacher_id',$e->teacher_id)->orWhere('student_id',$e->student_id); });
        if ($exceptLesson) $q->where('id','<>',$exceptLesson);
        if ($q->lockForUpdate()->first()) $this->fail('Teacher or student has an overlapping lesson, possibly in another unit.');
        foreach ($this->agreementsFor($e,$exceptAgreement) as $a) $this->agreementConflict($a,$start,$end,$exemptOccurrence);
    }
    public function materialize(int $unitId, int $actorId, array $p) {
        $this->required($p,['agreement_id','from','through']);
        return DB::transaction(function () use ($unitId,$actorId,$p) {
            $a=$this->row('erp_agreements',$p['agreement_id'],$unitId); $e=$this->row('erp_enrollments',$a->enrollment_id,$unitId); $this->parents($unitId,$e->teacher_id,$e->student_id);
            $e=$this->row('erp_enrollments',$e->id,$unitId,true);
            $a=$this->row('erp_agreements',$a->id,$unitId,true);
            if ($a->status !== 'confirmed') $this->fail('Only bilaterally confirmed agreements generate lessons.');
            $from=$this->date($p['from']); $through=$this->date($p['through']);
            if ($through < $from || (new DateTimeImmutable($from))->diff(new DateTimeImmutable($through))->days > 366) $this->fail('Generate at most 366 days per request.');
            $created=[];
            foreach ($this->occurrences($a,max($from,$e->starts_on),min($through,$e->ends_on ?: '9999-12-31')) as [$start,$end]) {
                // Includes cancelled/rescheduled originals: generation must never resurrect them.
                $existing=DB::table('erp_lessons')->where('agreement_id',$a->id)->where('starts_at',$start)->lockForUpdate()->first();
                if ($existing) continue;
                $this->coverage($unitId,$e,$start,$end); $this->conflicts($e,$start,$end,null,$a->id);
                $created[]=$this->insert('erp_lessons',['unit_id'=>$unitId,'enrollment_id'=>$e->id,'agreement_id'=>$a->id,'student_id'=>$e->student_id,'teacher_id'=>$e->teacher_id,'subject_id'=>$e->subject_id,'starts_at'=>$start,'ends_at'=>$end,'status'=>'scheduled','kind'=>'regular','student_rate_paise'=>$e->student_rate_paise,'teacher_rate_paise'=>$e->teacher_rate_paise,'currency'=>$e->currency]);
            }
            $this->audit($unitId,$actorId,'lessons.materialized','agreement',$a->id,['count'=>count($created),'from'=>$from,'through'=>$through]); return $created;
        });
    }
    public function reschedule(int $unitId, int $actorId, array $p) {
        $this->required($p,['lesson_id','date','start_time','timezone','reason','teacher_confirmed','student_confirmed','confirmation_channel','confirmation_note']);
        return DB::transaction(function () use ($unitId,$actorId,$p) {
            $l=$this->row('erp_lessons',$p['lesson_id'],$unitId); $e=$this->row('erp_enrollments',$l->enrollment_id,$unitId); $this->parents($unitId,$l->teacher_id,$l->student_id); $l=$this->row('erp_lessons',$l->id,$unitId,true);
            $e=$this->row('erp_enrollments',$l->enrollment_id,$unitId,true);
            if ($l->status !== 'scheduled' || DB::table('erp_attendance')->where('lesson_id',$l->id)->lockForUpdate()->first()) $this->fail('Only scheduled lessons without attendance can be rescheduled.');
            if (!trim($p['reason'])) $this->fail('A rescheduling reason is required.');
            if (!in_array($p['teacher_confirmed'],[true,1,'1'],true) || !in_array($p['student_confirmed'],[true,1,'1'],true) || !in_array($p['confirmation_channel'],['whatsapp','call'],true) || !trim($p['confirmation_note'])) $this->fail('Record renewed bilateral WhatsApp/call confirmation for the replacement time.');
            $start=$this->localToUtc($p['date'],$p['start_time'],$p['timezone']); $end=gmdate('Y-m-d H:i:s',strtotime($start.' UTC')+strtotime($l->ends_at.' UTC')-strtotime($l->starts_at.' UTC'));
            $student=$this->row('erp_students',$e->student_id,$unitId,true); $effective=(new DateTimeImmutable($start,new DateTimeZone('UTC')))->setTimezone($this->zone($student->timezone))->format('Y-m-d');
            if ($effective < $e->starts_on || ($e->ends_on && $effective > $e->ends_on)) $this->fail('Replacement must remain within assignment dates.');
            if ($start === $l->starts_at) $this->fail('Choose a different replacement time.');
            $root=$l;
            while ($root->replaces_lesson_id) $root=$this->row('erp_lessons',$root->replaces_lesson_id,$unitId,true);
            $exempt=$root->agreement_id ? [$root->agreement_id,$root->starts_at] : null;
            $this->coverage($unitId,$e,$start,$end); $this->conflicts($e,$start,$end,$l->id,null,$exempt);
            DB::table('erp_lessons')->where('id',$l->id)->update(['status'=>'rescheduled','cancellation_reason'=>trim($p['reason']),'updated_at'=>$this->now()]);
            $next=$this->insert('erp_lessons',['unit_id'=>$unitId,'enrollment_id'=>$l->enrollment_id,'agreement_id'=>null,'student_id'=>$l->student_id,'teacher_id'=>$l->teacher_id,'subject_id'=>$l->subject_id,'starts_at'=>$start,'ends_at'=>$end,'status'=>'scheduled','kind'=>$l->kind,'replaces_lesson_id'=>$l->id,'student_rate_paise'=>$l->student_rate_paise,'teacher_rate_paise'=>$l->teacher_rate_paise,'currency'=>$l->currency]);
            $this->audit($unitId,$actorId,'lesson.rescheduled','lesson',$l->id,['replacement_id'=>$next->id,'reason'=>trim($p['reason']),'teacher_confirmed'=>true,'student_confirmed'=>true,'confirmation_channel'=>$p['confirmation_channel'],'confirmation_note'=>trim($p['confirmation_note']),'confirmed_at'=>$this->now(),'replacement_starts_at'=>$start]); return $next;
        });
    }
    public function cancel(int $unitId, int $actorId, array $p) {
        $this->required($p,['lesson_id','reason']);
        return DB::transaction(function () use ($unitId,$actorId,$p) {
            $l=$this->row('erp_lessons',$p['lesson_id'],$unitId); $this->parents($unitId,$l->teacher_id,$l->student_id); $l=$this->row('erp_lessons',$l->id,$unitId,true);
            $e=$this->row('erp_enrollments',$l->enrollment_id,$unitId,true);
            if ($l->status !== 'scheduled' || DB::table('erp_attendance')->where('lesson_id',$l->id)->lockForUpdate()->first()) $this->fail('Only scheduled lessons without attendance can be cancelled.');
            if (!trim($p['reason'])) $this->fail('A cancellation reason is required.');
            DB::table('erp_lessons')->where('id',$l->id)->update(['status'=>'cancelled','cancellation_reason'=>trim($p['reason']),'updated_at'=>$this->now()]);
            $this->audit($unitId,$actorId,'lesson.cancelled','lesson',$l->id,['reason'=>trim($p['reason'])]); return $this->row('erp_lessons',$l->id,$unitId);
        });
    }
}
