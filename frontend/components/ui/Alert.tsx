'use client';

import { useState } from 'react';
import type { ReactNode } from 'react';

type AlertType = 'success' | 'error' | 'warning' | 'info';

const variants: Record<AlertType, { bg: string; border: string; text: string }> = {
    success: { bg: 'bg-bg-success-50', border: 'border-otl-success-200', text: 'text-txt-success' },
    error: { bg: 'bg-bg-danger-50', border: 'border-otl-danger-200', text: 'text-txt-danger' },
    warning: { bg: 'bg-bg-warning-50', border: 'border-otl-warning-200', text: 'text-txt-warning' },
    info: { bg: 'bg-bg-primary-50', border: 'border-otl-primary-200', text: 'text-txt-primary' },
};

export function Alert({
    type = 'info',
    dismissible = false,
    children,
    className,
}: {
    type?: AlertType;
    dismissible?: boolean;
    children: ReactNode;
    className?: string;
}) {
    const [show, setShow] = useState(true);
    const v = variants[type];

    if (!show) return null;

    return (
        <div
            className={['flex items-start gap-3 rounded-md border px-4 py-3 text-body-sm', v.bg, v.border, v.text, className]
                .filter(Boolean)
                .join(' ')}
        >
            <svg className={`h-5 w-5 flex-shrink-0 ${v.text}`} fill="currentColor" viewBox="0 0 20 20">
                <path
                    fillRule="evenodd"
                    clipRule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                />
            </svg>
            <div className="flex-1">{children}</div>
            {dismissible && (
                <button type="button" onClick={() => setShow(false)} className={`flex-shrink-0 ${v.text} hover:opacity-70`}>
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            )}
        </div>
    );
}
