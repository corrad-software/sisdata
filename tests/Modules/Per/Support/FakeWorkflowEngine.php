<?php

namespace Tests\Modules\Per\Support;

use App\Models\User;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use InvalidArgumentException;

/** Spy WorkflowEngine: records start() calls; tests then dispatch StepDecided / WorkflowCompleted. */
class FakeWorkflowEngine implements WorkflowEngine
{
    /** @var array<string, array{flow: string, subjectType: string, subjectId: string, initiator: int, context: array<string, mixed>, title: string|null}> */
    public array $started = [];

    public bool $configured = true;

    public function start(string $flowCode, string $subjectType, string|int $subjectId, User $initiator, array $context = [], ?string $title = null): string
    {
        if (! $this->configured) {
            throw new InvalidArgumentException('no active flow');
        }
        $id = 'wf-'.(count($this->started) + 1);
        $this->started[$id] = ['flow' => $flowCode, 'subjectType' => $subjectType, 'subjectId' => (string) $subjectId, 'initiator' => $initiator->id, 'context' => $context, 'title' => $title];

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

    /** @return array{flow: string, subjectType: string, subjectId: string, initiator: int, context: array<string, mixed>, title: string|null}|null */
    public function last(): ?array
    {
        return $this->started === [] ? null : $this->started[array_key_last($this->started)];
    }
}
