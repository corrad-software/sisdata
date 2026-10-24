<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Cki\Support\Aliran;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Services\DefinitionService;
use Illuminate\Database\Seeder;

/**
 * MOD-CKI × MOD-WFE (SRS-CKI-14): seeds and activates the payment-approval
 * flow `cki.kelulusan_bayaran` and its approver groups (Keycloak demo users
 * as members). Lives outside app/Modules because it uses MOD-WFE internals,
 * like PjkAliranSeeder. Idempotent.
 *
 *   php artisan db:seed --class=CkiAliranSeeder
 */
class CkiAliranSeeder extends Seeder
{
    /** Group code => demo member e-mails. */
    private const AHLI = [
        'cki_kewangan' => ['nurul.huda@mais.test', 'siti.aminah@mais.test'],
        'cki_ketua_bahagian' => ['mohd.faizal@mais.test'],
        'cki_pengurusan' => ['nurul.huda@mais.test'],
    ];

    public function run(DefinitionService $definitions): void
    {
        foreach (Aliran::KUMPULAN as $code => $name) {
            $group = Kumpulan::query()->firstOrCreate(['kod' => $code], ['nama' => $name]);
            $group->ahli()->syncWithoutDetaching(User::query()->whereIn('email', self::AHLI[$code])->pluck('id')->all());
        }

        $author = User::query()->where('email', 'admin@example.com')->first() ?? User::query()->orderBy('id')->first();
        if ($author === null) {
            $this->command?->warn('CkiAliranSeeder: tiada pengguna — dilangkau.');

            return;
        }

        $structure = Aliran::definisi();
        if (Definisi::query()->where('kod_aliran', $structure['kod_aliran'])->exists()) {
            return;
        }
        $definition = $definitions->create($structure, $author, 'api', 'Dibenihkan oleh MOD-CKI (CkiAliranSeeder)');
        $definition->forceFill(['status' => Definisi::AKTIF, 'tarikh_berkuat_kuasa' => now(), 'diluluskan_pada' => now()])->save();
        $definitions->log($definition, null, 'aktif', 'sistem', 'Diaktifkan oleh CkiAliranSeeder');
        $this->command?->info("Aliran {$structure['kod_aliran']} diaktifkan.");
    }
}
