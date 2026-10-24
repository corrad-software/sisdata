<?php

namespace App\Modules\Aud\Contracts;

/**
 * Optional on an Eloquent model: its data classification (SRS §4.2 — awam,
 * dalaman, sulit, rahsia). Recorded with every audit entry for the model so
 * the "capaian data Sulit dan Rahsia" report (SRS-AUD-15) can find it. Models
 * with a `klasifikasi` column don't need this; the column value is used.
 * Explicit AuditLogger::record() calls pass it as $meta['klasifikasi'].
 */
interface HasClassification
{
    public function auditClassification(): string;
}
