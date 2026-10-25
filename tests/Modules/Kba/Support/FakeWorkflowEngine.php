<?php

namespace Tests\Modules\Kba\Support;

use App\Models\User;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use InvalidArgumentException;

/** Records started workflows; `$missing` simulates a flow nobody configured yet. */
class FakeWorkflowEngine implements WorkflowEngine
{
    /** @var array<string, array{flow: string, subjectType: string, subjectId: string, initiator: int, context: array<string, mixed>}> */
    public array $started = [];

    public bool $missing = false;

    public function start(string $flowCode, string $subjectType, string|int $subjectId, User $initiator, array $context = [], ?string $title = null): string
    {
        if ($this->missing) {
            throw new InvalidArgumentException('tiada aliran');
        }
        $id = 'wf-'.(count($this->started) + 1);
        $this->started[$id] = ['flow' => $flowCode, 'subjectType' => $subjectType, 'subjectId' => (string) $subjectId, 'initiator' => $initiator->id, 'context' => $context];

        return $id;
    }

    public function status(string $instanceId): string
    {
        return 'berjalan';
    }

    public function activeInstanceFor(string $subjectType, string|int $subjectId): ?string
    {
        return null;
    }
}
