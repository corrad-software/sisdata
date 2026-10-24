<?php

namespace Tests\Modules\Aud\Concerns;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;

/**
 * Test double for MOD-RBC's AccessControl: grants permission keys and role
 * codes per user id (or to everyone with '*').
 */
class FakeAccessControl implements AccessControl
{
    /** @var array<int|string, list<string>> */
    public array $permissions = [];

    /** @var array<int|string, list<string>> */
    public array $roles = [];

    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $roles
     */
    public function grant(User|string $user, array $permissions, array $roles = []): static
    {
        $key = $user instanceof User ? $user->getKey() : $user;
        $this->permissions[$key] = $permissions;
        $this->roles[$key] = $roles;

        return $this;
    }

    public function allows(User $user, string $permission): bool
    {
        return in_array($permission, $this->permissionsFor($user), true);
    }

    public function scope(User $user, string $type): ?array
    {
        return null;
    }

    public function roles(User $user): array
    {
        return $this->roles[$user->getKey()] ?? $this->roles['*'] ?? [];
    }

    public function permissions(User $user): array
    {
        return $this->permissionsFor($user);
    }

    /**
     * @return list<string>
     */
    private function permissionsFor(User $user): array
    {
        return $this->permissions[$user->getKey()] ?? $this->permissions['*'] ?? [];
    }
}
