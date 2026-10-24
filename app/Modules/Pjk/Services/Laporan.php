<?php

namespace App\Modules\Pjk\Services;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Pjk\Models\Bayaran;
use App\Modules\Pjk\Models\DepositCagaran;
use App\Modules\Pjk\Models\JadualCaj;
use App\Modules\Pjk\Models\KiraanAgihanManfaat;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\PelarasanLejar;
use App\Modules\Pjk\Models\PindaanKontrak;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\Models\SisihanRekonsiliasi;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Wang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SRS-PJK-26/27 + "Laporan yang boleh dijana": every report returns the
 * same shape {tajuk, kepala, baris, ringkasan} so it renders as a table and
 * exports to PDF / Excel. All queries go through the scoped models, so a
 * district officer only ever reports on their district (SRS-RBC-08).
 */
class Laporan
{
    public const JENIS = [
        'kontrak' => 'Sewaan dan pajakan mengikut status, lokasi, kategori, penyewa, hasil, tunggakan dan tempoh',
        'tunggakan' => 'Tunggakan dengan analisis umur hutang',
        'kutipan' => 'Kutipan hasil mengikut kaedah bayaran',
        'hampir_tamat' => 'Kontrak hampir tamat tempoh',
        'penghunian' => 'Penghunian dan kekosongan mengikut daerah dan kategori',
        'deposit' => 'Deposit dipegang, ditolak dan dipulangkan',
        'agihan' => 'Kiraan Dasar Agihan Manfaat',
        'rekonsiliasi' => 'Rekonsiliasi payment gateway, GRP dan e-Sewa — senarai sisihan',
        'pindaan' => 'Pindaan kontrak dan jejak audit pelarasan lejar',
    ];

    public function __construct(private Parameters $parameters) {}

    /**
     * @param  array<string, mixed>  $f
     * @return array{tajuk: string, kepala: list<string>, baris: list<list<string|int|null>>, ringkasan: array<string, mixed>}
     */
    public function build(string $jenis, array $f): array
    {
        return match ($jenis) {
            'kontrak' => $this->kontrak($f),
            'tunggakan' => $this->tunggakan($f),
            'kutipan' => $this->kutipan($f),
            'hampir_tamat' => $this->hampirTamat($f),
            'penghunian' => $this->penghunian($f),
            'deposit' => $this->deposit($f),
            'agihan' => $this->agihan($f),
            'rekonsiliasi' => $this->rekonsiliasi($f),
            default => $this->pindaan($f),
        };
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Builder<Kontrak>
     */
    private function contracts(array $f): Builder
    {
        return Kontrak::query()
            ->when(! empty($f['status']), fn ($q) => $q->where('status', $f['status']))
            ->when(! empty($f['kod_daerah']), fn ($q) => $q->where('kod_daerah', $f['kod_daerah']))
            ->when(! empty($f['kod_mukim']), fn ($q) => $q->where('kod_mukim', $f['kod_mukim']))
            ->when(! empty($f['kategori_hartanah']), fn ($q) => $q->where('kategori_hartanah', $f['kategori_hartanah']))
            ->when(! empty($f['bahagian']), fn ($q) => $q->where('bahagian', $f['bahagian']))
            ->when(! empty($f['penyewa']), fn ($q) => $q->where('penyewa', 'ilike', '%'.$f['penyewa'].'%'))
            ->when(! empty($f['dari']), fn ($q) => $q->whereDate('tarikh_tamat', '>=', $f['dari']))
            ->when(! empty($f['hingga']), fn ($q) => $q->whereDate('tarikh_mula', '<=', $f['hingga']));
    }

    /** @param array<string, mixed> $f */
    private function kontrak(array $f): array
    {
        $rows = $this->contracts($f)->with('premis:id,nama_premis')
            ->addSelect(['hasil' => DB::table('lejar_transaksi')->selectRaw("coalesce(sum(kredit) filter (where jenis in ('bayaran','tolakan_deposit')), 0) - coalesce(sum(debit) filter (where jenis = 'pembatalan'), 0)")->whereColumn('kontrak_id', 'kontrak_pajakan.id')])
            ->addSelect(['tunggakan' => DB::table('jadual_caj')->selectRaw('coalesce(sum(amaun - amaun_dibayar - amaun_dikecualikan), 0)')->whereColumn('kontrak_id', 'kontrak_pajakan.id')->where('dikeluarkan', true)->whereIn('status_caj', ['belum_bayar', 'separa'])->whereDate('tarikh_akhir', '<', Carbon::today())])
            ->orderBy('no_kontrak')->limit(5000)->get();

        $hasil = 0;
        $tunggakan = 0;
        $byStatus = [];
        $baris = [];
        foreach ($rows as $k) {
            $h = Wang::sen((string) $k->getAttribute('hasil'));
            $t = Wang::sen((string) $k->getAttribute('tunggakan'));
            $hasil += $h;
            $tunggakan += $t;
            $byStatus[$k->status] = ($byStatus[$k->status] ?? 0) + 1;
            $baris[] = [
                $k->no_kontrak, $k->penyewa, $k->premis->nama_premis ?? '—', $k->kod_daerah, Kod::KATEGORI_HARTANAH[$k->kategori_hartanah ?? ''] ?? '—',
                Kod::STATUS_KONTRAK[$k->status] ?? $k->status, $k->tarikh_mula?->format('Y-m-d'), $k->tarikh_tamat?->format('Y-m-d'),
                $k->tarikh_mula && $k->tarikh_tamat ? (int) round($k->tarikh_mula->diffInMonths($k->tarikh_tamat->copy()->addDay())) : null,
                Wang::rm($k->kadar), Wang::rm($h), Wang::rm($t),
            ];
        }

        return [
            'tajuk' => self::JENIS['kontrak'],
            'kepala' => ['No. kontrak', 'Penyewa', 'Premis', 'Daerah', 'Kategori', 'Status', 'Tarikh mula', 'Tarikh tamat', 'Tempoh (bulan)', 'Kadar sebulan', 'Jumlah hasil', 'Tunggakan'],
            'baris' => $baris,
            'ringkasan' => ['bil_kontrak' => count($baris), 'jumlah_hasil' => Wang::dec($hasil), 'jumlah_tunggakan' => Wang::dec($tunggakan), 'mengikut_status' => $byStatus],
        ];
    }

    /** @param array<string, mixed> $f */
    private function tunggakan(array $f): array
    {
        $today = Carbon::today();
        $ids = $this->contracts($f)->select('kontrak_pajakan.id');
        $charges = JadualCaj::query()->whereIn('kontrak_id', $ids)->where('dikeluarkan', true)->whereIn('status_caj', ['belum_bayar', 'separa'])->whereDate('tarikh_akhir', '<', $today)->get();
        $contracts = Kontrak::query()->whereIn('id', $charges->pluck('kontrak_id')->unique())->get()->keyBy('id');

        $per = [];
        $byDaerah = [];
        $total = array_fill_keys(array_keys(Kod::KUMPULAN_UMUR), 0);
        foreach ($charges as $c) {
            $baki = $c->bakiSen();
            if ($baki <= 0 || ! isset($contracts[$c->kontrak_id])) {
                continue;
            }
            $bucket = Kod::umur($c->umurTunggakan($today));
            $per[$c->kontrak_id] ??= array_fill_keys(array_keys(Kod::KUMPULAN_UMUR), 0);
            $per[$c->kontrak_id][$bucket] += $baki;
            $d = (string) $contracts[$c->kontrak_id]->kod_daerah;
            $byDaerah[$d] = ($byDaerah[$d] ?? 0) + $baki;
            $total[$bucket] += $baki;
        }
        $baris = [];
        foreach ($per as $id => $b) {
            $k = $contracts[$id];
            $baris[] = [$k->no_kontrak, $k->penyewa, $k->kod_daerah, ...array_map(fn ($s) => Wang::rm($s), array_values($b)), Wang::rm(array_sum($b))];
        }
        usort($baris, fn ($a, $b) => strcmp((string) $a[0], (string) $b[0]));

        return [
            'tajuk' => self::JENIS['tunggakan'],
            'kepala' => ['No. kontrak', 'Penyewa', 'Daerah', ...array_values(Kod::KUMPULAN_UMUR), 'Jumlah'],
            'baris' => $baris,
            'ringkasan' => [
                'jumlah' => Wang::dec(array_sum($total)),
                'kumpulan' => array_map(fn ($s) => Wang::dec($s), $total),
                'mengikut_daerah' => array_map(fn ($s) => Wang::dec($s), $byDaerah),
            ],
        ];
    }

    /** @param array<string, mixed> $f */
    private function kutipan(array $f): array
    {
        $from = $f['dari'] ?? Carbon::today()->startOfMonth()->format('Y-m-d');
        $to = $f['hingga'] ?? Carbon::today()->format('Y-m-d');
        $ids = $this->contracts(array_diff_key($f, ['dari' => 1, 'hingga' => 1]))->select('kontrak_pajakan.id');
        $rows = Bayaran::query()->whereIn('kontrak_id', $ids)->where('status', 'disahkan')
            ->whereBetween('tarikh_bayaran', [$from, $to])
            ->where(fn ($q) => $q->whereNull('rujukan_luar')->orWhere('rujukan_luar', 'not like', 'DEPOSIT-%')) // deposit deductions are not collections
            ->get();
        $by = [];
        foreach ($rows as $b) {
            $by[$b->saluran] ??= ['bil' => 0, 'sen' => 0];
            $by[$b->saluran]['bil']++;
            $by[$b->saluran]['sen'] += Wang::sen($b->amaun);
        }
        $baris = [];
        foreach (Kod::SALURAN_BAYARAN as $kod => $nama) {
            $baris[] = [$nama, $by[$kod]['bil'] ?? 0, Wang::rm($by[$kod]['sen'] ?? 0)];
        }

        return [
            'tajuk' => self::JENIS['kutipan']." ({$from} – {$to})",
            'kepala' => ['Kaedah bayaran', 'Bilangan', 'Jumlah'],
            'baris' => $baris,
            'ringkasan' => ['dari' => $from, 'hingga' => $to, 'jumlah' => Wang::dec(array_sum(array_column($by, 'sen'))), 'bil' => array_sum(array_column($by, 'bil'))],
        ];
    }

    /** @param array<string, mixed> $f */
    private function hampirTamat(array $f): array
    {
        $days = (int) ($f['hari'] ?? 180);
        $rows = $this->contracts(array_diff_key($f, ['status' => 1]))->whereIn('status', Kod::BERKUAT_KUASA)
            ->whereDate('tarikh_tamat', '>=', Carbon::today())->whereDate('tarikh_tamat', '<=', Carbon::today()->addDays($days))
            ->with('premis:id,nama_premis')->orderBy('tarikh_tamat')->get();

        return [
            'tajuk' => self::JENIS['hampir_tamat']." ({$days} hari)",
            'kepala' => ['No. kontrak', 'Penyewa', 'Premis', 'Daerah', 'Tarikh tamat', 'Baki hari', 'Kadar sebulan'],
            'baris' => $rows->map(fn (Kontrak $k) => [$k->no_kontrak, $k->penyewa, $k->premis->nama_premis ?? '—', $k->kod_daerah, $k->tarikh_tamat?->format('Y-m-d'), (int) Carbon::today()->diffInDays($k->tarikh_tamat), Wang::rm($k->kadar)])->values()->all(),
            'ringkasan' => ['bil' => $rows->count(), 'hari' => $days],
        ];
    }

    /**
     * SRS-PJK-26: occupancy and vacancy.
     *
     * @param  array<string, mixed>  $f
     */
    public function penghunian(array $f): array
    {
        $threshold = (int) $this->parameters->get('pjk.ambang_hari_kosong', 90);
        $today = Carbon::today();
        $premises = PremisAset::query()
            ->when(! empty($f['kod_daerah']), fn ($q) => $q->where('kod_daerah', $f['kod_daerah']))
            ->when(! empty($f['kategori_hartanah']), fn ($q) => $q->where('kategori_hartanah', $f['kategori_hartanah']))
            ->when(! empty($f['bahagian']), fn ($q) => $q->where('bahagian', $f['bahagian']))
            ->get();

        $groups = [];
        $vacantDays = [];
        $longVacant = [];
        $potentialMonthly = 0;
        $potentialLost = 0;
        foreach ($premises as $p) {
            $key = $p->kod_daerah.'|'.$p->kategori_hartanah;
            $groups[$key] ??= ['kod_daerah' => $p->kod_daerah, 'kategori_hartanah' => $p->kategori_hartanah, 'jumlah' => 0, 'disewa' => 0, 'kosong' => 0, 'lain' => 0];
            $groups[$key]['jumlah']++;
            $status = $p->status_premis === 'disewa' ? 'disewa' : ($p->status_premis === 'kosong' ? 'kosong' : 'lain');
            $groups[$key][$status]++;
            if ($p->status_premis === 'kosong') {
                $days = $p->tarikh_status ? (int) $p->tarikh_status->diffInDays($today) : 0;
                $vacantDays[] = $days;
                $rate = Wang::sen($p->kadar_tawaran);
                $potentialMonthly += $rate;
                $potentialLost += intdiv($rate * $days, 30);
                if ($days > $threshold) {
                    $longVacant[] = ['id' => $p->id, 'kod_premis' => $p->kod_premis, 'nama_premis' => $p->nama_premis, 'kod_daerah' => $p->kod_daerah, 'kategori_hartanah' => $p->kategori_hartanah, 'hari_kosong' => $days, 'kadar_tawaran' => $p->kadar_tawaran];
                }
            }
        }
        ksort($groups);
        $baris = array_values(array_map(fn ($g) => [
            $g['kod_daerah'], Kod::KATEGORI_HARTANAH[$g['kategori_hartanah']] ?? $g['kategori_hartanah'], $g['jumlah'], $g['disewa'], $g['kosong'], $g['lain'],
            $this->pct($g['disewa'], $g['jumlah'] - $g['lain']),
        ], $groups));
        usort($longVacant, fn ($a, $b) => $b['hari_kosong'] <=> $a['hari_kosong']);
        $rentable = $premises->whereIn('status_premis', ['disewa', 'kosong'])->count();

        return [
            'tajuk' => self::JENIS['penghunian'],
            'kepala' => ['Daerah', 'Kategori', 'Jumlah premis', 'Disewa', 'Kosong', 'Lain-lain', 'Kadar penghunian (%)'],
            'baris' => $baris,
            'ringkasan' => [
                'jumlah_premis' => $premises->count(),
                'disewa' => $premises->where('status_premis', 'disewa')->count(),
                'kosong' => $premises->where('status_premis', 'kosong')->count(),
                'kadar_penghunian' => $this->pct($premises->where('status_premis', 'disewa')->count(), $rentable),
                'purata_hari_kosong' => $vacantDays === [] ? 0 : intdiv(array_sum($vacantDays), count($vacantDays)),
                'ambang_hari_kosong' => $threshold,
                'kosong_melebihi_ambang' => array_slice($longVacant, 0, 200),
                'hasil_berpotensi_sebulan' => Wang::dec($potentialMonthly),
                'hasil_tidak_dijana_terkumpul' => Wang::dec($potentialLost),
            ],
        ];
    }

    /** @param array<string, mixed> $f */
    private function deposit(array $f): array
    {
        $ids = $this->contracts($f)->select('kontrak_pajakan.id');
        $rows = DepositCagaran::query()->whereIn('kontrak_id', $ids)->with('kontrak:id,no_kontrak,penyewa')->orderBy('id')->get();
        $by = [];
        foreach ($rows as $d) {
            $by[$d->status] = ($by[$d->status] ?? 0) + Wang::sen($d->amaun);
        }

        return [
            'tajuk' => self::JENIS['deposit'],
            'kepala' => ['No. kontrak', 'Penyewa', 'Jenis', 'Amaun', 'Status', 'Tarikh terima', 'Tolakan', 'Dipulangkan', 'Tarikh pulangan'],
            'baris' => $rows->map(fn (DepositCagaran $d) => [
                $d->kontrak->no_kontrak ?? '—', $d->kontrak->penyewa ?? '—', $d->jenis, Wang::rm($d->amaun), Kod::STATUS_DEPOSIT[$d->status] ?? $d->status,
                $d->tarikh_terima?->format('Y-m-d'), Wang::rm($d->amaun_tolakan), $d->amaun_dipulangkan !== null ? Wang::rm($d->amaun_dipulangkan) : '—', $d->tarikh_pulangan?->format('Y-m-d'),
            ])->values()->all(),
            'ringkasan' => ['mengikut_status' => array_map(fn ($s) => Wang::dec($s), $by)],
        ];
    }

    /** @param array<string, mixed> $f */
    private function agihan(array $f): array
    {
        $rows = KiraanAgihanManfaat::query()->whereHas('premis')->with('premis:id,kod_premis,nama_premis')
            ->when(! empty($f['tempoh']), fn ($q) => $q->where('tempoh_perakaunan', $f['tempoh']))
            ->when(! empty($f['kod_daerah']), fn ($q) => $q->where('kod_daerah', $f['kod_daerah']))
            ->when(! empty($f['kategori_hartanah']), fn ($q) => $q->where('kategori_hartanah', $f['kategori_hartanah']))
            ->when(! empty($f['status_perolehan']), fn ($q) => $q->where('status_perolehan', $f['status_perolehan']))
            ->orderBy('tempoh_perakaunan')->orderBy('premis_id')->get();
        $total = 0;
        $hasil = 0;
        foreach ($rows as $r) {
            $total += Wang::sen($r->amaun_agihan);
            $hasil += Wang::sen($r->hasil_kutipan);
        }

        return [
            'tajuk' => self::JENIS['agihan'],
            'kepala' => ['Tempoh', 'Premis', 'Daerah', 'Kategori', 'Status perolehan', 'Versi dasar', 'Hasil kutipan', 'Kadar (%)', 'Amaun agihan', 'Status'],
            'baris' => $rows->map(fn (KiraanAgihanManfaat $r) => [
                $r->tempoh_perakaunan, $r->premis->kod_premis ?? '—', $r->kod_daerah, Kod::KATEGORI_HARTANAH[$r->kategori_hartanah] ?? $r->kategori_hartanah,
                Kod::STATUS_PEROLEHAN[$r->status_perolehan] ?? $r->status_perolehan, $r->versi_dasar, Wang::rm($r->hasil_kutipan), $r->kadar_agihan, Wang::rm($r->amaun_agihan), $r->status_pengesahan,
            ])->values()->all(),
            'ringkasan' => ['jumlah_hasil' => Wang::dec($hasil), 'jumlah_agihan' => Wang::dec($total), 'bil' => $rows->count()],
        ];
    }

    /** @param array<string, mixed> $f */
    private function rekonsiliasi(array $f): array
    {
        $ids = $this->contracts([])->select('kontrak_pajakan.id');
        $rows = SisihanRekonsiliasi::query()->with('larian')
            ->where(fn ($q) => $q->whereIn('kontrak_id', $ids)->orWhereNull('kontrak_id'))
            ->when(! empty($f['status']), fn ($q) => $q->where('status_tindakan', $f['status']))
            ->when(! empty($f['larian']), fn ($q) => $q->where('larian_id', $f['larian']))
            ->orderByDesc('id')->limit(5000)->get();

        return [
            'tajuk' => self::JENIS['rekonsiliasi'],
            'kepala' => ['Tarikh', 'Sumber', 'Jenis sisihan', 'Rujukan', 'Amaun SISDATA', 'Amaun luar', 'Status tindakan'],
            'baris' => $rows->map(fn (SisihanRekonsiliasi $s) => [
                (string) $s->larian->getAttribute('tarikh')?->format('Y-m-d'), strtoupper($s->larian->sumber), Kod::JENIS_SISIHAN[$s->jenis] ?? $s->jenis, $s->rujukan,
                $s->amaun_sistem !== null ? Wang::rm($s->amaun_sistem) : '—', $s->amaun_luar !== null ? Wang::rm($s->amaun_luar) : '—', $s->status_tindakan,
            ])->values()->all(),
            'ringkasan' => ['bil' => $rows->count()],
        ];
    }

    /** @param array<string, mixed> $f */
    private function pindaan(array $f): array
    {
        $contracts = $this->contracts($f)->get(['id', 'no_kontrak'])->keyBy('id');
        $rows = [];
        foreach (PindaanKontrak::query()->whereIn('kontrak_id', $contracts->keys())->orderBy('created_at')->get() as $p) {
            $rows[] = [$p->created_at->format('Y-m-d'), $contracts[$p->kontrak_id]->no_kontrak, Kod::JENIS_PINDAAN[$p->jenis_pindaan] ?? $p->jenis_pindaan, '—', $p->justifikasi, $p->status, (string) $p->id_pemohon, (string) ($p->id_pelulus ?? '—'), $p->tarikh_keputusan?->format('Y-m-d')];
        }
        foreach (PelarasanLejar::query()->whereIn('kontrak_id', $contracts->keys())->orderBy('created_at')->get() as $p) {
            $rows[] = [$p->created_at?->format('Y-m-d'), $contracts[$p->kontrak_id]->no_kontrak, Kod::JENIS_PELARASAN[$p->jenis] ?? $p->jenis, Wang::rm($p->amaun), $p->justifikasi, $p->status, (string) $p->id_pemohon, (string) ($p->id_pelulus ?? '—'), $p->tarikh_keputusan?->format('Y-m-d')];
        }
        usort($rows, fn ($a, $b) => strcmp((string) $a[0], (string) $b[0]));

        return [
            'tajuk' => self::JENIS['pindaan'],
            'kepala' => ['Tarikh', 'No. kontrak', 'Jenis', 'Amaun', 'Justifikasi', 'Status', 'Pemohon (ID)', 'Pelulus (ID)', 'Tarikh keputusan'],
            'baris' => $rows,
            'ringkasan' => ['bil' => count($rows)],
        ];
    }

    private function pct(int $part, int $whole): string
    {
        return $whole <= 0 ? '0.0' : number_format($part * 100 / $whole, 1, '.', '');
    }
}
