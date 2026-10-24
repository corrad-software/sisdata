<?php

namespace App\Modules\Ptn\Services;

use App\Models\User;
use App\Modules\Ptn\Contracts\LotRiskStatus;
use App\Modules\Ptn\Models\RiskRatingLot;
use App\Modules\Ptn\Support\KodWarna;

class PtnLotRiskStatus implements LotRiskStatus
{
    public function __construct(private RisikoService $risiko) {}

    public function forLots(array $lotIds, ?User $user = null): array
    {
        if ($lotIds === []) {
            return [];
        }
        $q = RiskRatingLot::query()->whereIn('lot_id', $lotIds)->where('semasa', true);
        if ($user === null) {
            $q->withoutGlobalScope('access');
        }
        $out = [];
        foreach ($q->get() as $r) {
            $c = $this->risiko->warna($r);
            $out[$r->lot_id] = ['kod_warna' => $c, 'label' => KodWarna::papar($c)['label'], 'skor' => (float) $r->skor_keseluruhan, 'paksa_merah' => $r->paksa_merah, 'tarikh_kiraan' => $r->tarikh_kiraan->toIso8601String()];
        }

        return $out;
    }
}
