import type { ReactNode } from 'react';

export function BrowserFrame({ url, children, className }: { url?: string; children: ReactNode; className?: string }) {
    return (
        <div className={`overflow-hidden rounded-lg border border-otl-gray-200 shadow-card ${className ?? ''}`}>
            <div className="flex items-center gap-1.5 border-b border-otl-gray-200 bg-bg-washed px-3 py-2">
                <span className="h-2.5 w-2.5 flex-shrink-0 rounded-full bg-danger-400" />
                <span className="h-2.5 w-2.5 flex-shrink-0 rounded-full bg-warning-400" />
                <span className="h-2.5 w-2.5 flex-shrink-0 rounded-full bg-success-400" />

                {url && (
                    <span className="ms-2 max-w-xs flex-1 truncate rounded bg-bg-white px-2 py-1 text-body-xs text-txt-black-500">
                        {url}
                    </span>
                )}
            </div>

            <div className="max-h-[640px] overflow-y-auto bg-bg-washed">{children}</div>
        </div>
    );
}
