<?php

namespace App\Modules\Wfe\Contracts;

/**
 * Modules declare which flow codes they will start (from their provider's
 * boot()), so admins see them in the designer and a default template can be
 * seeded. The flow's stages, approvers and SLAs are configured by admins.
 *
 * @param  list<string>  $contextFields  fields the module passes in start($context) for branch conditions
 */
interface WorkflowFlows
{
    public function declare(string $flowCode, string $name, string $module, string $subjectType, array $contextFields = []): void;
}
