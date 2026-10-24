<?php

namespace App\Modules\Pjk\Contracts;

use Carbon\CarbonImmutable;

/**
 * Adapter to the MAIS-approved payment gateway (SRS-PJK-14..18, SRS-INT-11,
 * SRS-INT-16). Defined by MOD-PJK; MOD-PWS (public portal) and MOD-CKI may
 * reuse it. The live adapter (bank / aggregator) replaces the mock binding
 * once MAIS provides credentials — nothing else changes.
 *
 * Card data is NEVER passed through SISDATA: initiate() returns a URL on the
 * gateway's own page where the payer enters it.
 *
 * Normalised transaction status: menunggu | berjaya | gagal | dibatalkan.
 */
interface PaymentGateway
{
    /** Short code for logs / integration settings, e.g. "mock", "fpx_bank_x". */
    public function code(): string;

    /** @return list<string> supported channels (at least fpx, kad) */
    public function channels(): array;

    /**
     * Start a payment.
     *
     * @param  array{no_rujukan: string, amaun: string, saluran: string, keterangan: string, nama_pembayar?: string|null, emel_pembayar?: string|null, url_kembali?: string|null}  $payment
     * @return array{rujukan_gateway: string, url_bayaran: string}
     *
     * @throws GatewayException unavailable / timeout
     */
    public function initiate(array $payment): array;

    /**
     * Verify the callback's digital signature and normalise it.
     *
     * @param  array<string, string>  $headers  lower-case header names
     * @return array{id_acara: string, no_rujukan: string, status: string, amaun: string, rujukan_gateway: string|null, kod_respons: string|null}
     *
     * @throws InvalidGatewaySignature
     */
    public function parseCallback(string $rawBody, array $headers): array;

    /**
     * Status query for a transaction without a callback (SRS-PJK-16).
     *
     * @return array{status: string, amaun: string|null, rujukan_gateway: string|null, kod_respons: string|null}|null null when the gateway does not know it
     *
     * @throws GatewayException unavailable / timeout
     */
    public function query(string $noRujukan): ?array;

    /**
     * Daily settlement statement (SRS-PJK-18).
     *
     * @return list<array{rujukan: string, rujukan_gateway: string|null, amaun: string, status: string, tarikh: string}>
     *
     * @throws GatewayException
     */
    public function settlement(CarbonImmutable $date): array;
}
