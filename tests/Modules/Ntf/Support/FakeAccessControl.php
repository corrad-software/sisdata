<?php

namespace Tests\Modules\Ntf\Support;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;

/**
 * Test double so MOD-NTF tests don't depend on MOD-RBC's seeded roles:
 * explicit permissions, roles and scopes per user id.
 */
class FakeAccessControl implements AccessControl
{
    /** @var array<int, list<string>> */
    public array $permissions = [];

    /** @var array<int, list<string>> */
    public array $roles = [];

    /** @var array<int, array<string, list<string>>> */
    public array $scopes = [];

    /** @param list<string> $permissions */
    public function grant(User $user, array $permissions): static
    {
        $this->permissions[$user->id] = array_values(array_unique([...($this->permissions[$user->id] ?? []), ...$permissions]));

        return $this;
    }

    /** @param list<string> $roles */
    public function assignRoles(User $user, array $roles): static
    {
        $this->roles[$user->id] = $roles;

        return $this;
    }

    /** @param list<string> $values */
    public function scopeTo(User $user, string $type, array $values): static
    {
        $this->scopes[$user->id][$type] = $values;

        return $this;
    }

    public function allows(User $user, string $permission): bool
    {
        return in_array($permission, $this->permissions[$user->id] ?? [], true);
    }

    public function scope(User $user, string $type): ?array
    {
        return $this->scopes[$user->id][$type] ?? null;
    }

    public function roles(User $user): array
    {
        return $this->roles[$user->id] ?? [];
    }

    public function permissions(User $user): array
    {
        return $this->permissions[$user->id] ?? [];
    }
}
