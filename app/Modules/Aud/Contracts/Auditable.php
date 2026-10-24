<?php

namespace App\Modules\Aud\Contracts;

/**
 * Optional on an Eloquent model to tune how MOD-AUD's global observer records
 * it. Models that don't implement it are still audited, field by field, with
 * the global sensitive-field list applied (SRS-AUD-25).
 */
interface Auditable
{
    /** Module code recorded with each entry, e.g. "pro". */
    public function auditModule(): string;

    /**
     * Fields never recorded at all (e.g. noisy timestamps).
     *
     * @return list<string>
     */
    public function auditExcept(): array;

    /**
     * Fields recorded as changed but with the value masked (No. KP, tokens…).
     *
     * @return list<string>
     */
    public function auditSensitive(): array;
}
