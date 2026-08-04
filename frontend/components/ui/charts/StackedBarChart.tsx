interface StackedBarDatum {
    label: string;
    values: Record<string, number>;
}

export function StackedBarChart({
    data,
    colors = {},
    mode = 'stacked',
    height = 192,
    className,
}: {
    data: StackedBarDatum[];
    colors?: Record<string, string>;
    mode?: 'stacked' | 'grouped';
    height?: number;
    className?: string;
}) {
    const seriesKeys = Array.from(new Set(data.flatMap((item) => Object.keys(item.values))));

    const max =
        mode === 'stacked'
            ? Math.max(...data.map((item) => Object.values(item.values).reduce((sum, v) => sum + v, 0)), 1)
            : Math.max(...data.flatMap((item) => Object.values(item.values)), 1);

    return (
        <div className={className}>
            <div className="flex items-end gap-4" style={{ height }}>
                {data.map((item) => (
                    <div key={item.label} className="flex h-full flex-1 flex-col items-center justify-end gap-2">
                        {mode === 'stacked' ? (
                            <div className="flex w-full flex-1 flex-col-reverse items-stretch">
                                {seriesKeys.map((key) => {
                                    const value = item.values[key] ?? 0;
                                    const pct = Math.round((value / max) * 100);
                                    if (value <= 0) return null;
                                    return (
                                        <div
                                            key={key}
                                            className={`${colors[key] ?? 'bg-primary-600'} w-full`}
                                            style={{ height: `${pct}%` }}
                                        />
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="flex h-full w-full items-end gap-1">
                                {seriesKeys.map((key) => {
                                    const value = item.values[key] ?? 0;
                                    const pct = Math.max(2, Math.round((value / max) * 100));
                                    return (
                                        <div
                                            key={key}
                                            className={`${colors[key] ?? 'bg-primary-600'} flex-1 rounded-t-sm`}
                                            style={{ height: `${pct}%` }}
                                        />
                                    );
                                })}
                            </div>
                        )}
                        <p className="text-body-xs text-txt-black-500">{item.label}</p>
                    </div>
                ))}
            </div>

            <div className="mt-3 flex flex-wrap gap-4">
                {seriesKeys.map((key) => (
                    <div key={key} className="flex items-center gap-2 text-body-sm">
                        <span className={`${colors[key] ?? 'bg-primary-600'} h-2.5 w-2.5 flex-shrink-0 rounded-sm`} />
                        <span className="text-txt-black-700">{key}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}
