<x-admin-layout :breadcrumbs="[
    ['label' => __('Admin'), 'url' => route('admin.dashboard')],
    ['label' => __('Kitchen Sink'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Foundation'), 'url' => route('admin.kitchen-sink.foundation.colour')],
    ['label' => __('Colour')],
]">
    @include('admin.kitchen-sink._tabs', ['group' => 'foundation'])

    @php
        $ramps = [
            'Gray' => ['bg-gray-50', 'bg-gray-100', 'bg-gray-200', 'bg-gray-300', 'bg-gray-400', 'bg-gray-500', 'bg-gray-600', 'bg-gray-700', 'bg-gray-800', 'bg-gray-850', 'bg-gray-900', 'bg-gray-930', 'bg-gray-950'],
            'Primary' => ['bg-primary-50', 'bg-primary-100', 'bg-primary-200', 'bg-primary-300', 'bg-primary-400', 'bg-primary-500', 'bg-primary-600', 'bg-primary-700', 'bg-primary-800', 'bg-primary-900', 'bg-primary-950'],
            'Danger' => ['bg-danger-50', 'bg-danger-100', 'bg-danger-200', 'bg-danger-300', 'bg-danger-400', 'bg-danger-500', 'bg-danger-600', 'bg-danger-700', 'bg-danger-800', 'bg-danger-900', 'bg-danger-950'],
            'Success' => ['bg-success-50', 'bg-success-100', 'bg-success-200', 'bg-success-300', 'bg-success-400', 'bg-success-500', 'bg-success-600', 'bg-success-700', 'bg-success-800', 'bg-success-900', 'bg-success-950'],
            'Warning' => ['bg-warning-50', 'bg-warning-100', 'bg-warning-200', 'bg-warning-300', 'bg-warning-400', 'bg-warning-500', 'bg-warning-600', 'bg-warning-700', 'bg-warning-800', 'bg-warning-900', 'bg-warning-950'],
        ];

        $bgNeutral = ['bg-bg-white', 'bg-bg-white-hover', 'bg-bg-washed', 'bg-bg-washed-active', 'bg-bg-contrast', 'bg-bg-dialog', 'bg-bg-black-50', 'bg-bg-black-100', 'bg-bg-black-200', 'bg-bg-black-300', 'bg-bg-black-400', 'bg-bg-black-500', 'bg-bg-black-700', 'bg-bg-black-800', 'bg-bg-black-900'];

        $bgColored = [
            'Primary' => ['bg-bg-primary-50', 'bg-bg-primary-100', 'bg-bg-primary-200', 'bg-bg-primary-300', 'bg-bg-primary-400', 'bg-bg-primary-500', 'bg-bg-primary-600', 'bg-bg-primary-700', 'bg-bg-primary-800', 'bg-bg-primary-900', 'bg-bg-primary-950'],
            'Danger' => ['bg-bg-danger-50', 'bg-bg-danger-100', 'bg-bg-danger-200', 'bg-bg-danger-300', 'bg-bg-danger-400', 'bg-bg-danger-500', 'bg-bg-danger-600', 'bg-bg-danger-700', 'bg-bg-danger-800', 'bg-bg-danger-900', 'bg-bg-danger-950'],
            'Success' => ['bg-bg-success-50', 'bg-bg-success-100', 'bg-bg-success-200', 'bg-bg-success-300', 'bg-bg-success-400', 'bg-bg-success-500', 'bg-bg-success-600', 'bg-bg-success-700', 'bg-bg-success-800', 'bg-bg-success-900', 'bg-bg-success-950'],
            'Warning' => ['bg-bg-warning-50', 'bg-bg-warning-100', 'bg-bg-warning-200', 'bg-bg-warning-300', 'bg-bg-warning-400', 'bg-bg-warning-500', 'bg-bg-warning-600', 'bg-bg-warning-700', 'bg-bg-warning-800', 'bg-bg-warning-900', 'bg-bg-warning-950'],
        ];

        $textTokens = ['text-txt-black-900', 'text-txt-black-700', 'text-txt-black-500', 'text-txt-primary', 'text-txt-danger', 'text-txt-success', 'text-txt-warning'];

        $outlineTokens = ['border-otl-divider', 'border-otl-gray-200', 'border-otl-gray-300', 'border-otl-primary-200', 'border-otl-primary-300', 'border-otl-danger-200', 'border-otl-danger-300', 'border-otl-success-200', 'border-otl-success-300', 'border-otl-warning-200', 'border-otl-warning-300'];
    @endphp

    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-6">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Colour Ramps') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('The full tonal ramp for each palette. These are the raw primitive colours — components should use the semantic tokens below instead of these directly.') }}</p>
            </div>

            @foreach ($ramps as $name => $classes)
                <div>
                    <p class="mb-2 text-body-xs font-medium uppercase tracking-wide text-txt-black-500">{{ $name }}</p>
                    <div class="grid grid-cols-4 sm:grid-cols-7 gap-2">
                        @foreach ($classes as $class)
                            <div>
                                <div class="{{ $class }} h-12 w-full rounded-md border border-otl-gray-200"></div>
                                <p class="mt-1 text-center text-[10px] text-txt-black-500">{{ str_replace('bg-' . strtolower($name) . '-', '', $class) }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-6">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Semantic Background Tokens') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('Dark-mode aware aliases (e.g. bg-bg-white, bg-bg-washed) — these are the ones components should actually use, since they resolve to different values in light vs dark theme.') }}</p>
            </div>

            <div>
                <p class="mb-2 text-body-xs font-medium uppercase tracking-wide text-txt-black-500">{{ __('Neutral') }}</p>
                <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                    @foreach ($bgNeutral as $class)
                        <div>
                            <div class="{{ $class }} h-12 w-full rounded-md border border-otl-gray-200"></div>
                            <p class="mt-1 text-center text-[10px] text-txt-black-500 break-all">{{ $class }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            @foreach ($bgColored as $name => $classes)
                <div>
                    <p class="mb-2 text-body-xs font-medium uppercase tracking-wide text-txt-black-500">{{ $name }}</p>
                    <div class="grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-11 gap-2">
                        @foreach ($classes as $class)
                            <div>
                                <div class="{{ $class }} h-12 w-full rounded-md border border-otl-gray-200"></div>
                                <p class="mt-1 text-center text-[10px] text-txt-black-500">{{ str_replace('bg-bg-' . strtolower($name) . '-', '', $class) }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-6">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Semantic Text Tokens') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('Text colour aliases used throughout this app — headings use txt-black-900, secondary text uses txt-black-500.') }}</p>
            </div>

            <div class="space-y-2">
                @foreach ($textTokens as $class)
                    <p class="{{ $class }} text-body-sm font-medium">{{ $class }} — {{ __('The quick brown fox jumps over the lazy dog.') }}</p>
                @endforeach
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-6">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Semantic Outline & Focus Ring Tokens') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('Border colours for dividers, cards, and form fields, plus the focus ring tokens used on every interactive element.') }}</p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach ($outlineTokens as $class)
                    <div class="{{ $class }} rounded-md border-2 bg-bg-white p-3 text-center">
                        <p class="text-[10px] text-txt-black-500 break-all">{{ $class }}</p>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap gap-4">
                <input type="text" readonly value="fr-primary" class="rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 text-body-sm ring-2 ring-fr-primary ring-offset-1 focus:outline-none w-40">
                <input type="text" readonly value="fr-danger" class="rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 text-body-sm ring-2 ring-fr-danger ring-offset-1 focus:outline-none w-40">
            </div>
        </section>
    </div>
</x-admin-layout>
