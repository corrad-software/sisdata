<?php

namespace Tests\Modules\Tsu\Support;

use App\Models\User;
use App\Modules\Wfe\Contracts\ApproverDirectory;

class FakeApproverDirectory implements ApproverDirectory
{
    /** @var array<int, list<int>> subordinate id => supervisor ids */
    public array $supervisors = [];

    public function usersWithRole(string $roleCode): array
    {
        return [];
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
