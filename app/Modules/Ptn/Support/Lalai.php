<?php

namespace App\Modules\Ptn\Support;

/**
 * MOD-PTN code lists (Malay labels) and seeded defaults. Configurable values
 * live in the database (templates, frequencies, thresholds) — these are only
 * the defaults the seed migration writes and the closed code sets.
 */
final class Lalai
{
    public const TAHAP_RISIKO = ['rendah' => 'Rendah', 'sederhana' => 'Sederhana', 'tinggi' => 'Tinggi', 'kritikal' => 'Kritikal'];

    public const STATUS_JADUAL = [
        'dirancang' => 'Dirancang', 'ditugaskan' => 'Ditugaskan', 'dalam_pelaksanaan' => 'Dalam pelaksanaan',
        'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan',
    ];

    public const STATUS_LAWATAN = [
        'draf' => 'Draf', 'dihantar' => 'Dihantar (menunggu pengesahan)', 'konflik' => 'Konflik penyegerakan',
        'dikembalikan' => 'Dikembalikan untuk pembetulan', 'disahkan' => 'Disahkan (dikunci)',
    ];

    public const JENIS_PEMERIKSAAN = ['rutin' => 'Rutin (berkala)', 'susulan' => 'Susulan isu', 'aduan' => 'Berikutan aduan', 'khas' => 'Khas'];

    public const STATUS_ITEM = ['baik' => 'Baik', 'isu' => 'Isu', 'kritikal' => 'Kritikal'];

    public const KATEGORI_ITEM = [
        'keadaan_fizikal' => 'Keadaan fizikal tapak',
        'tanda_pencerobohan' => 'Tanda pencerobohan',
        'penggunaan_semasa' => 'Penggunaan semasa berbanding kategori guna tanah',
        'keadaan_aset' => 'Keadaan aset di atas lot',
        'akses_sempadan' => 'Akses dan sempadan',
    ];

    public const KEADAAN_TAPAK = ['baik' => 'Baik', 'sederhana' => 'Sederhana', 'buruk' => 'Buruk', 'kritikal' => 'Kritikal', 'terbiar' => 'Terbiar'];

    public const KATEGORI_ISU = [
        'pencerobohan' => 'Pencerobohan tanah',
        'pendudukan_haram' => 'Pendudukan tanpa kebenaran',
        'binaan_haram' => 'Binaan tanpa kebenaran',
        'pembuangan_sampah' => 'Pembuangan sampah / sisa',
        'guna_tanah_tidak_selaras' => 'Penggunaan tidak selaras kategori guna tanah',
        'tanah_terbiar' => 'Tanah terbiar',
        'aset_rosak' => 'Aset / bangunan rosak',
        'penyelenggaraan' => 'Keperluan penyelenggaraan',
        'sempadan' => 'Sempadan diusik / dipertikai',
        'akses' => 'Akses terhalang',
        'lain' => 'Lain-lain',
    ];

    /**
     * Finding categories that open a complaint in MOD-ADU automatically
     * (SRS-PTN-17/18), mapped to the MOD-REF complaint category.
     */
    public const KE_ADUAN = [
        'pencerobohan' => 'PENCEROBOHAN',
        'pendudukan_haram' => 'PENCEROBOHAN',
        'binaan_haram' => 'BINAAN_HARAM',
        'pembuangan_sampah' => 'PEMBUANGAN_SAMPAH',
        'aset_rosak' => 'KEROSAKAN_ASET',
        'sempadan' => 'PERTIKAIAN_SEMPADAN',
        'lain' => 'LAIN',
    ];

    /** Categories counted as encroachment for the risk score and the encroachment report. */
    public const PENCEROBOHAN = ['pencerobohan', 'pendudukan_haram', 'binaan_haram'];

    public const TAHAP_KETERUKAN = ['rendah' => 'Rendah', 'sederhana' => 'Sederhana', 'tinggi' => 'Tinggi', 'kritikal' => 'Kritikal'];

    public const STATUS_ISU = ['terbuka' => 'Terbuka', 'dalam_tindakan' => 'Dalam tindakan', 'selesai' => 'Selesai', 'ditutup' => 'Ditutup'];

    public const STATUS_TINDAKAN = [
        'belum_mula' => 'Belum mula', 'dalam_tindakan' => 'Dalam tindakan',
        'menunggu_pengesahan' => 'Menunggu pengesahan penyelesaian', 'selesai' => 'Selesai (disahkan)',
    ];

    public const WARNA = ['hijau' => 'Hijau — normal / dalam tempoh', 'kuning' => 'Kuning — perlu perhatian / hampir tarikh akhir', 'merah' => 'Merah — kritikal / melepasi tarikh akhir'];

    public const HEX = ['hijau' => '#16A34A', 'kuning' => '#EAB308', 'merah' => '#DC2626'];

    /** SRS-PTN-11: six risk categories with default weights (sum 100). */
    public const KATEGORI_RISIKO = [
        'pencerobohan' => ['Pencerobohan', 30],
        'tunggakan_cukai' => ['Tunggakan cukai', 20],
        'pertikaian' => ['Pertikaian / kes mahkamah', 20],
        'dokumen' => ['Dokumen tidak lengkap', 10],
        'tamat_kontrak' => ['Tamat tempoh kontrak', 10],
        'potensi_hasil' => ['Potensi hasil tidak dimanfaatkan', 10],
    ];

    /** Threshold categories besides the six risk categories. */
    public const KATEGORI_AMBANG_LAIN = [
        'keseluruhan' => 'Skor risiko keseluruhan',
        'tindakan' => 'Status tindakan pembetulan (tempoh kelewatan)',
        'lawatan' => 'Status lawatan berjadual (tempoh kelewatan)',
    ];

    public const JENIS_PENYELENGGARAAN = [
        'pembersihan' => 'Pembersihan / tebas', 'pagar' => 'Pembaikan pagar', 'papan_tanda' => 'Papan tanda MAIS',
        'bangunan' => 'Pembaikan bangunan / struktur', 'saliran' => 'Saliran', 'sempadan' => 'Penandaan semula sempadan', 'lain' => 'Lain-lain',
    ];

    /** SRS-PTN-08: lot fields an inspection may propose to change (applied only after approval). */
    public const MEDAN_CADANGAN = [
        'kategori_guna_tanah' => 'Kategori guna tanah',
        'zoning' => 'Zoning',
        'status_lot' => 'Status lot',
        'syarat_nyata' => 'Syarat nyata',
    ];

    /** Default visits per year by risk level (category "*"); PERTANIAN rendah gets one visit. */
    public const KEKERAPAN = [
        ['*', 'rendah', 1], ['*', 'sederhana', 2], ['*', 'tinggi', 4], ['*', 'kritikal', 12],
        ['BANGUNAN', 'rendah', 2], ['BANGUNAN', 'sederhana', 4],
    ];

    /** @return list<array{kod: string, kategori: string, label: string, kategori_isu: string}> */
    public static function itemRutin(): array
    {
        $i = fn (string $kod, string $kategori, string $label, string $isu) => ['kod' => $kod, 'kategori' => $kategori, 'label' => $label, 'kategori_isu' => $isu];

        return [
            $i('FIZ-01', 'keadaan_fizikal', 'Keadaan permukaan tanah (semak samun, hakisan, takungan air)', 'penyelenggaraan'),
            $i('FIZ-02', 'keadaan_fizikal', 'Kebersihan tapak (tiada sampah / sisa binaan)', 'pembuangan_sampah'),
            $i('CER-01', 'tanda_pencerobohan', 'Tiada binaan atau struktur tanpa kebenaran', 'binaan_haram'),
            $i('CER-02', 'tanda_pencerobohan', 'Tiada pendudukan / aktiviti tanpa kebenaran (bercucuk tanam, penghuni, letak kenderaan)', 'pendudukan_haram'),
            $i('CER-03', 'tanda_pencerobohan', 'Tiada pengambilan tanah oleh lot bersebelahan', 'pencerobohan'),
            $i('GUN-01', 'penggunaan_semasa', 'Penggunaan semasa selaras dengan kategori guna tanah', 'guna_tanah_tidak_selaras'),
            $i('GUN-02', 'penggunaan_semasa', 'Tanah dimanfaatkan (tidak terbiar)', 'tanah_terbiar'),
            $i('AST-01', 'keadaan_aset', 'Keadaan bangunan / struktur di atas lot', 'aset_rosak'),
            $i('AST-02', 'keadaan_aset', 'Kemudahan tapak (pagar, papan tanda MAIS, lampu)', 'penyelenggaraan'),
            $i('SEM-01', 'akses_sempadan', 'Batu / tanda sempadan wujud dan tidak diusik', 'sempadan'),
            $i('SEM-02', 'akses_sempadan', 'Laluan masuk ke lot boleh diakses', 'akses'),
        ];
    }

    /** @return list<array{kod: string, kategori: string, label: string, kategori_isu: string}> */
    public static function itemPertanian(): array
    {
        $i = fn (string $kod, string $kategori, string $label, string $isu) => ['kod' => $kod, 'kategori' => $kategori, 'label' => $label, 'kategori_isu' => $isu];

        return [
            ...array_values(array_filter(self::itemRutin(), fn ($r) => $r['kod'] !== 'AST-01')),
            $i('TAN-01', 'penggunaan_semasa', 'Tanaman / ternakan di atas lot dibenarkan (pajakan / sewaan sah)', 'pendudukan_haram'),
            $i('TAN-02', 'keadaan_fizikal', 'Tiada pembakaran terbuka atau pencemaran', 'lain'),
        ];
    }
}
