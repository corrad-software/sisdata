<?php

namespace App\Modules\Wfe\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A task passed its SLA and an escalation level fired (SRS-WFE-07/08).
 *
 * @param  list<int>  $recipientIds
 */
class TaskEscalated
{
    use Dispatchable;

    public function __construct(
        public int $taskId,
        public string $instanceId,
        public string $flowCode,
        public int $level,
        public array $recipientIds,
    ) {}
}
