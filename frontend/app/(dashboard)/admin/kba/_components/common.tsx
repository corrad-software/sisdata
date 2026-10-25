'use client';

import Link from 'next/link';
import type { ReactNode } from 'react';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { ApiError } from '@/lib/api';
import { KLASIFIKASI_LABEL, pecahPetikan, type Klasifikasi, type Petikan } from '@/lib/kba';

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
    return <p className="text-body-sm text-txt-black-500 py-4 text-center">{children}</p>;
}

export function Memuat() {
    return <p className="text-body-sm text-txt-black-500">Memuatkan…</p>;
}

export function ralat(e: unknown): string {
    if (e instanceof ApiError) {
        if (e.status === 403) return 'Anda tiada kebenaran untuk fungsi ini.';
        if (e.status === 404) return 'Rekod tidak ditemui atau anda tiada kebenaran untuk membukanya.';
        const first = e.errors ? Object.values(e.errors)[0]?.[0] : undefined;
        return first ?? e.message;
    }
    return 'Ralat tidak dijangka. Sila cuba lagi.';
}

export function RalatAkses({ error }: { error: unknown }) {
    return <Alert type="error">{ralat(error)}</Alert>;
}

const KLAS_VARIANT: Record<Klasifikasi, 'success' | 'info' | 'warning' | 'danger'> = { awam: 'success', dalaman: 'info', sulit: 'warning', rahsia: 'danger' };

export function KlasifikasiBadge({ k }: { k: Klasifikasi }) {
    return (
        <Badge variant={KLAS_VARIANT[k] ?? 'neutral'} shape="tag">
            {KLASIFIKASI_LABEL[k] ?? k}
        </Badge>
    );
}

/** Answer text with [n] markers turned into links to the cited source section (SRS-KBA-04). */
export function TeksBerpetikan({ teks, sumber }: { teks: string; sumber: Petikan[] }) {
    const byNo = new Map(sumber.map((s) => [s.no, s]));
    return (
        <div className="whitespace-pre-wrap break-words text-body-sm text-txt-black-900 leading-relaxed">
            {pecahPetikan(teks).map((p, i) => {
                if (p.jenis === 'teks') return <span key={i}>{p.nilai}</span>;
                const s = byNo.get(p.no);
                return s ? (
                    <Link
                        key={i}
                        href={s.pautan}
                        className="mx-0.5 inline-flex items-center rounded bg-bg-primary-50 px-1.5 text-body-xs font-semibold text-txt-primary hover:underline"
                        title={`${s.dokumen} ${s.versi}${s.seksyen ? ' · ' + s.seksyen : ''}`}
                    >
                        [{p.no}]
                    </Link>
                ) : (
                    <span key={i}>[{p.no}]</span>
                );
            })}
        </div>
    );
}

/** Clickable citation list: document, version, section (SRS-KBA-04). */
export function SenaraiPetikan({ sumber }: { sumber: Petikan[] }) {
    if (!sumber.length) return null;
    return (
        <ol className="space-y-2">
            {sumber.map((s) => (
                <li key={`${s.no}-${s.id_segmen}`} className="flex gap-2 text-body-sm">
                    <span className="mt-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded bg-bg-primary-50 px-1 text-body-xs font-semibold text-txt-primary">{s.no}</span>
                    <div className="min-w-0">
                        <Link href={s.pautan} className="font-medium text-txt-primary hover:underline break-words">
                            {s.dokumen}
                        </Link>
                        <span className="text-txt-black-500">
                            {' '}
                            · {s.versi}
                            {s.seksyen ? ` · ${s.seksyen}` : ''}
                        </span>
                        {s.klasifikasi && s.klasifikasi !== 'dalaman' && (
                            <span className="ml-2">
                                <KlasifikasiBadge k={s.klasifikasi} />
                            </span>
                        )}
                        {s.petikan && <p className="mt-0.5 text-body-xs text-txt-black-500 break-words">“{s.petikan}”</p>}
                    </div>
                </li>
            ))}
        </ol>
    );
}
