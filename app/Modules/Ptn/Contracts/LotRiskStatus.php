<?php

namespace App\Modules\Ptn\Contracts;

use App\Models\User;

/**
 * SRS-PTN-16: the lot's current risk colour, so other lists (MOD-PRO's lot
 * list, dashboards) show the same green/yellow/red as MOD-PTN — computed
 * against the current admin thresholds, with a text label for accessibility.
 */
interface LotRiskStatus
{
    /**
     * @param  list<string>  $lotIds
     * @return array<string, array{kod_warna: string, label: string, skor: float, paksa_merah: bool, tarikh_kiraan: string}> lots without a rating are absent
     */
    public function forLots(array $lotIds, ?User $user = null): array;
}
