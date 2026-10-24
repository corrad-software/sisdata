<?php

namespace App\Modules\Dms\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** SRS-DMS-23: an archived document was disposed after two-stage approval (file content destroyed, metadata kept). */
class DocumentDisposed
{
    use Dispatchable;

    public function __construct(public string $documentId, public ?string $ownerType, public ?string $ownerId) {}
}
