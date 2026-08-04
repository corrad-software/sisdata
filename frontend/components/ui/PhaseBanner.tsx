import type { ReactNode } from 'react';

export function PhaseBanner({ phase = 'Beta', children }: { phase?: string; children: ReactNode }) {
    return (
        <div className="flex items-center gap-3 border-b border-otl-divider py-2">
            <span className="inline-flex flex-shrink-0 items-center rounded-md bg-primary-600 px-2 py-0.5 text-body-xs font-semibold uppercase text-white">
                {phase}
            </span>
            <p className="text-body-sm text-txt-black-700">{children}</p>
        </div>
    );
}
