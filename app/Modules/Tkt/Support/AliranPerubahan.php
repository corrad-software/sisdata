<?php

namespace App\Modules\Tkt\Support;

use App\Modules\Tkt\Services\TiketService;

/**
 * Default MOD-WFE definition for `tkt.permintaan_perubahan` (§18.4: work
 * outside the warranty scope needs a separate, approved change request):
 * one approval stage by the support management group, justification
 * required, SLA 3 working days, escalation to the MAIS project committee
 * group. Seeded by database/seeders/TktAliranSeeder; editable in the WFE
 * designer.
 */
final class AliranPerubahan
{
    public const KUMPULAN_PENGURUSAN = 'tkt_pengurusan_sokongan';

    public const KUMPULAN_JAWATANKUASA = 'tkt_jawatankuasa_projek';

    /** @return array<string, mixed> */
    public static function structure(): array
    {
        return [
            'kod_aliran' => TiketService::FLOW,
            'nama' => 'Kelulusan permintaan perubahan (tiket sokongan)',
            'keterangan' => 'Permintaan perubahan daripada tiket sokongan diluluskan oleh pengurusan sokongan sebelum tindakan dimulakan.',
            'modul' => 'tkt',
            'jenis_transaksi' => TiketService::SUBJECT,
            'templat' => false,
            'peringkat' => [[
                'kod' => 'kelulusan_pengurusan', 'nama' => 'Kelulusan Pengurusan Sokongan', 'mod_kelulusan' => 'mana_mana',
                'sla_nilai' => 3, 'sla_unit' => 'hari_bekerja', 'perlu_mfa' => false, 'perlu_justifikasi' => true,
                'pelulus' => [['jenis' => 'kumpulan', 'nilai' => self::KUMPULAN_PENGURUSAN, 'label' => 'Pengurusan Sokongan']],
                'eskalasi' => [
                    ['aras' => 1, 'tempoh_nilai' => 0, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'penyelia', 'nilai_penerima' => null],
                    ['aras' => 2, 'tempoh_nilai' => 2, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'kumpulan', 'nilai_penerima' => self::KUMPULAN_JAWATANKUASA],
                ],
            ]],
            'peralihan' => [
                ['dari' => 'mula', 'ke' => 'kelulusan_pengurusan'],
                ['dari' => 'kelulusan_pengurusan', 'ke' => 'tamat'],
            ],
            'susulan' => [],
            'susun_atur' => ['nodes' => ['mula' => ['x' => 0, 'y' => 120], 'kelulusan_pengurusan' => ['x' => 260, 'y' => 100], 'tamat' => ['x' => 560, 'y' => 120]]],
        ];
    }
}
