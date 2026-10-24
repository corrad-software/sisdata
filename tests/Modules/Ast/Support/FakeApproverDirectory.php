<?php

namespace Tests\Modules\Ast\Support;

use App\Models\User;
use App\Modules\Wfe\Contracts\ApproverDirectory;

class FakeApproverDirectory implements ApproverDirectory
{
    /** @var array<int, list<int>> subordinate id => supervisor ids */
    public array $supervisors = [];

    /** @var array<string, list<int>> role code => user ids */
    public array $roles = [];

    public function usersWithRole(string $roleCode): array
    {
        return User::query()->whereIn('id', $this->roles[$roleCode] ?? [])->get()->all();
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
