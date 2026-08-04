@props(['label' => null, 'help' => null])

@php
$id = $attributes->get('id') ?? $attributes->wire('model')->value();
@endphp

<div>
    @if ($label)
        <x-input-label :for="$id" class="mb-1.5">{{ $label }}</x-input-label>
    @endif

    <div class="flex items-center gap-2">
        <input
            type="color"
            id="{{ $id }}"
            {{ $attributes->merge(['class' => 'h-10 w-14 flex-shrink-0 cursor-pointer rounded-md border border-otl-gray-300 bg-bg-white p-1']) }}
        >
        <input
            type="text"
            {{ $attributes->merge(['class' => 'block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm font-mono uppercase']) }}
            placeholder="#2563EB"
            maxlength="7"
        >
    </div>

    @if ($help)
        <p class="mt-1 text-body-xs text-txt-black-500">{{ $help }}</p>
    @endif
</div>
