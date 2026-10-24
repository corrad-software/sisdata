<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_the_users_api(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }

    public function test_non_admin_users_cannot_access_the_users_api(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->getJson('/api/v1/users')
            ->assertForbidden();
    }

    public function test_it_lists_users(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['name' => 'Jane Doe']);

        $this->actingAs($user)
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Jane Doe']);
    }

    public function test_it_can_search_users(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['name' => 'Findme Person']);
        User::factory()->create(['name' => 'Nomatch Person']);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/users?search=Findme')
            ->assertOk();

        $response->assertJsonFragment(['name' => 'Findme Person']);
        $response->assertJsonMissing(['name' => 'Nomatch Person']);
    }

    public function test_it_can_create_a_user(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->postJson('/api/v1/users', [
            'name' => 'New Person',
            'email' => 'new-person@example.com',
            'password' => 'Kukuh#Laluan2026',
            'role' => 'admin',
        ]);

        $response->assertCreated()->assertJsonPath('data.email', 'new-person@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'new-person@example.com',
            'role' => 'admin',
        ]);
    }

    public function test_it_validates_required_fields_on_create(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->postJson('/api/v1/users', ['name' => '', 'email' => '', 'password' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_it_can_update_a_user(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['name' => 'Old Name', 'role' => 'user']);

        $response = $this->actingAs($user)->putJson("/api/v1/users/{$target->id}", [
            'name' => 'Updated Name',
            'email' => $target->email,
            'role' => 'user',
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_it_can_delete_a_user(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson("/api/v1/users/{$target->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }
}
