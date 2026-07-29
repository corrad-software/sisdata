<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.theme-init')

        <title>{{ isset($breadcrumbs) && count($breadcrumbs) ? end($breadcrumbs)['label'] . ' - ' : '' }}{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        class="font-body antialiased"
        x-data="{ sidebarOpen: false, sidebarCollapsed: localStorage.getItem('sidebar-collapsed') === 'true' }"
        x-init="$watch('sidebarCollapsed', value => localStorage.setItem('sidebar-collapsed', value))"
    >
        <div class="min-h-screen flex flex-col bg-bg-washed">
            <x-masthead />

            <div class="flex flex-1 min-h-0">
                {{-- Mobile backdrop --}}
                <div
                    x-show="sidebarOpen"
                    x-on:click="sidebarOpen = false"
                    x-transition:enter="ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 z-30 bg-bg-black-900/50 lg:hidden"
                    style="display: none;"
                ></div>

                {{-- Sidebar --}}
                <aside
                    :class="[
                        sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                        sidebarCollapsed ? 'lg:w-20' : 'lg:w-64',
                    ]"
                    class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col bg-primary-700 transform transition-all duration-200 ease-in-out lg:relative lg:translate-x-0"
                >
                    <button
                        type="button"
                        x-on:click="sidebarCollapsed = !sidebarCollapsed"
                        class="hidden lg:flex absolute -right-3 top-14 h-6 w-6 items-center justify-center rounded-full border-2 border-primary-600 bg-bg-white text-primary-600 shadow-card hover:bg-primary-600 hover:text-white z-10"
                        :title="sidebarCollapsed ? '{{ __('Expand menu') }}' : '{{ __('Collapse menu') }}'"
                    >
                        <svg class="h-3.5 w-3.5 transition-transform" :class="sidebarCollapsed ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                    </button>

                    <div class="h-16 flex items-center justify-between px-6 border-b border-primary-600">
                        <a href="{{ route('admin.dashboard') }}" wire:navigate class="flex items-center gap-2 min-w-0">
                            <x-application-logo class="h-8 w-auto flex-shrink-0 fill-current text-white" />
                            <span :class="sidebarCollapsed ? 'lg:hidden' : ''" class="truncate text-body-sm font-semibold text-white">MYDS-Laravel</span>
                        </a>
                        <button type="button" class="lg:hidden text-primary-100 hover:text-white" x-on:click="sidebarOpen = false">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <nav class="flex-1 px-3 py-4 space-y-1">
                        <x-sidebar-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" :label="__('Dashboard')" wire:navigate>
                            <x-slot:icon>
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                </svg>
                            </x-slot:icon>
                            {{ __('Dashboard') }}
                        </x-sidebar-link>

                        <x-sidebar-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.index')" :label="__('Users')" wire:navigate>
                            <x-slot:icon>
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                </svg>
                            </x-slot:icon>
                            {{ __('Users') }}
                        </x-sidebar-link>

                        <div x-data="{ open: {{ request()->routeIs('admin.kitchen-sink.*') ? 'true' : 'false' }} }">
                            <button
                                type="button"
                                x-on:click="sidebarCollapsed ? (sidebarCollapsed = false, open = true) : (open = !open)"
                                class="flex w-full items-center gap-3 px-3 py-2 rounded-md text-body-sm leading-none font-medium {{ request()->routeIs('admin.kitchen-sink.*') ? 'bg-white text-primary-700 shadow-sm' : 'text-primary-100 hover:bg-primary-600 hover:text-white' }}"
                                :title="sidebarCollapsed ? '{{ __('Kitchen Sink') }}' : null"
                            >
                                <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                                </svg>
                                <span :class="sidebarCollapsed ? 'lg:hidden' : ''" class="flex-1 text-start truncate">{{ __('Kitchen Sink') }}</span>
                                <svg class="h-4 w-4 flex-shrink-0 transition-transform" :class="[open ? 'rotate-180' : '', sidebarCollapsed ? 'lg:hidden' : '']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>

                            <div x-show="open" x-transition :class="sidebarCollapsed ? 'lg:hidden' : ''" class="ms-4 mt-1 space-y-1 border-l border-primary-600 ps-3">
                                <x-sidebar-link :href="route('admin.kitchen-sink.foundation.colour')" :active="request()->routeIs('admin.kitchen-sink.foundation.*')" wire:navigate>
                                    {{ __('Foundation') }}
                                </x-sidebar-link>
                                <x-sidebar-link :href="route('admin.kitchen-sink.components.buttons')" :active="request()->routeIs('admin.kitchen-sink.components.*')" wire:navigate>
                                    {{ __('Components') }}
                                </x-sidebar-link>
                                <x-sidebar-link :href="route('admin.kitchen-sink.data-visual.dashboard')" :active="request()->routeIs('admin.kitchen-sink.data-visual.*')" wire:navigate>
                                    {{ __('Data Visual') }}
                                </x-sidebar-link>
                                <x-sidebar-link :href="route('admin.kitchen-sink.sample-pages.login')" :active="request()->routeIs('admin.kitchen-sink.sample-pages.*')" wire:navigate>
                                    {{ __('Sample Pages') }}
                                </x-sidebar-link>
                            </div>
                        </div>
                    </nav>

                    <div class="px-3 py-4 border-t border-primary-600">
                        <x-sidebar-link :href="route('dashboard')" :label="__('Back to site')" wire:navigate>
                            <x-slot:icon>
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                                </svg>
                            </x-slot:icon>
                            {{ __('Back to site') }}
                        </x-sidebar-link>
                    </div>
                </aside>

                {{-- Main column --}}
                <div class="flex-1 flex flex-col min-w-0">
                    <header class="h-16 flex items-center justify-between gap-4 px-4 sm:px-6 bg-bg-white border-b border-otl-divider">
                        <button type="button" class="lg:hidden text-txt-black-500" x-on:click="sidebarOpen = true">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>

                        <div class="flex-1"></div>

                        <livewire:layout.admin-navigation />
                    </header>

                    <!-- Page Heading -->
                    @if (isset($breadcrumbs) && count($breadcrumbs))
                        <div class="bg-bg-white border-b border-otl-divider px-4 sm:px-6 py-4 space-y-3">
                            <x-breadcrumb :items="$breadcrumbs" />
                            <h2 class="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">
                                {{ end($breadcrumbs)['label'] }}
                            </h2>
                        </div>
                    @endif

                    <main class="flex-1">
                        {{ $slot }}
                    </main>

                    <x-footer />
                </div>
            </div>
        </div>
    </body>
</html>
