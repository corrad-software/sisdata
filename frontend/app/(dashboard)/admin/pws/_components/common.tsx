'use client';

import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/Badge';
import { STATUS_KANDUNGAN_LABEL, STATUS_KANDUNGAN_VARIANT, type StatusKandungan } from '@/lib/pws';

export const inputClass =
    'block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm';

export function Card({ title, actions, children, id }: { title?: ReactNode; actions?: ReactNode; children: ReactNode; id?: string }) {
    return (
        <section id={id} className="min-w-0 space-y-4 border border-otl-gray-200 bg-bg-white p-4 shadow-card sm:rounded-lg sm:p-6">
            {(title || actions) && (
                <div className="flex flex-wrap items-center justify-between gap-3">
                    {title && <h3 className="font-heading text-body-lg font-semibold text-txt-black-900">{title}</h3>}
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
            <label htmlFor={htmlFor} className="mb-1.5 block text-body-sm font-medium text-txt-black-700">
                {label}
            </label>
            {children}
            {help && <p className="mt-1 text-body-xs text-txt-black-500">{help}</p>}
        </div>
    );
}

export function Select({ id, value, onChange, options, empty }: { id: string; value: string; onChange: (v: string) => void; options: { value: string; label: string }[]; empty?: string }) {
    return (
        <select id={id} className={inputClass} value={value} onChange={(e) => onChange(e.target.value)}>
            {empty !== undefined && <option value="">{empty}</option>}
            {options.map((o) => (
                <option key={o.value} value={o.value}>
                    {o.label}
                </option>
            ))}
        </select>
    );
}

export function Kosong({ children }: { children: ReactNode }) {
    return <p className="py-4 text-center text-body-sm text-txt-black-500">{children}</p>;
}

export function Memuat() {
    return <p className="text-body-sm text-txt-black-500">Memuatkan…</p>;
}

export function StatusKandunganBadge({ s }: { s: StatusKandungan }) {
    return <Badge variant={STATUS_KANDUNGAN_VARIANT[s] ?? 'neutral'}>{STATUS_KANDUNGAN_LABEL[s] ?? s}</Badge>;
}

export function AiBadge() {
    return (
        <Badge variant="info" shape="tag">
            Draf AI tempatan
        </Badge>
    );
}
