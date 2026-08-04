interface Series {
    name: string;
    data: number[];
    color?: string;
}

export function MultiLineChart({
    series,
    labels,
    height = 180,
    className,
}: {
    series: Series[];
    labels: string[];
    height?: number;
    className?: string;
}) {
    const allValues = series.flatMap((s) => s.data);
    const max = Math.max(...allValues, 1);
    const min = Math.min(0, ...allValues);
    const range = max - min || 1;
    const width = 300;
    const count = labels.length;

    const lines = series.map((s) => {
        const points = s.data
            .map((value, i) => {
                const x = count > 1 ? Math.round((i / (count - 1)) * width * 100) / 100 : width / 2;
                const y = Math.round((100 - ((value - min) / range) * 100) * 100) / 100;
                return `${x},${y}`;
            })
            .join(' ');

        return { points, color: s.color ?? 'text-primary-600', name: s.name };
    });

    return (
        <div className={className}>
            <svg viewBox={`0 0 ${width} 100`} preserveAspectRatio="none" className="w-full" style={{ height }}>
                {lines.map((line) => (
                    <polyline
                        key={line.name}
                        points={line.points}
                        fill="none"
                        stroke="currentColor"
                        className={line.color}
                        strokeWidth={2}
                        vectorEffect="non-scaling-stroke"
                        strokeLinejoin="round"
                        strokeLinecap="round"
                    />
                ))}
            </svg>

            <div className="mt-2 flex justify-between text-body-xs text-txt-black-500">
                {labels.map((label) => (
                    <span key={label}>{label}</span>
                ))}
            </div>

            <div className="mt-3 flex flex-wrap gap-4">
                {lines.map((line) => (
                    <div key={line.name} className="flex items-center gap-2 text-body-sm">
                        <span className={`${line.color} h-0.5 w-4 flex-shrink-0`} style={{ backgroundColor: 'currentColor' }} />
                        <span className="text-txt-black-700">{line.name}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}
