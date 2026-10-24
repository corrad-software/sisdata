<?php

namespace Tests\Modules\Dms\Support;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;

/** Test double for MOD-RBC: explicit permissions, scopes and roles per user. */
class FakeAccessControl implements AccessControl
{
    /** @var array<int, list<string>> */
    public array $permissions = [];

    /** @var array<int, array<string, list<string>|null>> */
    public array $scopes = [];

    /** @var array<int, list<string>> */
    public array $roles = [];

    public function grant(User $user, string ...$permissions): void
    {
        $this->permissions[$user->id] = array_values(array_unique([...($this->permissions[$user->id] ?? []), ...$permissions]));
    }

    /** @param list<string>|null $values */
    public function scopeTo(User $user, string $type, ?array $values): void
    {
        $this->scopes[$user->id][$type] = $values;
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
