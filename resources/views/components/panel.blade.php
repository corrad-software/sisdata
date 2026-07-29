@props(['title' => null])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-otl-gray-200 bg-bg-white']) }}>
    @if ($title)
        <div class="border-b border-otl-gray-200 px-4 py-3">
            <h4 class="text-body-sm font-semibold text-txt-black-900">{{ $title }}</h4>
        </div>
    @endif
    <div class="p-4">
        {{ $slot }}
    </div>
</div>
