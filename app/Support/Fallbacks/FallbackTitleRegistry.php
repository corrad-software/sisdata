<?php

namespace App\Support\Fallbacks;

use App\Models\User;
use App\Modules\Hkm\Contracts\TitleRegistry;
use RuntimeException;

/** Stand-in for TitleRegistry until MOD-HKM binds the real one. Titles can't be created without HKM. */
class FallbackTitleRegistry implements TitleRegistry
{
    public function registerFromAcquisition(string $lotId, array $data, User $actor, ?array $vault = null, ?string $sourceRef = null): string
    {
        throw new RuntimeException('MOD-HKM is not enabled: cannot register a title.');
    }

    public function forLot(string $lotId, User $user): array
    {
        return [];
    }
}
