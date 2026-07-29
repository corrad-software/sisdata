@props(['active' => false, 'label' => null])

@php
$classes = $active
    ? 'flex items-center gap-3 px-3 py-2 rounded-md text-body-sm leading-none font-medium bg-white text-primary-700 shadow-sm'
    : 'flex items-center gap-3 px-3 py-2 rounded-md text-body-sm leading-none font-medium text-primary-100 hover:bg-primary-600 hover:text-white';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if ($label) title="{{ $label }}" @endif>
    @isset($icon)
        <span class="flex-shrink-0">{{ $icon }}</span>
    @endisset

    <span :class="sidebarCollapsed ? 'lg:hidden' : ''" class="truncate">{{ $slot }}</span>
</a>
