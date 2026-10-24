<?php

namespace App\Modules\Cki\Support;

/** MOD-CKI code lists (Malay labels) — served to the frontend by /api/v1/cki/rujukan. */
final class Kod
{
    public const JENIS_CUKAI = [
        'tanah' => 'Cukai tanah',
        'petak' => 'Cukai petak',
        'taksiran_pbt' => 'Cukai taksiran PBT',
        'premium' => 'Premium',
        'fi' => 'Fi',
        'lain' => 'Bayaran lain berkaitan tanah',
    ];

    /** Default issuer per tax type. */
    public const PENGELUAR_LALAI = ['tanah' => 'ptd', 'petak' => 'ptd', 'taksiran_pbt' => 'pbt', 'premium' => 'ptd', 'fi' => 'ptd', 'lain' => 'ptd'];

    public const PIHAK_PENGELUAR = [
        'ptd' => 'Pejabat Tanah Daerah',
        'pbt' => 'Pihak Berkuasa Tempatan',
        'ptg' => 'Pejabat Tanah dan Galian (PTG)',
        'lain' => 'Lain-lain (LHDN / agensi berkaitan)',
    ];

    public const STATUS_BAYARAN = [
        'belum_bayar' => 'Belum dibayar',
        'dalam_proses' => 'Dalam proses bayaran',
        'tertunggak' => 'Tertunggak',
        'dibayar' => 'Telah dibayar',
    ];

    public const SUMBER_REKOD = ['api' => 'API berpusat', 'manual' => 'Manual (sandaran)', 'import' => 'Import Excel'];

    public const KAEDAH_BAYARAN = [
        'eft' => 'EFT',
        'bank_draf' => 'Bank draf',
        'fpx' => 'FPX',
        'kaunter_ptd' => 'Kaunter Pejabat Tanah daerah',
        'kaunter_pbt' => 'Kaunter PBT',
    ];

    /** SRS-CKI-12: fields each payment method must carry when the payment is recorded. */
    public const MEDAN_KAEDAH = [
        'eft' => ['no_rujukan_eft'],
        'bank_draf' => ['no_bank_draf', 'bank_penerbit'],
        'fpx' => ['rujukan_fpx'],
        'kaunter_ptd' => ['no_resit', 'nama_kaunter'],
        'kaunter_pbt' => ['no_resit', 'nama_kaunter'],
    ];

    /** Methods paid through a GRP payment request (voucher) rather than directly. */
    public const MELALUI_GRP = ['eft', 'bank_draf'];

    public const STATUS_TRANSAKSI = [
        'menunggu_kelulusan' => 'Menunggu kelulusan',
        'diluluskan' => 'Diluluskan — menunggu bayaran',
        'ditolak' => 'Ditolak',
        'dihantar_grp' => 'Dihantar ke GRP MAIS',
        'dibayar' => 'Dibayar — menunggu resit',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
    ];

    public const PERINGKAT = [
        'kewangan' => 'Bahagian Kewangan',
        'ketua_bahagian' => 'Ketua Bahagian',
        'pengurusan_atasan' => 'Pengurusan Atasan',
    ];

    public const PERINGKAT_TAHAP = ['kewangan' => 1, 'ketua_bahagian' => 2, 'pengurusan_atasan' => 3];

    public const KUMPULAN_UMUR = [
        '1_30' => '1–30 hari',
        '31_90' => '31–90 hari',
        '91_365' => '91–365 hari',
        'lebih_365' => 'Lebih 1 tahun',
    ];

    public const JENIS_SISIHAN = [
        'penyelarasan' => 'Rekod penyelarasan GRP',
        'bil_tanpa_resit' => 'Bil dibayar tanpa resit',
        'resit_tanpa_bil' => 'Resit tanpa bil',
        'beza_amaun_resit' => 'Perbezaan amaun bil dan resit',
        'beza_amaun_grp' => 'Perbezaan amaun sistem dan GRP MAIS',
        'tiada_dalam_grp' => 'Bayaran tiada dalam GRP MAIS',
    ];

    /** CKI document kind => [label, MOD-DMS category]. The kind travels as metadata `jenis_dokumen_cki`. */
    public const KATEGORI_DOKUMEN = [
        'bil' => ['Bil cukai', 'resit'],
        'resit' => ['Resit / bukti bayaran', 'resit'],
        'baucar' => ['Baucar bayaran', 'resit'],
        'permohonan' => ['Dokumen permohonan bayaran', 'surat_rasmi'],
        'kelulusan' => ['Dokumen kelulusan', 'surat_rasmi'],
    ];

    /** @param array<string, string> $map
     * @return list<array{kod: string, nama: string}> */
    public static function list(array $map): array
    {
        return array_map(fn ($k, $v) => ['kod' => (string) $k, 'nama' => $v], array_keys($map), $map);
    }
}
