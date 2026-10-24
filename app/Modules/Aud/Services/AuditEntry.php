<?php

namespace App\Modules\Aud\Services;

use App\Models\User;

/** One audit entry before it is chained and stored. */
final class AuditEntry
{
    /**
     * @param  list<array{0: string, 1: ?string, 2: ?string, 3: bool}>  $changes  [field, old, new, masked]
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $action,
        public string $module,
        public ?string $entityType = null,
        public ?string $entityId = null,
        public string $status = 'berjaya',
        public ?string $reason = null,
        public array $changes = [],
        public array $meta = [],
        public ?string $classification = null,
        public ?User $actor = null,
        public ?string $actorIdentifier = null,
    ) {}
}
