<?php

namespace App\Modules\Aud\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** SRS-AUD-07: the hash chain check found a mismatch. MOD-NTF alerts admins + auditors. */
class AuditIntegrityBroken
{
    use Dispatchable;

    public function __construct(public int $firstBadSequence, public string $detail) {}
}
