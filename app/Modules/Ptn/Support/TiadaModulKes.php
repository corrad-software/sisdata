<?php

namespace App\Modules\Ptn\Support;

use App\Models\User;
use App\Modules\Ptn\Contracts\LegalCaseIntake;
use RuntimeException;

/** LegalCaseIntake until MOD-KES (G4) exists: escalation to a court case is not available yet. */
class TiadaModulKes implements LegalCaseIntake
{
    public function available(): bool
    {
        return false;
    }

    public function open(array $data, User $by, string $sourceRef): string
    {
        throw new RuntimeException((string) __('ptn::ptn.kes_tiada'));
    }
}
