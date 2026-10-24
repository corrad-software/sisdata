<?php

namespace App\Modules\Adu\Contracts;

use App\Models\User;

/**
 * Opens a complaint/issue in MOD-ADU from another channel: a field inspection
 * finding (MOD-PTN), the public portal (MOD-PWS), the central complaints
 * system adapter, or a support ticket (MOD-TKT). MOD-ADU then owns routing,
 * SLA and follow-up.
 */
interface ComplaintIntake
{
    /**
     * @param  array{kategori: string, keterangan: string, lot_id?: string|null, kod_daerah?: string|null, kod_mukim?: string|null, lat?: float|null, lng?: float|null, keutamaan?: string, pelapor_nama?: string|null, pelapor_hubungan?: string|null}  $data
     * @param  string  $channel  ptn | portal | sistem_aduan_pusat | tkt | dalaman
     * @param  string|null  $sourceRef  e.g. "ptn_lawatan:<id>" — shown as the origin and used for idempotency
     * @return string complaint id
     */
    public function open(array $data, string $channel, ?User $reporter = null, ?string $sourceRef = null): string;
}
