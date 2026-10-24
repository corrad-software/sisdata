<?php

namespace App\Modules\Pjk\Support;

/**
 * Default MOD-WFE definitions for the five PJK flows (SRS-PJK-05: approval
 * layers configurable by value and category). Structure is MOD-WFE's
 * definition format; seeded by database/seeders/PjkAliranSeeder and edited by
 * admins in the WFE designer afterwards. Requesters are the WFE initiator, so
 * WFE never lets them approve their own request (segregation of duties).
 */
final class AliranLalai
{
    /** Approver groups (WFE wf_kumpulan) => name. */
    public const KUMPULAN = [
        'pjk_penyemak' => 'PJK — Pegawai kanan Unit Sewaan (penyemak)',
        'pjk_ketua_hartanah' => 'PJK — Ketua Bahagian Pengurusan Hartanah',
        'pjk_ketua_wakaf' => 'PJK — Ketua Bahagian Wakaf',
        'pjk_ketua_baitulmal' => 'PJK — Ketua Bahagian Baitulmal',
        'pjk_pengurusan' => 'PJK — Pengurusan Atasan (nilai tinggi)',
        'pjk_kewangan' => 'PJK — Bahagian Kewangan (pengesah pelarasan / deposit)',
    ];

    /** Contract value above which top management approves (RM). */
    public const HAD_PENGURUSAN = 500000;

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return [
            self::layered(Aliran::KONTRAK, 'nilai_kontrak', 'SRS-PJK-05: semakan Unit Sewaan → Ketua Bahagian mengikut bahagian (wakaf / baitulmal / hartanah) → Pengurusan Atasan jika nilai kontrak melebihi RM500,000.'),
            self::layered(Aliran::PINDAAN, 'nilai_kontrak', 'SRS-PJK-05/07: pembaharuan, semakan kadar, pindaan terma dan penamatan — lapisan sama seperti kontrak baharu.'),
            self::single(Aliran::PELARASAN, 'pjk_kewangan', 'Pengesahan pegawai kedua (Bahagian Kewangan)', 'SRS-PJK-28: pelarasan, pembatalan dan pengecualian lejar memerlukan pegawai kedua dengan justifikasi.', true),
            self::single(Aliran::DEPOSIT, 'pjk_kewangan', 'Kelulusan Bahagian Kewangan', 'SRS-PJK-13: penyelarasan dan pulangan deposit diluluskan Bahagian Kewangan.', false),
            self::single(Aliran::DASAR, 'pjk_pengurusan', 'Kelulusan Pengurusan Atasan', 'SRS-PJK-22: kadar Dasar Agihan Manfaat berversi diluluskan sebelum berkuat kuasa.', true),
        ];
    }

    /** @return array<string, mixed> */
    private static function layered(string $code, string $valueField, string $description): array
    {
        $stage = fn (string $kod, string $nama, string $group, bool $mfa = false) => [
            'kod' => $kod, 'nama' => $nama, 'mod_kelulusan' => 'mana_mana', 'sla_nilai' => 3, 'sla_unit' => 'hari_bekerja',
            'perlu_mfa' => $mfa, 'perlu_justifikasi' => $mfa,
            'pelulus' => [['jenis' => 'kumpulan', 'nilai' => $group, 'label' => self::KUMPULAN[$group]]],
            'eskalasi' => [['aras' => 1, 'tempoh_nilai' => 0, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'penyelia', 'nilai_penerima' => null]],
        ];
        $high = [['medan' => $valueField, 'operator' => 'lebih', 'nilai' => self::HAD_PENGURUSAN]];

        return [
            'kod_aliran' => $code,
            'nama' => Aliran::FLOWS[$code][0],
            'keterangan' => $description,
            'modul' => 'pjk',
            'jenis_transaksi' => Aliran::FLOWS[$code][1],
            'templat' => false,
            'peringkat' => [
                $stage('semakan', 'Semakan pegawai kanan Unit Sewaan', 'pjk_penyemak'),
                $stage('kb_hartanah', 'Kelulusan Ketua Bahagian Pengurusan Hartanah', 'pjk_ketua_hartanah'),
                $stage('kb_wakaf', 'Kelulusan Ketua Bahagian Wakaf', 'pjk_ketua_wakaf'),
                $stage('kb_baitulmal', 'Kelulusan Ketua Bahagian Baitulmal', 'pjk_ketua_baitulmal'),
                $stage('pengurusan', 'Kelulusan Pengurusan Atasan (nilai tinggi)', 'pjk_pengurusan', true),
            ],
            'peralihan' => [
                ['dari' => 'mula', 'ke' => 'semakan'],
                ['dari' => 'semakan', 'ke' => 'kb_wakaf', 'keutamaan' => 1, 'label' => 'Bahagian Wakaf', 'syarat' => [['medan' => 'bahagian', 'operator' => 'sama', 'nilai' => 'wakaf']]],
                ['dari' => 'semakan', 'ke' => 'kb_baitulmal', 'keutamaan' => 2, 'label' => 'Bahagian Baitulmal', 'syarat' => [['medan' => 'bahagian', 'operator' => 'sama', 'nilai' => 'baitulmal']]],
                ['dari' => 'semakan', 'ke' => 'kb_hartanah', 'keutamaan' => 3, 'label' => 'Bahagian Pengurusan Hartanah'],
                ['dari' => 'kb_hartanah', 'ke' => 'pengurusan', 'keutamaan' => 1, 'label' => 'Nilai > RM500,000', 'syarat' => $high],
                ['dari' => 'kb_hartanah', 'ke' => 'tamat', 'keutamaan' => 2],
                ['dari' => 'kb_wakaf', 'ke' => 'pengurusan', 'keutamaan' => 1, 'label' => 'Nilai > RM500,000', 'syarat' => $high],
                ['dari' => 'kb_wakaf', 'ke' => 'tamat', 'keutamaan' => 2],
                ['dari' => 'kb_baitulmal', 'ke' => 'pengurusan', 'keutamaan' => 1, 'label' => 'Nilai > RM500,000', 'syarat' => $high],
                ['dari' => 'kb_baitulmal', 'ke' => 'tamat', 'keutamaan' => 2],
                ['dari' => 'pengurusan', 'ke' => 'tamat'],
            ],
            'susulan' => [],
            'susun_atur' => ['nodes' => [
                'mula' => ['x' => 0, 'y' => 200], 'semakan' => ['x' => 200, 'y' => 180],
                'kb_hartanah' => ['x' => 480, 'y' => 40], 'kb_wakaf' => ['x' => 480, 'y' => 180], 'kb_baitulmal' => ['x' => 480, 'y' => 320],
                'pengurusan' => ['x' => 760, 'y' => 180], 'tamat' => ['x' => 1020, 'y' => 200],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private static function single(string $code, string $group, string $stageName, string $description, bool $mfa): array
    {
        return [
            'kod_aliran' => $code,
            'nama' => Aliran::FLOWS[$code][0],
            'keterangan' => $description,
            'modul' => 'pjk',
            'jenis_transaksi' => Aliran::FLOWS[$code][1],
            'templat' => false,
            'peringkat' => [[
                'kod' => 'pengesahan', 'nama' => $stageName, 'mod_kelulusan' => 'mana_mana', 'sla_nilai' => 3, 'sla_unit' => 'hari_bekerja',
                'perlu_mfa' => $mfa, 'perlu_justifikasi' => true,
                'pelulus' => [['jenis' => 'kumpulan', 'nilai' => $group, 'label' => self::KUMPULAN[$group]]],
                'eskalasi' => [['aras' => 1, 'tempoh_nilai' => 0, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'penyelia', 'nilai_penerima' => null]],
            ]],
            'peralihan' => [['dari' => 'mula', 'ke' => 'pengesahan'], ['dari' => 'pengesahan', 'ke' => 'tamat']],
            'susulan' => [],
            'susun_atur' => ['nodes' => ['mula' => ['x' => 0, 'y' => 120], 'pengesahan' => ['x' => 260, 'y' => 100], 'tamat' => ['x' => 560, 'y' => 120]]],
        ];
    }
}
