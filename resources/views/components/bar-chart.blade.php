@props([
    'data' => [],
    'color' => 'bg-primary-600',
    'height' => 192,
])

@php
$max = collect($data)->max('value') ?: 1;
@endphp

<div {{ $attributes }}>
    <div class="flex items-end gap-3" style="height: {{ $height }}px">
        @foreach ($data as $item)
            @php $pct = max(2, round(($item['value'] / $max) * 100)); @endphp
            <div class="flex h-full flex-1 flex-col items-center justify-end gap-2">
                <span class="text-body-xs text-txt-black-500">{{ $item['value'] }}</span>
                <div class="flex w-full flex-1 items-end">
                    <div class="{{ $color }} w-full rounded-t-md" style="height: {{ $pct }}%"></div>
                </div>
                <p class="text-body-xs text-txt-black-500">{{ $item['label'] }}</p>
            </div>
        @endforeach
    </div>
</div>
