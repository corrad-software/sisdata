<?php

namespace App\Modules\Adu\Support;

use App\Modules\Adu\Services\AduanService;

/**
 * SRS-ADU-19 / security control "pengasingan tugas antara penyiasat dan
 * pelulus": default MOD-WFE definition for `adu.penutupan` — one approval
 * stage by the complaint supervisors group (adu_penyelia), justification
 * required, SLA 2 working days with two escalation levels (supervisor of the
 * approver, then Ketua Bahagian group). WFE refuses approval by the
 * initiator (the investigating officer). Seeded by
 * database/seeders/AduAliranSeeder; editable in the WFE designer.
 */
final class AliranPenutupan
{
    public const KUMPULAN_PENYELIA = 'adu_penyelia';

    public const KUMPULAN_KETUA = 'adu_ketua_bahagian';

    /** @return array<string, mixed> */
    public static function structure(): array
    {
        return [
            'kod_aliran' => AduanService::FLOW,
            'nama' => 'Kelulusan penutupan aduan',
            'keterangan' => 'SRS-ADU-19: aduan hanya ditutup selepas resolusi direkod dan diluluskan penyelia (bukan penyiasat).',
            'modul' => 'adu',
            'jenis_transaksi' => AduanService::SUBJECT,
            'templat' => false,
            'peringkat' => [[
                'kod' => 'kelulusan_penyelia', 'nama' => 'Kelulusan Penyelia Aduan', 'mod_kelulusan' => 'mana_mana',
                'sla_nilai' => 2, 'sla_unit' => 'hari_bekerja', 'perlu_mfa' => false, 'perlu_justifikasi' => true,
                'pelulus' => [['jenis' => 'kumpulan', 'nilai' => self::KUMPULAN_PENYELIA, 'label' => 'Penyelia Aduan']],
                'eskalasi' => [
                    ['aras' => 1, 'tempoh_nilai' => 0, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'penyelia', 'nilai_penerima' => null],
                    ['aras' => 2, 'tempoh_nilai' => 2, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'kumpulan', 'nilai_penerima' => self::KUMPULAN_KETUA],
                ],
            ]],
            'peralihan' => [
                ['dari' => 'mula', 'ke' => 'kelulusan_penyelia'],
                ['dari' => 'kelulusan_penyelia', 'ke' => 'tamat'],
            ],
            'susulan' => [],
            'susun_atur' => ['nodes' => ['mula' => ['x' => 0, 'y' => 120], 'kelulusan_penyelia' => ['x' => 260, 'y' => 100], 'tamat' => ['x' => 560, 'y' => 120]]],
        ];
    }
}
