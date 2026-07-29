@props(['phase' => 'Beta'])

<div {{ $attributes->merge(['class' => 'flex items-center gap-3 border-b border-otl-divider py-2']) }}>
    <span class="inline-flex flex-shrink-0 items-center rounded-md bg-primary-600 px-2 py-0.5 text-body-xs font-semibold uppercase text-white">
        {{ $phase }}
    </span>
    <p class="text-body-sm text-txt-black-700">
        {{ $slot }}
    </p>
</div>
