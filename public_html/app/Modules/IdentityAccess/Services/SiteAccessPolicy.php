<?php

namespace App\Modules\IdentityAccess\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Single-deployment organization boundary, not row-level multitenancy.
 * Academic records currently have no business_id; only users belonging to the
 * configured deployment organization may use authenticated reports/admin tools.
 */
class SiteAccessPolicy
{
    public function isSiteMember(?User $user): bool
    {
        $siteId = filter_var(config('erp.business_id', 1), FILTER_VALIDATE_INT);
        $userSiteId = $user ? filter_var($user->business_id, FILTER_VALIDATE_INT) : false;

        return $user !== null && $siteId !== false && $siteId > 0
            && $userSiteId === $siteId && (int) $user->status === 1
            && $user->deleted_at === null;
    }

    public function assertSiteMember(?User $user): void
    {
        if (!$this->isSiteMember($user)) {
            throw new AuthorizationException('Access to this organization is required.');
        }
    }

    public function isAdministrator(?User $user): bool
    {
        if (!$this->isSiteMember($user)) {
            return false;
        }

        // Retain only the application's existing administrator checks.
        return (int) $user->getKey() === 1
            || strtolower((string) $user->user_type) === 'admin'
            || $user->hasRole('Admin')
            || $user->can('administration');
    }

    public function assertAdministrator(?User $user): void
    {
        if (!$this->isAdministrator($user)) {
            throw new AuthorizationException('Administrator access is required.');
        }
    }
}
