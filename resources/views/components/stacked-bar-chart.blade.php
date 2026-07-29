@props([
    'data' => [],
    'colors' => [],
    'mode' => 'stacked',
    'height' => 192,
])

@php
$seriesKeys = collect($data)->flatMap(fn ($item) => array_keys($item['values']))->unique()->values();

$max = $mode === 'stacked'
    ? collect($data)->max(fn ($item) => array_sum($item['values']))
    : collect($data)->flatMap(fn ($item) => array_values($item['values']))->max();
$max = $max ?: 1;
@endphp

<div {{ $attributes }}>
    <div class="flex items-end gap-4" style="height: {{ $height }}px">
        @foreach ($data as $item)
            <div class="flex h-full flex-1 flex-col items-center justify-end gap-2">
                @if ($mode === 'stacked')
                    <div class="flex w-full flex-1 flex-col-reverse items-stretch">
                        @foreach ($seriesKeys as $key)
                            @php $value = $item['values'][$key] ?? 0; $pct = round(($value / $max) * 100); @endphp
                            @if ($value > 0)
                                <div class="{{ $colors[$key] ?? 'bg-primary-600' }} w-full" style="height: {{ $pct }}%"></div>
                            @endif
                        @endforeach
                    </div>
                @else
                    <div class="flex h-full w-full items-end gap-1">
                        @foreach ($seriesKeys as $key)
                            @php $value = $item['values'][$key] ?? 0; $pct = max(2, round(($value / $max) * 100)); @endphp
                            <div class="{{ $colors[$key] ?? 'bg-primary-600' }} flex-1 rounded-t-sm" style="height: {{ $pct }}%"></div>
                        @endforeach
                    </div>
                @endif
                <p class="text-body-xs text-txt-black-500">{{ $item['label'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-3 flex flex-wrap gap-4">
        @foreach ($seriesKeys as $key)
            <div class="flex items-center gap-2 text-body-sm">
                <span class="{{ $colors[$key] ?? 'bg-primary-600' }} h-2.5 w-2.5 flex-shrink-0 rounded-sm"></span>
                <span class="text-txt-black-700">{{ $key }}</span>
            </div>
        @endforeach
    </div>
</div>
