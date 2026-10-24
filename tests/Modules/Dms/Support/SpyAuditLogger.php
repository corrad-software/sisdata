<?php

namespace Tests\Modules\Dms\Support;

use App\Modules\Aud\Contracts\AuditLogger;

class SpyAuditLogger implements AuditLogger
{
    /** @var list<array<string, mixed>> */
    public array $entries = [];

    public function record(string $action, string $module, ?string $entityType = null, string|int|null $entityId = null, string $status = 'berjaya', ?string $reason = null, array $changes = [], array $meta = []): void
    {
        $this->entries[] = compact('action', 'module', 'entityType', 'entityId', 'status', 'reason', 'changes', 'meta');
    }

    /** @return list<array<string, mixed>> */
    public function of(string $action, string|int|null $entityId = null): array
    {
        return array_values(array_filter($this->entries, fn ($e) => $e['action'] === $action && ($entityId === null || (string) $e['entityId'] === (string) $entityId)));
    }
}
