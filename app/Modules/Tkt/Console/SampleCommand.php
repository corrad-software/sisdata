<?php

namespace App\Modules\Tkt\Console;

use App\Models\User;
use App\Modules\Kba\Contracts\ArticleDrafts;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Tkt\Models\AhliPasukan;
use App\Modules\Tkt\Models\AktivitiSokongan;
use App\Modules\Tkt\Models\CadanganKb;
use App\Modules\Tkt\Models\Pasukan;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Services\Penugasan;
use App\Modules\Tkt\Services\SegerakPusat;
use App\Modules\Tkt\Services\SlaTiket;
use App\Modules\Tkt\Services\TiketService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * `php artisan tkt:sample --count=80` — SYNTHETIC support tickets for the
 * dev DB, spread over the last 6 months across every severity, category,
 * module and status (including SLA breaches, escalations, waiting for the
 * reporter, duplicates, reopened, satisfaction scores, a few from the
 * central complaints system), plus synthetic support staff, support
 * activities and KB-deflection sessions. Every ticket row is flagged
 * `sintetik`; staff accounts use @sintetik.sisdata.local addresses.
 * Goes through the real services with the clock moved back; notifications
 * and KB drafts are suppressed.
 */
class SampleCommand extends Command
{
    protected $signature = 'tkt:sample {--count=80 : Bilangan tiket sintetik} {--seed= : Benih rawak}';

    protected $description = 'Jana tiket sokongan SINTETIK merentas keterukan/status untuk DB pembangunan (MOD-TKT)';

    private const KES = [
        'pro' => [['Profil hartanah tidak memaparkan peta lot', 'Apabila membuka Profil Hartanah 360°, tab peta kosong dan muncul ralat memuatkan lapisan.'], ['Carian lot mengikut mukim lambat', 'Carian lot mengambil masa lebih 30 saat bagi mukim Damansara.']],
        'pjk' => [['Invois sewaan tidak dapat dijana', 'Butang Jana Invois tidak bertindak balas bagi kontrak pajakan bulan ini.'], ['Baki lejar penyewa tidak tepat', 'Baki tertunggak dipaparkan berbeza dengan resit bayaran yang telah diterima.']],
        'cki' => [['Status bayaran cukai tidak dikemas kini', 'Bayaran cukai tanah yang dibuat semalam masih berstatus belum bayar.']],
        'dms' => [['Gagal muat naik dokumen PDF', 'Muat naik geran berformat PDF ditolak dengan mesej jenis fail tidak sah.'], ['Pratonton dokumen tidak dipaparkan', 'Pratonton dokumen kekal memuatkan tanpa paparan.']],
        'rbc' => [['Tidak dapat log masuk melalui SSO', 'Selepas memasukkan kod TOTP, saya dikembalikan ke halaman log masuk.'], ['Mohon capaian modul Aduan', 'Saya memerlukan capaian lihat bagi modul Aduan untuk tugasan baharu.']],
        'gis' => [['Lapisan sempadan mukim tidak dipaparkan', 'Lapisan sempadan mukim hilang selepas zum melebihi aras 15.']],
        'wfe' => [['Tugasan kelulusan tidak muncul dalam peti masuk', 'Permohonan yang dihantar pegawai tidak muncul dalam senarai tugasan saya.']],
        'lap' => [['Laporan kutipan bulanan tidak lengkap', 'Laporan kutipan bulan lepas tidak mengandungi data daerah Sepang.'], ['Cadangan: tambah lajur mukim dalam laporan', 'Mohon tambah lajur mukim dalam laporan tunggakan untuk memudahkan analisis.']],
        'adu' => [['Bagaimana menukar kategori aduan?', 'Saya ingin tahu cara menukar kategori aduan yang telah ditriaj.']],
        'dsh' => [['Dashboard eksekutif lambat dimuatkan', 'Paparan dashboard mengambil masa lebih seminit pada waktu pagi.']],
        'umum' => [['Sistem tidak boleh diakses', 'Semua pengguna di pejabat tidak dapat mengakses SISDATA sejak jam 9 pagi.'], ['Pertanyaan format eksport Excel', 'Adakah eksport Excel menyokong format .xls lama?']],
    ];

    public function handle(TiketService $service, SlaTiket $sla, SegerakPusat $segerak): int
    {
        $count = max(1, (int) $this->option('count'));
        if ($this->option('seed') !== null) {
            mt_srand((int) $this->option('seed'));
        }
        app()->instance(Notifier::class, new class implements Notifier
        {
            public function send(string $type, User|iterable $recipients, array $data = [], ?string $link = null, ?string $dedupeKey = null): void {}
        });
        app()->instance(ArticleDrafts::class, new class implements ArticleDrafts
        {
            public function fromTicket(string $ticketId, array $data, ?User $author = null): int
            {
                return 0;
            }

            public function forTicket(string $ticketId): array
            {
                return [];
            }
        });

        [$reporters, $l1Techs, $l2Techs] = $this->kakitangan();
        $l1 = Pasukan::query()->where('kod', 'L1_MAIS')->first();
        $l2 = Pasukan::query()->where('kod', 'L2_VENDOR')->first();
        $now = CarbonImmutable::now();
        $statuses = ['baharu', 'disiasat', 'dalam_tindakan', 'menunggu_pelapor', 'selesai', 'disahkan', 'tutup', 'tutup', 'tutup', 'tutup', 'pendua'];
        $made = 0;

        for ($i = 0; $i < $count; $i++) {
            $modul = array_rand(self::KES);
            [$tajuk, $teks] = self::KES[$modul][array_rand(self::KES[$modul])];
            $severity = [1, 2, 2, 3, 3, 3, 3, 4, 4, 4][mt_rand(0, 9)];
            $kategori = $severity === 4 && mt_rand(0, 1) ? 'soalan' : (str_contains($tajuk, 'Mohon capaian') ? 'capaian' : (str_starts_with($tajuk, 'Cadangan') ? 'permintaan_perubahan' : 'ralat'));
            $target = $statuses[array_rand($statuses)];
            $opened = $now->subMinutes(mt_rand(60, 180 * 24 * 60));
            if (in_array($target, ['baharu', 'disiasat'], true)) {
                $opened = $now->subMinutes(mt_rand(20, 6 * 24 * 60));
            }
            Carbon::setTestNow($opened);
            $reporter = $reporters[array_rand($reporters)];
            $sesi = (string) Str::uuid();
            if (mt_rand(1, 100) <= 40) {
                CadanganKb::query()->create(['id_tiket_draf' => $sesi, 'id_pengguna' => $reporter->id, 'id_artikel_kb' => '/admin/kba/carian?q='.rawurlencode($tajuk), 'tajuk' => 'Panduan: '.$tajuk, 'skor_relevan' => mt_rand(40, 90) / 100, 'kedudukan' => 1, 'diklik' => (bool) mt_rand(0, 1), 'modul' => $modul, 'cap_masa' => now()]);
            }
            $central = mt_rand(1, 100) <= 6;
            if ($central) {
                [$t] = $segerak->terima(['nombor_aduan' => 'SAP-'.$opened->format('Ymd').'-'.mt_rand(1000, 9999), 'tajuk' => $tajuk, 'keterangan' => $teks, 'kategori' => 'TEKNIKAL', 'keterukan' => ['KRITIKAL', 'TINGGI', 'SEDERHANA', 'RENDAH'][$severity - 1], 'modul' => $modul], $reporter);
                $t->forceFill(['sintetik' => true])->save();
            } else {
                $t = $service->buka([
                    'tajuk' => $tajuk, 'penerangan' => $teks.' (data sintetik)', 'kategori' => $kategori, 'keterukan' => $severity, 'modul' => $modul,
                    'kesan' => ['semua', 'sebahagian', 'seorang', 'seorang'][$severity - 1], 'kesegeraan' => ['tinggi', 'tinggi', 'sederhana', 'rendah'][$severity - 1],
                ], mt_rand(1, 10) === 1 ? 'portal' : 'aplikasi', $reporter, null, [
                    'skrin' => 'SISDATA — '.$modul, 'modul' => $modul, 'url' => 'http://localhost:3000/admin/'.$modul, 'kod_fungsi' => $modul.'.utama',
                    'pelayar' => 'Mozilla/5.0 (sintetik)', 'versi_ui' => '0.1.0', 'id_permintaan' => [(string) Str::uuid()],
                ], $sesi, true);
            }
            $tech = $t->id_juruteknik !== null ? User::query()->find($t->id_juruteknik) : ($l1Techs[0] ?? null);
            if ($tech === null) {
                continue;
            }
            $made++;
            $step = function (int $minutes) use (&$opened) {
                $opened = $opened->addMinutes($minutes);
                Carbon::setTestNow($opened);
            };
            $breach = mt_rand(1, 100) <= 18;
            $respond = $breach ? mt_rand(200, 900) : mt_rand(5, 25 * $severity);
            if ($target === 'baharu') {
                continue;
            }
            $step($respond);
            $service->tukarStatus($t, 'disiasat', $tech, 'Siasatan awal dimulakan.');
            if ($breach || $severity <= 2) {
                $step(mt_rand(30, 600));
                $sla->semak();
            }
            if ($severity <= 3 && mt_rand(0, 3) === 0 && $l2 !== null && $l2Techs !== []) {
                $service->catatan($t, 'dalaman', 'Perlu semakan kod — diserahkan kepada vendor.', $tech);
                app(Penugasan::class)->serahAras($t, $l2->id, 'VND-'.mt_rand(10000, 99999), 'Isu memerlukan pembetulan kod oleh vendor.', $tech);
                $tech = User::query()->find($t->id_juruteknik) ?? $tech;
            }
            if ($target === 'disiasat') {
                continue;
            }
            if ($target === 'pendua') {
                $parent = Tiket::query()->where('id', '!=', $t->id)->where('modul', $t->modul)->where('sintetik', true)->orderBy('tarikh_buka')->first();
                if ($parent !== null) {
                    $service->tandaPendua($t, $parent, $tech);
                }

                continue;
            }
            $step(mt_rand(30, 300));
            $service->catatan($t, 'kelihatan_pelapor', 'Terima kasih. Kami sedang menyemak isu ini.', $tech);
            if ($target === 'menunggu_pelapor') {
                $service->tukarStatus($t, 'menunggu_pelapor', $tech, 'Mohon tangkapan skrin ralat.');

                continue;
            }
            if ($kategori !== 'permintaan_perubahan') {
                $service->tukarStatus($t, 'dalam_tindakan', $tech);
            }
            if ($target === 'dalam_tindakan') {
                continue;
            }
            $step(($breach ? mt_rand(3, 15) : mt_rand(1, 3)) * 60 * [1 => 1, 2 => 4, 3 => 12, 4 => 30][$severity]);
            $sla->semak();
            $service->tukarStatus($t, 'selesai', $tech, null, [
                'punca_akar' => 'Konfigurasi / data tidak konsisten (sintetik).',
                'penyelesaian' => 'Konfigurasi dibetulkan dan cache dikosongkan; pengguna diminta cuba semula.',
                'tindakan_pembetulan' => mt_rand(0, 2) === 0 ? 'Tambah semakan automatik dalam pemantauan harian.' : null,
                'masa_henti_minit' => $severity === 1 ? mt_rand(15, 180) : null,
                'sebab_lepas_sla' => $breach ? 'Menunggu pembetulan vendor / ujian regresi.' : null,
            ]);
            if ($target === 'selesai') {
                continue;
            }
            $step(mt_rand(60, 2 * 24 * 60));
            $service->sahkan($t, $reporter);
            if (mt_rand(0, 2) > 0) {
                $service->kepuasan($t, mt_rand(3, 5), mt_rand(0, 1) ? 'Pantas dan membantu.' : null, $reporter);
            }
            if ($target === 'disahkan') {
                continue;
            }
            $step(mt_rand(30, 600));
            $service->tutup($t, $tech);
        }

        // Self-service sessions that never became a ticket (deflected).
        foreach (range(1, max(5, (int) ($count / 4))) as $n) {
            Carbon::setTestNow($now->subMinutes(mt_rand(60, 120 * 24 * 60)));
            $modul = array_rand(self::KES);
            [$tajuk] = self::KES[$modul][array_rand(self::KES[$modul])];
            CadanganKb::query()->create(['id_tiket_draf' => (string) Str::uuid(), 'id_pengguna' => $reporters[array_rand($reporters)]->id, 'id_artikel_kb' => '/admin/kba/carian?q='.rawurlencode($tajuk), 'tajuk' => 'Panduan: '.$tajuk, 'skor_relevan' => 0.8, 'kedudukan' => 1, 'diklik' => true, 'tiket_diteruskan' => false, 'modul' => $modul, 'cap_masa' => now()]);
        }
        Carbon::setTestNow();

        foreach ([['tampalan_keselamatan', 'Tampalan keselamatan PHP 8.4.x & PostgreSQL 17.x'], ['penyelenggaraan_pencegahan', 'Penyelenggaraan indeks & pembersihan log bulanan'], ['masa_henti', 'Masa henti tidak berjadual — sambungan pangkalan data'], ['pemindahan_teknologi', 'Sesi latihan pentadbiran sistem kepada BTM MAIS'], ['tindakan_pencegahan', 'Pemantauan automatik ruang cakera pelayan']] as $k => [$jenis, $tajuk]) {
            foreach ([0, 1, 2] as $m) {
                AktivitiSokongan::query()->create([
                    'jenis' => $jenis, 'tarikh' => $now->subMonths($m)->startOfMonth()->addDays(5 + $k)->toDateString(), 'tajuk' => $tajuk.' (sintetik)',
                    'keterangan' => 'Rekod sintetik untuk laporan bulanan.', 'tempoh_minit' => $jenis === 'masa_henti' ? 45 : null,
                    'punca_akar' => $jenis === 'masa_henti' ? 'Had sambungan pangkalan data dicapai (sintetik).' : null, 'rujukan' => 'SINT-'.mt_rand(100, 999), 'sintetik' => true,
                ]);
            }
        }

        $this->info("{$made} tiket sintetik dijana (".Tiket::query()->where('sintetik', true)->count().' jumlah sintetik).');

        return self::SUCCESS;
    }

    /**
     * Synthetic reporters and support staff (@sintetik.sisdata.local) added
     * to the seeded L1 / L2 teams.
     *
     * @return array{0: list<User>, 1: list<User>, 2: list<User>}
     */
    private function kakitangan(): array
    {
        $make = fn (string $email, string $name) => User::query()->firstOrCreate(['email' => $email], ['name' => $name, 'password' => Str::random(40), 'role' => 'user', 'is_active' => true]);
        $reporters = [];
        foreach (['Aminah Rahman', 'Faizal Hamid', 'Nurul Huda', 'Rizal Osman', 'Salmah Yusof', 'Zainal Abidin'] as $i => $name) {
            $reporters[] = $make('pelapor'.($i + 1).'@sintetik.sisdata.local', $name.' (sintetik)');
        }
        $teams = ['L1_MAIS' => [['juruteknik', 3], ['penyelia', 1], ['pengurus', 1]], 'L2_VENDOR' => [['juruteknik', 2], ['penyelia', 1]]];
        $techs = ['L1_MAIS' => [], 'L2_VENDOR' => []];
        foreach ($teams as $kod => $roles) {
            $team = Pasukan::query()->where('kod', $kod)->first();
            if ($team === null) {
                continue;
            }
            foreach ($roles as [$role, $n]) {
                for ($j = 1; $j <= $n; $j++) {
                    $user = $make(strtolower($kod).'.'.$role.$j.'@sintetik.sisdata.local', ucfirst($role).' '.($kod === 'L1_MAIS' ? 'Meja Bantuan' : 'Vendor').' '.$j.' (sintetik)');
                    AhliPasukan::query()->firstOrCreate(['id_pasukan' => $team->id, 'id_pengguna' => $user->id], ['peranan' => $role, 'aktif' => true]);
                    if ($role === 'juruteknik') {
                        $techs[$kod][] = $user;
                    }
                }
            }
        }

        return [$reporters, $techs['L1_MAIS'], $techs['L2_VENDOR']];
    }
}
