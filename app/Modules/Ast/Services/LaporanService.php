<?php

namespace App\Modules\Ast\Services;

use App\Models\User;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\AsetKategori;
use App\Modules\Ast\Models\AsetQrLogImbasan;
use App\Modules\Ast\Models\JadualPenyelenggaraan;
use App\Modules\Ast\Models\RekodPenyelenggaraan;
use App\Modules\Ast\Support\Lalai;
use App\Modules\Ast\Support\Rujukan;
use App\Modules\Ast\Support\SusutNilai;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * MOD-AST reports (SRS-AST-24..26 + "Laporan yang boleh dijana"). Every
 * report runs on the viewer's scope (`untuk($user)` — lot daerah / mukim /
 * klasifikasi) and returns one generic shape so the UI and the CSV export
 * render it the same way: {tajuk, lajur[], baris[], ringkasan[], kumpulan?}.
 * Money is summed in SQL NUMERIC or bcmath — never floats.
 */
class LaporanService
{
    public const LAPORAN = [
        'inventori' => 'Inventori aset (kategori, daerah, kondisi, nilai)',
        'nilai_portfolio' => 'Nilai portfolio aset dan perbandingan tahunan',
        'aset_kritikal' => 'Senarai aset kritikal',
        'kos_penyelenggaraan' => 'Kos penyelenggaraan tahunan mengikut aset dan kategori',
        'tanpa_pemeriksaan' => 'Aset tanpa pemeriksaan melebihi tempoh',
        'jadual_penyelenggaraan' => 'Jadual penyelenggaraan akan datang dan tertunggak',
        'sejarah_nilai' => 'Sejarah nilai aset mengikut tahun',
        'log_imbasan' => 'Log imbasan QR mengikut aset dan tempoh',
    ];

    public function __construct(private Rujukan $ref) {}

    /**
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    public function jana(string $kod, User $user, array $f): array
    {
        return match ($kod) {
            'inventori' => $this->inventori($user, $f),
            'nilai_portfolio' => $this->nilaiPortfolio($user, $f),
            'aset_kritikal' => $this->inventori($user, ['status_kondisi' => 'kritikal'] + $f, 'Senarai aset kritikal'),
            'kos_penyelenggaraan' => $this->kos($user, $f),
            'tanpa_pemeriksaan' => $this->tanpaPemeriksaan($user, $f),
            'jadual_penyelenggaraan' => $this->jadual($user, $f),
            'sejarah_nilai' => $this->sejarahNilai($user, $f),
            'log_imbasan' => $this->logImbasan($user, $f),
            default => abort(404),
        };
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Builder<AsetFizikal>
     */
    public function asetQuery(User $user, array $f): Builder
    {
        $q = AsetFizikal::query()->untuk($user);
        if (empty($f['termasuk_arkib'])) {
            $q->where('aset_fizikal.status_arkib', false);
        }
        foreach (['kod_daerah', 'kod_mukim', 'status_kondisi', 'jenis_aset', 'status_aset', 'lot_id'] as $c) {
            if (! empty($f[$c]) && is_string($f[$c])) {
                $q->where('aset_fizikal.'.$c, $f[$c]);
            }
        }
        if (! empty($f['id_kategori'])) {
            $q->where('aset_fizikal.id_kategori', (int) $f['id_kategori']);
        }
        if (! empty($f['tag']) && is_string($f['tag'])) {
            $q->whereExists(fn ($s) => $s->select(DB::raw(1))->from('aset_tag')->whereColumn('aset_tag.id_aset', 'aset_fizikal.id')->where('aset_tag.nama_tag', $f['tag']));
        }
        if (! empty($f['q']) && is_string($f['q'])) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $f['q']).'%';
            $q->where(fn ($w) => $w->where('aset_fizikal.nama_aset', 'ilike', $like)->orWhere('aset_fizikal.no_rujukan_aset', 'ilike', $like)->orWhere('aset_fizikal.qr_ref', strtoupper($f['q'])));
        }

        return $q;
    }

    /** @param array<string, mixed> $f */
    private function inventori(User $user, array $f, string $tajuk = 'Inventori aset fizikal'): array
    {
        $kat = AsetKategori::query()->pluck('nama_kategori', 'id')->all();
        $lots = DB::table('lot_tanah')->whereIn('id', $this->asetQuery($user, $f)->select('lot_id'))->pluck('no_lot', 'id')->all();
        $rows = $this->asetQuery($user, $f)->orderBy('kod_daerah')->orderBy('nama_aset')->limit(5000)->get();
        $baris = $rows->map(fn (AsetFizikal $a) => [
            'no_rujukan_aset' => $a->no_rujukan_aset, 'nama_aset' => $a->nama_aset, 'kategori' => $kat[$a->id_kategori] ?? '', 'jenis' => Lalai::JENIS_ASET[$a->jenis_aset] ?? $a->jenis_aset,
            'no_lot' => $lots[$a->lot_id] ?? '', 'daerah' => $this->ref->namaDaerah($a->kod_daerah), 'mukim' => $this->ref->namaMukim($a->kod_mukim),
            'kondisi' => Lalai::KONDISI[$a->status_kondisi] ?? $a->status_kondisi, 'nilai_semasa' => $a->nilai_semasa, 'tarikh_pemeriksaan_terakhir' => $a->tarikh_pemeriksaan_terakhir?->toDateString(),
            'status' => Lalai::STATUS_ASET[$a->status_aset] ?? $a->status_aset, '_pautan' => '/admin/ast/aset/'.$a->id,
        ])->all();

        return [
            'tajuk' => $tajuk,
            'lajur' => $this->lajur(['no_rujukan_aset' => 'No. rujukan', 'nama_aset' => 'Nama aset', 'kategori' => 'Kategori', 'jenis' => 'Jenis', 'no_lot' => 'Lot', 'daerah' => 'Daerah', 'mukim' => 'Mukim', 'kondisi' => 'Kondisi', 'nilai_semasa' => 'Nilai semasa (RM)', 'tarikh_pemeriksaan_terakhir' => 'Pemeriksaan terakhir', 'status' => 'Status']),
            'baris' => $baris,
            'ringkasan' => [['label' => 'Bilangan aset', 'nilai' => count($baris)], ['label' => 'Jumlah nilai semasa (RM)', 'nilai' => SusutNilai::jumlah($rows->pluck('nilai_semasa'))]],
            'kumpulan' => [
                'kategori' => $this->kumpul($user, $f, 'id_kategori', fn ($k) => $kat[(int) $k] ?? (string) $k),
                'daerah' => $this->kumpul($user, $f, 'kod_daerah', fn ($k) => (string) $this->ref->namaDaerah((string) $k)),
                'kondisi' => $this->kumpul($user, $f, 'status_kondisi', fn ($k) => Lalai::KONDISI[(string) $k] ?? (string) $k),
            ],
        ];
    }

    /**
     * Count + exact SUM(nilai_semasa) per group.
     *
     * @param  array<string, mixed>  $f
     * @return list<array{kod: string, label: string, bilangan: int, nilai: string}>
     */
    public function kumpul(User $user, array $f, string $col, callable $label): array
    {
        return $this->asetQuery($user, $f)->reorder()->groupBy('aset_fizikal.'.$col)
            ->selectRaw("aset_fizikal.{$col} AS k, COUNT(*) AS n, COALESCE(SUM(aset_fizikal.nilai_semasa), 0)::text AS v")->orderByDesc('n')->get()
            ->map(fn ($r) => ['kod' => (string) $r->getAttribute('k'), 'label' => $label($r->getAttribute('k')), 'bilangan' => (int) $r->getAttribute('n'), 'nilai' => SusutNilai::bundar((string) $r->getAttribute('v'))])->values()->all();
    }

    /**
     * SRS-AST-25: total current value + book value, breakdown by category and
     * district, and the value of the portfolio at the end of each of the last
     * N years (latest aset_nilai_sejarah per asset with tahun ≤ year).
     *
     * @param  array<string, mixed>  $f
     */
    public function nilaiPortfolio(User $user, array $f): array
    {
        $tahunAkhir = (int) ($f['tahun'] ?? now()->year);
        $bil = max(2, min(10, (int) ($f['bilangan_tahun'] ?? 5)));
        $kat = AsetKategori::query()->get()->keyBy('id');
        $buku = '0';
        foreach ($this->asetQuery($user, $f)->whereNotNull('kos_perolehan')->cursor() as $a) {
            $k = $kat[$a->id_kategori] ?? null;
            if ($k === null) {
                continue;
            }
            $buku = bcadd($buku, SusutNilai::kira((string) $a->kos_perolehan, (int) ($a->tahun_perolehan ?? $a->created_at->year), $k->hayat_guna_tahun, (string) $k->kadar_nilai_sisa, $tahunAkhir)['nilai_buku'], 2);
        }
        $ids = $this->asetQuery($user, $f)->select('aset_fizikal.id');
        $tahunan = [];
        for ($y = $tahunAkhir - $bil + 1; $y <= $tahunAkhir; $y++) {
            $r = DB::query()->fromSub(
                DB::table('aset_nilai_sejarah')->whereIn('id_aset', $ids)->where('tahun', '<=', $y)
                    ->selectRaw('DISTINCT ON (id_aset) id_aset, nilai')->orderBy('id_aset')->orderByDesc('tahun')->orderByDesc('tarikh_penilaian')->orderByDesc('id'),
                's')->selectRaw('COUNT(*) AS n, COALESCE(SUM(nilai), 0)::text AS v')->first();
            $tahunan[] = ['tahun' => $y, 'bilangan' => (int) ($r->n ?? 0), 'nilai' => SusutNilai::bundar((string) ($r->v ?? '0'))];
        }
        foreach ($tahunan as $i => $t) {
            $sebelum = $tahunan[$i - 1]['nilai'] ?? null;
            $tahunan[$i]['perubahan'] = $sebelum === null ? null : bcsub($t['nilai'], $sebelum, 2);
            $tahunan[$i]['peratus'] = $sebelum === null || bccomp($sebelum, '0', 2) === 0 ? null : bcdiv(bcmul(bcsub($t['nilai'], $sebelum, 4), '100', 4), $sebelum, 2);
        }
        $jumlah = (string) ($this->asetQuery($user, $f)->reorder()->selectRaw('COALESCE(SUM(nilai_semasa),0)::text AS v')->value('v') ?? '0');

        return [
            'tajuk' => 'Nilai portfolio aset fizikal',
            'lajur' => $this->lajur(['tahun' => 'Tahun', 'bilangan' => 'Bilangan aset bernilai', 'nilai' => 'Nilai portfolio (RM)', 'perubahan' => 'Perubahan (RM)', 'peratus' => 'Perubahan (%)']),
            'baris' => $tahunan,
            'ringkasan' => [
                ['label' => 'Jumlah nilai semasa (RM)', 'nilai' => SusutNilai::bundar($jumlah)],
                ['label' => 'Jumlah nilai buku '.$tahunAkhir.' (RM, susut nilai garis lurus)', 'nilai' => $buku],
            ],
            'kumpulan' => [
                'kategori' => $this->kumpul($user, $f, 'id_kategori', fn ($k) => $kat[(int) $k]->nama_kategori ?? (string) $k),
                'daerah' => $this->kumpul($user, $f, 'kod_daerah', fn ($k) => (string) $this->ref->namaDaerah((string) $k)),
            ],
        ];
    }

    /**
     * SRS-AST-26: annual maintenance cost (completed work, by completion year) by asset and category.
     *
     * @param  array<string, mixed>  $f
     */
    public function kos(User $user, array $f): array
    {
        $tahun = (int) ($f['tahun'] ?? now()->year);
        $base = fn () => RekodPenyelenggaraan::query()->untuk($user)->where('rekod_penyelenggaraan.status', 'selesai')->whereNotNull('rekod_penyelenggaraan.kos')
            ->whereYear('rekod_penyelenggaraan.tarikh_siap', $tahun)->join('aset_fizikal as a', 'a.id', '=', 'rekod_penyelenggaraan.id_aset')
            ->when(! empty($f['kod_daerah']), fn ($q) => $q->where('rekod_penyelenggaraan.kod_daerah', $f['kod_daerah']))
            ->when(! empty($f['id_kategori']), fn ($q) => $q->where('a.id_kategori', (int) $f['id_kategori']));
        $perAset = $base()->groupBy('a.id', 'a.no_rujukan_aset', 'a.nama_aset', 'a.id_kategori')
            ->selectRaw('a.id, a.no_rujukan_aset, a.nama_aset, a.id_kategori, COUNT(*) AS n, SUM(rekod_penyelenggaraan.kos)::text AS v')->orderByRaw('SUM(rekod_penyelenggaraan.kos) DESC')->limit(2000)->get();
        $kat = AsetKategori::query()->pluck('nama_kategori', 'id')->all();
        $perKat = $base()->groupBy('a.id_kategori')->selectRaw('a.id_kategori AS k, COUNT(*) AS n, SUM(rekod_penyelenggaraan.kos)::text AS v')->get()
            ->map(fn ($r) => ['kod' => (string) $r->getAttribute('k'), 'label' => $kat[(int) $r->getAttribute('k')] ?? '', 'bilangan' => (int) $r->getAttribute('n'), 'nilai' => SusutNilai::bundar((string) $r->getAttribute('v'))])->sortByDesc('nilai')->values()->all();
        $bulanan = $base()->groupByRaw('EXTRACT(MONTH FROM rekod_penyelenggaraan.tarikh_siap)')->selectRaw('EXTRACT(MONTH FROM rekod_penyelenggaraan.tarikh_siap)::int AS m, SUM(rekod_penyelenggaraan.kos)::text AS v')->pluck('v', 'm')->all();

        return [
            'tajuk' => 'Kos penyelenggaraan tahunan '.$tahun,
            'lajur' => $this->lajur(['no_rujukan_aset' => 'No. rujukan', 'nama_aset' => 'Nama aset', 'kategori' => 'Kategori', 'bilangan_kerja' => 'Bilangan kerja', 'kos' => 'Kos (RM)']),
            'baris' => $perAset->map(fn ($r) => [
                'no_rujukan_aset' => $r->getAttribute('no_rujukan_aset'), 'nama_aset' => $r->getAttribute('nama_aset'), 'kategori' => $kat[(int) $r->getAttribute('id_kategori')] ?? '',
                'bilangan_kerja' => (int) $r->getAttribute('n'), 'kos' => SusutNilai::bundar((string) $r->getAttribute('v')), '_pautan' => '/admin/ast/aset/'.$r->getAttribute('id'),
            ])->all(),
            'ringkasan' => [['label' => 'Jumlah kos '.$tahun.' (RM)', 'nilai' => SusutNilai::jumlah($perAset->map(fn ($r) => (string) $r->getAttribute('v')))]],
            'kumpulan' => [
                'kategori' => $perKat,
                'bulan' => array_map(fn ($m) => ['kod' => (string) $m, 'label' => now()->setDate($tahun, $m, 1)->locale('ms')->translatedFormat('M'), 'bilangan' => 0, 'nilai' => SusutNilai::bundar((string) ($bulanan[$m] ?? '0'))], range(1, 12)),
            ],
        ];
    }

    /**
     * SRS-AST-21/26: never inspected, or last inspection older than the category's period.
     *
     * @param  array<string, mixed>  $f
     * @return Builder<AsetFizikal>
     */
    public function tanpaPemeriksaanQuery(User $user, array $f): Builder
    {
        return $this->asetQuery($user, $f)->join('aset_kategori as k', 'k.id', '=', 'aset_fizikal.id_kategori')
            ->where(fn ($w) => $w->whereNull('aset_fizikal.tarikh_pemeriksaan_terakhir')
                ->orWhereRaw("aset_fizikal.tarikh_pemeriksaan_terakhir < CURRENT_DATE - (k.tempoh_pemeriksaan_bulan || ' months')::interval"))
            ->select('aset_fizikal.*', 'k.tempoh_pemeriksaan_bulan');
    }

    /** @param array<string, mixed> $f */
    private function tanpaPemeriksaan(User $user, array $f): array
    {
        $rows = $this->tanpaPemeriksaanQuery($user, $f)->orderByRaw('aset_fizikal.tarikh_pemeriksaan_terakhir NULLS FIRST')->limit(5000)->get();

        return [
            'tajuk' => 'Aset tanpa pemeriksaan melebihi tempoh',
            'lajur' => $this->lajur(['no_rujukan_aset' => 'No. rujukan', 'nama_aset' => 'Nama aset', 'daerah' => 'Daerah', 'tarikh_pemeriksaan_terakhir' => 'Pemeriksaan terakhir', 'tempoh_bulan' => 'Tempoh ditetapkan (bulan)', 'kondisi' => 'Kondisi']),
            'baris' => $rows->map(fn (AsetFizikal $a) => [
                'no_rujukan_aset' => $a->no_rujukan_aset, 'nama_aset' => $a->nama_aset, 'daerah' => $this->ref->namaDaerah($a->kod_daerah),
                'tarikh_pemeriksaan_terakhir' => $a->tarikh_pemeriksaan_terakhir?->toDateString() ?? 'Belum pernah', 'tempoh_bulan' => (int) $a->getAttribute('tempoh_pemeriksaan_bulan'),
                'kondisi' => Lalai::KONDISI[$a->status_kondisi] ?? $a->status_kondisi, '_pautan' => '/admin/ast/aset/'.$a->id,
            ])->all(),
            'ringkasan' => [['label' => 'Bilangan aset', 'nilai' => $rows->count()]],
        ];
    }

    /** @param array<string, mixed> $f */
    private function jadual(User $user, array $f): array
    {
        $hari = max(7, min(365, (int) ($f['hari'] ?? 90)));
        $rows = JadualPenyelenggaraan::query()->untuk($user)->with(['aset' => fn ($q) => $q->withoutGlobalScope('access'), 'pegawai'])->where('status', 'aktif')
            ->whereDate('tarikh_jatuh_tempoh', '<=', now()->addDays($hari)->toDateString())
            ->when(! empty($f['kod_daerah']), fn ($q) => $q->where('kod_daerah', $f['kod_daerah']))->orderBy('tarikh_jatuh_tempoh')->limit(5000)->get();
        $today = now()->startOfDay();

        return [
            'tajuk' => 'Jadual penyelenggaraan — tertunggak dan '.$hari.' hari akan datang',
            'lajur' => $this->lajur(['tarikh_jatuh_tempoh' => 'Jatuh tempoh', 'status' => 'Status', 'no_rujukan_aset' => 'No. rujukan', 'nama_aset' => 'Aset', 'jenis' => 'Jenis penyelenggaraan', 'kitaran_bulan' => 'Kitaran (bulan)', 'pegawai' => 'Pegawai']),
            'baris' => $rows->map(fn (JadualPenyelenggaraan $j) => [
                'tarikh_jatuh_tempoh' => $j->tarikh_jatuh_tempoh->toDateString(), 'status' => $j->tarikh_jatuh_tempoh->lt($today) ? 'Tertunggak' : 'Akan datang',
                'no_rujukan_aset' => $j->aset?->no_rujukan_aset, 'nama_aset' => $j->aset?->nama_aset, 'jenis' => Lalai::JENIS_PENYELENGGARAAN[$j->jenis_penyelenggaraan] ?? $j->jenis_penyelenggaraan,
                'kitaran_bulan' => $j->kitaran_bulan, 'pegawai' => $j->pegawai?->name, '_pautan' => '/admin/ast/aset/'.$j->id_aset,
            ])->all(),
            'ringkasan' => [['label' => 'Tertunggak', 'nilai' => $rows->filter(fn ($j) => $j->tarikh_jatuh_tempoh->lt($today))->count()], ['label' => 'Akan datang', 'nilai' => $rows->filter(fn ($j) => ! $j->tarikh_jatuh_tempoh->lt($today))->count()]],
        ];
    }

    /** @param array<string, mixed> $f */
    private function sejarahNilai(User $user, array $f): array
    {
        $rows = DB::table('aset_nilai_sejarah as n')->join('aset_fizikal as a', 'a.id', '=', 'n.id_aset')->whereIn('a.id', $this->asetQuery($user, ['termasuk_arkib' => true] + $f)->select('aset_fizikal.id'))
            ->when(! empty($f['id_aset']), fn ($q) => $q->where('a.id', $f['id_aset']))
            ->orderBy('a.no_rujukan_aset')->orderBy('n.tahun')->orderBy('n.tarikh_penilaian')->limit(5000)
            ->get(['a.id', 'a.no_rujukan_aset', 'a.nama_aset', 'n.tahun', 'n.nilai', 'n.nilai_sebelum', 'n.kaedah_penilaian', 'n.id_laporan_penilaian', 'n.tarikh_penilaian']);

        return [
            'tajuk' => 'Sejarah nilai aset mengikut tahun',
            'lajur' => $this->lajur(['no_rujukan_aset' => 'No. rujukan', 'nama_aset' => 'Aset', 'tahun' => 'Tahun', 'tarikh_penilaian' => 'Tarikh penilaian', 'nilai_sebelum' => 'Nilai sebelum (RM)', 'nilai' => 'Nilai (RM)', 'kaedah' => 'Kaedah', 'laporan' => 'Rujukan laporan']),
            'baris' => $rows->map(fn ($r) => [
                'no_rujukan_aset' => $r->no_rujukan_aset, 'nama_aset' => $r->nama_aset, 'tahun' => (int) $r->tahun, 'tarikh_penilaian' => $r->tarikh_penilaian,
                'nilai_sebelum' => $r->nilai_sebelum, 'nilai' => $r->nilai, 'kaedah' => Lalai::KAEDAH_PENILAIAN[$r->kaedah_penilaian] ?? $r->kaedah_penilaian, 'laporan' => $r->id_laporan_penilaian,
                '_pautan' => '/admin/ast/aset/'.$r->id,
            ])->all(),
            'ringkasan' => [['label' => 'Rekod penilaian', 'nilai' => $rows->count()]],
        ];
    }

    /** @param array<string, mixed> $f */
    private function logImbasan(User $user, array $f): array
    {
        $dari = (string) ($f['dari'] ?? now()->subDays(30)->toDateString());
        $hingga = (string) ($f['hingga'] ?? now()->toDateString());
        $rows = AsetQrLogImbasan::query()->with('pengguna')->whereIn('id_aset', $this->asetQuery($user, ['termasuk_arkib' => true] + $f)->select('aset_fizikal.id'))
            ->when(! empty($f['id_aset']), fn ($q) => $q->where('id_aset', $f['id_aset']))
            ->whereDate('cap_masa', '>=', $dari)->whereDate('cap_masa', '<=', $hingga)->orderByDesc('cap_masa')->limit(5000)->get();
        $aset = AsetFizikal::query()->withoutGlobalScope('access')->whereIn('id', $rows->pluck('id_aset')->filter()->unique()->all())->get(['id', 'no_rujukan_aset', 'nama_aset'])->keyBy('id');

        return [
            'tajuk' => 'Log imbasan QR '.$dari.' hingga '.$hingga,
            'lajur' => $this->lajur(['cap_masa' => 'Masa', 'no_rujukan_aset' => 'No. rujukan', 'nama_aset' => 'Aset', 'pengimbas' => 'Pengimbas', 'peranan' => 'Peranan', 'paparan' => 'Paparan', 'lokasi' => 'Lokasi peranti']),
            'baris' => $rows->map(fn (AsetQrLogImbasan $l) => [
                'cap_masa' => $l->cap_masa->toIso8601String(), 'no_rujukan_aset' => $aset[$l->id_aset]->no_rujukan_aset ?? '', 'nama_aset' => $aset[$l->id_aset]->nama_aset ?? '',
                'pengimbas' => $l->pengguna->name ?? 'Tanpa akaun', 'peranan' => $l->peranan_pengimbas, 'paparan' => ['minimum' => 'Minimum', 'penuh' => 'Penuh', 'tidak_aktif' => 'QR tidak aktif'][$l->jenis_paparan] ?? $l->jenis_paparan,
                'lokasi' => $l->latitud_peranti !== null ? $l->latitud_peranti.', '.$l->longitud_peranti : null, '_pautan' => $l->id_aset ? '/admin/ast/aset/'.$l->id_aset : null,
            ])->all(),
            'ringkasan' => [['label' => 'Imbasan', 'nilai' => $rows->count()], ['label' => 'Tanpa akaun', 'nilai' => $rows->whereNull('id_pengguna')->count()]],
        ];
    }

    /**
     * @param  array<string, string>  $map
     * @return list<array{kod: string, label: string}>
     */
    private function lajur(array $map): array
    {
        return array_map(fn ($k, $v) => ['kod' => $k, 'label' => $v], array_keys($map), $map);
    }
}
