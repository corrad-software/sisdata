<?php

namespace App\Modules\Ptn\Http;

use App\Modules\Ptn\Models\GambarLawatan;
use App\Modules\Ptn\Models\IsuPemantauan;
use App\Modules\Ptn\Models\JadualPemantauan;
use App\Modules\Ptn\Models\LaporanLawatan;
use App\Modules\Ptn\Models\RekodLawatan;
use App\Modules\Ptn\Models\TindakanPembetulan;
use App\Modules\Ptn\Support\KodWarna;
use App\Modules\Ptn\Support\Lalai;
use App\Modules\Ptn\Support\Rujukan;

/** JSON shapes of MOD-PTN records (Malay labels included). */
class Present
{
    public function __construct(private Rujukan $ref, private KodWarna $warna) {}

    /**
     * @param  array<string, array{no_lot: string}>  $lots
     * @return array<string, mixed>
     */
    public function jadual(JadualPemantauan $j, array $lots = []): array
    {
        $c = $j->terbuka() ? $this->warna->untukTarikh($j->tarikh_sasaran, 'lawatan') : 'hijau';

        return [
            'id' => $j->id, 'lot_id' => $j->lot_id, 'no_lot' => $lots[$j->lot_id]['no_lot'] ?? null,
            'kod_daerah' => $j->kod_daerah, 'daerah' => $this->ref->namaDaerah($j->kod_daerah), 'kod_mukim' => $j->kod_mukim, 'mukim' => $this->ref->namaMukim($j->kod_mukim),
            'tahun' => $j->tahun, 'kekerapan' => $j->kekerapan, 'bil_dalam_tahun' => $j->bil_dalam_tahun,
            'tarikh_sasaran' => $j->tarikh_sasaran->toDateString(), 'objektif' => $j->objektif,
            'jenis_pemeriksaan' => $j->jenis_pemeriksaan, 'jenis_nama' => Lalai::JENIS_PEMERIKSAAN[$j->jenis_pemeriksaan] ?? $j->jenis_pemeriksaan,
            'keutamaan_risiko' => $j->keutamaan_risiko, 'keutamaan_nama' => Lalai::TAHAP_RISIKO[$j->keutamaan_risiko] ?? $j->keutamaan_risiko,
            'skor_keutamaan' => (float) $j->skor_keutamaan, 'faktor_keutamaan' => $j->faktor_keutamaan,
            'status_pelaksanaan' => $j->status_pelaksanaan, 'status_nama' => Lalai::STATUS_JADUAL[$j->status_pelaksanaan] ?? $j->status_pelaksanaan,
            'pegawai' => $j->pegawai ? ['id' => $j->pegawai->id, 'nama' => $j->pegawai->name] : null,
            'id_templat' => $j->id_templat, 'dijana_automatik' => $j->dijana_automatik, 'versi' => $j->versi, 'sintetik' => $j->sintetik,
            'kod_warna' => $c, 'warna' => KodWarna::papar($c),
        ];
    }

    /** @return array<string, mixed> */
    public function lawatan(RekodLawatan $l, ?string $noLot = null): array
    {
        return [
            'id' => $l->id, 'no_rujukan' => $l->no_rujukan, 'id_jadual' => $l->id_jadual, 'lot_id' => $l->lot_id, 'no_lot' => $noLot,
            'kod_daerah' => $l->kod_daerah, 'daerah' => $this->ref->namaDaerah($l->kod_daerah), 'mukim' => $this->ref->namaMukim($l->kod_mukim),
            'tarikh_lawatan' => $l->tarikh_lawatan->toDateString(), 'masa_mula' => $l->masa_mula?->toIso8601String(), 'masa_tamat' => $l->masa_tamat?->toIso8601String(),
            'pegawai' => $l->pegawai ? ['id' => $l->pegawai->id, 'nama' => $l->pegawai->name] : null,
            'keadaan_tapak' => $l->keadaan_tapak, 'keadaan_nama' => Lalai::KEADAAN_TAPAK[$l->keadaan_tapak ?? ''] ?? null,
            'ringkasan_penemuan' => $l->ringkasan_penemuan, 'isu_pencerobohan' => $l->isu_pencerobohan, 'isu_penyelenggaraan' => $l->isu_penyelenggaraan,
            'cadangan_tindakan' => $l->cadangan_tindakan,
            'lat' => $l->lat !== null ? (float) $l->lat : null, 'lng' => $l->lng !== null ? (float) $l->lng : null,
            'ketepatan_gps_m' => $l->ketepatan_gps_m !== null ? (float) $l->ketepatan_gps_m : null,
            'jarak_dari_lot_m' => $l->jarak_dari_lot_m !== null ? (float) $l->jarak_dari_lot_m : null, 'dalam_lingkungan' => $l->dalam_lingkungan,
            'status_lawatan' => $l->status_lawatan, 'status_nama' => Lalai::STATUS_LAWATAN[$l->status_lawatan] ?? $l->status_lawatan,
            'status_pengesahan' => $l->status_pengesahan, 'dikunci' => $l->dikunci(), 'sebab_konflik' => $l->sebab_konflik,
            'pengesah' => $l->pengesah ? ['id' => $l->pengesah->id, 'nama' => $l->pengesah->name] : null, 'tarikh_disahkan' => $l->tarikh_disahkan?->toIso8601String(),
            'sumber' => $l->sumber, 'disegerak_pada' => $l->disegerak_pada?->toIso8601String(), 'sintetik' => $l->sintetik,
            'data_tambahan' => $l->data_tambahan,
        ];
    }

    /** @return array<string, mixed> */
    public function gambar(GambarLawatan $g, bool $thumb = true): array
    {
        return [
            'id' => $g->id, 'id_dokumen' => $g->laluan_simpanan, 'tarikh_masa' => $g->tarikh_masa?->toIso8601String(),
            'lat' => $g->lat !== null ? (float) $g->lat : null, 'lng' => $g->lng !== null ? (float) $g->lng : null,
            'arah_kamera' => $g->arah_kamera !== null ? (float) $g->arah_kamera : null, 'ketepatan_m' => $g->ketepatan_m !== null ? (float) $g->ketepatan_m : null,
            'sumber_gambar' => $g->sumber_gambar, 'lingkungan' => $g->status_pengesahan_lingkungan,
            'jarak_dari_lot_m' => $g->jarak_dari_lot_m !== null ? (float) $g->jarak_dari_lot_m : null,
            'perlu_semakan' => $g->perlu_semakan, 'keputusan_semakan' => $g->keputusan_semakan, 'catatan_semakan' => $g->catatan_semakan,
            'kod_item' => $g->kod_item, 'jenis' => $g->jenis, 'kapsyen' => $g->kapsyen, 'nama_fail' => $g->nama_fail,
            'imej_kecil' => $thumb ? $g->imej_kecil : null,
        ];
    }

    /** @return array<string, mixed> */
    public function isu(IsuPemantauan $i, ?string $noLot = null): array
    {
        return [
            'id' => $i->id, 'no_rujukan' => $i->no_rujukan, 'id_lawatan' => $i->id_lawatan, 'lot_id' => $i->lot_id, 'no_lot' => $noLot,
            'daerah' => $this->ref->namaDaerah($i->kod_daerah), 'mukim' => $this->ref->namaMukim($i->kod_mukim), 'kod_daerah' => $i->kod_daerah,
            'kod_item' => $i->kod_item, 'kategori_isu' => $i->kategori_isu, 'kategori_nama' => Lalai::KATEGORI_ISU[$i->kategori_isu] ?? $i->kategori_isu,
            'tahap_keterukan' => $i->tahap_keterukan, 'tahap_nama' => Lalai::TAHAP_KETERUKAN[$i->tahap_keterukan] ?? $i->tahap_keterukan,
            'keterangan' => $i->keterangan, 'tarikh_dibuka' => $i->tarikh_dibuka->toDateString(), 'tarikh_ditutup' => $i->tarikh_ditutup?->toDateString(),
            'status_isu' => $i->status_isu, 'status_nama' => Lalai::STATUS_ISU[$i->status_isu] ?? $i->status_isu,
            'id_aduan_berkaitan' => $i->id_aduan_berkaitan, 'id_kes_berkaitan' => $i->id_kes_berkaitan, 'sintetik' => $i->sintetik,
        ];
    }

    /** @return array<string, mixed> */
    public function tindakan(TindakanPembetulan $t): array
    {
        $c = $this->warna->untukTarikh($t->tarikh_sasaran, 'tindakan', in_array($t->status_kemajuan, ['selesai', 'menunggu_pengesahan'], true));

        return [
            'id' => $t->id, 'id_isu' => $t->id_isu, 'langkah_tindakan' => $t->langkah_tindakan,
            'pegawai' => $t->pegawai ? ['id' => $t->pegawai->id, 'nama' => $t->pegawai->name] : null,
            'tarikh_sasaran' => $t->tarikh_sasaran->toDateString(), 'tarikh_sasaran_asal' => $t->tarikh_sasaran_asal->toDateString(),
            'tarikh_siap' => $t->tarikh_siap?->toDateString(), 'status_kemajuan' => $t->status_kemajuan,
            'status_nama' => Lalai::STATUS_TINDAKAN[$t->status_kemajuan] ?? $t->status_kemajuan, 'peratus_kemajuan' => $t->peratus_kemajuan,
            'kod_warna' => $c, 'warna' => KodWarna::papar($c), 'justifikasi_lanjutan' => $t->justifikasi_lanjutan, 'catatan_kemajuan' => $t->catatan_kemajuan,
            'tarikh_disahkan' => $t->tarikh_disahkan?->toIso8601String(),
            'hari_berbaki' => (int) now()->startOfDay()->diffInDays($t->tarikh_sasaran, false),
        ];
    }

    /** @return array<string, mixed> */
    public function laporan(LaporanLawatan $r): array
    {
        return [
            'id' => $r->id, 'jenis' => $r->jenis, 'id_laporan_asal' => $r->id_laporan_asal, 'status' => $r->status, 'status_kunci' => $r->status_kunci,
            'tarikh_jana' => $r->tarikh_jana->toIso8601String(), 'tarikh_sah' => $r->tarikh_sah?->toIso8601String(),
            'pengesah' => $r->pengesah?->name, 'id_dokumen_dms' => $r->id_dokumen_dms, 'id_aliran' => $r->id_aliran,
            'cincangan_kandungan' => $r->cincangan_kandungan, 'catatan_pembetulan' => $r->catatan_pembetulan,
        ];
    }
}
