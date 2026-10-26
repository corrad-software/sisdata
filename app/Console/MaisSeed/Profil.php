<?php

namespace App\Console\MaisSeed;

/**
 * Per property type: how the SYNTHETIC parts of the demo are shaped —
 * land category, zoning, express condition, plausible parcel size when only
 * a point is public, physical-asset category and what is let out (MOD-PJK).
 */
final class Profil
{
    /**
     * jenis => [kategori_guna_tanah, zoning (≤50), syarat nyata, [luas min m², max m²], kategori AST, sewaan: list<[kategori_hartanah, jenis_aset, min, max unit, kadar RM min, max]>]
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: array{0: int, 1: int}, 4: string, 5: list<array{0: string, 1: string, 2: int, 3: int, 4: int, 5: int}>}>
     */
    public const JENIS = [
        'masjid' => ['BANGUNAN', 'Institusi dan Kemudahan Masyarakat', 'Tapak masjid dan kemudahan berkaitan', [4000, 16000], 'MASJID_SURAU', [['bazar', 'gerai', 2, 5, 150, 450]]],
        'surau' => ['BANGUNAN', 'Institusi dan Kemudahan Masyarakat', 'Tapak surau', [600, 2500], 'MASJID_SURAU', []],
        'sekolah_agama' => ['BANGUNAN', 'Institusi dan Kemudahan Masyarakat', 'Tapak sekolah agama', [4000, 20000], 'SEKOLAH', []],
        'institusi_pendidikan' => ['BANGUNAN', 'Institusi dan Kemudahan Masyarakat', 'Bangunan institusi pendidikan', [20000, 160000], 'SEKOLAH', [['premis', 'bangunan', 1, 2, 2500, 9000]]],
        'bangunan_pejabat' => ['BANGUNAN', 'Perniagaan', 'Bangunan pejabat dan perniagaan', [2500, 14000], 'BANGUNAN_PEJABAT', [['premis', 'bangunan', 2, 6, 3500, 18000]]],
        'komersial' => ['BANGUNAN', 'Perniagaan', 'Bangunan perniagaan (rumah kedai)', [400, 9000], 'KOMPLEKS', [['premis_perniagaan', 'unit_kedai', 3, 8, 1200, 6500]]],
        'hotel' => ['BANGUNAN', 'Perniagaan', 'Bangunan hotel dan perniagaan', [3000, 12000], 'KOMPLEKS', [['premis', 'bangunan', 1, 1, 45000, 120000], ['premis_perniagaan', 'unit_kedai', 1, 3, 2500, 7000]]],
        'kediaman' => ['BANGUNAN', 'Kediaman', 'Bangunan kediaman', [250, 3500], 'KEDIAMAN', [['premis_kediaman', 'rumah', 1, 4, 450, 1600]]],
        'klinik' => ['BANGUNAN', 'Institusi dan Kemudahan Masyarakat', 'Bangunan klinik / pusat rawatan', [800, 6000], 'BANGUNAN_PEJABAT', [['premis', 'bangunan', 1, 1, 4500, 25000]]],
        'pusat_islam' => ['BANGUNAN', 'Institusi dan Kemudahan Masyarakat', 'Bangunan institusi agama dan dewan', [2000, 12000], 'KEMUDAHAN_AWAM', [['premis', 'bangunan', 1, 2, 1500, 6000], ['bazar', 'gerai', 1, 3, 150, 450]]],
        'kubur' => ['BANGUNAN', 'Institusi dan Kemudahan Masyarakat', 'Tanah perkuburan Islam', [6000, 40000], 'KEMUDAHAN_AWAM', []],
        'tanah_kosong' => ['BANGUNAN', 'Tanah Lapang dan Rekreasi', 'Tanah kosong untuk pembangunan', [4000, 60000], 'STRUKTUR', [['tanah', 'tanah', 1, 1, 800, 6000]]],
        'pertanian' => ['PERTANIAN', 'Pertanian', 'Tanaman kelapa sawit / dusun', [20000, 400000], 'STRUKTUR', [['tanah', 'tanah', 1, 1, 600, 5000]]],
        'lain' => ['BANGUNAN', 'Institusi dan Kemudahan Masyarakat', 'Bangunan institusi', [1000, 8000], 'BANGUNAN_PEJABAT', []],
    ];

    /** Types whose premises are mostly let (commercial demo story). */
    public const KOMERSIAL = ['bangunan_pejabat', 'komersial', 'hotel', 'kediaman', 'klinik', 'tanah_kosong', 'pertanian'];

    /** @return array{0: string, 1: string, 2: string, 3: array{0: int, 1: int}, 4: string, 5: list<array{0: string, 1: string, 2: int, 3: int, 4: int, 5: int}>} */
    public static function untuk(string $jenis): array
    {
        return self::JENIS[$jenis] ?? self::JENIS['lain'];
    }
}
