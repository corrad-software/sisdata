export function GaugeChart({
    value,
    min = 0,
    max = 100,
    size = 200,
    strokeWidth = 20,
    label,
    color = 'text-primary-600',
    className,
}: {
    value: number;
    min?: number;
    max?: number;
    size?: number;
    strokeWidth?: number;
    label?: string;
    color?: string;
    className?: string;
}) {
    const radius = (size - strokeWidth) / 2;
    const circumference = Math.PI * radius;
    const fraction = Math.max(0, Math.min(1, (value - min) / ((max - min) || 1)));
    const dash = fraction * circumference;
    const viewHeight = size / 2 + strokeWidth / 2;

    const arcPath = `M ${strokeWidth / 2} ${size / 2} A ${radius} ${radius} 0 0 1 ${size - strokeWidth / 2} ${size / 2}`;

    return (
        <div className={`inline-flex flex-col items-center ${className ?? ''}`}>
            <svg width={size} height={viewHeight} viewBox={`0 0 ${size} ${viewHeight}`}>
                <path d={arcPath} fill="none" className="stroke-otl-gray-200" strokeWidth={strokeWidth} strokeLinecap="round" />
                <path
                    d={arcPath}
                    fill="none"
                    stroke="currentColor"
                    className={color}
                    strokeWidth={strokeWidth}
                    strokeDasharray={`${Math.round(dash * 100) / 100} ${Math.round((circumference - dash) * 100) / 100}`}
                    strokeLinecap="round"
                />
            </svg>
            <p className="-mt-2 font-heading text-heading-xs font-semibold text-txt-black-900">{value}</p>
            {label && <p className="text-body-sm text-txt-black-500">{label}</p>}
        </div>
    );
}
