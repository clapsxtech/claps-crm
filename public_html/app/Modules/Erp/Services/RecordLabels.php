<?php
namespace App\Modules\Erp\Services;

use Illuminate\Support\Facades\DB;

/** Resolve only the IDs on visible register pages, in bounded batches. */
class RecordLabels
{
    public function forPages(int $unitId, array $pages): array
    {
        $ids=[];
        foreach($pages as $rows)foreach($rows as $row)foreach(['student_id','teacher_id','user_id','assigned_to','lesson_id','enrollment_id','attendance_id','other_attendance_id'] as $field){
            if(!empty($row->$field))$ids[$field][]=(int)$row->$field;
        }
        $labels=[];
        foreach(['student_id'=>['erp_students','s'],'teacher_id'=>['erp_teachers','t']] as $field=>[$table,$alias]){
            if(empty($ids[$field]))continue;
            $q=DB::table($table.' as '.$alias)->whereIn($alias.'.id',array_unique($ids[$field]));
            if($field==='student_id')$q->where('s.unit_id',$unitId);
            else $q->whereIn('t.id',DB::table('erp_teacher_units')->where('unit_id',$unitId)->select('teacher_id'));
            foreach($q->select($alias.'.id',$alias.'.name',$alias.'.code')->get() as $r)$labels[$field][$r->id]=$r->name.' · '.$r->code;
        }
        $users=array_unique(array_merge($ids['user_id']??[],$ids['assigned_to']??[]));
        if($users)foreach(DB::table('users')->where('business_id',config('erp.business_id'))->whereIn('id',$users)->select('id','name')->get() as $r){$labels['user_id'][$r->id]=$r->name;$labels['assigned_to'][$r->id]=$r->name;}
        foreach(['lesson_id'=>'erp_lessons','enrollment_id'=>'erp_enrollments'] as $field=>$table){
            if(empty($ids[$field]))continue;
            foreach($this->parties($table,$unitId)->whereIn('p.id',array_unique($ids[$field]))->select('p.id','s.name as student','t.name as teacher','subject.name as subject')->get() as $r)$labels[$field][$r->id]=$r->student.' / '.$r->teacher.' · '.$r->subject;
        }
        $attendance=array_unique(array_merge($ids['attendance_id']??[],$ids['other_attendance_id']??[]));
        if($attendance){
            $q=$this->parties('erp_lessons',$unitId)->join('erp_attendance as attendance',function($j){$j->on('attendance.lesson_id','=','p.id')->on('attendance.unit_id','=','p.unit_id');})->whereIn('attendance.id',$attendance);
            foreach($q->select('attendance.id','s.name as student','t.name as teacher','subject.name as subject')->get() as $r){$label=$r->student.' / '.$r->teacher.' · '.$r->subject;$labels['attendance_id'][$r->id]=$label;$labels['other_attendance_id'][$r->id]=$label;}
        }
        return $labels;
    }
    private function parties(string $table,int $unitId)
    {
        return DB::table($table.' as p')->where('p.unit_id',$unitId)
            ->join('erp_students as s',function($j){$j->on('s.id','=','p.student_id')->on('s.unit_id','=','p.unit_id');})
            ->join('erp_teachers as t','t.id','=','p.teacher_id')->join('erp_subjects as subject','subject.id','=','p.subject_id')
            ->whereIn('t.id',DB::table('erp_teacher_units')->where('unit_id',$unitId)->select('teacher_id'));
    }
}
