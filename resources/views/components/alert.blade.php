@props([
    'type' => 'info',
    'dismissible' => false,
])

@php
$variants = [
    'success' => ['bg' => 'bg-bg-success-50', 'border' => 'border-otl-success-200', 'text' => 'text-txt-success', 'icon' => 'text-txt-success'],
    'error' => ['bg' => 'bg-bg-danger-50', 'border' => 'border-otl-danger-200', 'text' => 'text-txt-danger', 'icon' => 'text-txt-danger'],
    'warning' => ['bg' => 'bg-bg-warning-50', 'border' => 'border-otl-warning-200', 'text' => 'text-txt-warning', 'icon' => 'text-txt-warning'],
    'info' => ['bg' => 'bg-bg-primary-50', 'border' => 'border-otl-primary-200', 'text' => 'text-txt-primary', 'icon' => 'text-txt-primary'],
][$type];
@endphp

<div
    @if ($dismissible) x-data="{ show: true }" x-show="show" @endif
    {{ $attributes->merge(['class' => "flex items-start gap-3 rounded-md border px-4 py-3 text-body-sm {$variants['bg']} {$variants['border']} {$variants['text']}"]) }}
>
    <svg class="h-5 w-5 flex-shrink-0 {{ $variants['icon'] }}" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
    </svg>

    <div class="flex-1">{{ $slot }}</div>

    @if ($dismissible)
        <button type="button" x-on:click="show = false" class="flex-shrink-0 {{ $variants['icon'] }} hover:opacity-70">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    @endif
</div>
