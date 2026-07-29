@props([
    'variant' => 'neutral',
    'shape' => 'pill',
])

@php
$variants = [
    'success' => 'bg-bg-success-50 text-txt-success',
    'danger' => 'bg-bg-danger-50 text-txt-danger',
    'warning' => 'bg-bg-warning-50 text-txt-warning',
    'info' => 'bg-bg-primary-50 text-txt-primary',
    'neutral' => 'bg-bg-washed text-txt-black-500',
][$variant];

$shapeClass = $shape === 'tag' ? 'rounded-md' : 'rounded-full';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center {$shapeClass} px-2.5 py-0.5 text-body-xs font-medium {$variants}"]) }}>
    {{ $slot }}
</span>
