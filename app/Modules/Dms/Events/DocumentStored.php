<?php

namespace App\Modules\Dms\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A document (or a new version of it) passed every check and was stored.
 * Dispatched after commit; e.g. MOD-KBA can index it.
 */
class DocumentStored
{
    use Dispatchable;

    public function __construct(
        public string $documentId,
        public int $version,
        public ?string $ownerType,
        public ?string $ownerId,
        public string $classification,
    ) {}
}
