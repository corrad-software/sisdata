<?php

namespace App\Modules\Ptn\Profile;

use App\Models\User;
use App\Modules\Pro\Contracts\ProfileSection;
use App\Modules\Ptn\Models\RiskRatingLot;
use App\Modules\Ptn\Services\RisikoService;

/** Profil 360° risiko tab: the lot's risk rating with the six category scores (SRS-PTN-11/16). */
class RisikoSection implements ProfileSection
{
    public function __construct(private RisikoService $risiko) {}

    public function key(): string
    {
        return 'risiko_pemantauan';
    }

    public function tab(): string
    {
        return 'risiko';
    }

    public function label(): string
    {
        return 'Penarafan Risiko Aset (Pemantauan)';
    }

    public function forLot(string $lotId, User $user): ?array
    {
        if (! $user->can('ptn.risiko.lihat')) {
            return null;
        }
        $r = RiskRatingLot::query()->untuk($user)->where('lot_id', $lotId)->where('semasa', true)->first();
        if ($r === null) {
            return ['items' => []];
        }
        $p = $this->risiko->papar($r, true);
        $c = $p['kod_warna'];

        return [
            'items' => [['skor_keseluruhan' => $p['skor_keseluruhan'], 'kod_warna' => $c, 'label_warna' => $p['warna']['label'], 'tarikh_kiraan' => $p['tarikh_kiraan']], ...array_map(fn ($k) => ['kategori' => $k['label'], 'skor' => $k['skor'], 'pemberat' => $k['pemberat'], 'butiran' => $k['butiran']], $p['kategori'])],
            'flags' => [['level' => ['hijau' => 'success', 'kuning' => 'warning', 'merah' => 'danger'][$c], 'label' => $p['warna']['label']]],
            'link' => '/admin/ptn/lot/'.$lotId,
        ];
    }

    public function timeline(string $lotId, User $user): array
    {
        return [];
    }
}
