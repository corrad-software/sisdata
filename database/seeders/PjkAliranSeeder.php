<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Pjk\Support\AliranLalai;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Services\DefinitionService;
use Illuminate\Database\Seeder;

/**
 * MOD-PJK × MOD-WFE (SRS-PJK-05): seeds and activates the five PJK approval
 * flows and their approver groups (Keycloak demo users as members). Lives
 * outside app/Modules because it uses MOD-WFE internals, like
 * HkmAliranSeeder / G1AliranSeeder. Idempotent.
 *
 *   php artisan db:seed --class=PjkAliranSeeder
 */
class PjkAliranSeeder extends Seeder
{
    /** Group code => demo member e-mails. */
    private const AHLI = [
        'pjk_penyemak' => ['siti.aminah@mais.test'],
        'pjk_ketua_hartanah' => ['mohd.faizal@mais.test'],
        'pjk_ketua_wakaf' => ['mohd.faizal@mais.test'],
        'pjk_ketua_baitulmal' => ['mohd.faizal@mais.test'],
        'pjk_pengurusan' => ['nurul.huda@mais.test'],
        'pjk_kewangan' => ['nurul.huda@mais.test', 'siti.aminah@mais.test'],
    ];

    public function run(DefinitionService $definitions): void
    {
        foreach (AliranLalai::KUMPULAN as $code => $name) {
            $group = Kumpulan::query()->firstOrCreate(['kod' => $code], ['nama' => $name]);
            $group->ahli()->syncWithoutDetaching(User::query()->whereIn('email', self::AHLI[$code])->pluck('id')->all());
        }

        $author = User::query()->where('email', 'admin@example.com')->first() ?? User::query()->orderBy('id')->first();
        if ($author === null) {
            $this->command?->warn('PjkAliranSeeder: tiada pengguna — dilangkau.');

            return;
        }

        foreach (AliranLalai::all() as $structure) {
            if (Definisi::query()->where('kod_aliran', $structure['kod_aliran'])->exists()) {
                continue;
            }
            $definition = $definitions->create($structure, $author, 'api', 'Dibenihkan oleh MOD-PJK (PjkAliranSeeder)');
            $definition->forceFill(['status' => Definisi::AKTIF, 'tarikh_berkuat_kuasa' => now(), 'diluluskan_pada' => now()])->save();
            $definitions->log($definition, null, 'aktif', 'sistem', 'Diaktifkan oleh PjkAliranSeeder');
            $this->command?->info("Aliran {$structure['kod_aliran']} diaktifkan.");
        }
    }
}
