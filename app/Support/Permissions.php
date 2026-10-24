<?php

namespace App\Support;

use App\Models\User;

/**
 * @deprecated MOD-RBC is live: permissions come from
 * App\Modules\Rbc\Contracts\AccessControl::permissions(). This legacy map
 * remains only for the fallback AccessControl in App\Support\Fallbacks (used
 * when MOD-RBC is not enabled, e.g. a worktree building another module).
 * Nothing else should call it.
 */
class Permissions
{
    public const ADMIN = ['users.manage', 'settings.manage', 'platform.view'];

    /**
     * @return list<string>
     */
    public static function for(User $user): array
    {
        return $user->role === 'admin' ? self::ADMIN : [];
    }
}
