<?php

namespace Tests\Modules\Ast\Support;

use App\Models\User;
use App\Modules\Wfe\Contracts\WorkflowEngine;

class FakeWorkflowEngine implements WorkflowEngine
{
    /** @var array<string, array{flow: string, subjectType: string, subjectId: string, initiator: int, context: array<string, mixed>}> */
    public array $started = [];

    public bool $noFlow = false;

    public function start(string $flowCode, string $subjectType, string|int $subjectId, User $initiator, array $context = [], ?string $title = null): string
    {
        if ($this->noFlow) {
            throw new \InvalidArgumentException("No active flow {$flowCode}");
        }
        $id = 'wf-'.(count($this->started) + 1);
        $this->started[$id] = ['flow' => $flowCode, 'subjectType' => $subjectType, 'subjectId' => (string) $subjectId, 'initiator' => $initiator->id, 'context' => $context];

        return $id;
    }

    /** @return list<array{flow: string, subjectType: string, subjectId: string, initiator: int, context: array<string, mixed>}> */
    public function of(string $flow): array
    {
        return array_values(array_filter($this->started, fn ($s) => $s['flow'] === $flow));
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
