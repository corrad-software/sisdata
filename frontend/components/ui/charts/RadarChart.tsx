interface RadarSeries {
    name: string;
    data: number[];
    color?: string;
}

export function RadarChart({
    labels,
    series,
    max = 100,
    size = 240,
    className,
}: {
    labels: string[];
    series: RadarSeries[];
    max?: number;
    size?: number;
    className?: string;
}) {
    const count = labels.length;
    const cx = size / 2;
    const cy = size / 2;
    const r = size / 2 - 24;

    const angleFor = (i: number) => -Math.PI / 2 + i * ((2 * Math.PI) / Math.max(1, count));

    const axisPoints = Array.from({ length: count }, (_, i) => {
        const angle = angleFor(i);
        return { x: Math.round((cx + r * Math.cos(angle)) * 100) / 100, y: Math.round((cy + r * Math.sin(angle)) * 100) / 100 };
    });

    const labelPoints = Array.from({ length: count }, (_, i) => {
        const angle = angleFor(i);
        const lr = r + 16;
        return {
            x: Math.round((cx + lr * Math.cos(angle)) * 100) / 100,
            y: Math.round((cy + lr * Math.sin(angle)) * 100) / 100,
            label: labels[i],
        };
    });

    const gridPolygons = [0.25, 0.5, 0.75, 1].map((level) =>
        Array.from({ length: count }, (_, i) => {
            const angle = angleFor(i);
            return `${Math.round((cx + r * level * Math.cos(angle)) * 100) / 100},${Math.round((cy + r * level * Math.sin(angle)) * 100) / 100}`;
        }).join(' '),
    );

    const seriesPolygons = series.map((s) => {
        const points = Array.from({ length: count }, (_, i) => {
            const value = s.data[i] ?? 0;
            const fraction = max > 0 ? Math.min(1, value / max) : 0;
            const angle = angleFor(i);
            return `${Math.round((cx + r * fraction * Math.cos(angle)) * 100) / 100},${Math.round((cy + r * fraction * Math.sin(angle)) * 100) / 100}`;
        }).join(' ');

        return { points, color: s.color ?? 'text-primary-600', name: s.name };
    });

    return (
        <div className={`flex flex-wrap items-center gap-6 ${className ?? ''}`}>
            <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} className="flex-shrink-0 overflow-visible">
                {gridPolygons.map((polygon, i) => (
                    <polygon key={i} points={polygon} fill="none" className="stroke-otl-gray-200" strokeWidth={1} />
                ))}

                {axisPoints.map((point, i) => (
                    <line key={i} x1={cx} y1={cy} x2={point.x} y2={point.y} className="stroke-otl-gray-200" strokeWidth={1} />
                ))}

                {seriesPolygons.map((s) => (
                    <g key={s.name}>
                        <polygon points={s.points} fill="currentColor" className={s.color} opacity={0.15} />
                        <polygon points={s.points} fill="none" stroke="currentColor" className={s.color} strokeWidth={2} strokeLinejoin="round" />
                    </g>
                ))}

                {labelPoints.map((point, i) => (
                    <text
                        key={i}
                        x={point.x}
                        y={point.y}
                        textAnchor="middle"
                        dominantBaseline="middle"
                        className="fill-txt-black-500"
                        style={{ fontSize: '10px' }}
                    >
                        {point.label}
                    </text>
                ))}
            </svg>

            {series.length > 1 && (
                <div className="space-y-2">
                    {seriesPolygons.map((s) => (
                        <div key={s.name} className="flex items-center gap-2 text-body-sm">
                            <span className={`${s.color} h-2.5 w-2.5 flex-shrink-0 rounded-full`} style={{ backgroundColor: 'currentColor' }} />
                            <span className="text-txt-black-700">{s.name}</span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
