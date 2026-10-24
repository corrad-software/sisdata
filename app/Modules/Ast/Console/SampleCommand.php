<?php

namespace App\Modules\Ast\Console;

use App\Models\User;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\AsetInsurans;
use App\Modules\Ast\Models\AsetJaminan;
use App\Modules\Ast\Models\AsetKategori;
use App\Modules\Ast\Models\AsetKontraktor;
use App\Modules\Ast\Models\AsetPelupusan;
use App\Modules\Ast\Models\AsetQrLogImbasan;
use App\Modules\Ast\Models\JadualPenyelenggaraan;
use App\Modules\Ast\Services\AsetService;
use App\Modules\Ast\Services\PemeriksaanService;
use App\Modules\Ast\Services\PenyelenggaraanService;
use App\Modules\Ast\Support\Lalai;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `php artisan ast:sample --count=300` — SYNTHETIC physical assets for the dev
 * DB on the existing (synthetic) lots: buildings, mosques, shops, complexes
 * with sub-asset units, fences, signage, equipment…; points inside the lot
 * polygon; some linked to MOD-PJK premises on the same lot; valuation
 * history; inspections (condition history); preventive schedules (some
 * overdue) and 3 years of maintenance work with contractors and costs;
 * insurance / warranties; QR scans; a few archived (disposed) assets; MOD-PTN
 * maintenance needs pulled in as work orders. Everything is flagged
 * `sintetik`; notifications are suppressed and no file goes to DMS.
 */
class SampleCommand extends Command
{
    protected $signature = 'ast:sample {--count=300 : Bilangan aset sintetik} {--seed= : Benih rawak (boleh diulang)}';

    protected $description = 'Jana inventori aset fizikal SINTETIK (aset, nilai, pemeriksaan, penyelenggaraan) untuk DB pembangunan (MOD-AST)';

    private const NAMA = [
        'BANGUNAN_PEJABAT' => ['Pejabat Agama Islam Daerah', 'Bangunan Pentadbiran MAIS', 'Pejabat Zakat Cawangan', 'Wisma Wakaf'],
        'MASJID_SURAU' => ['Masjid Jamek', 'Surau Al-Hidayah', 'Surau An-Nur', 'Masjid Al-Ikhlas', 'Surau Ar-Rahman', 'Masjid Kariah'],
        'SEKOLAH' => ['Sekolah Agama Rendah', 'Maahad Tahfiz', 'Tadika Islam', 'Kolej Islam'],
        'KEDAI' => ['Rumah Kedai Wakaf', 'Kedai Dua Tingkat', 'Kiosk Wakaf', 'Unit Kedai'],
        'KOMPLEKS' => ['Kompleks Perniagaan Wakaf', 'Bazar Ramadan Tetap', 'Kompleks Islam', 'Medan Selera Wakaf'],
        'KEDIAMAN' => ['Rumah Imam', 'Rumah Kakitangan', 'Asrama Anak Yatim', 'Rumah Transit Asnaf'],
        'KEMUDAHAN_AWAM' => ['Dewan Serbaguna', 'Tanah Perkuburan Islam', 'Taman Rekreasi', 'Pusat Komuniti'],
        'INFRASTRUKTUR' => ['Jalan Masuk', 'Tempat Letak Kereta', 'Longkang Monsun', 'Jambatan Kecil'],
        'STRUKTUR' => ['Pagar Sempadan', 'Papan Tanda MAIS', 'Pondok Pengawal', 'Wakaf Rehat'],
        'PERALATAN' => ['Set Penghawa Dingin', 'Genset Kecemasan', 'Sistem PA', 'Pam Air'],
    ];

    private const JENIS = ['STRUKTUR' => ['pagar', 'papan_tanda', 'struktur', 'struktur']];

    private const KOS = [
        'BANGUNAN_PEJABAT' => [800000, 4500000], 'MASJID_SURAU' => [300000, 3500000], 'SEKOLAH' => [500000, 3000000], 'KEDAI' => [150000, 450000],
        'KOMPLEKS' => [1500000, 6000000], 'KEDIAMAN' => [120000, 400000], 'KEMUDAHAN_AWAM' => [80000, 900000], 'INFRASTRUKTUR' => [40000, 600000],
        'STRUKTUR' => [3000, 60000], 'PERALATAN' => [4000, 90000],
    ];

    private const SYARIKAT = ['Binaan Teguh', 'Seri Kemuncak', 'Al-Amin Services', 'Cahaya Jaya', 'Mutiara Bina', 'Teknik Murni', 'Indah Kemas', 'Wira Elektrik', 'Bestari Landskap', 'Prima Sanitasi', 'Nur Iman Engineering', 'Delima Maju'];

    public function handle(): int
    {
        $count = max(1, (int) $this->option('count'));
        if ($this->option('seed') !== null) {
            mt_srand((int) $this->option('seed'));
        }
        $this->sunyi();
        $admin = User::query()->where('role', 'admin')->orderBy('id')->first() ?? User::query()->orderBy('id')->first();
        if ($admin === null) {
            $this->components->error('Tiada pengguna dalam DB — jalankan seeder pengguna dahulu.');

            return self::FAILURE;
        }
        $lots = DB::table('lot_tanah as l')->join('data_spatial as ds', fn ($j) => $j->on('ds.lot_id', '=', 'l.id')->where('ds.semasa', true))
            ->whereNull('l.deleted_at')->where('l.status_lot', '!=', 'digabung')->where('l.klasifikasi', 'dalaman')->pluck('l.id')->all();
        if ($lots === []) {
            $this->components->error('Tiada lot dengan geometri — jalankan `php artisan ref:sample-lots` / sampel MOD-PRO dahulu.');

            return self::FAILURE;
        }
        $officers = User::query()->where('is_active', true)->orderBy('id')->limit(40)->get()->all();
        $kategori = AsetKategori::query()->where('status_aktif', true)->get()->keyBy('kod');
        $kontraktor = $this->kontraktor();
        $aset = app(AsetService::class);
        $periksa = app(PemeriksaanService::class);
        $jaga = app(PenyelenggaraanService::class);

        $this->components->info("Menjana {$count} aset sintetik pada ".count($lots).' lot…');
        $bar = $this->output->createProgressBar($count);
        $made = [];
        $premis = DB::table('premis_aset')->whereNull('deleted_at')->whereNotIn('id', DB::table('aset_fizikal')->whereNotNull('id_premis_pjk')->select('id_premis_pjk'))
            ->whereIn('lot_id', $lots)->inRandomOrder()->limit(max(5, intdiv($count, 8)))->get(['id', 'lot_id', 'nama_premis', 'jenis_aset', 'kategori_hartanah']);

        // 1) Assets linked to MOD-PJK premises (link, not duplicate).
        foreach ($premis as $p) {
            if (count($made) >= $count) {
                break;
            }
            $kod = in_array($p->kategori_hartanah, ['bazar'], true) ? 'KOMPLEKS' : (in_array($p->jenis_aset, ['rumah'], true) ? 'KEDIAMAN' : 'KEDAI');
            $made[] = $a = $this->satu($aset, $admin, $officers, $kategori[$kod], (string) $p->lot_id, $p->nama_premis.' (premis sewaan)', ['id_premis_pjk' => (int) $p->id]);
            $bar->advance();
        }
        // 2) The rest, with complexes getting sub-asset units.
        while (count($made) < $count) {
            $kod = $this->pilih(['MASJID_SURAU' => 18, 'KEDAI' => 14, 'STRUKTUR' => 16, 'KEMUDAHAN_AWAM' => 10, 'BANGUNAN_PEJABAT' => 6, 'SEKOLAH' => 7, 'KEDIAMAN' => 8, 'KOMPLEKS' => 5, 'INFRASTRUKTUR' => 8, 'PERALATAN' => 8]);
            $lot = $lots[mt_rand(0, count($lots) - 1)];
            $made[] = $induk = $this->satu($aset, $admin, $officers, $kategori[$kod], $lot);
            $bar->advance();
            if ($kod === 'KOMPLEKS') {
                for ($i = 1, $n = mt_rand(2, 4); $i <= $n && count($made) < $count; $i++) {
                    $made[] = $this->satu($aset, $admin, $officers, $kategori['KEDAI'], $lot, 'Unit '.str_pad((string) $i, 2, '0', STR_PAD_LEFT).' '.$induk->nama_aset, ['id_aset_induk' => $induk->id]);
                    $bar->advance();
                }
            }
        }
        $bar->finish();
        $this->newLine();

        $this->components->task('Nilai, pemeriksaan dan kondisi', function () use ($made, $aset, $periksa, $officers, $admin) {
            foreach ($made as $a) {
                $this->sejarahNilai($aset, $a, $admin);
                $this->pemeriksaan($periksa, $a, $officers ?: [$admin]);
            }
        });
        $this->components->task('Jadual dan kerja penyelenggaraan', function () use ($made, $jaga, $kontraktor, $admin) {
            foreach ($made as $a) {
                $this->penyelenggaraan($jaga, $a->refresh(), $kontraktor, $admin);
            }
        });
        $this->components->task('Insurans, jaminan, tag dan imbasan QR', function () use ($made, $aset, $admin, $officers) {
            foreach ($made as $a) {
                $this->lain($aset, $a, $admin, $officers);
            }
        });
        $this->components->task('Aset dilupuskan (arkib)', function () use ($made, $aset, $admin) {
            foreach (array_slice(array_values(array_filter($made, fn ($a) => $a->id_aset_induk === null && $a->id_premis_pjk === null)), 0, max(2, intdiv(count($made), 50))) as $a) {
                $jenis = $this->pilih(['pelupusan' => 2, 'hapus_kira' => 2, 'roboh' => 1]);
                AsetPelupusan::query()->create([
                    'id_aset' => $a->id, 'jenis' => $jenis, 'sebab' => 'Data sintetik — aset uzur dan tidak ekonomik dibaiki.', 'kaedah' => $jenis === 'roboh' ? 'musnah' : 'tender',
                    'status' => 'lulus', 'id_pemohon' => $admin->id, 'id_pelulus' => $admin->id, 'tarikh_keputusan' => now()->subDays(mt_rand(10, 300)), 'ulasan' => 'Sintetik',
                    'kod_daerah' => $a->kod_daerah, 'kod_mukim' => $a->kod_mukim, 'klasifikasi' => $a->klasifikasi,
                ]);
                $aset->arkib($a->refresh(), Lalai::STATUS_SELEPAS_PELUPUSAN[$jenis], 'Sintetik: '.Lalai::JENIS_PELUPUSAN[$jenis].' diluluskan');
                DB::table('jadual_penyelenggaraan')->where('id_aset', $a->id)->update(['status' => 'tidak_aktif']);
            }
        });
        $ptn = $jaga->tarikKeperluanPtn($admin);
        DB::table('rekod_penyelenggaraan')->where('sumber', 'ptn')->update(['sintetik' => true]);
        $this->components->info(count($made).' aset sintetik dijana; '.$ptn['dicipta'].' keperluan MOD-PTN menjadi kerja penyelenggaraan ('.$ptn['perlu_dipadankan'].' menunggu padanan).');

        return self::SUCCESS;
    }

    /**
     * @param  list<User>  $officers
     * @param  array<string, mixed>  $extra
     */
    private function satu(AsetService $svc, User $admin, array $officers, AsetKategori $k, string $lot, ?string $nama = null, array $extra = []): AsetFizikal
    {
        $p = DB::selectOne('SELECT ST_Y(p) AS lat, ST_X(p) AS lng FROM (SELECT (ST_Dump(ST_GeneratePoints(ds.geometri, 1, ?))).geom AS p FROM data_spatial ds WHERE ds.lot_id = ? AND ds.semasa LIMIT 1) s', [mt_rand(1, 1_000_000), $lot]);
        [$min, $max] = self::KOS[$k->kod];
        $tahun = mt_rand(1975, (int) now()->year - 1);
        $kos = round(mt_rand($min, $max) / 100) * 100;
        $nama ??= self::NAMA[$k->kod][mt_rand(0, count(self::NAMA[$k->kod]) - 1)].' '.Str::title(Str::random(1)).mt_rand(1, 99);
        $jenisPilihan = self::JENIS[$k->kod] ?? [$k->jenis_lalai];
        $mukim = DB::table('lot_tanah')->where('id', $lot)->value('kod_mukim');

        return $svc->daftar($extra + [
            'lot_id' => $lot, 'id_kategori' => $k->id, 'jenis_aset' => $jenisPilihan[mt_rand(0, count($jenisPilihan) - 1)], 'nama_aset' => $nama,
            'keterangan' => 'Rekod SINTETIK untuk pembangunan.', 'alamat' => 'Lot sintetik, mukim '.$mukim.', Selangor',
            'latitud' => $p?->lat, 'longitud' => $p?->lng, 'tahun_pembinaan' => $tahun, 'tahun_perolehan' => min((int) now()->year - 1, $tahun + mt_rand(0, 3)),
            'saiz_keluasan' => in_array($k->kod, ['PERALATAN'], true) ? null : mt_rand(20, 4000), 'unit_ukuran' => in_array($k->kod, ['PERALATAN'], true) ? 'unit' : ($k->kod === 'STRUKTUR' ? 'm' : 'm2'),
            'kos_perolehan' => min($kos, 490000), 'id_pegawai_bertanggungjawab' => $officers !== [] ? $officers[mt_rand(0, count($officers) - 1)]->id : $admin->id, 'sintetik' => true,
        ], $admin);
    }

    private function sejarahNilai(AsetService $svc, AsetFizikal $a, User $admin): void
    {
        if ($a->kos_perolehan === null || mt_rand(1, 100) > 75) {
            return;
        }
        $nilai = (float) $a->kos_perolehan;
        $mula = max((int) ($a->tahun_perolehan ?? 2000) + 2, (int) now()->year - 12);
        for ($y = $mula; $y <= (int) now()->year; $y += mt_rand(3, 5)) {
            $nilai = round($nilai * (mt_rand(95, 135) / 100), -2);
            $svc->kemaskiniNilai($a, [
                'nilai' => $nilai, 'tarikh_penilaian' => Carbon::create($y, mt_rand(1, 12), mt_rand(1, 28))->min(now())->toDateString(),
                'kaedah_penilaian' => $this->pilih(['laporan_jpph' => 5, 'penilai_berdaftar' => 2, 'penilaian_dalaman' => 2]),
                'id_laporan_penilaian' => 'JPPH/SEL/'.$y.'/'.mt_rand(1000, 9999), 'catatan' => 'Sintetik',
            ], $admin);
        }
    }

    /** @param list<User> $officers */
    private function pemeriksaan(PemeriksaanService $svc, AsetFizikal $a, array $officers): void
    {
        if (mt_rand(1, 100) <= 15) {
            return; // never inspected (SRS-AST-21 report)
        }
        $n = mt_rand(1, 4);
        $tarikh = now()->subDays(mt_rand(30, 800));
        for ($i = 0; $i < $n; $i++) {
            $kondisi = $this->pilih(['baik' => 70, 'perlu_perhatian' => 22, 'kritikal' => 8]);
            $by = $officers[mt_rand(0, count($officers) - 1)];
            $svc->rekod($a->refresh(), [
                'tarikh_pemeriksaan' => $tarikh->toDateString(), 'status_kondisi_dilapor' => $kondisi, 'id_luar_talian' => (string) Str::uuid(),
                'isu_dikenalpasti' => ['baik' => 'Tiada isu ketara.', 'perlu_perhatian' => 'Cat mengelupas dan kebocoran kecil pada bumbung.', 'kritikal' => 'Retakan struktur dan pendawaian terdedah.'][$kondisi],
                'tindakan_disyorkan' => $kondisi === 'baik' ? null : 'Jadualkan pembaikan.', 'latitud' => $a->latitud, 'longitud' => $a->longitud, 'ketepatan_gps_m' => mt_rand(3, 25),
                'senarai_semak' => array_map(fn ($k) => ['kod' => $k, 'status' => $kondisi === 'baik' ? 'baik' : (mt_rand(0, 2) ? 'baik' : 'isu')], array_slice(array_keys(Lalai::SENARAI_SEMAK), 0, 5)),
                'sintetik' => true,
            ], $by, $this->pilih(['pwa' => 5, 'manual' => 3, 'qr' => 2]));
            $tarikh = $tarikh->copy()->addDays(mt_rand(60, 240));
            if ($tarikh->isFuture()) {
                break;
            }
        }
    }

    /** @param list<AsetKontraktor> $kontraktor */
    private function penyelenggaraan(PenyelenggaraanService $svc, AsetFizikal $a, array $kontraktor, User $admin): void
    {
        $jadual = JadualPenyelenggaraan::query()->withoutGlobalScope('access')->where('id_aset', $a->id)->get();
        foreach ($jadual as $j) {
            // Spread due dates; ~12 % overdue.
            $due = mt_rand(1, 100) <= 12 ? now()->subDays(mt_rand(1, 120)) : now()->addDays(mt_rand(0, 30 * $j->kitaran_bulan));
            $j->forceFill(['tarikh_jatuh_tempoh' => $due->toDateString(), 'sintetik' => true, 'tarikh_terakhir_dilaksana' => $due->copy()->subMonthsNoOverflow($j->kitaran_bulan)->toDateString()])->save();
        }
        $n = mt_rand(0, 6);
        for ($i = 0; $i < $n; $i++) {
            $c = $kontraktor[mt_rand(0, count($kontraktor) - 1)];
            $siap = now()->subDays(mt_rand(5, 1100));
            $jenis = $jadual->isNotEmpty() && mt_rand(0, 1) ? $jadual->random()->jenis_penyelenggaraan : array_rand(Lalai::JENIS_PENYELENGGARAAN);
            $k = $svc->ciptaKerja($a, [
                'jenis_kerja' => $this->pilih(['pencegahan' => 5, 'pembaikan' => 4, 'kecemasan' => 1]), 'jenis_penyelenggaraan' => $jenis,
                'keterangan' => 'Kerja '.(Lalai::JENIS_PENYELENGGARAAN[$jenis] ?? $jenis).' (sintetik)', 'keutamaan' => $this->pilih(['rendah' => 2, 'sederhana' => 5, 'tinggi' => 2, 'kritikal' => 1]),
                'tarikh_dijadual' => $siap->copy()->subDays(mt_rand(3, 30))->toDateString(), 'kos_anggaran' => mt_rand(3, 150) * 100, 'id_kontraktor' => $c->id, 'sintetik' => true,
            ], $admin);
            $kos = round(mt_rand(300, 15000) / 10) * 10;
            DB::table('rekod_penyelenggaraan')->where('id', $k->id)->update([
                'status' => 'selesai', 'tarikh_mula' => $siap->copy()->subDays(mt_rand(1, 10))->toDateString(), 'tarikh_siap' => $siap->toDateString(),
                'kos' => $kos, 'catatan_penyelesaian' => 'Kerja disiapkan (sintetik).', 'id_aliran' => null,
            ]);
        }
        // Open work: some planned / in progress, some past their planned date.
        if (mt_rand(1, 100) <= 30) {
            $c = $kontraktor[mt_rand(0, count($kontraktor) - 1)];
            $k = $svc->ciptaKerja($a, [
                'jenis_kerja' => 'pembaikan', 'jenis_penyelenggaraan' => 'bangunan', 'keterangan' => 'Pembaikan tertangguh (sintetik)',
                'tarikh_dijadual' => now()->addDays(mt_rand(-60, 45))->toDateString(), 'kos_anggaran' => mt_rand(5, 150) * 100, 'id_kontraktor' => $c->id, 'sintetik' => true,
            ], $admin);
            DB::table('rekod_penyelenggaraan')->where('id', $k->id)->update(['status' => $this->pilih(['dirancang' => 3, 'diluluskan' => 2, 'dalam_pelaksanaan' => 2]), 'id_aliran' => null]);
        }
    }

    /** @param list<User> $officers */
    private function lain(AsetService $svc, AsetFizikal $a, User $admin, array $officers): void
    {
        $k = AsetKategori::query()->find($a->id_kategori);
        if ($k && $k->jenis_lalai === 'bangunan' && mt_rand(1, 100) <= 45) {
            $mula = now()->subDays(mt_rand(0, 360));
            AsetInsurans::query()->create([
                'id_aset' => $a->id, 'no_polisi' => 'POL-'.mt_rand(100000, 999999), 'penanggung' => $this->pilih(['Takaful Sintetik Berhad' => 1, 'Syarikat Takaful Contoh' => 1]),
                'jenis_perlindungan' => 'Kebakaran dan bencana', 'nilai_dilindungi' => $a->kos_perolehan, 'premium' => round((float) $a->kos_perolehan * 0.002, 2),
                'tarikh_mula' => $mula->toDateString(), 'tarikh_tamat' => $mula->copy()->addYear()->toDateString(), 'sintetik' => true,
                'kod_daerah' => $a->kod_daerah, 'kod_mukim' => $a->kod_mukim, 'klasifikasi' => $a->klasifikasi,
            ]);
        }
        if ($k && in_array($k->kod, ['PERALATAN', 'BANGUNAN_PEJABAT', 'KOMPLEKS'], true) && mt_rand(1, 100) <= 50) {
            $mula = now()->subDays(mt_rand(30, 900));
            AsetJaminan::query()->create([
                'id_aset' => $a->id, 'perkara' => $k->kod === 'PERALATAN' ? 'Jaminan pengeluar' : 'Jaminan sistem bumbung', 'pembekal' => 'Pembekal Sintetik Sdn Bhd',
                'tarikh_mula' => $mula->toDateString(), 'tarikh_tamat' => $mula->copy()->addYears(mt_rand(1, 3))->toDateString(), 'sintetik' => true,
                'kod_daerah' => $a->kod_daerah, 'kod_mukim' => $a->kod_mukim, 'klasifikasi' => $a->klasifikasi,
            ]);
        }
        foreach ((array) array_rand(array_flip(['warisan', 'tarikan_pelancong', 'wakaf_khas', 'sewaan', 'oku_mesra', 'risiko_banjir', 'tepi_jalan_utama', 'perlu_naik_taraf', 'baharu_siap']), mt_rand(1, 2)) as $t) {
            if (mt_rand(0, 2) === 0) {
                $svc->tambahTag($a, (string) $t, $admin);
            }
        }
        for ($i = 0, $n = mt_rand(0, 4); $i < $n; $i++) {
            $u = mt_rand(0, 2) === 0 ? null : ($officers !== [] ? $officers[mt_rand(0, count($officers) - 1)] : null);
            AsetQrLogImbasan::query()->create([
                'qr_ref' => $a->qr_ref, 'id_aset' => $a->id, 'cap_masa' => now()->subMinutes(mt_rand(10, 60 * 24 * 300)), 'id_pengguna' => $u?->id,
                'peranan_pengimbas' => $u ? 'pegawai_operasi' : null, 'latitud_peranti' => $a->latitud, 'longitud_peranti' => $a->longitud,
                'jenis_paparan' => $u ? 'penuh' : 'minimum', 'ejen_pengguna' => 'Sintetik', 'kod_daerah' => $a->kod_daerah,
            ]);
        }
    }

    /** @return list<AsetKontraktor> */
    private function kontraktor(): array
    {
        $out = [];
        foreach (self::SYARIKAT as $i => $s) {
            $out[] = AsetKontraktor::query()->firstOrCreate(['nama_syarikat' => $s.' Sdn Bhd (SINTETIK)'], [
                'no_pendaftaran' => 'SSM-'.str_pad((string) (100000 + $i * 7919), 7, '0', STR_PAD_LEFT), 'no_telefon' => '03-0000 '.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'emel' => 'kontraktor'.$i.'@contoh.invalid', 'bidang' => array_slice(array_keys(Lalai::JENIS_PENYELENGGARAAN), $i % 10, 3),
                'tarikh_tamat_pendaftaran' => now()->addMonths(mt_rand(-2, 36))->toDateString(), 'status_aktif' => true, 'sintetik' => true,
            ]);
        }

        return $out;
    }

    /**
     * @template K of array-key
     *
     * @param  array<K, int>  $weights
     * @return K
     */
    private function pilih(array $weights): int|string
    {
        $r = mt_rand(1, array_sum($weights));
        foreach ($weights as $k => $w) {
            if (($r -= $w) <= 0) {
                return $k;
            }
        }

        return array_key_first($weights);
    }

    /** No notifications, DMS uploads or workflow instances for synthetic data. */
    private function sunyi(): void
    {
        app()->instance(Notifier::class, new class implements Notifier
        {
            public function send(string $type, User|iterable $recipients, array $data = [], ?string $link = null, ?string $dedupeKey = null): void {}
        });
        app()->instance(DocumentStore::class, new class implements DocumentStore
        {
            public function store(UploadedFile $file, string $ownerType, string|int $ownerId, string $category, User $uploader, string $classification = 'dalaman', array $metadata = []): string
            {
                throw new DocumentRejected('Data sintetik — tidak disimpan dalam DMS.', 'sintetik');
            }

            public function forOwner(string $ownerType, string|int $ownerId, User $user): array
            {
                return [];
            }

            public function temporaryUrl(string $documentId, User $user, int $minutes = 5): ?string
            {
                return null;
            }
        });
        app()->instance(WorkflowEngine::class, new class implements WorkflowEngine
        {
            public function start(string $flowCode, string $subjectType, string|int $subjectId, User $initiator, array $context = [], ?string $title = null): string
            {
                return 'sintetik-'.Str::random(8);
            }

            public function status(string $instanceId): string
            {
                return 'lulus';
            }

            public function activeInstanceFor(string $subjectType, string|int $subjectId): ?string
            {
                return null;
            }
        });
    }
}
