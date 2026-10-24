<?php

namespace Tests\Modules\Per\Support;

use App\Models\User;
use App\Modules\Wfe\Contracts\ApproverDirectory;

/** Supervisor hierarchy for escalation tests: everyone reports to $head. */
class FakeApproverDirectory implements ApproverDirectory
{
    public ?User $head = null;

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
        return $this->head !== null && $this->head->id !== $user->id ? [$this->head] : [];
    }
}
