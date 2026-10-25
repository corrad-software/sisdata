<?php

namespace App\Modules\Pws\Support;

use App\Modules\Pws\Services\KandunganService;

/**
 * Default MOD-WFE definitions for MOD-PWS publication approval (SRS-PWS-04,
 * SRS-GEN-03). Seeded by database/seeders/PwsSeeder; admins edit them in the
 * WFE designer afterwards. WFE never lets the submitter approve their own
 * request, so every AI draft is reviewed by a second officer.
 */
final class AliranLalai
{
    public const KUMPULAN = 'pws_penyemak_kandungan';

    public const KUMPULAN_NAMA = 'PWS — Penyemak kandungan Portal Hartanah Wakaf Selangor (Bahagian Wakaf)';

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return [
            self::single(KandunganService::ALIRAN_KANDUNGAN, 'Kelulusan terbitan kandungan Portal Hartanah Wakaf Selangor', KandunganService::SUBJEK_KANDUNGAN,
                'SRS-PWS-04: setiap draf (termasuk draf berbantu AI) disemak dan diterbitkan secara manual oleh pegawai yang diberi kuasa.'),
            self::single(KandunganService::ALIRAN_STATISTIK, 'Kelulusan terbitan statistik awam Portal Hartanah Wakaf Selangor', KandunganService::SUBJEK_STATISTIK,
                'Statistik agregat awam diluluskan sebelum dipaparkan di portal.'),
        ];
    }

    /** @return array<string, mixed> */
    private static function single(string $code, string $name, string $subject, string $description): array
    {
        return [
            'kod_aliran' => $code,
            'nama' => $name,
            'keterangan' => $description,
            'modul' => 'pws',
            'jenis_transaksi' => $subject,
            'templat' => false,
            'peringkat' => [[
                'kod' => 'semakan', 'nama' => 'Semakan dan kelulusan terbitan', 'mod_kelulusan' => 'mana_mana', 'sla_nilai' => 2, 'sla_unit' => 'hari_bekerja',
                'perlu_mfa' => false, 'perlu_justifikasi' => false,
                'pelulus' => [['jenis' => 'kumpulan', 'nilai' => self::KUMPULAN, 'label' => self::KUMPULAN_NAMA]],
                'eskalasi' => [['aras' => 1, 'tempoh_nilai' => 0, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'penyelia', 'nilai_penerima' => null]],
            ]],
            'peralihan' => [['dari' => 'mula', 'ke' => 'semakan'], ['dari' => 'semakan', 'ke' => 'tamat']],
            'susulan' => [],
            'susun_atur' => ['nodes' => ['mula' => ['x' => 0, 'y' => 120], 'semakan' => ['x' => 260, 'y' => 100], 'tamat' => ['x' => 560, 'y' => 120]]],
        ];
    }
}
