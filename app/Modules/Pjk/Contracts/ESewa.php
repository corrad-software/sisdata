<?php

namespace App\Modules\Pjk\Contracts;

use Carbon\CarbonImmutable;

/**
 * Adapter to e-Sewa MAIS — exchange of rental records and payment status
 * (SRS-PJK-21). Mock bound until MAIS provides the API.
 */
interface ESewa
{
    /**
     * Push the contract's current state (activation, renewal, termination).
     *
     * @param  array{no_kontrak: string, status: string, tarikh_mula: string|null, tarikh_tamat: string|null, kadar: string|null, kod_premis: string|null}  $contract
     *
     * @throws GatewayException
     */
    public function syncContract(array $contract): void;

    /**
     * Push a confirmed payment / receipt (or credit receipt, negative amaun).
     *
     * @param  array{no_resit: string, no_kontrak: string, amaun: string, tarikh: string}  $payment
     *
     * @throws GatewayException
     */
    public function pushPayment(array $payment): void;

    /**
     * Payments e-Sewa holds for a day.
     *
     * @return list<array{rujukan: string, amaun: string, tarikh: string, no_kontrak?: string}> rujukan = no_resit
     *
     * @throws GatewayException
     */
    public function payments(CarbonImmutable $date): array;
}
