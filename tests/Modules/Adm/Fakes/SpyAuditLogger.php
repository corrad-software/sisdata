<?php

namespace Tests\Modules\Adm\Fakes;

use App\Modules\Aud\Contracts\AuditLogger;

class SpyAuditLogger implements AuditLogger
{
    /** @var list<array<string, mixed>> */
    public array $records = [];

    public function record(string $action, string $module, ?string $entityType = null, string|int|null $entityId = null, string $status = 'berjaya', ?string $reason = null, array $changes = [], array $meta = []): void
    {
        $this->records[] = compact('action', 'module', 'entityType', 'entityId', 'status', 'reason', 'changes', 'meta');
    }

    /** @return list<array<string, mixed>> */
    public function ofAction(string $action): array
    {
        return array_values(array_filter($this->records, fn ($r) => $r['action'] === $action));
    }
}
