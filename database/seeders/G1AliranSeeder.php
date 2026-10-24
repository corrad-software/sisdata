<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Services\DefinitionService;
use Illuminate\Database\Seeder;

/**
 * Seeds and activates the G1 approval flows MOD-PRO and MOD-PER declare,
 * plus their approver groups (with the Keycloak demo users as members so the
 * flows can be demoed). Lives outside app/Modules for the same reason as
 * HkmAliranSeeder: it uses MOD-WFE internals. Idempotent; admins refine the
 * definitions in the WFE designer afterwards.
 *
 *   php artisan db:seed --class=G1AliranSeeder
 */
class G1AliranSeeder extends Seeder
{
    /** Group code => [name, demo member e-mails]. */
    private const GROUPS = [
        'per_penyemak' => ['PER — Pegawai kanan penyemak pemerolehan', ['siti.aminah@mais.test']],
        'per_ketua_hartanah' => ['PER — Ketua Bahagian Pengurusan Hartanah', ['mohd.faizal@mais.test']],
        'per_ketua_wakaf' => ['PER — Ketua Bahagian Wakaf', ['mohd.faizal@mais.test']],
        'per_ketua_baitulmal' => ['PER — Ketua Bahagian Baitulmal', ['mohd.faizal@mais.test']],
        'per_pengurusan' => ['PER — Pengurusan Tertinggi', ['nurul.huda@mais.test']],
        'pro_pengesah' => ['PRO — Pegawai pengesah perubahan rekod induk', ['siti.aminah@mais.test', 'mohd.faizal@mais.test']],
        'hkm_penyemak' => ['HKM — Pegawai Hartanah Kanan (penyemak perubahan hak milik)', ['siti.aminah@mais.test']],
        'hkm_ketua_bahagian' => ['HKM — Ketua Bahagian (pelulus perubahan hak milik)', ['mohd.faizal@mais.test']],
    ];

    public function run(DefinitionService $definitions): void
    {
        foreach (self::GROUPS as $code => [$name, $emails]) {
            $group = Kumpulan::query()->firstOrCreate(['kod' => $code], ['nama' => $name]);
            $ids = User::query()->whereIn('email', $emails)->pluck('id')->all();
            $group->ahli()->syncWithoutDetaching($ids);
        }

        $author = User::query()->where('email', 'admin@example.com')->first() ?? User::query()->orderBy('id')->first();
        if ($author === null) {
            $this->command?->warn('G1AliranSeeder: tiada pengguna — dilangkau.');

            return;
        }

        $per = json_decode((string) file_get_contents(base_path('app/Modules/Per/Database/wfe/per.kelulusan.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ([$per, $this->singleStage('pro.perubahan_kritikal', 'Perubahan kritikal rekod induk lot', 'pro', 'lot_tanah', 'SRS-PRO-22: pengesahan pegawai kedua bagi perubahan medan kritikal lot.', true),
            $this->singleStage('pro.arkib_rekod', 'Kelulusan arkib rekod induk', 'pro', 'rekod_arkib', 'SRS-PRO-23: kelulusan pegawai sebelum rekod diarkibkan.', false)] as $structure) {
            if (Definisi::query()->where('kod_aliran', $structure['kod_aliran'])->exists()) {
                continue;
            }

            $definition = $definitions->create($structure, $author, 'api', 'Dibenihkan oleh G1AliranSeeder');
            $definition->forceFill(['status' => Definisi::AKTIF, 'tarikh_berkuat_kuasa' => now(), 'diluluskan_pada' => now()])->save();
            $definitions->log($definition, null, 'aktif', 'sistem', 'Diaktifkan oleh G1AliranSeeder');
            $this->command?->info("Aliran {$structure['kod_aliran']} diaktifkan.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function singleStage(string $code, string $name, string $module, string $subject, string $description, bool $mfa): array
    {
        return [
            'kod_aliran' => $code,
            'nama' => $name,
            'keterangan' => $description,
            'modul' => $module,
            'jenis_transaksi' => $subject,
            'templat' => false,
            'peringkat' => [[
                'kod' => 'pengesahan', 'nama' => 'Pengesahan pegawai kedua', 'mod_kelulusan' => 'mana_mana',
                'sla_nilai' => 3, 'sla_unit' => 'hari_bekerja', 'perlu_mfa' => $mfa, 'perlu_justifikasi' => true,
                'pelulus' => [['jenis' => 'kumpulan', 'nilai' => 'pro_pengesah', 'label' => 'Pegawai pengesah rekod induk']],
                'eskalasi' => [
                    ['aras' => 1, 'tempoh_nilai' => 0, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'penyelia', 'nilai_penerima' => null],
                    ['aras' => 2, 'tempoh_nilai' => 2, 'tempoh_unit' => 'hari_bekerja', 'jenis_penerima' => 'peranan', 'nilai_penerima' => 'pentadbir_sistem'],
                ],
            ]],
            'peralihan' => [
                ['dari' => 'mula', 'ke' => 'pengesahan'],
                ['dari' => 'pengesahan', 'ke' => 'tamat'],
            ],
            'susulan' => [],
            'susun_atur' => ['nodes' => [
                'mula' => ['x' => 0, 'y' => 120], 'pengesahan' => ['x' => 260, 'y' => 100], 'tamat' => ['x' => 560, 'y' => 120],
            ]],
        ];
    }
}
