<?php

namespace Tests\Modules\Adu\Support;

use App\Models\User;
use App\Modules\Wfe\Contracts\ApproverDirectory;

/** Explicit role holders and supervisor hierarchy for MOD-ADU tests. */
class FakeApproverDirectory implements ApproverDirectory
{
    /** @var array<string, list<int>> */
    public array $roles = [];

    /** @var array<int, list<int>> subordinate id => supervisor ids */
    public array $supervisors = [];

    public function usersWithRole(string $roleCode): array
    {
        return User::query()->whereIn('id', $this->roles[$roleCode] ?? [])->where('is_active', true)->orderBy('id')->get()->all();
    }

    public function usersWithPosition(string $positionCode): array
    {
        return [];
    }

    public function positionOf(User $user): ?string
    {
        return null;
    }

    public function supervisorsOf(User $user): array
    {
        return User::query()->whereIn('id', $this->supervisors[$user->id] ?? [])->get()->all();
    }
}
