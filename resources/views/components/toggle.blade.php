@props([
    'label' => null,
    'name' => null,
])

@php
$id = $attributes->get('id') ?? $name;
@endphp

<label for="{{ $id }}" class="inline-flex items-center gap-3 cursor-pointer">
    <span class="relative inline-flex h-6 w-11 flex-shrink-0 items-center">
        <input
            type="checkbox"
            id="{{ $id }}"
            @if ($name) name="{{ $name }}" @endif
            {{ $attributes->except(['label', 'name'])->merge(['class' => 'peer absolute inset-0 h-full w-full cursor-pointer opacity-0']) }}
        >
        <span class="pointer-events-none block h-6 w-11 rounded-full bg-bg-black-200 transition-colors peer-checked:bg-primary-600 peer-focus-visible:ring-2 peer-focus-visible:ring-fr-primary peer-focus-visible:ring-offset-2"></span>
        <span class="pointer-events-none absolute left-1 top-1 h-4 w-4 rounded-full bg-white transition-transform peer-checked:translate-x-5"></span>
    </span>

    @if ($label)
        <span class="text-body-sm text-txt-black-900">{!! $label !!}</span>
    @endif
</label>
