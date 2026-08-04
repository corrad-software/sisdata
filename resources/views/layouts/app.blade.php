<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.theme-init')

        <title>{{ isset($breadcrumbs) && count($breadcrumbs) ? end($breadcrumbs)['label'] . ' - ' : '' }}{{ $siteTitle ?? config('app.name', 'Laravel') }}</title>

        @if (!empty($siteFaviconUrl))
            <link rel="icon" href="{{ $siteFaviconUrl }}">
        @endif

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @if (!empty($themeColorCss))
            <style>{!! $themeColorCss !!}</style>
        @endif
    </head>
    <body class="font-body antialiased">
        <div class="min-h-screen flex flex-col bg-bg-washed">
            <x-masthead />

            <livewire:layout.navigation />

            <!-- Page Heading -->
            @if (isset($breadcrumbs) && count($breadcrumbs))
                <header class="bg-bg-white border-b border-otl-divider">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-3">
                        <x-breadcrumb :items="$breadcrumbs" />
                        <h2 class="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">
                            {{ end($breadcrumbs)['label'] }}
                        </h2>
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main class="flex-1">
                {{ $slot }}
            </main>

            <x-footer />
        </div>
    </body>
</html>
