interface HorizontalBarDatum {
    label: string;
    value: number;
}

export function HorizontalBarChart({
    data,
    color = 'bg-primary-600',
    className,
}: {
    data: HorizontalBarDatum[];
    color?: string;
    className?: string;
}) {
    const max = Math.max(...data.map((d) => d.value), 1);

    return (
        <div className={`space-y-3 ${className ?? ''}`}>
            {data.map((item) => {
                const pct = Math.max(2, Math.round((item.value / max) * 100));

                return (
                    <div key={item.label} className="flex items-center gap-3">
                        <p className="w-28 flex-shrink-0 truncate text-body-sm text-txt-black-900">{item.label}</p>
                        <div className="h-5 flex-1 overflow-hidden rounded-md bg-bg-washed">
                            <div className={`${color} h-full rounded-md`} style={{ width: `${pct}%` }} />
                        </div>
                        <p className="w-14 flex-shrink-0 text-end text-body-sm text-txt-black-500">{item.value}</p>
                    </div>
                );
            })}
        </div>
    );
}
