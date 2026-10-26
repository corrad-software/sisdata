type TaskStatus = 'completed' | 'in_progress' | 'not_started';

interface TaskListItem {
    title: string;
    status?: TaskStatus;
    url?: string;
}

const STATUS_STYLES: Record<TaskStatus, { bg: string; text: string; label: string }> = {
    completed: { bg: 'bg-bg-success-50', text: 'text-txt-success', label: 'Selesai' },
    in_progress: { bg: 'bg-bg-warning-50', text: 'text-txt-warning', label: 'Dalam Proses' },
    not_started: { bg: 'bg-bg-washed', text: 'text-txt-black-500', label: 'Belum Bermula' },
};

export function TaskList({ items, className }: { items: TaskListItem[]; className?: string }) {
    return (
        <ol className={`divide-y divide-otl-gray-200 rounded-md border border-otl-gray-200 bg-bg-white ${className ?? ''}`}>
            {items.map((item, index) => {
                const status = STATUS_STYLES[item.status ?? 'not_started'];

                return (
                    <li key={item.title} className="flex items-center justify-between gap-4 px-4 py-3">
                        <div className="flex items-center gap-3">
                            <span className="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-otl-gray-300 text-body-xs font-medium text-txt-black-500">
                                {index + 1}
                            </span>
                            <a
                                href={item.url ?? '#'}
                                className="text-body-sm font-medium text-primary-600 hover:text-primary-700 hover:underline"
                            >
                                {item.title}
                            </a>
                        </div>
                        <span className={`inline-flex items-center rounded-full ${status.bg} px-2.5 py-0.5 text-body-xs font-medium ${status.text}`}>
                            {status.label}
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}
