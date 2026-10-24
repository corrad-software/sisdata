<?php

namespace App\Modules\Tsu\Http;

use App\Models\User;
use App\Modules\Tsu\Models\HasilUkur;
use App\Modules\Tsu\Models\KemaskiniStatusPtd;
use App\Modules\Tsu\Models\KosUrusan;
use App\Modules\Tsu\Models\LantikanJurukur;
use App\Modules\Tsu\Models\PanelJurukur;
use App\Modules\Tsu\Models\SebutHargaTawaran;
use App\Modules\Tsu\Models\SebutHargaUkur;
use App\Modules\Tsu\Models\UrusanDokumen;
use App\Modules\Tsu\Models\UrusanKeputusan;
use App\Modules\Tsu\Models\UrusanPeringkat;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Support\Lalai;
use App\Modules\Tsu\Support\Wang;

/** API shapes for MOD-TSU (labels in Malay; Rahsia/Sulit values only for permitted users). */
class Present
{
    /** @var array<int, string> */
    private array $names = [];

    public function nama(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        return $this->names[$id] ??= (string) (User::query()->whereKey($id)->value('name') ?? '—');
    }

    /** @return array<string, mixed> */
    public function urusan(UrusanTanah $u, User $viewer, ?string $noLot = null): array
    {
        $stage = $u->relationLoaded('peringkat') ? $u->peringkatAktif() : null;

        return [
            'id' => $u->id, 'no_rujukan_mais' => $u->no_rujukan_mais, 'no_rujukan_ptd' => $u->no_rujukan_ptd, 'no_rujukan_etanah' => $u->no_rujukan_etanah,
            'jenis_urusan' => $u->jenis_urusan, 'jenis_nama' => $u->jenisLabel(), 'tajuk' => $u->tajuk,
            'kod_daerah' => $u->kod_daerah, 'kod_mukim' => $u->kod_mukim, 'bahagian_pemilik' => $u->bahagian_pemilik, 'klasifikasi' => $u->klasifikasi,
            'status' => $u->status, 'status_nama' => Lalai::STATUS_URUSAN[$u->status] ?? $u->status,
            'peringkat_semasa' => $u->peringkat_semasa, 'peringkat_semasa_nama' => $stage?->nama_peringkat, 'peringkat_lewat' => $stage?->lewat() ?? false,
            'status_ptd' => $u->status_ptd, 'status_ptd_nama' => $u->status_ptd ? (Lalai::STATUS_PTD[$u->status_ptd] ?? $u->status_ptd) : null,
            'tarikh_mula' => $u->tarikh_mula->toDateString(), 'tarikh_sasaran_siap' => $u->tarikh_sasaran_siap->toDateString(), 'tarikh_siap' => $u->tarikh_siap?->toDateString(),
            'lewat_sasaran' => $u->tarikh_siap === null && $u->terbuka() && $u->tarikh_sasaran_siap->lt(today()),
            'dikunci' => $u->dikunci, 'sintetik' => $u->sintetik, 'no_lot' => $noLot,
            'pegawai' => $u->id_pegawai_bertanggungjawab ? ['id' => $u->id_pegawai_bertanggungjawab, 'nama' => $this->nama($u->id_pegawai_bertanggungjawab)] : null,
        ];
    }

    /** @return array<string, mixed> */
    public function peringkat(UrusanPeringkat $p, int $semasa): array
    {
        return [
            'id' => $p->id, 'no_urutan' => $p->no_urutan, 'kod' => $p->kod, 'nama_peringkat' => $p->nama_peringkat, 'tindakan_diperlukan' => $p->tindakan_diperlukan,
            'pihak_bertanggungjawab' => $p->pihak_bertanggungjawab, 'pihak_nama' => Lalai::PIHAK[$p->pihak_bertanggungjawab] ?? $p->pihak_bertanggungjawab,
            'tempoh_sasaran_hari' => $p->tempoh_sasaran_hari, 'tarikh_mula' => $p->tarikh_mula?->toDateString(), 'tarikh_sasaran' => $p->tarikh_sasaran?->toDateString(),
            'tarikh_selesai' => $p->tarikh_selesai?->toDateString(), 'status' => $p->status, 'status_nama' => Lalai::STATUS_PERINGKAT[$p->status] ?? $p->status,
            'semasa' => $p->no_urutan === $semasa, 'lewat' => $p->lewat(), 'perlu_kelulusan' => $p->perlu_kelulusan, 'kesan' => $p->kesan,
            'dokumen_wajib' => array_map(fn ($k) => ['kod' => $k, 'nama' => Lalai::KATEGORI_DOKUMEN[$k][0] ?? $k], $p->senarai_dokumen_wajib),
            'kategori_kos' => array_map(fn ($k) => ['kod' => $k, 'nama' => Lalai::KATEGORI_KOS[$k] ?? $k], $p->senarai_kategori_kos),
            'catatan' => $p->catatan, 'aras_eskalasi' => $p->aras_eskalasi, 'id_aliran_kerja' => $p->id_aliran_kerja,
        ];
    }

    /** @return array<string, mixed> */
    public function kos(KosUrusan $k): array
    {
        return [
            'id' => $k->id, 'peringkat_id' => $k->peringkat_id, 'kategori_kos' => $k->kategori_kos, 'kategori_nama' => Lalai::KATEGORI_KOS[$k->kategori_kos] ?? $k->kategori_kos,
            'keterangan' => $k->keterangan, 'amaun' => Wang::norm($k->amaun), 'status_bayaran' => $k->status_bayaran, 'status_bayaran_nama' => Lalai::STATUS_BAYARAN[$k->status_bayaran] ?? $k->status_bayaran,
            'no_invois' => $k->no_invois, 'tarikh_invois' => $k->tarikh_invois?->toDateString(), 'tarikh_akhir_bayaran' => $k->tarikh_akhir_bayaran?->toDateString(),
            'tarikh_bayaran' => $k->tarikh_bayaran?->toDateString(), 'no_resit' => $k->no_resit, 'id_dokumen' => $k->id_dokumen, 'rujukan_grp' => $k->rujukan_grp,
        ];
    }

    /** @return array<string, mixed> */
    public function dokumen(UrusanDokumen $d): array
    {
        return [
            'id' => $d->id, 'peringkat_id' => $d->peringkat_id, 'id_dokumen_dms' => $d->id_dokumen_dms, 'kategori_dokumen' => $d->kategori_dokumen,
            'kategori_nama' => Lalai::KATEGORI_DOKUMEN[$d->kategori_dokumen][0] ?? $d->kategori_dokumen, 'nama_fail' => $d->nama_fail,
            'tarikh_dokumen' => $d->tarikh_dokumen?->toDateString(), 'tarikh_luput' => $d->tarikh_luput?->toDateString(), 'sah' => $d->sah(),
            'tarikh_muat_naik' => $d->tarikh_muat_naik->toIso8601String(), 'pemuat_naik' => $this->nama($d->id_pemuat_naik),
        ];
    }

    /** @return array<string, mixed> */
    public function keputusan(UrusanKeputusan $k): array
    {
        return [
            'id' => $k->id, 'konteks' => $k->konteks, 'jenis_keputusan' => $k->jenis_keputusan, 'jenis_nama' => (string) __('tsu::tsu.keputusan.'.$k->jenis_keputusan),
            'kod_peringkat_wfe' => $k->kod_peringkat_wfe, 'tarikh_keputusan' => $k->tarikh_keputusan->toIso8601String(), 'justifikasi' => $k->justifikasi,
            'pelulus' => $this->nama($k->id_pelulus), 'indikator_mfa' => $k->indikator_mfa, 'peringkat_id' => $k->peringkat_id,
        ];
    }

    /** @return array<string, mixed> */
    public function ptd(KemaskiniStatusPtd $s): array
    {
        return [
            'id' => $s->id, 'sumber' => $s->sumber, 'status_ptd' => $s->status_ptd, 'status_nama' => Lalai::STATUS_PTD[$s->status_ptd] ?? $s->status_ptd,
            'tarikh_status' => $s->tarikh_status->toDateString(), 'catatan' => $s->catatan, 'rujukan_ptd' => $s->rujukan_ptd,
            'tarikh_susulan' => $s->tarikh_susulan?->toDateString(), 'pengguna' => $this->nama($s->id_pengguna), 'created_at' => $s->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function panel(PanelJurukur $p): array
    {
        return [
            'id' => $p->id, 'nama_firma' => $p->nama_firma, 'no_lesen_jurukur' => $p->no_lesen_jurukur, 'nama_jurukur_bertanggungjawab' => $p->nama_jurukur_bertanggungjawab,
            'maklumat_perhubungan' => $p->maklumat_perhubungan, 'pengalaman_tahun' => $p->pengalaman_tahun, 'status_panel' => $p->status_panel,
            'tarikh_tamat_lesen' => $p->tarikh_tamat_lesen->toDateString(), 'layak' => $p->layak(), 'ada_akaun' => $p->id_pengguna !== null, 'sintetik' => $p->sintetik,
        ];
    }

    /** @return array<string, mixed> Prices are NEVER returned before the closing date. */
    public function sebutharga(SebutHargaUkur $s): array
    {
        $open = ! $s->ditutup();

        return [
            'id' => $s->id, 'no_jemputan' => $s->no_jemputan, 'tarikh_jemputan' => $s->tarikh_jemputan->toDateString(), 'tarikh_tutup' => $s->tarikh_tutup->toIso8601String(),
            'ditutup' => ! $open, 'skop_kerja' => $s->skop_kerja, 'status' => $s->status, 'status_nama' => Lalai::STATUS_SEBUTHARGA[$s->status] ?? $s->status,
            'id_dokumen_jemputan' => $s->id_dokumen_jemputan, 'id_tawaran_disyorkan' => $s->id_tawaran_disyorkan, 'justifikasi_pemilihan' => $s->justifikasi_pemilihan,
            'penilai' => $this->nama($s->id_penilai), 'pelulus' => $this->nama($s->id_pelulus), 'tarikh_kelulusan' => $s->tarikh_kelulusan?->toIso8601String(),
            'tawaran' => $s->tawaran->map(fn (SebutHargaTawaran $t) => [
                'id' => $t->id, 'jurukur_id' => $t->jurukur_id, 'nama_firma' => $t->jurukur->nama_firma, 'no_lesen_jurukur' => $t->jurukur->no_lesen_jurukur,
                'status' => $t->status, 'tarikh_terima' => $t->tarikh_terima?->toIso8601String(),
                'harga_tawaran' => $open || $t->harga_tawaran === null ? null : Wang::norm($t->harga_tawaran),
                'tempoh_siap_hari' => $open ? null : $t->tempoh_siap_hari, 'pengalaman_tahun' => $t->pengalaman_tahun,
                'markah' => $t->markah !== null ? (float) $t->markah : null, 'indikator_disyorkan' => $t->indikator_disyorkan, 'catatan_penilaian' => $t->catatan_penilaian,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function lantikan(LantikanJurukur $l): array
    {
        return [
            'id' => $l->id, 'urusan_id' => $l->urusan_id, 'jurukur' => ['id' => $l->jurukur_id, 'nama_firma' => $l->jurukur->nama_firma, 'no_lesen_jurukur' => $l->jurukur->no_lesen_jurukur],
            'no_surat_setuju_terima' => $l->no_surat_setuju_terima, 'tarikh_surat_setuju_terima' => $l->tarikh_surat_setuju_terima?->toDateString(),
            'nilai_kontrak' => Wang::norm($l->nilai_kontrak), 'tarikh_mula' => $l->tarikh_mula->toDateString(), 'tempoh_siap_hari' => $l->tempoh_siap_hari,
            'tarikh_jangka_siap' => $l->tarikh_jangka_siap->toDateString(), 'skop_kerja' => $l->skop_kerja, 'status' => $l->status, 'tarikh_siap' => $l->tarikh_siap?->toDateString(),
            'peratus_siap_semasa' => $l->peratus_siap_semasa, 'peratus_dijangka' => $l->status === 'aktif' ? $l->peratusDijangka() : 100, 'lewat' => $l->lewat,
            'kemajuan' => $l->relationLoaded('kemajuan') ? $l->kemajuan->map(fn ($k) => [
                'id' => $k->id, 'milestone' => $k->milestone, 'peratus_siap' => $k->peratus_siap, 'peratus_dijangka' => $k->peratus_dijangka, 'lewat' => $k->lewat,
                'tarikh_lapor' => $k->tarikh_lapor->toDateString(), 'catatan' => $k->catatan, 'pelapor' => $this->nama($k->id_pelapor),
            ])->values()->all() : [],
        ];
    }

    /** @return array<string, mixed> */
    public function hasil(HasilUkur $h): array
    {
        return [
            'id' => $h->id, 'lantikan_id' => $h->lantikan_id, 'lot_id' => $h->lot_id, 'no_lot_cadangan' => $h->no_lot_cadangan, 'format_fail' => $h->format_fail,
            'sistem_koordinat' => $h->sistem_koordinat, 'keluasan_dikira' => (string) $h->keluasan_dikira, 'keluasan_dokumen' => $h->keluasan_dokumen !== null ? (string) $h->keluasan_dokumen : null,
            'sisihan_peratus' => $h->sisihan_peratus !== null ? (float) $h->sisihan_peratus : null, 'toleransi_peratus' => (float) $h->toleransi_peratus, 'dalam_toleransi' => $h->dalam_toleransi,
            'status_pengesahan' => $h->status_pengesahan, 'catatan_pengesahan' => $h->catatan_pengesahan, 'pemuat_naik' => $this->nama($h->id_pemuat_naik),
            'pegawai_sah' => $this->nama($h->id_pegawai_sah), 'tarikh_sah' => $h->tarikh_sah?->toIso8601String(), 'id_lot_dicipta' => $h->id_lot_dicipta,
            'id_spatial_dicadang' => $h->id_spatial_dicadang, 'id_dokumen_pelan_akui' => $h->id_dokumen_pelan_akui, 'created_at' => $h->created_at->toIso8601String(),
        ];
    }
}
