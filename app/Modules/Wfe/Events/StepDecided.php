<?php

namespace App\Modules\Wfe\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An approver acted on a workflow task (SRS-WFE-16).
 *
 * @param  string  $action  lulus | tolak | kembali | tangguh | selesai (follow-up)
 */
class StepDecided
{
    use Dispatchable;

    public function __construct(
        public string $instanceId,
        public int $taskId,
        public string $flowCode,
        public ?string $stageCode,
        public string $action,
        public int $userId,
        public ?int $onBehalfOf = null,
        public ?string $justification = null,
    ) {}
}
