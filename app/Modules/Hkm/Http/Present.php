<?php

namespace App\Modules\Hkm\Http;

use App\Models\User;
use App\Modules\Hkm\Models\DaftarPetiBesi;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Models\PengeluaranDokumen;
use App\Modules\Hkm\Models\PerubahanHakMilik;
use App\Modules\Hkm\Models\PetiBesi;
use App\Modules\Hkm\Models\WarisPemilikBersama;
use App\Modules\Hkm\Support\Bendera;
use App\Modules\Hkm\Support\Kod;
use App\Modules\Hkm\Support\RefLookup;
use App\Modules\Hkm\Support\StatusTanah;

/** JSON shapes of the HKM API (labels in Malay alongside codes). */
class Present
{
    public function __construct(private RefLookup $ref, private StatusTanah $statuses) {}

    /**
     * @param  array{no_lot: string, bahagian_pemilik: ?string, kategori_guna_tanah: ?string}|null  $lot
     * @return array<string, mixed>
     */
    public function title(HakMilik $h, ?array $lot = null): array
    {
        return [
            'id' => $h->id,
            'lot_id' => $h->lot_id,
            'no_lot' => $lot['no_lot'] ?? null,
            'bahagian_pemilik' => $lot['bahagian_pemilik'] ?? null,
            'no_hak_milik' => $h->no_hak_milik,
            'jenis_hak_milik' => $h->jenis_hak_milik,
            'jenis_hak_milik_nama' => $this->ref->namaJenis($h->jenis_hak_milik),
            'sementara' => $this->ref->sementara($h->jenis_hak_milik),
            'kategori_tanah' => $h->kategori_tanah,
            'kategori_tanah_nama' => $this->ref->namaKategori($h->kategori_tanah),
            'syarat_nyata' => $h->syarat_nyata,
            'keluasan' => $h->keluasan,
            'unit_keluasan' => $h->unit_keluasan,
            'kod_mukim' => $h->kod_mukim,
            'mukim' => $this->ref->namaMukim($h->kod_mukim),
            'kod_daerah' => $h->kod_daerah,
            'daerah' => $this->ref->namaDaerah($h->kod_daerah),
            'pemilik_berdaftar' => $h->pemilik_berdaftar,
            'tarikh_pendaftaran' => $h->tarikh_pendaftaran?->format('Y-m-d'),
            'tarikh_tamat_tempoh' => $h->tarikh_tamat_tempoh?->format('Y-m-d'),
            'tempoh_pajakan_tahun' => $h->tempoh_pajakan_tahun,
            'pejabat_pendaftaran' => $h->pejabat_pendaftaran,
            'no_perserahan' => $h->no_perserahan,
            'tarikh_perserahan' => $h->tarikh_perserahan?->format('Y-m-d'),
            'status_semasa' => $h->status_semasa,
            'status_semasa_nama' => $this->statuses->label($h->status_semasa),
            'id_hak_milik_digantikan' => $h->id_hak_milik_digantikan,
            'disahkan' => $h->disahkan,
            'disahkan_pada' => $h->disahkan_pada?->toIso8601String(),
            'klasifikasi' => $h->klasifikasi,
            'versi_rekod' => $h->versi_rekod,
            'sintetik' => stripos((string) $h->sumber_sistem, 'sintetik') !== false,
            'bendera' => Bendera::for($h),
            'dikemas_kini' => $h->updated_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function vault(PetiBesi $p): array
    {
        return [
            'id' => $p->id,
            'kod_peti_besi' => $p->kod_peti_besi,
            'nama' => $p->nama,
            'lokasi_bangunan' => $p->lokasi_bangunan,
            'tingkat' => $p->tingkat,
            'bilik' => $p->bilik,
            'lokasi' => $p->lokasi(),
            'id_pegawai_penjaga' => $p->id_pegawai_penjaga,
            'penjaga' => $p->penjaga?->name,
            'kapasiti' => $p->kapasiti,
            'status_peti_besi' => $p->status_peti_besi,
            'tarikh_pemeriksaan_akhir' => $p->tarikh_pemeriksaan_akhir?->format('Y-m-d'),
            'catatan' => $p->catatan,
            'bilangan_dokumen' => $p->daftar_count ?? null,
        ];
    }

    /** @return array<string, mixed> */
    public function entry(DaftarPetiBesi $d): array
    {
        $open = $d->relationLoaded('pengeluaranTerbuka') ? $d->pengeluaranTerbuka : null;

        return [
            'id' => $d->id,
            'peti_besi_id' => $d->peti_besi_id,
            'kod_peti_besi' => $d->petiBesi->kod_peti_besi,
            'lokasi_peti_besi' => $d->petiBesi->lokasi(),
            'hak_milik_id' => $d->hak_milik_id,
            'no_hak_milik' => $d->relationLoaded('hakMilik') ? $d->hakMilik->no_hak_milik : null,
            'lot_id' => $d->lot_id,
            'jenis_dokumen_asal' => $d->jenis_dokumen_asal,
            'jenis_dokumen_asal_nama' => Kod::JENIS_DOKUMEN_ASAL[$d->jenis_dokumen_asal] ?? $d->jenis_dokumen_asal,
            'no_fail' => $d->no_fail,
            'lokasi_rak' => $d->lokasi_rak,
            'no_kotak_pengikat' => $d->no_kotak_pengikat,
            'catatan_lokasi' => $d->catatan_lokasi,
            'status_dokumen_asal' => $d->status_dokumen_asal,
            'status_dokumen_asal_nama' => Kod::STATUS_DOKUMEN[$d->status_dokumen_asal] ?? $d->status_dokumen_asal,
            'dalam_simpanan' => $d->status_dokumen_asal === 'dalam_simpanan',
            'tarikh_masuk_simpanan' => $d->tarikh_masuk_simpanan->format('Y-m-d'),
            'pengeluaran_terbuka' => $open ? $this->issue($open) : null,
        ];
    }

    /** @return array<string, mixed> */
    public function issue(PengeluaranDokumen $e): array
    {
        return [
            'id' => $e->id,
            'daftar_peti_besi_id' => $e->daftar_peti_besi_id,
            'tarikh_pengeluaran' => $e->tarikh_pengeluaran->toIso8601String(),
            'pegawai_keluar' => $e->pegawaiKeluar?->name,
            'penerima_nama' => $e->penerima_nama,
            'penerima_jawatan' => $e->penerima_jawatan,
            'tujuan_pengeluaran' => $e->tujuan_pengeluaran,
            'status_semasa_dikeluarkan' => $e->status_semasa_dikeluarkan,
            'tarikh_jangka_pulang' => $e->tarikh_jangka_pulang->format('Y-m-d'),
            'tarikh_pulang_sebenar' => $e->tarikh_pulang_sebenar?->toIso8601String(),
            'pegawai_terima_pulang' => $e->pegawaiTerimaPulang?->name,
            'keadaan_dokumen_pulang' => $e->keadaan_dokumen_pulang,
            'keadaan_dokumen_pulang_nama' => $e->keadaan_dokumen_pulang ? (Kod::KEADAAN_PULANG[$e->keadaan_dokumen_pulang] ?? $e->keadaan_dokumen_pulang) : null,
            'catatan_pulang' => $e->catatan_pulang,
            'status_pengeluaran' => $e->status_pengeluaran,
            'lewat' => $e->lewat(),
            'hari_lewat' => $e->lewat() ? (int) $e->tarikh_jangka_pulang->diffInDays(today()) : 0,
        ];
    }

    /** @return array<string, mixed> */
    public function heir(WarisPemilikBersama $w, User $user): array
    {
        $ic = $w->no_pengenalan;
        if ($ic !== null && ! $user->can('hkm.sulit.lihat')) {
            $ic = preg_replace('/^(\d{6})-?(\d{2})-?(\d{4})$/', '******-**-$3', $ic) ?? '****';
        }

        return [
            'id' => $w->id,
            'nama' => $w->nama,
            'no_pengenalan' => $ic,
            'hubungan' => $w->hubungan,
            'hubungan_nama' => Kod::HUBUNGAN[$w->hubungan] ?? $w->hubungan,
            'pecahan_bahagian_pembilang' => $w->pecahan_bahagian_pembilang,
            'pecahan_bahagian_penyebut' => $w->pecahan_bahagian_penyebut,
            'pecahan' => $w->pecahan_bahagian_penyebut ? $w->pecahan_bahagian_pembilang.'/'.$w->pecahan_bahagian_penyebut : null,
            'sumber_pengesahan' => $w->sumber_pengesahan,
            'sumber_pengesahan_nama' => $w->sumber_pengesahan ? (Kod::SUMBER_PENGESAHAN[$w->sumber_pengesahan] ?? $w->sumber_pengesahan) : null,
            'no_kes_faraid' => $w->no_kes_faraid,
            'status_pengesahan' => $w->status_pengesahan,
            'status_pengesahan_nama' => Kod::STATUS_PENGESAHAN_WARIS[$w->status_pengesahan] ?? $w->status_pengesahan,
            'catatan' => $w->catatan,
            'sintetik' => $w->sintetik,
        ];
    }

    /** @return array<string, mixed> */
    public function change(PerubahanHakMilik $c): array
    {
        return [
            'id' => $c->id,
            'hak_milik_id' => $c->hak_milik_id,
            'no_hak_milik' => $c->relationLoaded('hakMilik') ? $c->hakMilik->no_hak_milik : null,
            'jenis_perubahan' => $c->jenis_perubahan,
            'jenis_perubahan_nama' => Kod::JENIS_PERUBAHAN[$c->jenis_perubahan] ?? $c->jenis_perubahan,
            'data_cadangan' => $c->data_cadangan,
            'data_semasa' => $c->data_semasa,
            'justifikasi' => $c->justifikasi,
            'id_dokumen_rujukan' => $c->id_dokumen_rujukan,
            'no_rujukan_dokumen' => $c->no_rujukan_dokumen,
            'status' => $c->status,
            'status_nama' => Kod::STATUS_PERUBAHAN[$c->status] ?? $c->status,
            'id_instans_aliran' => $c->id_instans_aliran,
            'pemohon' => $c->pemohon?->name,
            'cadangan_perubahan_id' => $c->cadangan_perubahan_id,
            'tarikh_keputusan' => $c->tarikh_keputusan?->toIso8601String(),
            'catatan_keputusan' => $c->catatan_keputusan,
            'dicipta' => $c->created_at->toIso8601String(),
        ];
    }
}
