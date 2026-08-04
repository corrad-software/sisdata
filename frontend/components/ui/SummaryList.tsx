interface SummaryListItem {
    label: string;
    value: string;
    action?: { label?: string; url?: string };
}

export function SummaryList({ items, className }: { items: SummaryListItem[]; className?: string }) {
    return (
        <dl className={`divide-y divide-otl-gray-200 rounded-md border border-otl-gray-200 bg-bg-white ${className ?? ''}`}>
            {items.map((item) => (
                <div key={item.label} className="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:gap-4">
                    <dt className="text-body-sm font-medium text-txt-black-500 sm:w-40 sm:flex-shrink-0">{item.label}</dt>
                    <dd className="flex-1 text-body-sm text-txt-black-900">{item.value}</dd>
                    {item.action && (
                        <dd>
                            <a
                                href={item.action.url ?? '#'}
                                className="text-body-sm font-medium text-primary-600 hover:text-primary-700"
                            >
                                {item.action.label ?? 'Change'}
                            </a>
                        </dd>
                    )}
                </div>
            ))}
        </dl>
    );
}
