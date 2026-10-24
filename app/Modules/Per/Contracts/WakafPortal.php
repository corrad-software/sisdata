<?php

namespace App\Modules\Per\Contracts;

/**
 * Integration point with Portal Hartanah Wakaf Selangor (SRS-PER-12/25,
 * A-15.13). MAIS has not supplied the portal API, so MOD-PER binds
 * Integration\MockWakafPortal; MOD-PWS (or a real HTTP adapter) replaces the
 * binding. Submissions also arrive by push at POST /api/v1/per/portal-wakaf.
 *
 * Either way a submission becomes a Wakaf case with status
 * "permohonan_awam_menunggu_semakan" — it never writes the master lot record.
 */
interface WakafPortal
{
    /**
     * Public waqf registration submissions not yet fetched.
     *
     * @return list<array{rujukan_portal: string, nama_pewakaf: string, no_pengenalan?: string|null, jenis_wakaf?: string|null, tujuan_wakaf?: string|null, no_lot?: string|null, kod_mukim?: string|null, keluasan?: string|null, nilai_anggaran?: string|null, tarikh_permohonan?: string|null}>
     */
    public function pending(): array;

    /** Report the review status back to the portal ("diterima", "ditolak", "selesai"…). */
    public function acknowledge(string $portalReference, string $status, ?string $caseNumber = null): void;
}
