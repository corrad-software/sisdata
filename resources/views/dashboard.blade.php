<x-app-layout>
    <x-slot name="header">
        <h2 class="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-card sm:rounded-lg border border-otl-gray-200">
                <div class="p-6 text-txt-black-900">
                    {{ __("You're logged in!") }}
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-card sm:rounded-lg border border-otl-gray-200">
                <div class="p-6">
                    <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900 mb-2">
                        {{ __('User Management') }}
                    </h3>
                    <p class="text-body-sm text-txt-black-500 mb-4">
                        {{ __('Sample CRUD module demonstrating the Livewire + Blade component pattern used in this starter.') }}
                    </p>
                    <x-button href="{{ route('users.index') }}" wire:navigate>
                        {{ __('Manage Users') }}
                    </x-button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
