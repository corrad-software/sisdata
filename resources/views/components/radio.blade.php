@props([
    'label' => null,
    'name' => null,
    'value' => null,
])

@php
$slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) ($value ?? $label)));
$id = $attributes->get('id') ?? trim(($name ? "{$name}-" : '').$slug, '-');
@endphp

<label for="{{ $id }}" class="flex items-center gap-2 cursor-pointer">
    <input
        type="radio"
        id="{{ $id }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($value !== null) value="{{ $value }}" @endif
        {{ $attributes->except(['label', 'name', 'value'])->merge(['class' => 'border-otl-gray-300 text-primary-600 focus:ring-fr-primary']) }}
    >
    <span class="text-body-sm text-txt-black-900">{!! $label ?? $slot !!}</span>
</label>
