'use client';

import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/Badge';
import { warnaBahagian, warnaJenisHakMilik, warnaKategori, senaraiLegenda, WARNA_JENIS_HAK_MILIK, WARNA_KATEGORI_GUNA_TANAH, type WarnaLegenda } from '@/lib/legend';
import type { Bendera, Kod } from '@/lib/hkm';

export const inputClass =
    'block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm';

export function Card({ title, actions, children, id }: { title?: ReactNode; actions?: ReactNode; children: ReactNode; id?: string }) {
    return (
        <section id={id} className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-4 sm:p-6 space-y-4">
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

/** Label + control wrapper used by every HKM form. */
export function Field({ label, htmlFor, children, help, className }: { label: string; htmlFor: string; children: ReactNode; help?: string; className?: string }) {
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

/** Definition list in a responsive grid (no horizontal scroll on phones). */
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

function Chip({ w, children, title }: { w: WarnaLegenda; children: ReactNode; title?: string }) {
    return (
        <span
            title={title}
            className="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-body-xs font-medium whitespace-nowrap"
            style={{ backgroundColor: w.latar, color: w.teks }}
        >
            <span aria-hidden className="inline-block h-2 w-2 rounded-full" style={{ backgroundColor: w.warna }} />
            {children}
        </span>
    );
}

/** SRS-HKM-20: title-type chip in the shared legend colour. */
export function JenisChip({ kod, nama }: { kod: string; nama?: string | null }) {
    const w = warnaJenisHakMilik(kod);
    return (
        <Chip w={w} title={nama ?? w.label}>
            {kod}
        </Chip>
    );
}

export function KategoriChip({ kod, nama }: { kod: string | null; nama?: string | null }) {
    if (!kod) return <span className="text-txt-black-500">—</span>;
    return <Chip w={warnaKategori(kod)}>{nama ?? warnaKategori(kod).label}</Chip>;
}

export function BahagianChip({ kod }: { kod: string | null }) {
    if (!kod) return null;
    return <Chip w={warnaBahagian(kod)}>{warnaBahagian(kod).label}</Chip>;
}

export function Legenda() {
    return (
        <details className="text-body-xs text-txt-black-700">
            <summary className="cursor-pointer select-none text-txt-primary">Legenda warna</summary>
            <div className="mt-2 grid gap-2 sm:grid-cols-2">
                <div className="flex flex-wrap gap-1.5">
                    {senaraiLegenda(WARNA_JENIS_HAK_MILIK).map((w) => (
                        <Chip key={w.kod} w={w}>
                            {w.kod} · {w.label}
                        </Chip>
                    ))}
                </div>
                <div className="flex flex-wrap gap-1.5">
                    {senaraiLegenda(WARNA_KATEGORI_GUNA_TANAH).map((w) => (
                        <Chip key={w.kod} w={w}>
                            {w.label}
                        </Chip>
                    ))}
                </div>
            </div>
        </details>
    );
}

const flagVariant = { danger: 'danger', warning: 'warning', info: 'info' } as const;

export function BenderaList({ bendera, compact }: { bendera: Bendera[]; compact?: boolean }) {
    if (bendera.length === 0) return compact ? null : <span className="text-body-xs text-txt-black-500">Tiada bendera</span>;
    return (
        <div className="flex flex-wrap gap-1.5">
            {bendera.map((b, i) => (
                <Badge key={`${b.kod}-${i}`} variant={flagVariant[b.level]}>
                    {b.label}
                </Badge>
            ))}
        </div>
    );
}

const dokumenVariant: Record<string, 'success' | 'warning' | 'danger' | 'neutral' | 'info'> = {
    dalam_simpanan: 'success',
    dikeluarkan: 'warning',
    dalam_proses_pt: 'info',
    dipegang_pihak_ketiga: 'warning',
    hilang: 'danger',
    diganti: 'neutral',
};

export function StatusDokumen({ kod, nama }: { kod: string; nama: string }) {
    return <Badge variant={dokumenVariant[kod] ?? 'neutral'}>{nama}</Badge>;
}

export function Kosong({ children }: { children: ReactNode }) {
    return <p className="text-body-sm text-txt-black-500 py-4 text-center">{children}</p>;
}

/** Modal body with a title and a scrollable form area. */
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
