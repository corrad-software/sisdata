<x-admin-layout :breadcrumbs="[['label' => __('Admin')]]">
    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="bg-bg-white overflow-hidden shadow-card sm:rounded-lg border border-otl-gray-200">
            <div class="p-6">
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900 mb-2">
                    {{ __('User Management') }}
                </h3>
                <p class="text-body-sm text-txt-black-500 mb-4">
                    {{ __('Search, create, edit, and remove users from the sidebar admin area.') }}
                </p>
                <x-button href="{{ route('admin.users.index') }}" wire:navigate>
                    {{ __('Manage Users') }}
                </x-button>
            </div>
        </div>
    </div>
</x-admin-layout>
