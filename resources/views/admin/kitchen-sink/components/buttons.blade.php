<x-admin-layout :breadcrumbs="[
    ['label' => __('Admin'), 'url' => route('admin.dashboard')],
    ['label' => __('Kitchen Sink'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Components'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Buttons')],
]">
    @include('admin.kitchen-sink._tabs', ['group' => 'components'])

    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Button') }}</h3>

            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-3">
                    <x-button variant="primary">Primary</x-button>
                    <x-button variant="secondary">Secondary</x-button>
                    <x-button variant="danger">Danger</x-button>
                    <x-button variant="outline">Outline</x-button>
                    <x-button variant="primary" disabled>Disabled</x-button>
                    <x-button variant="primary" href="#">As link</x-button>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <x-button variant="primary" size="sm">Small</x-button>
                    <x-button variant="primary" size="md">Medium</x-button>
                    <x-button variant="primary" size="lg">Large</x-button>
                </div>
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Backlink') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('A lightweight link back to the previous page or listing — used above a page title instead of a full breadcrumb trail.') }}</p>

            <a href="#" class="inline-flex items-center gap-1.5 text-body-sm font-medium text-primary-600 hover:text-primary-700 hover:underline">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                {{ __('Back to Users') }}
            </a>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Skip Link') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('An accessibility link, hidden until keyboard-focused, that jumps straight to the main content — try pressing Tab from inside this card.') }}</p>

            <div tabindex="-1" class="relative rounded-md border border-dashed border-otl-gray-300 p-4">
                <a
                    href="#ks-skip-target"
                    class="absolute left-4 top-4 -translate-y-16 rounded-md bg-primary-600 px-4 py-2 text-body-sm font-medium text-white transition-transform focus:translate-y-0 focus:outline-none focus:ring-2 focus:ring-fr-primary"
                >
                    {{ __('Skip to main content') }}
                </a>
                <p class="text-body-sm text-txt-black-500">{{ __('Tab to reveal the skip link above.') }}</p>
                <p id="ks-skip-target" tabindex="-1" class="mt-3 text-body-sm font-medium text-txt-black-900">{{ __('Main content target') }}</p>
            </div>
        </section>
    </div>
</x-admin-layout>
