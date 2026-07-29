@props([
    'data' => [],
    'size' => 160,
    'legend' => true,
])

@php
$total = collect($data)->sum('value') ?: 1;
$cx = $size / 2;
$cy = $size / 2;
$r = $size / 2;
$cumulative = 0;
$isSingleSegment = count($data) === 1;

$segments = collect($data)->map(function ($item) use ($total, $cx, $cy, $r, &$cumulative) {
    $fraction = $item['value'] / $total;
    $startAngle = $cumulative * 2 * M_PI - M_PI / 2;
    $cumulative += $fraction;
    $endAngle = $cumulative * 2 * M_PI - M_PI / 2;

    $x1 = round($cx + $r * cos($startAngle), 2);
    $y1 = round($cy + $r * sin($startAngle), 2);
    $x2 = round($cx + $r * cos($endAngle), 2);
    $y2 = round($cy + $r * sin($endAngle), 2);
    $largeArc = ($endAngle - $startAngle) > M_PI ? 1 : 0;

    return [
        'path' => "M{$cx},{$cy} L{$x1},{$y1} A{$r},{$r} 0 {$largeArc} 1 {$x2},{$y2} Z",
        'color' => $item['color'] ?? 'text-primary-600',
        'label' => $item['label'],
        'value' => $item['value'],
        'percent' => round($fraction * 100),
    ];
});
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-6']) }}>
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}" class="flex-shrink-0">
        @if ($isSingleSegment)
            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="currentColor" class="{{ $segments[0]['color'] }}" />
        @else
            @foreach ($segments as $segment)
                <path d="{{ $segment['path'] }}" fill="currentColor" class="{{ $segment['color'] }}" stroke="white" stroke-width="1" />
            @endforeach
        @endif
    </svg>

    @if ($legend)
        <div class="space-y-2">
            @foreach ($segments as $segment)
                <div class="flex items-center gap-2 text-body-sm">
                    <span class="{{ $segment['color'] }} h-2.5 w-2.5 flex-shrink-0 rounded-full" style="background-color: currentColor"></span>
                    <span class="text-txt-black-700">{{ $segment['label'] }}</span>
                    <span class="text-txt-black-500">{{ $segment['percent'] }}%</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
