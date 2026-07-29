@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'error' => null,
    'help' => null,
    'required' => false,
])

@php
$id = $attributes->get('id') ?? $name;
@endphp

<div>
    @if ($label)
        <x-input-label :for="$id" class="mb-1.5">
            {!! $label !!} @if ($required) <span class="text-danger-600">*</span> @endif
        </x-input-label>
    @endif

    <input
        type="{{ $type }}"
        id="{{ $id }}"
        @if ($name) name="{{ $name }}" @endif
        {{ $attributes->except(['label', 'name', 'error', 'help', 'required'])->merge([
            'class' => 'block w-full rounded-md border bg-bg-white text-txt-black-900 ' . ($error ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500' : 'border-otl-gray-300 focus:border-primary-600 focus:ring-fr-primary') . ' text-body-sm shadow-sm focus:ring-1 disabled:bg-bg-washed disabled:opacity-60',
        ]) }}
    >

    @if ($help && ! $error)
        <p class="mt-1 text-body-xs text-txt-black-500">{{ $help }}</p>
    @endif

    @if ($error)
        <p class="mt-1 text-body-xs text-danger-600">{{ $error }}</p>
    @endif
</div>
