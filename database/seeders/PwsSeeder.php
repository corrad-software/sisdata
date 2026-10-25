<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Pws\Support\AliranLalai;
use App\Modules\Rbc\Models\Peranan;
use App\Modules\Rbc\Services\RoleService;
use App\Modules\Wfe\Models\Definisi;
use App\Modules\Wfe\Models\Kumpulan;
use App\Modules\Wfe\Services\DefinitionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * MOD-PWS × MOD-RBC × MOD-WFE. Outside app/Modules because it uses RBC and
 * WFE internals (like PjkAliranSeeder). Idempotent.
 *
 *   php artisan db:seed --class=PwsSeeder
 *
 *  - role `integrasi_portal_hws` holding ONLY the integration permissions
 *    (pjk.portal.cipta, per.portal_wakaf.cipta) and the service account the
 *    public gateway acts as when it forwards a portal call to PJK / PER;
 *  - non-production only: role/account for the mock gateway simulation
 *    (pjk.bayaran.cipta) used by the portal's test-gateway page;
 *  - the two publication flows and their reviewer group.
 */
class PwsSeeder extends Seeder
{
    public const PERANAN = 'integrasi_portal_hws';

    public const PERANAN_UJIAN = 'ujian_gateway_hws';

    public function run(RoleService $roles, DefinitionService $definitions): void
    {
        $this->account($roles, (string) config('pws.akaun_integrasi'), 'Akaun Integrasi Portal Hartanah Wakaf Selangor', self::PERANAN,
            'Akaun perkhidmatan get laluan API awam Portal Hartanah Wakaf Selangor (MOD-PWS)', ['pjk.portal.cipta', 'per.portal_wakaf.cipta']);

        if (config('pws.gateway_ujian')) {
            $this->account($roles, (string) config('pws.akaun_gateway_ujian'), 'Ujian Gateway Portal Hartanah Wakaf Selangor', self::PERANAN_UJIAN,
                'BUKAN PRODUKSI: simulasi gateway pembayaran mock untuk halaman ujian portal', ['pjk.bayaran.cipta']);
        }

        Kumpulan::query()->firstOrCreate(['kod' => AliranLalai::KUMPULAN], ['nama' => AliranLalai::KUMPULAN_NAMA]);
        $author = User::query()->where('email', 'admin@example.com')->first() ?? User::query()->where('role', 'admin')->orderBy('id')->first();
        if ($author === null) {
            $this->command?->warn('PwsSeeder: tiada pentadbir — aliran kelulusan tidak dibenihkan.');

            return;
        }
        foreach (AliranLalai::all() as $structure) {
            if (Definisi::query()->where('kod_aliran', $structure['kod_aliran'])->exists()) {
                continue;
            }
            $definition = $definitions->create($structure, $author, 'api', 'Dibenihkan oleh MOD-PWS (PwsSeeder)');
            $definition->forceFill(['status' => Definisi::AKTIF, 'tarikh_berkuat_kuasa' => now(), 'diluluskan_pada' => now()])->save();
            $definitions->log($definition, null, 'aktif', 'sistem', 'Diaktifkan oleh PwsSeeder');
            $this->command?->info("Aliran {$structure['kod_aliran']} diaktifkan.");
        }
    }

    /** @param list<string> $permissions */
    private function account(RoleService $roles, string $email, string $name, string $roleCode, string $description, array $permissions): void
    {
        $role = Peranan::query()->where('kod', $roleCode)->first() ?? $roles->create(['kod' => $roleCode, 'nama' => $name, 'keterangan' => $description]);
        $roles->setPermissions($role, $permissions);

        $user = User::query()->firstOrCreate(['email' => $email], [
            'name' => $name, 'password' => Str::random(64), 'role' => 'user', 'is_active' => true, 'status_akaun' => 'aktif',
        ]);
        $roles->assignRoleByCode($user, $roleCode);
    }
}
