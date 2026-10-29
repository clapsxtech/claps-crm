<?php
namespace App\Modules\Erp\Services;
use Illuminate\Support\Facades\DB;
class Reporting
{
    /** Read-only dashboard projections. Never use these snapshots to authorize financial writes. */
    public function teacherStatements(int $unitId,array $teacherIds): array
    {
        $rows=DB::table('erp_ledger as l')->leftJoin('erp_ledger as original',function($j){$j->on('original.id','=','l.reverses_id')->on('original.unit_id','=','l.unit_id')->on('original.teacher_id','=','l.teacher_id');})
            ->where('l.unit_id',$unitId)->whereIn('l.teacher_id',$teacherIds)
            ->select('l.teacher_id')->selectRaw("SUM(l.amount_paise) AS net, SUM(CASE WHEN (CASE WHEN l.kind = 'reversal' THEN original.kind ELSE l.kind END) = 'teacher_accrual' THEN l.amount_paise ELSE 0 END) AS earned, SUM(CASE WHEN (CASE WHEN l.kind = 'reversal' THEN original.kind ELSE l.kind END) = 'teacher_payment' THEN -l.amount_paise ELSE 0 END) AS paid")
            ->groupBy('l.teacher_id')->get()->keyBy('teacher_id');
        $result=[];foreach($teacherIds as $id){$r=$rows->get($id);$earned=(int)($r->earned??0);$paid=(int)($r->paid??0);$net=(int)($r->net??0);$result[$id]=['earned_paise'=>$earned,'paid_paise'=>$paid,'other_adjustments_paise'=>$net-$earned+$paid,'net_payable_paise'=>$net];}return $result;
    }
    public function requestSummaries(int $unitId,array $requestIds): array
    {
        $coverage=DB::table('erp_request_allocations as a')->join('erp_payment_requests as request',function($j){$j->on('request.id','=','a.request_id')->on('request.unit_id','=','a.unit_id');})
            ->join('erp_ledger as payment',function($j){$j->on('payment.id','=','a.payment_id')->on('payment.unit_id','=','a.unit_id')->on('payment.student_id','=','request.student_id');})
            ->where('a.unit_id',$unitId)->whereIn('a.request_id',$requestIds)->where('payment.kind','payment')
            ->whereNotExists(function($q){$q->selectRaw('1')->from('erp_ledger as reversal')->whereColumn('reversal.reverses_id','payment.id');})
            ->select('a.request_id')->selectRaw('SUM(a.amount_paise) AS covered')->groupBy('a.request_id');
        $rows=DB::table('erp_payment_requests as r')->leftJoinSub($coverage,'coverage',function($j){$j->on('coverage.request_id','=','r.id');})
            ->where('r.unit_id',$unitId)->whereIn('r.id',$requestIds)->select('r.id','r.amount_paise','r.status',DB::raw('COALESCE(coverage.covered,0) AS covered'))->get();
        $result=[];foreach($rows as $r){$amount=(int)$r->amount_paise;$covered=(int)$r->covered;$result[$r->id]=['requested_paise'=>$amount,'covered_paise'=>$covered,'outstanding_paise'=>max(0,$amount-$covered),'status'=>$r->status==='cancelled'?'cancelled':($covered>=$amount?'fulfilled':'pending')];}return $result;
    }
    public function datasets(): array
    {
        return [
            'students'=>['id','code','name','registered_on','status','grade','syllabus','timezone'],
            'enrollments'=>['id','student_id','teacher_id','subject_id','starts_on','ends_on','student_rate_paise','teacher_rate_paise','currency','status'],
            'lessons'=>['id','enrollment_id','student_id','teacher_id','subject_id','starts_at','ends_at','status','kind','replaces_lesson_id','student_rate_paise','teacher_rate_paise','currency'],
            'attendance'=>['id','lesson_id','actual_minutes','actual_starts_at','submitted_late','parent_review_status','parent_deadline_at','status','submitted_by','reviewed_by','reviewed_at'],
            'ledger'=>['id','student_id','teacher_id','attendance_id','kind','amount_paise','currency','reference','reverses_id','occurred_at','created_by'],
            'tasks'=>['id','student_id','assigned_to','title','due_on','status','created_by'],
            'marks'=>['id','enrollment_id','assessment','assessed_on','score','maximum'],
            'payment_requests'=>['id','student_id','reference','amount_paise','status','requested_on','due_on'],
            'leads'=>['id','external_id','name','phone','email','enquiry_date','status','grade','subject','source','assigned_to','created_by','is_hot','next_follow_up_on','is_post_demo','student_id','joined_on','admission_code'],
            'lead_activities'=>['id','lead_id','actor_id','kind','channel','outcome','summary','occurred_at'],
            'demos'=>['id','lead_id','student_id','teacher_id','subject_id','scheduled_on','scheduled_time','status','meet_link','created_by','completed_at'],
            'lead_reminders'=>['id','lead_id','due_on','due_time','text','is_completed','completed_at','created_by'],
            'lead_transfers'=>['id','lead_id','from_user_id','to_user_id','status','resolved_at'],
            'teacher_enquiries'=>['id','phone','enquiry_date','source','staff_id','notes'],
            'student_profiles'=>['id','student_id','whatsapp_number','student_contact','country','source','section','hour_rate_paise','email','date_of_birth','father_name','mother_name','school','preferred_medium','preferred_time','network_connectivity','source_external_id'],
            'teacher_profiles'=>['id','teacher_id','whatsapp_number','date_of_birth','category','teacher_type','medium','source_external_id'],
            'invoices'=>['id','student_id','invoice_number','status','issue_date','due_date','currency','subtotal_paise','discount_paise','tax_paise','total_paise','billing_name','billing_contact','created_by'],
            'invoice_lines'=>['id','invoice_id','description','quantity','unit_rate_paise','amount_paise'],
            'task_templates'=>['id','department','name','default_points','active','created_by'],
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
