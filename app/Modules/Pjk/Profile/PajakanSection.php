<?php

namespace App\Modules\Pjk\Profile;

use App\Models\User;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\Support\Akses;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Wang;
use App\Modules\Pro\Contracts\FinancialSeries;
use App\Modules\Pro\Contracts\ProfileSection;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Profil Hartanah 360° (SRS-PRO-26) — tab Pemanfaatan: the lot's premises
 * and leases; timeline of lease events (SRS-PRO-27); and rent collected as
 * `hasil_sewaan` for the revenue chart (SRS-PRO-28). Sulit: needs
 * pjk.kontrak.lihat and the user's scope.
 */
class PajakanSection implements FinancialSeries, ProfileSection
{
    public function __construct(private Akses $akses, private AccessControl $access) {}

    public function key(): string
    {
        return 'pajakan';
    }

    public function tab(): string
    {
        return 'pemanfaatan';
    }

    public function label(): string
    {
        return __('pjk::pjk.profil.pajakan');
    }

    public function forLot(string $lotId, User $user): ?array
    {
        if (! $this->access->allows($user, 'pjk.kontrak.lihat')) {
            return null;
        }
        $premises = $this->akses->premis($user)->where('lot_id', $lotId)->get();
        $contracts = $this->akses->kontrak($user)->where('lot_id', $lotId)->orderByDesc('tarikh_mula')->get();
        if ($premises->isEmpty() && $contracts->isEmpty()) {
            return null;
        }

        $flags = [];
        $today = Carbon::today();
        $arrears = DB::table('jadual_caj')->whereIn('kontrak_id', $contracts->pluck('id'))->where('dikeluarkan', true)
            ->whereIn('status_caj', ['belum_bayar', 'separa'])->whereDate('tarikh_akhir', '<', $today)
            ->selectRaw('coalesce(sum(amaun - amaun_dibayar - amaun_dikecualikan), 0)::text as t')->value('t');
        if (Wang::sen((string) $arrears) > 0) {
            $flags[] = ['level' => 'warning', 'label' => __('pjk::pjk.profil.bendera_tunggakan', ['amaun' => Wang::rm((string) $arrears)])];
        }
        foreach ($contracts->where('status', 'hampir_tamat') as $k) {
            $flags[] = ['level' => 'info', 'label' => __('pjk::pjk.profil.bendera_hampir_tamat', ['no' => $k->no_kontrak, 'tarikh' => $k->tarikh_tamat?->format('d/m/Y')])];
        }

        $items = [];
        foreach ($contracts as $k) {
            $items[] = [
                'id' => $k->id, 'jenis' => 'kontrak', 'no_kontrak' => $k->no_kontrak, 'penyewa' => $k->penyewa,
                'status' => $k->status, 'status_nama' => Kod::STATUS_KONTRAK[$k->status] ?? $k->status,
                'tarikh_mula' => $k->tarikh_mula?->format('Y-m-d'), 'tarikh_tamat' => $k->tarikh_tamat?->format('Y-m-d'),
                'kadar' => $k->kadar, 'kadar_rm' => Wang::rm($k->kadar), 'link' => '/admin/pjk/kontrak/'.$k->id,
            ];
        }
        foreach ($premises as $p) {
            $items[] = [
                'id' => $p->id, 'jenis' => 'premis', 'kod_premis' => $p->kod_premis, 'nama_premis' => $p->nama_premis,
                'status' => $p->status_premis, 'status_nama' => Kod::STATUS_PREMIS[$p->status_premis] ?? $p->status_premis,
                'kategori' => Kod::KATEGORI_HARTANAH[$p->kategori_hartanah] ?? $p->kategori_hartanah, 'link' => '/admin/pjk/premis?premis='.$p->id,
            ];
        }
        $current = $contracts->first(fn (Kontrak $k) => $k->berkuatKuasa());

        return ['items' => $items, 'flags' => $flags, 'link' => $current ? '/admin/pjk/kontrak/'.$current->id : '/admin/pjk/kontrak?lot_id='.$lotId];
    }

    public function timeline(string $lotId, User $user): array
    {
        if (! $this->access->allows($user, 'pjk.kontrak.lihat')) {
            return [];
        }
        $contracts = $this->akses->kontrak($user)->where('lot_id', $lotId)->pluck('no_kontrak', 'id');
        if ($contracts->isEmpty()) {
            return [];
        }
        $events = [];
        foreach (DB::table('sejarah_status_kontrak')->whereIn('kontrak_id', $contracts->keys())->whereIn('status_selepas', ['aktif', 'diperbaharui', 'ditamatkan', 'tamat_tempoh'])->orderBy('id')->get() as $s) {
            $events[] = [
                'date' => Carbon::parse($s->created_at)->format('Y-m-d'),
                'type' => 'pjk.'.$s->status_selepas,
                'title' => __('pjk::pjk.profil.peristiwa', ['no' => $contracts[$s->kontrak_id], 'status' => Kod::STATUS_KONTRAK[$s->status_selepas] ?? $s->status_selepas]),
                'link' => '/admin/pjk/kontrak/'.$s->kontrak_id,
            ];
        }

        return $events;
    }

    public function financialSeries(string $lotId, User $user): array
    {
        if (! $this->access->allows($user, 'pjk.kontrak.lihat')) {
            return [];
        }
        $ids = $this->akses->kontrak($user)->where('lot_id', $lotId)->pluck('id');
        if ($ids->isEmpty()) {
            return [];
        }
        // Rent revenue: confirmed collections allocated to non-deposit charges, net of reversals.
        $rows = DB::table('agihan_bayaran as a')->join('bayaran as b', 'b.id', '=', 'a.bayaran_id')->join('jadual_caj as c', 'c.id', '=', 'a.caj_id')
            ->whereIn('c.kontrak_id', $ids)->where('a.dibalikkan', false)->where('b.status', 'disahkan')->where('c.kod_caj', '!=', 'deposit')
            ->groupBy('b.tarikh_bayaran')->orderBy('b.tarikh_bayaran')
            ->selectRaw('b.tarikh_bayaran::text as tarikh, sum(a.amaun)::text as jumlah')->get();

        return $rows->map(fn ($r) => [
            'tarikh' => (string) $r->tarikh,
            'jenis' => 'hasil_sewaan',
            // FinancialSeries contract uses float; converted from the exact DECIMAL sum only here.
            'amaun' => round(Wang::sen((string) $r->jumlah) / 100, 2),
            'keterangan' => __('pjk::pjk.profil.hasil_sewaan'),
        ])->values()->all();
    }

    /** @return list<int> */
    public static function premisIds(string $lotId): array
    {
        return PremisAset::query()->withoutGlobalScope('access')->where('lot_id', $lotId)->pluck('id')->all();
    }
}
