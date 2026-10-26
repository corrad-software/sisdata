<?php

namespace App\Console\MaisSeed;

use App\Modules\Kes\Models\PanelPeguam;
use App\Modules\Kes\Models\Prosiding;
use App\Modules\Kes\Services\KeputusanService;
use App\Modules\Kes\Services\KesService;
use App\Modules\Kes\Services\KosService;
use App\Modules\Kes\Services\PanelService;
use App\Modules\Kes\Services\ProsidingService;
use App\Modules\Kes\Services\StatusLot;
use App\Modules\Pro\Models\LotTanah;
use Illuminate\Support\Carbon;

/**
 * MOD-KES through KesService & co. (always Rahsia), like `kes:sample`: a
 * few SYNTHETIC cases on suitable properties — vacant possession against a
 * defaulting tenant, encroachment, waqf status — with proceedings, costs,
 * panel counsel and decisions. Party names are synthetic; no real case.
 */
class KesBuilder
{
    private const FIRMA = ['Tetuan Contoh Amanah & Rakan (Sintetik)', 'Tetuan Sampel Hidayah Legal (Sintetik)', 'Tetuan Rekaan Ihsan & Co (Sintetik)'];

    public function __construct(private KesService $kes, private ProsidingService $prosiding, private KosService $kos, private KeputusanService $keputusan, private PanelService $panel, private StatusLot $statusLot) {}

    /** @param array<string, mixed> $h */
    public function bina(LotTanah $lot, array $h, Rawak $r, Konteks $k): void
    {
        $jenis = ! empty($h['_ceroboh']) ? 'pencerobohan' : (in_array($h['jenis'], Profil::KOMERSIAL, true) ? 'tuntutan_milikan' : ($h['bahagian'] === 'wakaf' ? 'wakaf' : 'sengketa_pemilikan'));
        [$mahkamah, $peringkat, $prefix] = match ($jenis) {
            'wakaf' => ['Mahkamah Tinggi Syariah Shah Alam', 'syariah_tinggi', '10100'],
            'tuntutan_milikan' => ['Mahkamah Sesyen Shah Alam', 'sesyen', 'BA-B52NCvC'],
            default => ['Mahkamah Tinggi Shah Alam', 'tinggi', 'BA-22NCvC'],
        };
        $tajuk = [
            'pencerobohan' => 'MAIS lwn Penceroboh (SINTETIK) — pencerobohan dan binaan tanpa izin di '.$h['nama'],
            'tuntutan_milikan' => 'MAIS lwn Penyewa (SINTETIK) — tuntutan milikan kosong dan tunggakan sewa, '.$h['nama'],
            'wakaf' => 'Permohonan pengesahan status wakaf — '.$h['nama'].' (SINTETIK)',
            'sengketa_pemilikan' => 'Sengketa pemilikan sebahagian lot — '.$h['nama'].' (SINTETIK)',
        ][$jenis];
        $status = $r->weighted(['aktif' => 25, 'sebutan' => 20, 'dalam_bicara' => 20, 'tangguh' => 5, 'keputusan' => 10, 'selesai' => 20]);
        $filed = Carbon::now()->subDays($r->int(90, 900));

        $case = $this->kes->daftar([
            'no_kes' => sprintf('%s-%d-%02d/%d', $prefix, $r->int(100, 999), $r->int(1, 12), (int) $filed->format('Y')),
            'tajuk' => mb_substr($tajuk, 0, 250), 'jenis' => $jenis, 'mahkamah' => $mahkamah, 'peringkat_mahkamah' => $peringkat, 'tarikh_pemfailan' => $filed->toDateString(),
            'ringkasan_tuntutan' => 'SINTETIK — kes rekaan untuk demonstrasi; tiada kaitan dengan mana-mana kes sebenar melibatkan hartanah ini.',
            'nilai_tuntutan' => number_format($r->int(20, 1500) * 1000, 2, '.', ''),
            'peranan_mais' => $jenis === 'sengketa_pemilikan' ? 'defendan' : 'plaintif',
            'lot_ids' => [$lot->id], 'id_pegawai_bertanggungjawab' => $k->pegawai->id,
            'pihak' => [
                ['nama_pihak' => 'Majlis Agama Islam Selangor', 'jenis_pihak' => 'mais', 'peranan' => $jenis === 'sengketa_pemilikan' ? 'defendan' : 'plaintif', 'status_wakilan' => 'diwakili', 'adalah_mais' => true],
                ['nama_pihak' => $jenis === 'tuntutan_milikan' ? 'Penyewa Sintetik '.$r->int(100, 999).' Enterprise' : 'Pihak Lawan Sintetik '.$r->int(100, 999), 'jenis_pihak' => $jenis === 'tuntutan_milikan' ? 'syarikat' : 'individu', 'peranan' => $jenis === 'sengketa_pemilikan' ? 'plaintif' : 'defendan', 'status_wakilan' => $r->chance(70) ? 'diwakili' : 'sendiri'],
            ],
            'peguam' => [['nama_peguam' => 'Peguam Pihak Lawan (Sintetik)', 'nama_firma' => 'Tetuan Pihak Lawan & Co (Sintetik)', 'pihak_indeks' => 1]],
        ], $k->admin, 'manual', null, true);
        $k->tambah('kes_mahkamah');

        $firms = $this->firma();
        $this->panel->lantik($case, $r->pick($firms), ['kes_pihak_id' => $case->pihak()->where('adalah_mais', true)->value('id')], $k->admin, true);

        $order = ['didaftar', 'aktif', 'sebutan', 'dalam_bicara', 'tangguh', 'keputusan', 'selesai'];
        $stage = (int) array_search($status, $order, true);
        $t = $filed->copy()->addDays($r->int(20, 60))->setTime(9, 0);
        for ($p = 0, $n = $r->int(1, 2 + $stage); $p < $n && $t->isPast(); $p++) {
            $this->prosiding->rekod($case, [
                'tarikh_prosiding' => $t->toDateTimeString(), 'jenis_prosiding' => ['sebutan', 'pengurusan_kes', 'sebutan', 'bicara', 'bicara', 'pendengaran'][min($p, 5)],
                'status' => $r->chance(15) ? 'ditangguh' : 'selesai', 'hasil' => 'Prosiding berjalan (SINTETIK).',
                'arahan_mahkamah' => $r->chance(50) ? 'Pihak-pihak diarahkan memfailkan dokumen dalam tempoh 14 hari.' : null,
            ], $k->admin, true);
            $t = $t->copy()->addDays($r->int(30, 90));
        }
        for ($c = 0, $m = $r->int(1, 4); $c < $m; $c++) {
            $kat = $r->pick(['fi_peguam', 'fi_peguam', 'kos_mahkamah', 'kos_berkaitan']);
            $this->kos->tambah($case, [
                'kategori_kos' => $kat, 'amaun' => number_format($r->int(3, 300) * 100, 2, '.', ''),
                'tarikh' => $filed->copy()->addDays($r->int(0, max(1, (int) $filed->diffInDays(now()))))->toDateString(),
                'keterangan' => ['fi_peguam' => 'Fi guaman', 'kos_mahkamah' => 'Fi pemfailan / setem', 'kos_berkaitan' => 'Laporan jurukur / penilai'][$kat].' (SINTETIK)',
                'no_resit' => 'INV-S'.$r->int(10000, 99999), 'status_bayaran' => $r->chance(65) ? 'dibayar' : 'belum_dibayar',
            ], $k->admin, true);
        }
        if (in_array($status, ['keputusan', 'selesai'], true)) {
            $this->keputusan->rekod($case, ['jenis_keputusan' => $r->pick(['menang', 'menang', 'penyelesaian_luar_mahkamah', 'kalah']), 'tarikh_keputusan' => min(now()->subDays(5), $t)->toDateString(), 'ringkasan_perintah' => 'Perintah mahkamah (SINTETIK).', 'implikasi' => 'Implikasi kepada lot berkaitan (SINTETIK).'], $k->admin, true);
        } else {
            Prosiding::query()->create([
                'kes_mahkamah_id' => $case->id, 'tarikh_prosiding' => now()->startOfDay()->addDays($r->pick([1, 7, 14, 21, 30, 45]))->setTime(9, 30),
                'jenis_prosiding' => $r->pick(['sebutan', 'bicara', 'pengurusan_kes']), 'status' => 'dijadualkan',
                'catatan' => 'Dijadualkan (SINTETIK)', 'sumber' => 'manual', 'id_pegawai_rekod' => $k->admin->id, 'sintetik' => true,
            ]);
        }
        if ($status !== 'didaftar') {
            $this->kes->tukarStatus($case, $status, $k->admin, 'Data SINTETIK');
        }
        $this->kes->segarTarikhBerikutnya($case);
        $this->statusLot->segar($this->kes->lotIds($case));
        $k->tambah('kes_'.$status);
    }

    /** @return list<PanelPeguam> */
    private function firma(): array
    {
        $out = [];
        foreach (self::FIRMA as $i => $nama) {
            $p = PanelPeguam::query()->where('nama_firma', $nama)->first();
            if ($p === null) {
                $p = new PanelPeguam;
                $p->forceFill([
                    'nama_firma' => $nama, 'no_pendaftaran' => 'LF-MS'.(1000 + $i), 'pegawai_penghubung' => 'Pegawai Penghubung (Sintetik)',
                    'no_telefon' => '03-0000 000'.$i, 'emel' => 'firma.mais'.($i + 1).'@contoh.invalid',
                    'status' => 'aktif', 'tarikh_lantikan' => now()->subYears(2)->toDateString(), 'tarikh_tamat' => now()->addYears(1)->toDateString(), 'sintetik' => true,
                ])->save();
            }
            $out[] = $p;
        }

        return $out;
    }
}
