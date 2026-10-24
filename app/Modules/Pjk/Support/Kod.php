<?php

namespace App\Modules\Pjk\Support;

/** MOD-PJK code lists with their Malay labels (SRS-GEN-09). */
final class Kod
{
    public const STATUS_KONTRAK = [
        'draf' => 'Draf',
        'dalam_kelulusan' => 'Dalam kelulusan',
        'aktif' => 'Aktif',
        'hampir_tamat' => 'Hampir tamat',
        'tamat_tempoh' => 'Tamat tempoh',
        'diperbaharui' => 'Diperbaharui',
        'ditamatkan' => 'Ditamatkan',
    ];

    /** Statuses in which the contract earns rent and the premise is occupied. */
    public const BERKUAT_KUASA = ['aktif', 'hampir_tamat'];

    public const STATUS_PREMIS = [
        'kosong' => 'Kosong',
        'disewa' => 'Telah disewa',
        'penyelenggaraan' => 'Dalam penyelenggaraan',
        'penguatkuasaan' => 'Dalam tindakan penguatkuasaan',
        'tidak_tersedia' => 'Tidak tersedia',
    ];

    public const KATEGORI_HARTANAH = [
        'premis' => 'Premis',
        'premis_perniagaan' => 'Premis perniagaan',
        'premis_kediaman' => 'Premis kediaman',
        'tanah' => 'Tanah',
        'bazar' => 'Bazar',
    ];

    public const JENIS_ASET = [
        'tanah' => 'Tanah',
        'bangunan' => 'Bangunan',
        'unit_kedai' => 'Unit kedai',
        'gerai' => 'Gerai',
        'rumah' => 'Rumah',
    ];

    public const JENIS_ENTITI = [
        'individu' => 'Individu',
        'syarikat' => 'Syarikat',
        'pertubuhan' => 'Pertubuhan / koperasi',
        'agensi' => 'Agensi kerajaan',
    ];

    public const JENIS_PERJANJIAN = [
        'sewaan' => 'Perjanjian sewaan',
        'pajakan' => 'Perjanjian pajakan',
        'lesen' => 'Lesen menduduki',
    ];

    public const KITARAN_CAJ = [
        'bulanan' => 'Bulanan',
        'suku_tahunan' => 'Suku tahunan',
        'tahunan' => 'Tahunan',
    ];

    /** Months per billing cycle. */
    public const BULAN_KITARAN = ['bulanan' => 1, 'suku_tahunan' => 3, 'tahunan' => 12];

    public const KOD_CAJ = [
        'sewa' => 'Sewa',
        'deposit' => 'Deposit',
        'penalti' => 'Penalti lewat bayar',
        'lain' => 'Caj lain',
    ];

    public const STATUS_CAJ = [
        'dijadualkan' => 'Dijadualkan',
        'belum_bayar' => 'Belum dibayar',
        'separa' => 'Dibayar separa',
        'dibayar' => 'Dibayar',
        'dikecualikan' => 'Dikecualikan',
        'dibatalkan' => 'Dibatalkan',
    ];

    public const JENIS_LEJAR = [
        'caj' => 'Caj',
        'bayaran' => 'Bayaran',
        'pelarasan_debit' => 'Pelarasan (debit)',
        'pelarasan_kredit' => 'Pelarasan (kredit)',
        'pembatalan' => 'Pembatalan bayaran',
        'pengecualian' => 'Pengecualian caj',
        'tolakan_deposit' => 'Tolakan deposit',
    ];

    public const SALURAN_BAYARAN = [
        'fpx' => 'FPX',
        'kad' => 'Kad kredit / debit',
        'kaunter' => 'Kaunter',
        'eft' => 'EFT',
        'pindahan_bank' => 'Pindahan bank',
        'cek' => 'Cek',
    ];

    public const SALURAN_DALAM_TALIAN = ['fpx', 'kad'];

    public const SALURAN_LUAR_TALIAN = ['kaunter', 'eft', 'pindahan_bank', 'cek'];

    public const STATUS_GATEWAY = [
        'menunggu' => 'Menunggu',
        'berjaya' => 'Berjaya',
        'gagal' => 'Gagal',
        'dibatalkan' => 'Dibatalkan',
        'tamat_masa' => 'Tamat masa',
    ];

    public const STATUS_BAYARAN = [
        'menunggu_pengesahan' => 'Menunggu pengesahan',
        'disahkan' => 'Disahkan',
        'ditolak' => 'Ditolak',
        'dibatalkan' => 'Dibatalkan',
    ];

    public const JENIS_PINDAAN = [
        'pembaharuan' => 'Pembaharuan perjanjian',
        'semakan_kadar' => 'Semakan kadar',
        'pindaan_terma' => 'Pindaan terma',
        'penamatan' => 'Penamatan perjanjian',
    ];

    public const JENIS_PELARASAN = [
        'pelarasan_debit' => 'Pelarasan debit (tambah caj)',
        'pelarasan_kredit' => 'Pelarasan kredit (kurangkan baki)',
        'pembatalan' => 'Pembatalan bayaran / resit',
        'pengecualian' => 'Pengecualian caj',
    ];

    public const STATUS_DEPOSIT = [
        'belum_diterima' => 'Belum diterima',
        'dipegang' => 'Dipegang',
        'dalam_penyelarasan' => 'Dalam penyelarasan',
        'dipulangkan' => 'Dipulangkan',
        'dilucut_hak' => 'Dilucut hak',
    ];

    public const JENIS_SISIHAN = [
        'tiada_lejar' => 'Transaksi berjaya tanpa rekod lejar',
        'tiada_penyelesaian' => 'Rekod lejar tanpa penyelesaian',
        'beza_amaun' => 'Perbezaan amaun',
        'beza_status' => 'Perbezaan status',
    ];

    public const STATUS_PERMOHONAN = [
        'diterima' => 'Diterima',
        'dalam_semakan' => 'Dalam semakan',
        'kontrak_disediakan' => 'Kontrak disediakan (dalam kelulusan)',
        'lulus' => 'Lulus',
        'tolak' => 'Ditolak',
        'dibatalkan' => 'Dibatalkan',
    ];

    public const JENIS_SURAT = [
        'tawaran' => 'Surat tawaran',
        'peringatan_bayaran' => 'Peringatan bayaran',
        'tamat_tempoh' => 'Notis tamat tempoh',
        'teguran' => 'Surat teguran',
        'penamatan' => 'Notis penamatan',
    ];

    public const STATUS_PEROLEHAN = ['wakaf' => 'Diwakafkan', 'belian' => 'Dibeli'];

    public const BAHAGIAN = ['hartanah' => 'Hartanah MAIS', 'wakaf' => 'Wakaf', 'baitulmal' => 'Baitulmal'];

    public const KUMPULAN_UMUR = ['1_30' => '1–30 hari', '31_60' => '31–60 hari', '61_90' => '61–90 hari', 'lebih_90' => '> 90 hari'];

    /** @param array<string, string> $list @return list<array{kod: string, nama: string}> */
    public static function options(array $list): array
    {
        return array_map(fn ($k, $v) => ['kod' => (string) $k, 'nama' => $v], array_keys($list), $list);
    }

    public static function umur(int $days): string
    {
        return match (true) {
            $days <= 30 => '1_30',
            $days <= 60 => '31_60',
            $days <= 90 => '61_90',
            default => 'lebih_90',
        };
    }
}
