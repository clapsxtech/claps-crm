<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Modules\Erp\Services\Audit;
use App\Modules\IdentityAccess\Services\SiteAccessPolicy;
class GrantErpMembership extends Command
{
    protected $signature='erp:grant-membership {unit : AA or BB} {user : Existing user ID} {role : staff, admin or teacher}';
    protected $description='Explicit deployment-operator grant of unit membership; no automatic teacher identity matching.';
    public function handle()
    {
        $unit=DB::table('erp_units')->where('code',strtoupper($this->argument('unit')))->first();$user=User::find($this->argument('user'));$role=$this->argument('role');
        if(!$unit||!(new SiteAccessPolicy())->isSiteMember($user)||!in_array($role,['staff','admin','teacher'],true)){$this->error('Existing unit, active site user, and admin/teacher role required.');return 1;}
        if($role==='teacher'&&DB::table('erp_memberships')->where('user_id',$user->id)->where('unit_id','!=',$unit->id)->exists()){$this->error('Teacher accounts must belong to one business unit only.');return 1;}
        DB::transaction(function()use($unit,$user,$role){DB::table('erp_memberships')->updateOrInsert(['unit_id'=>$unit->id,'user_id'=>$user->id],['role'=>$role,'created_at'=>now(),'updated_at'=>now()]);Audit::record($unit->id,null,'membership.operator_granted','user',$user->id,['role'=>$role]);});$this->info('Membership granted explicitly.');return 0;
    }
}
