<?php

namespace App\Modules\Tkt\Services;

use App\Modules\Tkt\Models\AktivitiSokongan;
use App\Modules\Tkt\Models\Eskalasi;
use App\Modules\Tkt\Models\LogIntegrasi;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Support\Lalai;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * SRS-TKT-17 dashboard figures and SRS-TKT-19 / §18.6 monthly support
 * report, computed from the real ticket data (never compiled by hand).
 * Aggregates only — the drill-down list goes through the ticket list,
 * which applies the viewer's access (SRS-TKT-20).
 *
 * SLA met = done no later than the target and never flagged as missed
 * (targets already exclude the time spent waiting for the reporter).
 */
class Analitik
{
    public function __construct(private Cadangan $cadangan) {}

    /**
     * @param  array{dari?: string|null, hingga?: string|null, modul?: string|null}  $f
     * @return array<string, mixed>
     */
    public function papanPemuka(array $f = []): array
    {
        $open = Tiket::query()->whereIn('status', Lalai::TERBUKA)->when($f['modul'] ?? null, fn (Builder $q, $m) => $q->where('modul', $m));
        $bySeverity = (clone $open)->selectRaw('keterukan, count(*) as n')->groupBy('keterukan')->pluck('n', 'keterukan')->all();
        $byModule = (clone $open)->selectRaw('modul, count(*) as n')->groupBy('modul')->orderByDesc('n')->pluck('n', 'modul')->all();
        $breachedOpen = (clone $open)->where(fn ($q) => $q->where('selesai_dilepasi', true)->orWhere('respons_dilepasi', true)->orWhere('tarikh_sasaran_selesai', '<', now()))->count();

        $period = $this->tempoh($f);
        $resolved = $this->diselesaikan($period, $f['modul'] ?? null);

        return [
            'tempoh' => ['dari' => $period[0]->toDateString(), 'hingga' => $period[1]->toDateString()],
            'terbuka' => array_sum($bySeverity),
            'terbuka_dilepasi_sla' => $breachedOpen,
            'terbuka_mengikut_keterukan' => array_map(fn (int $s) => ['kod' => (string) $s, 'label' => Lalai::KETERUKAN[$s], 'nilai' => (int) ($bySeverity[$s] ?? 0)], [1, 2, 3, 4]),
            'terbuka_mengikut_modul' => array_map(fn ($m, $n) => ['kod' => (string) $m, 'label' => Lalai::MODUL[$m] ?? (string) $m, 'nilai' => (int) $n], array_keys($byModule), $byModule),
            'sla' => $this->pematuhan($resolved),
            'sla_mengikut_keterukan' => $this->slaMengikutKeterukan($period, $f['modul'] ?? null),
            'mttr_jam' => $this->mttr($resolved),
            'purata_respons_jam' => $this->purataRespons($period, $f['modul'] ?? null),
            'modul_tertinggi' => $this->modulTertinggi($period),
            'masalah_berulang' => $this->berulang($period),
            'trend' => $this->trend(12, $f['modul'] ?? null),
            'kepuasan' => $this->kepuasan($period),
            'penyelesaian_kendiri' => $this->cadangan->metrik($period[0]->toDateString(), $period[1]->toDateString()),
            'prestasi_juruteknik' => $this->juruteknik($period),
        ];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function tempoh(array $f): array
    {
        $to = ! empty($f['hingga']) ? CarbonImmutable::parse((string) $f['hingga'], KalendarSokongan::TZ)->endOfDay() : CarbonImmutable::now(KalendarSokongan::TZ)->endOfDay();
        $from = ! empty($f['dari']) ? CarbonImmutable::parse((string) $f['dari'], KalendarSokongan::TZ)->startOfDay() : $to->subDays(89)->startOfDay();

        return [$from, $to];
    }

    /**
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $period
     * @return Builder<Tiket>
     */
    private function diselesaikan(array $period, ?string $modul = null): Builder
    {
        return Tiket::query()->whereNotNull('tarikh_selesai')->whereBetween('tarikh_selesai', [$period[0]->utc(), $period[1]->utc()])
            ->when($modul, fn (Builder $q, $m) => $q->where('modul', $m));
    }

    /**
     * @param  Builder<Tiket>  $q
     * @return array{bilangan: int, respons_dipenuhi: int, selesai_dipenuhi: int, peratus_respons: float|null, peratus_selesai: float|null}
     */
    public function pematuhan(Builder $q): array
    {
        $row = (clone $q)->selectRaw('count(*) as n,
            sum(case when tarikh_respons_pertama is not null and tarikh_respons_pertama <= tarikh_sasaran_respons and not respons_dilepasi then 1 else 0 end) as r,
            sum(case when tarikh_selesai <= tarikh_sasaran_selesai and not selesai_dilepasi then 1 else 0 end) as s')->toBase()->first();
        $n = (int) ($row->n ?? 0);

        return [
            'bilangan' => $n,
            'respons_dipenuhi' => (int) ($row->r ?? 0),
            'selesai_dipenuhi' => (int) ($row->s ?? 0),
            'peratus_respons' => $n > 0 ? round(100 * (int) $row->r / $n, 1) : null,
            'peratus_selesai' => $n > 0 ? round(100 * (int) $row->s / $n, 1) : null,
        ];
    }

    /**
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $period
     * @return list<array<string, mixed>>
     */
    public function slaMengikutKeterukan(array $period, ?string $modul = null): array
    {
        return array_map(function (int $s) use ($period, $modul) {
            $q = $this->diselesaikan($period, $modul)->where('keterukan', $s);

            return ['keterukan' => $s, 'label' => Lalai::KETERUKAN[$s]] + $this->pematuhan($q) + ['mttr_jam' => $this->mttr($q)];
        }, [1, 2, 3, 4]);
    }

    /** Mean time to resolve (wall-clock hours, waiting-for-reporter time excluded). */
    public function mttr(Builder $q): ?float
    {
        $v = (clone $q)->selectRaw('avg(greatest(0, extract(epoch from (tarikh_selesai - tarikh_buka)) / 60 - sla_jeda_minit)) as m')->toBase()->value('m');

        return $v === null ? null : round((float) $v / 60, 1);
    }

    /** @param array{0: CarbonImmutable, 1: CarbonImmutable} $period */
    private function purataRespons(array $period, ?string $modul): ?float
    {
        $v = Tiket::query()->whereNotNull('tarikh_respons_pertama')->whereBetween('tarikh_buka', [$period[0]->utc(), $period[1]->utc()])
            ->when($modul, fn (Builder $q, $m) => $q->where('modul', $m))
            ->selectRaw('avg(extract(epoch from (tarikh_respons_pertama - tarikh_buka)) / 60) as m')->toBase()->value('m');

        return $v === null ? null : round((float) $v / 60, 1);
    }

    /**
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $period
     * @return list<array{kod: string, label: string, nilai: int}>
     */
    private function modulTertinggi(array $period): array
    {
        return Tiket::query()->whereBetween('tarikh_buka', [$period[0]->utc(), $period[1]->utc()])
            ->selectRaw('modul, count(*) as n')->groupBy('modul')->orderByDesc('n')->limit(8)->toBase()->get()
            ->map(fn ($r) => ['kod' => (string) $r->modul, 'label' => Lalai::MODUL[$r->modul] ?? (string) $r->modul, 'nilai' => (int) $r->n])->values()->all();
    }

    /**
     * Recurring problems: module + category combinations and repeated
     * titles seen more than once in the period (input to preventive
     * maintenance).
     *
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $period
     * @return array{gabungan: list<array<string, mixed>>, tajuk: list<array<string, mixed>>}
     */
    public function berulang(array $period): array
    {
        $range = [$period[0]->utc(), $period[1]->utc()];
        $combos = Tiket::query()->whereBetween('tarikh_buka', $range)->whereNotIn('status', ['pendua'])
            ->selectRaw('modul, kategori, count(*) as n')->groupBy('modul', 'kategori')->havingRaw('count(*) > 1')->orderByDesc('n')->limit(10)->toBase()->get()
            ->map(fn ($r) => ['modul' => (string) $r->modul, 'modul_nama' => Lalai::MODUL[$r->modul] ?? $r->modul, 'kategori' => (string) $r->kategori, 'kategori_nama' => Lalai::KATEGORI[$r->kategori] ?? $r->kategori, 'bilangan' => (int) $r->n])->values()->all();
        $titles = Tiket::query()->whereBetween('tarikh_buka', $range)
            ->selectRaw("modul, regexp_replace(lower(tajuk), '[^a-z0-9 ]', '', 'g') as kunci, max(tajuk) as tajuk, count(*) as n")
            ->groupBy('modul', 'kunci')->havingRaw('count(*) > 1')->orderByDesc('n')->limit(10)->toBase()->get()
            ->map(fn ($r) => ['modul' => (string) $r->modul, 'tajuk' => (string) $r->tajuk, 'bilangan' => (int) $r->n])->values()->all();

        return ['gabungan' => $combos, 'tajuk' => $titles];
    }

    /** @return list<array{tempoh: string, dibuka: int, diselesaikan: int}> */
    public function trend(int $months, ?string $modul = null): array
    {
        $start = CarbonImmutable::now(KalendarSokongan::TZ)->startOfMonth()->subMonths($months - 1);
        $tz = KalendarSokongan::TZ;
        $opened = Tiket::query()->where('tarikh_buka', '>=', $start->utc())->when($modul, fn (Builder $q, $m) => $q->where('modul', $m))
            ->selectRaw("to_char(tarikh_buka at time zone 'UTC' at time zone '{$tz}', 'YYYY-MM') as b, count(*) as n")->groupBy('b')->pluck('n', 'b')->all();
        $closed = Tiket::query()->where('tarikh_selesai', '>=', $start->utc())->when($modul, fn (Builder $q, $m) => $q->where('modul', $m))
            ->selectRaw("to_char(tarikh_selesai at time zone 'UTC' at time zone '{$tz}', 'YYYY-MM') as b, count(*) as n")->groupBy('b')->pluck('n', 'b')->all();
        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $key = $start->addMonths($i)->format('Y-m');
            $out[] = ['tempoh' => $key, 'dibuka' => (int) ($opened[$key] ?? 0), 'diselesaikan' => (int) ($closed[$key] ?? 0)];
        }

        return $out;
    }

    /**
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $period
     * @return array{bilangan: int, purata: float|null}
     */
    private function kepuasan(array $period): array
    {
        $row = Tiket::query()->whereNotNull('kepuasan_skor')->whereBetween('kepuasan_pada', [$period[0]->utc(), $period[1]->utc()])
            ->selectRaw('count(*) as n, avg(kepuasan_skor) as a')->toBase()->first();

        return ['bilangan' => (int) ($row->n ?? 0), 'purata' => $row?->a !== null ? round((float) $row->a, 2) : null];
    }

    /**
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $period
     * @return list<array<string, mixed>>
     */
    public function juruteknik(array $period): array
    {
        return $this->diselesaikan($period)->whereNotNull('id_juruteknik')
            ->join('users', 'users.id', '=', 'tkt_tiket.id_juruteknik')
            ->selectRaw('users.name as nama, count(*) as n, sum(case when tarikh_selesai <= tarikh_sasaran_selesai and not selesai_dilepasi then 1 else 0 end) as s')
            ->groupBy('users.name')->orderByDesc('n')->limit(20)->toBase()->get()
            ->map(fn ($r) => ['nama' => (string) $r->nama, 'diselesaikan' => (int) $r->n, 'peratus_sla' => (int) $r->n > 0 ? round(100 * (int) $r->s / (int) $r->n, 1) : null])->values()->all();
    }

    /**
     * SRS-TKT-19 / §18.6 monthly support report as export sections.
     *
     * @return array{ringkasan: array<string, mixed>, seksyen: list<array{tajuk: string, lajur: list<string>, baris: list<list<scalar|null>>}>}
     */
    public function laporanBulanan(string $bulan): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $bulan.'-01 00:00:00', KalendarSokongan::TZ);
        $end = $start->endOfMonth();
        $period = [$start, $end];
        $range = [$start->utc(), $end->utc()];
        $fmt = fn ($d) => $d === null ? null : CarbonImmutable::instance($d)->setTimezone(KalendarSokongan::TZ)->format('d/m/Y H:i');

        $opened = Tiket::query()->whereBetween('tarikh_buka', $range);
        $resolved = $this->diselesaikan($period);
        $sla = $this->pematuhan($resolved);
        $openAtEnd = Tiket::query()->where('tarikh_buka', '<=', $range[1])
            ->where(fn ($q) => $q->whereNull('tarikh_tutup')->orWhere('tarikh_tutup', '>', $range[1]))
            ->where(fn ($q) => $q->whereNull('tarikh_selesai')->orWhere('tarikh_selesai', '>', $range[1]))->count();
        $ringkasan = [
            'bulan' => $bulan,
            'dibuka' => (clone $opened)->count(),
            'diselesaikan' => $sla['bilangan'],
            'ditutup' => Tiket::query()->whereBetween('tarikh_tutup', $range)->count(),
            'terbuka_akhir_bulan' => $openAtEnd,
            'peratus_respons' => $sla['peratus_respons'],
            'peratus_selesai' => $sla['peratus_selesai'],
            'mttr_jam' => $this->mttr($resolved),
            'kepuasan' => $this->kepuasan($period),
            'eskalasi' => Eskalasi::query()->where('sebab', '!=', 'hampir_sla')->whereBetween('cap_masa', $range)->distinct('id_tiket')->count('id_tiket'),
        ];

        $matrix = (clone $opened)->selectRaw('kategori, keterukan, count(*) as n')->groupBy('kategori', 'keterukan')->toBase()->get();
        $kategoriRows = [];
        foreach (Lalai::KATEGORI as $kod => $nama) {
            $cells = [];
            foreach ([1, 2, 3, 4] as $s) {
                $cells[] = (int) ($matrix->first(fn ($r) => $r->kategori === $kod && (int) $r->keterukan === $s)->n ?? 0);
            }
            $kategoriRows[] = [$nama, ...$cells, array_sum($cells)];
        }
        $statusRows = (clone $opened)->selectRaw('status, count(*) as n')->groupBy('status')->toBase()->get()
            ->map(fn ($r) => [Lalai::STATUS[$r->status] ?? $r->status, (int) $r->n])->values()->all();
        $slaRows = array_map(fn ($r) => [$r['label'], $r['bilangan'], $r['respons_dipenuhi'], $r['peratus_respons'], $r['selesai_dipenuhi'], $r['peratus_selesai'], $r['mttr_jam']], $this->slaMengikutKeterukan($period));
        $breached = Tiket::query()->with('juruteknik')
            ->where(fn ($q) => $q->where('selesai_dilepasi', true)->orWhere('respons_dilepasi', true))
            ->where(fn ($q) => $q->whereBetween('tarikh_buka', $range)->orWhereBetween('tarikh_selesai', $range))
            ->orderBy('tarikh_buka')->limit(500)->get()
            ->map(fn (Tiket $t) => [$t->nombor_rujukan, Lalai::KETERUKAN_RINGKAS[$t->keterukan] ?? $t->keterukan, Lalai::MODUL[$t->modul ?? 'umum'] ?? $t->modul, $t->respons_dilepasi ? 'Ya' : 'Tidak', $t->selesai_dilepasi ? 'Ya' : 'Tidak', $t->sebab_lepas_sla ?? '—', $t->juruteknik?->name, Lalai::STATUS[$t->status] ?? $t->status])->values()->all();
        $activities = fn (string $jenis) => AktivitiSokongan::query()->where('jenis', $jenis)->whereBetween('tarikh', [$start->toDateString(), $end->toDateString()])->orderBy('tarikh')->get();
        $downtime = $activities('masa_henti')->map(fn (AktivitiSokongan $a) => [$a->tarikh->format('d/m/Y'), $a->tajuk, $a->tempoh_minit, $a->punca_akar, $a->rujukan])->values()->all();
        foreach (Tiket::query()->whereNotNull('masa_henti_minit')->whereBetween('tarikh_selesai', $range)->get() as $t) {
            $downtime[] = [$fmt($t->tarikh_buka), $t->nombor_rujukan.' — '.$t->tajuk, $t->masa_henti_minit, $t->punca_akar, $t->nombor_rujukan];
        }
        $simple = fn (string $jenis) => $activities($jenis)->map(fn (AktivitiSokongan $a) => [$a->tarikh->format('d/m/Y'), $a->tajuk, $a->keterangan, $a->rujukan])->values()->all();
        $recurring = $this->berulang([$start->subMonths(2)->startOfMonth(), $end]);
        $corrective = Tiket::query()->whereNotNull('tindakan_pembetulan')->whereBetween('tarikh_selesai', $range)->orderBy('tarikh_selesai')->limit(200)->get()
            ->map(fn (Tiket $t) => [$t->nombor_rujukan, Lalai::MODUL[$t->modul ?? 'umum'] ?? $t->modul, $t->tindakan_pembetulan])->values()->all();
        foreach ($activities('tindakan_pencegahan') as $a) {
            $corrective[] = ['—', $a->tajuk, $a->keterangan];
        }
        $escalations = Eskalasi::query()->with(['penerima', 'tiket'])->where('sebab', '!=', 'hampir_sla')->whereBetween('cap_masa', $range)->orderBy('cap_masa')->limit(500)->get()
            ->map(fn (Eskalasi $e) => [$fmt($e->cap_masa), $e->tiket?->nombor_rujukan, $e->peringkat, (string) __('tkt::tkt.sebab_eskalasi.'.$e->sebab), (string) __('tkt::tkt.sasaran.'.$e->sasaran), $e->penerima->name ?? $e->jenis_penerima])->values()->all();
        $deflect = $this->cadangan->metrik($start->toDateString(), $end->toDateString());
        $sync = LogIntegrasi::query()->where('arah', 'keluar')->whereBetween('cap_masa', $range)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all();
        $articles = Tiket::query()->whereNotNull('id_artikel_kb')->whereBetween('tarikh_selesai', $range)->get(['nombor_rujukan', 'id_artikel_kb', 'tajuk'])
            ->map(fn (Tiket $t) => [$t->nombor_rujukan, $t->id_artikel_kb, $t->tajuk])->values()->all();

        $ringkasan['bilangan_artikel_kb'] = count($articles);
        $ringkasan['penyelesaian_kendiri'] = $deflect['kadar_penyelesaian_kendiri'];

        $s = fn (string $tajuk, array $lajur, array $baris) => ['tajuk' => $tajuk, 'lajur' => $lajur, 'baris' => $baris];

        return [
            'ringkasan' => $ringkasan,
            'seksyen' => [
                $s('1. Ringkasan', ['Perkara', 'Nilai'], [
                    ['Tiket dibuka', $ringkasan['dibuka']], ['Tiket diselesaikan', $ringkasan['diselesaikan']], ['Tiket ditutup', $ringkasan['ditutup']],
                    ['Tiket terbuka pada akhir bulan', $openAtEnd], ['Pematuhan SLA respons (%)', $sla['peratus_respons']], ['Pematuhan SLA penyelesaian (%)', $sla['peratus_selesai']],
                    ['Masa purata penyelesaian (jam)', $ringkasan['mttr_jam']], ['Tiket dieskalasi', $ringkasan['eskalasi']],
                    ['Purata kepuasan (1–5)', $ringkasan['kepuasan']['purata']], ['Kadar penyelesaian kendiri (%)', $deflect['kadar_penyelesaian_kendiri']],
                ]),
                $s('2. Tiket mengikut kategori dan severity', ['Kategori', 'S1', 'S2', 'S3', 'S4', 'Jumlah'], $kategoriRows),
                $s('3. Tiket dibuka mengikut status semasa', ['Status', 'Bilangan'], $statusRows),
                $s('4. Pencapaian SLA mengikut aras', ['Severity', 'Diselesaikan', 'Respons dipenuhi', 'Respons (%)', 'Penyelesaian dipenuhi', 'Penyelesaian (%)', 'MTTR (jam)'], $slaRows),
                $s('5. Tiket melepasi SLA dan sebab', ['No. tiket', 'Severity', 'Modul', 'Respons dilepasi', 'Penyelesaian dilepasi', 'Sebab', 'Juruteknik', 'Status'], $breached),
                $s('6. Masa henti sistem dan punca akar', ['Tarikh', 'Perkara', 'Tempoh (minit)', 'Punca akar', 'Rujukan'], $downtime),
                $s('7. Tampalan keselamatan', ['Tarikh', 'Tampalan', 'Keterangan', 'Rujukan'], $simple('tampalan_keselamatan')),
                $s('8. Penyelenggaraan pencegahan', ['Tarikh', 'Aktiviti', 'Keterangan', 'Rujukan'], $simple('penyelenggaraan_pencegahan')),
                $s('9. Trend masalah berulang (3 bulan)', ['Modul', 'Kategori', 'Bilangan'], array_map(fn ($r) => [$r['modul_nama'], $r['kategori_nama'], $r['bilangan']], $recurring['gabungan'])),
                $s('10. Tindakan pembetulan dan pencegahan dicadangkan', ['No. tiket', 'Modul / perkara', 'Tindakan'], $corrective),
                $s('11. Status pemindahan teknologi kepada BTM MAIS', ['Tarikh', 'Aktiviti', 'Keterangan', 'Rujukan'], $simple('pemindahan_teknologi')),
                $s('12. Laporan eskalasi', ['Masa', 'No. tiket', 'Peringkat', 'Sebab', 'Sasaran', 'Penerima'], $escalations),
                $s('13. Penyelesaian kendiri (Pangkalan Pengetahuan)', ['Perkara', 'Nilai'], [
                    ['Sesi dengan cadangan', $deflect['sesi']], ['Tiket diteruskan', $deflect['diteruskan']], ['Diselesaikan sendiri', $deflect['diselesaikan_sendiri']], ['Kadar (%)', $deflect['kadar_penyelesaian_kendiri']],
                ]),
                $s('14. Penyegerakan Sistem Aduan berpusat', ['Status', 'Transaksi'], array_map(fn ($k, $v) => [$k, (int) $v], array_keys($sync), $sync)),
                $s('15. Draf artikel Pangkalan Pengetahuan daripada tiket', ['No. tiket', 'ID artikel', 'Tajuk tiket'], $articles),
            ],
        ];
    }

    /** Months with any ticket activity, newest first (for the report picker). */
    public function senaraiBulan(): array
    {
        $tz = KalendarSokongan::TZ;

        return DB::table('tkt_tiket')->selectRaw("distinct to_char(tarikh_buka at time zone 'UTC' at time zone '{$tz}', 'YYYY-MM') as b")
            ->orderByDesc('b')->limit(36)->pluck('b')->map(fn ($b) => (string) $b)->values()->all();
    }
}
