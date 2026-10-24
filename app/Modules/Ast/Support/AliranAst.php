<?php

namespace App\Modules\Ast\Support;

/**
 * Default MOD-WFE definitions for MOD-AST's approvals (SRS-GEN-03), seeded by
 * database/seeders/AstAliranSeeder and editable in the WFE designer:
 *  - ast.kelulusan_perolehan       asset registration whose acquisition cost ≥ ast.ambang_kelulusan_perolehan_rm
 *  - ast.pelupusan_aset            disposal / write-off / demolition (the asset is archived on approval)
 *  - ast.kelulusan_penyelenggaraan maintenance work whose estimate ≥ ast.ambang_kelulusan_penyelenggaraan_rm
 */
final class AliranAst
{
    public const PEROLEHAN = 'ast.kelulusan_perolehan';

    public const PELUPUSAN = 'ast.pelupusan_aset';

    public const PENYELENGGARAAN = 'ast.kelulusan_penyelenggaraan';

    public const SUBJEK_ASET = 'aset_fizikal';

    public const SUBJEK_PELUPUSAN = 'aset_pelupusan';

    public const SUBJEK_KERJA = 'rekod_penyelenggaraan';

    public const KUMPULAN_PEROLEHAN = 'ast_pelulus_perolehan';

    public const KUMPULAN_PELUPUSAN = 'ast_jawatankuasa_pelupusan';

    public const KUMPULAN_PENYELENGGARAAN = 'ast_pelulus_penyelenggaraan';

    /** @return list<array<string, mixed>> */
    public static function structures(): array
    {
        $one = fn (string $flow, string $nama, string $ket, string $subject, string $stage, string $stageName, string $group, string $groupLabel, int $sla) => [
            'kod_aliran' => $flow,
            'nama' => $nama,
            'keterangan' => $ket,
            'modul' => 'ast',
            'jenis_transaksi' => $subject,
            'templat' => false,
            'peringkat' => [[
                'kod' => $stage, 'nama' => $stageName, 'mod_kelulusan' => 'mana_mana',
                'sla_nilai' => $sla, 'sla_unit' => 'hari_bekerja', 'perlu_mfa' => false, 'perlu_justifikasi' => true,
                'pelulus' => [['jenis' => 'kumpulan', 'nilai' => $group, 'label' => $groupLabel]],
                'eskalasi' => [['aras' => 1, 'tempoh_nilai' => 0, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'penyelia', 'nilai_penerima' => null]],
            ]],
            'peralihan' => [['dari' => 'mula', 'ke' => $stage], ['dari' => $stage, 'ke' => 'tamat']],
            'susulan' => [],
            'susun_atur' => ['nodes' => ['mula' => ['x' => 0, 'y' => 120], $stage => ['x' => 260, 'y' => 100], 'tamat' => ['x' => 560, 'y' => 120]]],
        ];

        return [
            $one(self::PEROLEHAN, 'Kelulusan perolehan aset bernilai tinggi', 'Pendaftaran aset dengan kos perolehan melebihi ambang memerlukan kelulusan sebelum rekod disahkan.', self::SUBJEK_ASET, 'kelulusan_perolehan', 'Kelulusan Ketua Unit Aset', self::KUMPULAN_PEROLEHAN, 'Pelulus perolehan aset', 5),
            $one(self::PELUPUSAN, 'Kelulusan pelupusan / hapus kira aset', 'Pelupusan, hapus kira atau perobohan aset; aset diarkibkan (tidak dipadam) selepas diluluskan.', self::SUBJEK_PELUPUSAN, 'kelulusan_pelupusan', 'Jawatankuasa Pelupusan Aset', self::KUMPULAN_PELUPUSAN, 'Jawatankuasa pelupusan aset', 10),
            $one(self::PENYELENGGARAAN, 'Kelulusan kerja penyelenggaraan melebihi ambang', 'Kerja penyelenggaraan dengan anggaran kos melebihi ambang memerlukan kelulusan sebelum dilaksanakan.', self::SUBJEK_KERJA, 'kelulusan_kos', 'Kelulusan Kos Penyelenggaraan', self::KUMPULAN_PENYELENGGARAAN, 'Pelulus kos penyelenggaraan', 3),
        ];
    }
}
