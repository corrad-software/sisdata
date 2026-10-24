<?php

namespace App\Support\Fallbacks;

use App\Models\User;
use App\Modules\Adu\Contracts\ComplaintIntake;
use RuntimeException;

/** Stand-in for ComplaintIntake until MOD-ADU binds the real one. */
class FallbackComplaintIntake implements ComplaintIntake
{
    public function open(array $data, string $channel, ?User $reporter = null, ?string $sourceRef = null): string
    {
        throw new RuntimeException('MOD-ADU is not enabled: cannot open a complaint.');
    }
}
