<?php

namespace App\Modules\Wfe\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A workflow task was created and assigned.
 *
 * @param  string  $assignee  "peranan:<kod>", "jawatan:<kod>", "kumpulan:<kod>" or "individu:<id>"
 * @param  list<int>  $notifiedUserIds
 */
class TaskAssigned
{
    use Dispatchable;

    public function __construct(
        public int $taskId,
        public string $instanceId,
        public string $flowCode,
        public string $assignee,
        public array $notifiedUserIds = [],
        public ?string $dueAt = null,
    ) {}
}
