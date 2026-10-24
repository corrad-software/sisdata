<?php

namespace App\Modules\Tsu\Profile;

use App\Models\User;
use App\Modules\Pro\Contracts\FinancialSeries;
use App\Modules\Pro\Contracts\ProfileSection;
use App\Modules\Tsu\Models\KosUrusan;
use App\Modules\Tsu\Models\LotSejarahPecahan;
use App\Modules\Tsu\Models\UrusanKeputusan;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Support\Lalai;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Profil Hartanah 360° section "urusan_tanah" (risiko tab, the key MOD-PRO
 * expects from MOD-TSU): active Land Office matters with current stage and
 * lateness flags; timeline of registrations, decisions, closure and the
 * TWO-WAY lineage asal ↔ baharu (SRS-TSU-21); and the urusan costs for the
 * lot's cumulative cost chart (SRS-TSU-15, jenis "kos_urusan_tanah").
 */
class UrusanTanahSection implements FinancialSeries, ProfileSection
{
    public const JENIS_KOS = 'kos_urusan_tanah';

    public function key(): string
    {
        return 'urusan_tanah';
    }

    public function tab(): string
    {
        return 'risiko';
    }

    public function label(): string
    {
        return 'Urusan Pejabat Tanah (Tukar Syarat, Pelupusan dan Ukur)';
    }

    /** @return Builder<UrusanTanah> */
    private function forLotQuery(string $lotId, User $user)
    {
        return UrusanTanah::query()->untuk($user)->whereHas('lot', fn ($q) => $q->where('lot_id', $lotId));
    }

    public function forLot(string $lotId, User $user): ?array
    {
        if (! $user->can('tsu.urusan.lihat')) {
            return null;
        }
        $all = $this->forLotQuery($lotId, $user)->with('peringkat')->orderByDesc('tarikh_mula')->get();
        $lineage = LotSejarahPecahan::query()->where('id_lot_asal', $lotId)->orWhere('id_lot_baharu', $lotId)->get();
        if ($all->isEmpty() && $lineage->isEmpty()) {
            return ['items' => []];
        }
        $flags = [];
        $items = [];
        foreach ($all as $u) {
            $stage = $u->peringkatAktif();
            $late = $stage?->lewat() ?? false;
            if ($u->terbuka()) {
                $flags[] = ['level' => $late ? 'danger' : 'info', 'label' => $u->jenisLabel().' '.$u->no_rujukan_mais.($late ? ' — melepasi sasaran peringkat' : ' — dalam proses')];
            }
            $items[] = [
                'no_rujukan' => $u->no_rujukan_mais, 'jenis' => $u->jenisLabel(), 'status' => Lalai::STATUS_URUSAN[$u->status] ?? $u->status,
                'peringkat_semasa' => $u->terbuka() ? $stage?->nama_peringkat : null, 'tarikh_sasaran_peringkat' => $u->terbuka() ? $stage?->tarikh_sasaran?->toDateString() : null,
                'status_ptd' => $u->status_ptd ? (Lalai::STATUS_PTD[$u->status_ptd] ?? $u->status_ptd) : null, 'tarikh_mula' => $u->tarikh_mula->toDateString(),
                'link' => '/admin/tsu/urusan/'.$u->id,
            ];
        }
        $no = DB::table('lot_tanah')->whereIn('id', $lineage->flatMap(fn ($s) => [$s->id_lot_asal, $s->id_lot_baharu])->unique()->values())->pluck('no_lot', 'id');
        foreach ($lineage as $s) {
            $isAsal = $s->id_lot_asal === $lotId;
            $other = $isAsal ? $s->id_lot_baharu : $s->id_lot_asal;
            $items[] = [
                'sejarah' => $isAsal ? 'Lot baharu hasil '.(Lalai::JENIS_URUSAN[$s->jenis_perubahan] ?? $s->jenis_perubahan) : 'Berasal daripada lot (hasil '.(Lalai::JENIS_URUSAN[$s->jenis_perubahan] ?? $s->jenis_perubahan).')',
                'no_lot' => $no[$other] ?? $other, 'tarikh' => $s->tarikh_berkuat_kuasa->toDateString(), 'link' => '/admin/pro/lot/'.$other,
            ];
        }
        if ($lineage->contains(fn ($s) => $s->id_lot_asal === $lotId)) {
            $flags[] = ['level' => 'warning', 'label' => 'Lot asal — telah dipecah / disatukan dan diarkibkan'];
        }

        return ['items' => $items, 'flags' => $flags, 'link' => '/admin/tsu/urusan?lot_id='.$lotId];
    }

    public function timeline(string $lotId, User $user): array
    {
        if (! $user->can('tsu.urusan.lihat')) {
            return [];
        }
        $out = [];
        $all = $this->forLotQuery($lotId, $user)->limit(50)->get();
        foreach ($all as $u) {
            $out[] = ['date' => $u->tarikh_mula->toDateString(), 'type' => 'urusan_tanah', 'title' => 'Urusan '.$u->jenisLabel().' '.$u->no_rujukan_mais.' didaftarkan', 'link' => '/admin/tsu/urusan/'.$u->id];
            if ($u->tarikh_tutup) {
                $out[] = ['date' => $u->tarikh_tutup->toDateString(), 'type' => 'urusan_tanah', 'title' => 'Urusan '.$u->no_rujukan_mais.' ditutup', 'link' => '/admin/tsu/urusan/'.$u->id];
            }
        }
        foreach (UrusanKeputusan::query()->whereIn('urusan_id', $all->pluck('id'))->where('konteks', 'urusan')->whereIn('jenis_keputusan', ['lulus', 'tolak', 'lot_baharu_dicipta'])->whereNull('kod_peringkat_wfe')->get() as $k) {
            $u = $all->firstWhere('id', $k->urusan_id);
            $out[] = ['date' => $k->tarikh_keputusan->toDateString(), 'type' => 'urusan_tanah', 'title' => 'Keputusan '.(string) __('tsu::tsu.keputusan.'.$k->jenis_keputusan).' — '.$u?->no_rujukan_mais, 'link' => '/admin/tsu/urusan/'.$k->urusan_id];
        }
        // SRS-TSU-21: the lineage appears on BOTH lots' timelines.
        $lineage = LotSejarahPecahan::query()->where('id_lot_asal', $lotId)->orWhere('id_lot_baharu', $lotId)->get();
        $no = DB::table('lot_tanah')->whereIn('id', $lineage->flatMap(fn ($s) => [$s->id_lot_asal, $s->id_lot_baharu])->unique()->values())->pluck('no_lot', 'id');
        foreach ($lineage as $s) {
            $jenis = Lalai::JENIS_URUSAN[$s->jenis_perubahan] ?? $s->jenis_perubahan;
            $out[] = $s->id_lot_asal === $lotId
                ? ['date' => $s->tarikh_berkuat_kuasa->toDateString(), 'type' => 'sejarah_lot', 'title' => $jenis.': lot baharu '.($no[$s->id_lot_baharu] ?? '').' dicipta daripada lot ini', 'link' => '/admin/pro/lot/'.$s->id_lot_baharu]
                : ['date' => $s->tarikh_berkuat_kuasa->toDateString(), 'type' => 'sejarah_lot', 'title' => $jenis.': lot ini dicipta daripada lot '.($no[$s->id_lot_asal] ?? ''), 'link' => '/admin/pro/lot/'.$s->id_lot_asal];
        }

        return $out;
    }

    /** SRS-TSU-15: every urusan cost on the lot's cumulative cost chart (shared equally when an urusan covers several lots). */
    public function financialSeries(string $lotId, User $user): array
    {
        if (! $user->can('tsu.kos.lihat')) {
            return [];
        }
        $ids = $this->forLotQuery($lotId, $user)->pluck('id');
        $lots = DB::table('urusan_lot')->whereIn('urusan_id', $ids)->where('peranan_lot', '!=', 'baharu')->selectRaw('urusan_id, count(*) AS n')->groupBy('urusan_id')->pluck('n', 'urusan_id');
        $out = [];
        foreach (KosUrusan::query()->whereIn('urusan_id', $ids)->where('status_bayaran', '!=', 'dibatalkan')->get() as $k) {
            $share = max(1, (int) ($lots[$k->urusan_id] ?? 1));
            $out[] = [
                'tarikh' => ($k->tarikh_bayaran ?? $k->tarikh_invois ?? $k->created_at)->toDateString(), 'jenis' => self::JENIS_KOS,
                'amaun' => (float) bcdiv((string) $k->amaun, (string) $share, 2),
                'keterangan' => (Lalai::KATEGORI_KOS[$k->kategori_kos] ?? $k->kategori_kos).($k->status_bayaran === 'dibayar' ? '' : ' (belum dibayar)'),
                'link' => '/admin/tsu/urusan/'.$k->urusan_id.'?tab=kos',
            ];
        }

        return $out;
    }
}
