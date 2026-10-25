<?php

namespace Tests\Modules\Rbc;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use App\Modules\Rbc\Events\AccessDenied;
use App\Modules\Rbc\Models\Peranan;
use App\Modules\Rbc\Services\PermissionCatalogue;
use App\Modules\Rbc\Services\PermissionSync;
use App\Modules\Rbc\Services\RbcAccessControl;
use App\Modules\Rbc\Support\BaseRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Modules\Rbc\Concerns\InteractsWithRbc;
use Tests\Modules\Rbc\Fixtures\LotUjian;
use Tests\TestCase;

/**
 * SRS-RBC-04..09, SRS-GEN-01: central evaluation through AccessControl +
 * Gate, record scoping via ScopedByAccess, base roles, least privilege.
 */
class AccessControlTest extends TestCase
{
    use InteractsWithRbc, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LotUjian::createTable();
        app(PermissionRegistry::class)->register('ujian', 'Modul Ujian', [
            'ujian.lot.lihat' => 'Lihat lot ujian',
            'ujian.lot.kemas_kini' => 'Kemas kini lot ujian',
            'ujian.lot.eksport' => 'Eksport lot ujian',
        ]);

        Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1/ujian')->group(function () {
            Route::get('lot', fn () => LotUjian::query()->orderBy('no_lot')->pluck('no_lot'))->middleware('can:ujian.lot.lihat');
            Route::put('lot/{id}', fn (string $id) => tap(LotUjian::query()->findOrFail($id))->update(['no_lot' => request('no_lot')]))->middleware('can:ujian.lot.kemas_kini');
            Route::get('lot/eksport', fn () => LotUjian::query()->pluck('no_lot'))->middleware('can:ujian.lot.eksport');
        });

        foreach ([['L1', 'petaling', 'dalaman'], ['L2', 'petaling', 'rahsia'], ['L3', 'klang', 'dalaman'], ['L4', 'gombak', 'dalaman']] as [$lot, $daerah, $klas]) {
            LotUjian::query()->create(['no_lot' => $lot, 'kod_daerah' => $daerah, 'klasifikasi' => $klas]);
        }
    }

    public function test_SRS_RBC_05_seven_base_roles_exist_as_initial_configuration(): void
    {
        $roles = Peranan::query()->whereNotNull('kategori_rfp')->orderBy('id')->pluck('nama', 'kod')->all();

        $this->assertSame([
            'pentadbir_sistem' => 'Pentadbir Sistem',
            'pentadbir_aplikasi' => 'Pentadbir Aplikasi',
            'pegawai_operasi' => 'Pegawai Operasi',
            'pelulus' => 'Pelulus',
            'pengguna_paparan' => 'Pengguna Paparan',
            'juruaudit' => 'Juruaudit',
            'pemegang_taruh_terhad' => 'Pemegang Taruh Terhad',
        ], $roles);
        $this->assertFalse(Peranan::query()->whereNotNull('kategori_rfp')->where('boleh_padam', true)->exists());
    }

    public function test_SRS_RBC_05_base_role_scopes_follow_rfp_11_2_3(): void
    {
        $perms = fn (string $role) => $this->role($role)->kebenaranKeys();
        $all = array_keys(app(PermissionCatalogue::class)->flat());

        // Pentadbir Sistem: everything registered (at seed time).
        $this->assertEmpty(array_diff(array_filter($all, fn ($k) => ! str_starts_with($k, 'ujian.')), $perms(BaseRoles::PENTADBIR_SISTEM)));
        // Pentadbir Aplikasi: users & roles, but not security policy changes.
        $this->assertContains('rbc.peranan.cipta', $perms(BaseRoles::PENTADBIR_APLIKASI));
        $this->assertNotContains('rbc.polisi.kemas_kini', $perms(BaseRoles::PENTADBIR_APLIKASI));
        // Juruaudit: read/export only.
        $this->assertContains('rbc.log_masuk.eksport', $perms(BaseRoles::JURUAUDIT));
        foreach (array_diff($perms(BaseRoles::JURUAUDIT), BaseRoles::EVERYONE) as $key) { // support tickets are for everyone (SRS-TKT-01)
            $this->assertTrue(Str::endsWith($key, ['.lihat', '.eksport']), $key);
        }
        // Pengguna Paparan: only "lihat", never platform administration.
        foreach (array_diff($perms(BaseRoles::PENGGUNA_PAPARAN), BaseRoles::EVERYONE) as $key) {
            $this->assertStringEndsWith('.lihat', $key);
            $this->assertFalse(Str::startsWith($key, ['rbc.', 'aud.', 'adm.']), $key);
        }
    }

    public function test_SRS_DMS_19_wildcard_base_roles_never_get_sulit_or_rahsia_access(): void
    {
        foreach ([BaseRoles::PEGAWAI_OPERASI, BaseRoles::PELULUS, BaseRoles::PENGGUNA_PAPARAN] as $role) {
            $this->assertFalse(BaseRoles::grantsByDefault($role, 'dms.sulit.lihat'), $role);
            $this->assertFalse(BaseRoles::grantsByDefault($role, 'dms.rahsia.lihat'), $role);
            $this->assertTrue(BaseRoles::grantsByDefault($role, 'dms.dokumen.lihat'), $role);
        }

        // Only Pentadbir Sistem holds them by default; everyone else needs an explicit grant.
        $this->assertTrue(BaseRoles::grantsByDefault(BaseRoles::PENTADBIR_SISTEM, 'dms.rahsia.lihat'));
        $this->assertTrue(BaseRoles::grantsByDefault(BaseRoles::PENTADBIR_APLIKASI, 'ref.rujukan.lihat'));
        $this->assertTrue(BaseRoles::grantsByDefault(BaseRoles::PENTADBIR_APLIKASI, 'wfe.tugasan.lihat'));
        $this->assertFalse(BaseRoles::grantsByDefault(BaseRoles::PENTADBIR_APLIKASI, 'dms.sulit.lihat'));
        // Dashboard settings are administration, not an operations update.
        $this->assertFalse(BaseRoles::grantsByDefault(BaseRoles::PEGAWAI_OPERASI, 'dsh.widget.kemas_kini'));
        // Other users' AI questions/feedback are not for view-only or operational roles.
        foreach ([BaseRoles::PENGGUNA_PAPARAN, BaseRoles::PELULUS, BaseRoles::PEGAWAI_OPERASI] as $role) {
            $this->assertFalse(BaseRoles::grantsByDefault($role, 'kba.analitik.lihat'), $role);
            $this->assertFalse(BaseRoles::grantsByDefault($role, 'kba.maklum_balas.lihat'), $role);
        }
        $this->assertTrue(BaseRoles::grantsByDefault(BaseRoles::PENGGUNA_PAPARAN, 'kba.soalan.lihat'));
        // SRS-TKT-01: every role can open a support ticket; ticket configuration stays with admins.
        foreach (array_keys(BaseRoles::definitions()) as $role) {
            $this->assertTrue(BaseRoles::grantsByDefault($role, 'tkt.tiket.cipta'), $role);
        }
        $this->assertFalse(BaseRoles::grantsByDefault(BaseRoles::PEGAWAI_OPERASI, 'tkt.konfigurasi.kemas_kini'));
    }

    public function test_SRS_RBC_05_a_newly_registered_module_gets_base_role_defaults_once(): void
    {
        app(PermissionSync::class)->sync(); // ujian.* registered in setUp → new keys

        $this->assertContains('ujian.lot.lihat', $this->role(BaseRoles::PENGGUNA_PAPARAN)->kebenaranKeys());
        $this->assertNotContains('ujian.lot.kemas_kini', $this->role(BaseRoles::PENGGUNA_PAPARAN)->kebenaranKeys());
        $this->assertContains('ujian.lot.kemas_kini', $this->role(BaseRoles::PEGAWAI_OPERASI)->kebenaranKeys());
        $this->assertContains('ujian.lot.eksport', $this->role(BaseRoles::PENTADBIR_SISTEM)->kebenaranKeys());
        $this->assertDatabaseHas('kebenaran', ['kunci' => 'ujian.lot.lihat', 'modul' => 'ujian', 'fungsi' => 'lot', 'tindakan' => 'lihat']);

        // An admin removes it; the next sync must not put it back.
        $role = $this->role(BaseRoles::PENGGUNA_PAPARAN);
        $role->kebenaran()->where('kebenaran', 'ujian.lot.lihat')->delete();
        app(PermissionSync::class)->sync();
        $this->assertNotContains('ujian.lot.lihat', $role->kebenaranKeys());
    }

    public function test_SRS_RBC_05_legacy_admin_account_is_pentadbir_sistem(): void
    {
        $legacy = User::factory()->create(['role' => 'admin']);

        $this->assertSame([BaseRoles::PENTADBIR_SISTEM], app(AccessControl::class)->roles($legacy));
        $this->assertTrue($legacy->can('rbc.peranan.cipta'));

        $legacy->update(['role' => 'user']);
        RbcAccessControl::flushAll();
        $this->assertSame([], app(AccessControl::class)->roles($legacy));
    }

    public function test_SRS_RBC_04_permission_is_enforced_on_the_api(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/v1/ujian/lot')->assertForbidden();
        $this->actingAs(User::factory()->withPermissions(['ujian.lot.lihat'])->create())->getJson('/api/v1/ujian/lot')->assertOk()->assertJsonCount(4);
        $this->actingAs(User::factory()->withPermissions(['ujian.lot.lihat'])->create())->putJson('/api/v1/ujian/lot/1', ['no_lot' => 'X'])->assertForbidden();
    }

    public function test_SRS_RBC_07_officer_of_district_a_cannot_see_district_b(): void
    {
        $officer = User::factory()->withPermissions(['ujian.lot.lihat', 'ujian.lot.kemas_kini'])->withScope('daerah', ['petaling'])->create();

        $this->actingAs($officer)->getJson('/api/v1/ujian/lot')->assertOk()->assertExactJson(['L1', 'L2']);

        // Direct API call on another district's record: not found, not editable.
        $klang = LotUjian::query()->withoutGlobalScope('access')->where('no_lot', 'L3')->sole();
        $this->actingAs($officer)->putJson("/api/v1/ujian/lot/{$klang->id}", ['no_lot' => 'DIUBAH'])->assertNotFound();
        $this->assertSame('L3', $klang->fresh()->no_lot);
    }

    public function test_SRS_RBC_07_scopes_combine_across_dimensions(): void
    {
        $officer = User::factory()->withPermissions(['ujian.lot.lihat'])
            ->withScope('daerah', ['petaling', 'klang'])
            ->withScope('klasifikasi', ['dalaman'])
            ->create();

        $this->actingAs($officer)->getJson('/api/v1/ujian/lot')->assertExactJson(['L1', 'L3']);
    }

    public function test_SRS_RBC_07_role_scope_and_user_scope_intersect(): void
    {
        $role = Peranan::query()->create(['kod' => 'pegawai_selangor_tengah', 'nama' => 'Pegawai Selangor Tengah']);
        $role->kebenaran()->create(['kebenaran' => 'ujian.lot.lihat', 'modul' => 'ujian', 'fungsi' => 'lot', 'tindakan' => 'lihat']);
        $role->skop()->createMany([['jenis_skop' => 'daerah', 'nilai_skop' => 'petaling'], ['jenis_skop' => 'daerah', 'nilai_skop' => 'gombak']]);

        $roleOnly = User::factory()->withRole('pegawai_selangor_tengah')->create();
        $both = User::factory()->withRole('pegawai_selangor_tengah')->withScope('daerah', ['gombak', 'klang'])->create();

        $this->actingAs($roleOnly)->getJson('/api/v1/ujian/lot')->assertExactJson(['L1', 'L2', 'L4']);
        $this->actingAs($both)->getJson('/api/v1/ujian/lot')->assertExactJson(['L4']);
        $this->assertSame(['gombak'], app(AccessControl::class)->scope($both, AccessControl::SCOPE_DAERAH));
    }

    public function test_SRS_RBC_07_unrestricted_roles_and_console_jobs_see_everything(): void
    {
        app(PermissionSync::class)->sync();
        $this->actingAs($this->admin())->getJson('/api/v1/ujian/lot')->assertOk();
        $this->assertNull(app(AccessControl::class)->scope($this->admin(), AccessControl::SCOPE_DAERAH));

        $this->app['auth']->forgetGuards(); // no user, as in a queue job or artisan command
        $this->assertSame(4, LotUjian::query()->count());
    }

    public function test_SRS_RBC_08_gate_and_can_middleware_delegate_to_access_control(): void
    {
        $user = User::factory()->withPermissions(['ujian.lot.lihat'])->create();

        $this->assertTrue($user->can('ujian.lot.lihat'));
        $this->assertFalse($user->can('ujian.lot.kemas_kini'));
        $this->assertSame(['ujian.lot.lihat'], app(AccessControl::class)->permissions($user));

        // UserResource.permissions comes from AccessControl.
        $this->actingAs($user)->getJson('/api/v1/user')->assertJsonPath('data.permissions', ['ujian.lot.lihat']);
    }

    public function test_SRS_RBC_08_denied_requests_dispatch_access_denied(): void
    {
        Event::fake([AccessDenied::class]);
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/ujian/lot')->assertForbidden();
        $this->actingAs($user)->putJson('/api/v1/settings', [])->assertForbidden();

        Event::assertDispatched(AccessDenied::class, fn ($e) => $e->user->is($user) && $e->permission === 'ujian.lot.lihat' && $e->resource === 'GET api/v1/ujian/lot');
        Event::assertDispatched(AccessDenied::class, fn ($e) => $e->permission === 'rbc.pentadbiran.akses');
    }

    public function test_SRS_RBC_08_admin_alias_middleware_is_permission_based(): void
    {
        $legacyRoleOnly = User::factory()->create();
        $legacyRoleOnly->forceFill(['role' => 'admin'])->saveQuietly(); // the string alone grants nothing

        $this->actingAs($legacyRoleOnly)->getJson('/api/v1/platform/status')->assertForbidden();
        $this->actingAs(User::factory()->withPermissions(['rbc.pentadbiran.akses'])->create())->getJson('/api/v1/platform/status')->assertOk();
    }

    public function test_SRS_RBC_09_new_role_starts_with_no_permissions(): void
    {
        $response = $this->actingAs($this->admin())->postJson('/api/v1/rbc/peranan', ['nama' => 'Pegawai Khas'])->assertCreated();

        $this->assertSame([], $response->json('data.kebenaran'));
        $user = User::factory()->withRole($response->json('data.kod'))->create();
        $this->assertSame([], app(AccessControl::class)->permissions($user));
        $this->actingAs($user)->getJson('/api/v1/ujian/lot')->assertForbidden();
    }

    public function test_SRS_RBC_09_user_without_roles_has_no_access(): void
    {
        $user = User::factory()->create();

        $this->assertSame([], app(AccessControl::class)->permissions($user));
        foreach (['/api/v1/users', '/api/v1/rbc/peranan', '/api/v1/rbc/matriks', '/api/v1/rbc/log-masuk', '/api/v1/rbc/polisi'] as $uri) {
            $this->actingAs($user)->getJson($uri)->assertForbidden();
        }
    }

    /** Acceptance #6: Pengguna Paparan cannot change data through the API or export. */
    public function test_SRS_RBC_04_pengguna_paparan_cannot_modify_data_through_any_endpoint(): void
    {
        app(PermissionSync::class)->sync();
        $viewer = User::factory()->withRole(BaseRoles::PENGGUNA_PAPARAN)->create();

        $this->actingAs($viewer)->getJson('/api/v1/ujian/lot')->assertOk();
        $this->actingAs($viewer)->putJson('/api/v1/ujian/lot/1', ['no_lot' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->getJson('/api/v1/ujian/lot/eksport')->assertForbidden();

        $target = User::factory()->create();
        $role = Peranan::query()->first();
        $writes = [
            ['POST', '/api/v1/users'], ['PUT', "/api/v1/users/{$target->id}"], ['DELETE', "/api/v1/users/{$target->id}"],
            ['POST', '/api/v1/rbc/peranan'], ['PUT', "/api/v1/rbc/peranan/{$role->id}"], ['PUT', "/api/v1/rbc/peranan/{$role->id}/kebenaran"],
            ['DELETE', "/api/v1/rbc/peranan/{$role->id}"], ['PUT', "/api/v1/rbc/pengguna/{$target->id}/status"],
            ['PUT', "/api/v1/rbc/pengguna/{$target->id}/peranan"], ['PUT', '/api/v1/rbc/polisi'], ['POST', '/api/v1/rbc/pemetaan'],
            ['POST', '/api/v1/rbc/recert'], ['PUT', '/api/v1/settings'],
            ['GET', '/api/v1/rbc/matriks/eksport'], ['GET', '/api/v1/rbc/log-masuk/eksport'],
        ];
        foreach ($writes as [$method, $uri]) {
            $this->actingAs($viewer)->json($method, $uri, [])->assertForbidden();
        }
    }

    /** Acceptance #7: a new district-scoped role takes effect immediately. */
    public function test_SRS_RBC_06_new_scoped_role_takes_effect_immediately(): void
    {
        $appAdmin = User::factory()->withRole(BaseRoles::PENTADBIR_APLIKASI)->withPermissions(['ujian.lot.lihat'])->create();
        $officer = User::factory()->create();

        $roleId = $this->actingAs($appAdmin)->postJson('/api/v1/rbc/peranan', ['nama' => 'Pegawai Daerah Klang'])->assertCreated()->json('data.id');
        $this->actingAs($appAdmin)->putJson("/api/v1/rbc/peranan/{$roleId}/kebenaran", ['kebenaran' => ['ujian.lot.lihat']])->assertOk();
        $this->actingAs($appAdmin)->putJson("/api/v1/rbc/peranan/{$roleId}/skop", ['skop' => ['daerah' => ['klang']]])->assertOk();
        $this->actingAs($appAdmin)->putJson("/api/v1/rbc/pengguna/{$officer->id}/peranan", ['peranan' => [$roleId]])->assertOk();

        $this->actingAs($officer)->getJson('/api/v1/ujian/lot')->assertOk()->assertExactJson(['L3']);
    }

    public function test_SRS_GEN_01_every_rbc_and_user_route_authorises_through_the_gate(): void
    {
        $open = ['rbc.sesi.status', 'rbc.sesi.lanjut', 'rbc.kata_laluan.tukar'];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();
            if (! (str_starts_with($name, 'rbc.') || str_starts_with($name, 'users.')) || in_array($name, $open, true)) {
                continue;
            }

            $can = collect($route->gatherMiddleware())->first(fn ($m) => is_string($m) && str_starts_with($m, 'can:'));
            $this->assertNotNull($can, "{$name} has no can: middleware");
            $this->assertMatchesRegularExpression('/^can:[a-z_]+\.[a-z_]+\.[a-z_]+$/', $can);
        }
    }
}
