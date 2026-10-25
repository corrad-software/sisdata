<?php

namespace App\Support\Fallbacks;

use App\Models\User;
use App\Modules\Tkt\Contracts\TicketIntake;
use RuntimeException;

/** Stand-in for TicketIntake until MOD-TKT binds the real one. */
class FallbackTicketIntake implements TicketIntake
{
    public function open(array $data, string $channel, ?User $reporter = null, ?string $sourceRef = null): string
    {
        throw new RuntimeException('MOD-TKT is not enabled: cannot open a ticket.');
    }
}
