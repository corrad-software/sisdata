<?php

namespace App\Modules\Ptn\Profile;

use App\Models\User;
use App\Modules\Pro\Contracts\ProfileSection;
use App\Modules\Ptn\Models\IsuPemantauan;
use App\Modules\Ptn\Models\JadualPemantauan;
use App\Modules\Ptn\Models\RekodLawatan;
use App\Modules\Ptn\Models\RiskRatingLot;
use App\Modules\Ptn\Services\RisikoService;
use App\Modules\Ptn\Support\KodWarna;
use App\Modules\Ptn\Support\Lalai;

/**
 * Profil Hartanah 360° section "pemantauan" (pemanfaatan tab, the key
 * MOD-PRO expects from MOD-PTN): last inspection, site condition, next
 * scheduled visit, open findings, with the risk colour as a flag (text label
 * + colour, SRS-PTN-16) and visits / findings on the lot timeline.
 */
class PemantauanSection implements ProfileSection
{
    public function __construct(private KodWarna $warna, private RisikoService $risiko) {}

    public function key(): string
    {
        return 'pemantauan';
    }

    public function tab(): string
    {
        return 'pemanfaatan';
    }

    public function label(): string
    {
        return 'Pemantauan Tanah';
    }

    /** @return array{items: list<array<string, mixed>>, flags?: list<array{level: string, label: string}>, link?: string}|null */
    public function forLot(string $lotId, User $user): ?array
    {
        if (! $user->can('ptn.lawatan.lihat')) {
            return null;
        }
        $last = RekodLawatan::query()->untuk($user)->where('lot_id', $lotId)->whereIn('status_lawatan', ['dihantar', 'disahkan'])->orderByDesc('tarikh_lawatan')->first();
        $next = JadualPemantauan::query()->untuk($user)->where('lot_id', $lotId)->whereIn('status_pelaksanaan', JadualPemantauan::TERBUKA)->orderBy('tarikh_sasaran')->first();
        $open = IsuPemantauan::query()->untuk($user)->where('lot_id', $lotId)->whereIn('status_isu', IsuPemantauan::TERBUKA)->get();
        $rating = RiskRatingLot::query()->untuk($user)->where('lot_id', $lotId)->where('semasa', true)->first();
        if ($last === null && $next === null && $open->isEmpty() && $rating === null) {
            return ['items' => [], 'link' => '/admin/ptn/lot/'.$lotId];
        }
        $flags = [];
        if ($rating !== null) {
            $c = $this->risiko->warna($rating);
            $flags[] = ['level' => ['hijau' => 'success', 'kuning' => 'warning', 'merah' => 'danger'][$c], 'label' => 'Risiko '.strtoupper($c).' — skor '.$rating->skor_keseluruhan.($rating->paksa_merah ? ' (penemuan kritikal terbuka)' : '')];
        }
        if ($next !== null && $this->warna->untukTarikh($next->tarikh_sasaran, 'lawatan') === 'merah') {
            $flags[] = ['level' => 'danger', 'label' => 'Lawatan tertunggak sejak '.$next->tarikh_sasaran->format('d/m/Y')];
        }
        $items = [[
            'lawatan_terakhir' => $last?->tarikh_lawatan->toDateString(), 'no_rujukan' => $last?->no_rujukan,
            'keadaan_tapak' => $last ? (Lalai::KEADAAN_TAPAK[$last->keadaan_tapak ?? ''] ?? null) : null,
            'lawatan_seterusnya' => $next?->tarikh_sasaran->toDateString(),
            'isu_terbuka' => $open->count(), 'isu_kritikal' => $open->where('tahap_keterukan', 'kritikal')->count(),
            'link' => $last ? '/admin/ptn/lawatan/'.$last->id : '/admin/ptn/lot/'.$lotId,
        ]];
        foreach ($open->take(10) as $i) {
            $items[] = ['isu' => $i->no_rujukan, 'kategori' => Lalai::KATEGORI_ISU[$i->kategori_isu] ?? $i->kategori_isu, 'tahap' => Lalai::TAHAP_KETERUKAN[$i->tahap_keterukan] ?? $i->tahap_keterukan, 'tarikh_dibuka' => $i->tarikh_dibuka->toDateString(), 'link' => '/admin/ptn/isu/'.$i->id];
        }

        return ['items' => $items, 'flags' => $flags, 'link' => '/admin/ptn/lot/'.$lotId];
    }

    /** @return list<array{date: string, type: string, title: string, link?: string}> */
    public function timeline(string $lotId, User $user): array
    {
        if (! $user->can('ptn.lawatan.lihat')) {
            return [];
        }
        $out = [];
        foreach (RekodLawatan::query()->untuk($user)->where('lot_id', $lotId)->whereIn('status_lawatan', ['dihantar', 'disahkan'])->orderByDesc('tarikh_lawatan')->limit(30)->get() as $v) {
            $out[] = ['date' => $v->tarikh_lawatan->toDateString(), 'type' => 'pemantauan', 'title' => 'Lawatan pemantauan '.$v->no_rujukan.' — '.(Lalai::KEADAAN_TAPAK[$v->keadaan_tapak ?? ''] ?? 'tiada penilaian'), 'link' => '/admin/ptn/lawatan/'.$v->id];
        }
        foreach (IsuPemantauan::query()->untuk($user)->where('lot_id', $lotId)->orderByDesc('tarikh_dibuka')->limit(30)->get() as $i) {
            $out[] = ['date' => $i->tarikh_dibuka->toDateString(), 'type' => 'pemantauan', 'title' => 'Penemuan '.$i->no_rujukan.': '.(Lalai::KATEGORI_ISU[$i->kategori_isu] ?? $i->kategori_isu).' ('.$i->tahap_keterukan.')', 'link' => '/admin/ptn/isu/'.$i->id];
            if ($i->tarikh_ditutup) {
                $out[] = ['date' => $i->tarikh_ditutup->toDateString(), 'type' => 'pemantauan', 'title' => 'Isu '.$i->no_rujukan.' ditutup', 'link' => '/admin/ptn/isu/'.$i->id];
            }
        }

        return $out;
    }
}
