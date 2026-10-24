<?php

namespace App\Modules\Kes\Support;

/**
 * Fixed code lists of MOD-KES with their Malay labels. Case types and status
 * stages are NOT here — they are configurable (kes_jenis / kes_status,
 * SRS-KES-01/07) and read through Services\Rujukan.
 */
final class Kod
{
    public const PERINGKAT_MAHKAMAH = [
        'majistret' => 'Mahkamah Majistret',
        'sesyen' => 'Mahkamah Sesyen',
        'tinggi' => 'Mahkamah Tinggi',
        'rayuan' => 'Mahkamah Rayuan',
        'persekutuan' => 'Mahkamah Persekutuan',
        'syariah_rendah' => 'Mahkamah Rendah Syariah',
        'syariah_tinggi' => 'Mahkamah Tinggi Syariah',
        'syariah_rayuan' => 'Mahkamah Rayuan Syariah',
        'tribunal' => 'Tribunal / Pentadbiran Tanah',
    ];

    public const PERANAN = [
        'plaintif' => 'Plaintif',
        'defendan' => 'Defendan',
        'pihak_ketiga' => 'Pihak ketiga',
        'pencelah' => 'Pencelah',
        'pemohon' => 'Pemohon',
        'responden' => 'Responden',
    ];

    public const JENIS_PIHAK = ['individu' => 'Individu', 'syarikat' => 'Syarikat / pertubuhan', 'agensi' => 'Agensi kerajaan', 'mais' => 'MAIS'];

    public const STATUS_WAKILAN = ['diwakili' => 'Diwakili peguam', 'tidak_diwakili' => 'Tidak diwakili', 'sendiri' => 'Mewakili diri sendiri'];

    public const JENIS_PROSIDING = [
        'sebutan' => 'Sebutan',
        'bicara' => 'Perbicaraan',
        'pengurusan_kes' => 'Pengurusan kes',
        'pendengaran' => 'Pendengaran',
        'keputusan' => 'Keputusan',
        'pengemukaan_dokumen' => 'Pengemukaan dokumen',
        'rayuan' => 'Pendengaran rayuan',
        'lain' => 'Lain-lain',
    ];

    public const STATUS_PROSIDING = ['dijadualkan' => 'Dijadualkan', 'selesai' => 'Selesai', 'ditangguh' => 'Ditangguhkan', 'dibatalkan' => 'Dibatalkan'];

    /** Proceedings reminded 14/7/1 days ahead (SRS-KES-10: perbicaraan, sebutan, pengemukaan dokumen…). */
    public const PROSIDING_DIPERINGAT = ['sebutan', 'bicara', 'pengurusan_kes', 'pendengaran', 'keputusan', 'pengemukaan_dokumen', 'rayuan'];

    public const JENIS_TARIKH = ['pengemukaan_dokumen' => 'Pengemukaan dokumen', 'had_rayuan' => 'Had pemfailan rayuan', 'had_pematuhan' => 'Had pematuhan perintah', 'lain' => 'Lain-lain'];

    public const KATEGORI_KOS = ['fi_peguam' => 'Fi peguam', 'kos_mahkamah' => 'Kos mahkamah', 'kos_berkaitan' => 'Kos berkaitan'];

    public const STATUS_BAYARAN = [
        'belum_dibayar' => 'Belum dibayar',
        'menunggu_kelulusan' => 'Menunggu kelulusan',
        'diluluskan' => 'Diluluskan',
        'dihantar_grp' => 'Dihantar ke GRP',
        'dibayar' => 'Dibayar (disahkan GRP)',
        'ditolak' => 'Ditolak',
    ];

    public const JENIS_KEPUTUSAN = [
        'menang' => 'Menang',
        'kalah' => 'Kalah',
        'selesai' => 'Selesai',
        'penyelesaian_luar_mahkamah' => 'Penyelesaian luar mahkamah',
        'ditolak' => 'Ditolak / dibatalkan',
    ];

    public const JENIS_TINDAKAN = [
        'pematuhan_perintah' => 'Pematuhan perintah mahkamah',
        'penguatkuasaan_keputusan' => 'Penguatkuasaan keputusan',
        'pemfailan_rayuan' => 'Pertimbangan / pemfailan rayuan',
        'pengemaskinian_hak_milik' => 'Pengemaskinian rekod hak milik / status tanah',
        'lain' => 'Lain-lain',
    ];

    /** SRS-KES-17 default follow-ups per decision type. */
    public const SUSULAN_LALAI = [
        'menang' => ['penguatkuasaan_keputusan', 'pengemaskinian_hak_milik'],
        'kalah' => ['pemfailan_rayuan', 'pematuhan_perintah'],
        'selesai' => ['pematuhan_perintah'],
        'penyelesaian_luar_mahkamah' => ['pematuhan_perintah'],
        'ditolak' => ['pemfailan_rayuan'],
    ];

    public const STATUS_TINDAKAN = ['dibuka' => 'Dibuka', 'dalam_tindakan' => 'Dalam tindakan (tugasan WFE)', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];

    public const STATUS_RAYUAN = [
        'cadangan' => 'Cadangan', 'menunggu_kelulusan' => 'Menunggu kelulusan', 'ditolak' => 'Tidak diluluskan',
        'difailkan' => 'Difailkan', 'diputuskan' => 'Diputuskan', 'ditarik_balik' => 'Ditarik balik',
    ];

    public const STATUS_PENYELESAIAN = [
        'cadangan' => 'Cadangan', 'menunggu_kelulusan' => 'Menunggu kelulusan', 'diluluskan' => 'Diluluskan',
        'ditolak' => 'Tidak diluluskan', 'dimeterai' => 'Dimeterai',
    ];

    public const STATUS_LANTIKAN = ['aktif' => 'Aktif', 'menunggu_kelulusan' => 'Menunggu kelulusan MAIS', 'ditolak' => 'Tidak diluluskan', 'tamat' => 'Tamat'];

    public const KATEGORI_DOKUMEN = [
        'surat_tuntutan' => 'Surat tuntutan',
        'pliding' => 'Pliding',
        'afidavit' => 'Afidavit',
        'perintah_mahkamah' => 'Perintah mahkamah',
        'penghakiman' => 'Penghakiman',
        'perjanjian_penyelesaian' => 'Perjanjian penyelesaian',
        'laporan_peguam' => 'Laporan peguam',
        'dokumen_aduan' => 'Dokumen aduan / pemantauan',
        'lain' => 'Lain-lain',
    ];

    public const PERANAN_LOT = ['subjek' => 'Subjek pertikaian', 'berkaitan' => 'Berkaitan', 'terjejas' => 'Terjejas'];

    public const JENIS_LOG = [
        'lihat' => 'Paparan rekod', 'lihat_senarai' => 'Paparan senarai', 'lihat_pihak' => 'Paparan butiran pihak',
        'muat_turun' => 'Muat turun dokumen', 'cetak' => 'Cetakan', 'eksport' => 'Eksport', 'ditolak' => 'Capaian ditolak',
    ];

    /** @param array<string, string> $list */
    public static function papar(array $list, ?string $kod): ?string
    {
        return $kod === null ? null : ($list[$kod] ?? $kod);
    }

    /**
     * @param  array<string, string>  $list
     * @return list<array{kod: string, nama: string}>
     */
    public static function senarai(array $list): array
    {
        return array_map(fn ($k, $v) => ['kod' => (string) $k, 'nama' => $v], array_keys($list), $list);
    }
}
