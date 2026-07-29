<x-admin-layout :breadcrumbs="[
    ['label' => __('Admin'), 'url' => route('admin.dashboard')],
    ['label' => __('Kitchen Sink'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Foundation'), 'url' => route('admin.kitchen-sink.foundation.colour')],
    ['label' => __('Style & Icon')],
]">
    @include('admin.kitchen-sink._tabs', ['group' => 'foundation'])

    @php
        $radii = [
            ['class' => 'rounded-xs', 'name' => 'rounded-xs', 'px' => '4px'],
            ['class' => 'rounded-sm', 'name' => 'rounded-sm', 'px' => '6px'],
            ['class' => 'rounded-md', 'name' => 'rounded-md', 'px' => '8px'],
            ['class' => 'rounded-lg', 'name' => 'rounded-lg', 'px' => '12px'],
            ['class' => 'rounded-xl', 'name' => 'rounded-xl', 'px' => '14px'],
            ['class' => 'rounded-full', 'name' => 'rounded-full', 'px' => '9999px'],
        ];

        $shadows = [
            ['class' => 'shadow-button', 'name' => 'shadow-button'],
            ['class' => 'shadow-card', 'name' => 'shadow-card'],
            ['class' => 'shadow-context-menu', 'name' => 'shadow-context-menu'],
        ];

        $iconSizes = [
            ['class' => 'h-4 w-4', 'label' => '16px'],
            ['class' => 'h-5 w-5', 'label' => '20px (base)'],
            ['class' => 'h-6 w-6', 'label' => '24px'],
            ['class' => 'h-8 w-8', 'label' => '32px'],
            ['class' => 'h-[42px] w-[42px]', 'label' => '42px'],
        ];
    @endphp

    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Radius Scale') }}</h3>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                @foreach ($radii as $item)
                    <div class="text-center">
                        <div class="{{ $item['class'] }} h-16 w-full border-2 border-primary-600 bg-bg-primary-50"></div>
                        <p class="mt-2 font-mono text-body-xs text-txt-black-500">{{ $item['name'] }}</p>
                        <p class="font-mono text-body-xs text-txt-black-500">{{ $item['px'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Shadow Scale') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('Only three named shadows exist — there is no sm–xl scale like default Tailwind.') }}</p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                @foreach ($shadows as $item)
                    <div class="rounded-lg bg-bg-white p-4 text-center {{ $item['class'] }}">
                        <p class="font-mono text-body-xs text-txt-black-500">{{ $item['name'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-6" x-data="{ playA: true, playB: true, playC: true }">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Motion') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('Named durations (200/400/600ms) and a few of the built-in keyframe animations. Click Replay to trigger each again.') }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="rounded-md border border-otl-gray-200 p-4">
                    <p class="mb-2 text-body-xs font-medium text-txt-black-500">duration-200 / 400 / 600</p>
                    <div class="flex items-center gap-2" x-data="{ hover: false }">
                        <div class="h-8 w-8 rounded-md bg-primary-600 transition-transform duration-200" :class="hover ? '-translate-y-1' : ''" x-on:mouseenter="hover = true" x-on:mouseleave="hover = false"></div>
                    </div>
                    <p class="mt-2 text-body-xs text-txt-black-500">{{ __('Hover the square (duration-200).') }}</p>
                </div>

                <div class="rounded-md border border-otl-gray-200 p-4 overflow-hidden">
                    <p class="mb-2 text-body-xs font-medium text-txt-black-500">animate-shimmer</p>
                    <div class="h-8 w-full rounded-md bg-gradient-to-r from-bg-washed via-primary-100 to-bg-washed bg-[length:200%_100%] animate-shimmer"></div>
                </div>

                <div class="rounded-md border border-otl-gray-200 p-4 space-y-2">
                    <p class="text-body-xs font-medium text-txt-black-500">animate-expire</p>
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-bg-washed">
                        <template x-if="playC">
                            <div class="h-full bg-primary-600 animate-expire"></div>
                        </template>
                    </div>
                    <button type="button" class="text-body-xs font-medium text-primary-600 hover:text-primary-700" x-on:click="playC = false; $nextTick(() => playC = true)">
                        {{ __('Replay') }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="rounded-md border border-otl-gray-200 p-4 space-y-2 overflow-hidden">
                    <p class="text-body-xs font-medium text-txt-black-500">animate-slide-up</p>
                    <div class="h-10 flex items-end overflow-hidden">
                        <template x-if="playA">
                            <div class="h-8 w-full rounded-md bg-primary-600 animate-slide-up"></div>
                        </template>
                    </div>
                    <button type="button" class="text-body-xs font-medium text-primary-600 hover:text-primary-700" x-on:click="playA = false; $nextTick(() => playA = true)">
                        {{ __('Replay') }}
                    </button>
                </div>

                <div class="rounded-md border border-otl-gray-200 p-4 space-y-2 overflow-hidden">
                    <p class="text-body-xs font-medium text-txt-black-500">animate-slide-down</p>
                    <div class="h-10 flex items-start overflow-hidden">
                        <template x-if="playB">
                            <div class="h-8 w-full rounded-md bg-primary-600 animate-slide-down"></div>
                        </template>
                    </div>
                    <button type="button" class="text-body-xs font-medium text-primary-600 hover:text-primary-700" x-on:click="playB = false; $nextTick(() => playB = true)">
                        {{ __('Replay') }}
                    </button>
                </div>
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Icon Sizes') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('Icons are drawn on a 20×20 base grid with a 1.5px stroke, then scaled to these recommended sizes.') }}</p>
            </div>

            <div class="flex flex-wrap items-end gap-8">
                @foreach ($iconSizes as $item)
                    <div class="text-center">
                        <svg class="{{ $item['class'] }} text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="mt-2 font-mono text-body-xs text-txt-black-500">{{ $item['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-admin-layout>
