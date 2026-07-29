@props([
    'data' => [],
    'size' => 160,
    'strokeWidth' => 20,
    'legend' => true,
    'total' => null,
    'centerLabel' => null,
    'rounded' => false,
])

@php
$total = $total ?? (collect($data)->sum('value') ?: 1);
$radius = ($size - $strokeWidth) / 2;
$circumference = 2 * M_PI * $radius;
$offset = 0;

$segments = collect($data)->map(function ($item) use ($total, $circumference, &$offset) {
    $fraction = $item['value'] / $total;
    $dash = $fraction * $circumference;
    $segment = [
        'dasharray' => round($dash, 2) . ' ' . round($circumference - $dash, 2),
        'dashoffset' => -round($offset, 2),
        'color' => $item['color'] ?? 'text-primary-600',
        'label' => $item['label'],
        'value' => $item['value'],
        'percent' => round($fraction * 100),
    ];
    $offset += $dash;

    return $segment;
});
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-6']) }}>
    <div class="relative flex-shrink-0" style="width: {{ $size }}px; height: {{ $size }}px">
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}" class="-rotate-90">
            <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}" fill="none" class="stroke-otl-gray-200" stroke-width="{{ $strokeWidth }}" />

            @foreach ($segments as $segment)
                <circle
                    cx="{{ $size / 2 }}"
                    cy="{{ $size / 2 }}"
                    r="{{ $radius }}"
                    fill="none"
                    stroke="currentColor"
                    class="{{ $segment['color'] }}"
                    stroke-width="{{ $strokeWidth }}"
                    stroke-dasharray="{{ $segment['dasharray'] }}"
                    stroke-dashoffset="{{ $segment['dashoffset'] }}"
                    stroke-linecap="{{ $rounded ? 'round' : 'butt' }}"
                />
            @endforeach
        </svg>

        @if ($centerLabel)
            <div class="absolute inset-0 flex items-center justify-center">
                <span class="font-heading text-heading-2xs font-semibold text-txt-black-900">{{ $centerLabel }}</span>
            </div>
        @endif
    </div>

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
