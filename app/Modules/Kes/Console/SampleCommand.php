<?php

namespace App\Modules\Kes\Console;

use App\Models\User;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Models\PanelPeguam;
use App\Modules\Kes\Models\Prosiding;
use App\Modules\Kes\Services\KeputusanService;
use App\Modules\Kes\Services\KesService;
use App\Modules\Kes\Services\KosService;
use App\Modules\Kes\Services\PanelService;
use App\Modules\Kes\Services\ProsidingService;
use App\Modules\Kes\Services\StatusLot;
use App\Modules\Ntf\Contracts\Notifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * `php artisan kes:sample --count=40` — SYNTHETIC court cases for the dev DB,
 * linked to existing (synthetic) lots: every case type and stage, parties
 * (generated names — never real people), counsel incl. synthetic panel
 * firms, proceeding history with upcoming dates (some inside 14/7/1 days),
 * legal costs, decisions with follow-ups, a few appeals / settlements and
 * archived closed cases. Every row is flagged `sintetik`; no workflow or
 * notification is started.
 */
class SampleCommand extends Command
{
    protected $signature = 'kes:sample {--count=40 : Bilangan kes sintetik} {--seed= : Benih rawak (boleh diulang)}';

    protected $description = 'Jana kes mahkamah SINTETIK dipaut kepada lot sedia ada untuk DB pembangunan (MOD-KES)';

    private const MAHKAMAH = [
        ['Mahkamah Tinggi Shah Alam', 'tinggi', 'BA-22NCvC'], ['Mahkamah Sesyen Petaling Jaya', 'sesyen', 'BA-B52NCvC'],
        ['Mahkamah Majistret Klang', 'majistret', 'BA-A72NCvC'], ['Mahkamah Tinggi Syariah Shah Alam', 'syariah_tinggi', '10100'],
        ['Mahkamah Rendah Syariah Petaling', 'syariah_rendah', '10006'], ['Mahkamah Rayuan Putrajaya', 'rayuan', 'B-01(NCvC)(W)'],
        ['Pentadbir Tanah Daerah Hulu Langat', 'tribunal', 'PTD-HL'],
    ];

    private const TAJUK = [
        'sivil' => 'Tuntutan ganti rugi kerosakan bangunan wakaf', 'jenayah' => 'Pendakwaan pencerobohan dan khianat harta MAIS',
        'faraid' => 'Pertikaian pembahagian harta pusaka melibatkan bahagian Baitulmal', 'wakaf' => 'Pengesahan status tanah wakaf am',
        'sengketa_pemilikan' => 'Sengketa pemilikan tanah lot bersempadan', 'tuntutan_milikan' => 'Tuntutan milikan kosong (vacant possession) terhadap penghuni',
        'pencerobohan' => 'Pencerobohan dan pembinaan tanpa izin di atas tanah MAIS', 'kaveat' => 'Permohonan memotong kaveat persendirian',
        'rayuan' => 'Rayuan terhadap keputusan Mahkamah Tinggi',
    ];

    private const NAMA = ['Ahmad', 'Siti', 'Muhammad', 'Nur', 'Aisyah', 'Hafiz', 'Farah', 'Azman', 'Zulkifli', 'Rosnah', 'Kamal', 'Hidayah', 'Faizal', 'Salmah', 'Iskandar', 'Liyana'];

    private const BAPA = ['Abdullah', 'Ismail', 'Hassan', 'Osman', 'Yusof', 'Ibrahim', 'Rahman', 'Salleh', 'Hamid', 'Ali'];

    private const SYARIKAT = ['Contoh Hartanah Sdn. Bhd.', 'Sampel Pembinaan Enterprise', 'Ujian Maju Holdings Sdn. Bhd.', 'Rekaan Niaga Resources'];

    private const FIRMA = ['Tetuan Sampel & Rakan (Sintetik)', 'Tetuan Contoh Aziz & Co (Sintetik)', 'Tetuan Rekaan Hamid Partnership (Sintetik)', 'Tetuan Ujian Legal Chambers (Sintetik)'];

    /** Stage distribution: [status, share]. */
    private const AGIHAN = [['didaftar', 8], ['aktif', 20], ['sebutan', 15], ['dalam_bicara', 15], ['tangguh', 7], ['keputusan', 10], ['rayuan', 7], ['selesai', 18]];

    public function handle(KesService $kes, ProsidingService $prosiding, KosService $kos, KeputusanService $keputusan, PanelService $panel, StatusLot $statusLot): int
    {
        $count = max(1, (int) $this->option('count'));
        if ($this->option('seed') !== null) {
            mt_srand((int) $this->option('seed'));
        }
        app()->instance(Notifier::class, new class implements Notifier
        {
            public function send(string $type, User|iterable $recipients, array $data = [], ?string $link = null, ?string $dedupeKey = null): void {}
        });

        $lots = DB::table('lot_tanah')->whereNull('deleted_at')->inRandomOrder()->limit(300)->get(['id', 'kod_daerah']);
        if ($lots->isEmpty()) {
            $this->error('Tiada lot dalam lot_tanah — jalankan `php artisan ref:sample-lots` / import MOD-PRO dahulu.');

            return self::FAILURE;
        }
        $byDistrict = $lots->groupBy('kod_daerah');
        $officers = User::query()->where('is_active', true)->where('email', 'not like', '%@sistem.sisdata.local')->orderBy('id')->limit(6)->get();
        $actor = $officers->first() ?? User::query()->orderBy('id')->first();
        if ($actor === null) {
            $this->error('Tiada pengguna — jalankan `php artisan db:seed` dahulu.');

            return self::FAILURE;
        }
        $firms = $this->firma();
        $jenis = array_keys(self::TAJUK);
        $statuses = [];
        foreach (self::AGIHAN as [$s, $w]) {
            $statuses = [...$statuses, ...array_fill(0, $w, $s)];
        }

        $bar = $this->output->createProgressBar($count);
        for ($i = 0; $i < $count; $i++) {
            $j = $jenis[$i % count($jenis)];
            [$mahkamah, $peringkat, $prefix] = self::MAHKAMAH[mt_rand(0, count(self::MAHKAMAH) - 1)];
            if (in_array($j, ['faraid', 'wakaf'], true)) {
                [$mahkamah, $peringkat, $prefix] = self::MAHKAMAH[mt_rand(3, 4)];
            }
            $status = $statuses[mt_rand(0, count($statuses) - 1)];
            $filed = Carbon::now()->subDays(mt_rand(60, 1100));
            $district = $byDistrict->keys()->random();
            $pool = $byDistrict[$district];
            $lotIds = $pool->random(min($pool->count(), mt_rand(1, 3)))->pluck('id')->map(fn ($v) => (string) $v)->values()->all();
            $maisPlaintif = in_array($j, ['tuntutan_milikan', 'pencerobohan', 'kaveat', 'wakaf'], true) ? mt_rand(0, 4) > 0 : mt_rand(0, 2) === 0;
            $pegawai = $officers->isNotEmpty() ? $officers->random() : null;
            $individu = $this->nama();

            $k = $kes->daftar([
                'no_kes' => sprintf('%s-%d-%02d/%d', $prefix, mt_rand(100, 999), mt_rand(1, 12), (int) $filed->format('Y')),
                'tajuk' => ($maisPlaintif ? 'MAIS lwn ' : '').self::TAJUK[$j].($maisPlaintif ? '' : ' (MAIS sebagai defendan)'),
                'jenis' => $j, 'mahkamah' => $mahkamah, 'peringkat_mahkamah' => $peringkat, 'tarikh_pemfailan' => $filed->toDateString(),
                'ringkasan_tuntutan' => 'SINTETIK — '.self::TAJUK[$j].'. Data rekaan untuk pembangunan; tiada kaitan dengan mana-mana kes sebenar.',
                'nilai_tuntutan' => mt_rand(0, 5) === 0 ? null : number_format(mt_rand(20, 4000) * 1000, 2, '.', ''),
                'peranan_mais' => $maisPlaintif ? 'plaintif' : 'defendan',
                'lot_ids' => $lotIds,
                'id_pegawai_bertanggungjawab' => $pegawai?->id,
                'pihak' => [
                    ['nama_pihak' => 'Majlis Agama Islam Selangor', 'jenis_pihak' => 'mais', 'peranan' => $maisPlaintif ? 'plaintif' : 'defendan', 'status_wakilan' => 'diwakili', 'adalah_mais' => true],
                    ['nama_pihak' => $individu, 'jenis_pihak' => 'individu', 'peranan' => $maisPlaintif ? 'defendan' : 'plaintif', 'no_pengenalan' => sprintf('%02d%02d%02d-10-%04d', mt_rand(50, 99), mt_rand(1, 12), mt_rand(1, 28), mt_rand(1000, 9999)), 'maklumat_perhubungan' => '01'.mt_rand(2, 9).'-'.mt_rand(1000000, 9999999).' (sintetik)', 'status_wakilan' => mt_rand(0, 3) ? 'diwakili' : 'sendiri'],
                    ...(mt_rand(0, 2) === 0 ? [['nama_pihak' => self::SYARIKAT[mt_rand(0, count(self::SYARIKAT) - 1)], 'jenis_pihak' => 'syarikat', 'peranan' => 'pihak_ketiga', 'status_wakilan' => 'diwakili']] : []),
                ],
                'peguam' => [['nama_peguam' => 'Encik '.$this->nama(), 'nama_firma' => 'Tetuan Pihak Lawan & Co (Sintetik)', 'pihak_indeks' => 1]],
            ], $actor, 'manual', null, true);

            if ($firms !== []) {
                $panel->lantik($k, $firms[array_rand($firms)], ['kes_pihak_id' => $k->pihak()->where('adalah_mais', true)->value('id')], $actor, true);
            }
            $this->sejarah($k, $status, $filed, $actor, $prosiding, $kos, $keputusan, $kes, $firms);
            $k->forceFill(['created_at' => $filed->copy()->subDays(mt_rand(1, 20))])->saveQuietly();
            $kes->segarTarikhBerikutnya($k);
            $statusLot->segar($kes->lotIds($k));
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info("{$count} kes sintetik dijana (".KesMahkamah::query()->withoutGlobalScope('access')->where('sintetik', true)->count().' jumlah sintetik).');

        return self::SUCCESS;
    }

    /** @param list<PanelPeguam> $firms */
    private function sejarah(KesMahkamah $k, string $status, Carbon $filed, User $actor, ProsidingService $prosiding, KosService $kos, KeputusanService $keputusan, KesService $kes, array $firms): void
    {
        $order = array_column(self::AGIHAN, 0);
        $stage = array_search($status, $order, true) ?: 0;
        $t = $filed->copy()->addDays(mt_rand(20, 60))->setTime(9, 0);
        $n = $stage === 0 ? 0 : mt_rand(1, 3 + $stage);
        $jenis = ['sebutan', 'pengurusan_kes', 'sebutan', 'bicara', 'bicara', 'pendengaran'];
        for ($p = 0; $p < $n && $t->isPast(); $p++) {
            $prosiding->rekod($k, [
                'tarikh_prosiding' => $t->toDateTimeString(), 'jenis_prosiding' => $jenis[min($p, count($jenis) - 1)],
                'status' => mt_rand(0, 5) === 0 ? 'ditangguh' : 'selesai', 'hasil' => 'Prosiding berjalan (sintetik).',
                'arahan_mahkamah' => mt_rand(0, 1) ? 'Pihak-pihak diarahkan memfailkan dokumen dalam tempoh 14 hari.' : null,
            ], $actor, true);
            $t = $t->copy()->addDays(mt_rand(30, 90));
        }
        // Costs.
        for ($c = 0, $m = mt_rand(1, 5); $c < $m; $c++) {
            $d = $filed->copy()->addDays(mt_rand(0, max(1, (int) $filed->diffInDays(now()))));
            $kat = ['fi_peguam', 'fi_peguam', 'kos_mahkamah', 'kos_berkaitan'][mt_rand(0, 3)];
            $kos->tambah($k, [
                'kategori_kos' => $kat, 'amaun' => number_format(mt_rand(3, 450) * 100 + mt_rand(0, 99) / 100, 2, '.', ''), 'tarikh' => $d->toDateString(),
                'keterangan' => ['fi_peguam' => 'Fi guaman peringkat '.($c + 1), 'kos_mahkamah' => 'Fi pemfailan / setem', 'kos_berkaitan' => 'Laporan penilai / jurukur'][$kat].' (sintetik)',
                'no_resit' => 'INV-S'.mt_rand(10000, 99999), 'status_bayaran' => mt_rand(0, 2) ? 'dibayar' : 'belum_dibayar',
                'kes_panel_peguam_id' => $kat === 'fi_peguam' && $firms !== [] ? $firms[array_rand($firms)]->id : null,
            ], $actor, true);
        }
        $terminal = in_array($status, ['keputusan', 'rayuan', 'selesai'], true);
        if ($terminal) {
            $hasil = $status === 'selesai' ? ['menang', 'kalah', 'penyelesaian_luar_mahkamah', 'ditolak', 'selesai'][mt_rand(0, 4)] : ['menang', 'kalah'][mt_rand(0, 1)];
            $tarikh = min(now()->subDays(5)->getTimestamp(), $t->getTimestamp());
            $keputusan->rekod($k, ['jenis_keputusan' => $hasil, 'tarikh_keputusan' => Carbon::createFromTimestamp($tarikh)->toDateString(), 'ringkasan_perintah' => 'Perintah mahkamah (sintetik).', 'implikasi' => 'Implikasi kepada lot berkaitan (sintetik).'], $actor, true);
            if ($status === 'rayuan') {
                $keputusan->rayuan($k, ['pihak_merayu' => 'pihak_lawan', 'mahkamah_rayuan' => 'Mahkamah Rayuan Putrajaya', 'peringkat_mahkamah' => 'rayuan', 'alasan' => 'Rayuan pihak lawan (sintetik).', 'tarikh_failan' => now()->subDays(mt_rand(5, 60))->toDateString()], $actor, true);
            }
        }
        if (! $terminal || $status === 'rayuan') {
            // Upcoming proceeding — roughly a third inside the 14/7/1-day reminder windows.
            $ahead = [1, 7, 14, 3, 10, 21, 30, 45, 60, 90][mt_rand(0, 9)];
            if ($status !== 'didaftar' || mt_rand(0, 1)) {
                Prosiding::query()->create([
                    'kes_mahkamah_id' => $k->id, 'tarikh_prosiding' => now()->startOfDay()->addDays($ahead)->setTime(mt_rand(0, 1) ? 9 : 14, 30),
                    'jenis_prosiding' => ['sebutan', 'bicara', 'pengurusan_kes', 'pengemukaan_dokumen'][mt_rand(0, 3)], 'status' => 'dijadualkan',
                    'catatan' => 'Dijadualkan (sintetik)', 'sumber' => 'manual', 'id_pegawai_rekod' => $actor->id, 'sintetik' => true,
                ]);
            }
            if (mt_rand(0, 3) === 0) {
                $prosiding->tarikhPenting($k, ['jenis' => 'pengemukaan_dokumen', 'tarikh' => now()->addDays(mt_rand(1, 40))->toDateString(), 'keterangan' => 'Pemfailan afidavit balasan (sintetik)'], $actor, true);
            }
        }
        if ($status !== 'didaftar') {
            $kes->tukarStatus($k, $status, $actor, 'Data sintetik');
        }
        if ($status === 'selesai' && mt_rand(0, 2) === 0) {
            $kes->arkib($k, 'Kes selesai dan ditutup (sintetik)', $actor);
        }
    }

    /** @return list<PanelPeguam> */
    private function firma(): array
    {
        $existing = PanelPeguam::query()->where('sintetik', true)->get()->all();
        if ($existing !== []) {
            return $existing;
        }
        $out = [];
        foreach (self::FIRMA as $i => $nama) {
            $p = new PanelPeguam;
            $p->forceFill([
                'nama_firma' => $nama, 'no_pendaftaran' => 'LF-S'.(1000 + $i), 'pegawai_penghubung' => 'Puan '.$this->nama(),
                'no_telefon' => '03-'.mt_rand(1000, 9999).' '.mt_rand(1000, 9999), 'emel' => 'firma'.($i + 1).'@contoh.test',
                'status' => 'aktif', 'tarikh_lantikan' => now()->subYears(2)->toDateString(), 'tarikh_tamat' => now()->addYears(1)->toDateString(), 'sintetik' => true,
            ])->save();
            $out[] = $p;
        }

        return $out;
    }

    private function nama(): string
    {
        $n = self::NAMA[mt_rand(0, count(self::NAMA) - 1)];
        $female = in_array($n, ['Siti', 'Nur', 'Aisyah', 'Farah', 'Rosnah', 'Hidayah', 'Salmah', 'Liyana'], true);

        return $n.' '.($female ? 'binti' : 'bin').' '.self::BAPA[mt_rand(0, count(self::BAPA) - 1)];
    }
}
