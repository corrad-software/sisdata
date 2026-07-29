@props([
    'series' => [],
    'labels' => [],
    'height' => 180,
])

@php
$allValues = collect($series)->flatMap(fn ($s) => $s['data']);
$max = $allValues->max() ?: 1;
$min = min(0, $allValues->min() ?: 0);
$range = ($max - $min) ?: 1;
$width = 300;
$count = count($labels);

$lines = collect($series)->map(function ($s) use ($count, $width, $min, $range) {
    $points = collect($s['data'])->values()->map(function ($value, $i) use ($count, $width, $min, $range) {
        $x = $count > 1 ? round(($i / ($count - 1)) * $width, 2) : $width / 2;
        $y = round(100 - ((($value - $min) / $range) * 100), 2);
        return "{$x},{$y}";
    })->implode(' ');

    return [
        'points' => $points,
        'color' => $s['color'] ?? 'text-primary-600',
        'name' => $s['name'],
    ];
});
@endphp

<div {{ $attributes }}>
    <svg viewBox="0 0 {{ $width }} 100" preserveAspectRatio="none" class="w-full" style="height: {{ $height }}px">
        @foreach ($lines as $line)
            <polyline
                points="{{ $line['points'] }}"
                fill="none"
                stroke="currentColor"
                class="{{ $line['color'] }}"
                stroke-width="2"
                vector-effect="non-scaling-stroke"
                stroke-linejoin="round"
                stroke-linecap="round"
            />
        @endforeach
    </svg>

    <div class="mt-2 flex justify-between text-body-xs text-txt-black-500">
        @foreach ($labels as $label)
            <span>{{ $label }}</span>
        @endforeach
    </div>

    <div class="mt-3 flex flex-wrap gap-4">
        @foreach ($lines as $line)
            <div class="flex items-center gap-2 text-body-sm">
                <span class="{{ $line['color'] }} h-0.5 w-4 flex-shrink-0" style="background-color: currentColor"></span>
                <span class="text-txt-black-700">{{ $line['name'] }}</span>
            </div>
        @endforeach
    </div>
</div>
