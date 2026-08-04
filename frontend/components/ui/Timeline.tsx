type TimelineColor = 'success' | 'danger' | 'warning' | 'primary';

interface TimelineItem {
    title: string;
    time: string;
    description?: string;
    color?: TimelineColor;
}

const DOT_CLASSES: Record<TimelineColor, string> = {
    success: 'bg-bg-success-50 text-txt-success',
    danger: 'bg-bg-danger-50 text-txt-danger',
    warning: 'bg-bg-warning-50 text-txt-warning',
    primary: 'bg-bg-primary-50 text-txt-primary',
};

export function Timeline({ items }: { items: TimelineItem[] }) {
    return (
        <ol className="relative border-s border-otl-gray-200 ms-3">
            {items.map((item) => (
                <li key={item.title} className="mb-8 ms-6 last:mb-0">
                    <span
                        className={`absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-bg-white ${DOT_CLASSES[item.color ?? 'primary']}`}
                    >
                        <svg className="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 2a8 8 0 100 16 8 8 0 000-16z" />
                        </svg>
                    </span>

                    <h4 className="text-body-sm font-medium text-txt-black-900">{item.title}</h4>
                    <time className="block text-body-xs text-txt-black-500 mb-1">{item.time}</time>

                    {item.description && <p className="text-body-sm text-txt-black-500">{item.description}</p>}
                </li>
            ))}
        </ol>
    );
}
