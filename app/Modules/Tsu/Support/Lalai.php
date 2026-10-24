<?php

namespace App\Modules\Tsu\Support;

/**
 * MOD-TSU code lists (labels in Malay) and the seeded default process
 * templates of the seven Land Office transaction types (SRS-TSU-01). The
 * templates are only defaults: admins version them through the template API
 * without a code release (SRS-TSU-02).
 */
final class Lalai
{
    public const JENIS_URUSAN = [
        'tukar_syarat' => 'Tukar syarat / jenis kegunaan tanah',
        'pecah_sempadan' => 'Pecah sempadan',
        'pecah_bahagian' => 'Pecah bahagian',
        'penyatuan' => 'Penyatuan (cantuman) lot',
        'pelupusan' => 'Pelupusan tanah',
        'serah_balik_beri_milik' => 'Penyerahan balik dan pemberimilikan semula',
        'hak_milik_kekal' => 'Permohonan hak milik kekal',
    ];

    /** Types whose approved survey creates new lots (SRS-TSU-19). */
    public const JENIS_LOT_BAHARU = ['pecah_sempadan', 'pecah_bahagian', 'penyatuan'];

    public const PIHAK = [
        'mais' => 'MAIS',
        'pejabat_tanah' => 'Pejabat Tanah Daerah',
        'ptg' => 'Pejabat Tanah dan Galian (PTG)',
        'jurukur' => 'Jurukur berlesen',
        'pbt' => 'Pihak Berkuasa Tempatan',
        'jpph' => 'JPPH',
        'jupem' => 'JUPEM',
    ];

    /** Parties outside MAIS — "urusan tertunggak di pihak luar" (SRS-TSU-27). */
    public const PIHAK_LUAR = ['pejabat_tanah', 'ptg', 'jurukur', 'pbt', 'jpph', 'jupem'];

    public const KATEGORI_KOS = [
        'fi_permohonan' => 'Fi permohonan',
        'premium' => 'Premium',
        'cukai_tambahan' => 'Cukai tambahan',
        'upah_ukur' => 'Upah ukur',
        'fi_pelan' => 'Fi pelan',
        'fi_pendaftaran' => 'Fi pendaftaran',
        'kos_lain' => 'Kos lain',
    ];

    public const STATUS_BAYARAN = ['belum_bayar' => 'Belum dibayar', 'dalam_proses' => 'Dalam proses bayaran', 'dibayar' => 'Telah dibayar', 'dibatalkan' => 'Dibatalkan'];

    /** TSU document category => [label, MOD-DMS category]. */
    public const KATEGORI_DOKUMEN = [
        'borang_permohonan' => ['Borang permohonan', 'surat_rasmi'],
        'pelan_permulaan' => ['Pelan permulaan / pelan cadangan', 'pelan'],
        'pelan_ukur' => ['Pelan ukur', 'pelan'],
        'pelan_akui' => ['Pelan akui (PA)', 'pelan'],
        'laporan_penilaian' => ['Laporan penilaian JPPH', 'laporan'],
        'laporan_tapak' => ['Laporan lawatan tapak', 'laporan'],
        'gambar_lawatan' => ['Gambar lawatan tapak', 'gambar_tapak'],
        'surat_kelulusan' => ['Surat kelulusan', 'surat_rasmi'],
        'keputusan_mmkn' => ['Keputusan / kertas MMKN', 'surat_rasmi'],
        'notis_bayaran' => ['Notis / invois bayaran', 'resit'],
        'resit' => ['Resit bayaran', 'resit'],
        'surat_jemputan' => ['Dokumen jemputan sebut harga', 'surat_rasmi'],
        'surat_setuju_terima' => ['Surat setuju terima', 'perjanjian'],
        'fail_ukur' => ['Fail hasil ukur (DXF / Shapefile / GeoJSON)', 'fail_gis'],
        'hak_milik' => ['Hak milik baharu', 'geran'],
        'perjanjian' => ['Perjanjian jual beli / tukar', 'perjanjian'],
        'lain' => ['Lain-lain', 'lain'],
    ];

    public const STATUS_URUSAN = [
        'aktif' => 'Dalam proses',
        'menunggu_kelulusan' => 'Menunggu kelulusan',
        'ditolak' => 'Ditolak',
        'selesai' => 'Selesai (belum ditutup)',
        'ditutup' => 'Ditutup',
        'dibatalkan' => 'Dibatalkan',
    ];

    public const URUSAN_TERBUKA = ['aktif', 'menunggu_kelulusan'];

    public const STATUS_PERINGKAT = [
        'belum_mula' => 'Belum bermula',
        'aktif' => 'Sedang berjalan',
        'menunggu_kelulusan' => 'Menunggu kelulusan',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak',
        'dilangkau' => 'Dilangkau',
    ];

    public const KAEDAH_PELUPUSAN = ['jualan' => 'Jualan', 'tukaran' => 'Tukaran (istibdal bagi tanah wakaf)', 'pindah_milik' => 'Pindah milik', 'pajakan' => 'Pajakan jangka panjang'];

    /** Structured Land Office statuses for manual updates (SRS-TSU-22, SRS-INT-10). */
    public const STATUS_PTD = [
        'diterima' => 'Permohonan diterima',
        'dalam_semakan' => 'Dalam semakan',
        'lawatan_tapak' => 'Lawatan tapak dijadualkan',
        'ulasan_jabatan' => 'Menunggu ulasan jabatan teknikal',
        'dibentang_mmkn' => 'Dibentangkan kepada MMKN / Jawatankuasa',
        'lulus' => 'Diluluskan',
        'lulus_bersyarat' => 'Diluluskan bersyarat',
        'notis_bayaran' => 'Notis bayaran dikeluarkan',
        'didaftarkan' => 'Hak milik didaftarkan',
        'ditolak' => 'Ditolak',
        'dikembalikan' => 'Dikembalikan untuk pembetulan',
    ];

    public const STATUS_SEBUTHARGA = [
        'dibuka' => 'Dibuka', 'ditutup' => 'Ditutup', 'dinilai' => 'Dinilai', 'menunggu_kelulusan' => 'Menunggu kelulusan',
        'diluluskan' => 'Diluluskan', 'ditolak' => 'Ditolak', 'dibatalkan' => 'Dibatalkan',
    ];

    public const PERANAN_LOT = ['asal' => 'Lot asal', 'baharu' => 'Lot baharu', 'terlibat' => 'Lot terlibat'];

    /** Lot field names MOD-TSU may change on the master record after an approved urusan. */
    public const MEDAN_SYARAT = ['kategori_guna_tanah', 'syarat_nyata', 'zoning'];

    /**
     * Seeded templates. Stage tuple: [kod, nama, pihak, hari, dokumen wajib, kategori kos, perlu kelulusan, kesan, tindakan].
     *
     * @return array<string, array{nama: string, aliran: string, peringkat: list<array{0: string, 1: string, 2: string, 3: int, 4: list<string>, 5: list<string>, 6: bool, 7: string|null, 8: string}>}>
     */
    public static function templat(): array
    {
        $ukur = [
            ['jemputan_ukur', 'Perolehan jurukur berlesen', 'mais', 30, [], [], false, 'perlu_lantikan', 'Jemput sebut harga panel jurukur, nilai tawaran dan lantik jurukur.'],
            ['kerja_ukur', 'Kerja ukur', 'jurukur', 60, [], ['upah_ukur'], false, 'perlu_hasil_ukur_diterima', 'Jurukur melaksanakan kerja ukur dan menyerahkan hasil ukur.'],
            ['pengesahan_hasil_ukur', 'Pengesahan hasil ukur (GIS)', 'mais', 14, ['pelan_ukur'], ['fi_pelan'], false, null, 'GIS Specialist mengimport, mengesahkan CRS dan membandingkan keluasan.'],
        ];

        return [
            'tukar_syarat' => ['nama' => 'Tukar syarat / jenis kegunaan tanah', 'aliran' => AliranTsu::URUSAN, 'peringkat' => [
                ['penyediaan_dokumen', 'Penyediaan pelan dan dokumen', 'mais', 14, ['borang_permohonan', 'pelan_permulaan'], [], false, null, 'Sediakan pelan cadangan, borang dan dokumen sokongan.'],
                ['kelulusan_mais', 'Kelulusan dalaman MAIS', 'mais', 14, [], [], true, null, 'Kelulusan Ketua Bahagian / Pengurusan untuk mengemukakan permohonan.'],
                ['permohonan_etanah', 'Permohonan melalui e-Tanah', 'mais', 7, [], ['fi_permohonan'], false, 'perlu_rujukan_etanah', 'Daftar permohonan di e-Tanah dan rekod nombor rujukan.'],
                ['penghantaran_ptd', 'Penghantaran ke Pejabat Tanah Daerah', 'pejabat_tanah', 14, [], [], false, 'perlu_rujukan_ptd', 'Serahan permohonan fizikal / rujukan PTD.'],
                ['keputusan_ptd', 'Keputusan PTD / MMKN', 'pejabat_tanah', 120, ['surat_kelulusan'], [], false, null, 'Menunggu keputusan dan rekod surat kelulusan / syarat.'],
                ['bayaran_premium', 'Bayaran premium', 'mais', 30, ['notis_bayaran', 'resit'], ['premium', 'cukai_tambahan'], false, 'perlu_kos_dibayar', 'Bahagian Kewangan menjelaskan premium mengikut notis.'],
                ['pendaftaran_hak_milik', 'Pendaftaran hak milik baharu', 'pejabat_tanah', 60, ['hak_milik'], ['fi_pendaftaran'], false, 'kemas_kini_syarat', 'Hak milik baharu dengan syarat baharu didaftarkan; rekod lot dikemas kini.'],
            ]],
            'pecah_sempadan' => ['nama' => 'Pecah sempadan', 'aliran' => AliranTsu::URUSAN, 'peringkat' => [
                ['penyediaan_dokumen', 'Penyediaan pelan pecahan dan dokumen', 'mais', 14, ['borang_permohonan', 'pelan_permulaan'], [], false, null, 'Sediakan pelan cadangan pecah sempadan.'],
                ['kelulusan_mais', 'Kelulusan dalaman MAIS', 'mais', 14, [], [], true, null, 'Kelulusan dalaman sebelum permohonan dikemukakan.'],
                ['permohonan_ptd', 'Permohonan kepada PTD', 'pejabat_tanah', 90, ['surat_kelulusan'], ['fi_permohonan'], false, 'perlu_rujukan_ptd', 'Permohonan pecah sempadan dan kelulusan PTD.'],
                ...$ukur,
                ['bayaran_fi', 'Bayaran fi dan premium', 'mais', 30, ['resit'], ['premium', 'fi_pendaftaran'], false, 'perlu_kos_dibayar', 'Jelaskan fi pelan, pendaftaran dan premium.'],
                ['daftar_lot_baharu', 'Pendaftaran lot dan hak milik baharu', 'pejabat_tanah', 60, ['pelan_akui'], [], false, 'cipta_lot_baharu', 'Lot baharu dicipta dengan geometri hasil ukur; lot asal diarkibkan.'],
                ['pemindahan_rekod', 'Pemindahan rekod ke lot baharu', 'mais', 14, [], [], false, 'perlu_pemindahan_selesai', 'Petakan pajakan, cukai dan aset ke lot baharu (pengesahan dua peringkat).'],
            ]],
            'pecah_bahagian' => ['nama' => 'Pecah bahagian', 'aliran' => AliranTsu::URUSAN, 'peringkat' => [
                ['penyediaan_dokumen', 'Penyediaan pelan pecah bahagian dan dokumen', 'mais', 14, ['borang_permohonan', 'pelan_permulaan'], [], false, null, 'Sediakan pelan dan persetujuan pemilik bersama.'],
                ['kelulusan_mais', 'Kelulusan dalaman MAIS', 'mais', 14, [], [], true, null, 'Kelulusan dalaman sebelum permohonan dikemukakan.'],
                ['permohonan_ptd', 'Permohonan kepada PTD', 'pejabat_tanah', 90, ['surat_kelulusan'], ['fi_permohonan'], false, 'perlu_rujukan_ptd', 'Permohonan pecah bahagian dan kelulusan PTD.'],
                ...$ukur,
                ['bayaran_fi', 'Bayaran fi', 'mais', 30, ['resit'], ['fi_pendaftaran'], false, 'perlu_kos_dibayar', 'Jelaskan fi pendaftaran.'],
                ['daftar_lot_baharu', 'Pendaftaran lot dan hak milik baharu', 'pejabat_tanah', 60, ['pelan_akui'], [], false, 'cipta_lot_baharu', 'Lot baharu dicipta; lot asal diarkibkan.'],
                ['pemindahan_rekod', 'Pemindahan rekod ke lot baharu', 'mais', 14, [], [], false, 'perlu_pemindahan_selesai', 'Petakan rekod berkaitan ke lot baharu.'],
            ]],
            'penyatuan' => ['nama' => 'Penyatuan (cantuman) lot', 'aliran' => AliranTsu::URUSAN, 'peringkat' => [
                ['penyediaan_dokumen', 'Penyediaan pelan penyatuan dan dokumen', 'mais', 14, ['borang_permohonan', 'pelan_permulaan', 'hak_milik'], [], false, null, 'Sediakan pelan penyatuan lot-lot bersebelahan dan salinan hak milik setiap lot.'],
                ['kelulusan_mais', 'Kelulusan dalaman MAIS', 'mais', 14, [], [], true, null, 'Kelulusan dalaman sebelum permohonan dikemukakan.'],
                ['permohonan_ptd', 'Permohonan kepada PTD', 'pejabat_tanah', 90, ['surat_kelulusan'], ['fi_permohonan'], false, 'perlu_rujukan_ptd', 'Permohonan penyatuan dan kelulusan PTD.'],
                ...$ukur,
                ['bayaran_fi', 'Bayaran fi, premium dan cukai tambahan', 'mais', 30, ['resit'], ['premium', 'cukai_tambahan', 'fi_pendaftaran'], false, 'perlu_kos_dibayar', 'Jelaskan fi, premium dan cukai tambahan.'],
                ['daftar_lot_baharu', 'Pendaftaran lot dan hak milik baharu', 'pejabat_tanah', 60, ['pelan_akui'], [], false, 'cipta_lot_baharu', 'Satu lot baharu dicipta; lot-lot asal diarkibkan.'],
                ['pemindahan_rekod', 'Pemindahan rekod ke lot baharu', 'mais', 14, [], [], false, 'perlu_pemindahan_selesai', 'Petakan rekod lot-lot asal ke lot baharu.'],
            ]],
            'pelupusan' => ['nama' => 'Pelupusan tanah', 'aliran' => AliranTsu::PELUPUSAN, 'peringkat' => [
                ['permohonan_dokumen', 'Permohonan dan dokumen sokongan', 'mais', 14, ['borang_permohonan'], [], false, null, 'Daftar cadangan pelupusan, kaedah dan pihak penerima.'],
                ['lawatan_tapak', 'Lawatan tapak', 'mais', 14, ['laporan_tapak', 'gambar_lawatan'], [], false, null, 'Lawatan tapak dan laporan keadaan semasa.'],
                ['laporan_jpph', 'Laporan penilaian JPPH', 'jpph', 45, ['laporan_penilaian'], ['kos_lain'], false, 'perlu_penilaian', 'Dapatkan laporan penilaian terkini daripada JPPH.'],
                ['kelulusan_pelupusan', 'Kelulusan pelupusan (aliran tertinggi)', 'mais', 30, ['laporan_penilaian'], [], true, 'perlu_penilaian', 'Kelulusan berlapis MAIS dengan MFA, justifikasi bertulis dan penilaian terkini.'],
                ['permohonan_ptd', 'Permohonan kepada PTD', 'pejabat_tanah', 90, [], ['fi_permohonan'], false, 'perlu_rujukan_ptd', 'Kemukakan permohonan pindah milik / pelupusan kepada PTD.'],
                ['invois_bayaran', 'Invois dan permohonan bayaran', 'mais', 30, ['notis_bayaran'], ['fi_pendaftaran', 'cukai_tambahan'], false, 'perlu_kos_dibayar', 'Rekod invois, permohonan bayaran dan resit.'],
                ['pendaftaran', 'Pendaftaran pelupusan', 'pejabat_tanah', 60, ['surat_kelulusan'], [], false, 'arkib_lot_dilupus', 'Pindah milik didaftarkan; lot diarkibkan berserta sejarahnya.'],
            ]],
            'serah_balik_beri_milik' => ['nama' => 'Penyerahan balik dan pemberimilikan semula', 'aliran' => AliranTsu::URUSAN, 'peringkat' => [
                ['penyediaan_dokumen', 'Penyediaan dokumen (Borang 12A)', 'mais', 14, ['borang_permohonan', 'pelan_permulaan'], [], false, null, 'Sediakan borang serah balik dan pelan.'],
                ['kelulusan_mais', 'Kelulusan dalaman MAIS', 'mais', 14, [], [], true, null, 'Kelulusan dalaman sebelum penyerahan.'],
                ['permohonan_ptd', 'Permohonan kepada PTD', 'pejabat_tanah', 30, [], ['fi_permohonan'], false, 'perlu_rujukan_ptd', 'Serah permohonan kepada PTD.'],
                ['keputusan_mmkn', 'Keputusan MMKN', 'ptg', 120, ['keputusan_mmkn'], [], false, null, 'Rekod keputusan MMKN dan syarat baharu.'],
                ['bayaran_premium', 'Bayaran premium', 'mais', 30, ['notis_bayaran', 'resit'], ['premium', 'upah_ukur'], false, 'perlu_kos_dibayar', 'Jelaskan premium dan bayaran ukur.'],
                ['hak_milik_baharu', 'Pendaftaran hak milik baharu', 'pejabat_tanah', 90, ['hak_milik'], ['fi_pendaftaran'], false, 'kemas_kini_syarat', 'Hak milik baharu dikeluarkan; syarat lot dikemas kini.'],
            ]],
            'hak_milik_kekal' => ['nama' => 'Permohonan hak milik kekal', 'aliran' => AliranTsu::URUSAN, 'peringkat' => [
                ['permohonan', 'Permohonan hak milik kekal', 'mais', 14, ['borang_permohonan'], ['fi_permohonan'], false, null, 'Daftar permohonan (rujuk rekod MOD-HKM bagi hak milik sementara).'],
                ['kelulusan_mais', 'Kelulusan dalaman MAIS', 'mais', 14, [], [], true, null, 'Kelulusan dalaman.'],
                ['ukur_halus', 'Ukur halus JUPEM', 'jupem', 180, ['pelan_ukur'], ['upah_ukur'], false, null, 'Ukur halus dan penyediaan pelan akui oleh JUPEM.'],
                ['pengesahan_hasil_ukur', 'Pengesahan hasil ukur (GIS)', 'mais', 14, ['pelan_akui'], ['fi_pelan'], false, 'cadang_geometri', 'Import pelan akui, banding keluasan dan cadang geometri kepada MOD-PRO.'],
                ['bayaran', 'Bayaran fi pendaftaran', 'mais', 30, ['resit'], ['fi_pendaftaran'], false, 'perlu_kos_dibayar', 'Jelaskan fi.'],
                ['daftar_hak_milik_kekal', 'Pendaftaran hak milik kekal', 'pejabat_tanah', 60, ['hak_milik'], [], false, null, 'Hak milik kekal didaftarkan (MOD-HKM).'],
            ]],
        ];
    }

    /** @return list<array{kod: string, nama: string}> */
    public static function senarai(array $map): array
    {
        $out = [];
        foreach ($map as $kod => $nama) {
            $out[] = ['kod' => (string) $kod, 'nama' => is_array($nama) ? (string) $nama[0] : (string) $nama];
        }

        return $out;
    }
}
