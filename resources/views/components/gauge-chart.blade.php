@props([
    'value' => 0,
    'min' => 0,
    'max' => 100,
    'size' => 200,
    'strokeWidth' => 20,
    'label' => null,
    'color' => 'text-primary-600',
])

@php
$radius = ($size - $strokeWidth) / 2;
$circumference = M_PI * $radius;
$fraction = max(0, min(1, ($value - $min) / (($max - $min) ?: 1)));
$dash = $fraction * $circumference;
$viewHeight = $size / 2 + $strokeWidth / 2;
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex flex-col items-center']) }}>
    <svg width="{{ $size }}" height="{{ $viewHeight }}" viewBox="0 0 {{ $size }} {{ $viewHeight }}">
        <path
            d="M {{ $strokeWidth / 2 }} {{ $size / 2 }} A {{ $radius }} {{ $radius }} 0 0 1 {{ $size - $strokeWidth / 2 }} {{ $size / 2 }}"
            fill="none"
            class="stroke-otl-gray-200"
            stroke-width="{{ $strokeWidth }}"
            stroke-linecap="round"
        />
        <path
            d="M {{ $strokeWidth / 2 }} {{ $size / 2 }} A {{ $radius }} {{ $radius }} 0 0 1 {{ $size - $strokeWidth / 2 }} {{ $size / 2 }}"
            fill="none"
            stroke="currentColor"
            class="{{ $color }}"
            stroke-width="{{ $strokeWidth }}"
            stroke-dasharray="{{ round($dash, 2) }} {{ round($circumference - $dash, 2) }}"
            stroke-linecap="round"
        />
    </svg>
    <p class="-mt-2 font-heading text-heading-xs font-semibold text-txt-black-900">{{ $value }}</p>
    @if ($label)
        <p class="text-body-sm text-txt-black-500">{{ $label }}</p>
    @endif
</div>
