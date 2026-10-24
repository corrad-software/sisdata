<?php

namespace App\Modules\Ptn\Services;

use App\Models\User;
use App\Modules\Ptn\Models\IsuPemantauan;
use App\Modules\Ptn\Models\JadualPemantauan;
use App\Modules\Ptn\Models\KeperluanPenyelenggaraan;
use App\Modules\Ptn\Models\RekodLawatan;
use App\Modules\Ptn\Models\RiskRatingLot;
use App\Modules\Ptn\Models\TindakanPembetulan;
use App\Modules\Ptn\Support\KodWarna;
use App\Modules\Ptn\Support\Lalai;
use App\Modules\Ptn\Support\Rujukan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reports and dashboard figures (SRS-PTN-22/23/24 + "Laporan yang boleh
 * dijana"). Every query starts from a model with ScopedByAccess, so the
 * viewer's daerah/mukim scope applies to dashboards, KPIs and exports alike.
 * Colours use the CURRENT thresholds (KodWarna).
 */
class Analitik
{
    public const LAPORAN = [
        'isu' => 'Isu pemantauan mengikut lokasi, kategori, status, pegawai dan tempoh penyelesaian',
        'prestasi' => 'Prestasi pemantauan',
        'tindakan' => 'Tindakan pembetulan tertunggak mengikut kod warna',
        'risiko' => 'Risk rating portfolio mengikut daerah dan kategori',
        'pencerobohan' => 'Penemuan pencerobohan mengikut daerah dan tempoh',
        'penyelenggaraan' => 'Keperluan penyelenggaraan yang dikenal pasti',
    ];

    public function __construct(private KodWarna $warna, private RisikoService $risiko, private Rujukan $ref) {}

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $q
     * @param  array<string, mixed>  $f
     * @return Builder<T>
     */
    private function tapis(Builder $q, array $f, string $table, string $dateCol): Builder
    {
        return $q->when($f['kod_daerah'] ?? null, fn ($q, $v) => $q->where("{$table}.kod_daerah", $v))
            ->when($f['kod_mukim'] ?? null, fn ($q, $v) => $q->where("{$table}.kod_mukim", $v))
            ->when($f['dari'] ?? null, fn ($q, $v) => $q->where("{$table}.{$dateCol}", '>=', $v))
            ->when($f['hingga'] ?? null, fn ($q, $v) => $q->where("{$table}.{$dateCol}", '<=', $v));
    }

    /** @return array{0: string, 1: list<float>} */
    private function warnaSql(): array
    {
        [$case, $b] = $this->warna->sqlSkor('risk_rating_lot.skor_keseluruhan');

        return ["CASE WHEN risk_rating_lot.paksa_merah THEN 'merah' ELSE {$case} END", $b];
    }

    /**
     * SRS-PTN-24 risk dashboard.
     *
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    public function dashboard(User $user, array $f = []): array
    {
        [$case, $b] = $this->warnaSql();
        $base = RiskRatingLot::query()->untuk($user)->where('risk_rating_lot.semasa', true)
            ->when($f['kod_daerah'] ?? null, fn ($q, $v) => $q->where('risk_rating_lot.kod_daerah', $v));
        $dist = (clone $base)->toBase()->selectRaw("{$case} AS warna, COUNT(*) AS n", $b)->groupByRaw('1')->get()->mapWithKeys(fn ($r) => [$r->warna => $r->n]);
        $high = (clone $base)->toBase()->join('lot_tanah as l', 'l.id', '=', 'risk_rating_lot.lot_id')
            ->selectRaw("risk_rating_lot.lot_id, l.no_lot, l.kod_daerah, l.kod_mukim, risk_rating_lot.skor_keseluruhan, risk_rating_lot.paksa_merah, {$case} AS warna", $b)
            ->orderByDesc('risk_rating_lot.paksa_merah')->orderByDesc('risk_rating_lot.skor_keseluruhan')->limit(15)->get()
            ->map(fn ($r) => ['lot_id' => $r->lot_id, 'no_lot' => $r->no_lot, 'daerah' => $this->ref->namaDaerah($r->kod_daerah), 'mukim' => $this->ref->namaMukim($r->kod_mukim), 'skor' => (float) $r->skor_keseluruhan, 'kod_warna' => $r->warna, 'paksa_merah' => (bool) $r->paksa_merah])->all();
        $open = $this->tapis(IsuPemantauan::query()->untuk($user), $f, 'isu_pemantauan', 'tarikh_dibuka')->whereIn('status_isu', IsuPemantauan::TERBUKA);
        $actions = $this->tapis(TindakanPembetulan::query()->untuk($user), $f, 'tindakan_pembetulan', 'tarikh_sasaran')->whereIn('status_kemajuan', ['belum_mula', 'dalam_tindakan'])->get(['id', 'tarikh_sasaran', 'status_kemajuan']);
        $byColour = ['hijau' => 0, 'kuning' => 0, 'merah' => 0];
        foreach ($actions as $a) {
            $byColour[$this->warna->untukTarikh($a->tarikh_sasaran, 'tindakan')]++;
        }
        $prestasi = $this->prestasi($user, ['tahun' => (int) ($f['tahun'] ?? now()->year)] + $f);

        return [
            'taburan_warna' => ['hijau' => (int) ($dist['hijau'] ?? 0), 'kuning' => (int) ($dist['kuning'] ?? 0), 'merah' => (int) ($dist['merah'] ?? 0)],
            'lot_risiko_tinggi' => $high,
            'isu_terbuka' => (clone $open)->count(),
            'isu_kritikal_terbuka' => (clone $open)->where('tahap_keterukan', 'kritikal')->count(),
            'tindakan_tertunggak' => $byColour['merah'],
            'tindakan_mengikut_warna' => $byColour,
            'lawatan_tertunggak' => $prestasi['bil_tertunggak'],
            'peratus_ikut_jadual' => $prestasi['peratus_ikut_jadual'],
            'trend' => $this->risiko->trend($user, $f, 12),
            'legenda' => array_map(fn ($k) => KodWarna::papar($k), array_keys(Lalai::WARNA)),
        ];
    }

    /**
     * SRS-PTN-23: % of due schedules visited by their target date, overdue
     * list, average days between consecutive visits, findings by category.
     *
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    public function prestasi(User $user, array $f = []): array
    {
        $tahun = (int) ($f['tahun'] ?? now()->year);
        $today = now()->toDateString();
        $sched = JadualPemantauan::query()->untuk($user)->where('tahun', $tahun)->where('status_pelaksanaan', '!=', 'dibatalkan')
            ->when($f['kod_daerah'] ?? null, fn ($q, $v) => $q->where('jadual_pemantauan.kod_daerah', $v));
        $due = (clone $sched)->where('tarikh_sasaran', '<=', $today);
        $dueCount = (clone $due)->count();
        $onTime = (clone $due)->whereExists(fn ($q) => $q->select(DB::raw(1))->from('rekod_lawatan as v')->whereColumn('v.id_jadual', 'jadual_pemantauan.id')
            ->whereIn('v.status_lawatan', ['dihantar', 'disahkan'])->whereColumn('v.tarikh_lawatan', '<=', 'jadual_pemantauan.tarikh_sasaran'))->count();
        $done = (clone $sched)->whereExists(fn ($q) => $q->select(DB::raw(1))->from('rekod_lawatan as v')->whereColumn('v.id_jadual', 'jadual_pemantauan.id')->whereIn('v.status_lawatan', ['dihantar', 'disahkan']))->count();
        $overdue = (clone $sched)->toBase()->whereIn('status_pelaksanaan', JadualPemantauan::TERBUKA)->where('tarikh_sasaran', '<', $today)
            ->join('lot_tanah as l', 'l.id', '=', 'jadual_pemantauan.lot_id')->leftJoin('users as u', 'u.id', '=', 'jadual_pemantauan.id_pegawai_bertugas')
            ->orderBy('jadual_pemantauan.tarikh_sasaran')
            ->get(['jadual_pemantauan.id', 'jadual_pemantauan.lot_id', 'l.no_lot', 'jadual_pemantauan.kod_daerah', 'jadual_pemantauan.tarikh_sasaran', 'jadual_pemantauan.keutamaan_risiko', 'u.name as pegawai']);
        $overdueRows = $overdue->filter(fn ($r) => $this->warna->untukTarikh(Carbon::parse($r->tarikh_sasaran), 'lawatan') === 'merah')->values();
        $lots = (clone $sched)->distinct()->count('lot_id');
        $visitedLots = RekodLawatan::query()->untuk($user)->whereIn('status_lawatan', ['dihantar', 'disahkan'])->whereYear('tarikh_lawatan', $tahun)
            ->when($f['kod_daerah'] ?? null, fn ($q, $v) => $q->where('kod_daerah', $v))->distinct()->count('lot_id');

        $visits = RekodLawatan::query()->untuk($user)->whereIn('status_lawatan', ['dihantar', 'disahkan'])
            ->when($f['kod_daerah'] ?? null, fn ($q, $v) => $q->where('kod_daerah', $v))->toBase()
            ->selectRaw('lot_id, tarikh_lawatan, LAG(tarikh_lawatan) OVER (PARTITION BY lot_id ORDER BY tarikh_lawatan) AS sebelum');
        $avg = DB::query()->fromSub($visits, 'v')->whereNotNull('sebelum')->selectRaw('AVG(tarikh_lawatan - sebelum) AS purata')->value('purata');

        $findings = IsuPemantauan::query()->untuk($user)->whereYear('tarikh_dibuka', $tahun)->when($f['kod_daerah'] ?? null, fn ($q, $v) => $q->where('kod_daerah', $v))
            ->groupBy('kategori_isu')->selectRaw('kategori_isu, COUNT(*) AS n')->orderByDesc('n')->get()->pluck('n', 'kategori_isu');

        return [
            'tahun' => $tahun,
            'bil_jadual' => (clone $sched)->count(),
            'bil_perlu_setakat_ini' => $dueCount,
            'bil_ikut_jadual' => $onTime,
            'bil_dilaksana' => $done,
            'peratus_ikut_jadual' => $dueCount > 0 ? round($onTime / $dueCount * 100, 1) : null,
            'bil_lot_dijadual' => $lots,
            'bil_lot_dilawat' => $visitedLots,
            'peratus_lot_dilawat' => $lots > 0 ? round(min($visitedLots, $lots) / $lots * 100, 1) : null,
            'purata_hari_antara_lawatan' => $avg !== null ? round((float) $avg, 1) : null,
            'bil_tertunggak' => $overdueRows->count(),
            'tertunggak' => $overdueRows->take(200)->map(fn ($r) => ['id' => $r->id, 'lot_id' => $r->lot_id, 'no_lot' => $r->no_lot, 'daerah' => $this->ref->namaDaerah($r->kod_daerah), 'tarikh_sasaran' => Carbon::parse($r->tarikh_sasaran)->toDateString(), 'hari_lewat' => (int) Carbon::parse($r->tarikh_sasaran)->diffInDays(now()->startOfDay()), 'keutamaan' => $r->keutamaan_risiko, 'pegawai' => $r->pegawai])->all(),
            'penemuan_mengikut_kategori' => collect($findings)->map(fn ($n, $k) => ['kod' => $k, 'label' => Lalai::KATEGORI_ISU[$k] ?? $k, 'jumlah' => (int) $n])->values()->all(),
        ];
    }

    /**
     * SRS-PTN-22.
     *
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    public function isu(User $user, array $f = []): array
    {
        $q = $this->tapis(IsuPemantauan::query()->untuk($user), $f, 'isu_pemantauan', 'tarikh_dibuka')
            ->when($f['kategori_isu'] ?? null, fn ($q, $v) => $q->where('kategori_isu', $v))
            ->when($f['status_isu'] ?? null, fn ($q, $v) => $q->where('status_isu', $v))
            ->when($f['tahap_keterukan'] ?? null, fn ($q, $v) => $q->where('tahap_keterukan', $v));
        $group = function (string $col) use ($q) {
            return (clone $q)->toBase()->groupBy($col)->selectRaw("{$col} AS kod, COUNT(*) AS jumlah, COUNT(*) FILTER (WHERE status_isu IN ('terbuka','dalam_tindakan')) AS terbuka, AVG(CASE WHEN tarikh_ditutup IS NOT NULL THEN tarikh_ditutup - tarikh_dibuka END) AS purata_hari")
                ->orderByDesc('jumlah')->get()->map(fn ($r) => ['kod' => $r->kod, 'jumlah' => (int) $r->jumlah, 'terbuka' => (int) $r->terbuka, 'purata_hari_selesai' => $r->purata_hari !== null ? round((float) $r->purata_hari, 1) : null])->all();
        };
        $label = fn (array $rows, callable $fn) => array_map(fn ($r) => $r + ['label' => $fn($r['kod'])], $rows);
        $byOfficer = (clone $q)->toBase()->join('tindakan_pembetulan as t', 't.id_isu', '=', 'isu_pemantauan.id')->leftJoin('users as u', 'u.id', '=', 't.id_pegawai_bertanggungjawab')
            ->groupBy('u.name')->selectRaw("COALESCE(u.name, '—') AS kod, COUNT(DISTINCT isu_pemantauan.id) AS jumlah, COUNT(*) FILTER (WHERE t.status_kemajuan <> 'selesai') AS terbuka")
            ->orderByDesc('jumlah')->get()->map(fn ($r) => ['kod' => $r->kod, 'label' => $r->kod, 'jumlah' => (int) $r->jumlah, 'terbuka' => (int) $r->terbuka, 'purata_hari_selesai' => null])->all();
        $rows = (clone $q)->join('lot_tanah as l', 'l.id', '=', 'isu_pemantauan.lot_id')->orderByDesc('tarikh_dibuka')->limit(500)
            ->get(['isu_pemantauan.*', 'l.no_lot']);

        return [
            'mengikut_daerah' => $label($group('kod_daerah'), fn ($k) => $this->ref->namaDaerah($k)),
            'mengikut_mukim' => $label($group('kod_mukim'), fn ($k) => $this->ref->namaMukim($k)),
            'mengikut_kategori' => $label($group('kategori_isu'), fn ($k) => Lalai::KATEGORI_ISU[$k] ?? $k),
            'mengikut_status' => $label($group('status_isu'), fn ($k) => Lalai::STATUS_ISU[$k] ?? $k),
            'mengikut_keterukan' => $label($group('tahap_keterukan'), fn ($k) => Lalai::TAHAP_KETERUKAN[$k] ?? $k),
            'mengikut_pegawai' => $byOfficer,
            'senarai' => $rows->map(fn ($i) => [
                'id' => $i->id, 'no_rujukan' => $i->no_rujukan, 'no_lot' => $i->getAttribute('no_lot'), 'daerah' => $this->ref->namaDaerah($i->kod_daerah),
                'kategori' => Lalai::KATEGORI_ISU[$i->kategori_isu] ?? $i->kategori_isu, 'tahap' => $i->tahap_keterukan, 'status' => $i->status_isu,
                'tarikh_dibuka' => $i->tarikh_dibuka->toDateString(), 'tarikh_ditutup' => $i->tarikh_ditutup?->toDateString(),
                'tempoh_hari' => (int) $i->tarikh_dibuka->diffInDays($i->tarikh_ditutup ?? now()->startOfDay()),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    public function tindakan(User $user, array $f = []): array
    {
        $rows = $this->tapis(TindakanPembetulan::query()->untuk($user), $f, 'tindakan_pembetulan', 'tarikh_sasaran')->whereIn('status_kemajuan', ['belum_mula', 'dalam_tindakan', 'menunggu_pengesahan'])
            ->with(['pegawai', 'isu'])->orderBy('tarikh_sasaran')->limit(1000)->get();
        $out = [];
        foreach ($rows as $t) {
            $c = $this->warna->untukTarikh($t->tarikh_sasaran, 'tindakan', $t->status_kemajuan === 'menunggu_pengesahan');
            if (($f['kod_warna'] ?? null) && $f['kod_warna'] !== $c) {
                continue;
            }
            $out[] = [
                'id' => $t->id, 'id_isu' => $t->id_isu, 'no_rujukan_isu' => $t->isu?->no_rujukan, 'langkah_tindakan' => $t->langkah_tindakan,
                'pegawai' => $t->pegawai?->name, 'tarikh_sasaran' => $t->tarikh_sasaran->toDateString(), 'status_kemajuan' => $t->status_kemajuan,
                'peratus_kemajuan' => $t->peratus_kemajuan, 'kod_warna' => $c, 'daerah' => $this->ref->namaDaerah($t->kod_daerah),
                'hari_berbaki' => (int) now()->startOfDay()->diffInDays($t->tarikh_sasaran, false),
            ];
        }
        $count = ['hijau' => 0, 'kuning' => 0, 'merah' => 0];
        foreach ($out as $r) {
            $count[$r['kod_warna']]++;
        }

        return ['mengikut_warna' => $count, 'senarai' => $out];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    public function risiko(User $user, array $f = []): array
    {
        [$case, $b] = $this->warnaSql();
        $base = RiskRatingLot::query()->untuk($user)->where('risk_rating_lot.semasa', true)->join('lot_tanah as l', 'l.id', '=', 'risk_rating_lot.lot_id')
            ->when($f['kod_daerah'] ?? null, fn ($q, $v) => $q->where('risk_rating_lot.kod_daerah', $v));
        $agg = fn (string $col) => (clone $base)->toBase()->groupBy(DB::raw($col))
            ->selectRaw("{$col} AS kod, COUNT(*) AS jumlah, COUNT(*) FILTER (WHERE {$case} = 'hijau') AS hijau, COUNT(*) FILTER (WHERE {$case} = 'kuning') AS kuning, COUNT(*) FILTER (WHERE {$case} = 'merah') AS merah, AVG(risk_rating_lot.skor_keseluruhan) AS purata", [...$b, ...$b, ...$b])
            ->orderBy('kod')->get()->map(fn ($r) => ['kod' => $r->kod, 'jumlah' => (int) $r->jumlah, 'hijau' => (int) $r->hijau, 'kuning' => (int) $r->kuning, 'merah' => (int) $r->merah, 'purata' => round((float) $r->purata, 1)])->all();
        $byDistrict = array_map(fn ($r) => $r + ['label' => $this->ref->namaDaerah($r['kod'])], $agg('risk_rating_lot.kod_daerah'));
        $byCat = array_map(fn ($r) => $r + ['label' => $this->ref->kategoriGunaTanah()[$r['kod'] ?? ''] ?? ($r['kod'] ?? 'Tiada')], $agg("COALESCE(l.kategori_guna_tanah, 'TIADA')"));
        $catAvg = (clone $base)->toBase()->selectRaw('AVG(risk_rating_lot.skor_pencerobohan) AS pencerobohan, AVG(risk_rating_lot.skor_tunggakan_cukai) AS tunggakan_cukai, AVG(risk_rating_lot.skor_pertikaian) AS pertikaian, AVG(risk_rating_lot.skor_dokumen) AS dokumen, AVG(risk_rating_lot.skor_tamat_kontrak) AS tamat_kontrak, AVG(risk_rating_lot.skor_potensi_hasil) AS potensi_hasil')->first();
        $kategori = [];
        foreach (Lalai::KATEGORI_RISIKO as $kod => [$labelK]) {
            $kategori[] = ['kod' => $kod, 'label' => $labelK, 'purata' => round((float) ($catAvg->{$kod} ?? 0), 1)];
        }

        return ['mengikut_daerah' => $byDistrict, 'mengikut_kategori_lot' => $byCat, 'purata_kategori_risiko' => $kategori];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    public function pencerobohan(User $user, array $f = []): array
    {
        $q = $this->tapis(IsuPemantauan::query()->untuk($user), $f, 'isu_pemantauan', 'tarikh_dibuka')->whereIn('kategori_isu', Lalai::PENCEROBOHAN);
        $byDistrict = (clone $q)->toBase()->groupBy('kod_daerah')->selectRaw("kod_daerah, COUNT(*) AS jumlah, COUNT(*) FILTER (WHERE status_isu IN ('terbuka','dalam_tindakan')) AS terbuka")
            ->orderByDesc('jumlah')->get()->map(fn ($r) => ['kod' => $r->kod_daerah, 'label' => $this->ref->namaDaerah($r->kod_daerah), 'jumlah' => (int) $r->jumlah, 'terbuka' => (int) $r->terbuka])->all();
        $byMonth = (clone $q)->toBase()->groupByRaw("to_char(tarikh_dibuka, 'YYYY-MM')")->selectRaw("to_char(tarikh_dibuka, 'YYYY-MM') AS bulan, COUNT(*) AS jumlah")
            ->orderBy('bulan')->get()->map(fn ($r) => ['bulan' => $r->bulan, 'jumlah' => (int) $r->jumlah])->all();

        return ['mengikut_daerah' => $byDistrict, 'mengikut_bulan' => $byMonth];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    public function penyelenggaraan(User $user, array $f = []): array
    {
        $rows = $this->tapis(KeperluanPenyelenggaraan::query()->untuk($user), $f, 'keperluan_penyelenggaraan', 'created_at')
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('keperluan_penyelenggaraan.status', $v))
            ->join('lot_tanah as l', 'l.id', '=', 'keperluan_penyelenggaraan.lot_id')->orderByDesc('keperluan_penyelenggaraan.created_at')->limit(1000)
            ->get(['keperluan_penyelenggaraan.*', 'l.no_lot']);

        return ['senarai' => $rows->map(fn ($k) => [
            'id' => $k->id, 'lot_id' => $k->lot_id, 'no_lot' => $k->getAttribute('no_lot'), 'daerah' => $this->ref->namaDaerah($k->kod_daerah),
            'jenis' => Lalai::JENIS_PENYELENGGARAAN[$k->jenis] ?? $k->jenis, 'keterangan' => $k->keterangan, 'keutamaan' => $k->keutamaan,
            'status' => $k->status, 'tarikh_jadual' => $k->tarikh_jadual?->toDateString(), 'id_aset_fizikal' => $k->id_aset_fizikal,
            'id_lawatan' => $k->id_lawatan, 'tarikh' => $k->created_at->toDateString(),
        ])->all()];
    }

    /**
     * Tabular sections for CSV/PDF export of a report.
     *
     * @param  array<string, mixed>  $d
     * @return list<array{tajuk: string, lajur: list<string>, baris: list<list<scalar|null>>}>
     */
    public function seksyen(string $laporan, array $d): array
    {
        $grp = fn (string $t, array $rows) => ['tajuk' => $t, 'lajur' => ['Kod', 'Nama', 'Jumlah', 'Terbuka', 'Purata hari selesai'], 'baris' => array_map(fn ($r) => [$r['kod'], $r['label'] ?? $r['kod'], $r['jumlah'], $r['terbuka'] ?? null, $r['purata_hari_selesai'] ?? null], $rows)];

        return match ($laporan) {
            'isu' => [
                $grp('Mengikut daerah', $d['mengikut_daerah']), $grp('Mengikut mukim', $d['mengikut_mukim']), $grp('Mengikut kategori', $d['mengikut_kategori']),
                $grp('Mengikut status', $d['mengikut_status']), $grp('Mengikut pegawai bertanggungjawab', $d['mengikut_pegawai']),
                ['tajuk' => 'Senarai isu', 'lajur' => ['No. rujukan', 'No. lot', 'Daerah', 'Kategori', 'Tahap', 'Status', 'Dibuka', 'Ditutup', 'Tempoh (hari)'], 'baris' => array_map(fn ($r) => [$r['no_rujukan'], $r['no_lot'], $r['daerah'], $r['kategori'], $r['tahap'], $r['status'], $r['tarikh_dibuka'], $r['tarikh_ditutup'], $r['tempoh_hari']], $d['senarai'])],
            ],
            'prestasi' => [
                ['tajuk' => 'Ringkasan '.$d['tahun'], 'lajur' => ['Petunjuk', 'Nilai'], 'baris' => [
                    ['Jadual lawatan', $d['bil_jadual']], ['Perlu dilaksana setakat ini', $d['bil_perlu_setakat_ini']], ['Dilaksana ikut jadual', $d['bil_ikut_jadual']],
                    ['% ikut jadual', $d['peratus_ikut_jadual']], ['% lot dilawat', $d['peratus_lot_dilawat']], ['Purata hari antara lawatan', $d['purata_hari_antara_lawatan']], ['Lawatan tertunggak', $d['bil_tertunggak']],
                ]],
                ['tajuk' => 'Lawatan tertunggak', 'lajur' => ['No. lot', 'Daerah', 'Tarikh sasaran', 'Hari lewat', 'Keutamaan', 'Pegawai'], 'baris' => array_map(fn ($r) => [$r['no_lot'], $r['daerah'], $r['tarikh_sasaran'], $r['hari_lewat'], $r['keutamaan'], $r['pegawai']], $d['tertunggak'])],
                ['tajuk' => 'Taburan penemuan mengikut kategori', 'lajur' => ['Kategori', 'Jumlah'], 'baris' => array_map(fn ($r) => [$r['label'], $r['jumlah']], $d['penemuan_mengikut_kategori'])],
            ],
            'tindakan' => [['tajuk' => 'Tindakan pembetulan belum selesai', 'lajur' => ['Isu', 'Langkah', 'Pegawai', 'Tarikh sasaran', 'Hari berbaki', 'Status', 'Kod warna', 'Daerah'], 'baris' => array_map(fn ($r) => [$r['no_rujukan_isu'], $r['langkah_tindakan'], $r['pegawai'], $r['tarikh_sasaran'], $r['hari_berbaki'], $r['status_kemajuan'], strtoupper($r['kod_warna']), $r['daerah']], $d['senarai'])]],
            'risiko' => [
                ['tajuk' => 'Mengikut daerah', 'lajur' => ['Daerah', 'Lot', 'Hijau', 'Kuning', 'Merah', 'Purata skor'], 'baris' => array_map(fn ($r) => [$r['label'], $r['jumlah'], $r['hijau'], $r['kuning'], $r['merah'], $r['purata']], $d['mengikut_daerah'])],
                ['tajuk' => 'Mengikut kategori guna tanah', 'lajur' => ['Kategori', 'Lot', 'Hijau', 'Kuning', 'Merah', 'Purata skor'], 'baris' => array_map(fn ($r) => [$r['label'], $r['jumlah'], $r['hijau'], $r['kuning'], $r['merah'], $r['purata']], $d['mengikut_kategori_lot'])],
                ['tajuk' => 'Purata skor kategori risiko', 'lajur' => ['Kategori', 'Purata'], 'baris' => array_map(fn ($r) => [$r['label'], $r['purata']], $d['purata_kategori_risiko'])],
            ],
            'pencerobohan' => [
                ['tajuk' => 'Mengikut daerah', 'lajur' => ['Daerah', 'Jumlah', 'Terbuka'], 'baris' => array_map(fn ($r) => [$r['label'], $r['jumlah'], $r['terbuka']], $d['mengikut_daerah'])],
                ['tajuk' => 'Mengikut bulan', 'lajur' => ['Bulan', 'Jumlah'], 'baris' => array_map(fn ($r) => [$r['bulan'], $r['jumlah']], $d['mengikut_bulan'])],
            ],
            default => [['tajuk' => 'Keperluan penyelenggaraan', 'lajur' => ['Tarikh', 'No. lot', 'Daerah', 'Jenis', 'Keterangan', 'Keutamaan', 'Status', 'Tarikh jadual', 'Aset (MOD-AST)'], 'baris' => array_map(fn ($r) => [$r['tarikh'], $r['no_lot'], $r['daerah'], $r['jenis'], $r['keterangan'], $r['keutamaan'], $r['status'], $r['tarikh_jadual'], $r['id_aset_fizikal']], $d['senarai'])]],
        };
    }
}
