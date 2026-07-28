<?php

namespace Tests\Feature;

use App\Livewire\UserManagement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_user_management(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_it_lists_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['name' => 'Jane Doe']);

        Livewire::actingAs($user)
            ->test(UserManagement::class)
            ->assertSee('Jane Doe');
    }

    public function test_it_can_search_users(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['name' => 'Findme Person']);
        User::factory()->create(['name' => 'Nomatch Person']);

        Livewire::actingAs($user)
            ->test(UserManagement::class)
            ->set('search', 'Findme')
            ->assertSee('Findme Person')
            ->assertDontSee('Nomatch Person');
    }

    public function test_it_can_create_a_user(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(UserManagement::class)
            ->set('name', 'New Person')
            ->set('email', 'new-person@example.com')
            ->set('password', 'password123')
            ->set('role', 'admin')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'new-person@example.com',
            'role' => 'admin',
        ]);
    }

    public function test_it_validates_required_fields_on_create(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(UserManagement::class)
            ->set('name', '')
            ->set('email', '')
            ->set('password', '')
            ->call('save')
            ->assertHasErrors(['name', 'email', 'password']);
    }

    public function test_it_can_update_a_user(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create(['name' => 'Old Name']);

        Livewire::actingAs($user)
            ->test(UserManagement::class)
            ->call('openEditModal', $target->id)
            ->set('name', 'Updated Name')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_it_can_delete_a_user(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        Livewire::actingAs($user)
            ->test(UserManagement::class)
            ->call('confirmDelete', $target->id)
            ->call('delete');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }
}
