<?php

namespace App\Modules\Tsu\Support;

/**
 * MOD-WFE flows MOD-TSU starts (SRS-GEN-03 — no approval logic in this
 * module). Default definitions are seeded by database/seeders/TsuAliranSeeder
 * and refined by admins in the WFE designer.
 *
 *  - tsu.kelulusan_urusan     internal approval stage of a tukar syarat / pecah / penyatuan / serah balik / HMK urusan (MFA, SRS-WFE-19)
 *  - tsu.kelulusan_pelupusan  SRS-TSU-05: layered disposal approval; wakaf land passes the Bahagian Wakaf (Syariah) review; the highest stage requires MFA + written justification
 *  - tsu.kelulusan_lantikan   SRS-TSU-09: authorised officer approves the recommended surveyor
 *  - tsu.pengesahan_pemindahan SRS-TSU-20: two-level confirmation before records move to the new lot
 */
final class AliranTsu
{
    public const URUSAN = 'tsu.kelulusan_urusan';

    public const PELUPUSAN = 'tsu.kelulusan_pelupusan';

    public const LANTIKAN = 'tsu.kelulusan_lantikan';

    public const PEMINDAHAN = 'tsu.pengesahan_pemindahan';

    public const SUBJEK_PERINGKAT = 'urusan_peringkat';

    public const SUBJEK_SEBUTHARGA = 'sebutharga_ukur';

    public const SUBJEK_PEMINDAHAN = 'pemindahan_rekod';

    /** WFE stage code of the highest disposal approval (must carry MFA + justification). */
    public const PERINGKAT_TERTINGGI = 'kelulusan_tertinggi';

    public const KUMPULAN = [
        'tsu_ketua_bahagian' => 'TSU — Ketua Bahagian Pengurusan Hartanah',
        'tsu_bahagian_wakaf' => 'TSU — Bahagian Wakaf (semakan Syariah / istibdal)',
        'tsu_pengurusan' => 'TSU — Pengurusan Tertinggi / Majlis',
        'tsu_pegawai_berkuasa' => 'TSU — Pegawai berkuasa lantikan jurukur',
        'tsu_pengesah_pemindahan' => 'TSU — Pegawai pengesah pemindahan rekod (peringkat 1)',
    ];

    /** @return list<array<string, mixed>> */
    public static function structures(): array
    {
        $stage = fn (string $kod, string $nama, string $group, int $sla, bool $mfa, bool $just) => [
            'kod' => $kod, 'nama' => $nama, 'mod_kelulusan' => 'mana_mana',
            'sla_nilai' => $sla, 'sla_unit' => 'hari_bekerja', 'perlu_mfa' => $mfa, 'perlu_justifikasi' => $just,
            'pelulus' => [['jenis' => 'kumpulan', 'nilai' => $group, 'label' => self::KUMPULAN[$group]]],
            'eskalasi' => [
                ['aras' => 1, 'tempoh_nilai' => 0, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'penyelia', 'nilai_penerima' => null],
                ['aras' => 2, 'tempoh_nilai' => 2, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'kumpulan', 'nilai_penerima' => 'tsu_pengurusan'],
            ],
        ];
        $base = fn (string $flow, string $nama, string $ket, string $subject, array $stages, array $transitions, array $nodes) => [
            'kod_aliran' => $flow, 'nama' => $nama, 'keterangan' => $ket, 'modul' => 'tsu', 'jenis_transaksi' => $subject, 'templat' => false,
            'peringkat' => $stages, 'peralihan' => $transitions, 'susulan' => [], 'susun_atur' => ['nodes' => $nodes],
        ];

        return [
            $base(self::URUSAN, 'Kelulusan dalaman urusan tanah', 'SRS-TSU-01/07, SRS-WFE-19: kelulusan Ketua Bahagian (MFA) bagi peringkat kelulusan urusan Pejabat Tanah.', self::SUBJEK_PERINGKAT,
                [$stage('kelulusan_ketua_bahagian', 'Kelulusan Ketua Bahagian', 'tsu_ketua_bahagian', 5, true, true)],
                [['dari' => 'mula', 'ke' => 'kelulusan_ketua_bahagian'], ['dari' => 'kelulusan_ketua_bahagian', 'ke' => 'tamat']],
                ['mula' => ['x' => 0, 'y' => 120], 'kelulusan_ketua_bahagian' => ['x' => 260, 'y' => 100], 'tamat' => ['x' => 560, 'y' => 120]]),
            $base(self::PELUPUSAN, 'Kelulusan pelupusan tanah (aliran tertinggi)', 'SRS-TSU-05: Ketua Bahagian → (tanah wakaf: semakan Bahagian Wakaf / Syariah) → Pengurusan Tertinggi / Majlis dengan MFA, justifikasi bertulis dan laporan penilaian terkini.', self::SUBJEK_PERINGKAT,
                [
                    $stage('semakan_ketua_bahagian', 'Semakan Ketua Bahagian Pengurusan Hartanah', 'tsu_ketua_bahagian', 5, false, true),
                    $stage('semakan_wakaf', 'Semakan Bahagian Wakaf (Syariah / istibdal)', 'tsu_bahagian_wakaf', 10, false, true),
                    $stage(self::PERINGKAT_TERTINGGI, 'Kelulusan Pengurusan Tertinggi / Majlis', 'tsu_pengurusan', 10, true, true),
                ],
                [
                    ['dari' => 'mula', 'ke' => 'semakan_ketua_bahagian'],
                    ['dari' => 'semakan_ketua_bahagian', 'ke' => 'semakan_wakaf', 'keutamaan' => 1, 'label' => 'Tanah wakaf', 'syarat' => [['medan' => 'bahagian_pemilik', 'operator' => 'sama', 'nilai' => 'wakaf']]],
                    ['dari' => 'semakan_ketua_bahagian', 'ke' => self::PERINGKAT_TERTINGGI, 'keutamaan' => 2],
                    ['dari' => 'semakan_wakaf', 'ke' => self::PERINGKAT_TERTINGGI],
                    ['dari' => self::PERINGKAT_TERTINGGI, 'ke' => 'tamat'],
                ],
                ['mula' => ['x' => 0, 'y' => 160], 'semakan_ketua_bahagian' => ['x' => 220, 'y' => 140], 'semakan_wakaf' => ['x' => 480, 'y' => 40], self::PERINGKAT_TERTINGGI => ['x' => 740, 'y' => 140], 'tamat' => ['x' => 1000, 'y' => 160]]),
            $base(self::LANTIKAN, 'Kelulusan pemilihan jurukur berlesen', 'SRS-TSU-09: pegawai berkuasa meluluskan jurukur yang disyorkan berdasarkan jadual perbandingan tawaran.', self::SUBJEK_SEBUTHARGA,
                [$stage('kelulusan_pegawai_berkuasa', 'Kelulusan Pegawai Berkuasa', 'tsu_pegawai_berkuasa', 5, false, true)],
                [['dari' => 'mula', 'ke' => 'kelulusan_pegawai_berkuasa'], ['dari' => 'kelulusan_pegawai_berkuasa', 'ke' => 'tamat']],
                ['mula' => ['x' => 0, 'y' => 120], 'kelulusan_pegawai_berkuasa' => ['x' => 260, 'y' => 100], 'tamat' => ['x' => 560, 'y' => 120]]),
            $base(self::PEMINDAHAN, 'Pengesahan dua peringkat pemindahan rekod lot', 'SRS-TSU-20: pemindahan rekod pajakan, cukai dan aset ke lot baharu hanya selepas dua pengesah berbeza.', self::SUBJEK_PEMINDAHAN,
                [
                    $stage('pengesahan_1', 'Pengesahan peringkat 1', 'tsu_pengesah_pemindahan', 3, false, true),
                    $stage('pengesahan_2', 'Pengesahan peringkat 2 (Ketua Bahagian)', 'tsu_ketua_bahagian', 3, false, true),
                ],
                [['dari' => 'mula', 'ke' => 'pengesahan_1'], ['dari' => 'pengesahan_1', 'ke' => 'pengesahan_2'], ['dari' => 'pengesahan_2', 'ke' => 'tamat']],
                ['mula' => ['x' => 0, 'y' => 120], 'pengesahan_1' => ['x' => 220, 'y' => 100], 'pengesahan_2' => ['x' => 480, 'y' => 100], 'tamat' => ['x' => 740, 'y' => 120]]),
        ];
    }
}
