<?php

namespace App\Modules\Wfe\Models\Concerns;

/** Shared App\Modules\Aud\Contracts\Auditable answers for WFE models. */
trait AuditedByWfe
{
    public function auditModule(): string
    {
        return 'wfe';
    }

    public function auditExcept(): array
    {
        return ['updated_at'];
    }

    public function auditSensitive(): array
    {
        return [];
    }
}
