interface PieChartDatum {
    label: string;
    value: number;
    color?: string;
}

export function PieChart({
    data,
    size = 160,
    legend = true,
    className,
}: {
    data: PieChartDatum[];
    size?: number;
    legend?: boolean;
    className?: string;
}) {
    const total = data.reduce((sum, d) => sum + d.value, 0) || 1;
    const cx = size / 2;
    const cy = size / 2;
    const r = size / 2;
    const isSingleSegment = data.length === 1;

    const segments = data.map((item, i) => {
        const fraction = item.value / total;
        const priorValue = data.slice(0, i).reduce((sum, d) => sum + d.value, 0);
        const cumulativeBefore = priorValue / total;
        const startAngle = cumulativeBefore * 2 * Math.PI - Math.PI / 2;
        const endAngle = (cumulativeBefore + fraction) * 2 * Math.PI - Math.PI / 2;

        const x1 = Math.round((cx + r * Math.cos(startAngle)) * 100) / 100;
        const y1 = Math.round((cy + r * Math.sin(startAngle)) * 100) / 100;
        const x2 = Math.round((cx + r * Math.cos(endAngle)) * 100) / 100;
        const y2 = Math.round((cy + r * Math.sin(endAngle)) * 100) / 100;
        const largeArc = endAngle - startAngle > Math.PI ? 1 : 0;

        return {
            path: `M${cx},${cy} L${x1},${y1} A${r},${r} 0 ${largeArc} 1 ${x2},${y2} Z`,
            color: item.color ?? 'text-primary-600',
            label: item.label,
            percent: Math.round(fraction * 100),
        };
    });

    return (
        <div className={`flex flex-wrap items-center gap-6 ${className ?? ''}`}>
            <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} className="flex-shrink-0">
                {isSingleSegment ? (
                    <circle cx={cx} cy={cy} r={r} fill="currentColor" className={segments[0].color} />
                ) : (
                    segments.map((segment, i) => (
                        <path key={i} d={segment.path} fill="currentColor" className={segment.color} stroke="white" strokeWidth={1} />
                    ))
                )}
            </svg>

            {legend && (
                <div className="space-y-2">
                    {segments.map((segment) => (
                        <div key={segment.label} className="flex items-center gap-2 text-body-sm">
                            <span className={`${segment.color} h-2.5 w-2.5 flex-shrink-0 rounded-full`} style={{ backgroundColor: 'currentColor' }} />
                            <span className="text-txt-black-700">{segment.label}</span>
                            <span className="text-txt-black-500">{segment.percent}%</span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
