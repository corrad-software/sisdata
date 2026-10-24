'use client';

import { useState } from 'react';
import type { ReactNode, SelectHTMLAttributes } from 'react';
import { Badge } from '@/components/ui/Badge';
import { STATUS_LABEL, STATUS_VARIANT } from '@/lib/rbc';
import type { ScopeMap } from '@/lib/rbc';
import type { AccountStatus } from '@/lib/types';

export function Card({ title, actions, children, className = '' }: { title?: ReactNode; actions?: ReactNode; children: ReactNode; className?: string }) {
    return (
        <section className={`bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-4 sm:p-6 ${className}`}>
            {(title || actions) && (
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    {title && <h3 className="font-heading font-semibold text-body-lg text-txt-black-900">{title}</h3>}
                    {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
                </div>
            )}
            {children}
        </section>
    );
}

export function StatusBadge({ status }: { status: AccountStatus }) {
    return <Badge variant={STATUS_VARIANT[status]}>{STATUS_LABEL[status]}</Badge>;
}

export function Select({ label, id, children, className = '', ...props }: SelectHTMLAttributes<HTMLSelectElement> & { label?: string; id: string }) {
    return (
        <div className={className}>
            {label && (
                <label htmlFor={id} className="block mb-1.5 text-body-sm font-medium text-txt-black-700">
                    {label}
                </label>
            )}
            <select
                id={id}
                {...props}
                className="block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm"
            >
                {children}
            </select>
        </div>
    );
}

export function TextInput({ label, id, value, onChange, placeholder, type = 'text', className = '' }: { label: string; id: string; value: string; onChange: (v: string) => void; placeholder?: string; type?: string; className?: string }) {
    return (
        <div className={className}>
            <label htmlFor={id} className="block mb-1.5 text-body-sm font-medium text-txt-black-700">
                {label}
            </label>
            <input
                id={id}
                type={type}
                value={value}
                placeholder={placeholder}
                onChange={(e) => onChange(e.target.value)}
                className="block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm"
            />
        </div>
    );
}

export function EmptyRow({ colSpan, children = 'Tiada rekod.' }: { colSpan: number; children?: ReactNode }) {
    return (
        <tr>
            <td colSpan={colSpan} className="text-center text-txt-black-500 py-6">
                {children}
            </td>
        </tr>
    );
}

/** Horizontal scroll stays inside the card (no page scroll at 375px). */
export function ScrollX({ children }: { children: ReactNode }) {
    return <div className="relative -mx-4 sm:mx-0 overflow-x-auto">{children}</div>;
}

function toText(scope: ScopeMap, type: string): string {
    return (scope[type] ?? []).join(', ');
}

/**
 * Scope editor (SRS-RBC-07): one comma-separated list per scope type.
 * Empty = unrestricted on that dimension.
 */
export function ScopeEditor({ types, value, onSave, saving }: { types: Record<string, string>; value: ScopeMap; onSave: (scope: ScopeMap) => void; saving?: boolean }) {
    const [draft, setDraft] = useState<Record<string, string>>(() => Object.fromEntries(Object.keys(types).map((t) => [t, toText(value, t)])));

    function save() {
        const scope: ScopeMap = {};
        for (const [type, text] of Object.entries(draft)) {
            const values = text.split(',').map((v) => v.trim()).filter(Boolean);
            if (values.length) scope[type] = values;
        }
        onSave(scope);
    }

    return (
        <div className="space-y-4">
            <p className="text-body-sm text-txt-black-500">Biarkan kosong untuk tiada had. Pisahkan beberapa nilai dengan koma (cth. petaling, klang).</p>
            <div className="grid gap-4 sm:grid-cols-2">
                {Object.entries(types).map(([type, label]) => (
                    <TextInput key={type} id={`skop-${type}`} label={label} value={draft[type] ?? ''} onChange={(v) => setDraft((d) => ({ ...d, [type]: v }))} />
                ))}
            </div>
            <div className="flex justify-end">
                <button
                    type="button"
                    onClick={save}
                    disabled={saving}
                    className="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-body-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                >
                    Simpan Skop
                </button>
            </div>
        </div>
    );
}
