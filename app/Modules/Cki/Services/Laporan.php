<?php

namespace App\Modules\Cki\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Cki\Models\Cukai;
use App\Modules\Cki\Models\Penyelarasan;
use App\Modules\Cki\Models\RekonsiliasiLarian;
use App\Modules\Cki\Support\Akses;
use App\Modules\Cki\Support\Kod;
use App\Modules\Cki\Support\Wang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SRS-CKI-03/21/22/23 + "Laporan yang boleh dijana": every report is built
 * from the user's scoped query (district officers see their district), as
 * {tajuk, kepala, baris} for JSON / PDF / Excel.
 */
class Laporan
{
    public const JENIS = [
        'belum_bayar' => 'Cukai belum bayar (belum tertunggak)',
        'telah_bayar' => 'Cukai telah bayar beserta kaedah dan resit',
        'tertunggak' => 'Cukai tertunggak — umur tunggakan dan denda',
        'penalti' => 'Penalti mengikut punca kelewatan',
        'bayaran_daerah' => 'Bayaran mengikut daerah dan mukim',
        'trend_tahunan' => 'Trend bayaran tahunan',
        'anggaran_obligasi' => 'Anggaran obligasi cukai tahunan (bajet)',
        'rekonsiliasi' => 'Rekonsiliasi sistem, resit dan GRP — senarai sisihan',
        'luar_liputan' => 'Lot di luar liputan integrasi',
        'prestasi_integrasi' => 'Prestasi integrasi API',
    ];

    public function __construct(private Akses $akses, private Parameters $parameters) {}

    /**
     * @param  array<string, mixed>  $f
     * @return Builder<Cukai>
     */
    public function bil(User $user, array $f): Builder
    {
        return $this->akses->cukai($user)->whereNotNull('cukai.obligasi_id')
            ->when(! empty($f['kod_daerah']), fn ($q) => $q->where('cukai.kod_daerah', $f['kod_daerah']))
            ->when(! empty($f['kod_mukim']), fn ($q) => $q->where('cukai.kod_mukim', $f['kod_mukim']))
            ->when(! empty($f['kod_pbt']), fn ($q) => $q->where('cukai.kod_pbt', $f['kod_pbt']))
            ->when(! empty($f['kod_agensi']), fn ($q) => $q->where('cukai.kod_agensi', $f['kod_agensi']))
            ->when(! empty($f['jenis_cukai']), fn ($q) => $q->where('cukai.jenis', $f['jenis_cukai']))
            ->when(! empty($f['tahun']), fn ($q) => $q->where('cukai.tahun_taksiran', (int) $f['tahun']))
            ->when(! empty($f['dari']), fn ($q) => $q->whereDate('cukai.tarikh_akhir', '>=', $f['dari']))
            ->when(! empty($f['hingga']), fn ($q) => $q->whereDate('cukai.tarikh_akhir', '<=', $f['hingga']));
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{tajuk: string, kepala: list<string>, baris: list<list<string|int|null>>, ringkasan?: array<string, mixed>}
     */
    public function build(string $jenis, User $user, array $f): array
    {
        $today = Carbon::today()->format('Y-m-d');
        $nama = fn ($k) => Kod::JENIS_CUKAI[$k] ?? $k;

        return match ($jenis) {
            'belum_bayar' => $this->rows($jenis, ['No. bil', 'Lot', 'Daerah', 'Mukim', 'Agensi', 'Jenis', 'Tahun', 'Amaun', 'Tarikh akhir', 'Status'],
                $this->bil($user, $f)->whereIn('cukai.status_bayaran', ['belum_bayar', 'dalam_proses'])->whereDate('cukai.tarikh_akhir', '>=', $today)->leftJoin('lot_tanah as l', 'l.id', '=', 'cukai.lot_id')->orderBy('cukai.tarikh_akhir')->limit(5000)
                    ->get(['cukai.*', 'l.no_lot'])->map(fn (Cukai $c) => [$c->no_bil, $c->getAttribute('no_lot'), $c->kod_daerah, $c->kod_mukim, $c->kod_agensi, $nama($c->jenis), $c->tahun_taksiran, Wang::norm($c->baki()), $c->tarikh_akhir?->format('d/m/Y'), Kod::STATUS_BAYARAN[$c->status_bayaran] ?? $c->status_bayaran])->all()),
            'telah_bayar' => $this->rows($jenis, ['No. bil', 'Lot', 'Daerah', 'Jenis', 'Tahun', 'Amaun dibayar', 'Tarikh bayar', 'Kaedah', 'No. resit', 'Sebelum tarikh akhir'],
                $this->bil($user, $f)->where('cukai.status_bayaran', 'dibayar')->leftJoin('lot_tanah as l', 'l.id', '=', 'cukai.lot_id')->orderByDesc('cukai.tarikh_bayar')->limit(5000)
                    ->get(['cukai.*', 'l.no_lot'])->map(fn (Cukai $c) => [$c->no_bil, $c->getAttribute('no_lot'), $c->kod_daerah, $nama($c->jenis), $c->tahun_taksiran, Wang::norm($c->amaun_dibayar), $c->tarikh_bayar?->format('d/m/Y'),
                        Kod::KAEDAH_BAYARAN[$c->kaedah_bayaran ?? ''] ?? ($c->kaedah_bayaran ?? '—'), $c->no_resit, $c->tarikh_bayar && $c->tarikh_akhir && $c->tarikh_bayar->lte($c->tarikh_akhir) ? 'Ya' : 'Tidak'])->all()),
            'tertunggak' => $this->rows($jenis, ['No. bil', 'Lot', 'Daerah', 'Mukim', 'Jenis', 'Tahun', 'Pokok', 'Denda', 'Baki', 'Tarikh akhir', 'Umur (hari)', 'Kumpulan umur'],
                $this->bil($user, $f)->whereIn('cukai.status_bayaran', ['tertunggak', 'dalam_proses', 'belum_bayar'])->whereDate('cukai.tarikh_akhir', '<', $today)->leftJoin('lot_tanah as l', 'l.id', '=', 'cukai.lot_id')->orderBy('cukai.tarikh_akhir')->limit(5000)
                    ->get(['cukai.*', 'l.no_lot'])->map(function (Cukai $c) use ($nama) {
                        $age = (int) $c->tarikh_akhir?->diffInDays(Carbon::today());

                        return [$c->no_bil, $c->getAttribute('no_lot'), $c->kod_daerah, $c->kod_mukim, $nama($c->jenis), $c->tahun_taksiran, Wang::norm($c->amaun_pokok), Wang::norm($c->amaun_denda), Wang::norm($c->baki()), $c->tarikh_akhir?->format('d/m/Y'), $age, Kod::KUMPULAN_UMUR[self::kumpulanUmur($age)]];
                    })->all()),
            'penalti' => $this->penalti($user, $f),
            'bayaran_daerah' => $this->rows($jenis, ['Daerah', 'Mukim', 'Bil. bil', 'Jumlah bil', 'Telah dibayar', 'Tertunggak', 'Denda'],
                $this->bil($user, $f)->groupBy('cukai.kod_daerah', 'cukai.kod_mukim')->orderBy('cukai.kod_daerah')->orderBy('cukai.kod_mukim')
                    ->selectRaw("cukai.kod_daerah, cukai.kod_mukim, count(*) as n, sum(cukai.amaun_pokok + cukai.amaun_denda)::text as jumlah, sum(cukai.amaun_dibayar)::text as dibayar,
                        sum(CASE WHEN cukai.status_bayaran <> 'dibayar' AND cukai.tarikh_akhir < ? THEN cukai.amaun_pokok + cukai.amaun_denda - cukai.amaun_dibayar ELSE 0 END)::text as tunggak, sum(cukai.amaun_denda)::text as denda", [$today])
                    ->toBase()->get()->map(fn ($r) => [$this->daerah($r->kod_daerah), $this->mukim($r->kod_mukim), (int) $r->n, Wang::norm($r->jumlah), Wang::norm($r->dibayar), Wang::norm($r->tunggak), Wang::norm($r->denda)])->all()),
            'trend_tahunan' => $this->rows($jenis, ['Tahun', 'Bil. bil', 'Jumlah bil', 'Telah dibayar', 'Dibayar sebelum tarikh akhir (%)', 'Denda'],
                $this->bil($user, array_diff_key($f, ['tahun' => 1]))->groupBy('cukai.tahun_taksiran')->orderBy('cukai.tahun_taksiran')
                    ->selectRaw("cukai.tahun_taksiran as tahun, count(*) as n, sum(cukai.amaun_pokok + cukai.amaun_denda)::text as jumlah, sum(cukai.amaun_dibayar)::text as dibayar,
                        count(*) filter (where cukai.status_bayaran = 'dibayar' and cukai.tarikh_bayar <= cukai.tarikh_akhir) as tepat, sum(cukai.amaun_denda)::text as denda")
                    ->toBase()->get()->map(fn ($r) => [(int) $r->tahun, (int) $r->n, Wang::norm($r->jumlah), Wang::norm($r->dibayar), $r->n ? number_format(100 * $r->tepat / $r->n, 1) : '0.0', Wang::norm($r->denda)])->all()),
            'anggaran_obligasi' => $this->anggaran($user, $f),
            'rekonsiliasi' => $this->rekonsiliasi($user, $f),
            'luar_liputan' => $this->rows($jenis, ['Lot', 'Daerah', 'Mukim', 'Jenis', 'Agensi', 'No. akaun', 'Mod agensi'],
                $this->akses->obligasi($user)->leftJoin('lot_tanah as l', 'l.id', '=', 'obligasi_cukai.lot_id')
                    ->leftJoin('daftar_liputan_integrasi as d', 'd.kod_agensi', '=', 'obligasi_cukai.kod_agensi')
                    ->where('obligasi_cukai.aktif', true)->where(fn ($w) => $w->where('obligasi_cukai.status_liputan_api', 'luar')->orWhere('d.mod_semasa', 'sandaran'))
                    ->when(! empty($f['kod_daerah']), fn ($q) => $q->where('obligasi_cukai.kod_daerah', $f['kod_daerah']))
                    ->orderBy('obligasi_cukai.kod_daerah')->limit(5000)->get(['obligasi_cukai.*', 'l.no_lot', 'd.mod_semasa'])
                    ->map(fn ($o) => [$o->getAttribute('no_lot'), $o->kod_daerah, $o->kod_mukim, $nama($o->jenis_cukai), $o->kod_agensi, $o->no_akaun_cukai, $o->getAttribute('mod_semasa') === 'sandaran' ? 'Mod sandaran' : 'Luar liputan'])->all()),
            'prestasi_integrasi' => $this->prestasi(),
            default => abort(404),
        };
    }

    public static function kumpulanUmur(int $days): string
    {
        return match (true) {
            $days <= 30 => '1_30',
            $days <= 90 => '31_90',
            $days <= 365 => '91_365',
            default => 'lebih_365',
        };
    }

    /**
     * SRS-CKI-21: payment performance dashboard.
     *
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    public function dashboard(User $user, array $f): array
    {
        $today = Carbon::today()->format('Y-m-d');
        $risk = Carbon::today()->addDays(max(1, (int) $this->parameters->get('cki.hari_risiko_lewat', 30)))->format('Y-m-d');
        $t = $this->bil($user, $f)->selectRaw("count(*) as n, coalesce(sum(cukai.amaun_pokok + cukai.amaun_denda), 0)::text as jumlah, coalesce(sum(cukai.amaun_dibayar), 0)::text as dibayar,
            coalesce(sum(CASE WHEN cukai.status_bayaran <> 'dibayar' AND cukai.tarikh_akhir < ? THEN cukai.amaun_pokok + cukai.amaun_denda - cukai.amaun_dibayar ELSE 0 END), 0)::text as tunggak,
            count(*) filter (where cukai.status_bayaran <> 'dibayar' AND cukai.tarikh_akhir < ?) as n_tunggak,
            coalesce(sum(cukai.amaun_denda), 0)::text as denda,
            coalesce(sum(CASE WHEN cukai.status_bayaran <> 'dibayar' AND cukai.tarikh_akhir BETWEEN ? AND ? THEN cukai.amaun_pokok + cukai.amaun_denda - cukai.amaun_dibayar ELSE 0 END), 0)::text as risiko,
            count(*) filter (where cukai.status_bayaran <> 'dibayar' AND cukai.tarikh_akhir BETWEEN ? AND ?) as n_risiko", [$today, $today, $today, $risk, $today, $risk])->toBase()->first();

        $group = fn (string $col, callable $label) => $this->bil($user, $f)->groupBy($col)->orderBy($col)
            ->selectRaw("{$col} as k, count(*) as n, coalesce(sum(cukai.amaun_pokok + cukai.amaun_denda), 0)::text as jumlah, coalesce(sum(cukai.amaun_dibayar), 0)::text as dibayar,
                coalesce(sum(CASE WHEN cukai.status_bayaran <> 'dibayar' AND cukai.tarikh_akhir < ? THEN cukai.amaun_pokok + cukai.amaun_denda - cukai.amaun_dibayar ELSE 0 END), 0)::text as tunggak", [$today])
            ->toBase()->get()->map(fn ($r) => ['kod' => (string) $r->k, 'nama' => $label((string) $r->k), 'bil' => (int) $r->n, 'jumlah' => Wang::norm($r->jumlah), 'dibayar' => Wang::norm($r->dibayar), 'tertunggak' => Wang::norm($r->tunggak)])->values()->all();

        $umur = $this->bil($user, $f)->where('cukai.status_bayaran', '<>', 'dibayar')->whereDate('cukai.tarikh_akhir', '<', $today)
            ->selectRaw("CASE WHEN ?::date - cukai.tarikh_akhir <= 30 THEN '1_30' WHEN ?::date - cukai.tarikh_akhir <= 90 THEN '31_90' WHEN ?::date - cukai.tarikh_akhir <= 365 THEN '91_365' ELSE 'lebih_365' END as k,
                count(*) as n, sum(cukai.amaun_pokok + cukai.amaun_denda - cukai.amaun_dibayar)::text as baki", [$today, $today, $today])
            ->groupBy('k')->toBase()->get()->keyBy('k');

        return [
            'jumlah' => [
                'bil' => (int) $t->n, 'jumlah_bil' => Wang::norm($t->jumlah), 'telah_dibayar' => Wang::norm($t->dibayar), 'tertunggak' => Wang::norm($t->tunggak),
                'bil_tertunggak' => (int) $t->n_tunggak, 'denda_terkumpul' => Wang::norm($t->denda), 'risiko_lewat' => Wang::norm($t->risiko), 'bil_risiko_lewat' => (int) $t->n_risiko,
            ],
            'mengikut_daerah' => $group('cukai.kod_daerah', fn ($k) => $this->daerah($k)),
            'mengikut_pbt' => $group('cukai.kod_agensi', fn ($k) => $this->agensi($k)),
            'mengikut_jenis' => $group('cukai.jenis', fn ($k) => Kod::JENIS_CUKAI[$k] ?? $k),
            'umur_tunggakan' => array_map(fn ($k, $label) => ['kod' => $k, 'nama' => $label, 'bil' => (int) ($umur[$k]->n ?? 0), 'amaun' => Wang::norm($umur[$k]->baki ?? '0')], array_keys(Kod::KUMPULAN_UMUR), Kod::KUMPULAN_UMUR),
            'anggaran' => $this->anggaran($user, $f)['ringkasan'] ?? null,
        ];
    }

    /**
     * SRS-CKI-03: payment schedule for the planning period, grouped by district, PBT / agency and due month.
     *
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    public function jadual(User $user, array $f): array
    {
        $days = max(1, (int) ($f['hari'] ?? $this->parameters->get('cki.hari_perancangan', 90)));
        $until = Carbon::today()->addDays($days)->format('Y-m-d');
        $bills = $this->bil($user, array_diff_key($f, ['dari' => 1, 'hingga' => 1]))->leftJoin('lot_tanah as l', 'l.id', '=', 'cukai.lot_id')
            ->where('cukai.status_bayaran', '<>', 'dibayar')->whereDate('cukai.tarikh_akhir', '<=', $until)
            ->orderBy('cukai.tarikh_akhir')->limit(2000)->get(['cukai.*', 'l.no_lot']);
        $groups = [];
        foreach ($bills as $c) {
            $key = match ($f['kumpulan'] ?? 'tarikh_akhir') {
                'daerah' => (string) $c->kod_daerah,
                'pbt' => (string) $c->kod_agensi,
                default => $c->tarikh_akhir?->format('Y-m') ?? '—',
            };
            $label = match ($f['kumpulan'] ?? 'tarikh_akhir') {
                'daerah' => $this->daerah($key),
                'pbt' => $this->agensi($key),
                default => $c->tarikh_akhir ? $c->tarikh_akhir->translatedFormat('F Y') : '—',
            };
            $groups[$key] ??= ['kod' => $key, 'nama' => $label, 'jumlah' => '0.00', 'bil' => []];
            $groups[$key]['jumlah'] = Wang::add($groups[$key]['jumlah'], $c->baki());
            $groups[$key]['bil'][] = [
                'id' => $c->id, 'no_bil' => $c->no_bil, 'no_lot' => $c->getAttribute('no_lot'), 'jenis_nama' => Kod::JENIS_CUKAI[$c->jenis] ?? $c->jenis,
                'kod_daerah' => $c->kod_daerah, 'kod_agensi' => $c->kod_agensi, 'tarikh_akhir' => $c->tarikh_akhir?->format('Y-m-d'), 'baki' => Wang::norm($c->baki()),
                'status_bayaran' => $c->status_bayaran, 'status_nama' => Kod::STATUS_BAYARAN[$c->status_bayaran] ?? $c->status_bayaran,
            ];
        }

        return ['hari' => $days, 'hingga' => $until, 'jumlah' => Wang::add('0', ...array_column($groups, 'jumlah')), 'kumpulan' => array_values($groups)];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{tajuk: string, kepala: list<string>, baris: list<list<string|int|null>>, ringkasan?: array<string, mixed>}
     */
    private function anggaran(User $user, array $f): array
    {
        $base = (int) ($f['tahun'] ?? Carbon::today()->format('Y'));
        $rate = (string) $this->parameters->get('cki.kadar_anggaran_peratus', '3.00');
        $rows = $this->bil($user, array_merge($f, ['tahun' => $base]))->groupBy('cukai.kod_daerah', 'cukai.jenis')->orderBy('cukai.kod_daerah')
            ->selectRaw('cukai.kod_daerah, cukai.jenis, count(*) as n, sum(cukai.amaun_pokok)::text as pokok')->toBase()->get();
        $total = '0.00';
        $out = [];
        foreach ($rows as $r) {
            $est = Wang::add($r->pokok, Wang::percent($r->pokok, $rate));
            $total = Wang::add($total, $est);
            $out[] = [$this->daerah($r->kod_daerah), Kod::JENIS_CUKAI[$r->jenis] ?? $r->jenis, (int) $r->n, Wang::norm($r->pokok), $est];
        }

        return $this->rows('anggaran_obligasi', ['Daerah', 'Jenis', 'Bil. obligasi', 'Pokok '.$base, 'Anggaran '.($base + 1)], $out, ['tahun_asas' => $base, 'tahun_anggaran' => $base + 1, 'kadar_peratus' => $rate, 'jumlah_anggaran' => $total]);
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{tajuk: string, kepala: list<string>, baris: list<list<string|int|null>>, ringkasan?: array<string, mixed>}
     */
    private function penalti(User $user, array $f): array
    {
        $bills = $this->bil($user, $f)->where('cukai.amaun_denda', '>', 0)->orderByDesc('cukai.amaun_denda')->limit(5000)->get();
        $tx = DB::table('transaksi_bayaran_cukai')->whereIn('id_bil', $bills->pluck('id'))->orderBy('created_at')->get()->groupBy('id_bil');
        $punca = [];
        $rows = [];
        foreach ($bills as $c) {
            $first = ($tx[$c->id] ?? collect())->first();
            $cause = match (true) {
                $first === null => 'Tiada tindakan bayaran sebelum tarikh akhir',
                Carbon::parse($first->created_at)->gt($c->tarikh_akhir) => 'Permohonan bayaran dibuat selepas tarikh akhir',
                default => 'Kelewatan kelulusan / pemprosesan bayaran',
            };
            $punca[$cause] = Wang::add($punca[$cause] ?? '0', $c->amaun_denda);
            $rows[] = [$c->no_bil, $c->kod_daerah, Kod::JENIS_CUKAI[$c->jenis] ?? $c->jenis, $c->tahun_taksiran, Wang::norm($c->amaun_denda), $c->tarikh_akhir?->format('d/m/Y'), $cause];
        }

        return $this->rows('penalti', ['No. bil', 'Daerah', 'Jenis', 'Tahun', 'Denda', 'Tarikh akhir', 'Punca kelewatan'], $rows, ['mengikut_punca' => $punca]);
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{tajuk: string, kepala: list<string>, baris: list<list<string|int|null>>, ringkasan?: array<string, mixed>}
     */
    private function rekonsiliasi(User $user, array $f): array
    {
        $run = ! empty($f['larian']) ? RekonsiliasiLarian::query()->find((int) $f['larian']) : RekonsiliasiLarian::query()->latest('id')->first();
        $rows = $run === null ? [] : Penyelarasan::query()->where('id_larian', $run->id)->orderBy('jenis')->get()
            ->map(fn (Penyelarasan $p) => [Kod::JENIS_SISIHAN[$p->jenis] ?? $p->jenis, $p->kod_daerah, $p->amaun_sistem, $p->amaun_resit, $p->amaun_grp, $p->rujukan_grp, $p->catatan_sisihan, $p->status_padanan])->all();

        return $this->rows('rekonsiliasi', ['Jenis sisihan', 'Daerah', 'Amaun sistem', 'Amaun resit', 'Amaun GRP', 'Rujukan GRP', 'Catatan', 'Status'], $rows,
            $run ? ['larian' => $run->id, 'dari' => $run->dari->format('Y-m-d'), 'hingga' => $run->hingga->format('Y-m-d'), 'padan' => $run->bil_padan, 'sisihan' => $run->bil_sisihan] : []);
    }

    /** @return array{tajuk: string, kepala: list<string>, baris: list<list<string|int|null>>} */
    private function prestasi(): array
    {
        $since = now()->subDays(30);
        $calls = DB::table('log_integrasi_cukai')->where('masa_panggilan', '>=', $since)->groupBy('kod_agensi')
            ->selectRaw('kod_agensi, count(*) as n, count(*) filter (where not berjaya) as gagal, avg(masa_tindak_balas) as purata, max(masa_panggilan) as akhir')->get()->keyBy('kod_agensi');
        $modes = DB::table('mod_operasi_integrasi')->where('tarikh_mula', '>=', $since)->groupBy('kod_agensi')
            ->selectRaw('kod_agensi, count(*) as n, sum(extract(epoch from (coalesce(tarikh_tamat, now()) - tarikh_mula)) / 3600) as jam')->get()->keyBy('kod_agensi');
        $rows = [];
        foreach (DB::table('daftar_liputan_integrasi')->orderBy('kod_agensi')->get() as $a) {
            $c = $calls[$a->kod_agensi] ?? null;
            $m = $modes[$a->kod_agensi] ?? null;
            $rows[] = [$a->nama_agensi, $a->mod_semasa === 'sandaran' ? 'Sandaran' : 'API', (int) ($c->n ?? 0), (int) ($c->gagal ?? 0),
                $c && $c->n ? number_format(100 * $c->gagal / $c->n, 1) : '0.0', $c ? (int) round((float) $c->purata) : 0, (int) ($m->n ?? 0), number_format((float) ($m->jam ?? 0), 1)];
        }

        return $this->rows('prestasi_integrasi', ['Agensi', 'Mod semasa', 'Panggilan (30 hari)', 'Gagal', 'Kadar kegagalan (%)', 'Purata masa tindak balas (ms)', 'Bil. mod sandaran', 'Tempoh sandaran (jam)'], $rows);
    }

    /**
     * @param  list<string>  $kepala
     * @param  list<list<string|int|null>>  $baris
     * @param  array<string, mixed>  $ringkasan
     * @return array{tajuk: string, kepala: list<string>, baris: list<list<string|int|null>>, ringkasan?: array<string, mixed>}
     */
    private function rows(string $jenis, array $kepala, array $baris, array $ringkasan = []): array
    {
        return ['tajuk' => self::JENIS[$jenis], 'kepala' => $kepala, 'baris' => $baris] + ($ringkasan === [] ? [] : ['ringkasan' => $ringkasan]);
    }

    /** @var array<string, string>|null */
    private ?array $daerahNama = null;

    private function daerah(?string $kod): string
    {
        $this->daerahNama ??= DB::table('ref_daerah')->whereNull('sah_hingga')->pluck('nama', 'kod')->map(fn ($n) => (string) $n)->all();

        return $kod === null ? '—' : ($this->daerahNama[$kod] ?? $kod);
    }

    /** @var array<string, string>|null */
    private ?array $mukimNama = null;

    private function mukim(?string $kod): string
    {
        $this->mukimNama ??= DB::table('ref_mukim')->whereNull('sah_hingga')->pluck('nama', 'kod')->map(fn ($n) => (string) $n)->all();

        return $kod === null ? '—' : ($this->mukimNama[$kod] ?? $kod);
    }

    /** @var array<string, string>|null */
    private ?array $agensiNama = null;

    private function agensi(?string $kod): string
    {
        $this->agensiNama ??= DB::table('daftar_liputan_integrasi')->pluck('nama_agensi', 'kod_agensi')->map(fn ($n) => (string) $n)->all();

        return $kod === null ? '—' : ($this->agensiNama[$kod] ?? $kod);
    }
}
