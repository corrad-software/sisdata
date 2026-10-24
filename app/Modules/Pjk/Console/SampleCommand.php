<?php

namespace App\Modules\Pjk\Console;

use App\Models\User;
use App\Modules\Pjk\Models\Bayaran;
use App\Modules\Pjk\Models\DasarAgihanManfaat;
use App\Modules\Pjk\Models\JadualCaj;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\Penyewa;
use App\Modules\Pjk\Models\PermohonanSewaan;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\Models\SejarahStatusPremis;
use App\Modules\Pjk\Models\TransaksiGateway;
use App\Modules\Pjk\Services\BayaranService;
use App\Modules\Pjk\Services\JadualCajService;
use App\Modules\Pjk\Services\KontrakService;
use App\Modules\Pjk\Support\Penomboran;
use App\Modules\Pjk\Support\Wang;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Synthetic MOD-PJK data on existing lots (dev / demo only): tenants,
 * premises (category by the lot's bahagian: wakaf / baitulmal / hartanah),
 * contracts in every status, charge schedules, payments (on time, partial,
 * late, unpaid → arrears), deposits, benefit policies and applications.
 * Everything is flagged `sintetik`; identity numbers use an impossible date
 * ("990000-99-…") so they can never be a real MyKad.
 *
 *   php artisan pjk:sample --count=120
 */
class SampleCommand extends Command
{
    protected $signature = 'pjk:sample {--count=120 : Bilangan kontrak} {--seed=7 : Benih rawak}';

    protected $description = 'MOD-PJK: jana data sintetik penyewa, premis, kontrak, jadual caj, bayaran dan deposit di atas lot sedia ada';

    private const KATEGORI = [
        'wakaf' => ['premis', 'bazar', 'tanah', 'premis_perniagaan'],
        'baitulmal' => ['premis_kediaman', 'premis', 'tanah'],
        'hartanah' => ['premis_perniagaan', 'tanah', 'premis_kediaman', 'bazar', 'premis'],
    ];

    private const JENIS_ASET = ['premis' => 'bangunan', 'premis_perniagaan' => 'unit_kedai', 'premis_kediaman' => 'rumah', 'tanah' => 'tanah', 'bazar' => 'gerai'];

    private const KADAR = ['premis' => [800, 3500], 'premis_perniagaan' => [1200, 6000], 'premis_kediaman' => [400, 1500], 'tanah' => [300, 4000], 'bazar' => [150, 600]];

    private const NAMA = ['Ahmad', 'Siti', 'Mohd', 'Nur', 'Aminah', 'Hafiz', 'Farah', 'Ismail', 'Zainab', 'Rahman', 'Aisyah', 'Kamal', 'Huda', 'Azman', 'Salmah'];

    private const SYARIKAT = ['Niaga', 'Makmur', 'Bestari', 'Sinar', 'Harmoni', 'Cemerlang', 'Gemilang', 'Seri', 'Mutiara', 'Berkat'];

    public function handle(KontrakService $kontrak, JadualCajService $jadual, BayaranService $bayaran): int
    {
        $count = max(1, (int) $this->option('count'));
        mt_srand((int) $this->option('seed'));
        $officer = User::query()->where('email', 'admin@example.com')->first() ?? User::query()->orderBy('id')->first();

        $lots = DB::table('lot_tanah')->whereNull('deleted_at')->where('status_lot', '!=', 'arkib')
            ->whereNotIn('id', PremisAset::query()->withoutGlobalScope('access')->select('lot_id'))
            ->orderByDesc('sintetik')->orderBy('id')->limit((int) ceil($count * 1.3))->get(['id', 'kod_daerah', 'kod_mukim', 'bahagian_pemilik', 'keluasan', 'no_lot']);
        if ($lots->count() < $count) {
            $this->error("Hanya {$lots->count()} lot tersedia tanpa premis; perlu sekurang-kurangnya {$count}. Jalankan `php artisan ref:sample-lots` dan import ke lot_tanah dahulu.");

            return self::FAILURE;
        }

        $this->seedPolicies($officer);
        $today = Carbon::today();
        $made = ['premis' => 0, 'kontrak' => 0, 'bayaran' => 0];
        $bar = $this->output->createProgressBar($lots->count());

        foreach ($lots as $i => $lot) {
            $bahagian = in_array($lot->bahagian_pemilik, ['wakaf', 'baitulmal', 'hartanah'], true) ? $lot->bahagian_pemilik : 'hartanah';
            $kategori = self::KATEGORI[$bahagian][mt_rand(0, count(self::KATEGORI[$bahagian]) - 1)];
            [$lo, $hi] = self::KADAR[$kategori];
            $rate = intdiv(mt_rand($lo, $hi), 10) * 1000; // sen, rounded to RM10
            $premis = DB::transaction(fn () => PremisAset::query()->create([
                'lot_id' => $lot->id, 'kod_premis' => Penomboran::next('premis'),
                'nama_premis' => ucfirst(str_replace('_', ' ', $kategori)).' '.$lot->no_lot.' (sintetik)',
                'jenis_aset' => self::JENIS_ASET[$kategori], 'kategori_hartanah' => $kategori,
                'keluasan' => Wang::dec(mt_rand(5000, 300000)), 'alamat' => 'Alamat sintetik '.($i + 1).', Selangor',
                'status_premis' => 'kosong', 'tarikh_status' => $today->copy()->subDays(mt_rand(10, 700))->format('Y-m-d'),
                'kadar_tawaran' => Wang::dec($rate), 'kod_daerah' => $lot->kod_daerah, 'kod_mukim' => $lot->kod_mukim,
                'bahagian' => $bahagian, 'papar_portal' => true, 'sintetik' => true,
            ]));
            SejarahStatusPremis::query()->create(['premis_id' => $premis->id, 'status_selepas' => 'kosong', 'tarikh' => $premis->tarikh_status?->format('Y-m-d'), 'sebab' => 'Data sintetik']);
            $made['premis']++;

            if ($made['kontrak'] < $count) {
                $made['bayaran'] += $this->contract($premis, $rate, $officer, $kontrak, $jadual, $bayaran, $made['kontrak']);
                $made['kontrak']++;
            } elseif (mt_rand(0, 2) === 0) {
                $this->application($premis);
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $kontrak->refreshStatuses($today);
        $this->info("Premis: {$made['premis']} · kontrak: {$made['kontrak']} · bayaran: {$made['bayaran']} (semua bertanda sintetik).");

        return self::SUCCESS;
    }

    private function contract(PremisAset $premis, int $rate, ?User $officer, KontrakService $service, JadualCajService $jadual, BayaranService $bayaran, int $n): int
    {
        $today = Carbon::today();
        $penyewa = $this->tenant($n);
        $months = [12, 24, 24, 36, 36, 60][mt_rand(0, 5)];
        $profile = $n % 20; // 0 draf, 1 dalam kelulusan, 2 ditamatkan, 3-4 expiring soon, rest: running / expired
        $start = match (true) {
            in_array($profile, [0, 1], true) => $today->copy()->addDays(mt_rand(10, 60)),
            in_array($profile, [3, 4], true) => $today->copy()->subMonthsNoOverflow($months)->addDays(mt_rand(20, 170)),
            default => $today->copy()->subMonthsNoOverflow(mt_rand(1, $months + 6))->startOfMonth(),
        };
        $end = $start->copy()->addMonthsNoOverflow($months)->subDay();

        $k = DB::transaction(function () use ($premis, $penyewa, $rate, $start, $end, $officer, $service) {
            $k = Kontrak::query()->create([
                'no_kontrak' => Penomboran::next('kontrak'), 'lot_id' => $premis->lot_id, 'premis_id' => $premis->id, 'penyewa_id' => $penyewa->id, 'penyewa' => $penyewa->nama,
                'jenis_perjanjian' => $premis->kategori_hartanah === 'tanah' ? 'pajakan' : 'sewaan', 'kategori_hartanah' => $premis->kategori_hartanah,
                'tarikh_mula' => $start->format('Y-m-d'), 'tarikh_tamat' => $end->format('Y-m-d'), 'kadar' => Wang::dec($rate), 'deposit' => Wang::dec($rate * 2),
                'kitaran_caj' => 'bulanan', 'hari_tempoh_bayar' => 14, 'nilai_kontrak' => $service->value(Wang::dec($rate), $start->format('Y-m-d'), $end->format('Y-m-d')),
                'status' => 'draf', 'klasifikasi' => 'sulit', 'kod_daerah' => $premis->kod_daerah, 'kod_mukim' => $premis->kod_mukim, 'bahagian' => $premis->bahagian,
                'tarikh_status' => Carbon::today()->format('Y-m-d'), 'sumber' => 'kaunter', 'id_pendaftar' => $officer?->id, 'sintetik' => true,
                'terma' => 'Terma sintetik: premis digunakan untuk tujuan yang diluluskan sahaja.',
            ]);
            DB::table('sejarah_status_kontrak')->insert(['kontrak_id' => $k->id, 'status_selepas' => 'draf', 'catatan' => 'Data sintetik', 'created_at' => now()]);

            return $k;
        });

        if ($n % 20 === 0) {
            return 0;
        }
        if ($n % 20 === 1) {
            $service->transition($k, 'dalam_kelulusan', $officer?->id, 'Data sintetik — menunggu kelulusan');

            return 0;
        }

        $service->activate($k, $officer?->id, 'Data sintetik — diaktifkan tanpa aliran WFE', false, false, $start->copy());
        $behaviour = mt_rand(1, 100); // ≤70 on time, ≤85 partial / late, else stops paying
        $payments = 0;
        $deposit = JadualCaj::query()->where('kontrak_id', $k->id)->where('kod_caj', 'deposit')->first();
        if ($deposit !== null && $behaviour <= 95) {
            $payments += $this->pay($k, $bayaran, Wang::sen($deposit->amaun), $start, 'kaunter');
        }
        $stopAt = $today->copy()->subMonthsNoOverflow(mt_rand(1, 5));
        foreach (JadualCaj::query()->where('kontrak_id', $k->id)->where('kod_caj', 'sewa')->whereDate('tarikh_caj', '<=', $today)->orderBy('tarikh_caj')->get() as $caj) {
            $jadual->issue($caj, $officer?->id);
            $amount = Wang::sen($caj->amaun);
            $when = $caj->tarikh_caj->copy()->addDays(mt_rand(0, 12));
            if ($behaviour > 85 && $caj->tarikh_caj->gte($stopAt)) {
                continue; // arrears
            }
            if ($behaviour > 70 && mt_rand(0, 3) === 0) {
                $amount = intdiv($amount, 2);
                $when = $caj->tarikh_caj->copy()->addDays(mt_rand(20, 70));
            }
            if ($when->gt($today)) {
                continue;
            }
            $payments += $this->pay($k, $bayaran, $amount, $when, ['fpx', 'kad', 'kaunter', 'eft', 'fpx', 'pindahan_bank'][mt_rand(0, 5)]);
        }

        if ($n % 20 === 2) {
            $service->transition($k, 'ditamatkan', $officer?->id, 'Data sintetik — ditamatkan atas permintaan penyewa');
            $k->forceFill(['tarikh_penamatan' => $today->format('Y-m-d'), 'sebab_penamatan' => 'Permintaan penyewa (sintetik)'])->save();
            $jadual->cancelFrom($k, $today);
            $service->releasePremis($k, $officer?->id, 'Kontrak sintetik ditamatkan');
        }

        return $payments;
    }

    private function pay(Kontrak $k, BayaranService $service, int $amount, Carbon $when, string $channel): int
    {
        if ($amount <= 0) {
            return 0;
        }
        $tx = null;
        if (in_array($channel, ['fpx', 'kad'], true)) {
            $tx = TransaksiGateway::query()->create([
                'no_rujukan' => Penomboran::next('gateway'), 'kontrak_id' => $k->id, 'saluran' => $channel, 'amaun' => Wang::dec($amount),
                'status' => 'menunggu', 'masa_mula' => $when->copy()->setTime(10, 0), 'rujukan_gateway' => 'SINTETIK-'.Str::upper(Str::random(10)), 'sumber' => 'portal',
            ]);
        }
        $b = Bayaran::query()->create([
            'no_bayaran' => Penomboran::next('bayaran'), 'kontrak_id' => $k->id, 'saluran' => $channel, 'amaun' => Wang::dec($amount),
            'tarikh_bayaran' => $when->format('Y-m-d'), 'rujukan_luar' => ($tx !== null ? $tx->rujukan_gateway : null) ?? ($channel === 'kaunter' ? null : 'REF-SINTETIK-'.mt_rand(100000, 999999)),
            'status' => 'menunggu_pengesahan', 'transaksi_gateway_id' => $tx?->id, 'nama_pembayar' => $k->penyewa,
            'tarikh_pengesahan' => $when->copy()->setTime(16, 0), 'sintetik' => true,
        ]);
        $tx?->forceFill(['status' => 'berjaya', 'masa_pengesahan' => $when->copy()->setTime(10, 5), 'kod_respons' => '00', 'disahkan_melalui' => 'panggilan_balik'])->save();
        $service->settle($b, null, false); // no PDF / e-mail / GRP push for synthetic history

        return 1;
    }

    private function tenant(int $n): Penyewa
    {
        $company = $n % 3 === 0;
        $name = $company
            ? self::SYARIKAT[$n % count(self::SYARIKAT)].' '.self::SYARIKAT[intdiv($n, 7) % count(self::SYARIKAT)].' Enterprise (Sintetik) '.str_pad((string) $n, 3, '0', STR_PAD_LEFT)
            : self::NAMA[$n % count(self::NAMA)].' bin/binti Sintetik '.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
        $suffix = str_pad((string) (Penyewa::query()->where('sintetik', true)->count() + 1), 4, '0', STR_PAD_LEFT);

        return Penyewa::query()->create([
            'jenis_entiti' => $company ? 'syarikat' : 'individu', 'nama' => $name,
            'no_pengenalan' => $company ? null : '990000-99-'.$suffix,
            'no_pendaftaran_syarikat' => $company ? 'SINTETIK-'.$suffix : null,
            'alamat' => 'No. '.($n + 1).', Jalan Sintetik, Selangor', 'poskod' => '40000', 'telefon' => '000-000'.$suffix,
            'emel' => 'penyewa'.$suffix.'@contoh.invalid', 'klasifikasi' => 'sulit', 'sintetik' => true,
        ]);
    }

    private function application(PremisAset $premis): void
    {
        PermohonanSewaan::query()->create([
            'no_permohonan' => Penomboran::next('permohonan'), 'sumber' => mt_rand(0, 1) ? 'portal' : 'kaunter',
            'rujukan_portal' => 'PWS-SINTETIK-'.Str::upper(Str::random(8)), 'premis_id' => $premis->id,
            'maklumat_pemohon' => ['jenis_entiti' => 'individu', 'nama' => 'Pemohon Sintetik '.$premis->id, 'no_pengenalan' => '990000-98-'.str_pad((string) $premis->id, 4, '0', STR_PAD_LEFT), 'emel' => 'pemohon'.$premis->id.'@contoh.invalid'],
            'tarikh_mula_dicadang' => Carbon::today()->addMonth()->startOfMonth()->format('Y-m-d'), 'tempoh_bulan' => 24,
            'kadar_dicadang' => $premis->kadar_tawaran, 'tujuan' => 'Permohonan sintetik', 'status' => 'diterima', 'kod_daerah' => $premis->kod_daerah, 'sintetik' => true,
        ]);
    }

    private function seedPolicies(?User $officer): void
    {
        if (DasarAgihanManfaat::query()->exists()) {
            return;
        }
        $rates = ['premis' => ['90.0000', '60.0000'], 'premis_perniagaan' => ['85.0000', '55.0000'], 'premis_kediaman' => ['95.0000', '65.0000'], 'tanah' => ['80.0000', '50.0000'], 'bazar' => ['90.0000', '60.0000']];
        foreach ($rates as $kategori => [$wakaf, $belian]) {
            foreach (['wakaf' => $wakaf, 'belian' => $belian] as $status => $rate) {
                DasarAgihanManfaat::query()->create([
                    'versi_dasar' => 1, 'kategori_hartanah' => $kategori, 'status_perolehan' => $status, 'kadar_agihan' => $rate,
                    'penerima_manfaat' => $status === 'wakaf'
                        ? [['kod' => 'mauquf_alaih', 'nama' => 'Penerima manfaat wakaf (mauquf alaih)', 'peratus' => '70.0000'], ['kod' => 'pembangunan_wakaf', 'nama' => 'Tabung pembangunan wakaf', 'peratus' => '30.0000']]
                        : [['kod' => 'baitulmal', 'nama' => 'Baitulmal MAIS', 'peratus' => '60.0000'], ['kod' => 'penyelenggaraan', 'nama' => 'Tabung penyelenggaraan hartanah', 'peratus' => '40.0000']],
                    'tarikh_mula_kuasa' => '2020-01-01', 'rujukan_kelulusan' => 'SINTETIK/DAM/2020/1', 'status' => 'aktif', 'id_pencipta' => $officer?->id,
                ]);
            }
        }
    }
}
