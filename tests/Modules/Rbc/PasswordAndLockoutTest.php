<?php

namespace Tests\Modules\Rbc;

use App\Models\User;
use App\Modules\Rbc\Models\KataLaluanSejarah;
use App\Modules\Rbc\Models\LogLogMasuk;
use App\Modules\Rbc\Services\PasswordPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Modules\Rbc\Concerns\InteractsWithRbc;
use Tests\TestCase;

/** SRS-RBC-11..14: internal accounts (password policy, lockout, hashing). */
class PasswordAndLockoutTest extends TestCase
{
    use InteractsWithRbc, RefreshDatabase;

    public static function weakPasswords(): array
    {
        return [
            'too short' => ['Ab1#short'],
            'only two classes' => ['alllowercase12345'],
            'only letters' => ['OnlyLettersHereNoDigits'],
        ];
    }

    #[DataProvider('weakPasswords')]
    public function test_SRS_RBC_12_weak_passwords_are_rejected_on_registration(string $password): void
    {
        $this->withHeader('Referer', 'http://localhost:3000')
            ->postJson('/api/v1/register', ['name' => 'Baru', 'email' => 'baru@mais.test', 'password' => $password, 'password_confirmation' => $password])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'baru@mais.test']);
    }

    public function test_SRS_RBC_12_policy_accepts_12_chars_with_three_classes(): void
    {
        $policy = app(PasswordPolicy::class);

        $this->assertSame([], $policy->problems('lowercase12#x'));
        $this->assertSame([], $policy->problems('Uppercase1234'));
        $this->assertNotSame([], $policy->problems('Upper#1low'));
    }

    public function test_SRS_RBC_12_admin_rejects_weak_password_when_creating_user(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/v1/users', ['name' => 'X', 'email' => 'x@mais.test', 'password' => 'password123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_SRS_RBC_12_admin_set_password_must_be_changed_at_first_login(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/v1/users', ['name' => 'Pegawai Baru', 'email' => 'baru@mais.test', 'password' => self::STRONG_PASSWORD])
            ->assertCreated();

        $this->assertTrue(User::where('email', 'baru@mais.test')->sole()->wajib_tukar_kata_laluan);

        $this->login('baru@mais.test')->assertOk();
        $this->api('GET', '/api/v1/user')->assertOk()->assertJsonPath('data.perlu_tukar_kata_laluan', true);
        $this->api('GET', '/api/v1/rbc/peranan')->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');

        $this->api('POST', '/api/v1/rbc/kata-laluan', [
            'kata_laluan_semasa' => self::STRONG_PASSWORD,
            'kata_laluan' => 'Baharu#Laluan2027',
            'kata_laluan_confirmation' => 'Baharu#Laluan2027',
        ])->assertOk();

        $this->api('GET', '/api/v1/user')->assertOk()->assertJsonPath('data.perlu_tukar_kata_laluan', false);
        $this->assertFalse(User::where('email', 'baru@mais.test')->sole()->wajib_tukar_kata_laluan);
    }

    public function test_SRS_RBC_12_password_expires_after_90_days(): void
    {
        $this->userWithPassword(attributes: ['kata_laluan_ditukar_pada' => now()->subDays(91)]);

        $this->login('pegawai@mais.test')->assertOk()->assertJsonPath('data.perlu_tukar_kata_laluan', true);
        $this->api('GET', '/api/v1/users')->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
    }

    public function test_SRS_RBC_12_password_not_expired_at_89_days(): void
    {
        $this->userWithPassword(attributes: ['kata_laluan_ditukar_pada' => now()->subDays(89)]);

        $this->login('pegawai@mais.test')->assertOk()->assertJsonPath('data.perlu_tukar_kata_laluan', false);
    }

    public function test_SRS_RBC_12_last_five_passwords_cannot_be_reused(): void
    {
        $user = $this->userWithPassword();
        $policy = app(PasswordPolicy::class);

        $history = ['Pertama#Laluan01', 'Kedua#Laluan002', 'Ketiga#Laluan03', 'Keempat#Laluan4', 'Kelima#Laluan05'];
        foreach ($history as $password) {
            $policy->setPassword($user, $password);
        }

        // Current + the 4 before it + the original are remembered; the 5 most recent are banned.
        $this->assertNotSame([], $policy->problems('Kelima#Laluan05', $user->fresh()));
        $this->assertNotSame([], $policy->problems('Kedua#Laluan002', $user->fresh()));
        $this->assertSame([], $policy->problems('Keenam#Laluan06', $user->fresh()));
        $this->assertLessThanOrEqual(5, KataLaluanSejarah::where('user_id', $user->id)->count());

        $this->login('pegawai@mais.test', 'Kelima#Laluan05')->assertOk();
        $this->api('POST', '/api/v1/rbc/kata-laluan', [
            'kata_laluan_semasa' => 'Kelima#Laluan05',
            'kata_laluan' => 'Ketiga#Laluan03',
            'kata_laluan_confirmation' => 'Ketiga#Laluan03',
        ])->assertUnprocessable()->assertJsonValidationErrors('kata_laluan');
    }

    public function test_SRS_RBC_12_change_password_requires_current_password(): void
    {
        $this->userWithPassword();
        $this->login('pegawai@mais.test')->assertOk();

        $this->api('POST', '/api/v1/rbc/kata-laluan', [
            'kata_laluan_semasa' => 'salah',
            'kata_laluan' => 'Baharu#Laluan2027',
            'kata_laluan_confirmation' => 'Baharu#Laluan2027',
        ])->assertUnprocessable()->assertJsonValidationErrors('kata_laluan_semasa');
    }

    public function test_SRS_RBC_13_account_locks_after_five_consecutive_failures(): void
    {
        $user = $this->userWithPassword();

        foreach (range(1, 4) as $i) {
            $this->login('pegawai@mais.test', 'Salah#Laluan'.$i)->assertUnprocessable();
        }
        $this->assertNull($user->fresh()->dikunci_hingga);

        $this->login('pegawai@mais.test', 'Salah#Laluan5')->assertUnprocessable()
            ->assertJsonPath('errors.email.0', fn ($m) => str_contains($m, 'dikunci'));

        $lockedUntil = User::query()->findOrFail($user->id)->dikunci_hingga;
        $this->assertTrue($lockedUntil !== null && $lockedUntil->isFuture());

        // Even the right password is refused while locked.
        $this->login('pegawai@mais.test')->assertUnprocessable();
        $this->assertSame('akaun_dikunci', LogLogMasuk::query()->latest('id')->first()->sebab);

        // Lock expires after the configured duration (default 30 min).
        $this->travel(31)->minutes();
        $this->login('pegawai@mais.test')->assertOk();
    }

    public function test_SRS_RBC_13_failures_outside_the_window_do_not_accumulate(): void
    {
        $user = $this->userWithPassword();

        foreach (range(1, 4) as $i) {
            $this->login('pegawai@mais.test', 'Salah#Laluan'.$i)->assertUnprocessable();
        }
        $this->travel(16)->minutes();
        $this->login('pegawai@mais.test', 'Salah#Laluan5')->assertUnprocessable();

        $this->assertNull($user->fresh()->dikunci_hingga);
    }

    public function test_SRS_RBC_13_success_resets_the_consecutive_count(): void
    {
        $user = $this->userWithPassword();

        foreach (range(1, 4) as $i) {
            $this->login('pegawai@mais.test', 'Salah#Laluan'.$i)->assertUnprocessable();
        }
        $this->login('pegawai@mais.test')->assertOk();
        $this->login('pegawai@mais.test', 'Salah#Laluan5')->assertUnprocessable();

        $this->assertNull($user->fresh()->dikunci_hingga);
    }

    public function test_SRS_RBC_13_threshold_and_lock_duration_are_configurable(): void
    {
        $this->setPolicy(['kunci_ambang_cubaan' => 3, 'kunci_tempoh_minit' => 5]);
        $user = $this->userWithPassword();

        foreach (range(1, 3) as $i) {
            $this->login('pegawai@mais.test', 'Salah#Laluan'.$i)->assertUnprocessable();
        }
        $this->assertTrue($user->fresh()->dikunci_hingga?->isFuture());

        $this->travel(6)->minutes();
        $this->login('pegawai@mais.test')->assertOk();
    }

    public function test_SRS_RBC_13_admin_can_unlock_an_account(): void
    {
        $user = $this->userWithPassword(attributes: ['dikunci_hingga' => now()->addHour()]);

        $this->actingAs(User::factory()->create())->postJson("/api/v1/rbc/pengguna/{$user->id}/buka-kunci")->assertForbidden();

        $this->actingAs($this->admin())->postJson("/api/v1/rbc/pengguna/{$user->id}/buka-kunci")
            ->assertOk()->assertJsonPath('data.dikunci_hingga', null);

        $this->login('pegawai@mais.test')->assertOk();
    }

    public function test_SRS_RBC_14_passwords_are_stored_as_salted_bcrypt_hashes(): void
    {
        $this->withHeader('Referer', 'http://localhost:3000')
            ->postJson('/api/v1/register', ['name' => 'Baru', 'email' => 'baru@mais.test', 'password' => self::STRONG_PASSWORD, 'password_confirmation' => self::STRONG_PASSWORD])
            ->assertCreated();

        $stored = User::where('email', 'baru@mais.test')->sole()->getAuthPassword();

        $this->assertNotSame(self::STRONG_PASSWORD, $stored);
        $this->assertStringNotContainsString(self::STRONG_PASSWORD, $stored);
        $this->assertSame('bcrypt', Hash::info($stored)['algoName']);
        $this->assertTrue(Hash::check(self::STRONG_PASSWORD, $stored));
        $this->assertNotSame($stored, Hash::make(self::STRONG_PASSWORD), 'each hash has its own salt');
    }

    public function test_SRS_RBC_11_sso_accounts_keep_no_password_and_cannot_use_password_login(): void
    {
        $user = User::factory()->sso()->create(['email' => 'sso@mais.test']);
        $this->assertNull($user->fresh()->password);

        $this->login('sso@mais.test', 'anything')->assertUnprocessable();
        $this->assertSame('akaun_sso', LogLogMasuk::query()->latest('id')->first()->sebab);

        $this->actingAs($user)->postJson('/api/v1/rbc/kata-laluan', [
            'kata_laluan_semasa' => 'x', 'kata_laluan' => self::STRONG_PASSWORD, 'kata_laluan_confirmation' => self::STRONG_PASSWORD,
        ])->assertUnprocessable();
    }

    public function test_SRS_RBC_11_admin_creates_sso_account_without_password(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/v1/users', ['name' => 'Pegawai SSO', 'email' => 'sso@mais.test', 'jenis_pengesahan' => 'sso'])
            ->assertCreated()
            ->assertJsonPath('data.jenis_pengesahan', 'sso');

        $this->assertNull(User::where('email', 'sso@mais.test')->sole()->password);

        $this->actingAs($this->admin())
            ->postJson('/api/v1/users', ['name' => 'X', 'email' => 'sso2@mais.test', 'jenis_pengesahan' => 'sso', 'password' => self::STRONG_PASSWORD])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
