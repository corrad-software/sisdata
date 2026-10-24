<?php

namespace App\Modules\Wfe\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A workflow instance reached a final outcome. The owning module applies it. */
class WorkflowCompleted
{
    use Dispatchable;

    /**
     * @param  string  $outcome  lulus | tolak | ditarik_balik | dibatalkan
     */
    public function __construct(
        public string $instanceId,
        public string $flowCode,
        public string $subjectType,
        public string $subjectId,
        public string $outcome,
        public ?int $decidedBy = null,
        public ?string $remarks = null,
    ) {}
}
