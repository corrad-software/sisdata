<?php

namespace App\Modules\Aud\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * SRS-AUD-26: an audit entry could not be written. The business transaction
 * went ahead; the entry was spooled to disk for `aud:ulang-spool` and logged
 * as an incident. MOD-NTF alerts Pentadbir Sistem.
 */
class AuditWriteFailed
{
    use Dispatchable;

    public function __construct(public string $action, public string $module, public string $error) {}
}
