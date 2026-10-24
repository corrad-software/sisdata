<?php

namespace Tests\Modules\Ref;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Ref\Database\seeders\RefSeeder;
use App\Modules\Ref\RefServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class RefTestCase extends TestCase
{
    use RefreshDatabase;

    protected function seedRef(bool $spatial = true): RefSeeder
    {
        $seeder = $this->app->make(RefSeeder::class);
        $seeder->withSpatial = $spatial;
        $seeder->setContainer($this->app)->__invoke();

        return $seeder;
    }

    /**
     * Stand-in for MOD-RBC: grants exactly the listed permissions to $user.
     *
     * @param  list<string>  $permissions
     */
    protected function grant(User $user, array $permissions): void
    {
        $this->app->instance(AccessControl::class, new class($user->id, $permissions) implements AccessControl
        {
            /** @param list<string> $permissions */
            public function __construct(private int $userId, private array $permissions) {}

            public function allows(User $user, string $permission): bool
            {
                return $user->id === $this->userId && in_array($permission, $this->permissions, true);
            }

            public function scope(User $user, string $type): ?array
            {
                return null;
            }

            public function roles(User $user): array
            {
                return ['pengguna'];
            }

            public function permissions(User $user): array
            {
                return $user->id === $this->userId ? $this->permissions : [];
            }
        });
    }

    protected function viewer(): User
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->grant($user, [RefServiceProvider::PERMISSION_VIEW]);

        return $user;
    }
}
