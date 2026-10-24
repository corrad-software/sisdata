<?php

namespace App\Modules\Ast\Http;

use App\Models\User;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\AsetKategori;
use App\Modules\Ast\Models\AsetKontraktor;
use App\Modules\Ast\Models\AsetPelupusan;
use App\Modules\Ast\Models\AsetPemeriksaan;
use App\Modules\Ast\Models\JadualPenyelenggaraan;
use App\Modules\Ast\Models\RekodPenyelenggaraan;
use App\Modules\Ast\Support\Lalai;
use App\Modules\Ast\Support\Rujukan;
use App\Modules\Ast\Support\SusutNilai;

/** JSON shapes of MOD-AST records (Malay labels included). */
class Present
{
    /** @var array<int, AsetKategori>|null */
    private ?array $kategori = null;

    public function __construct(private Rujukan $ref) {}

    public function kategori(int $id): ?AsetKategori
    {
        $this->kategori ??= AsetKategori::query()->get()->keyBy('id')->all();

        return $this->kategori[$id] ?? null;
    }

    /**
     * @param  array<string, array{no_lot: string}>  $lots
     * @return array<string, mixed>
     */
    public function aset(AsetFizikal $a, array $lots = []): array
    {
        $k = $this->kategori($a->id_kategori);

        return [
            'id' => $a->id, 'no_rujukan_aset' => $a->no_rujukan_aset, 'qr_ref' => $a->qr_ref, 'lot_id' => $a->lot_id, 'no_lot' => $lots[$a->lot_id]['no_lot'] ?? null,
            'id_aset_induk' => $a->id_aset_induk, 'id_kategori' => $a->id_kategori, 'kategori' => $k?->nama_kategori, 'jenis_aset' => $a->jenis_aset, 'jenis_nama' => Lalai::JENIS_ASET[$a->jenis_aset] ?? $a->jenis_aset,
            'nama_aset' => $a->nama_aset, 'alamat' => $a->alamat, 'latitud' => $a->latitud !== null ? (float) $a->latitud : null, 'longitud' => $a->longitud !== null ? (float) $a->longitud : null,
            'kod_daerah' => $a->kod_daerah, 'daerah' => $this->ref->namaDaerah($a->kod_daerah), 'kod_mukim' => $a->kod_mukim, 'mukim' => $this->ref->namaMukim($a->kod_mukim),
            'status_kondisi' => $a->status_kondisi, 'kondisi_nama' => Lalai::KONDISI[$a->status_kondisi] ?? $a->status_kondisi, 'warna_kondisi' => Lalai::WARNA_KONDISI[$a->status_kondisi] ?? '#9CA3AF',
            'nilai_semasa' => $a->nilai_semasa, 'tarikh_nilai_semasa' => $a->tarikh_nilai_semasa?->toDateString(), 'tarikh_pemeriksaan_terakhir' => $a->tarikh_pemeriksaan_terakhir?->toDateString(),
            'status_aset' => $a->status_aset, 'status_nama' => Lalai::STATUS_ASET[$a->status_aset] ?? $a->status_aset, 'status_kelulusan' => $a->status_kelulusan,
            'kelulusan_nama' => Lalai::STATUS_KELULUSAN[$a->status_kelulusan] ?? $a->status_kelulusan, 'status_arkib' => $a->status_arkib,
            'id_premis_pjk' => $a->id_premis_pjk, 'sintetik' => $a->sintetik,
        ];
    }

    /** @return array<string, mixed> */
    public function asetPenuh(AsetFizikal $a, User $user, array $lot = []): array
    {
        $k = $this->kategori($a->id_kategori);
        $susut = $a->kos_perolehan !== null && $k !== null
            ? SusutNilai::kira((string) $a->kos_perolehan, (int) ($a->tahun_perolehan ?? $a->created_at->year), $k->hayat_guna_tahun, (string) $k->kadar_nilai_sisa, now()->year) : null;
        $tempohPeriksa = $k->tempoh_pemeriksaan_bulan ?? 12;

        return $this->aset($a) + [
            'keterangan' => $a->keterangan, 'tahun_pembinaan' => $a->tahun_pembinaan, 'tahun_perolehan' => $a->tahun_perolehan, 'tarikh_perolehan' => $a->tarikh_perolehan?->toDateString(),
            'saiz_keluasan' => $a->saiz_keluasan, 'unit_ukuran' => $a->unit_ukuran, 'kos_perolehan' => $a->kos_perolehan,
            'pegawai' => $a->pegawai ? ['id' => $a->pegawai->id, 'nama' => $a->pegawai->name] : null, 'id_pegawai_bertanggungjawab' => $a->id_pegawai_bertanggungjawab,
            'tarikh_arkib' => $a->tarikh_arkib?->toDateString(), 'sebab_arkib' => $a->sebab_arkib, 'id_aliran' => $a->id_aliran,
            'induk' => $a->induk ? ['id' => $a->induk->id, 'no_rujukan_aset' => $a->induk->no_rujukan_aset, 'nama_aset' => $a->induk->nama_aset] : null,
            'lot' => $lot ?: null, 'pautan_lot' => '/admin/pro/lot/'.$a->lot_id,
            'susut_nilai' => $susut, 'hayat_guna_tahun' => $k?->hayat_guna_tahun, 'kadar_nilai_sisa' => $k?->kadar_nilai_sisa,
            'tempoh_pemeriksaan_bulan' => $tempohPeriksa,
            'pemeriksaan_lewat' => $a->tarikh_pemeriksaan_terakhir === null || $a->tarikh_pemeriksaan_terakhir->lt(now()->subMonthsNoOverflow($tempohPeriksa)->startOfDay()),
            'tag' => $a->tag->map(fn ($t) => ['nama' => $t->nama_tag, 'jenis' => $t->jenis_tag])->values()->all(),
            'boleh' => [
                'kemas_kini' => $user->can('ast.aset.kemas_kini') && ! $a->status_arkib,
                'nilai' => $user->can('ast.sulit.kemas_kini') && ! $a->status_arkib,
                'pelupusan' => $user->can('ast.aset.padam') && ! $a->status_arkib,
                'qr' => $user->can('ast.qr.kemas_kini'),
                'label' => $user->can('ast.qr.eksport'),
                'pemeriksaan' => $user->can('ast.pemeriksaan.cipta') && ! $a->status_arkib,
                'penyelenggaraan' => $user->can('ast.penyelenggaraan.cipta') && ! $a->status_arkib,
                'imbasan' => $user->can('ast.imbasan.lihat'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function pemeriksaan(AsetPemeriksaan $p): array
    {
        return [
            'id' => $p->id, 'id_aset' => $p->id_aset, 'tarikh_pemeriksaan' => $p->tarikh_pemeriksaan->toDateString(), 'pegawai' => $p->pegawai?->name,
            'kaedah' => $p->kaedah, 'kaedah_nama' => Lalai::KAEDAH_PEMERIKSAAN[$p->kaedah] ?? $p->kaedah,
            'status_kondisi_dilapor' => $p->status_kondisi_dilapor, 'kondisi_nama' => Lalai::KONDISI[$p->status_kondisi_dilapor] ?? $p->status_kondisi_dilapor,
            'isu_dikenalpasti' => $p->isu_dikenalpasti, 'tindakan_disyorkan' => $p->tindakan_disyorkan, 'senarai_semak' => $p->senarai_semak,
            'latitud' => $p->latitud !== null ? (float) $p->latitud : null, 'longitud' => $p->longitud !== null ? (float) $p->longitud : null,
            'jarak_dari_aset_m' => $p->jarak_dari_aset_m !== null ? (float) $p->jarak_dari_aset_m : null, 'status_penyegerakan' => $p->status_penyegerakan,
            'id_luar_talian' => $p->id_luar_talian, 'masa_tamat' => $p->masa_tamat?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function jadual(JadualPenyelenggaraan $j): array
    {
        $today = now()->startOfDay();

        return [
            'id' => $j->id, 'id_aset' => $j->id_aset, 'jenis_penyelenggaraan' => $j->jenis_penyelenggaraan, 'jenis_nama' => Lalai::JENIS_PENYELENGGARAAN[$j->jenis_penyelenggaraan] ?? $j->jenis_penyelenggaraan,
            'kitaran_bulan' => $j->kitaran_bulan, 'tarikh_jatuh_tempoh' => $j->tarikh_jatuh_tempoh->toDateString(), 'tempoh_amaran_hari' => $j->tempoh_amaran_hari,
            'status' => $j->status, 'tertunggak' => $j->status === 'aktif' && $j->tarikh_jatuh_tempoh->lt($today),
            'hampir' => $j->status === 'aktif' && ! $j->tarikh_jatuh_tempoh->lt($today) && $j->tarikh_jatuh_tempoh->lte($today->copy()->addDays($j->tempoh_amaran_hari)),
            'pegawai' => $j->pegawai ? ['id' => $j->pegawai->id, 'nama' => $j->pegawai->name] : null, 'dijana_automatik' => $j->dijana_automatik,
            'tarikh_terakhir_dilaksana' => $j->tarikh_terakhir_dilaksana?->toDateString(), 'tarikh_eskalasi' => $j->tarikh_eskalasi?->toDateString(),
            'aset' => $j->relationLoaded('aset') && $j->aset ? ['id' => $j->aset->id, 'no_rujukan_aset' => $j->aset->no_rujukan_aset, 'nama_aset' => $j->aset->nama_aset] : null,
        ];
    }

    /** @return array<string, mixed> */
    public function kerja(RekodPenyelenggaraan $k, bool $kos = true): array
    {
        return [
            'id' => $k->id, 'no_rujukan' => $k->no_rujukan, 'id_aset' => $k->id_aset, 'id_jadual' => $k->id_jadual,
            'jenis_kerja' => $k->jenis_kerja, 'jenis_kerja_nama' => Lalai::JENIS_KERJA[$k->jenis_kerja] ?? $k->jenis_kerja,
            'jenis_penyelenggaraan' => $k->jenis_penyelenggaraan, 'jenis_nama' => Lalai::JENIS_PENYELENGGARAAN[$k->jenis_penyelenggaraan] ?? $k->jenis_penyelenggaraan,
            'keterangan' => $k->keterangan, 'keutamaan' => $k->keutamaan, 'keutamaan_nama' => Lalai::KEUTAMAAN[$k->keutamaan] ?? $k->keutamaan,
            'tarikh_dijadual' => $k->tarikh_dijadual?->toDateString(), 'tarikh_mula' => $k->tarikh_mula?->toDateString(), 'tarikh_siap' => $k->tarikh_siap?->toDateString(),
            'kos_anggaran' => $kos ? $k->kos_anggaran : null, 'kos' => $kos ? $k->kos : null,
            'id_kontraktor' => $k->id_kontraktor, 'nama_syarikat_pelaksana' => $k->nama_syarikat_pelaksana,
            'status' => $k->status, 'status_nama' => Lalai::STATUS_KERJA[$k->status] ?? $k->status, 'id_aliran' => $k->id_aliran,
            'sumber' => $k->sumber, 'sumber_nama' => Lalai::SUMBER_KERJA[$k->sumber] ?? $k->sumber, 'id_keperluan_ptn' => $k->id_keperluan_ptn,
            'id_gambar_sebelum' => $k->id_gambar_sebelum, 'id_gambar_selepas' => $k->id_gambar_selepas,
            'pegawai' => $k->pegawai ? ['id' => $k->pegawai->id, 'nama' => $k->pegawai->name] : null, 'catatan_penyelesaian' => $k->catatan_penyelesaian,
            'tertunggak' => in_array($k->status, Lalai::KERJA_TERBUKA, true) && $k->tarikh_dijadual !== null && $k->tarikh_dijadual->lt(now()->startOfDay()),
            'aset' => $k->relationLoaded('aset') && $k->aset ? ['id' => $k->aset->id, 'no_rujukan_aset' => $k->aset->no_rujukan_aset, 'nama_aset' => $k->aset->nama_aset] : null,
        ];
    }

    /** @return array<string, mixed> */
    public function kontraktor(AsetKontraktor $c): array
    {
        return [
            'id' => $c->id, 'nama_syarikat' => $c->nama_syarikat, 'no_pendaftaran' => $c->no_pendaftaran, 'no_telefon' => $c->no_telefon, 'emel' => $c->emel,
            'bidang' => $c->bidang, 'tarikh_tamat_pendaftaran' => $c->tarikh_tamat_pendaftaran?->toDateString(), 'id_pengguna' => $c->id_pengguna, 'status_aktif' => $c->status_aktif,
        ];
    }

    /** @return array<string, mixed> */
    public function pelupusan(AsetPelupusan $p): array
    {
        return [
            'id' => $p->id, 'jenis' => $p->jenis, 'jenis_nama' => Lalai::JENIS_PELUPUSAN[$p->jenis] ?? $p->jenis, 'sebab' => $p->sebab, 'kaedah' => $p->kaedah,
            'kaedah_nama' => $p->kaedah ? (Lalai::KAEDAH_PELUPUSAN[$p->kaedah] ?? $p->kaedah) : null, 'nilai_pelupusan' => $p->nilai_pelupusan,
            'tarikh_dicadang' => $p->tarikh_dicadang?->toDateString(), 'status' => $p->status, 'status_nama' => Lalai::STATUS_PELUPUSAN[$p->status] ?? $p->status,
            'id_aliran' => $p->id_aliran, 'pemohon' => $p->pemohon?->name, 'tarikh_keputusan' => $p->tarikh_keputusan?->toIso8601String(), 'ulasan' => $p->ulasan,
            'created_at' => $p->created_at->toIso8601String(),
        ];
    }
}
