@props([
    'data' => [],
    'colLabels' => [],
    'max' => null,
    'colorScale' => ['bg-bg-washed', 'bg-primary-200', 'bg-primary-400', 'bg-primary-600', 'bg-primary-800'],
])

@php
$allCells = collect($data)->flatMap(fn ($row) => $row['cells']);
$max = $max ?? ($allCells->max() ?: 1);
$steps = count($colorScale);

$colorFor = function ($value) use ($max, $steps, $colorScale) {
    if ($value <= 0) {
        return $colorScale[0];
    }

    $index = min($steps - 1, (int) ceil(($value / $max) * ($steps - 1)));

    return $colorScale[$index];
};
@endphp

<div {{ $attributes }}>
    <div class="inline-block">
        @if (count($colLabels))
            <div class="mb-1 flex gap-1 ps-14">
                @foreach ($colLabels as $label)
                    <span class="w-5 flex-shrink-0 text-center text-[10px] text-txt-black-500">{{ $label }}</span>
                @endforeach
            </div>
        @endif

        @foreach ($data as $row)
            <div class="mb-1 flex items-center gap-1">
                <span class="w-12 flex-shrink-0 text-body-xs text-txt-black-500">{{ $row['label'] }}</span>
                @foreach ($row['cells'] as $value)
                    <span class="{{ $colorFor($value) }} h-5 w-5 flex-shrink-0 rounded-sm" title="{{ $value }}"></span>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
