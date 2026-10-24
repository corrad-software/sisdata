<?php

namespace App\Modules\Adu\Support;

/**
 * Seeded defaults and fixed vocabularies of MOD-ADU. Categories themselves
 * are MOD-REF's ref_kategori_aduan; everything here can be edited by admins
 * once seeded (subcategories, SLA, triage rules).
 */
final class Lalai
{
    /** SRS-ADU-06: intake channels. */
    public const SALURAN = [
        'dalaman' => 'Sistem (dalaman)',
        'portal' => 'Portal aduan awam',
        'kaunter' => 'Kaunter',
        'emel' => 'E-mel',
        'telefon' => 'Telefon',
        'lapangan' => 'Lapangan / mudah alih',
        'sistem_aduan_pusat' => 'Sistem Aduan berpusat MAIS',
        'ptn' => 'Pemantauan Tanah (MOD-PTN)',
        'tkt' => 'Tiket sokongan (MOD-TKT)',
    ];

    public const STATUS = [
        'baharu' => 'Baharu',
        'ditugaskan' => 'Ditugaskan',
        'dalam_siasatan' => 'Dalam siasatan',
        'dirujuk' => 'Dirujuk',
        'menunggu_kelulusan' => 'Menunggu kelulusan penutupan',
        'ditutup' => 'Ditutup',
        'pendua' => 'Pendua',
    ];

    public const STATUS_TERBUKA = ['baharu', 'ditugaskan', 'dalam_siasatan', 'dirujuk', 'menunggu_kelulusan'];

    public const STATUS_TUTUP = ['ditutup', 'pendua'];

    /** Status shown to the public (SRS-ADU-04): never internal detail. */
    public const STATUS_AWAM = [
        'baharu' => 'Aduan diterima',
        'ditugaskan' => 'Aduan telah ditugaskan kepada pegawai',
        'dalam_siasatan' => 'Siasatan sedang dijalankan',
        'dirujuk' => 'Siasatan sedang dijalankan',
        'menunggu_kelulusan' => 'Siasatan sedang dijalankan',
        'ditutup' => 'Aduan telah diselesaikan',
        'pendua' => 'Aduan digabungkan dengan aduan sedia ada',
    ];

    public const KEUTAMAAN = ['rendah' => 'Rendah', 'sederhana' => 'Sederhana', 'tinggi' => 'Tinggi', 'kritikal' => 'Kritikal'];

    public const KATEGORI_RESOLUSI = [
        'selesai_tindakan' => 'Selesai — tindakan diambil',
        'selesai_penguatkuasaan' => 'Selesai — tindakan penguatkuasaan',
        'dirujuk_perundangan' => 'Dirujuk ke tindakan perundangan',
        'dirujuk_agensi' => 'Dirujuk kepada agensi lain',
        'tiada_asas' => 'Tiada asas / tidak berasas',
        'bukan_tanah_mais' => 'Bukan tanah MAIS',
        'pendua' => 'Pendua aduan sedia ada',
        'ditarik_balik' => 'Ditarik balik oleh pengadu',
    ];

    public const JENIS_RUJUKAN = [
        'penguatkuasaan' => 'Bahagian Penguatkuasaan',
        'perundangan' => 'Bahagian Perundangan',
        'pengurusan_hartanah' => 'Bahagian Pengurusan Hartanah',
        'agensi_luar' => 'Agensi luar',
    ];

    /** Rough category → referral division used as the default in the referral form. */
    public const BAHAGIAN_KATEGORI = [
        'PENCEROBOHAN' => 'penguatkuasaan',
        'BINAAN_HARAM' => 'penguatkuasaan',
        'PERTIKAIAN_SEMPADAN' => 'perundangan',
        'ISU_PENYEWA' => 'pengurusan_hartanah',
        'TUNGGAKAN' => 'pengurusan_hartanah',
    ];

    public const JENIS_TINDAKAN = [
        'terima' => 'Aduan diterima',
        'triaj' => 'Triaj automatik',
        'kemas_kini' => 'Butiran dikemas kini',
        'tugas' => 'Ditugaskan',
        'tugas_semula' => 'Ditugaskan semula',
        'mula_siasatan' => 'Siasatan dimulakan',
        'tindakan' => 'Tindakan susulan',
        'catatan' => 'Catatan',
        'komunikasi' => 'Komunikasi dengan pengadu',
        'lawatan_dijadual' => 'Lawatan tapak dijadualkan',
        'lawatan_selesai' => 'Lawatan tapak selesai',
        'rujuk' => 'Dirujuk',
        'eskalasi' => 'Eskalasi',
        'dokumen' => 'Dokumen dilampirkan',
        'resolusi' => 'Resolusi direkod',
        'penutupan_diluluskan' => 'Penutupan diluluskan',
        'penutupan_ditolak' => 'Penutupan ditolak',
        'buka_semula' => 'Dibuka semula',
        'pendua' => 'Ditanda pendua',
        'bukan_tanah_mais' => 'Disahkan bukan tanah MAIS',
        'segerak_pusat' => 'Status disalurkan ke Sistem Aduan berpusat',
        'pembetulan' => 'Catatan pembetulan',
    ];

    /** SRS-ADU-08: initial subcategories (incl. kecurian & penghuni haram named by the SRS). */
    public const SUBKATEGORI = [
        'PENCEROBOHAN' => ['PENGHUNI_HARAM' => 'Penghuni haram', 'PERTANIAN_HARAM' => 'Pertanian / tanaman tanpa kebenaran', 'PENGGUNAAN_TANPA_IZIN' => 'Penggunaan tanah tanpa izin'],
        'BINAAN_HARAM' => ['BANGUNAN' => 'Bangunan / rumah', 'STRUKTUR_SEMENTARA' => 'Struktur sementara / gerai', 'PAGAR' => 'Pagar / tembok'],
        'KEROSAKAN_ASET' => ['BANGUNAN_ROSAK' => 'Bangunan rosak', 'KECURIAN' => 'Kecurian aset / bahan', 'VANDALISME' => 'Vandalisme'],
        'PERTIKAIAN_SEMPADAN' => ['TANDA_SEMPADAN' => 'Batu / tanda sempadan', 'TUNTUTAN_PIHAK_KETIGA' => 'Tuntutan pihak ketiga'],
        'PEMBUANGAN_SAMPAH' => ['SAMPAH_DOMESTIK' => 'Sampah domestik', 'SISA_BINAAN' => 'Sisa binaan'],
        'LAIN' => ['LAIN_LAIN' => 'Lain-lain'],
    ];

    /** SRS-ADU-12: [respons, penyelesaian] in working days. */
    public const SLA = [
        'PENCEROBOHAN' => [2, 30],
        'BINAAN_HARAM' => [2, 30],
        'PEMBUANGAN_SAMPAH' => [2, 14],
        'KEROSAKAN_ASET' => [2, 21],
        'PERTIKAIAN_SEMPADAN' => [3, 60],
        'ISU_PENYEWA' => [3, 30],
        'TUNGGAKAN' => [3, 30],
        'PENCEMARAN' => [1, 14],
        'KESELAMATAN' => [1, 7],
        'LAIN' => [3, 30],
    ];

    /**
     * SRS-ADU-14: tiered escalation path. `sasaran` respons|selesai, `peratus`
     * of that SLA period elapsed (working time), `penerima` pegawai | penyelia
     * | ketua_bahagian | peranan:<kod> | kebenaran:<key>.
     */
    public const LALUAN_ESKALASI = [
        ['peringkat' => 1, 'sasaran' => 'respons', 'peratus' => 100, 'penerima' => ['penyelia']],
        ['peringkat' => 2, 'sasaran' => 'selesai', 'peratus' => 80, 'penerima' => ['pegawai', 'penyelia']],
        ['peringkat' => 3, 'sasaran' => 'selesai', 'peratus' => 100, 'penerima' => ['penyelia']],
        ['peringkat' => 4, 'sasaran' => 'selesai', 'peratus' => 150, 'penerima' => ['ketua_bahagian']],
    ];

    /** SRS-ADU-09: keyword → suggested category [priority, words]. */
    public const KATA_KUNCI = [
        'PENCEROBOHAN' => ['tinggi', ['ceroboh', 'pencerobohan', 'menceroboh', 'duduki', 'penghuni haram', 'setinggan', 'bercucuk tanam', 'tanam tanpa izin']],
        'BINAAN_HARAM' => ['tinggi', ['binaan', 'bina rumah', 'membina', 'bangunan haram', 'tanpa kebenaran', 'struktur', 'gerai', 'pagar baru']],
        'PEMBUANGAN_SAMPAH' => ['sederhana', ['sampah', 'buang sampah', 'sisa binaan', 'longgokan']],
        'KEROSAKAN_ASET' => ['sederhana', ['rosak', 'pecah', 'curi', 'kecurian', 'vandal', 'runtuh', 'bocor']],
        'PERTIKAIAN_SEMPADAN' => ['sederhana', ['sempadan', 'batu sempadan', 'pertikaian', 'tuntut', 'bertindih']],
        'PENCEMARAN' => ['tinggi', ['pencemaran', 'cemar', 'tumpahan', 'minyak', 'asap', 'bau busuk']],
        'KESELAMATAN' => ['kritikal', ['bahaya', 'keselamatan', 'kebakaran', 'api', 'dadah', 'jenayah']],
        'ISU_PENYEWA' => ['rendah', ['penyewa', 'pemajak', 'sewa', 'tuan rumah']],
    ];

    public static function label(array $map, ?string $kod): ?string
    {
        return $kod === null ? null : ($map[$kod] ?? $kod);
    }
}
