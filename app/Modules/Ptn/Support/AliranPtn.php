<?php

namespace App\Modules\Ptn\Support;

/**
 * Default MOD-WFE definitions for MOD-PTN's approvals (SRS-GEN-03), seeded
 * by database/seeders/PtnAliranSeeder and editable in the WFE designer:
 *  - ptn.pengesahan_laporan  (SRS-PTN-09/10) report validation by the district validating officer
 *  - ptn.kemaskini_lot       (SRS-PTN-08) master-data change proposal by an authorised officer
 *  - ptn.pengesahan_tindakan (SRS-PTN-13) verification that a corrective action is resolved
 */
final class AliranPtn
{
    public const LAPORAN = 'ptn.pengesahan_laporan';

    public const KEMASKINI_LOT = 'ptn.kemaskini_lot';

    public const TINDAKAN = 'ptn.pengesahan_tindakan';

    public const SUBJEK_LAPORAN = 'laporan_lawatan';

    public const SUBJEK_CADANGAN = 'cadangan_kemaskini_lot';

    public const SUBJEK_TINDAKAN = 'tindakan_pembetulan';

    public const KUMPULAN_PENGESAH = 'ptn_pengesah_laporan';

    public const KUMPULAN_PEGAWAI_BERKUASA = 'ptn_pegawai_berkuasa';

    public const KUMPULAN_PENYELIA = 'ptn_penyelia_tindakan';

    /** @return list<array<string, mixed>> */
    public static function structures(): array
    {
        $one = fn (string $flow, string $nama, string $ket, string $subject, string $stage, string $stageName, string $group, string $groupLabel, int $sla, bool $mfa) => [
            'kod_aliran' => $flow,
            'nama' => $nama,
            'keterangan' => $ket,
            'modul' => 'ptn',
            'jenis_transaksi' => $subject,
            'templat' => false,
            'peringkat' => [[
                'kod' => $stage, 'nama' => $stageName, 'mod_kelulusan' => 'mana_mana',
                'sla_nilai' => $sla, 'sla_unit' => 'hari_bekerja', 'perlu_mfa' => $mfa, 'perlu_justifikasi' => true,
                'pelulus' => [['jenis' => 'kumpulan', 'nilai' => $group, 'label' => $groupLabel]],
                'eskalasi' => [['aras' => 1, 'tempoh_nilai' => 0, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'penyelia', 'nilai_penerima' => null]],
            ]],
            'peralihan' => [['dari' => 'mula', 'ke' => $stage], ['dari' => $stage, 'ke' => 'tamat']],
            'susulan' => [],
            'susun_atur' => ['nodes' => ['mula' => ['x' => 0, 'y' => 120], $stage => ['x' => 260, 'y' => 100], 'tamat' => ['x' => 560, 'y' => 120]]],
        ];

        return [
            $one(self::LAPORAN, 'Pengesahan laporan lawatan pemantauan', 'SRS-PTN-09/10: laporan lawatan disahkan pegawai pengesah dan dikunci.', self::SUBJEK_LAPORAN, 'pengesahan_laporan', 'Pengesahan Pegawai Daerah', self::KUMPULAN_PENGESAH, 'Pegawai pengesah laporan', 3, false),
            $one(self::KEMASKINI_LOT, 'Kelulusan cadangan kemas kini rekod tanah', 'SRS-PTN-08: perubahan Pangkalan Data Induk daripada lawatan hanya selepas kelulusan pegawai berkuasa.', self::SUBJEK_CADANGAN, 'kelulusan_pegawai_berkuasa', 'Kelulusan Pegawai Berkuasa', self::KUMPULAN_PEGAWAI_BERKUASA, 'Pegawai berkuasa rekod tanah', 5, false),
            $one(self::TINDAKAN, 'Pengesahan penyelesaian tindakan pembetulan', 'SRS-PTN-13: penyelesaian tindakan pembetulan disahkan penyelia sebelum isu ditutup.', self::SUBJEK_TINDAKAN, 'pengesahan_penyelesaian', 'Pengesahan Penyelia', self::KUMPULAN_PENYELIA, 'Penyelia tindakan pembetulan', 3, false),
        ];
    }
}
