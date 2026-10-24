<?php

namespace App\Support\Fallbacks;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Support\Permissions;

/** Stand-in for AccessControl until its owning module binds the real one. See App\Support\Fallbacks. */
class FallbackAccessControl implements AccessControl
{
    // Without MOD-RBC: legacy admins may do everything, others nothing.
    public function allows(User $user, string $permission): bool
    {
        return $user->role === 'admin';
    }

    public function scope(User $user, string $type): ?array
    {
        return null;
    }

    public function roles(User $user): array
    {
        return [$user->role];
    }

    public function permissions(User $user): array
    {
        if ($user->role !== 'admin') {
            return [];
        }

        $declared = [];
        foreach (app(PermissionRegistry::class)->all() as $module) {
            array_push($declared, ...array_keys($module['permissions']));
        }

        return array_values(array_unique([...Permissions::for($user), ...$declared]));
    }
}
