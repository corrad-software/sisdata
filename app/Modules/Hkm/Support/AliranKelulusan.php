<?php

namespace App\Modules\Hkm\Support;

use App\Modules\Hkm\Services\ChangeService;

/**
 * SRS-HKM-19: default WFE definition for `hkm.perubahan_hak_milik` — two
 * approval stages (Semakan Pegawai Hartanah Kanan → Kelulusan Ketua
 * Bahagian), BOTH with perlu_mfa (step-up MFA) and justification required.
 * Structure is MOD-WFE's definition format; it is seeded by
 * database/seeders/HkmAliranSeeder (WFE has no "seed default definition"
 * contract yet) and can then be edited by admins in the WFE designer.
 */
final class AliranKelulusan
{
    public const KUMPULAN_PENYEMAK = 'hkm_penyemak';

    public const KUMPULAN_PELULUS = 'hkm_ketua_bahagian';

    /** @return array<string, mixed> */
    public static function structure(): array
    {
        $escalation = fn (string $unit, int $second) => [
            ['aras' => 1, 'tempoh_nilai' => 0, 'tempoh_unit' => $unit, 'jenis_penerima' => 'penyelia', 'nilai_penerima' => null],
            ['aras' => 2, 'tempoh_nilai' => $second, 'tempoh_unit' => $unit, 'jenis_penerima' => 'peranan', 'nilai_penerima' => 'pentadbir_sistem'],
        ];

        return [
            'kod_aliran' => ChangeService::FLOW,
            'nama' => 'Perubahan hak milik (dua peringkat, MFA)',
            'keterangan' => 'SRS-HKM-19: perubahan butiran hak milik, status tanah, pemilik, sekatan kepentingan dan waris — dua peringkat kelulusan dengan pengesahan MFA.',
            'modul' => 'hkm',
            'jenis_transaksi' => ChangeService::SUBJECT,
            'templat' => false,
            'peringkat' => [
                [
                    'kod' => 'semakan', 'nama' => 'Semakan Pegawai Hartanah Kanan', 'mod_kelulusan' => 'mana_mana',
                    'sla_nilai' => 3, 'sla_unit' => 'hari_bekerja', 'perlu_mfa' => true, 'perlu_justifikasi' => true,
                    'pelulus' => [['jenis' => 'kumpulan', 'nilai' => self::KUMPULAN_PENYEMAK, 'label' => 'Pegawai Hartanah Kanan (penyemak)']],
                    'eskalasi' => $escalation('hari_bekerja', 2),
                ],
                [
                    'kod' => 'kelulusan', 'nama' => 'Kelulusan Ketua Bahagian', 'mod_kelulusan' => 'mana_mana',
                    'sla_nilai' => 3, 'sla_unit' => 'hari_bekerja', 'perlu_mfa' => true, 'perlu_justifikasi' => true,
                    'pelulus' => [['jenis' => 'kumpulan', 'nilai' => self::KUMPULAN_PELULUS, 'label' => 'Ketua Bahagian Pengurusan Hartanah']],
                    'eskalasi' => $escalation('hari_bekerja', 2),
                ],
            ],
            'peralihan' => [
                ['dari' => 'mula', 'ke' => 'semakan'],
                ['dari' => 'semakan', 'ke' => 'kelulusan'],
                ['dari' => 'kelulusan', 'ke' => 'tamat'],
            ],
            'susulan' => [],
            'susun_atur' => ['nodes' => [
                'mula' => ['x' => 0, 'y' => 120], 'semakan' => ['x' => 220, 'y' => 100],
                'kelulusan' => ['x' => 520, 'y' => 100], 'tamat' => ['x' => 800, 'y' => 120],
            ]],
        ];
    }
}
