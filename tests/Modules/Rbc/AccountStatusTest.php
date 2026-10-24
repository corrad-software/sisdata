<?php

namespace Tests\Modules\Rbc;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Contracts\AccountStatus;
use App\Modules\Rbc\Events\AccountStatusChanged;
use App\Modules\Rbc\Models\LogLogMasuk;
use App\Modules\Rbc\Models\StatusAkaunLog;
use App\Modules\Rbc\Support\BaseRoles;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Modules\Rbc\Concerns\InteractsWithRbc;
use Tests\TestCase;

/** SRS-RBC-15/16: four account statuses, logged changes, automatic expiry/suspension. */
class AccountStatusTest extends TestCase
{
    use InteractsWithRbc, RefreshDatabase;

    public static function blockedStatuses(): array
    {
        return [[AccountStatus::GANTUNG], [AccountStatus::NYAHAKTIF], [AccountStatus::TAMAT_TEMPOH]];
    }

    #[DataProvider('blockedStatuses')]
    public function test_SRS_RBC_15_only_aktif_accounts_can_log_in_with_password(string $status): void
    {
        $this->userWithPassword(attributes: ['status_akaun' => $status]);

        $this->login('pegawai@mais.test')->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertGuest('web');
        $this->assertSame('status_'.$status, LogLogMasuk::query()->sole()->sebab);
    }

    #[DataProvider('blockedStatuses')]
    public function test_SRS_RBC_15_non_aktif_accounts_hold_no_permissions(string $status): void
    {
        $user = User::factory()->withRole(BaseRoles::PENTADBIR_SISTEM)->create(['status_akaun' => $status]);

        $this->assertSame([], app(AccessControl::class)->permissions($user));
        $this->assertFalse($user->can('rbc.pengguna.lihat'));
        $this->actingAs($user)->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_SRS_RBC_15_status_change_is_logged_and_dispatched(): void
    {
        Event::fake([AccountStatusChanged::class]);
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)->putJson("/api/v1/rbc/pengguna/{$user->id}/status", ['status' => AccountStatus::NYAHAKTIF, 'sebab' => 'Bersara'])
            ->assertOk()
            ->assertJsonPath('data.status_akaun', AccountStatus::NYAHAKTIF)
            ->assertJsonPath('data.log_status.0.status_baru', AccountStatus::NYAHAKTIF)
            ->assertJsonPath('data.log_status.0.sebab', 'Bersara');

        $log = StatusAkaunLog::query()->sole();
        $this->assertSame([AccountStatus::AKTIF, AccountStatus::NYAHAKTIF, $admin->id], [$log->status_lama, $log->status_baru, $log->dilakukan_oleh]);
        $this->assertFalse($user->fresh()->is_active);

        Event::assertDispatched(AccountStatusChanged::class, fn ($e) => $e->user->is($user) && $e->from === 'aktif' && $e->to === 'nyahaktif' && $e->reason === 'Bersara');
    }

    public function test_SRS_RBC_15_status_change_requires_permission_and_a_valid_status(): void
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->withRole(BaseRoles::PENGGUNA_PAPARAN)->create())
            ->putJson("/api/v1/rbc/pengguna/{$user->id}/status", ['status' => AccountStatus::GANTUNG, 'sebab' => 'x'])
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->putJson("/api/v1/rbc/pengguna/{$user->id}/status", ['status' => 'dibekukan', 'sebab' => 'x'])
            ->assertUnprocessable();

        $this->assertSame(AccountStatus::AKTIF, $user->fresh()->status_akaun);
    }

    public function test_SRS_RBC_15_admin_cannot_suspend_themself(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->putJson("/api/v1/rbc/pengguna/{$admin->id}/status", ['status' => AccountStatus::GANTUNG, 'sebab' => 'x'])
            ->assertUnprocessable();
    }

    public function test_SRS_RBC_15_legacy_is_active_flag_maps_to_status(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)->putJson("/api/v1/users/{$user->id}", ['name' => $user->name, 'email' => $user->email, 'is_active' => false])
            ->assertOk()->assertJsonPath('data.status_akaun', AccountStatus::NYAHAKTIF);

        $this->assertSame(1, StatusAkaunLog::query()->count());
    }

    public function test_SRS_RBC_16_account_expires_on_its_access_end_date(): void
    {
        $user = User::factory()->create(['tarikh_tamat_capaian' => now()->subDay()->toDateString()]);
        $today = User::factory()->create(['tarikh_tamat_capaian' => now()->toDateString()]);

        $this->artisan('rbc:semak-status')->assertSuccessful();

        $this->assertSame(AccountStatus::TAMAT_TEMPOH, $user->fresh()->status_akaun);
        $this->assertSame(AccountStatus::AKTIF, $today->fresh()->status_akaun);
    }

    public function test_SRS_RBC_16_account_is_suspended_after_90_days_without_login(): void
    {
        $stale = User::factory()->create(['tarikh_log_masuk_akhir' => now()->subDays(91), 'created_at' => now()->subYear()]);
        $recent = User::factory()->create(['tarikh_log_masuk_akhir' => now()->subDays(89), 'created_at' => now()->subYear()]);

        $this->artisan('rbc:semak-status')->assertSuccessful();

        $this->assertSame(AccountStatus::GANTUNG, $stale->fresh()->status_akaun);
        $this->assertSame(AccountStatus::AKTIF, $recent->fresh()->status_akaun);
        $this->assertStringContainsString('90', StatusAkaunLog::query()->where('user_id', $stale->id)->sole()->sebab);
    }

    public function test_SRS_RBC_16_inactivity_period_is_configurable(): void
    {
        $this->setPolicy(['tidak_aktif_gantung_hari' => 30]);
        $user = User::factory()->create(['tarikh_log_masuk_akhir' => now()->subDays(31), 'created_at' => now()->subYear()]);

        $this->artisan('rbc:semak-status')->assertSuccessful();

        $this->assertSame(AccountStatus::GANTUNG, $user->fresh()->status_akaun);
    }

    public function test_SRS_RBC_16_reactivated_account_is_not_suspended_again_immediately(): void
    {
        $user = User::factory()->create(['tarikh_log_masuk_akhir' => now()->subDays(200), 'created_at' => now()->subYear()]);
        $this->artisan('rbc:semak-status');
        $this->assertSame(AccountStatus::GANTUNG, $user->fresh()->status_akaun);

        $this->actingAs($this->admin())->putJson("/api/v1/rbc/pengguna/{$user->id}/status", ['status' => AccountStatus::AKTIF, 'sebab' => 'Kembali bertugas'])->assertOk();
        $this->artisan('rbc:semak-status');

        $this->assertSame(AccountStatus::AKTIF, $user->fresh()->status_akaun);
    }

    public function test_SRS_RBC_16_expiry_is_applied_at_login_even_before_the_schedule_runs(): void
    {
        $user = $this->userWithPassword(attributes: ['tarikh_tamat_capaian' => now()->subDay()->toDateString()]);

        $this->login('pegawai@mais.test')->assertUnprocessable();

        $this->assertSame(AccountStatus::TAMAT_TEMPOH, $user->fresh()->status_akaun);
    }

    public function test_SRS_RBC_16_command_is_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode(' ');

        $this->assertStringContainsString('rbc:semak-status', $events);
        $this->assertStringContainsString('rbc:tamat-sesi', $events);
        $this->assertStringContainsString('rbc:recert', $events);
    }
}
