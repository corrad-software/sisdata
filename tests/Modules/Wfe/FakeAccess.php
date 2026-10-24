<?php

namespace Tests\Modules\Wfe;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;

/** Test double for RBC's AccessControl: explicit grants per user. */
class FakeAccess implements AccessControl
{
    /** @var array<int, array{permissions: list<string>, roles: list<string>, districts: list<string>|null}> */
    private array $grants = [];

    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $roles
     * @param  list<string>|null  $districts
     */
    public function grant(User $user, array $permissions, array $roles = [], ?array $districts = null): void
    {
        $this->grants[$user->id] = ['permissions' => $permissions, 'roles' => $roles, 'districts' => $districts];
    }

    public function allows(User $user, string $permission): bool
    {
        return in_array($permission, $this->grants[$user->id]['permissions'] ?? [], true);
    }

    public function scope(User $user, string $type): ?array
    {
        return $type === self::SCOPE_DAERAH ? ($this->grants[$user->id]['districts'] ?? null) : null;
    }

    public function roles(User $user): array
    {
        return $this->grants[$user->id]['roles'] ?? [];
    }

    public function permissions(User $user): array
    {
        return $this->grants[$user->id]['permissions'] ?? [];
    }
}
