<?php

namespace App\Modules\Cki\Profile;

use App\Models\User;
use App\Modules\Cki\Models\Cukai;
use App\Modules\Cki\Support\Akses;
use App\Modules\Cki\Support\Kod;
use App\Modules\Cki\Support\Wang;
use App\Modules\Pro\Contracts\FinancialSeries;
use App\Modules\Pro\Contracts\ProfileSection;
use App\Modules\Rbc\Contracts\AccessControl;

/**
 * Profil Hartanah 360° (SRS-PRO-26) — tab Kewangan, section "cukai": the
 * lot's tax position (latest bill per obligation, arrears flag), bill
 * timeline events (SRS-PRO-27) and taxes paid as `kos_cukai` for the
 * revenue / cost chart (SRS-PRO-28). Needs cki.bil.lihat and the user's scope.
 */
class CukaiSection implements FinancialSeries, ProfileSection
{
    public function __construct(private Akses $akses, private AccessControl $access) {}

    public function key(): string
    {
        return 'cukai';
    }

    public function tab(): string
    {
        return 'kewangan';
    }

    public function label(): string
    {
        return __('cki::cki.profil.label');
    }

    public function forLot(string $lotId, User $user): ?array
    {
        if (! $this->access->allows($user, 'cki.bil.lihat')) {
            return null;
        }
        $bills = $this->akses->cukai($user)->where('lot_id', $lotId)->orderByDesc('tahun_taksiran')->get();
        $obligations = $this->akses->obligasi($user)->where('lot_id', $lotId)->where('aktif', true)->get();
        if ($bills->isEmpty() && $obligations->isEmpty()) {
            return null;
        }
        $items = [];
        $flags = [];
        $arrears = '0.00';
        foreach ($bills->groupBy(fn (Cukai $c) => $c->obligasi_id ?? $c->id) as $group) {
            /** @var Cukai $c */
            $c = $group->first();
            $items[] = [
                'label' => (Kod::JENIS_CUKAI[$c->jenis] ?? $c->jenis).' '.$c->tahun_taksiran.' ('.$c->no_bil.')',
                'nilai' => Wang::rm($c->jumlahPerlu()).' · '.(Kod::STATUS_BAYARAN[$c->status_bayaran] ?? $c->status_bayaran).' · tarikh akhir '.$c->tarikh_akhir?->format('d/m/Y'),
                'kod' => $c->status_bayaran,
                'link' => '/admin/cki/bil/'.$c->id,
            ];
        }
        foreach ($bills as $c) {
            if ($c->tertunggak()) {
                $arrears = Wang::add($arrears, $c->baki());
            }
        }
        if (! Wang::isZero($arrears)) {
            $flags[] = ['level' => 'danger', 'label' => __('cki::cki.profil.bendera_tunggakan', ['amaun' => Wang::rm($arrears)])];
        }
        if ($obligations->contains(fn ($o) => $o->status_liputan_api === 'luar')) {
            $flags[] = ['level' => 'info', 'label' => __('cki::cki.profil.bendera_luar_liputan')];
        }
        $items[] = ['label' => __('cki::cki.profil.garis_masa'), 'nilai' => __('cki::cki.profil.lihat_garis_masa'), 'link' => '/admin/cki/lot/'.$lotId];

        return ['items' => $items, 'flags' => $flags, 'link' => '/admin/cki/lot/'.$lotId];
    }

    public function timeline(string $lotId, User $user): array
    {
        if (! $this->access->allows($user, 'cki.bil.lihat')) {
            return [];
        }
        $events = [];
        foreach ($this->akses->cukai($user)->where('lot_id', $lotId)->orderBy('tahun_taksiran')->get() as $c) {
            $nama = Kod::JENIS_CUKAI[$c->jenis] ?? $c->jenis;
            if ($c->tarikh_bil) {
                $events[] = ['date' => $c->tarikh_bil->format('Y-m-d'), 'type' => 'cki.bil', 'title' => __('cki::cki.profil.peristiwa_bil', ['jenis' => $nama, 'tahun' => $c->tahun_taksiran, 'amaun' => Wang::rm($c->jumlahPerlu())]), 'link' => '/admin/cki/bil/'.$c->id];
            }
            if ($c->tarikh_bayar) {
                $events[] = ['date' => $c->tarikh_bayar->format('Y-m-d'), 'type' => 'cki.bayar', 'title' => __('cki::cki.profil.peristiwa_bayar', ['jenis' => $nama, 'tahun' => $c->tahun_taksiran, 'amaun' => Wang::rm($c->amaun_dibayar)]), 'link' => '/admin/cki/bil/'.$c->id];
            }
        }

        return $events;
    }

    public function financialSeries(string $lotId, User $user): array
    {
        if (! $this->access->allows($user, 'cki.bil.lihat')) {
            return [];
        }

        return $this->akses->cukai($user)->where('lot_id', $lotId)->whereNotNull('tarikh_bayar')->where('amaun_dibayar', '>', 0)->orderBy('tarikh_bayar')->get()
            ->map(fn (Cukai $c) => [
                'tarikh' => (string) $c->tarikh_bayar?->format('Y-m-d'),
                'jenis' => 'kos_cukai',
                // FinancialSeries contract is float; converted from the exact DECIMAL only here.
                'amaun' => Wang::float($c->amaun_dibayar),
                'keterangan' => (Kod::JENIS_CUKAI[$c->jenis] ?? $c->jenis).' '.$c->tahun_taksiran,
                'link' => '/admin/cki/bil/'.$c->id,
            ])->values()->all();
    }
}
