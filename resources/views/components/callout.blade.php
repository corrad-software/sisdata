@props([
    'type' => 'info',
    'title' => null,
])

@php
$variants = [
    'success' => ['bg' => 'bg-bg-success-50', 'border' => 'border-otl-success-200', 'icon' => 'text-txt-success'],
    'danger' => ['bg' => 'bg-bg-danger-50', 'border' => 'border-otl-danger-200', 'icon' => 'text-txt-danger'],
    'warning' => ['bg' => 'bg-bg-warning-50', 'border' => 'border-otl-warning-200', 'icon' => 'text-txt-warning'],
    'info' => ['bg' => 'bg-bg-primary-50', 'border' => 'border-otl-primary-200', 'icon' => 'text-txt-primary'],
][$type];
@endphp

<div {{ $attributes->merge(['class' => "rounded-lg border {$variants['border']} {$variants['bg']} p-4"]) }}>
    <div class="flex gap-3">
        <svg class="h-5 w-5 flex-shrink-0 {{ $variants['icon'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
        </svg>
        <div class="space-y-1">
            @if ($title)
                <p class="text-body-sm font-semibold {{ $variants['icon'] }}">{{ $title }}</p>
            @endif
            <div class="text-body-sm text-txt-black-700">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
