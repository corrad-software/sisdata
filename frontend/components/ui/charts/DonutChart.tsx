interface DonutChartDatum {
    label: string;
    value: number;
    color?: string;
}

export function DonutChart({
    data,
    size = 160,
    strokeWidth = 20,
    legend = true,
    total: totalProp,
    centerLabel,
    rounded = false,
    className,
}: {
    data: DonutChartDatum[];
    size?: number;
    strokeWidth?: number;
    legend?: boolean;
    total?: number;
    centerLabel?: string;
    rounded?: boolean;
    className?: string;
}) {
    const total = totalProp ?? (data.reduce((sum, d) => sum + d.value, 0) || 1);
    const radius = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;

    const segments = data.map((item, i) => {
        const fraction = item.value / total;
        const dash = fraction * circumference;
        const priorValue = data.slice(0, i).reduce((sum, d) => sum + d.value, 0);
        const offset = (priorValue / total) * circumference;

        return {
            dasharray: `${Math.round(dash * 100) / 100} ${Math.round((circumference - dash) * 100) / 100}`,
            dashoffset: -Math.round(offset * 100) / 100,
            color: item.color ?? 'text-primary-600',
            label: item.label,
            percent: Math.round(fraction * 100),
        };
    });

    return (
        <div className={`flex flex-wrap items-center gap-6 ${className ?? ''}`}>
            <div className="relative flex-shrink-0" style={{ width: size, height: size }}>
                <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} className="-rotate-90">
                    <circle
                        cx={size / 2}
                        cy={size / 2}
                        r={radius}
                        fill="none"
                        className="stroke-otl-gray-200"
                        strokeWidth={strokeWidth}
                    />

                    {segments.map((segment, i) => (
                        <circle
                            key={i}
                            cx={size / 2}
                            cy={size / 2}
                            r={radius}
                            fill="none"
                            stroke="currentColor"
                            className={segment.color}
                            strokeWidth={strokeWidth}
                            strokeDasharray={segment.dasharray}
                            strokeDashoffset={segment.dashoffset}
                            strokeLinecap={rounded ? 'round' : 'butt'}
                        />
                    ))}
                </svg>

                {centerLabel && (
                    <div className="absolute inset-0 flex items-center justify-center">
                        <span className="font-heading text-heading-2xs font-semibold text-txt-black-900">{centerLabel}</span>
                    </div>
                )}
            </div>

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
