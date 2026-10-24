<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Rbc\Database\Seeders\RbcSeeder;
use App\Modules\Rbc\Services\RoleService;
use App\Modules\Rbc\Support\BaseRoles;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // MOD-RBC: base roles + permissions, admin@example.com as Pentadbir Sistem,
        // SSO claim mapping rules.
        $this->call(RbcSeeder::class);

        $test = User::query()->firstOrCreate(['email' => 'test@example.com'], User::factory()->raw([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]));
        app(RoleService::class)->assignRoleByCode($test, BaseRoles::PENGGUNA_PAPARAN);
    }
}
