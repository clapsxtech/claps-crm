<?php
namespace App\Modules\Erp\Services;
use Illuminate\Support\Facades\DB;
class Reporting
{
    public function datasets(): array
    {
        return [
            'students'=>['id','code','name','registered_on','status','grade','syllabus','timezone'],
            'enrollments'=>['id','student_id','teacher_id','subject_id','starts_on','ends_on','student_rate_paise','teacher_rate_paise','currency','status'],
            'lessons'=>['id','enrollment_id','student_id','teacher_id','subject_id','starts_at','ends_at','status','kind','replaces_lesson_id','student_rate_paise','teacher_rate_paise','currency'],
            'attendance'=>['id','lesson_id','actual_minutes','status','submitted_by','reviewed_by','reviewed_at'],
            'ledger'=>['id','student_id','teacher_id','attendance_id','kind','amount_paise','currency','reference','reverses_id','occurred_at','created_by'],
            'tasks'=>['id','student_id','assigned_to','title','due_on','status','created_by'],
            'marks'=>['id','enrollment_id','assessment','assessed_on','score','maximum'],
            'payment_requests'=>['id','student_id','reference','amount_paise','status','requested_on','due_on'],
            'migration_history'=>['id','batch_id','enrollment_id','external_id','starts_at','actual_minutes','status','financial_effect'],
        ];
    }
    public function rows(int $unitId,string $dataset)
    {
        abort_unless(isset($this->datasets()[$dataset]),404);
        return DB::table('erp_'.$dataset)->where('unit_id',$unitId)->orderBy('id')->select($this->datasets()[$dataset]);
    }
    public function safeCsvValue($value)
    {
        if($value===null)return '';
        if(is_string($value)&&!is_numeric($value)&&preg_match('/^(?:[\t\r\n]|[\s\x00-\x1f]*[=+\-@])/u',$value))return "'".$value;
        return $value;
    }
    public function reconciliation(int $unitId): array
    {
        $counts=[];foreach(array_keys($this->datasets()) as $table)$counts[$table]=DB::table('erp_'.$table)->where('unit_id',$unitId)->count();
        $ledger=DB::table('erp_ledger')->where('unit_id',$unitId)->select('kind','currency',DB::raw('COUNT(*) AS entries'),DB::raw('SUM(amount_paise) AS amount_paise'))->groupBy('kind','currency')->orderBy('kind')->get();
        return ['unit_id'=>$unitId,'generated_at_utc'=>now('UTC')->toIso8601String(),'counts'=>$counts,'ledger_by_kind'=>$ledger,'attendance_minutes'=>(int)DB::table('erp_attendance')->where('unit_id',$unitId)->where('status','!=','rejected')->sum('actual_minutes'),'archived_historical_minutes'=>(int)DB::table('erp_migration_history')->where('unit_id',$unitId)->sum('actual_minutes'),'pending_duplicate_reviews'=>DB::table('erp_duplicate_reviews')->where('unit_id',$unitId)->where('status','pending')->count(),'writes'=>0,'source_reconciled'=>false,'notice'=>'Application totals only. Compare with approved source totals and cutoff; this does not certify migration or authorize cutover.'];
    }
}
