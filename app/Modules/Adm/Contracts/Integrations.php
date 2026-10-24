<?php

namespace App\Modules\Adm\Contracts;

/**
 * Admin-managed integration settings (SRS-ADM-04) and the integration log
 * (SRS-ADM-14/15/17). A module that talks to an external system reads its
 * endpoint/timeout from here instead of from .env, checks the rate limit, and
 * logs every exchange so the health screen and ICT alerts see failures.
 */
interface Integrations
{
    /**
     * @return array{kod: string, nama: string, jenis: string, endpoint: string, id_rahsia: ?string, tempoh_tamat_masa: int, kadar_capaian: ?int, jadual: ?string, status: string}|null
     */
    public function settings(string $code): ?array;

    /** False when the integration is inactive or its per-minute rate limit is used up. */
    public function allow(string $code): bool;

    /**
     * Record one exchange. Message/context are redacted before storage — but
     * never pass credentials or full personal data in the first place.
     *
     * @param  array<string, mixed>  $context
     */
    public function log(string $code, bool $success, ?int $httpStatus = null, ?int $durationMs = null, ?string $message = null, ?string $operation = null, ?int $records = null, array $context = [], string $direction = 'keluar'): void;
}
