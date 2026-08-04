interface HeatmapRow {
    label: string;
    cells: number[];
}

const DEFAULT_COLOR_SCALE = ['bg-bg-washed', 'bg-primary-200', 'bg-primary-400', 'bg-primary-600', 'bg-primary-800'];

export function Heatmap({
    data,
    colLabels = [],
    max: maxProp,
    colorScale = DEFAULT_COLOR_SCALE,
    className,
}: {
    data: HeatmapRow[];
    colLabels?: string[];
    max?: number;
    colorScale?: string[];
    className?: string;
}) {
    const allCells = data.flatMap((row) => row.cells);
    const max = maxProp ?? Math.max(...allCells, 1);
    const steps = colorScale.length;

    function colorFor(value: number) {
        if (value <= 0) return colorScale[0];
        const index = Math.min(steps - 1, Math.ceil((value / max) * (steps - 1)));
        return colorScale[index];
    }

    return (
        <div className={className}>
            <div className="inline-block">
                {colLabels.length > 0 && (
                    <div className="mb-1 flex gap-1 ps-14">
                        {colLabels.map((label, i) => (
                            <span key={i} className="w-5 flex-shrink-0 text-center text-[10px] text-txt-black-500">
                                {label}
                            </span>
                        ))}
                    </div>
                )}

                {data.map((row) => (
                    <div key={row.label} className="mb-1 flex items-center gap-1">
                        <span className="w-12 flex-shrink-0 text-body-xs text-txt-black-500">{row.label}</span>
                        {row.cells.map((value, i) => (
                            <span key={i} title={String(value)} className={`${colorFor(value)} h-5 w-5 flex-shrink-0 rounded-sm`} />
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
}
