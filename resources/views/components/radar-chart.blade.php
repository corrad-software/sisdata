@props([
    'labels' => [],
    'series' => [],
    'max' => 100,
    'size' => 240,
])

@php
$count = count($labels);
$cx = $size / 2;
$cy = $size / 2;
$r = $size / 2 - 24;

$angleFor = fn ($i) => -M_PI / 2 + $i * (2 * M_PI / max(1, $count));

$axisPoints = collect(range(0, $count - 1))->map(function ($i) use ($angleFor, $cx, $cy, $r) {
    $angle = $angleFor($i);
    return ['x' => round($cx + $r * cos($angle), 2), 'y' => round($cy + $r * sin($angle), 2)];
});

$labelPoints = collect(range(0, $count - 1))->map(function ($i) use ($angleFor, $cx, $cy, $r, $labels) {
    $angle = $angleFor($i);
    $lr = $r + 16;
    return ['x' => round($cx + $lr * cos($angle), 2), 'y' => round($cy + $lr * sin($angle), 2), 'label' => $labels[$i]];
});

$gridPolygons = collect([0.25, 0.5, 0.75, 1])->map(function ($level) use ($count, $angleFor, $cx, $cy, $r) {
    return collect(range(0, $count - 1))->map(function ($i) use ($angleFor, $cx, $cy, $r, $level) {
        $angle = $angleFor($i);
        return round($cx + $r * $level * cos($angle), 2) . ',' . round($cy + $r * $level * sin($angle), 2);
    })->implode(' ');
});

$seriesPolygons = collect($series)->map(function ($s) use ($count, $angleFor, $cx, $cy, $r, $max) {
    $points = collect(range(0, $count - 1))->map(function ($i) use ($s, $angleFor, $cx, $cy, $r, $max) {
        $value = $s['data'][$i] ?? 0;
        $fraction = $max > 0 ? min(1, $value / $max) : 0;
        $angle = $angleFor($i);
        return round($cx + $r * $fraction * cos($angle), 2) . ',' . round($cy + $r * $fraction * sin($angle), 2);
    })->implode(' ');

    return [
        'points' => $points,
        'color' => $s['color'] ?? 'text-primary-600',
        'name' => $s['name'],
    ];
});
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-6']) }}>
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}" class="flex-shrink-0 overflow-visible">
        @foreach ($gridPolygons as $polygon)
            <polygon points="{{ $polygon }}" fill="none" class="stroke-otl-gray-200" stroke-width="1" />
        @endforeach

        @foreach ($axisPoints as $point)
            <line x1="{{ $cx }}" y1="{{ $cy }}" x2="{{ $point['x'] }}" y2="{{ $point['y'] }}" class="stroke-otl-gray-200" stroke-width="1" />
        @endforeach

        @foreach ($seriesPolygons as $s)
            <polygon points="{{ $s['points'] }}" fill="currentColor" class="{{ $s['color'] }}" opacity="0.15" />
            <polygon points="{{ $s['points'] }}" fill="none" stroke="currentColor" class="{{ $s['color'] }}" stroke-width="2" stroke-linejoin="round" />
        @endforeach

        @foreach ($labelPoints as $point)
            <text x="{{ $point['x'] }}" y="{{ $point['y'] }}" text-anchor="middle" dominant-baseline="middle" class="fill-txt-black-500" style="font-size: 10px">{{ $point['label'] }}</text>
        @endforeach
    </svg>

    @if (count($series) > 1)
        <div class="space-y-2">
            @foreach ($seriesPolygons as $s)
                <div class="flex items-center gap-2 text-body-sm">
                    <span class="{{ $s['color'] }} h-2.5 w-2.5 flex-shrink-0 rounded-full" style="background-color: currentColor"></span>
                    <span class="text-txt-black-700">{{ $s['name'] }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
