@props([
    'data' => [],
    'color' => 'bg-primary-600',
])

@php
$max = collect($data)->max('value') ?: 1;
@endphp

<div {{ $attributes->merge(['class' => 'space-y-3']) }}>
    @foreach ($data as $item)
        @php $pct = max(2, round(($item['value'] / $max) * 100)); @endphp
        <div class="flex items-center gap-3">
            <p class="w-28 flex-shrink-0 truncate text-body-sm text-txt-black-900">{{ $item['label'] }}</p>
            <div class="h-5 flex-1 overflow-hidden rounded-md bg-bg-washed">
                <div class="{{ $color }} h-full rounded-md" style="width: {{ $pct }}%"></div>
            </div>
            <p class="w-14 flex-shrink-0 text-end text-body-sm text-txt-black-500">{{ $item['value'] }}</p>
        </div>
    @endforeach
</div>
