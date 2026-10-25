'use client';

import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/Badge';
import { KETERUKAN_VARIANT, slaVariant, STATUS_VARIANT, tempoh, type Kod, type Tiket } from '@/lib/tkt';

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

export function StatusTiket({ t }: { t: Pick<Tiket, 'status' | 'status_nama'> }) {
    return <Badge variant={STATUS_VARIANT[t.status] ?? 'neutral'}>{t.status_nama}</Badge>;
}

export function Keterukan({ t }: { t: Pick<Tiket, 'keterukan' | 'keterukan_nama'> }) {
    return <Badge variant={KETERUKAN_VARIANT[t.keterukan] ?? 'neutral'}>{`S${t.keterukan}`}</Badge>;
}

export function SlaBadge({ t }: { t: Pick<Tiket, 'sla' | 'status'> }) {
    const s = t.sla;
    const baki = s.selesai.baki_minit;
    const teks = s.status === 'dalam_tempoh' || s.status === 'hampir_tamat' ? `${s.status_nama} · ${tempoh(baki)}` : s.status_nama;
    return <Badge variant={slaVariant(s.status)}>{teks}</Badge>;
}

/** Simple horizontal bar list (no chart dependency). */
export function Bar({ rows, href }: { rows: Array<{ kod: string; label: string; nilai: number }>; href?: (kod: string) => string }) {
    const max = Math.max(1, ...rows.map((r) => r.nilai));
    if (rows.length === 0) return <Kosong>Tiada data.</Kosong>;
    return (
        <ul className="space-y-2">
            {rows.map((r) => (
                <li key={r.kod} className="min-w-0">
                    <div className="flex justify-between gap-2 text-body-xs text-txt-black-700">
                        {href ? (
                            <a href={href(r.kod)} className="truncate text-txt-primary hover:underline">
                                {r.label}
                            </a>
                        ) : (
                            <span className="truncate">{r.label}</span>
                        )}
                        <span className="font-medium">{r.nilai}</span>
                    </div>
                    <div className="mt-1 h-2 rounded bg-bg-washed">
                        <div className="h-2 rounded bg-primary-600" style={{ width: `${(100 * r.nilai) / max}%` }} />
                    </div>
                </li>
            ))}
        </ul>
    );
}
