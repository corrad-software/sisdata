@props([
    'label' => null,
    'name' => null,
    'error' => null,
    'help' => null,
    'required' => false,
    'rows' => 4,
    'maxlength' => null,
])

@php
$id = $attributes->get('id') ?? $name;
@endphp

<div @if ($maxlength) x-data="{ count: {{ strlen((string) $slot) }} }" @endif>
    @if ($label)
        <x-input-label :for="$id" class="mb-1.5">
            {!! $label !!} @if ($required) <span class="text-danger-600">*</span> @endif
        </x-input-label>
    @endif

    <textarea
        id="{{ $id }}"
        rows="{{ $rows }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($maxlength) maxlength="{{ $maxlength }}" x-on:input="count = $el.value.length" @endif
        {{ $attributes->except(['label', 'name', 'error', 'help', 'required', 'rows', 'maxlength'])->merge([
            'class' => 'block w-full rounded-md border bg-bg-white text-txt-black-900 ' . ($error ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500' : 'border-otl-gray-300 focus:border-primary-600 focus:ring-fr-primary') . ' text-body-sm shadow-sm focus:ring-1 disabled:bg-bg-washed disabled:opacity-60',
        ]) }}
    >{{ $slot }}</textarea>

    <div class="mt-1 flex items-center justify-between gap-2">
        <div>
            @if ($help && ! $error)
                <p class="text-body-xs text-txt-black-500">{{ $help }}</p>
            @endif

            @if ($error)
                <p class="text-body-xs text-danger-600">{{ $error }}</p>
            @endif
        </div>

        @if ($maxlength)
            <p class="flex-shrink-0 text-body-xs text-txt-black-500" x-text="count + ' / {{ $maxlength }}'"></p>
        @endif
    </div>
</div>
