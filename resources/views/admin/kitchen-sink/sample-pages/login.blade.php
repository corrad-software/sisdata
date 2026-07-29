<x-admin-layout :breadcrumbs="[
    ['label' => __('Admin'), 'url' => route('admin.dashboard')],
    ['label' => __('Kitchen Sink'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Sample Pages'), 'url' => route('admin.kitchen-sink.sample-pages.login')],
    ['label' => __('Login')],
]">
    @include('admin.kitchen-sink._tabs', ['group' => 'sample-pages'])

    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-4">
        <div>
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Login Page') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('A full-page reference template — the MYDS-styled version of a login screen, built entirely from existing kitchen sink components.') }}</p>
        </div>

        <x-browser-frame url="yourapp.test/login" class="max-w-2xl mx-auto">
            <div class="flex min-h-[640px] flex-col bg-bg-washed">
                <x-masthead />

                <div class="flex flex-1 flex-col items-center justify-center px-4 py-10">
                    <div class="mb-6 flex items-center gap-2">
                        <x-application-logo class="h-10 w-auto fill-current text-primary-600" />
                        <span class="text-body-md font-semibold text-txt-black-900">MYDS-Laravel</span>
                    </div>

                    <div class="w-full max-w-sm rounded-lg border border-otl-gray-200 bg-bg-white p-6 shadow-card">
                        <h2 class="mb-1 font-heading text-heading-xs font-semibold text-txt-black-900">{{ __('Log in to your account') }}</h2>
                        <p class="mb-6 text-body-sm text-txt-black-500">{{ __('Welcome back! Please enter your details.') }}</p>

                        <form class="space-y-4" onsubmit="return false">
                            <x-input label="{{ __('Email') }}" type="email" name="ks_login_email" placeholder="jane@example.com" />
                            <x-input label="{{ __('Password') }}" type="password" name="ks_login_password" placeholder="••••••••" />

                            <div class="flex items-center justify-between">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" class="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary">
                                    <span class="text-body-sm text-txt-black-900">{{ __('Remember me') }}</span>
                                </label>
                                <a href="#" class="text-body-sm font-medium text-primary-600 hover:text-primary-700">{{ __('Forgot password?') }}</a>
                            </div>

                            <x-button variant="primary" type="submit" class="w-full">{{ __('Log in') }}</x-button>
                        </form>

                        <p class="mt-6 text-center text-body-sm text-txt-black-500">
                            {{ __("Don't have an account?") }}
                            <a href="{{ route('admin.kitchen-sink.sample-pages.register') }}" wire:navigate class="font-medium text-primary-600 hover:text-primary-700">{{ __('Register') }}</a>
                        </p>
                    </div>
                </div>

                <x-footer />
            </div>
        </x-browser-frame>
    </div>
</x-admin-layout>
