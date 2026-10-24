<?php

namespace App\Modules\Cki\Contracts;

/**
 * Adapter to the approved third-party central integration provider ("MBI")
 * that fronts every Pejabat Tanah daerah (cukai tanah / petak, PTG / e-Tanah)
 * and every PBT in Selangor (cukai taksiran / pintu) — SRS-CKI-04/24,
 * SRS-INT-11. Two services: bill & status enquiry, and direct payment with an
 * official receipt. A mock is bound until MAIS provides access; the live
 * adapter reads its endpoint from ADM Integrations ("cki_mbi") and its
 * credentials from ADM Secrets ("cki.mbi.kunci_api") — never from code or
 * plain-text config. Calls go through the API Gateway with TLS + IP allow-list.
 *
 * Amounts are DECIMAL strings ("123.40"). Bill status: belum_bayar | dibayar.
 */
interface PenyediaCukaiBerpusat
{
    /** Short code for logs, e.g. "mock_mbi", "mbi". */
    public function code(): string;

    /**
     * Health probe used for automatic fallback detection (SRS-CKI-08).
     *
     * @throws PenyediaTidakTersedia
     */
    public function ping(string $kodAgensi): void;

    /**
     * Bills of one tax account for an assessment year.
     *
     * @param  array{kod_agensi: string, jenis_cukai: string, no_akaun: string, tahun: int}  $akaun
     * @return list<array{no_bil: string, tahun_taksiran: int, amaun_pokok: string, amaun_denda: string, tarikh_bil: string, tarikh_akhir: string, status_bayaran: string, tarikh_bayar: string|null, no_resit: string|null, rujukan_transaksi: string}>
     *
     * @throws PenyediaTidakTersedia unavailable / timeout
     */
    public function semakBil(array $akaun): array;

    /**
     * Pay a bill directly and receive the official receipt.
     *
     * @param  array{kod_agensi: string, no_akaun: string, no_bil: string, amaun: string, rujukan: string}  $bayaran  rujukan = SISDATA no_permohonan (idempotency)
     * @return array{no_resit: string, amaun: string, tarikh: string, rujukan_transaksi: string}
     *
     * @throws PenyediaTidakTersedia
     */
    public function bayar(array $bayaran): array;
}
