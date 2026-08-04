interface BarChartDatum {
    label: string;
    value: number;
}

export function BarChart({
    data,
    color = 'bg-primary-600',
    height = 192,
    className,
}: {
    data: BarChartDatum[];
    color?: string;
    height?: number;
    className?: string;
}) {
    const max = Math.max(...data.map((d) => d.value), 1);

    return (
        <div className={className}>
            <div className="flex items-end gap-3" style={{ height }}>
                {data.map((item) => {
                    const pct = Math.max(2, Math.round((item.value / max) * 100));

                    return (
                        <div key={item.label} className="flex h-full flex-1 flex-col items-center justify-end gap-2">
                            <span className="text-body-xs text-txt-black-500">{item.value}</span>
                            <div className="flex w-full flex-1 items-end">
                                <div className={`${color} w-full rounded-t-md`} style={{ height: `${pct}%` }} />
                            </div>
                            <p className="text-body-xs text-txt-black-500">{item.label}</p>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
