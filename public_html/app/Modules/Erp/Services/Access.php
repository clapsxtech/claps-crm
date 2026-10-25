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
        abort_unless(DB::table('erp_units')->where('id',$unitId)->exists(),404);
        if (DB::table('erp_units')->where('id',$unitId)->whereNotNull('archived_at')->exists()) {
            abort_if($write,403,'Archived teams are read-only. Restore this team to make changes.');
            abort_unless((new SiteAccessPolicy())->isAdministrator($user),403,'This team is archived.');
        }
        if ((new SiteAccessPolicy())->isAdministrator($user)) return;
        abort_unless(DB::table('erp_memberships')->where('user_id',$user->id)->where('unit_id',$unitId)->whereIn('role',array_merge(['admin','staff'], TeamPermissions::ROLES))->exists(),403,'Assigned team membership required.');
    }
    public function units(User $user)
    {
        (new SiteAccessPolicy())->assertSiteMember($user);
        if ((new SiteAccessPolicy())->isAdministrator($user)) return DB::table('erp_units')->get();
        return DB::table('erp_units')->join('erp_memberships','erp_memberships.unit_id','=','erp_units.id')->where('erp_memberships.user_id',$user->id)->whereIn('erp_memberships.role',array_merge(['admin','staff'], TeamPermissions::ROLES))->whereNull('erp_units.archived_at')->select('erp_units.*')->get();
    }
}
