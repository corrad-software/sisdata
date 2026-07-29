@props([
    'label' => '',
    'value' => '',
    'change' => null,
    'trend' => 'neutral',
])

@php
$trendStyles = [
    'up' => 'text-txt-success bg-bg-success-50',
    'down' => 'text-txt-danger bg-bg-danger-50',
    'neutral' => 'text-txt-black-500 bg-bg-washed',
][$trend];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border border-otl-gray-200 bg-bg-white p-4 shadow-card']) }}>
    <p class="text-body-xs font-medium text-txt-black-500">{{ $label }}</p>
    <div class="mt-1.5 flex items-end justify-between gap-2">
        <p class="font-heading text-heading-xs font-semibold text-txt-black-900">{{ $value }}</p>

        @if ($change)
            <span class="inline-flex flex-shrink-0 items-center gap-0.5 rounded-full {{ $trendStyles }} px-2 py-0.5 text-body-xs font-medium">
                @if ($trend === 'up')
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                    </svg>
                @elseif ($trend === 'down')
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                @endif
                {{ $change }}
            </span>
        @endif
    </div>

    @if ($slot->isNotEmpty())
        <div class="mt-3">
            {{ $slot }}
        </div>
    @endif
</div>
