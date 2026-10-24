<?php

namespace App\Support\Fallbacks;

use App\Modules\Aud\Contracts\AuditLogger;

/** Stand-in for AuditLogger until its owning module binds the real one. See App\Support\Fallbacks. */
class FallbackAuditLogger implements AuditLogger
{
    public function record(string $action, string $module, ?string $entityType = null, string|int|null $entityId = null, string $status = 'berjaya', ?string $reason = null, array $changes = [], array $meta = []): void {}
}
