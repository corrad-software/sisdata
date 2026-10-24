<?php

namespace App\Modules\Pjk\Contracts;

use Carbon\CarbonImmutable;

/**
 * Adapter to GRP MAIS (finance) — receipts / revenue journal sync and the
 * daily statement used for reconciliation (SRS-PJK-21, SRS-INT-08). Mock
 * bound until MAIS provides the API.
 */
interface GrpMais
{
    /**
     * Post an official receipt (or credit receipt) as a revenue journal.
     *
     * @param  array{no_resit: string, jenis: string, no_kontrak: string, amaun: string, tarikh: string, kod_hasil: string}  $receipt
     * @return string GRP document number
     *
     * @throws GatewayException
     */
    public function postReceipt(array $receipt): string;

    /**
     * Receipts recorded in GRP for a day.
     *
     * @return list<array{rujukan: string, amaun: string, tarikh: string}> rujukan = no_resit
     *
     * @throws GatewayException
     */
    public function receipts(CarbonImmutable $date): array;
}
