interface LineChartDatum {
    label: string;
    value: number;
}

export function LineChart({
    data,
    height = 160,
    showLabels = true,
    className,
}: {
    data: LineChartDatum[];
    height?: number;
    showLabels?: boolean;
    className?: string;
}) {
    const values = data.map((d) => d.value);
    const max = Math.max(...values, 1);
    const min = Math.min(0, ...values);
    const range = max - min || 1;
    const count = data.length;
    const width = 300;

    const points = data
        .map((item, i) => {
            const x = count > 1 ? Math.round((i / (count - 1)) * width * 100) / 100 : width / 2;
            const y = Math.round((100 - ((item.value - min) / range) * 100) * 100) / 100;
            return `${x},${y}`;
        })
        .join(' ');

    const areaPoints = `0,100 ${points} ${width},100`;

    return (
        <div className={className}>
            <svg viewBox={`0 0 ${width} 100`} preserveAspectRatio="none" className="w-full" style={{ height }}>
                <polygon points={areaPoints} className="fill-primary-100" />
                <polyline
                    points={points}
                    fill="none"
                    className="stroke-primary-600"
                    strokeWidth={2}
                    vectorEffect="non-scaling-stroke"
                    strokeLinejoin="round"
                    strokeLinecap="round"
                />
            </svg>
            {showLabels && (
                <div className="mt-2 flex justify-between text-body-xs text-txt-black-500">
                    {data.map((item) => (
                        <span key={item.label}>{item.label}</span>
                    ))}
                </div>
            )}
        </div>
    );
}
