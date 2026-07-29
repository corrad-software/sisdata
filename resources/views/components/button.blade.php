@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
])

@php
$tag = $href ? 'a' : 'button';
@endphp

@php
$variants = [
    'primary' => 'bg-primary-600 text-white border border-primary-600 shadow-button hover:bg-primary-700 hover:border-primary-700 focus-visible:ring-fr-primary disabled:bg-bg-primary-disabled disabled:border-bg-primary-disabled',
    'secondary' => 'bg-bg-white text-txt-black-900 border border-otl-gray-300 shadow-button hover:bg-bg-washed focus-visible:ring-fr-primary disabled:opacity-50',
    'danger' => 'bg-danger-600 text-white border border-danger-600 shadow-button hover:bg-danger-700 hover:border-danger-700 focus-visible:ring-fr-danger disabled:opacity-50',
    'outline' => 'bg-transparent text-primary-600 border border-primary-600 hover:bg-primary-50 focus-visible:ring-fr-primary disabled:opacity-50',
];

$sizes = [
    'sm' => 'px-3 py-1.5 text-body-xs gap-1.5',
    'md' => 'px-4 py-2 text-body-sm gap-2',
    'lg' => 'px-5 py-2.5 text-body-md gap-2',
];
@endphp

<{{ $tag }}
    @if (! $href) type="{{ $type }}" @else href="{{ $href }}" @endif
    {{ $attributes->except('href')->merge(['class' => "inline-flex items-center justify-center font-medium rounded-md transition ease-in-out duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed {$variants[$variant]} {$sizes[$size]}"]) }}
>
    {{ $slot }}
</{{ $tag }}>
