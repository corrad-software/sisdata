<?php

namespace App\Support;

use App\Models\User;

/**
 * Interim permission list until MOD-RBC (G0) replaces it with the real
 * peranan/peranan_kebenaran tables. Only exists so the API can tell the
 * frontend which menu items to show — the API itself still enforces access
 * through middleware, never through this list.
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
