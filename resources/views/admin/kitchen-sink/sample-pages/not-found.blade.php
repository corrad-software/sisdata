<x-admin-layout :breadcrumbs="[
    ['label' => __('Admin'), 'url' => route('admin.dashboard')],
    ['label' => __('Kitchen Sink'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Sample Pages'), 'url' => route('admin.kitchen-sink.sample-pages.login')],
    ['label' => __('404 Page')],
]">
    @include('admin.kitchen-sink._tabs', ['group' => 'sample-pages'])

    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-4">
        <div>
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('404 / Error Page') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('A full-page not-found state — swap the icon and copy to reuse this for other error pages (403, 500, etc).') }}</p>
        </div>

        <x-browser-frame url="yourapp.test/404" class="max-w-2xl mx-auto">
            <div class="flex min-h-[640px] flex-col bg-bg-washed">
                <x-masthead />

                <div class="flex flex-1 flex-col items-center justify-center px-4 py-10 text-center">
                    <svg class="h-20 w-20 text-primary-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 16.318A4.486 4.486 0 0012.016 15a4.486 4.486 0 00-3.198 1.318M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75zm-.375 0h.008v.015h-.008V9.75zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75zm-.375 0h.008v.015h-.008V9.75z" />
                    </svg>

                    <p class="mt-4 font-heading text-heading-lg font-semibold text-primary-600">404</p>
                    <h2 class="mt-2 font-heading text-heading-xs font-semibold text-txt-black-900">{{ __('Page not found') }}</h2>
                    <p class="mt-2 max-w-sm text-body-sm text-txt-black-500">
                        {{ __("Sorry, the page you're looking for doesn't exist or has been moved.") }}
                    </p>

                    <x-button variant="primary" href="#" class="mt-6">{{ __('Back to Home') }}</x-button>
                </div>

                <x-footer />
            </div>
        </x-browser-frame>
    </div>
</x-admin-layout>
