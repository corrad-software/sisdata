@props([
    'data' => [],
    'height' => 160,
    'showLabels' => true,
])

@php
$values = collect($data)->pluck('value');
$max = $values->max() ?: 1;
$min = min(0, $values->min() ?: 0);
$range = ($max - $min) ?: 1;
$count = count($data);
$width = 300;

$points = collect($data)->values()->map(function ($item, $i) use ($count, $width, $min, $range) {
    $x = $count > 1 ? round(($i / ($count - 1)) * $width, 2) : $width / 2;
    $y = round(100 - ((($item['value'] - $min) / $range) * 100), 2);
    return "{$x},{$y}";
})->implode(' ');

$areaPoints = "0,100 {$points} {$width},100";
@endphp

<div {{ $attributes }}>
    <svg viewBox="0 0 {{ $width }} 100" preserveAspectRatio="none" class="w-full" style="height: {{ $height }}px">
        <polygon points="{{ $areaPoints }}" class="fill-primary-100" />
        <polyline points="{{ $points }}" fill="none" class="stroke-primary-600" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round" />
    </svg>
    @if ($showLabels)
        <div class="mt-2 flex justify-between text-body-xs text-txt-black-500">
            @foreach ($data as $item)
                <span>{{ $item['label'] }}</span>
            @endforeach
        </div>
    @endif
</div>
