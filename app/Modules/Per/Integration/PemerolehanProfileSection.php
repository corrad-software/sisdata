<?php

namespace App\Modules\Per\Integration;

use App\Models\User;
use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Pro\Contracts\ProfileSection;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Database\Eloquent\Collection;

/**
 * MOD-PER's contribution to Profil Hartanah 360° (SRS-PRO-26/27): the lot's
 * acquisition history with each channel's colour (SRS-PER-28) and timeline
 * events (case opened, stages completed, title registered).
 */
class PemerolehanProfileSection implements ProfileSection
{
    public function __construct(private AccessControl $access) {}

    public function key(): string
    {
        return 'pemerolehan';
    }

    public function tab(): string
    {
        return 'identiti';
    }

    public function label(): string
    {
        return __('per::per.profil_label');
    }

    public function forLot(string $lotId, User $user): ?array
    {
        if (! $this->access->allows($user, 'per.kes.lihat')) {
            return null;
        }
        $cases = $this->cases($lotId, $user);
        if ($cases->isEmpty()) {
            return null;
        }

        $flags = [];
        $open = $cases->whereIn('status_pemerolehan', Pemerolehan::AKTIF)->count();
        if ($open > 0) {
            $flags[] = ['level' => 'maklumat', 'label' => __('per::per.profil_kes_aktif', ['bilangan' => $open])];
        }

        return [
            'items' => $cases->map(fn (Pemerolehan $c) => [
                'no_kes' => $c->no_kes,
                'saluran' => $c->saluran->nama_saluran,
                'kod_saluran' => $c->saluran->kod_saluran,
                'warna' => $c->saluran->warna,
                'ikon' => $c->saluran->ikon,
                'status' => $c->status_pemerolehan,
                'tarikh_mula_kes' => $c->tarikh_mula_kes->toDateString(),
                'tarikh_selesai' => $c->tarikh_selesai?->toDateString(),
                'tempoh_keseluruhan_hari' => $c->tempoh_keseluruhan_hari,
                'tempoh_sasaran_hari' => $c->tempoh_sasaran_hari,
                'nilai_pemerolehan' => $c->nilai_pemerolehan,
                'link' => $c->pautan(),
            ])->values()->all(),
            'flags' => $flags,
            'link' => '/admin/per?lot='.$lotId,
        ];
    }

    public function timeline(string $lotId, User $user): array
    {
        if (! $this->access->allows($user, 'per.kes.lihat')) {
            return [];
        }

        $events = [];
        foreach ($this->cases($lotId, $user) as $c) {
            $events[] = ['date' => $c->tarikh_mula_kes->toDateString(), 'type' => 'pemerolehan', 'title' => __('per::per.garis_masa_mula', ['no_kes' => $c->no_kes, 'saluran' => $c->saluran->nama_saluran]), 'link' => $c->pautan()];
            foreach ($c->peringkat as $s) {
                if ($s->tarikh_keluar !== null) {
                    $events[] = ['date' => $s->tarikh_keluar->toDateString(), 'type' => 'pemerolehan', 'title' => __('per::per.garis_masa_peringkat', ['no_kes' => $c->no_kes, 'peringkat' => $s->nama_peringkat]), 'link' => $c->pautan()];
                }
            }
            if ($c->tarikh_selesai !== null) {
                $events[] = ['date' => $c->tarikh_selesai->toDateString(), 'type' => 'pemerolehan', 'title' => __('per::per.garis_masa_selesai', ['no_kes' => $c->no_kes]), 'link' => $c->pautan()];
            }
        }
        usort($events, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $events;
    }

    /** @return Collection<int, Pemerolehan> */
    private function cases(string $lotId, User $user)
    {
        return Pemerolehan::query()->visibleTo($user)->with(['saluran', 'peringkat'])
            ->where('lot_id', $lotId)->orderBy('tarikh_mula_kes')->get();
    }
}
