<?php

namespace App\Modules\Aud\Contracts;

/**
 * Explicit audit entries for things the global model observer can't see
 * (SRS-AUD-02): views of sensitive records, downloads, prints, exports,
 * approvals, AI prompts, notification deliveries… Data changes on Eloquent
 * models are recorded automatically by MOD-AUD (SRS-AUD-09) — modules must
 * NOT call this for plain create/update/delete.
 *
 * User, role, session, IP, device, channel and correlation ID are filled in
 * by MOD-AUD from the current request; callers only describe the action.
 * Never throws (SRS-AUD-26): a failed write is reported as an incident.
 */
interface AuditLogger
{
    /**
     * @param  string  $action  e.g. lihat, muat_turun, cetak, eksport, lulus, tolak, log_masuk
     * @param  string  $module  module code, e.g. "dms"
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes  field => [old, new]
     * @param  array<string, mixed>  $meta  extra context (filters used, file name…)
     */
    public function record(
        string $action,
        string $module,
        ?string $entityType = null,
        string|int|null $entityId = null,
        string $status = 'berjaya',
        ?string $reason = null,
        array $changes = [],
        array $meta = [],
    ): void;
}
