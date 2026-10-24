<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Tsu\Support\AliranTsu;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Services\DefinitionService;
use Illuminate\Database\Seeder;

/**
 * MOD-TSU × MOD-WFE: seeds and activates tsu.kelulusan_urusan,
 * tsu.kelulusan_pelupusan (layered, wakaf → Bahagian Wakaf review, highest
 * stage MFA + justification), tsu.kelulusan_lantikan and
 * tsu.pengesahan_pemindahan with their approver groups (Keycloak demo users
 * as members where they exist). Outside app/Modules because it uses MOD-WFE
 * internals (no seeding contract — same as PtnAliranSeeder). Idempotent.
 *
 *   php artisan db:seed --class=TsuAliranSeeder
 */
class TsuAliranSeeder extends Seeder
{
    private const AHLI = [
        'tsu_ketua_bahagian' => ['mohd.faizal@mais.test'],
        'tsu_bahagian_wakaf' => ['mohd.faizal@mais.test'],
        'tsu_pengurusan' => ['nurul.huda@mais.test'],
        'tsu_pegawai_berkuasa' => ['nurul.huda@mais.test'],
        'tsu_pengesah_pemindahan' => ['siti.aminah@mais.test'],
    ];

    public function run(DefinitionService $definitions): void
    {
        foreach (AliranTsu::KUMPULAN as $kod => $nama) {
            $group = Kumpulan::query()->firstOrCreate(['kod' => $kod], ['nama' => $nama]);
            $ids = User::query()->whereIn('email', self::AHLI[$kod])->pluck('id')->all();
            if ($ids !== []) {
                $group->ahli()->syncWithoutDetaching($ids);
            }
        }

        $author = User::query()->where('email', 'admin@example.com')->first() ?? User::query()->where('role', 'admin')->orderBy('id')->first() ?? User::query()->orderBy('id')->first();
        if ($author === null) {
            $this->command?->warn('TsuAliranSeeder: tiada pengguna untuk dijadikan pencipta definisi — dilangkau.');

            return;
        }
        foreach (AliranTsu::structures() as $structure) {
            if (Definisi::query()->where('kod_aliran', $structure['kod_aliran'])->exists()) {
                continue;
            }
            $definition = $definitions->create($structure, $author, 'api', 'Dibenihkan oleh MOD-TSU');
            $definition->forceFill(['status' => Definisi::AKTIF, 'tarikh_berkuat_kuasa' => now(), 'diluluskan_pada' => now()])->save();
            $definitions->log($definition, null, 'aktif', 'sistem', 'Diaktifkan oleh TsuAliranSeeder');
            $this->command?->info("Aliran {$structure['kod_aliran']} diaktifkan.");
        }
    }
}
