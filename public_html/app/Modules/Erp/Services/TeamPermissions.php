<?php
namespace App\Modules\Erp\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Services\SiteAccessPolicy;
use Illuminate\Support\Facades\DB;

/** One role matrix for navigation, exports and HTTP mutations. Unknown actions fail closed. */
class TeamPermissions
{
    public const ROLES = ['team_manager','academics','finance','sales_followups','read_only'];
    public const LABELS = ['team_manager'=>'Team manager','academics'=>'Academics / attendance','finance'=>'Finance','sales_followups'=>'Sales / follow-ups','read_only'=>'Read-only'];
    private const PAGES = [
        'team_manager'=>['dashboard','admissions','teacher-recruitment','allocation','people','academics','timetable','attendance','money','followups','migration'],
        'academics'=>['teacher-recruitment','allocation','people','academics','timetable','attendance'],
        'finance'=>['money'],
        'sales_followups'=>['admissions','teacher-recruitment','allocation','followups'],
        'read_only'=>['dashboard','admissions','teacher-recruitment','allocation','people','academics','timetable','attendance','money','followups','migration'],
    ];
    private const ACTIONS = [
        'academics'=>['student','student-update','teacher','eligibility','mark','assign','end-assignment','availability','propose','confirm','materialize','reschedule','cancel','extra','exception','end-agreement','attendance','approve','correct','duplicate'],
        'finance'=>['payment','import-batch','salary-paid','salary-reverse','receipt-reverse','receipt-correct','request-create','request-cancel','request-allocate'],
        'sales_followups'=>['task','complete-task','task-update','task-item','task-item-complete','followup','admission-create-lead','admission-update-lead','admission-schedule-demo','admission-update-demo','admission-followup','admission-reminder','admission-complete-reminder','admission-transfer','admission-resolve-transfer','admission-convert'],
    ];
    public function role(User $user, int $unit): ?string
    {
        (new SiteAccessPolicy())->assertSiteMember($user);
        if ((new SiteAccessPolicy())->isAdministrator($user)) return 'superadmin';
        return DB::table('erp_memberships')->where('unit_id',$unit)->where('user_id',$user->id)->value('role');
    }
    public function allows(User $user, int $unit, string $kind, string $value): bool
    {
        $role=$this->role($user,$unit);
        if ($role==='superadmin') return true;
        if (in_array($role,['admin','staff'],true)) $role='team_manager';
        if (!isset(self::PAGES[$role ?? ''])) return false;
        if ($kind==='detail') return in_array($role,['team_manager','read_only'],true);
        if ($kind==='page') return in_array($value,self::PAGES[$role],true);
        if ($kind==='action') {
            if ($role==='read_only') return false;
            if(in_array($value,['allocation-demo','allocation-feedback'],true))return in_array($role,['team_manager','academics','sales_followups'],true);
            if(in_array($value,['allocation-qualify','allocation-window','allocation-regular'],true))return in_array($role,['team_manager','academics'],true);
            if (str_starts_with($value,'teacher-candidate-')) return $role==='team_manager' && in_array($value,['teacher-candidate-create','teacher-candidate-update','teacher-candidate-contact','teacher-candidate-register','teacher-candidate-approve','teacher-candidate-resolve'],true);
            $actions=$role==='team_manager'?array_merge(...array_values(self::ACTIONS)):(self::ACTIONS[$role]??[]);
            return in_array($value,$actions,true);
        }
        if ($kind==='lookup') {
            $common=['students','teachers','subjects'];
            $sets=['academics'=>['enrollments','lessons','agreements','attendance','reviews'], 'finance'=>['requests','payments','salary','attendance'], 'sales_followups'=>['tasks','items','members']];
            return in_array($value,array_merge($common,...array_values(in_array($role,['team_manager','read_only'],true)?$sets:[$sets[$role]??[]])),true);
        }
        if ($kind==='export') {
            if (in_array($role,['team_manager','read_only'],true)) return true;
            $sets=['academics'=>['marks','enrollments','lessons','attendance'], 'finance'=>['ledger','payment_requests'], 'sales_followups'=>['tasks']];
            return in_array($value,$sets[$role]??[],true);
        }
        return false;
    }
    public function landing(User $user,int $unit): string
    {
        foreach (['dashboard','admissions','academics','money','followups'] as $page) if($this->allows($user,$unit,'page',$page)) return $page;
        return 'dashboard';
    }
}
