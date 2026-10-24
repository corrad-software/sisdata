<?php

namespace App\Modules\Kes\Services;

use App\Models\User;
use App\Modules\Kes\Models\Keputusan;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Models\KosGuaman;
use App\Modules\Kes\Models\LogCapaian;
use App\Modules\Kes\Models\Prosiding;
use App\Modules\Kes\Models\TarikhPenting;
use App\Modules\Kes\Support\Kod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * SRS-KES-21..23 reports and the case dashboard. Every query starts from the
 * viewer's scoped case query (Akses::hadkan via bolehDilihat), so district /
 * panel scope applies to every figure and every export.
 *
 * Filters (SRS-KES-21): status, jenis, mahkamah, peringkat_mahkamah,
 * kod_daerah, kod_mukim, kategori_tanah, id_pegawai, peguam, dari/hingga
 * (filing date), aktif, arkib, lot_id, q.
 */
class Laporan
{
    public const KUMPUL = ['status', 'jenis', 'mahkamah', 'peringkat', 'daerah', 'mukim', 'kategori_tanah', 'pegawai', 'peguam', 'tahun'];

    public const JENIS = ['ringkasan', 'senarai', 'kalendar', 'tanpa_tarikh', 'kos', 'keputusan', 'pendedahan', 'beban', 'log_capaian'];

    public function __construct(private Rujukan $ref, private NilaiLot $nilai) {}

    /**
     * @param  array<string, mixed>  $f
     * @return Builder<KesMahkamah>
     */
    public function kes(User $user, array $f): Builder
    {
        $q = KesMahkamah::query()->bolehDilihat($user);
        foreach (['status' => 'status', 'jenis' => 'jenis', 'peringkat_mahkamah' => 'peringkat_mahkamah'] as $key => $col) {
            if (! empty($f[$key])) {
                $q->where('kes_mahkamah.'.$col, (string) $f[$key]);
            }
        }
        if (! empty($f['mahkamah'])) {
            $q->where('kes_mahkamah.mahkamah', 'ilike', '%'.$this->like((string) $f['mahkamah']).'%');
        }
        foreach (['kod_daerah' => 'kod_daerah', 'kod_mukim' => 'kod_mukim', 'kategori_tanah' => 'kategori_guna_tanah', 'lot_id' => 'lot_id'] as $key => $col) {
            if (! empty($f[$key])) {
                $v = (string) $f[$key];
                $q->whereExists(fn ($s) => $s->selectRaw('1')->from('kes_mahkamah_lot as fl')->whereColumn('fl.kes_mahkamah_id', 'kes_mahkamah.id')->where('fl.'.$col, $v));
            }
        }
        if (! empty($f['id_pegawai'])) {
            $q->where('kes_mahkamah.id_pegawai_bertanggungjawab', (int) $f['id_pegawai']);
        }
        if (! empty($f['peguam'])) {
            $v = (string) $f['peguam'];
            $q->whereExists(fn ($s) => $s->selectRaw('1')->from('kes_peguam as fp')->whereColumn('fp.kes_mahkamah_id', 'kes_mahkamah.id')
                ->where(fn ($w) => ctype_digit($v) ? $w->where('fp.kes_panel_peguam_id', (int) $v) : $w->where('fp.nama_firma', 'ilike', '%'.$this->like($v).'%')->orWhere('fp.nama_peguam', 'ilike', '%'.$this->like($v).'%')));
        }
        if (! empty($f['dari'])) {
            $q->where('kes_mahkamah.tarikh_pemfailan', '>=', (string) $f['dari']);
        }
        if (! empty($f['hingga'])) {
            $q->where('kes_mahkamah.tarikh_pemfailan', '<=', (string) $f['hingga']);
        }
        if (! empty($f['aktif'])) {
            $q->aktif();
        }
        if (array_key_exists('arkib', $f) && $f['arkib'] !== null && $f['arkib'] !== '') {
            $q->where('kes_mahkamah.status_arkib', filter_var($f['arkib'], FILTER_VALIDATE_BOOLEAN));
        }
        if (! empty($f['q'])) {
            $like = '%'.$this->like((string) $f['q']).'%';
            $q->where(fn ($w) => $w->where('kes_mahkamah.no_rujukan', 'ilike', $like)->orWhere('kes_mahkamah.no_kes', 'ilike', $like)->orWhere('kes_mahkamah.tajuk', 'ilike', $like));
        }

        return $q;
    }

    /**
     * Grouped summary by one dimension.
     *
     * @param  array<string, mixed>  $f
     * @return list<array{kod: string, label: string, bil: int, aktif: int, nilai_tuntutan: string, kos: string}>
     */
    public function ringkasan(User $user, array $f, string $kumpul): array
    {
        $terminal = $this->ref->statusTerminal();
        $ids = $this->kes($user, $f)->select('kes_mahkamah.id');
        $q = DB::table('kes_mahkamah as k')->whereIn('k.id', $ids->toBase());
        [$expr, $label] = match ($kumpul) {
            'jenis' => ['k.jenis', fn ($v) => $this->ref->namaJenis($v)],
            'mahkamah' => ['k.mahkamah', fn ($v) => $v],
            'peringkat' => ['k.peringkat_mahkamah', fn ($v) => Kod::papar(Kod::PERINGKAT_MAHKAMAH, $v)],
            'daerah' => ['l.kod_daerah', fn ($v) => $this->ref->namaDaerah($v)],
            'mukim' => ['l.kod_mukim', fn ($v) => $this->ref->namaMukim($v)],
            'kategori_tanah' => ['l.kategori_guna_tanah', fn ($v) => $v],
            'pegawai' => ['k.id_pegawai_bertanggungjawab', fn ($v) => $v === null ? null : DB::table('users')->where('id', $v)->value('name')],
            'peguam' => ['p.nama_firma', fn ($v) => $v],
            'tahun' => ["to_char(k.tarikh_pemfailan, 'YYYY')", fn ($v) => $v],
            default => ['k.status', fn ($v) => $this->ref->namaStatus($v)],
        };
        if (in_array($kumpul, ['daerah', 'mukim', 'kategori_tanah'], true)) {
            $q->leftJoin('kes_mahkamah_lot as l', 'l.kes_mahkamah_id', '=', 'k.id');
        }
        if ($kumpul === 'peguam') {
            $q->leftJoin('kes_peguam as p', fn ($j) => $j->on('p.kes_mahkamah_id', '=', 'k.id')->where('p.status_lantikan', 'aktif'));
        }
        $placeholders = implode(',', array_fill(0, count($terminal), '?'));
        $rows = (clone $q)->groupByRaw($expr)->selectRaw("{$expr} AS kod, COUNT(DISTINCT k.id) AS bil,
                COUNT(DISTINCT CASE WHEN k.status NOT IN ({$placeholders}) THEN k.id END) AS aktif", $terminal)
            ->orderByDesc('bil')->get();
        // Money per group on distinct (group, case) pairs — no double counting through joins.
        $inner = (clone $q)->selectRaw("({$expr})::text AS kod, k.id, k.nilai_tuntutan, k.kos_guaman")->distinct();
        $money = DB::query()->fromSub($inner, 'x')->groupBy('kod')->selectRaw('kod, COALESCE(SUM(nilai_tuntutan),0) AS n, COALESCE(SUM(kos_guaman),0) AS c')->get()->keyBy(fn ($r) => (string) $r->kod);
        $out = [];
        foreach ($rows as $r) {
            $kod = $r->kod === null ? '' : (string) $r->kod;
            $sum = $money[$kod] ?? null;
            $out[] = [
                'kod' => $kod,
                'label' => $kod === '' ? (string) __('kes::kes.tiada_maklumat') : (string) ($label($r->kod) ?? $kod),
                'bil' => (int) $r->bil,
                'aktif' => (int) $r->aktif,
                'nilai_tuntutan' => bcadd((string) ($sum->n ?? '0'), '0', 2),
                'kos' => bcadd((string) ($sum->c ?? '0'), '0', 2),
            ];
        }

        return $out;
    }

    /**
     * Legal calendar (SRS-KES-09): proceedings + important dates in a range.
     *
     * @param  array<string, mixed>  $f  dari, hingga, jenis_prosiding + case filters
     * @return list<array<string, mixed>>
     */
    public function kalendar(User $user, array $f): array
    {
        $dari = (string) ($f['dari'] ?? now()->startOfMonth()->toDateString());
        $hingga = (string) ($f['hingga'] ?? now()->addMonths(2)->endOfMonth()->toDateString());
        unset($f['dari'], $f['hingga']);
        $ids = $this->kes($user, $f)->select('kes_mahkamah.id');
        $out = [];
        $pros = Prosiding::query()->with('kes.pegawai')->whereIn('kes_mahkamah_id', $ids)
            ->whereBetween('tarikh_prosiding', [$dari.' 00:00:00', $hingga.' 23:59:59'])
            ->when($f['jenis_prosiding'] ?? null, fn ($q, $v) => $q->where('jenis_prosiding', $v))
            ->orderBy('tarikh_prosiding')->limit(2000)->get();
        foreach ($pros as $p) {
            $out[] = [
                'id' => 'p'.$p->id, 'jenis_entri' => 'prosiding', 'tarikh' => $p->tarikh_prosiding->toIso8601String(),
                'jenis' => $p->jenis_prosiding, 'jenis_nama' => Kod::JENIS_PROSIDING[$p->jenis_prosiding] ?? $p->jenis_prosiding,
                'status' => $p->status, 'status_nama' => Kod::STATUS_PROSIDING[$p->status] ?? $p->status,
                'kes_id' => $p->kes_mahkamah_id, 'no_rujukan' => $p->kes?->no_rujukan, 'no_kes' => $p->kes?->no_kes, 'mahkamah' => $p->kes?->mahkamah,
                'jenis_kes' => $this->ref->namaJenis($p->kes?->jenis), 'kod_daerah' => $p->kes?->kod_daerah, 'pegawai' => $p->kes?->pegawai?->name,
            ];
        }
        if (empty($f['jenis_prosiding'])) {
            foreach (TarikhPenting::query()->with('kes.pegawai')->whereIn('kes_mahkamah_id', $ids)->where('status', 'belum')->whereBetween('tarikh', [$dari, $hingga])->orderBy('tarikh')->limit(1000)->get() as $t) {
                $out[] = [
                    'id' => 't'.$t->id, 'jenis_entri' => 'tarikh_penting', 'tarikh' => $t->tarikh->toDateString().'T00:00:00',
                    'jenis' => $t->jenis, 'jenis_nama' => Kod::JENIS_TARIKH[$t->jenis] ?? $t->jenis, 'status' => $t->status, 'status_nama' => 'Belum selesai',
                    'kes_id' => $t->kes_mahkamah_id, 'no_rujukan' => $t->kes?->no_rujukan, 'no_kes' => $t->kes?->no_kes, 'mahkamah' => $t->kes?->mahkamah,
                    'jenis_kes' => $this->ref->namaJenis($t->kes?->jenis), 'kod_daerah' => $t->kes?->kod_daerah, 'pegawai' => $t->kes?->pegawai?->name, 'keterangan' => $t->keterangan,
                ];
            }
        }
        usort($out, fn ($a, $b) => strcmp((string) $a['tarikh'], (string) $b['tarikh']));

        return $out;
    }

    /**
     * Legal costs: by category × year, and the most expensive cases.
     *
     * @param  array<string, mixed>  $f
     * @return array{mengikut_tahun: list<array<string, mixed>>, mengikut_kes: list<array<string, mixed>>, jumlah: string}
     */
    public function kos(User $user, array $f): array
    {
        $ids = $this->kes($user, $f)->select('kes_mahkamah.id');
        $rows = KosGuaman::query()->whereIn('kes_mahkamah_id', $ids)->where('status_bayaran', '!=', 'ditolak')
            ->selectRaw("to_char(tarikh, 'YYYY') AS tahun, kategori_kos, SUM(amaun) AS n")->groupBy('tahun', 'kategori_kos')->orderBy('tahun')->get();
        $tahun = [];
        foreach ($rows as $r) {
            $t = (string) $r->getAttribute('tahun');
            $tahun[$t] ??= ['tahun' => $t, 'fi_peguam' => '0.00', 'kos_mahkamah' => '0.00', 'kos_berkaitan' => '0.00', 'jumlah' => '0.00'];
            $tahun[$t][(string) $r->kategori_kos] = bcadd((string) $r->getAttribute('n'), '0', 2);
            $tahun[$t]['jumlah'] = bcadd($tahun[$t]['jumlah'], (string) $r->getAttribute('n'), 2);
        }
        $kes = $this->kes($user, $f)->where('kes_mahkamah.kos_guaman', '>', 0)->orderByDesc('kes_mahkamah.kos_guaman')->limit(100)->get()
            ->map(fn (KesMahkamah $k) => ['id' => $k->id, 'no_rujukan' => $k->no_rujukan, 'no_kes' => $k->no_kes, 'jenis' => $this->ref->namaJenis($k->jenis), 'kos' => (string) $k->kos_guaman])->values()->all();

        return ['mengikut_tahun' => array_values($tahun), 'mengikut_kes' => $kes, 'jumlah' => Wang::jumlah(array_column(array_values($tahun), 'jumlah'))];
    }

    /**
     * Decisions by type × case type (also the "trend keputusan mengikut jenis kes").
     *
     * @param  array<string, mixed>  $f
     * @return list<array{jenis_kes: string|null, jenis_kes_nama: string|null, tahun: string, jenis_keputusan: string, jenis_keputusan_nama: string, bil: int}>
     */
    public function keputusan(User $user, array $f): array
    {
        $ids = $this->kes($user, $f)->select('kes_mahkamah.id');

        return DB::table('kes_keputusan as d')->join('kes_mahkamah as k', 'k.id', '=', 'd.kes_mahkamah_id')->whereIn('d.kes_mahkamah_id', $ids->toBase())
            ->selectRaw("k.jenis, to_char(d.tarikh_keputusan, 'YYYY') AS tahun, d.jenis_keputusan, COUNT(*) AS bil")
            ->groupBy('k.jenis', 'tahun', 'd.jenis_keputusan')->orderBy('tahun')->get()
            ->map(fn ($r) => [
                'jenis_kes' => $r->jenis, 'jenis_kes_nama' => $this->ref->namaJenis($r->jenis), 'tahun' => (string) $r->tahun,
                'jenis_keputusan' => (string) $r->jenis_keputusan, 'jenis_keputusan_nama' => Kod::JENIS_KEPUTUSAN[$r->jenis_keputusan] ?? (string) $r->jenis_keputusan, 'bil' => (int) $r->bil,
            ])->values()->all();
    }

    /**
     * SRS-KES-23 portfolio legal exposure.
     *
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    public function pendedahan(User $user, array $f): array
    {
        $aktif = $this->kes($user, $f)->aktif()->select('kes_mahkamah.id');
        $lotIds = DB::table('kes_mahkamah_lot')->whereIn('kes_mahkamah_id', $aktif->toBase())->distinct()->pluck('lot_id')->map(fn ($v) => (string) $v)->all();
        $nilai = $this->nilai->untuk($lotIds, $user);

        return [
            'bil_kes_aktif' => $this->kes($user, $f)->aktif()->count(),
            'bil_lot_terlibat' => count($lotIds),
            'nilai_lot' => $nilai,
            'nilai_tuntutan_aktif' => bcadd((string) ($this->kes($user, $f)->aktif()->sum('kes_mahkamah.nilai_tuntutan') ?: '0'), '0', 2),
            'kos_mengikut_tahun' => $this->kos($user, $f)['mengikut_tahun'],
            'trend_keputusan' => $this->keputusan($user, $f),
        ];
    }

    /**
     * SRS-KES-20: current caseload per officer.
     *
     * @param  array<string, mixed>  $f
     * @return list<array{id: int|null, nama: string, aktif: int, jumlah: int, prosiding_30_hari: int}>
     */
    public function beban(User $user, array $f): array
    {
        $terminal = $this->ref->statusTerminal();
        $placeholders = implode(',', array_fill(0, count($terminal), '?'));
        $ids = $this->kes($user, $f)->select('kes_mahkamah.id');
        $rows = DB::table('kes_mahkamah as k')->leftJoin('users as u', 'u.id', '=', 'k.id_pegawai_bertanggungjawab')->whereIn('k.id', $ids->toBase())
            ->groupBy('k.id_pegawai_bertanggungjawab', 'u.name')
            ->selectRaw("k.id_pegawai_bertanggungjawab AS id, u.name AS nama, COUNT(*) AS jumlah, SUM(CASE WHEN k.status NOT IN ({$placeholders}) AND NOT k.status_arkib THEN 1 ELSE 0 END) AS aktif,
                SUM(CASE WHEN k.tarikh_prosiding_berikutnya BETWEEN CURRENT_DATE AND CURRENT_DATE + 30 THEN 1 ELSE 0 END) AS hampir", $terminal)
            ->orderByDesc('aktif')->get();

        return $rows->map(fn ($r) => ['id' => $r->id === null ? null : (int) $r->id, 'nama' => (string) ($r->nama ?? __('kes::kes.belum_ditugaskan')), 'aktif' => (int) $r->aktif, 'jumlah' => (int) $r->jumlah, 'prosiding_30_hari' => (int) $r->hampir])->values()->all();
    }

    /**
     * SRS-KES-22 dashboard.
     *
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    public function dashboard(User $user, array $f): array
    {
        $aktifQ = fn () => $this->kes($user, $f)->aktif()->where('kes_mahkamah.status_arkib', false);
        $hampir = Prosiding::query()->with('kes')->where('status', 'dijadualkan')
            ->whereBetween('tarikh_prosiding', [now()->startOfDay(), now()->addDays(30)->endOfDay()])
            ->whereIn('kes_mahkamah_id', $aktifQ()->select('kes_mahkamah.id'))->orderBy('tarikh_prosiding')->limit(15)->get();
        $lotIds = DB::table('kes_mahkamah_lot')->whereIn('kes_mahkamah_id', $aktifQ()->select('kes_mahkamah.id')->toBase())->distinct()->pluck('lot_id')->map(fn ($v) => (string) $v)->all();

        return [
            'bil_aktif' => $aktifQ()->count(),
            'bil_jumlah' => $this->kes($user, $f)->count(),
            'mengikut_status' => $this->ringkasan($user, [...$f, 'aktif' => 1], 'status'),
            'mengikut_jenis' => $this->ringkasan($user, [...$f, 'aktif' => 1], 'jenis'),
            'prosiding_hampir' => $hampir->map(fn (Prosiding $p) => [
                'id' => $p->id, 'kes_id' => $p->kes_mahkamah_id, 'no_rujukan' => $p->kes?->no_rujukan, 'tarikh' => $p->tarikh_prosiding->toIso8601String(),
                'jenis_nama' => Kod::JENIS_PROSIDING[$p->jenis_prosiding] ?? $p->jenis_prosiding, 'baki_hari' => (int) now()->startOfDay()->diffInDays($p->tarikh_prosiding->copy()->startOfDay()),
            ])->values()->all(),
            'bil_tanpa_tarikh' => Eskalasi::tanpaTarikh($this->kes($user, $f))->count(),
            'bil_lot_terlibat' => count($lotIds),
            'nilai_lot' => $this->nilai->untuk($lotIds, $user),
            'kos_terkumpul' => bcadd((string) ($this->kes($user, $f)->sum('kes_mahkamah.kos_guaman') ?: '0'), '0', 2),
            'kos_tahun_ini' => Wang::jumlah(KosGuaman::query()->whereYear('tarikh', now()->year)->where('status_bayaran', '!=', 'ditolak')->whereIn('kes_mahkamah_id', $this->kes($user, $f)->select('kes_mahkamah.id'))->pluck('amaun')->map(fn ($v) => (string) $v)->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $f  dari, hingga, id_pengguna, jenis_tindakan, kes_id
     * @return Builder<LogCapaian>
     */
    public function logCapaian(array $f): Builder
    {
        return LogCapaian::query()->with('pengguna')
            ->when($f['dari'] ?? null, fn ($q, $v) => $q->where('cap_masa', '>=', $v.' 00:00:00'))
            ->when($f['hingga'] ?? null, fn ($q, $v) => $q->where('cap_masa', '<=', $v.' 23:59:59'))
            ->when($f['id_pengguna'] ?? null, fn ($q, $v) => $q->where('id_pengguna', (int) $v))
            ->when($f['jenis_tindakan'] ?? null, fn ($q, $v) => $q->where('jenis_tindakan', $v))
            ->when($f['kes_id'] ?? null, fn ($q, $v) => $q->where('kes_mahkamah_id', $v))
            ->orderByDesc('cap_masa')->orderByDesc('id');
    }

    /** @return Builder<Keputusan> */
    public function senaraiKeputusan(User $user, array $f): Builder
    {
        return Keputusan::query()->whereIn('kes_mahkamah_id', $this->kes($user, $f)->select('kes_mahkamah.id'));
    }

    private function like(string $v): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $v);
    }
}
