<x-admin-layout :breadcrumbs="[
    ['label' => __('Admin'), 'url' => route('admin.dashboard')],
    ['label' => __('Kitchen Sink'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Sample Pages'), 'url' => route('admin.kitchen-sink.sample-pages.login')],
    ['label' => __('Empty State')],
]">
    @include('admin.kitchen-sink._tabs', ['group' => 'sample-pages'])

    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-4">
        <div>
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Empty State Page') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('Shown within a realistic page shell — the pattern to reuse whenever a list or table has zero results.') }}</p>
        </div>

        <x-browser-frame url="yourapp.test/admin/users" class="max-w-2xl mx-auto">
            <div class="flex min-h-[640px] flex-col bg-bg-washed">
                <x-masthead />

                <div class="border-b border-otl-divider bg-bg-white px-4 py-4 sm:px-6">
                    <h1 class="font-heading text-heading-xs font-semibold text-txt-black-900">{{ __('Users') }}</h1>
                </div>

                <div class="flex flex-1 items-center justify-center px-4 py-10 sm:px-6">
                    <div class="mx-auto max-w-sm text-center">
                        <svg class="mx-auto h-16 w-16 text-primary-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.24A2.25 2.25 0 0017.15 3.75H6.85a2.25 2.25 0 00-2.09 1.49L2.35 13.177a2.25 2.25 0 00-.1.661z" />
                        </svg>

                        <h2 class="mt-4 font-heading text-heading-2xs font-semibold text-txt-black-900">{{ __('No users yet') }}</h2>
                        <p class="mt-2 text-body-sm text-txt-black-500">{{ __('Get started by adding your first user.') }}</p>

                        <x-button variant="primary" href="#" class="mt-6">
                            + {{ __('Add User') }}
                        </x-button>
                    </div>
                </div>

                <x-footer />
            </div>
        </x-browser-frame>
    </div>
</x-admin-layout>
