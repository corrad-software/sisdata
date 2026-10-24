<?php

namespace Tests\Modules\Rbc;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Events\UserRolesChanged;
use App\Modules\Rbc\Models\Peranan;
use App\Modules\Rbc\Models\PerananKebenaran;
use App\Modules\Rbc\Support\BaseRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Modules\Rbc\Concerns\InteractsWithRbc;
use Tests\TestCase;

/** SRS-RBC-06: roles added, edited, deactivated and cloned through the admin API — no code change. */
class RoleManagementTest extends TestCase
{
    use InteractsWithRbc, RefreshDatabase;

    public function test_SRS_RBC_06_admin_creates_and_edits_a_role_with_permissions_and_scopes(): void
    {
        $admin = $this->admin();

        $id = $this->actingAs($admin)->postJson('/api/v1/rbc/peranan', ['nama' => 'Penyelia Sewaan', 'keterangan' => 'Unit Sewaan'])
            ->assertCreated()
            ->assertJsonPath('data.kod', 'penyelia_sewaan')
            ->assertJsonPath('data.status', 'aktif')
            ->json('data.id');

        $this->actingAs($admin)->putJson("/api/v1/rbc/peranan/{$id}", ['nama' => 'Penyelia Unit Sewaan', 'keterangan' => null])
            ->assertOk()->assertJsonPath('data.nama', 'Penyelia Unit Sewaan');

        $this->actingAs($admin)->putJson("/api/v1/rbc/peranan/{$id}/kebenaran", ['kebenaran' => ['rbc.pengguna.lihat', 'rbc.laporan.eksport']])
            ->assertOk()->assertJsonPath('data.kebenaran', ['rbc.laporan.eksport', 'rbc.pengguna.lihat']);

        $this->actingAs($admin)->putJson("/api/v1/rbc/peranan/{$id}/skop", ['skop' => ['daerah' => ['petaling'], 'klasifikasi' => ['dalaman', 'sulit']]])
            ->assertOk()->assertJsonPath('data.skop.daerah', ['petaling']);

        $this->assertDatabaseHas('peranan_kebenaran', ['peranan_id' => $id, 'kebenaran' => 'rbc.laporan.eksport', 'modul' => 'rbc', 'fungsi' => 'laporan', 'tindakan' => 'eksport']);
    }

    public function test_SRS_RBC_06_unknown_permissions_and_scope_types_are_rejected(): void
    {
        $admin = $this->admin();
        $role = Peranan::query()->create(['kod' => 'ujian', 'nama' => 'Ujian']);

        $this->actingAs($admin)->putJson("/api/v1/rbc/peranan/{$role->id}/kebenaran", ['kebenaran' => ['rbc.pengguna.terbang']])
            ->assertUnprocessable()->assertJsonValidationErrors('kebenaran');
        $this->actingAs($admin)->putJson("/api/v1/rbc/peranan/{$role->id}/skop", ['skop' => ['planet' => ['marikh']]])
            ->assertUnprocessable()->assertJsonValidationErrors('skop');
    }

    public function test_SRS_RBC_06_deactivated_role_grants_nothing_until_reactivated(): void
    {
        $admin = $this->admin();
        $role = Peranan::query()->create(['kod' => 'pemerhati', 'nama' => 'Pemerhati']);
        $role->kebenaran()->create(['kebenaran' => 'rbc.matriks.lihat', 'modul' => 'rbc', 'fungsi' => 'matriks', 'tindakan' => 'lihat']);
        $user = User::factory()->withRole('pemerhati')->create();

        $this->actingAs($user)->getJson('/api/v1/rbc/matriks')->assertOk();

        $this->actingAs($admin)->putJson("/api/v1/rbc/peranan/{$role->id}/status", ['status' => 'tidak_aktif'])->assertOk();
        $this->actingAs($user)->getJson('/api/v1/rbc/matriks')->assertForbidden();

        $this->actingAs($admin)->putJson("/api/v1/rbc/peranan/{$role->id}/status", ['status' => 'aktif'])->assertOk();
        $this->actingAs($user)->getJson('/api/v1/rbc/matriks')->assertOk();
    }

    public function test_SRS_RBC_06_role_is_cloned_with_its_permissions_and_scopes(): void
    {
        $source = $this->role(BaseRoles::JURUAUDIT);
        $source->skop()->create(['jenis_skop' => 'bahagian', 'nilai_skop' => 'ICT']);

        $clone = $this->actingAs($this->admin())->postJson("/api/v1/rbc/peranan/{$source->id}/klon", ['nama' => 'Juruaudit Dalaman ICT'])
            ->assertCreated()
            ->assertJsonPath('data.kategori_rfp', null)
            ->assertJsonPath('data.boleh_padam', true)
            ->assertJsonPath('data.skop.bahagian', ['ICT'])
            ->json('data');

        $this->assertSame($source->kebenaranKeys(), $clone['kebenaran']);
    }

    public function test_SRS_RBC_06_base_roles_cannot_be_deleted_and_used_roles_are_protected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->deleteJson('/api/v1/rbc/peranan/'.$this->role(BaseRoles::PELULUS)->id)->assertUnprocessable();

        $custom = Peranan::query()->create(['kod' => 'sementara', 'nama' => 'Sementara']);
        $user = User::factory()->withRole('sementara')->create();
        $this->actingAs($admin)->deleteJson("/api/v1/rbc/peranan/{$custom->id}")->assertUnprocessable();

        $this->actingAs($admin)->putJson("/api/v1/rbc/pengguna/{$user->id}/peranan", ['peranan' => []])->assertOk();
        $this->actingAs($admin)->deleteJson("/api/v1/rbc/peranan/{$custom->id}")->assertNoContent();
    }

    public function test_SRS_RBC_06_role_management_requires_permission(): void
    {
        $role = $this->role(BaseRoles::PELULUS);
        $viewer = User::factory()->withPermissions(['rbc.peranan.lihat'])->create();

        $this->actingAs($viewer)->getJson('/api/v1/rbc/peranan')->assertOk()->assertJsonCount(Peranan::count(), 'data');
        $this->actingAs($viewer)->getJson('/api/v1/rbc/kebenaran')->assertOk()->assertJsonPath('data.tindakan', AccessControl::ACTIONS);
        $this->actingAs($viewer)->postJson('/api/v1/rbc/peranan', ['nama' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->putJson("/api/v1/rbc/peranan/{$role->id}/kebenaran", ['kebenaran' => []])->assertForbidden();
        $this->actingAs($viewer)->postJson("/api/v1/rbc/peranan/{$role->id}/klon", ['nama' => 'X'])->assertForbidden();
    }

    public function test_SRS_RBC_06_admins_cannot_grant_permissions_they_do_not_hold(): void
    {
        $appAdmin = User::factory()->withRole(BaseRoles::PENTADBIR_APLIKASI)->create();
        $role = Peranan::query()->create(['kod' => 'baru', 'nama' => 'Baru']);
        $target = User::factory()->create();

        // Pentadbir Aplikasi lacks rbc.polisi.kemas_kini → cannot hand it out, directly or via Pentadbir Sistem.
        $this->actingAs($appAdmin)->putJson("/api/v1/rbc/peranan/{$role->id}/kebenaran", ['kebenaran' => ['rbc.polisi.kemas_kini']])->assertForbidden();
        $this->actingAs($appAdmin)->putJson("/api/v1/rbc/pengguna/{$target->id}/peranan", ['peranan' => [$this->role(BaseRoles::PENTADBIR_SISTEM)->id]])->assertForbidden();
        $this->actingAs($appAdmin)->putJson("/api/v1/rbc/pengguna/{$appAdmin->id}/peranan", ['peranan' => [$this->role(BaseRoles::PENTADBIR_SISTEM)->id]])->assertForbidden();

        // A role whose permissions are all held by the granting admin can be handed out. (Not a base
        // role: e.g. Pengguna Paparan carries every module's *.lihat, incl. some Pentadbir Aplikasi lacks.)
        $held = Peranan::query()->create(['kod' => 'paparan_pengguna', 'nama' => 'Paparan Pengguna']);
        $held->kebenaran()->create(['kebenaran' => 'rbc.pengguna.lihat', ...PerananKebenaran::split('rbc.pengguna.lihat')]);
        $this->actingAs($appAdmin)->putJson("/api/v1/rbc/pengguna/{$target->id}/peranan", ['peranan' => [$held->id]])->assertOk();
    }

    public function test_SRS_RBC_04_user_roles_and_scopes_are_assigned_and_reported(): void
    {
        Event::fake([UserRolesChanged::class]);
        $admin = $this->admin();
        $user = User::factory()->create(['bahagian' => 'Bahagian Wakaf']);

        $this->actingAs($admin)->putJson("/api/v1/rbc/pengguna/{$user->id}/peranan", ['peranan' => [$this->role(BaseRoles::PEGAWAI_OPERASI)->id]])->assertOk();
        $this->actingAs($admin)->putJson("/api/v1/rbc/pengguna/{$user->id}/skop", ['skop' => ['daerah' => ['klang'], 'mukim' => ['kapar']]])
            ->assertOk()
            ->assertJsonPath('data.peranan.0.kod', BaseRoles::PEGAWAI_OPERASI)
            ->assertJsonPath('data.skop_berkesan.daerah', ['klang'])
            ->assertJsonPath('data.skop_berkesan.bahagian', null);

        Event::assertDispatched(UserRolesChanged::class, fn ($e) => $e->user->is($user) && $e->added === [BaseRoles::PEGAWAI_OPERASI] && $e->actor?->is($admin));
    }

    public function test_SRS_RBC_04_user_list_filters_by_status_and_role(): void
    {
        $admin = $this->admin();
        User::factory()->withRole(BaseRoles::PELULUS)->create(['name' => 'Pelulus Satu']);
        User::factory()->create(['name' => 'Digantung', 'status_akaun' => 'gantung']);

        $this->actingAs($admin)->getJson('/api/v1/users?peranan=pelulus')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Pelulus Satu');
        $this->actingAs($admin)->getJson('/api/v1/users?status=gantung')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Digantung');
    }
}
