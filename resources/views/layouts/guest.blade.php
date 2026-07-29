<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.theme-init')

        @php
            $guestTitles = [
                'login' => __('Log in'),
                'register' => __('Register'),
                'password.request' => __('Forgot Password'),
                'password.reset' => __('Reset Password'),
                'password.confirm' => __('Confirm Password'),
                'verification.notice' => __('Verify Email'),
            ];
            $pageTitle = $guestTitles[request()->route()?->getName()] ?? null;
        @endphp

        <title>{{ $pageTitle ? "$pageTitle - " : '' }}{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-body text-txt-black-900 antialiased">
        <div class="min-h-screen flex flex-col bg-bg-washed">
            <x-masthead />

            <div class="flex-1 flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
                <div>
                    <a href="/" wire:navigate>
                        <x-application-logo class="w-16 h-16 fill-current text-primary-600" />
                    </a>
                </div>

                <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-bg-white shadow-card overflow-hidden sm:rounded-lg border border-otl-gray-200">
                    {{ $slot }}
                </div>
            </div>

            <x-footer />
        </div>
    </body>
</html>
