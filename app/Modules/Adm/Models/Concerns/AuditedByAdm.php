<?php

namespace App\Modules\Adm\Models\Concerns;

/**
 * Config tables are audited by MOD-AUD's global observer (SRS-ADM-19): every
 * change lands in audit_rekod with before/after, user, IP and time. Each
 * config table carries a `justifikasi` column written on the same update, so
 * the justification is part of the audited change itself.
 */
trait AuditedByAdm
{
    public function auditModule(): string
    {
        return 'adm';
    }

    /** @return list<string> */
    public function auditExcept(): array
    {
        return ['created_at', 'updated_at'];
    }

    /** @return list<string> */
    public function auditSensitive(): array
    {
        return property_exists($this, 'auditMasked') ? $this->auditMasked : [];
    }
}
