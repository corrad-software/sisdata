@props(['text' => ''])

<span x-data="{ show: false }" {{ $attributes->merge(['class' => 'relative inline-flex']) }}>
    <span
        tabindex="0"
        x-on:mouseenter="show = true"
        x-on:mouseleave="show = false"
        x-on:focus="show = true"
        x-on:blur="show = false"
    >
        {{ $slot }}
    </span>

    <span
        x-show="show"
        x-transition
        style="display: none;"
        class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-txt-black-900 px-2.5 py-1.5 text-body-xs text-white shadow-card"
    >
        {{ $text }}
        <span class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-txt-black-900"></span>
    </span>
</span>
