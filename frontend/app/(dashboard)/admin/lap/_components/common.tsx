'use client';

import type { ReactNode } from 'react';

export const inputClass =
    'block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm';

export function Card({ title, actions, children, id }: { title?: ReactNode; actions?: ReactNode; children: ReactNode; id?: string }) {
    return (
        <section id={id} className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-4 sm:p-6 space-y-4 min-w-0">
            {(title || actions) && (
                <div className="flex flex-wrap items-center justify-between gap-3">
                    {title && <h3 className="font-heading font-semibold text-body-lg text-txt-black-900">{title}</h3>}
                    {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
                </div>
            )}
            {children}
        </section>
    );
}

export function Field({ label, htmlFor, children, help, className }: { label: string; htmlFor: string; children: ReactNode; help?: ReactNode; className?: string }) {
    return (
        <div className={className}>
            <label htmlFor={htmlFor} className="block mb-1.5 text-body-sm font-medium text-txt-black-700">
                {label}
            </label>
            {children}
            {help && <p className="mt-1 text-body-xs text-txt-black-500">{help}</p>}
        </div>
    );
}

export function Kosong({ children }: { children: ReactNode }) {
    return <p className="text-body-sm text-txt-black-500 py-6 text-center">{children}</p>;
}

/** SRS-LAP-21 label on every analytic / AI output. */
export function LabelCadangan({ teks }: { teks: string | null | undefined }) {
    if (!teks) return null;
    return (
        <p className="rounded-md border border-otl-warning-200 bg-bg-warning-50 px-3 py-2 text-body-xs text-txt-warning" role="note">
            {teks}
        </p>
    );
}
