@props(['items' => []])

@php
$dotClasses = fn ($color) => match ($color ?? 'primary') {
    'success' => 'bg-bg-success-50 text-txt-success',
    'danger' => 'bg-bg-danger-50 text-txt-danger',
    'warning' => 'bg-bg-warning-50 text-txt-warning',
    default => 'bg-bg-primary-50 text-txt-primary',
};
@endphp

<ol {{ $attributes->merge(['class' => 'relative border-s border-otl-gray-200 ms-3']) }}>
    @foreach ($items as $item)
        <li class="mb-8 ms-6 last:mb-0">
            <span class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-bg-white {{ $dotClasses($item['color'] ?? null) }}">
                <svg class="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 2a8 8 0 100 16 8 8 0 000-16z" />
                </svg>
            </span>

            <h4 class="text-body-sm font-medium text-txt-black-900">{{ $item['title'] }}</h4>
            <time class="block text-body-xs text-txt-black-500 mb-1">{{ $item['time'] }}</time>

            @if (! empty($item['description']))
                <p class="text-body-sm text-txt-black-500">{{ $item['description'] }}</p>
            @endif
        </li>
    @endforeach
</ol>
