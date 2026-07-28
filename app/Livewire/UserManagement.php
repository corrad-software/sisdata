<?php

namespace App\Livewire;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class UserManagement extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    public ?int $editingUserId = null;

    public ?int $deletingUserId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'user';

    public bool $is_active = true;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
    }

    public function openEditModal(int $userId): void
    {
        $user = User::findOrFail($userId);

        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->role;
        $this->is_active = $user->is_active;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'role' => $this->role,
            'is_active' => $this->is_active,
        ];

        if ($this->editingUserId) {
            $rules = (new UpdateUserRequest)->rules($this->editingUserId);
            $validated = Validator::make($data, $rules)->validate();

            $user = User::findOrFail($this->editingUserId);
            $user->fill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                'is_active' => $validated['is_active'] ?? false,
            ]);

            if (filled($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            $user->save();

            session()->flash('status', __('User updated successfully.'));
        } else {
            $rules = (new StoreUserRequest)->rules();
            $validated = Validator::make($data, $rules)->validate();

            User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            session()->flash('status', __('User created successfully.'));
        }

        $this->resetForm();
        $this->dispatch('close-modal', 'user-form');
    }

    public function confirmDelete(int $userId): void
    {
        $this->deletingUserId = $userId;
    }

    public function delete(): void
    {
        User::whereKey($this->deletingUserId)->delete();

        $this->deletingUserId = null;

        session()->flash('status', __('User deleted successfully.'));
        $this->dispatch('close-modal', 'confirm-user-deletion');
    }

    private function resetForm(): void
    {
        $this->reset(['editingUserId', 'name', 'email', 'password', 'role', 'is_active']);
        $this->resetErrorBag();
    }

    public function render()
    {
        $users = User::query()
            ->when($this->search, fn ($query) => $query
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(10);

        return view('livewire.user-management', ['users' => $users]);
    }
}
