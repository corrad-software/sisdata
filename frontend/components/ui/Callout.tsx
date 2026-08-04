import type { ReactNode } from 'react';

type CalloutType = 'success' | 'danger' | 'warning' | 'info';

const variants: Record<CalloutType, { bg: string; border: string; icon: string }> = {
    success: { bg: 'bg-bg-success-50', border: 'border-otl-success-200', icon: 'text-txt-success' },
    danger: { bg: 'bg-bg-danger-50', border: 'border-otl-danger-200', icon: 'text-txt-danger' },
    warning: { bg: 'bg-bg-warning-50', border: 'border-otl-warning-200', icon: 'text-txt-warning' },
    info: { bg: 'bg-bg-primary-50', border: 'border-otl-primary-200', icon: 'text-txt-primary' },
};

export function Callout({
    type = 'info',
    title,
    children,
    className,
}: {
    type?: CalloutType;
    title?: string;
    children: ReactNode;
    className?: string;
}) {
    const v = variants[type];

    return (
        <div className={`rounded-lg border ${v.border} ${v.bg} p-4 ${className ?? ''}`}>
            <div className="flex gap-3">
                <svg className={`h-5 w-5 flex-shrink-0 ${v.icon}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"
                    />
                </svg>
                <div className="space-y-1">
                    {title && <p className={`text-body-sm font-semibold ${v.icon}`}>{title}</p>}
                    <div className="text-body-sm text-txt-black-700">{children}</div>
                </div>
            </div>
        </div>
    );
}
