<?php

namespace App\Modules\Hkm\Support;

use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Models\WarisPemilikBersama;

/**
 * Risk flags of a title, shown on lists, the title page and the lot profile
 * (SRS-HKM-05 missing original, SRS-HKM-12 unresolved ownership, SRS-HKM-13
 * active encumbrance, SRS-HKM-17 expiry / unverified). Expects the relations
 * daftarPetiBesi, sekatan and waris to be loaded (or loads them).
 */
class Bendera
{
    /**
     * @return list<array{kod: string, level: string, label: string}>
     */
    public static function for(HakMilik $title, int $nearDays = 180): array
    {
        $title->loadMissing(['daftarPetiBesi', 'sekatan', 'waris']);
        $flags = [];

        $docs = $title->daftarPetiBesi;
        if ($docs->isEmpty()) {
            $flags[] = ['kod' => 'dokumen_asal_tiada', 'level' => 'danger', 'label' => __('hkm::hkm.bendera.dokumen_tiada_daftar')];
        } else {
            foreach ($docs as $doc) {
                if ($doc->status_dokumen_asal !== 'dalam_simpanan') {
                    $flags[] = [
                        'kod' => 'dokumen_asal_tiada',
                        'level' => in_array($doc->status_dokumen_asal, ['hilang'], true) ? 'danger' : 'warning',
                        'label' => __('hkm::hkm.bendera.dokumen_tiada', ['status' => Kod::STATUS_DOKUMEN[$doc->status_dokumen_asal] ?? $doc->status_dokumen_asal]),
                    ];
                    break;
                }
            }
        }

        $active = $title->sekatan->where('status_sekatan', 'aktif');
        if ($active->isNotEmpty()) {
            $types = $active->pluck('jenis_sekatan')->unique()->map(fn ($j) => Kod::JENIS_SEKATAN[$j] ?? $j)->implode(', ');
            $flags[] = ['kod' => 'sekatan_aktif', 'level' => 'warning', 'label' => __('hkm::hkm.bendera.sekatan', ['jenis' => $types])];
        }

        if (self::ownershipUnresolved($title)) {
            $flags[] = ['kod' => 'pemilikan_belum_selesai', 'level' => 'warning', 'label' => __('hkm::hkm.bendera.pemilikan')];
        }

        if ($title->tarikh_tamat_tempoh !== null && ! in_array($title->status_semasa, ['diganti', 'dilupuskan'], true)) {
            $days = (int) today()->diffInDays($title->tarikh_tamat_tempoh, false);
            if ($days < 0) {
                $flags[] = ['kod' => 'tamat_tempoh', 'level' => 'danger', 'label' => __('hkm::hkm.bendera.tamat')];
            } elseif ($days <= $nearDays) {
                $flags[] = ['kod' => 'hampir_tamat', 'level' => 'warning', 'label' => __('hkm::hkm.bendera.hampir_tamat', ['hari' => $days])];
            }
        }

        if (! $title->disahkan) {
            $flags[] = ['kod' => 'belum_disahkan', 'level' => 'info', 'label' => __('hkm::hkm.bendera.belum_disahkan')];
        }

        return $flags;
    }

    /** SRS-HKM-12: pending verification / faraid, dispute, or shares not adding up to 1. */
    public static function ownershipUnresolved(HakMilik $title): bool
    {
        $title->loadMissing('waris');
        $sharing = $title->waris->filter(fn (WarisPemilikBersama $w) => in_array($w->hubungan, Kod::HUBUNGAN_BERBAHAGI, true));

        if ($sharing->contains(fn (WarisPemilikBersama $w) => in_array($w->status_pengesahan, Kod::WARIS_BELUM_SELESAI, true))) {
            return true;
        }
        if ($sharing->isEmpty()) {
            return false;
        }

        // Exact rational sum of numerator/denominator pairs.
        [$num, $den] = [0, 1];
        foreach ($sharing as $w) {
            if (! $w->pecahan_bahagian_penyebut) {
                return true;
            }
            $num = $num * $w->pecahan_bahagian_penyebut + $w->pecahan_bahagian_pembilang * $den;
            $den *= $w->pecahan_bahagian_penyebut;
            $g = self::gcd($num, $den);
            [$num, $den] = [intdiv($num, $g), intdiv($den, $g)];
        }

        return $num !== $den;
    }

    private static function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return max(1, abs($a));
    }
}
