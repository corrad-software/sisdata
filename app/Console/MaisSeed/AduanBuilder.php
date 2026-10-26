<?php

namespace App\Console\MaisSeed;

use App\Models\User;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\ResolusiAduan;
use App\Modules\Adu\Services\AduanService;
use App\Modules\Adu\Services\Kronologi;
use App\Modules\Adu\Services\SlaAduan;
use App\Modules\Adu\Support\Lalai;
use App\Modules\Adu\Support\LotSpatial;
use App\Modules\Pro\Models\LotTanah;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * MOD-ADU through AduanService::terima (triage, SLA, timeline) with the
 * clock moved back, like `adu:sample`: complaints that fit the property
 * type (tenant issues at shops, damage at halls, dumping at vacant land…),
 * progressed to a mix of statuses. Reporters are SYNTHETIC or anonymous.
 */
class AduanBuilder
{
    private const TEKS = [
        'ISU_PENYEWA' => ['Penyewa premis menyewakan semula ruang tanpa kebenaran.', 'Premis sewaan tidak diselenggara dan menjejaskan kebersihan.', 'Penyewa menggunakan premis bukan untuk tujuan asal.'],
        'TUNGGAKAN' => ['Penyewa dilaporkan tidak membayar sewa beberapa bulan.'],
        'KEROSAKAN_ASET' => ['Bumbung dewan bocor ketika hujan lebat.', 'Pintu pagar rosak dan tidak boleh dikunci.', 'Lampu kawasan letak kereta tidak berfungsi.'],
        'PEMBUANGAN_SAMPAH' => ['Longgokan sampah dan sisa binaan di kawasan lapang lot.', 'Sampah domestik dibuang di hujung lot setiap malam.'],
        'KESELAMATAN' => ['Kawasan semak di belakang bangunan menjadi tempat berkumpul pada waktu malam.', 'Struktur lama berisiko runtuh kepada orang awam.'],
        'PENCEROBOHAN' => ['Individu dilihat bercucuk tanam di sebahagian tanah ini tanpa izin.', 'Pondok sementara dibina di sempadan lot.'],
        'BINAAN_HARAM' => ['Gerai dibina tanpa kebenaran di tepi jalan hadapan lot.'],
        'PERTIKAIAN_SEMPADAN' => ['Pagar jiran didakwa melepasi batu sempadan lot.'],
        'LAIN' => ['Pertanyaan mengenai status pemilikan dan sewaan tanah ini.', 'Pokok tumbang menghalang laluan masuk.'],
    ];

    public function __construct(private AduanService $service, private SlaAduan $sla, private Kronologi $kronologi, private LotSpatial $spatial) {}

    /** @param array<string, mixed> $h */
    public function bina(LotTanah $lot, array $h, Rawak $r, Konteks $k): void
    {
        $komersial = in_array($h['jenis'], Profil::KOMERSIAL, true);
        $n = $r->chance($komersial ? 60 : 30) ? $r->int(1, $komersial ? 3 : 2) : 0;
        for ($i = 0; $i < $n; $i++) {
            $kategori = $komersial
                ? $r->weighted(['ISU_PENYEWA' => 30, 'TUNGGAKAN' => 10, 'KEROSAKAN_ASET' => 20, 'PEMBUANGAN_SAMPAH' => 15, 'KESELAMATAN' => 10, 'LAIN' => 15])
                : $r->weighted(['KEROSAKAN_ASET' => 30, 'PEMBUANGAN_SAMPAH' => 20, 'KESELAMATAN' => 15, 'PERTIKAIAN_SEMPADAN' => 10, 'BINAAN_HARAM' => 10, 'LAIN' => 15]);
            $this->satu($lot, $kategori, $r, $k);
        }
    }

    private function satu(LotTanah $lot, string $kategori, Rawak $r, Konteks $k): void
    {
        $realNow = CarbonImmutable::now();
        $received = $realNow->subDays($r->int(0, 420))->setTime($r->int(8, 17), $r->int(0, 59));
        $age = (int) $received->diffInDays($realNow);
        $saluran = $r->pick(['dalaman', 'portal', 'portal', 'kaunter', 'emel', 'telefon', 'lapangan', 'sistem_aduan_pusat']);
        $anon = $r->chance(30);
        $point = $this->spatial->pusatLot($lot->id);
        Carbon::setTestNow($received);
        try {
            $text = self::TEKS[$kategori] ?? self::TEKS['LAIN'];
            $aduan = $this->service->terima([
                'kategori' => $kategori,
                'keterangan' => '[SINTETIK] '.$r->pick($text),
                'lot_id' => $lot->id, 'lat' => $point['lat'] ?? null, 'lng' => $point['lng'] ?? null, 'kod_daerah' => $lot->kod_daerah,
                'tahap_keutamaan' => $r->pick(['rendah', 'sederhana', 'sederhana', 'tinggi', 'kritikal']),
                'sintetik' => true,
            ], $saluran, in_array($saluran, ['portal', 'sistem_aduan_pusat'], true) ? null : $k->pegawai,
                $saluran === 'sistem_aduan_pusat' ? 'SAP-SINTETIK-MAIS-'.$received->format('YmdHis').'-'.$r->int(100, 999) : null,
                $anon ? null : ['nama' => 'Pengadu Sintetik '.$r->int(1000, 9999), 'no_telefon' => '000-000 '.$r->int(1000, 9999), 'emel' => 'pengadu'.$r->int(1000, 99999).'@contoh.invalid', 'alamat' => 'Alamat SINTETIK, Selangor'],
                $anon);
            $k->tambah('aduan');
            $this->kemajuan($aduan, $received, $age, $r, $k);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function kemajuan(Aduan $aduan, CarbonImmutable $received, int $age, Rawak $r, Konteks $k): void
    {
        $target = $r->weighted(['baharu' => 12, 'ditugaskan' => 15, 'dalam_siasatan' => 18, 'dirujuk' => 8, 'ditutup' => 45, 'pendua' => 2]);
        if ($age < 3 && $target === 'ditutup') {
            $target = 'dalam_siasatan';
        }
        if ($target === 'baharu' || ($aduan->status_bukan_tanah_mais && $target !== 'ditutup')) {
            return;
        }
        $officer = $r->pick($k->lapangan);
        $step = fn (int $days) => Carbon::setTestNow($received->addDays(min($days, max(0, $age))));
        $step(1);
        if ($aduan->id_pegawai === null) {
            $aduan->update(['id_pegawai' => $officer->id, 'status' => 'ditugaskan']);
            $this->kronologi->rekod($aduan, 'tugas', 'Ditugaskan kepada '.$officer->name.' (SINTETIK)', $officer, 'baharu', 'ditugaskan');
        }
        if ($target === 'ditugaskan') {
            return;
        }
        $step(2);
        $aduan->update(['status' => 'dalam_siasatan']);
        $this->sla->respons($aduan);
        $this->kronologi->rekod($aduan, 'mula_siasatan', null, $officer, 'ditugaskan', 'dalam_siasatan');
        $step($r->int(3, 10));
        $this->kronologi->rekod($aduan, 'tindakan', 'Lawatan tapak dan pengesahan maklumat (SINTETIK).', $officer);
        if ($target === 'dalam_siasatan') {
            return;
        }
        if ($target === 'dirujuk') {
            $jenis = Lalai::BAHAGIAN_KATEGORI[$aduan->kategori] ?? 'penguatkuasaan';
            $aduan->update(['status' => 'dirujuk', 'bahagian_dirujuk' => $jenis]);
            $this->kronologi->rekod($aduan, 'rujuk', 'Dirujuk kepada '.Lalai::JENIS_RUJUKAN[$jenis].' (SINTETIK)', $officer, 'dalam_siasatan', 'dirujuk');

            return;
        }
        if ($target === 'pendua') {
            $aduan->update(['status' => 'pendua', 'tarikh_tutup' => now()]);
            $this->sla->selesai($aduan);
            $this->kronologi->rekod($aduan, 'pendua', 'Ditanda pendua (SINTETIK)', $officer, 'dalam_siasatan', 'pendua');

            return;
        }
        $step($r->int(5, 60));
        $approver = collect($k->pelulus)->first(fn (User $u) => $u->id !== $officer->id) ?? $officer;
        ResolusiAduan::query()->create([
            'aduan_id' => $aduan->id, 'no_rujukan_aduan' => $aduan->no_rujukan_aduan, 'kategori_resolusi' => $r->pick(['selesai_tindakan', 'selesai_penguatkuasaan', 'tiada_asas', 'dirujuk_agensi']),
            'keterangan_resolusi' => 'Isu diselesaikan (SINTETIK).', 'justifikasi' => 'Disahkan melalui lawatan tapak (SINTETIK).',
            'id_pegawai_penyelesai' => $officer->id, 'id_penyelia_pelulus' => $approver->id, 'status' => 'lulus', 'tarikh_tutup' => now(),
            'status_makluman_pengadu' => $aduan->status_tanpa_nama ? 'tidak_berkenaan' : 'dihantar',
        ]);
        $aduan->update(['status' => 'ditutup', 'tarikh_tutup' => now(), 'id_pegawai' => $aduan->id_pegawai ?? $officer->id]);
        $this->sla->selesai($aduan);
        $this->kronologi->rekod($aduan, 'penutupan_diluluskan', 'Diluluskan penyelia (SINTETIK)', $approver, 'dalam_siasatan', 'ditutup');
        $k->tambah('aduan_ditutup');
    }
}
