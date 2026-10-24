<?php

namespace App\Modules\Cki\Support;

/**
 * MOD-CKI approval flow (SRS-CKI-14, SRS-GEN-03). CKI only starts it and
 * applies the outcome on WorkflowCompleted. The REQUIRED level ("tahap") is
 * computed by CKI from the admin-configurable value thresholds
 * (parameter cki.had_kelulusan) and passed in the start context; the WFE
 * definition branches on it, so the thresholds live in exactly one place.
 * Requester = WFE initiator, who WFE never lets approve their own request
 * (segregation of duties). Seeded by database/seeders/CkiAliranSeeder.
 */
final class Aliran
{
    public const BAYARAN = 'cki.kelulusan_bayaran';

    /** flow => [name, subject type, context fields] */
    public const FLOWS = [
        self::BAYARAN => ['Kelulusan bayaran cukai mengikut had nilai', 'transaksi_bayaran_cukai', ['amaun', 'tahap', 'peringkat', 'jenis_cukai', 'kaedah_bayaran', 'kod_daerah']],
    ];

    public const KUMPULAN = [
        'cki_kewangan' => 'CKI — Bahagian Kewangan (pelulus bayaran)',
        'cki_ketua_bahagian' => 'CKI — Ketua Bahagian Pengurusan Hartanah',
        'cki_pengurusan' => 'CKI — Pengurusan Atasan',
    ];

    /** Default value thresholds (RM): up to "hingga" the level suffices; null = no upper bound. */
    public const HAD_LALAI = [
        ['hingga' => '5000.00', 'peringkat' => 'kewangan'],
        ['hingga' => '50000.00', 'peringkat' => 'ketua_bahagian'],
        ['hingga' => null, 'peringkat' => 'pengurusan_atasan'],
    ];

    /** @return array<string, mixed> MOD-WFE definition structure */
    public static function definisi(): array
    {
        $stage = fn (string $kod, string $nama, string $group, bool $mfa) => [
            'kod' => $kod, 'nama' => $nama, 'mod_kelulusan' => 'mana_mana', 'sla_nilai' => 3, 'sla_unit' => 'hari_bekerja',
            'perlu_mfa' => $mfa, 'perlu_justifikasi' => true,
            'pelulus' => [['jenis' => 'kumpulan', 'nilai' => $group, 'label' => self::KUMPULAN[$group]]],
            'eskalasi' => [['aras' => 1, 'tempoh_nilai' => 0, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'penyelia', 'nilai_penerima' => null]],
        ];

        return [
            'kod_aliran' => self::BAYARAN,
            'nama' => self::FLOWS[self::BAYARAN][0],
            'keterangan' => 'SRS-CKI-14: Bahagian Kewangan meluluskan semua bayaran; Ketua Bahagian bagi tahap 2 ke atas; Pengurusan Atasan bagi tahap 3. Tahap dikira daripada parameter cki.had_kelulusan.',
            'modul' => 'cki',
            'jenis_transaksi' => self::FLOWS[self::BAYARAN][1],
            'templat' => false,
            'peringkat' => [
                $stage('kewangan', 'Kelulusan Bahagian Kewangan', 'cki_kewangan', false),
                $stage('ketua_bahagian', 'Kelulusan Ketua Bahagian', 'cki_ketua_bahagian', false),
                $stage('pengurusan', 'Kelulusan Pengurusan Atasan', 'cki_pengurusan', true),
            ],
            'peralihan' => [
                ['dari' => 'mula', 'ke' => 'kewangan'],
                ['dari' => 'kewangan', 'ke' => 'ketua_bahagian', 'keutamaan' => 1, 'label' => 'Tahap 2 ke atas', 'syarat' => [['medan' => 'tahap', 'operator' => 'lebih', 'nilai' => 1]]],
                ['dari' => 'kewangan', 'ke' => 'tamat', 'keutamaan' => 2],
                ['dari' => 'ketua_bahagian', 'ke' => 'pengurusan', 'keutamaan' => 1, 'label' => 'Tahap 3', 'syarat' => [['medan' => 'tahap', 'operator' => 'lebih', 'nilai' => 2]]],
                ['dari' => 'ketua_bahagian', 'ke' => 'tamat', 'keutamaan' => 2],
                ['dari' => 'pengurusan', 'ke' => 'tamat'],
            ],
            'susulan' => [],
            'susun_atur' => ['nodes' => [
                'mula' => ['x' => 0, 'y' => 160], 'kewangan' => ['x' => 220, 'y' => 140], 'ketua_bahagian' => ['x' => 480, 'y' => 140],
                'pengurusan' => ['x' => 740, 'y' => 140], 'tamat' => ['x' => 1000, 'y' => 160],
            ]],
        ];
    }
}
