<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccountStatus;
use App\Modules\Rbc\Models\Peranan;
use App\Modules\Rbc\Services\RoleService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 *
 * MOD-RBC test helpers (for every module's tests):
 *
 *     User::factory()->withRole('pengguna_paparan')->create();
 *     User::factory()->withPermissions(['dms.dokumen.lihat'])->create();
 *     User::factory()->withPermissions(['pro.lot.lihat'])->withScope('daerah', ['petaling'])->create();
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'kata_laluan_ditukar_pada' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status_akaun' => $status, 'is_active' => $status === AccountStatus::AKTIF]);
    }

    public function sso(): static
    {
        return $this->state(fn () => ['jenis_pengesahan' => 'sso', 'password' => null, 'oidc_sub' => (string) Str::uuid()]);
    }

    /** Give the user an existing role by code (e.g. a base role). */
    public function withRole(string $code): static
    {
        return $this->afterCreating(function (User $user) use ($code) {
            app(RoleService::class)->assignRoleByCode($user, $code);
        });
    }

    /**
     * Give the user exactly these permissions through a throw-away role.
     *
     * @param  list<string>  $permissions
     */
    public function withPermissions(array $permissions): static
    {
        return $this->afterCreating(function (User $user) use ($permissions) {
            $role = Peranan::query()->create(['kod' => 'ujian_'.Str::lower(Str::random(10)), 'nama' => 'Peranan ujian']);
            $role->kebenaran()->createMany(array_map(fn (string $key) => [
                'kebenaran' => $key,
                ...array_combine(['modul', 'fungsi', 'tindakan'], explode('.', $key, 3) + ['', '', '']),
            ], $permissions));
            $existing = $user->peranan()->wherePivot('sumber', 'manual')->pluck('peranan.id')->map(fn ($id) => (int) $id)->all();
            app(RoleService::class)->syncUserRoles($user, [...$existing, $role->id]);
        });
    }

    /**
     * Restrict the user's records to these scope values (pengguna_skop).
     *
     * @param  list<string>  $values
     */
    public function withScope(string $type, array $values): static
    {
        return $this->afterCreating(function (User $user) use ($type, $values) {
            foreach ($values as $value) {
                $user->skop()->create(['jenis_skop' => $type, 'nilai_skop' => $value]);
            }
        });
    }
}
