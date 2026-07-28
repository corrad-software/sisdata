<div>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <x-alert type="success" dismissible>{{ session('status') }}</x-alert>
            @endif

            <div class="bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
                    <h2 class="font-heading font-semibold text-heading-2xs text-txt-black-900">
                        {{ __('User Management') }}
                    </h2>

                    <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                        <input
                            type="search"
                            wire:model.live.debounce.400ms="search"
                            placeholder="{{ __('Search by name or email...') }}"
                            class="rounded-md border-otl-gray-300 focus:border-primary-600 focus:ring-fr-primary text-body-sm w-full sm:w-64"
                        >

                        <x-button
                            variant="primary"
                            wire:click="openCreateModal"
                            x-data=""
                            x-on:click="$dispatch('open-modal', 'user-form')"
                        >
                            {{ __('Add User') }}
                        </x-button>
                    </div>
                </div>

                <x-table>
                    <x-slot name="head">
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </x-slot>

                    @forelse ($users as $user)
                        <tr wire:key="user-{{ $user->id }}">
                            <td class="font-medium">{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td class="capitalize">{{ $user->role }}</td>
                            <td>
                                @if ($user->is_active)
                                    <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-0.5 text-body-xs font-medium text-success-700">
                                        {{ __('Active') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-bg-washed px-2.5 py-0.5 text-body-xs font-medium text-txt-black-500">
                                        {{ __('Inactive') }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <button
                                    type="button"
                                    wire:click="openEditModal({{ $user->id }})"
                                    x-data=""
                                    x-on:click="$dispatch('open-modal', 'user-form')"
                                    class="text-primary-600 hover:text-primary-700 text-body-sm font-medium"
                                >
                                    {{ __('Edit') }}
                                </button>
                                <button
                                    type="button"
                                    wire:click="confirmDelete({{ $user->id }})"
                                    x-data=""
                                    x-on:click="$dispatch('open-modal', 'confirm-user-deletion')"
                                    class="ms-3 text-danger-600 hover:text-danger-700 text-body-sm font-medium"
                                >
                                    {{ __('Delete') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-txt-black-500 py-6">
                                {{ __('No users found.') }}
                            </td>
                        </tr>
                    @endforelse
                </x-table>

                <div class="mt-4">
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>

    {{-- Create / Edit modal --}}
    <x-modal name="user-form" focusable>
        <form wire:submit="save" class="p-6 space-y-4">
            <h2 class="font-heading font-semibold text-heading-2xs text-txt-black-900">
                {{ $editingUserId ? __('Edit User') : __('Add User') }}
            </h2>

            <x-input label="{{ __('Name') }}" wire:model="name" name="name" required error="{{ $errors->first('name') }}" />

            <x-input label="{{ __('Email') }}" type="email" wire:model="email" name="email" required error="{{ $errors->first('email') }}" />

            <x-input
                label="{{ __('Password') }}"
                type="password"
                wire:model="password"
                name="password"
                :help="$editingUserId ? __('Leave blank to keep the current password.') : null"
                :required="! $editingUserId"
                error="{{ $errors->first('password') }}"
            />

            <div>
                <x-input-label for="role" class="mb-1.5">{{ __('Role') }}</x-input-label>
                <select id="role" wire:model="role" class="block w-full rounded-md border-otl-gray-300 focus:border-primary-600 focus:ring-fr-primary text-body-sm">
                    <option value="user">{{ __('User') }}</option>
                    <option value="admin">{{ __('Admin') }}</option>
                </select>
                <x-input-error :messages="$errors->get('role')" class="mt-1" />
            </div>

            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model="is_active" class="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary">
                <span class="text-body-sm text-txt-black-900">{{ __('Active') }}</span>
            </label>

            <div class="flex justify-end gap-3 pt-2">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-button>
                <x-button variant="primary" type="submit">
                    {{ __('Save') }}
                </x-button>
            </div>
        </form>
    </x-modal>

    {{-- Delete confirmation modal --}}
    <x-modal name="confirm-user-deletion" focusable>
        <div class="p-6">
            <h2 class="font-heading font-semibold text-heading-2xs text-txt-black-900">
                {{ __('Delete this user?') }}
            </h2>
            <p class="mt-1 text-body-sm text-txt-black-500">
                {{ __('This action cannot be undone.') }}
            </p>

            <div class="mt-6 flex justify-end gap-3">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-button>
                <x-button variant="danger" type="button" wire:click="delete">
                    {{ __('Delete') }}
                </x-button>
            </div>
        </div>
    </x-modal>
</div>
