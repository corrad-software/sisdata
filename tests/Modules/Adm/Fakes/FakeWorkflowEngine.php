<?php

namespace Tests\Modules\Adm\Fakes;

use App\Models\User;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use InvalidArgumentException;

class FakeWorkflowEngine implements WorkflowEngine
{
    /** @var list<array<string, mixed>> */
    public array $started = [];

    public function __construct(public bool $flowActive = true) {}

    public function start(string $flowCode, string $subjectType, string|int $subjectId, User $initiator, array $context = [], ?string $title = null): string
    {
        if (! $this->flowActive) {
            throw new InvalidArgumentException('No active flow');
        }

        $this->started[] = compact('flowCode', 'subjectType', 'subjectId', 'context', 'title') + ['initiator' => $initiator->id];

        return 'wf-'.count($this->started);
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
