<?php

namespace Tests\Feature\Console;

use App\Models\User;
use App\Modules\Rbc\Database\Seeders\RbcSeeder;
use App\Modules\Rbc\Models\PemetaanTuntutan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** sisdata:demo-seed guards and the demo SSO mapping it relies on. */
class SisdataDemoSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';
        try {
            $this->artisan('sisdata:demo-seed', ['--force' => true])->assertFailed();
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertSame(0, User::query()->count());
    }

    public function test_demo_seed_refuses_a_database_that_already_has_lots(): void
    {
        DB::table('lot_tanah')->insert(['id' => (string) Str::uuid7(), 'no_lot' => 'UJI-1', 'kod_daerah' => '1008', 'kod_mukim' => '100803', 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('sisdata:demo-seed')->assertFailed();
        $this->assertSame(0, User::query()->count());
    }

    public function test_demo_seed_mfa_bypass_option_is_refused_outside_uat(): void
    {
        $this->artisan('sisdata:demo-seed', ['--pintas-mfa-uat' => true])->assertFailed();
        $this->assertSame(0, User::query()->count());
    }

    public function test_SRS_RBC_07_seeded_district_unit_mapping_uses_ref_district_codes(): void
    {
        $this->seed(RbcSeeder::class);

        $petaling = PemetaanTuntutan::query()->where('nilai', 'Pejabat Agama Islam Daerah Petaling')->firstOrFail();
        $this->assertSame(['daerah', '1008'], [$petaling->skop_jenis, $petaling->skop_nilai]);
        $this->assertSame('1001', PemetaanTuntutan::query()->where('nilai', 'Pejabat Agama Islam Daerah Klang')->value('skop_nilai'));

        // An older seed stored the district name, which matches no kod_daerah: re-seeding repairs it.
        $petaling->forceFill(['skop_nilai' => 'petaling'])->save();
        $this->seed(RbcSeeder::class);
        $this->assertSame('1008', $petaling->fresh()->skop_nilai);
    }
}
