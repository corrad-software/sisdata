<?php

namespace App\Modules\Kes\Support;

/**
 * MOD-KES approvals in MOD-WFE (SRS-GEN-03 — no approval logic here): flow
 * codes, their subject tables, and the default definitions seeded by
 * database/seeders/KesAliranSeeder (editable in the WFE designer).
 *
 *  kes.pemfailan        decision to file a case (MAIS as plaintiff/applicant)
 *  kes.lantikan_panel   appointment of a panel law firm to a case
 *  kes.bayaran_kos      payment of a legal fee / court cost (then GRP MAIS)
 *  kes.rayuan           decision to appeal
 *  kes.penyelesaian     out-of-court settlement
 *  kes.tindakan_susulan follow-up task after a decision (SRS-KES-17)
 */
final class Aliran
{
    public const KUMPULAN_KETUA = 'kes_ketua_perundangan';

    public const KUMPULAN_PENGURUSAN = 'kes_pengurusan_tertinggi';

    public const KUMPULAN_PEGAWAI = 'kes_pegawai_perundangan';

    /** flow => [name, subject table, MFA on the decision stage, needs management second stage] */
    public const FLOWS = [
        'kes.pemfailan' => ['Kelulusan pemfailan kes mahkamah', 'kes_mahkamah', false, false],
        'kes.lantikan_panel' => ['Kelulusan lantikan panel peguam', 'kes_peguam', false, false],
        'kes.bayaran_kos' => ['Kelulusan bayaran fi peguam / kos mahkamah', 'kos_guaman', true, false],
        'kes.rayuan' => ['Kelulusan pemfailan rayuan', 'kes_rayuan', true, false],
        'kes.penyelesaian' => ['Kelulusan penyelesaian luar mahkamah', 'kes_penyelesaian', true, true],
        'kes.tindakan_susulan' => ['Tugasan tindakan susulan keputusan mahkamah', 'kes_tindakan_susulan', false, false],
    ];

    public const KONTEKS = ['kod_daerah', 'jenis', 'peranan_mais', 'nilai'];

    /** @return array<string, mixed> WFE definition structure */
    public static function structure(string $flow): array
    {
        [$nama, $subject, $mfa, $dua] = self::FLOWS[$flow];
        $tugasan = $flow === 'kes.tindakan_susulan';
        $stages = [[
            'kod' => $tugasan ? 'pelaksanaan' : 'kelulusan_ketua',
            'nama' => $tugasan ? 'Pelaksanaan & pengesahan tindakan susulan' : 'Kelulusan Ketua Bahagian Perundangan',
            'mod_kelulusan' => 'mana_mana',
            'sla_nilai' => $tugasan ? 14 : 3, 'sla_unit' => 'hari_bekerja', 'perlu_mfa' => $mfa, 'perlu_justifikasi' => true,
            'pelulus' => [['jenis' => 'kumpulan', 'nilai' => $tugasan ? self::KUMPULAN_PEGAWAI : self::KUMPULAN_KETUA, 'label' => $tugasan ? 'Pegawai Perundangan' : 'Ketua Bahagian Perundangan']],
            'eskalasi' => [
                ['aras' => 1, 'tempoh_nilai' => 0, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'penyelia', 'nilai_penerima' => null],
                ['aras' => 2, 'tempoh_nilai' => 2, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'kumpulan', 'nilai_penerima' => self::KUMPULAN_KETUA],
            ],
        ]];
        if ($dua) {
            $stages[] = [
                'kod' => 'kelulusan_pengurusan', 'nama' => 'Kelulusan Pengurusan Tertinggi MAIS', 'mod_kelulusan' => 'mana_mana',
                'sla_nilai' => 5, 'sla_unit' => 'hari_bekerja', 'perlu_mfa' => true, 'perlu_justifikasi' => true,
                'pelulus' => [['jenis' => 'kumpulan', 'nilai' => self::KUMPULAN_PENGURUSAN, 'label' => 'Pengurusan Tertinggi MAIS']],
                'eskalasi' => [['aras' => 1, 'tempoh_nilai' => 0, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'penyelia', 'nilai_penerima' => null]],
            ];
        }
        $peralihan = [['dari' => 'mula', 'ke' => $stages[0]['kod']]];
        for ($i = 1; $i < count($stages); $i++) {
            $peralihan[] = ['dari' => $stages[$i - 1]['kod'], 'ke' => $stages[$i]['kod']];
        }
        $peralihan[] = ['dari' => $stages[count($stages) - 1]['kod'], 'ke' => 'tamat'];
        $nodes = ['mula' => ['x' => 0, 'y' => 120]];
        foreach ($stages as $i => $s) {
            $nodes[$s['kod']] = ['x' => 260 + $i * 280, 'y' => 100];
        }
        $nodes['tamat'] = ['x' => 260 + count($stages) * 280, 'y' => 120];

        return [
            'kod_aliran' => $flow,
            'nama' => $nama,
            'keterangan' => 'Dibenihkan oleh MOD-KES. Peringkat, pelulus dan SLA boleh diubah dalam pereka aliran.',
            'modul' => 'kes',
            'jenis_transaksi' => $subject,
            'templat' => false,
            'peringkat' => $stages,
            'peralihan' => $peralihan,
            'susulan' => [],
            'susun_atur' => ['nodes' => $nodes],
        ];
    }
}
