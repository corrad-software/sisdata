<?php

namespace App\Modules\Tkt\Support;

/**
 * MOD-TKT codes, Malay labels and seeded defaults.
 *
 * SLA FIGURES ARE ASSUMPTIONS: SRS §18.2 gives no numbers ("mengikut
 * kontrak", PLAN-SISDATA §14 #9). They are seeded into tkt_sla_konfigurasi
 * and editable by administrators without code (SRS-TKT-08); replace them with
 * the contract figures before acceptance testing.
 */
final class Lalai
{
    public const KATEGORI = [
        'ralat' => 'Ralat sistem',
        'soalan' => 'Soalan / pertanyaan',
        'permintaan_perubahan' => 'Permintaan perubahan',
        'capaian' => 'Capaian / akaun',
    ];

    public const KETERUKAN = [
        1 => 'Severity 1 — Kritikal',
        2 => 'Severity 2 — Tinggi',
        3 => 'Severity 3 — Sederhana',
        4 => 'Severity 4 — Rendah',
    ];

    public const KETERUKAN_RINGKAS = [1 => 'S1', 2 => 'S2', 3 => 'S3', 4 => 'S4'];

    public const KESAN = [
        'seorang' => 'Saya seorang sahaja',
        'sebahagian' => 'Sebahagian pengguna / satu bahagian',
        'semua' => 'Semua pengguna / fungsi teras terhenti',
    ];

    public const KESEGERAAN = [
        'rendah' => 'Rendah — ada cara lain / boleh tunggu',
        'sederhana' => 'Sederhana — kerja terganggu',
        'tinggi' => 'Tinggi — kerja terhenti sepenuhnya',
    ];

    /**
     * Severity/priority matrix (impact × urgency → severity). Default for
     * parameter tkt.matriks_keterukan; a security issue is always S1 and a
     * question at most S4 (SRS §18.2 definitions).
     */
    public const MATRIKS = [
        'semua' => ['tinggi' => 1, 'sederhana' => 2, 'rendah' => 3],
        'sebahagian' => ['tinggi' => 2, 'sederhana' => 3, 'rendah' => 3],
        'seorang' => ['tinggi' => 3, 'sederhana' => 3, 'rendah' => 4],
    ];

    public const STATUS = [
        'baharu' => 'Baharu',
        'disiasat' => 'Disiasat',
        'dalam_tindakan' => 'Dalam tindakan',
        'menunggu_pelapor' => 'Menunggu maklum balas pelapor',
        'menunggu_kelulusan' => 'Menunggu kelulusan perubahan',
        'selesai' => 'Selesai (menunggu pengesahan)',
        'disahkan' => 'Disahkan pelapor',
        'tutup' => 'Ditutup',
        'pendua' => 'Pendua (ditutup)',
        'dirujuk_aduan' => 'Dirujuk ke Modul Aduan',
    ];

    /** Statuses in which the ticket is still being worked on. */
    public const TERBUKA = ['baharu', 'disiasat', 'dalam_tindakan', 'menunggu_pelapor', 'menunggu_kelulusan'];

    /** Final statuses. */
    public const AKHIR = ['tutup', 'pendua', 'dirujuk_aduan'];

    /** SLA clock stops while waiting for the reporter (SRS §18.2). */
    public const JEDA = ['menunggu_pelapor', 'selesai'];

    /**
     * Status transitions a support officer may make directly. selesai →
     * disahkan is the reporter's; → tutup only after verification or by the
     * auto-close job (SRS-TKT-12); menunggu_kelulusan only through WFE.
     *
     * @var array<string, list<string>>
     */
    public const PERALIHAN = [
        'baharu' => ['disiasat'],
        'disiasat' => ['dalam_tindakan', 'menunggu_pelapor', 'selesai'],
        'dalam_tindakan' => ['disiasat', 'menunggu_pelapor', 'selesai'],
        'menunggu_pelapor' => ['disiasat', 'dalam_tindakan', 'selesai'],
        'menunggu_kelulusan' => [],
        'selesai' => [],
        'disahkan' => ['tutup'],
    ];

    public const SALURAN = [
        'aplikasi' => 'Butang bantuan SISDATA',
        'portal' => 'Portal awam',
        'emel' => 'E-mel',
        'pemantauan' => 'Sistem pemantauan',
        'sistem_aduan_pusat' => 'Sistem Aduan berpusat MAIS',
    ];

    public const JENIS_CATATAN = [
        'dalaman' => 'Catatan dalaman',
        'kelihatan_pelapor' => 'Balasan kepada pelapor',
        'pelapor' => 'Mesej pelapor',
        'perubahan_status' => 'Perubahan status',
        'tugasan' => 'Penugasan',
        'eskalasi' => 'Eskalasi',
        'perubahan_medan' => 'Perubahan keterukan / kategori',
        'lampiran' => 'Lampiran',
        'sistem' => 'Sistem',
    ];

    /** Entries the reporter sees in their timeline. */
    public const KRONOLOGI_PELAPOR = ['kelihatan_pelapor', 'pelapor', 'perubahan_status', 'lampiran'];

    public const PERANAN_AHLI = ['juruteknik' => 'Juruteknik', 'penyelia' => 'Penyelia sokongan', 'pengurus' => 'Pengurus projek'];

    public const JENIS_AKTIVITI = [
        'tampalan_keselamatan' => 'Tampalan keselamatan',
        'penyelenggaraan_pencegahan' => 'Penyelenggaraan pencegahan',
        'masa_henti' => 'Masa henti sistem',
        'pemindahan_teknologi' => 'Pemindahan teknologi kepada BTM',
        'tindakan_pencegahan' => 'Tindakan pencegahan dicadangkan',
    ];

    /** SISDATA modules a ticket can be about (frontend path segment → name). */
    public const MODUL = [
        'rbc' => 'Pengguna & Capaian', 'aud' => 'Audit Trail', 'adm' => 'Pentadbiran Sistem', 'ntf' => 'Notifikasi',
        'dms' => 'Dokumen Digital', 'wfe' => 'Aliran Kerja', 'ref' => 'Data Rujukan', 'pro' => 'Profil Hartanah',
        'hkm' => 'Hak Milik', 'per' => 'Pemerolehan', 'gis' => 'GIS', 'dsh' => 'Dashboard Eksekutif',
        'pjk' => 'Pajakan & Sewaan', 'cki' => 'Cukai', 'ptn' => 'Pemantauan Tanah', 'adu' => 'Aduan',
        'kes' => 'Kes Perundangan', 'tsu' => 'Tukar Syarat & Urusan', 'ast' => 'Aset', 'kba' => 'Pangkalan Pengetahuan',
        'lap' => 'Laporan & Analitik', 'tkt' => 'Tiket Sokongan', 'pws' => 'Portal Awam', 'umum' => 'Umum / lain-lain',
    ];

    /**
     * ASSUMED SLA defaults (minutes of service time) until the contract
     * figures are supplied — see class doc. Escalation: level 1 to the support
     * supervisor at breach, level 2 to the project manager, level 3 to the
     * MAIS Project Committee (critical / high only) — SRS §18.5.
     *
     * @return array<int, array{ambang_respons: int, ambang_penyelesaian: int, waktu_perkhidmatan: string, ambang_amaran_peratus: int, hierarki_eskalasi: list<array{peringkat: int, penerima: string, selepas_minit: int}>}>
     */
    public static function sla(): array
    {
        $chain = function (int $l2, ?int $l3): array {
            $levels = [
                ['peringkat' => 1, 'penerima' => 'penyelia', 'selepas_minit' => 0],
                ['peringkat' => 2, 'penerima' => 'pengurus', 'selepas_minit' => $l2],
            ];
            if ($l3 !== null) {
                $levels[] = ['peringkat' => 3, 'penerima' => 'peranan:jawatankuasa_projek', 'selepas_minit' => $l3];
            }

            return $levels;
        };

        return [
            1 => ['ambang_respons' => 30, 'ambang_penyelesaian' => 240, 'waktu_perkhidmatan' => '24x7', 'ambang_amaran_peratus' => 75, 'hierarki_eskalasi' => $chain(60, 240)],
            2 => ['ambang_respons' => 120, 'ambang_penyelesaian' => 1080, 'waktu_perkhidmatan' => 'waktu_pejabat', 'ambang_amaran_peratus' => 75, 'hierarki_eskalasi' => $chain(240, 1080)],
            3 => ['ambang_respons' => 240, 'ambang_penyelesaian' => 2700, 'waktu_perkhidmatan' => 'waktu_pejabat', 'ambang_amaran_peratus' => 80, 'hierarki_eskalasi' => $chain(1080, null)],
            4 => ['ambang_respons' => 540, 'ambang_penyelesaian' => 10800, 'waktu_perkhidmatan' => 'waktu_pejabat', 'ambang_amaran_peratus' => 80, 'hierarki_eskalasi' => $chain(2700, null)],
        ];
    }

    /** Default central-system mapping (SRS-TKT-15): jenis => [tempatan => berpusat]. */
    public const PEMETAAN = [
        'kategori' => ['ralat' => 'TEKNIKAL', 'soalan' => 'PERTANYAAN', 'permintaan_perubahan' => 'PERMINTAAN', 'capaian' => 'AKAUN'],
        'keterukan' => ['1' => 'KRITIKAL', '2' => 'TINGGI', '3' => 'SEDERHANA', '4' => 'RENDAH'],
        'status' => ['baharu' => 'DITERIMA', 'disiasat' => 'DALAM_SIASATAN', 'dalam_tindakan' => 'DALAM_TINDAKAN', 'menunggu_pelapor' => 'MENUNGGU_PENGADU', 'menunggu_kelulusan' => 'DALAM_TINDAKAN', 'selesai' => 'SELESAI', 'disahkan' => 'SELESAI', 'tutup' => 'DITUTUP', 'pendua' => 'DITUTUP', 'dirujuk_aduan' => 'DIRUJUK'],
        'medan' => ['tajuk' => 'subjek', 'penerangan' => 'keterangan', 'nombor_rujukan' => 'no_tiket_sisdata', 'penyelesaian' => 'resolusi'],
    ];

    public static function keterukan(int|string|null $value): string
    {
        return self::KETERUKAN[(int) $value] ?? (string) $value;
    }
}
