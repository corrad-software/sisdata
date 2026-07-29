<x-app-layout :breadcrumbs="[['label' => __('Dashboard')]]">
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-bg-white overflow-hidden shadow-card sm:rounded-lg border border-otl-gray-200">
                <div class="p-6 text-txt-black-900">
                    {{ __("You're logged in!") }}
                </div>
            </div>

            <div class="bg-bg-white overflow-hidden shadow-card sm:rounded-lg border border-otl-gray-200">
                <div class="p-6">
                    <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900 mb-2">
                        {{ __('Admin Area') }}
                    </h3>
                    <p class="text-body-sm text-txt-black-500 mb-4">
                        {{ __('Sidebar-based admin section, including the sample User Management CRUD module.') }}
                    </p>
                    <x-button href="{{ route('admin.dashboard') }}" wire:navigate>
                        {{ __('Go to Admin') }}
                    </x-button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
