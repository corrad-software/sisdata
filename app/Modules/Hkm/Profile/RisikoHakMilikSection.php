<?php

namespace App\Modules\Hkm\Profile;

use App\Models\User;
use App\Modules\Hkm\Support\Akses;
use App\Modules\Hkm\Support\Bendera;
use App\Modules\Hkm\Support\Kod;
use App\Modules\Pro\Contracts\ProfileSection;

/**
 * Profil 360° tab Risiko: active encumbrances (SRS-HKM-13 "ditandakan pada
 * profil lot"), original documents not in custody (SRS-HKM-05), unresolved
 * ownership (SRS-HKM-12) and expiring titles (SRS-HKM-17) as flags.
 */
class RisikoHakMilikSection implements ProfileSection
{
    public function __construct(private Akses $akses) {}

    public function key(): string
    {
        return 'hak_milik_risiko';
    }

    public function tab(): string
    {
        return 'risiko';
    }

    public function label(): string
    {
        return __('hkm::hkm.profil.risiko');
    }

    public function forLot(string $lotId, User $user): ?array
    {
        $titles = $this->akses->titles($user)->where('lot_id', $lotId)->where('status_semasa', '!=', 'diganti')
            ->with(['sekatan', 'daftarPetiBesi', 'waris'])->get();
        if ($titles->isEmpty()) {
            return null;
        }

        $items = [];
        $flags = [];
        foreach ($titles as $h) {
            foreach ($h->sekatan->where('status_sekatan', 'aktif') as $s) {
                $items[] = [
                    'hak_milik_id' => $h->id,
                    'no_hak_milik' => $h->no_hak_milik,
                    'jenis_sekatan' => $s->jenis_sekatan,
                    'jenis_sekatan_nama' => Kod::JENIS_SEKATAN[$s->jenis_sekatan] ?? $s->jenis_sekatan,
                    'no_rujukan_sekatan' => $s->no_rujukan_sekatan,
                    'pihak_terlibat' => $s->pihak_terlibat,
                    'tarikh_masuk' => $s->tarikh_masuk?->format('Y-m-d'),
                ];
            }
            foreach (Bendera::for($h) as $flag) {
                if ($flag['level'] === 'info') {
                    continue;
                }
                $flags[] = ['level' => $flag['level'], 'label' => $h->no_hak_milik.': '.$flag['label']];
            }
        }

        if ($items === [] && $flags === []) {
            return null;
        }

        return ['items' => $items, 'flags' => $flags, 'link' => '/admin/hkm/hak-milik/'.$titles->first()->id.'?tab=sekatan'];
    }

    public function timeline(string $lotId, User $user): array
    {
        return [];
    }
}
