<?php

namespace App\Modules\Ast\Support;

/** MOD-AST code lists with their Malay labels (SRS-GEN-09). Categories themselves are configurable rows (aset_kategori). */
final class Lalai
{
    public const JENIS_ASET = [
        'bangunan' => 'Bangunan', 'struktur' => 'Struktur', 'infrastruktur' => 'Infrastruktur', 'kemudahan' => 'Kemudahan',
        'peralatan' => 'Peralatan', 'pagar' => 'Pagar', 'papan_tanda' => 'Papan tanda', 'lain' => 'Lain-lain',
    ];

    /** SRS-AST-05 condition scale. */
    public const KONDISI = ['baik' => 'Baik', 'perlu_perhatian' => 'Perlu perhatian', 'kritikal' => 'Kritikal'];

    public const WARNA_KONDISI = ['baik' => '#16A34A', 'perlu_perhatian' => '#EAB308', 'kritikal' => '#DC2626'];

    public const STATUS_ASET = [
        'aktif' => 'Aktif', 'dalam_penyelenggaraan' => 'Dalam penyelenggaraan', 'tidak_aktif' => 'Tidak aktif',
        'dilupuskan' => 'Dilupuskan', 'dihapus_kira' => 'Dihapus kira', 'dirobohkan' => 'Dirobohkan',
    ];

    public const STATUS_KELULUSAN = ['lulus' => 'Diluluskan', 'menunggu' => 'Menunggu kelulusan perolehan', 'ditolak' => 'Perolehan ditolak'];

    public const UNIT_UKURAN = ['m2' => 'meter persegi', 'm' => 'meter', 'unit' => 'unit', 'ekar' => 'ekar', 'hektar' => 'hektar'];

    /** Includes MOD-PTN's maintenance-need codes so needs map 1:1. */
    public const JENIS_PENYELENGGARAAN = [
        'pemeriksaan_struktur' => 'Pemeriksaan struktur', 'bumbung' => 'Bumbung dan talang', 'elektrik' => 'Pendawaian elektrik',
        'paip_sanitasi' => 'Paip dan sanitasi', 'pendingin_hawa' => 'Pendingin hawa', 'cat' => 'Mengecat', 'landskap' => 'Landskap',
        'pembersihan' => 'Pembersihan / tebas', 'keselamatan_kebakaran' => 'Sistem keselamatan kebakaran', 'lif' => 'Lif / eskalator',
        'pagar' => 'Pembaikan pagar', 'papan_tanda' => 'Papan tanda MAIS', 'jalan_parkir' => 'Jalan dan tempat letak kereta',
        'saliran' => 'Saliran', 'sempadan' => 'Penandaan semula sempadan', 'bangunan' => 'Pembaikan bangunan / struktur',
        'servis_peralatan' => 'Servis peralatan', 'lain' => 'Lain-lain',
    ];

    public const JENIS_KERJA = ['pencegahan' => 'Pencegahan (berjadual)', 'pembaikan' => 'Pembaikan (korektif)', 'kecemasan' => 'Kecemasan'];

    public const KEUTAMAAN = ['rendah' => 'Rendah', 'sederhana' => 'Sederhana', 'tinggi' => 'Tinggi', 'kritikal' => 'Kritikal'];

    public const STATUS_KERJA = [
        'dirancang' => 'Dirancang', 'menunggu_kelulusan' => 'Menunggu kelulusan', 'diluluskan' => 'Diluluskan',
        'dalam_pelaksanaan' => 'Dalam pelaksanaan', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak', 'dibatalkan' => 'Dibatalkan',
    ];

    public const KERJA_TERBUKA = ['dirancang', 'menunggu_kelulusan', 'diluluskan', 'dalam_pelaksanaan'];

    public const SUMBER_KERJA = ['manual' => 'Manual', 'jadual' => 'Jadual berkala', 'ptn' => 'Pemantauan Tanah (MOD-PTN)', 'pemeriksaan' => 'Pemeriksaan aset'];

    public const KAEDAH_PEMERIKSAAN = ['pwa' => 'Aplikasi lapangan (PWA)', 'manual' => 'Manual', 'qr' => 'Imbasan QR'];

    public const KAEDAH_PENILAIAN = [
        'laporan_jpph' => 'Laporan penilaian JPPH', 'penilai_berdaftar' => 'Penilai berdaftar', 'penilaian_dalaman' => 'Penilaian dalaman MAIS',
        'kos_perolehan' => 'Kos perolehan', 'susut_nilai' => 'Nilai buku (susut nilai garis lurus)',
    ];

    public const KATEGORI_GAMBAR = ['umum' => 'Umum', 'pemeriksaan' => 'Pemeriksaan', 'sebelum' => 'Sebelum kerja', 'selepas' => 'Selepas kerja'];

    /** SRS-AST-22 supporting document categories (DMS). */
    public const KATEGORI_DOKUMEN = [
        'pelan_bangunan' => 'Pelan bangunan', 'perakuan_siap' => 'Perakuan siap dan pematuhan (CCC)', 'permit' => 'Permit / lesen',
        'polisi_insurans' => 'Polisi insurans', 'dokumen_pemilikan' => 'Dokumen pemilikan', 'jaminan' => 'Sijil jaminan / waranti',
        'invois' => 'Invois / resit', 'laporan_penilaian' => 'Laporan penilaian', 'lain' => 'Lain-lain',
    ];

    public const JENIS_PELUPUSAN = ['pelupusan' => 'Pelupusan', 'hapus_kira' => 'Hapus kira', 'roboh' => 'Perobohan'];

    public const KAEDAH_PELUPUSAN = ['jualan' => 'Jualan', 'tender' => 'Tender', 'sebut_harga' => 'Sebut harga', 'musnah' => 'Pemusnahan', 'pindahan' => 'Pindahan', 'lain' => 'Lain-lain'];

    public const STATUS_PELUPUSAN = ['menunggu' => 'Menunggu kelulusan', 'lulus' => 'Diluluskan', 'tolak' => 'Ditolak', 'ditarik_balik' => 'Ditarik balik'];

    /** Asset status after an approved disposal of each type. */
    public const STATUS_SELEPAS_PELUPUSAN = ['pelupusan' => 'dilupuskan', 'hapus_kira' => 'dihapus_kira', 'roboh' => 'dirobohkan'];

    /** SRS-AST-07 label templates: kod => [label, width mm, height mm, qr mm, labels per A4 row, rows]. */
    public const TEMPLAT_LABEL = [
        'kecil' => ['Kecil 50 × 30 mm (tag peralatan)', 50, 30, 22, 4, 9],
        'sederhana' => ['Sederhana 70 × 50 mm', 70, 50, 34, 2, 5],
        'besar' => ['Besar 100 × 70 mm (bangunan / pagar)', 100, 70, 50, 2, 3],
    ];

    /** Field-inspection checklist (SRS-AST-20). */
    public const SENARAI_SEMAK = [
        'struktur' => 'Struktur / dinding / lantai', 'bumbung' => 'Bumbung dan siling', 'elektrik' => 'Elektrik dan lampu',
        'paip' => 'Paip, tandas dan saliran', 'keselamatan' => 'Keselamatan (pemadam api, laluan kecemasan)',
        'kebersihan' => 'Kebersihan dan landskap', 'papan_tanda' => 'Papan tanda / label QR', 'pagar' => 'Pagar dan pintu pagar',
    ];
}
