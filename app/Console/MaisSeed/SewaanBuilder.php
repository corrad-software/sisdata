<?php

namespace App\Console\MaisSeed;

use App\Modules\Pjk\Models\Bayaran;
use App\Modules\Pjk\Models\JadualCaj;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\Penyewa;
use App\Modules\Pjk\Models\PermohonanSewaan;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\Models\TransaksiGateway;
use App\Modules\Pjk\Services\BayaranService;
use App\Modules\Pjk\Services\JadualCajService;
use App\Modules\Pjk\Services\KontrakService;
use App\Modules\Pjk\Services\PenyewaService;
use App\Modules\Pjk\Services\PremisService;
use App\Modules\Pjk\Support\Penomboran;
use App\Modules\Pjk\Support\Wang;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Ref\Services\SyntheticPeople;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Throwable;

/**
 * MOD-PJK through its services: premises on the lot, SYNTHETIC tenants
 * (Malaysian-style names, "(Sintetik)" suffix, impossible IC place code 00),
 * contracts in every state — running, expiring soon, terminated, draft,
 * submitted to the real `pjk.kelulusan_kontrak` WFE flow — multi-year
 * charge schedules, deposits and payments with realistic arrears patterns.
 * Vacant premises stay `papar_portal` so they appear in the public catalogue
 * (Portal Hartanah Wakaf Selangor / MOD-PWS).
 */
class SewaanBuilder
{
    private const NIAGA = [
        'premis_perniagaan' => ['Restoran', 'Kedai Runcit', 'Farmasi', 'Butik Muslimah', 'Kedai Buku Islam', 'Klinik Pergigian', 'Pusat Tuisyen', 'Kedai Gunting', 'Dobi', 'Kedai Telefon', 'Kafe', 'Agensi Pelancongan Umrah'],
        'premis' => ['Pusat Latihan', 'Pejabat Urusan', 'Pusat Dialisis', 'Klinik', 'Pusat Jagaan', 'Akademi Tahfiz', 'Pejabat Koperasi'],
        'bazar' => ['Gerai Makanan', 'Gerai Pakaian', 'Gerai Kuih', 'Gerai Minuman'],
        'tanah' => ['Ladang', 'Tapak Semaian', 'Tapak Letak Kenderaan', 'Dusun'],
    ];

    private const NAMA_SYARIKAT = ['Barakah', 'Nur Iman', 'Seri Kenanga', 'Mutiara Timur', 'Al-Falah', 'Darul Makmur', 'Sinar Bestari', 'Teratai Emas', 'Cahaya Ilham', 'Delima Jaya', 'Pelangi Ceria', 'Kasturi', 'Lestari Murni', 'Firdaus', 'Ar-Rayyan', 'Embun Pagi', 'Bayu Selatan', 'Sri Mawar'];

    private const AKHIRAN = ['Enterprise', 'Trading', 'Resources', 'Sdn. Bhd.', 'Services', 'Holdings Sdn. Bhd.', 'Venture'];

    public function __construct(
        private PremisService $premis,
        private PenyewaService $penyewa,
        private KontrakService $kontrak,
        private JadualCajService $jadual,
        private BayaranService $bayaran,
    ) {}

    /**
     * @param  array<string, mixed>  $h
     * @return list<PremisAset>
     */
    public function bina(LotTanah $lot, array $h, Rawak $r, Konteks $k): array
    {
        [, , , , , $plans] = Profil::untuk($h['jenis']);
        $komersial = in_array($h['jenis'], Profil::KOMERSIAL, true);
        if (! $komersial && ! $r->chance(35)) {
            return [];
        }
        $out = [];
        $n = 0;
        foreach ($plans as [$kategori, $jenisAset, $min, $max, $lo, $hi]) {
            $units = $r->int($min, $max);
            for ($u = 1; $u <= $units; $u++) {
                $n++;
                $out[] = $this->satu($lot, $h, $r, $k, $kategori, $jenisAset, $lo, $hi, $n, $units > 1 ? $u : null);
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $h */
    private function satu(LotTanah $lot, array $h, Rawak $r, Konteks $k, string $kategori, string $jenisAset, int $lo, int $hi, int $n, ?int $unit): PremisAset
    {
        $label = match ($kategori) {
            'premis_perniagaan' => 'Unit Kedai',
            'premis_kediaman' => 'Rumah',
            'bazar' => 'Gerai',
            'tanah' => 'Tanah',
            default => $jenisAset === 'bangunan' && $h['jenis'] === 'hotel' ? 'Bangunan Hotel' : 'Ruang',
        };
        $rate = intdiv($r->int($lo, $hi), 10) * 10;
        // Listed for rent in a public PWS / MAIS document: the first premise is vacant at the public rate.
        $awam = $n === 1 && ($h['status_sewa'] ?? '') === 'untuk_disewa' ? self::kadarAwam($h) : null;
        if ($awam !== null) {
            $rate = (int) round($awam);
        }
        $premis = $this->premis->create([
            'lot_id' => $lot->id,
            'nama_premis' => Str::limit(trim($label.' '.($unit !== null ? sprintf('%02d', $unit) : '').' — '.$h['nama']), 200, ''),
            'jenis_aset' => $jenisAset, 'kategori_hartanah' => $kategori,
            'saluran_pemerolehan' => $h['bahagian'] === 'wakaf' ? 'wakaf' : 'belian',
            'keluasan' => match ($kategori) {
                'premis_perniagaan' => $r->int(110, 260), 'premis_kediaman' => $r->int(70, 160), 'bazar' => $r->int(6, 20), 'tanah' => round((float) $lot->keluasanM2() * 0.9, 2),
                default => $h['jenis'] === 'hotel' ? $r->int(3000, 9000) : $r->int(150, 1200),
            },
            'alamat' => $h['alamat'] ?? null,
            'kadar_tawaran' => number_format($rate, 2, '.', ''),
            'papar_portal' => true,
            'keterangan_awam' => Str::limit($label.' di '.$h['nama'].'. '.($awam !== null
                ? 'Disenaraikan untuk sewaan dalam dokumen awam PWS/MAIS (kadar sumber awam); keluasan premis ialah contoh SINTETIK.'
                : 'Maklumat sewaan (kadar, keluasan) ialah contoh SINTETIK.'), 2000, ''),
        ], $k->pegawai);
        $premis->forceFill(['sintetik' => true])->save();
        $k->tambah('premis_aset');

        $profile = $r->weighted(['aktif' => 62, 'hampir_tamat' => 8, 'kosong' => 9, 'draf' => 4, 'kelulusan' => 9, 'ditamatkan' => 4, 'tamat' => 4]);
        if ($h['jenis'] === 'hotel' && $kategori === 'premis') {
            $profile = 'aktif'; // the operator lease of a waqf hotel
        }
        if ($awam !== null) {
            $profile = 'kosong';
        }
        if ($profile === 'kosong') {
            if ($r->chance(60)) {
                $this->permohonan($premis, $r, $k);
            }

            return $premis;
        }
        $this->kontrak($premis, $h, $r, $k, $rate, $profile, $n);

        return $premis->refresh();
    }

    /** @param array<string, mixed> $h */
    private function kontrak(PremisAset $premis, array $h, Rawak $r, Konteks $k, int $rate, string $profile, int $n): void
    {
        $today = Carbon::today();
        $months = $premis->kategori_hartanah === 'tanah' ? $r->pick([36, 60, 120]) : ($h['jenis'] === 'hotel' && $premis->kategori_hartanah === 'premis' ? 120 : $r->pick([12, 24, 24, 36, 36, 60]));
        $start = match ($profile) {
            'draf', 'kelulusan' => $today->copy()->addDays($r->int(10, 60))->startOfMonth()->addMonth(),
            'hampir_tamat' => $today->copy()->subMonthsNoOverflow($months)->addDays($r->int(15, 75))->startOfMonth(),
            'tamat' => $today->copy()->subMonthsNoOverflow($months + $r->int(1, 8))->startOfMonth(),
            default => $today->copy()->subMonthsNoOverflow($r->int(2, max(3, $months - 3)))->startOfMonth(),
        };
        $end = $start->copy()->addMonthsNoOverflow($months)->subDay();
        $tenant = $this->penyewa($premis, $r, $k, $n, $h);

        $kontrak = $this->kontrak->create([
            'premis_id' => $premis->id, 'penyewa_id' => $tenant->id,
            'jenis_perjanjian' => $premis->kategori_hartanah === 'tanah' ? 'pajakan' : ($premis->kategori_hartanah === 'bazar' ? 'lesen' : 'sewaan'),
            'tarikh_mula' => $start->format('Y-m-d'), 'tarikh_tamat' => $end->format('Y-m-d'),
            'kadar' => number_format($rate, 2, '.', ''), 'deposit' => number_format($rate * ($premis->kategori_hartanah === 'bazar' ? 1 : 2), 2, '.', ''),
            'kitaran_caj' => $premis->kategori_hartanah === 'tanah' && $r->chance(50) ? 'tahunan' : 'bulanan',
            'terma' => 'Terma contoh SINTETIK: premis digunakan bagi tujuan '.Str::lower($tenant->nama).' sahaja; patuh syariah; tiada sewaan semula tanpa kebenaran MAIS.',
            'sumber' => $r->chance(30) ? 'portal' : 'kaunter',
        ], $k->pegawai);
        $kontrak->forceFill(['sintetik' => true])->save();
        $k->tambah('kontrak_pajakan');

        if ($profile === 'draf') {
            return;
        }
        if ($profile === 'kelulusan') {
            try {
                $this->kontrak->submit($kontrak, $k->pegawai);
                $k->tambah('wfe_pjk');
            } catch (Throwable $e) {
                $k->amaran($h['kunci'].': kontrak tidak dihantar ke WFE ('.$e->getMessage().')');
            }

            return;
        }

        $this->kontrak->activate($kontrak, $k->pegawai->id, 'Data SINTETIK — diaktifkan (rekod sejarah, tiada aliran WFE)', false, false, $start->copy());
        $behaviour = $r->int(1, 100); // ≤68 on time, ≤86 sometimes partial / late, else stops paying
        $deposit = JadualCaj::query()->where('kontrak_id', $kontrak->id)->where('kod_caj', 'deposit')->first();
        if ($deposit !== null && $behaviour <= 95) {
            $this->bayar($kontrak, Wang::sen($deposit->amaun), $start->copy(), 'kaunter', $r, $k);
        }
        $stopAt = $today->copy()->subMonthsNoOverflow($r->int(2, 7));
        foreach (JadualCaj::query()->where('kontrak_id', $kontrak->id)->where('kod_caj', 'sewa')->whereDate('tarikh_caj', '<=', $today)->orderBy('tarikh_caj')->get() as $caj) {
            $this->jadual->issue($caj, $k->pegawai->id);
            $amount = Wang::sen($caj->amaun);
            $when = $caj->tarikh_caj->copy()->addDays($r->int(0, 12));
            if ($behaviour > 86 && $caj->tarikh_caj->gte($stopAt)) {
                continue; // arrears
            }
            if ($behaviour > 68 && $r->chance(25)) {
                $amount = intdiv($amount, 2);
                $when = $caj->tarikh_caj->copy()->addDays($r->int(20, 70));
            }
            if ($when->gt($today)) {
                continue;
            }
            $this->bayar($kontrak, $amount, $when, $r->pick(['fpx', 'kad', 'kaunter', 'eft', 'fpx', 'pindahan_bank']), $r, $k);
        }

        if ($profile === 'ditamatkan') {
            $this->kontrak->transition($kontrak, 'ditamatkan', $k->pegawai->id, 'Data SINTETIK — ditamatkan atas permintaan penyewa');
            $kontrak->forceFill(['tarikh_penamatan' => $today->format('Y-m-d'), 'sebab_penamatan' => 'Permintaan penyewa (SINTETIK)'])->save();
            $this->jadual->cancelFrom($kontrak, $today);
            $this->kontrak->releasePremis($kontrak, $k->pegawai->id, 'Kontrak SINTETIK ditamatkan');
        }
    }

    private function bayar(Kontrak $k0, int $amount, Carbon $when, string $channel, Rawak $r, Konteks $k): void
    {
        if ($amount <= 0) {
            return;
        }
        $tx = null;
        if (in_array($channel, ['fpx', 'kad'], true)) {
            $tx = TransaksiGateway::query()->create([
                'no_rujukan' => Penomboran::next('gateway'), 'kontrak_id' => $k0->id, 'saluran' => $channel, 'amaun' => Wang::dec($amount),
                'status' => 'menunggu', 'masa_mula' => $when->copy()->setTime(10, 0), 'rujukan_gateway' => 'SINTETIK-'.strtoupper(substr(md5($k0->id.$when->format('Ymd').$amount), 0, 10)), 'sumber' => 'portal',
            ]);
        }
        $b = Bayaran::query()->create([
            'no_bayaran' => Penomboran::next('bayaran'), 'kontrak_id' => $k0->id, 'saluran' => $channel, 'amaun' => Wang::dec($amount),
            'tarikh_bayaran' => $when->format('Y-m-d'), 'rujukan_luar' => ($tx !== null ? $tx->rujukan_gateway : null) ?? ($channel === 'kaunter' ? null : 'REF-SINTETIK-'.$r->int(100000, 999999)),
            'status' => 'menunggu_pengesahan', 'transaksi_gateway_id' => $tx?->id, 'nama_pembayar' => $k0->penyewa,
            'tarikh_pengesahan' => $when->copy()->setTime(16, 0), 'sintetik' => true,
        ]);
        $tx?->forceFill(['status' => 'berjaya', 'masa_pengesahan' => $when->copy()->setTime(10, 5), 'kod_respons' => '00', 'disahkan_melalui' => 'panggilan_balik'])->save();
        $this->bayaran->settle($b, null, false); // no PDF / e-mail / GRP push for synthetic history
        $k->tambah('bayaran');
    }

    /** @param array<string, mixed> $h */
    private function penyewa(PremisAset $premis, Rawak $r, Konteks $k, int $n, array $h): Penyewa
    {
        $seq = (int) DB::table('penyewa')->where('sintetik', true)->count() + 1;
        $suffix = str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
        $hotel = $h['jenis'] === 'hotel' && $premis->kategori_hartanah === 'premis';
        $company = $hotel || $premis->kategori_hartanah === 'premis' || ($premis->kategori_hartanah !== 'premis_kediaman' && $r->chance(55));
        $business = $hotel ? 'Hospitaliti' : $r->pick(self::NIAGA[$premis->kategori_hartanah] ?? self::NIAGA['premis']);
        if ($company) {
            $name = $business.' '.$r->pick(self::NAMA_SYARIKAT).' '.($hotel ? 'Sdn. Bhd.' : $r->pick(self::AKHIRAN)).' (Sintetik)';
            $data = ['jenis_entiti' => 'syarikat', 'nama' => $name, 'no_pendaftaran_syarikat' => 'SINTETIK-'.$suffix];
        } else {
            $person = (new SyntheticPeople(new Randomizer(new Mt19937(crc32($h['kunci'].':'.$n) & 0x7FFFFFFF))))->person(1960, 2000);
            $data = ['jenis_entiti' => 'individu', 'nama' => $person['nama'].' (Sintetik)', 'no_pengenalan' => $person['no_pengenalan']];
        }
        try {
            $tenant = $this->penyewa->create($data + [
                'alamat' => 'No. '.$r->int(1, 99).', Jalan Contoh '.$r->int(1, 30).' (alamat SINTETIK), Selangor', 'poskod' => (string) $r->int(40000, 48999),
                'telefon' => '000-000 '.substr($suffix, -4), 'emel' => 'penyewa'.$suffix.'@contoh.invalid',
            ], $k->pegawai);
        } catch (Throwable) {
            $tenant = $this->penyewa->create(['jenis_entiti' => 'syarikat', 'nama' => 'Penyewa Sintetik '.$suffix, 'no_pendaftaran_syarikat' => 'SINTETIK-X'.$suffix], $k->pegawai);
        }
        $tenant->forceFill(['sintetik' => true])->save();
        $k->tambah('penyewa');

        return $tenant;
    }

    private function permohonan(PremisAset $premis, Rawak $r, Konteks $k): void
    {
        PermohonanSewaan::query()->create([
            'no_permohonan' => Penomboran::next('permohonan'), 'sumber' => $r->chance(60) ? 'portal' : 'kaunter',
            'rujukan_portal' => 'PWS-SINTETIK-'.strtoupper(substr(md5('mohon'.$premis->id), 0, 10)), 'premis_id' => $premis->id,
            'maklumat_pemohon' => ['jenis_entiti' => 'syarikat', 'nama' => 'Pemohon Sintetik '.$premis->id, 'no_pendaftaran_syarikat' => 'SINTETIK-P'.$premis->id, 'emel' => 'pemohon'.$premis->id.'@contoh.invalid'],
            'tarikh_mula_dicadang' => Carbon::today()->addMonth()->startOfMonth()->format('Y-m-d'), 'tempoh_bulan' => $r->pick([12, 24, 36]),
            'kadar_dicadang' => $premis->kadar_tawaran, 'tujuan' => 'Permohonan sewaan contoh SINTETIK', 'status' => $r->pick(['diterima', 'dalam_semakan']),
            'kod_daerah' => $premis->kod_daerah, 'sintetik' => true,
        ]);
        $k->tambah('permohonan_sewaan');
    }

    /** First RM amount of a public rent fact ("RM1,500 sebulan", "RM2,500.00 + RM3,900.00" → 1500 / 2500). @param array<string, mixed> $h */
    private static function kadarAwam(array $h): ?float
    {
        foreach ((array) ($h['fakta'] ?? []) as $f) {
            if (in_array($f['medan'] ?? '', ['kadar_sewa', 'kadar_sewa_keseluruhan'], true) && preg_match('/RM\s?([\d,]+(?:\.\d{2})?)/', (string) $f['nilai'], $m)) {
                $v = (float) str_replace(',', '', $m[1]);

                return $v > 0 ? $v : null;
            }
        }

        return null;
    }
}
