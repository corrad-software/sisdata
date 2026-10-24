<?php

namespace App\Modules\Per\Models\Concerns;

/**
 * MOD-AUD tuning shared by MOD-PER models (SRS-PER-30): module code "per",
 * classification Sulit (SRS §4.1), personal fields masked in the trail.
 * A model lists its masked fields in `protected array $sensitive`.
 */
trait AuditedByPer
{
    public function auditModule(): string
    {
        return 'per';
    }

    /** @return list<string> */
    public function auditExcept(): array
    {
        return ['created_at', 'updated_at'];
    }

    /** @return list<string> */
    public function auditSensitive(): array
    {
        /** @var list<string> $fields */
        $fields = get_object_vars($this)['sensitive'] ?? [];

        return $fields;
    }

    public function auditClassification(): string
    {
        return 'sulit';
    }
}
