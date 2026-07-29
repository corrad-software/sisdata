<x-admin-layout :breadcrumbs="[
    ['label' => __('Admin'), 'url' => route('admin.dashboard')],
    ['label' => __('Kitchen Sink'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Components'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Navigation')],
]">
    @include('admin.kitchen-sink._tabs', ['group' => 'components'])

    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Navbar Utilities') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('The controls shown in both navbars.') }}</p>

            <div class="flex items-center gap-1">
                <x-quick-search />
                <x-font-size-toggle />
                <x-theme-toggle />
                <x-language-switcher />
                <livewire:layout.notification-bell />
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Sidebar Link') }}</h3>

            <div class="max-w-xs rounded-md bg-primary-700 p-3 space-y-1">
                <x-sidebar-link href="#" :active="true">{{ __('Active item') }}</x-sidebar-link>
                <x-sidebar-link href="#">{{ __('Inactive item') }}</x-sidebar-link>

                <div x-data="{ open: true }">
                    <button
                        type="button"
                        x-on:click="open = !open"
                        class="flex w-full items-center justify-between gap-3 px-3 py-2 rounded-md text-body-sm font-medium text-primary-100 hover:bg-primary-600 hover:text-white"
                    >
                        <span>{{ __('Parent with children') }}</span>
                        <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <div x-show="open" x-transition class="ms-4 mt-1 space-y-1 border-l border-primary-600 ps-3">
                        <x-sidebar-link href="#">{{ __('Child item one') }}</x-sidebar-link>
                        <x-sidebar-link href="#" :active="true">{{ __('Child item two') }}</x-sidebar-link>
                        <x-sidebar-link href="#">{{ __('Child item three') }}</x-sidebar-link>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Tabs') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('Client-side tabs (no page navigation) — unlike this page\'s own tab bar above, which routes between subpages. Tabs can be nested, since each x-tabs instance tracks its own active tab independently.') }}</p>

            <x-tabs :tabs="[
                'profile' => __('Profile'),
                'security' => __('Security'),
                'notifications' => __('Notifications'),
            ]">
                <div x-show="tab === 'profile'">
                    <p class="text-body-sm text-txt-black-700">{{ __('Profile panel content goes here.') }}</p>
                </div>

                <div x-show="tab === 'security'" class="space-y-4">
                    <p class="text-body-sm text-txt-black-700">{{ __('Security panel content, with a nested set of tabs below:') }}</p>

                    <x-tabs :tabs="['password' => __('Password'), 'sessions' => __('Sessions')]" class="max-w-sm">
                        <div x-show="tab === 'password'">
                            <p class="text-body-sm text-txt-black-500">{{ __('Password sub-panel content.') }}</p>
                        </div>
                        <div x-show="tab === 'sessions'">
                            <p class="text-body-sm text-txt-black-500">{{ __('Sessions sub-panel content.') }}</p>
                        </div>
                    </x-tabs>
                </div>

                <div x-show="tab === 'notifications'">
                    <p class="text-body-sm text-txt-black-700">{{ __('Notifications panel content goes here.') }}</p>
                </div>
            </x-tabs>
        </section>
    </div>
</x-admin-layout>
