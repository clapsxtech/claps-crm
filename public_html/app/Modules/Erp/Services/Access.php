<?php
namespace App\Modules\Erp\Services;
use App\Models\User;
use App\Modules\IdentityAccess\Services\SiteAccessPolicy;
use Illuminate\Support\Facades\DB;
class Access
{
    public function unit(User $user, int $unitId, bool $write = true): void
    {
        (new SiteAccessPolicy())->assertSiteMember($user);
        // No implicit cross-unit access, even for an installation administrator.
        abort_unless(DB::table('erp_memberships')->where('user_id',$user->id)->where('unit_id',$unitId)->where('role','admin')->exists(),403,'Unit administrator membership required.');
    }
    public function units(User $user)
    {
        (new SiteAccessPolicy())->assertSiteMember($user);
        return DB::table('erp_units')->join('erp_memberships','erp_memberships.unit_id','=','erp_units.id')->where('erp_memberships.user_id',$user->id)->where('erp_memberships.role','admin')->select('erp_units.*')->get();
    }
}
