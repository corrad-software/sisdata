<?php

namespace Tests\Modules\Rbc;

use App\Models\User;
use App\Modules\Adm\Services\ParameterService;
use App\Modules\Rbc\Services\PanduanService;
use App\Modules\Rbc\Support\BaseRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** SRS-GEN-08: per-user interactive guide (contextual online help) preferences. */
class PanduanInteraktifTest extends TestCase
{
    use RefreshDatabase;

    public function test_SRS_GEN_08_new_user_gets_the_admin_default_and_no_completed_tours(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/rbc/panduan')
            ->assertOk()
            ->assertJsonPath('data.aktif', true)
            ->assertJsonPath('data.selesai', []);

        $this->actingAs($user)->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.tetapan_panduan.aktif', true);

        $this->assertNull($user->fresh()->tetapan_panduan);
    }

    public function test_SRS_GEN_08_admin_parameter_turns_the_default_off_for_users_without_prefs(): void
    {
        app(ParameterService::class)->store(PanduanService::PARAMETER_DEFAULT, false, 'Ujian', null);

        $fresh = User::factory()->create();
        $chosen = User::factory()->create(['tetapan_panduan' => ['aktif' => true, 'selesai' => []]]);

        $this->actingAs($fresh)->getJson('/api/v1/rbc/panduan')->assertJsonPath('data.aktif', false);
        $this->actingAs($chosen)->getJson('/api/v1/rbc/panduan')->assertJsonPath('data.aktif', true);
    }

    public function test_SRS_GEN_08_user_can_switch_the_guide_off_and_on(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/v1/rbc/panduan', ['aktif' => false])
            ->assertOk()->assertJsonPath('data.aktif', false);
        $this->assertFalse($user->fresh()->tetapan_panduan['aktif']);
        $this->actingAs($user)->getJson('/api/v1/user')->assertJsonPath('data.tetapan_panduan.aktif', false);

        $this->actingAs($user)->putJson('/api/v1/rbc/panduan', ['aktif' => true])
            ->assertOk()->assertJsonPath('data.aktif', true);
    }

    public function test_SRS_GEN_08_completing_a_tour_records_its_version(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/v1/rbc/panduan', ['selesai' => ['kunci' => 'pengenalan', 'versi' => '1']])
            ->assertOk()
            ->assertJsonPath('data.selesai.pengenalan', '1')
            ->assertJsonPath('data.aktif', true);

        $this->actingAs($user)->putJson('/api/v1/rbc/panduan', ['selesai' => ['kunci' => 'pro.senarai', 'versi' => '2']])
            ->assertOk()
            ->assertJsonPath('data.selesai.pengenalan', '1')
            ->assertJsonPath('data.selesai', ['pengenalan' => '1', 'pro.senarai' => '2']);

        // A bumped version simply overwrites — the frontend shows a tour again when versions differ.
        $this->actingAs($user)->putJson('/api/v1/rbc/panduan', ['selesai' => ['kunci' => 'pengenalan', 'versi' => '2']])
            ->assertJsonPath('data.selesai.pengenalan', '2');
    }

    public function test_SRS_GEN_08_invalid_tour_keys_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/v1/rbc/panduan', ['selesai' => ['kunci' => '<script>', 'versi' => '1']])
            ->assertUnprocessable()->assertJsonValidationErrors('selesai.kunci');
        $this->actingAs($user)->putJson('/api/v1/rbc/panduan', ['aktif' => 'mungkin'])
            ->assertUnprocessable()->assertJsonValidationErrors('aktif');
    }

    public function test_SRS_GEN_08_reset_forgets_completed_tours_and_turns_the_guide_back_on(): void
    {
        $user = User::factory()->create(['tetapan_panduan' => ['aktif' => false, 'selesai' => ['pengenalan' => '1', 'gis.peta' => '1']]]);

        $this->actingAs($user)->putJson('/api/v1/rbc/panduan', ['set_semula' => true])
            ->assertOk()
            ->assertJsonPath('data.aktif', true)
            ->assertJsonPath('data.selesai', []);

        $this->assertSame([], $user->fresh()->tetapan_panduan['selesai']);
    }

    public function test_SRS_GEN_08_a_user_cannot_read_or_change_another_users_prefs(): void
    {
        $owner = User::factory()->create(['tetapan_panduan' => ['aktif' => false, 'selesai' => ['pengenalan' => '1']]]);
        $other = User::factory()->create();

        // No user id is accepted: the payload can't target anyone else, and extra keys are ignored.
        $this->actingAs($other)->putJson('/api/v1/rbc/panduan', ['aktif' => true, 'user_id' => $owner->id, 'set_semula' => true])
            ->assertOk();
        $this->actingAs($other)->putJson("/api/v1/rbc/panduan?user_id={$owner->id}", ['aktif' => false])->assertOk();

        $this->assertSame(['aktif' => false, 'selesai' => ['pengenalan' => '1']], $owner->fresh()->tetapan_panduan);
        $this->assertFalse($other->fresh()->tetapan_panduan['aktif']);

        // Another user's resource (admin user list / detail) never carries their prefs.
        $this->actingAs($other)->getJson('/api/v1/rbc/panduan')->assertJsonMissingPath('data.selesai.pengenalan');
        $admin = User::factory()->withRole(BaseRoles::PENTADBIR_SISTEM)->create();
        $listed = collect($this->actingAs($admin)->getJson('/api/v1/users?per_page=50')->assertOk()->json('data'))->firstWhere('id', $owner->id);
        $this->assertIsArray($listed);
        $this->assertArrayNotHasKey('tetapan_panduan', $listed);
        // Even an administrator's PUT only ever changes the administrator's own prefs.
        $this->actingAs($admin)->putJson('/api/v1/rbc/panduan', ['aktif' => true])->assertOk();
        $this->assertSame(false, data_get(User::query()->findOrFail($owner->id)->tetapan_panduan, 'aktif'));
    }

    public function test_SRS_GEN_08_guests_cannot_use_the_endpoint(): void
    {
        $this->getJson('/api/v1/rbc/panduan')->assertUnauthorized();
        $this->putJson('/api/v1/rbc/panduan', ['aktif' => false])->assertUnauthorized();
    }
}
