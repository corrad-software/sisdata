<?php

namespace Tests\Modules\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_lists_enabled_modules_and_postgres_extensions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson('/api/v1/platform/status')
            ->assertOk()
            ->assertJsonPath('data.modules.0', 'platform')
            ->assertJsonStructure(['data' => ['extensions' => ['postgis', 'vector', 'pg_trgm']]]);
    }

    public function test_status_requires_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->getJson('/api/v1/platform/status')
            ->assertForbidden();
    }
}
