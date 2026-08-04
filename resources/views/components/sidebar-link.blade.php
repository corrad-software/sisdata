@props(['active' => false, 'label' => null])

@php
$classes = $active
    ? 'flex items-center gap-3 px-3 py-2 rounded-md text-body-sm leading-tight font-medium bg-white text-primary-700 shadow-sm'
    : 'flex items-center gap-3 px-3 py-2 rounded-md text-body-sm leading-tight font-medium text-primary-100 hover:bg-primary-600 hover:text-white';
@endphp

<div class="relative" x-data="{ showTooltip: false }">
    <a
        {{ $attributes->merge(['class' => $classes]) }}
        x-on:mouseenter="showTooltip = true"
        x-on:mouseleave="showTooltip = false"
    >
        @isset($icon)
            <span class="flex-shrink-0">{{ $icon }}</span>
        @endisset

        <span :class="sidebarCollapsed ? 'lg:hidden' : ''" class="truncate">{{ $slot }}</span>
    </a>

    @if ($label)
        <div
            x-show="showTooltip && sidebarCollapsed"
            x-transition
            style="display: none;"
            class="hidden lg:block absolute left-full top-1/2 z-20 ms-2 -translate-y-1/2 whitespace-nowrap rounded-md bg-txt-black-900 px-2.5 py-1.5 text-body-xs text-white shadow-card pointer-events-none"
        >
            {{ $label }}
            <span class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-txt-black-900"></span>
        </div>
    @endif
</div>
