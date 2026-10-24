'use client';

import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/Badge';
import { IKON_SALURAN, legendFor, warnaLatar } from '@/lib/legend-pemerolehan';
import { STATUS_LABEL, STATUS_VARIANT, type StatusKes } from '@/lib/per';

export const inputClass =
    'block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm disabled:bg-bg-washed disabled:text-txt-black-500';

export const cardClass = 'bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-4 sm:p-6';

/*
 * Tailwind classes per channel for the MYDS chart components (they take a
 * colour class, not a hex). Literal strings so Tailwind generates them; the
 * hex values match frontend/lib/legend-pemerolehan.ts and the API (SRS-PER-28).
 */
export const KELAS_TEKS: Record<string, string> = {
    pemberimilikan_5a: 'text-[#2563EB]',
    serah_milik: 'text-[#0891B2]',
    faraid: 'text-[#7C3AED]',
    daftar_kematian: 'text-[#475569]',
    perintah_arb: 'text-[#B45309]',
    serahan_hakmilik: 'text-[#0D9488]',
    perintah_mahkamah: 'text-[#BE123C]',
    pusaka_jkptgs: 'text-[#C2410C]',
    wakaf: 'text-[#15803D]',
    pembelian_terus: 'text-[#A21CAF]',
    tanah_warta: 'text-[#4D7C0F]',
};

export const KELAS_LATAR: Record<string, string> = {
    pemberimilikan_5a: 'bg-[#2563EB]',
    serah_milik: 'bg-[#0891B2]',
    faraid: 'bg-[#7C3AED]',
    daftar_kematian: 'bg-[#475569]',
    perintah_arb: 'bg-[#B45309]',
    serahan_hakmilik: 'bg-[#0D9488]',
    perintah_mahkamah: 'bg-[#BE123C]',
    pusaka_jkptgs: 'bg-[#C2410C]',
    wakaf: 'bg-[#15803D]',
    pembelian_terus: 'bg-[#A21CAF]',
    tanah_warta: 'bg-[#4D7C0F]',
};

export function SaluranIkon({ ikon, className = 'h-5 w-5' }: { ikon: string; className?: string }) {
    const d = IKON_SALURAN[ikon] ?? IKON_SALURAN.dokumen_tanah;
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" d={d} />
        </svg>
    );
}

/** Channel chip in the channel's own colour (SRS-PER-28). */
export function SaluranChip({ kod, nama, warna, ikon, ringkas = false }: { kod: string; nama?: string; warna?: string; ikon?: string; ringkas?: boolean }) {
    const legend = legendFor(kod);
    const colour = warna ?? legend?.warna ?? '#475569';
    const label = ringkas ? (legend?.singkatan ?? nama ?? kod) : (nama ?? legend?.nama ?? kod);
    return (
        <span
            className="inline-flex max-w-full items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-body-xs font-medium"
            style={{ color: colour, borderColor: colour, backgroundColor: warnaLatar(colour) }}
            title={nama ?? legend?.nama}
        >
            <SaluranIkon ikon={ikon ?? legend?.ikon ?? 'dokumen_tanah'} className="h-3.5 w-3.5 flex-shrink-0" />
            <span className="truncate">{label}</span>
        </span>
    );
}

export function StatusBadge({ status }: { status: StatusKes }) {
    return <Badge variant={STATUS_VARIANT[status]}>{STATUS_LABEL[status]}</Badge>;
}

export function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-1 py-2 border-b border-otl-divider last:border-0">
            <dt className="text-body-sm text-txt-black-500">{label}</dt>
            <dd className="sm:col-span-2 text-body-sm text-txt-black-900 break-words">{children}</dd>
        </div>
    );
}

export function SectionTitle({ children, action }: { children: ReactNode; action?: ReactNode }) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 mb-3">
            <h3 className="font-heading font-semibold text-body-lg text-txt-black-900">{children}</h3>
            {action}
        </div>
    );
}

export function ErrorList({ items }: { items: string[] }) {
    if (items.length === 0) return null;
    return (
        <ul className="list-disc pl-5 space-y-0.5 text-body-sm">
            {items.map((i) => (
                <li key={i}>{i}</li>
            ))}
        </ul>
    );
}
