'use client';

import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/Badge';
import { STATUS_ITEM_VARIANT, TAHAP_VARIANT, WARNA_HEX, WARNA_LABEL, WARNA_VARIANT, type Kod, type KodWarna } from '@/lib/ptn';

/** Tailwind classes for the chart components (they colour with currentColor / bg classes). */
export const WARNA_TEKS: Record<KodWarna, string> = { hijau: 'text-success-600', kuning: 'text-warning-600', merah: 'text-danger-600' };
export const WARNA_BG: Record<KodWarna, string> = { hijau: 'bg-success-600', kuning: 'bg-warning-600', merah: 'bg-danger-600' };

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

export function Select({ id, value, onChange, options, empty }: { id: string; value: string; onChange: (v: string) => void; options: Kod[]; empty?: string }) {
    return (
        <select id={id} className={inputClass} value={value} onChange={(e) => onChange(e.target.value)}>
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

/** SRS-PTN-16: colour always with a text label and a shape cue (●) — never colour alone. */
export function KodWarnaBadge({ warna, label }: { warna: KodWarna; label?: string }) {
    return (
        <Badge variant={WARNA_VARIANT[warna]}>
            <span aria-hidden="true" className="mr-1" style={{ color: WARNA_HEX[warna] }}>
                ●
            </span>
            {label ?? WARNA_LABEL[warna]}
        </Badge>
    );
}

export function Legenda() {
    return (
        <ul className="flex flex-wrap gap-2" aria-label="Legenda kod warna">
            {(['hijau', 'kuning', 'merah'] as KodWarna[]).map((w) => (
                <li key={w}>
                    <KodWarnaBadge warna={w} />
                </li>
            ))}
        </ul>
    );
}

export function StatusItem({ status, label }: { status: string | null; label?: string }) {
    if (!status) return <span className="text-txt-black-500">—</span>;
    return <Badge variant={STATUS_ITEM_VARIANT[status] ?? 'neutral'}>{label ?? { baik: 'Baik', isu: 'Isu', kritikal: 'Kritikal' }[status] ?? status}</Badge>;
}

export function Tahap({ tahap, label }: { tahap: string; label?: string }) {
    return <Badge variant={TAHAP_VARIANT[tahap] ?? 'neutral'}>{label ?? tahap}</Badge>;
}

export function Penapis({ children }: { children: ReactNode }) {
    return (
        <Card>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">{children}</div>
        </Card>
    );
}
