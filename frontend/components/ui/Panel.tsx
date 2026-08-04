import type { ReactNode } from 'react';

export function Panel({ title, children, className }: { title?: string; children: ReactNode; className?: string }) {
    return (
        <div className={`rounded-lg border border-otl-gray-200 bg-bg-white ${className ?? ''}`}>
            {title && (
                <div className="border-b border-otl-gray-200 px-4 py-3">
                    <h4 className="text-body-sm font-semibold text-txt-black-900">{title}</h4>
                </div>
            )}
            <div className="p-4">{children}</div>
        </div>
    );
}
