<?php

namespace App\Support\Fallbacks;

use App\Modules\Wfe\Contracts\WorkflowFlows;

/** Stand-in for WorkflowFlows until its owning module binds the real one. See App\Support\Fallbacks. */
class FallbackWorkflowFlows implements WorkflowFlows
{
    public function declare(string $flowCode, string $name, string $module, string $subjectType, array $contextFields = []): void {}
}
