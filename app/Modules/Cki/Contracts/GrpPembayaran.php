<?php

namespace App\Modules\Cki\Contracts;

use Carbon\CarbonImmutable;

/**
 * OUTGOING payments through GRP MAIS (SRS-CKI-11/15/20): MAIS pays the land
 * office / local authority. Defined here rather than reusing
 * Pjk\Contracts\GrpMais because that contract only posts INCOMING receipts as
 * revenue journals (no_kontrak / kod_hasil) and lists receipts — it has no
 * payment request (permohonan bayaran / baucar), no status of a request and no
 * list of payments made. Mock bound until MAIS provides the GRP API.
 *
 * Request status: menunggu | lulus | dibayar | ditolak.
 */
interface GrpPembayaran
{
    /**
     * Submit a payment request (GRP raises the voucher).
     *
     * @param  array{no_permohonan: string, no_bil: string, penerima: string, amaun: string, kaedah: string, kod_vot: string, tarikh: string, keterangan: string}  $permohonan
     * @return string GRP reference (no. dokumen / baucar)
     *
     * @throws PenyediaTidakTersedia
     */
    public function hantarPermohonan(array $permohonan): string;

    /**
     * @return array{status: string, no_baucar: string|null, no_rujukan_bayaran: string|null, tarikh_bayar: string|null, amaun: string|null}|null
     *
     * @throws PenyediaTidakTersedia
     */
    public function status(string $rujukanGrp): ?array;

    /**
     * Payments GRP actually made in a date range (reconciliation source).
     *
     * @return list<array{rujukan_grp: string, no_permohonan: string, amaun: string, tarikh: string}>
     *
     * @throws PenyediaTidakTersedia
     */
    public function bayaran(CarbonImmutable $dari, CarbonImmutable $hingga): array;
}
