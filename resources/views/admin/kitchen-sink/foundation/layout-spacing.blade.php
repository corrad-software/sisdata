<x-admin-layout :breadcrumbs="[
    ['label' => __('Admin'), 'url' => route('admin.dashboard')],
    ['label' => __('Kitchen Sink'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Foundation'), 'url' => route('admin.kitchen-sink.foundation.colour')],
    ['label' => __('Layout & Spacing')],
]">
    @include('admin.kitchen-sink._tabs', ['group' => 'foundation'])

    @php
        $spacingScale = [
            ['token' => '1', 'w' => 'w-1', 'px' => '4px'],
            ['token' => '2', 'w' => 'w-2', 'px' => '8px'],
            ['token' => '2.5', 'w' => 'w-2.5', 'px' => '10px'],
            ['token' => '3', 'w' => 'w-3', 'px' => '12px'],
            ['token' => '4', 'w' => 'w-4', 'px' => '16px'],
            ['token' => '4.5', 'w' => 'w-4.5', 'px' => '18px'],
            ['token' => '5', 'w' => 'w-5', 'px' => '20px'],
            ['token' => '6', 'w' => 'w-6', 'px' => '24px'],
            ['token' => '8', 'w' => 'w-8', 'px' => '32px'],
            ['token' => '10', 'w' => 'w-10', 'px' => '40px'],
            ['token' => '10.5', 'w' => 'w-10.5', 'px' => '42px'],
            ['token' => '12', 'w' => 'w-12', 'px' => '48px'],
            ['token' => '16', 'w' => 'w-16', 'px' => '64px'],
            ['token' => '20', 'w' => 'w-20', 'px' => '80px'],
            ['token' => '24', 'w' => 'w-24', 'px' => '96px'],
        ];
    @endphp

    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('12-8-4 Grid System') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('MYDS uses a responsive column grid: 12 columns on desktop, 8 on tablet, 4 on mobile. Resize your browser to see it change live.') }}</p>
            </div>

            <div class="rounded-md border border-otl-gray-200 bg-bg-washed p-2">
                <p class="md:hidden text-body-sm font-medium text-primary-600">{{ __('Mobile — 4 columns, 18px gap, ≤767px') }}</p>
                <p class="hidden md:inline-block lg:hidden text-body-sm font-medium text-primary-600">{{ __('Tablet — 8 columns, 24px gap, 768–1023px') }}</p>
                <p class="hidden lg:inline-block text-body-sm font-medium text-primary-600">{{ __('Desktop — 12 columns, 24px gap, max-width 1280px, ≥1024px') }}</p>
            </div>

            <div class="max-w-[1280px] mx-auto">
                <div class="grid grid-cols-4 md:grid-cols-8 lg:grid-cols-12 gap-[18px] md:gap-6">
                    @for ($i = 1; $i <= 12; $i++)
                        <div class="flex h-12 items-center justify-center rounded-md bg-primary-100 text-body-xs font-medium text-primary-700">
                            {{ $i }}
                        </div>
                    @endfor
                </div>
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Spacing Scale') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('Base 4px scale, with three MYDS-specific additions (2.5, 4.5, 10.5) shown alongside the defaults.') }}</p>
            </div>

            <div class="space-y-2">
                @foreach ($spacingScale as $item)
                    <div class="flex items-center gap-4">
                        <p class="w-16 flex-shrink-0 font-mono text-body-xs text-txt-black-500">{{ $item['token'] }}</p>
                        <div class="{{ $item['w'] }} h-4 flex-shrink-0 rounded bg-primary-600"></div>
                        <p class="font-mono text-body-xs text-txt-black-500">{{ $item['px'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-admin-layout>
