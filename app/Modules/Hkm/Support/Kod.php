<?php

namespace App\Modules\Hkm\Support;

/**
 * Fixed code lists of MOD-HKM. Land status (SRS-HKM-08) is NOT here: it is
 * configurable through the ADM parameter `hkm.senarai_status_tanah` (see
 * StatusTanah). Labels are Malay; the frontend mirrors them in lib/hkm.ts.
 */
final class Kod
{
    /** SRS-HKM-05 — original document status. */
    public const STATUS_DOKUMEN = [
        'dalam_simpanan' => 'Dalam simpanan',
        'dikeluarkan' => 'Dikeluarkan',
        'dalam_proses_pt' => 'Dalam proses di Pejabat Tanah',
        'dipegang_pihak_ketiga' => 'Dipegang pihak ketiga',
        'hilang' => 'Hilang',
        'diganti' => 'Diganti',
    ];

    /** Statuses an issue may put the document in (the rest are set by the custodian). */
    public const STATUS_KELUAR = ['dikeluarkan', 'dalam_proses_pt', 'dipegang_pihak_ketiga'];

    public const JENIS_DOKUMEN_ASAL = [
        'dokumen_hak_milik_daftar' => 'Dokumen hak milik daftar (DHD)',
        'dokumen_hak_milik_keluaran' => 'Dokumen hak milik keluaran (DHK)',
        'pelan_akui' => 'Pelan akui',
        'surat_kelulusan' => 'Surat kelulusan',
        'surat_ikatan_wakaf' => 'Surat ikatan / ikrar wakaf',
        'lain' => 'Lain-lain',
    ];

    public const KEADAAN_PULANG = [
        'baik' => 'Baik',
        'rosak_sedikit' => 'Rosak sedikit',
        'rosak_teruk' => 'Rosak teruk',
        'tidak_lengkap' => 'Tidak lengkap',
    ];

    public const STATUS_PETI_BESI = ['aktif' => 'Aktif', 'penuh' => 'Penuh', 'tidak_aktif' => 'Tidak aktif'];

    /** SRS-HKM-13 */
    public const JENIS_SEKATAN = [
        'kaveat_persendirian' => 'Kaveat persendirian',
        'kaveat_pendaftar' => 'Kaveat pendaftar',
        'kaveat_lien' => 'Kaveat lien',
        'gadaian' => 'Gadaian',
        'lien' => 'Lien',
        'sekatan_kepentingan' => 'Sekatan kepentingan',
        'syarat_nyata_terhad' => 'Syarat nyata yang menghadkan penggunaan',
        'perintah_mahkamah' => 'Perintah mahkamah / larangan',
    ];

    public const STATUS_SEKATAN = ['aktif' => 'Aktif', 'dilupuskan' => 'Dilupuskan', 'ditarik_balik' => 'Ditarik balik'];

    /** SRS-HKM-12 */
    public const HUBUNGAN = [
        'pemilik_bersama' => 'Pemilik bersama',
        'waris' => 'Waris',
        'pentadbir_pusaka' => 'Pentadbir pusaka',
        'pemegang_amanah' => 'Pemegang amanah',
        'pewakaf' => 'Pewakaf',
        'pemilik_asal' => 'Pemilik asal',
    ];

    /** Rows that share the ownership (their fractions must add up to 1). */
    public const HUBUNGAN_BERBAHAGI = ['pemilik_bersama', 'waris'];

    public const SUMBER_PENGESAHAN = [
        'e_faraid' => 'e-Faraid MAIS',
        'perintah_mahkamah_syariah' => 'Perintah Mahkamah Syariah',
        'surat_kuasa_tadbir' => 'Surat Kuasa Tadbir / Perintah Pembahagian',
        'carian_rasmi' => 'Carian rasmi Pejabat Tanah',
        'dokumen_wakaf' => 'Dokumen wakaf',
        'import_data' => 'Import data (perlu disahkan)',
    ];

    public const STATUS_PENGESAHAN_WARIS = [
        'belum_disahkan' => 'Belum disahkan',
        'disahkan' => 'Disahkan',
        'menunggu_faraid' => 'Menunggu pembahagian faraid',
        'dipertikai' => 'Dipertikai',
    ];

    /** Unresolved ownership (SRS-HKM-12 flag). */
    public const WARIS_BELUM_SELESAI = ['belum_disahkan', 'menunggu_faraid', 'dipertikai'];

    /** SRS-HKM-21 */
    public const JENIS_URUSAN = [
        'pindah_milik' => 'Pindah milik',
        'gadaian' => 'Gadaian',
        'pelepasan_gadaian' => 'Pelepasan gadaian',
        'kaveat' => 'Kemasukan kaveat',
        'tarik_balik_kaveat' => 'Tarik balik kaveat',
        'pecah_sempadan' => 'Pecah sempadan / bahagian',
        'tukar_syarat' => 'Tukar syarat',
        'penyatuan' => 'Penyatuan',
        'pajakan' => 'Pajakan',
        'hak_milik_kekal' => 'Pendaftaran hak milik kekal',
        'pembetulan' => 'Pembetulan',
        'lain' => 'Lain-lain',
    ];

    public const STATUS_URUSAN = [
        'diserah' => 'Diserah',
        'dalam_proses' => 'Dalam proses',
        'didaftarkan' => 'Didaftarkan',
        'ditolak' => 'Ditolak',
        'ditarik_balik' => 'Ditarik balik',
    ];

    /** SRS-HKM-16 — Land Office stages of a permanent-title application. */
    public const PERINGKAT_HMK = [
        'pendaftaran' => 'Pendaftaran permohonan',
        'semakan_dokumen' => 'Semakan dokumen',
        'ukur_halus' => 'Ukur halus (JUPEM)',
        'kelulusan_pt' => 'Kelulusan Pejabat Tanah',
        'bayaran' => 'Bayaran',
        'penyediaan_hak_milik' => 'Penyediaan hak milik',
        'penerimaan' => 'Penerimaan hak milik baharu',
        'selesai' => 'Selesai',
    ];

    public const STATUS_HMK = [
        'dalam_proses' => 'Dalam proses',
        'lulus' => 'Lulus',
        'ditolak' => 'Ditolak',
        'dibatalkan' => 'Dibatalkan',
        'selesai' => 'Selesai',
    ];

    /** Default checklist (SRS-HKM-16); stored per application so it can be edited. */
    public const SENARAI_SEMAK_HMK = [
        'salinan_hak_milik_sementara' => 'Salinan hak milik sementara',
        'pelan_akui' => 'Pelan akui / pelan ukur',
        'resit_cukai_terkini' => 'Resit cukai tanah terkini',
        'borang_permohonan' => 'Borang permohonan lengkap',
        'surat_kebenaran_mais' => 'Surat kebenaran / resolusi MAIS',
    ];

    /** SRS-HKM-19 change request kinds. */
    public const JENIS_PERUBAHAN = [
        'butiran' => 'Pindaan butiran hak milik',
        'status' => 'Perubahan status tanah',
        'pemilik' => 'Perubahan pemilik berdaftar',
        'sekatan' => 'Sekatan kepentingan / bebanan',
        'waris' => 'Waris / pemilik bersama',
    ];

    public const STATUS_PERUBAHAN = [
        'menunggu_kelulusan' => 'Menunggu kelulusan',
        'diluluskan' => 'Diluluskan',
        'ditolak' => 'Ditolak',
        'ditarik_balik' => 'Ditarik balik',
        'dibatalkan' => 'Dibatalkan',
        'gagal' => 'Gagal dilaksanakan',
    ];

    /** Fields a "butiran" change may touch. */
    public const MEDAN_BUTIRAN = [
        'no_hak_milik', 'jenis_hak_milik', 'kategori_tanah', 'syarat_nyata', 'keluasan', 'unit_keluasan',
        'tarikh_pendaftaran', 'tarikh_tamat_tempoh', 'tempoh_pajakan_tahun', 'pejabat_pendaftaran',
        'no_perserahan', 'tarikh_perserahan',
    ];

    public const SUMBER_CADANGAN = [
        'e_tanah' => 'e-Tanah',
        'ptd' => 'Pejabat Tanah Daerah',
        'ptg' => 'Pejabat Tanah dan Galian',
        'penyedia_berpusat' => 'Penyedia integrasi berpusat',
        'e_faraid' => 'e-Faraid MAIS',
        'lain' => 'Lain-lain',
    ];

    public const STATUS_CADANGAN = ['draf' => 'Draf — menunggu pengesahan', 'disahkan' => 'Disahkan (dihantar untuk kelulusan)', 'ditolak' => 'Ditolak'];

    /**
     * @param  array<string, string>  $list
     * @return list<array{kod: string, nama: string}>
     */
    public static function options(array $list): array
    {
        return array_map(fn ($k, $v) => ['kod' => (string) $k, 'nama' => $v], array_keys($list), $list);
    }
}
