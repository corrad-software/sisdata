<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum only boots session middleware for requests it recognizes as
        // coming from the SPA frontend, which it detects via Referer/Origin —
        // and APP_URL (:8000) isn't in the stateful list; the SPA origin is.
        $this->withHeader('Referer', 'http://localhost:3000');
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()->assertJsonPath('data.email', 'jane@example.com');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_incorrect_password(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/v1/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'New Person',
            'email' => 'new-person@example.com',
            'password' => 'Kukuh#Laluan2026',
            'password_confirmation' => 'Kukuh#Laluan2026',
        ]);

        $response->assertCreated()->assertJsonPath('data.email', 'new-person@example.com');
        $this->assertDatabaseHas('users', ['email' => 'new-person@example.com']);
        $this->assertAuthenticated();
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'New Person',
            'email' => 'new-person@example.com',
            'password' => 'password123',
            'password_confirmation' => 'does-not-match',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_authenticated_user_can_fetch_own_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_guest_cannot_fetch_profile(): void
    {
        $this->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_authenticated_user_can_logout(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'jane@example.com',
            'password' => 'password123',
        ])->assertOk();

        $this->postJson('/api/v1/logout')->assertNoContent();

        $this->assertGuest('web');
    }
}
