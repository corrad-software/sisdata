<?php

namespace Tests\Modules\Adm\Fakes;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;

/** Explicit per-user permissions and daerah scope, standing in for MOD-RBC. */
class FakeAccessControl implements AccessControl
{
    /**
     * @param  array<int, list<string>>  $permissions  user id => permission keys ('*' = all)
     * @param  array<int, list<string>>  $daerah  user id => allowed daerah codes (absent = unrestricted)
     */
    public function __construct(public array $permissions = [], public array $daerah = []) {}

    public function allows(User $user, string $permission): bool
    {
        $granted = $this->permissions[$user->id] ?? [];

        return in_array('*', $granted, true) || in_array($permission, $granted, true);
    }

    public function scope(User $user, string $type): ?array
    {
        return $type === self::SCOPE_DAERAH ? ($this->daerah[$user->id] ?? null) : null;
    }

    public function roles(User $user): array
    {
        return [];
    }

    public function permissions(User $user): array
    {
        return $this->permissions[$user->id] ?? [];
    }
}
