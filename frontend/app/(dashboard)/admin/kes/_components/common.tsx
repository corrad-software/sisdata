'use client';

import type { ReactNode } from 'react';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { perluMfa, ralat, STATUS_VARIANT, type Kod } from '@/lib/kes';

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

export function Select({ id, value, onChange, options, empty, required }: { id: string; value: string; onChange: (v: string) => void; options: Kod[]; empty?: string; required?: boolean }) {
    return (
        <select id={id} className={inputClass} value={value} onChange={(e) => onChange(e.target.value)} required={required}>
            {empty !== undefined && <option value="">{empty}</option>}
            {options.map((o) => (
                <option key={o.kod} value={o.kod}>
                    {o.nama}
                </option>
            ))}
        </select>
    );
}

export function InfoGrid({ items }: { items: Array<[string, ReactNode]> }) {
    return (
        <dl className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
            {items.map(([label, value]) => (
                <div key={label} className="min-w-0">
                    <dt className="text-body-xs font-medium text-txt-black-500">{label}</dt>
                    <dd className="mt-0.5 text-body-sm text-txt-black-900 break-words">{value ?? '—'}</dd>
                </div>
            ))}
        </dl>
    );
}

export function ModalBody({ title, children, footer }: { title: string; children: ReactNode; footer: ReactNode }) {
    return (
        <div className="flex max-h-[90vh] flex-col">
            <div className="border-b border-otl-divider px-5 py-4">
                <h3 className="font-heading font-semibold text-body-lg text-txt-black-900">{title}</h3>
            </div>
            <div className="space-y-4 overflow-y-auto px-5 py-4">{children}</div>
            <div className="flex flex-wrap justify-end gap-2 border-t border-otl-divider px-5 py-3">{footer}</div>
        </div>
    );
}

export function Kosong({ children }: { children: ReactNode }) {
    return <p className="text-body-sm text-txt-black-500 py-4 text-center">{children}</p>;
}

export function StatusKes({ k }: { k: { status: string; status_nama: string | null; status_arkib?: boolean } }) {
    return (
        <span className="inline-flex flex-wrap gap-1">
            <Badge variant={STATUS_VARIANT[k.status] ?? 'neutral'}>{k.status_nama ?? k.status}</Badge>
            {k.status_arkib && <Badge variant="neutral">Diarkib</Badge>}
        </span>
    );
}

/** Error panel; a password session gets the SSO (MFA) prompt instead of a bare 403. */
export function RalatAkses({ error }: { error: unknown }) {
    if (perluMfa(error)) {
        return (
            <Alert type="warning">
                <strong>Pengesahan MFA diperlukan.</strong> Rekod dan dokumen kes mahkamah berklasifikasi Rahsia memerlukan log masuk melalui SSO MAIS (MFA).{' '}
                <a href="/auth/oidc/redirect" className="font-medium underline">
                    Log masuk semula melalui SSO MAIS
                </a>
                .
            </Alert>
        );
    }
    return (
        <Alert type="error">
            {ralat(error)}
        </Alert>
    );
}
