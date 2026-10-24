'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { Breadcrumb } from '@/components/Breadcrumb';
import { useCan } from '@/lib/pjk';

/** MOD-PJK section: page header + full-width section nav (same pattern as MOD-HKM). */
const SECTIONS: Array<{ key: string; href: string; label: string; perms: string[] }> = [
    { key: 'ringkasan', href: '/admin/pjk/ringkasan', label: 'Ringkasan', perms: ['pjk.laporan.lihat'] },
    { key: 'kontrak', href: '/admin/pjk/kontrak', label: 'Perjanjian', perms: ['pjk.kontrak.lihat'] },
    { key: 'penyewa', href: '/admin/pjk/penyewa', label: 'Penyewa', perms: ['pjk.penyewa.lihat'] },
    { key: 'premis', href: '/admin/pjk/premis', label: 'Premis & Katalog', perms: ['pjk.premis.lihat'] },
    { key: 'permohonan', href: '/admin/pjk/permohonan', label: 'Permohonan', perms: ['pjk.permohonan.lihat'] },
    { key: 'bayaran', href: '/admin/pjk/bayaran', label: 'Pengesahan Bayaran', perms: ['pjk.bayaran.lulus'] },
    { key: 'rekonsiliasi', href: '/admin/pjk/rekonsiliasi', label: 'Rekonsiliasi', perms: ['pjk.rekonsiliasi.lihat'] },
    { key: 'agihan', href: '/admin/pjk/agihan', label: 'Agihan Manfaat', perms: ['pjk.agihan.lihat'] },
    { key: 'templat', href: '/admin/pjk/templat', label: 'Templat Surat', perms: ['pjk.templat.kemas_kini', 'pjk.surat.cipta'] },
    { key: 'laporan', href: '/admin/pjk/laporan', label: 'Laporan', perms: ['pjk.laporan.lihat'] },
];

export default function PjkLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const can = useCan();
    const section = pathname.split('/')[3] ?? 'kontrak';
    const current = SECTIONS.find((s) => s.key === section);
    const label = current?.label ?? (section === 'gateway-ujian' ? 'Gateway Pembayaran (Ujian)' : 'Pajakan dan Kontrak');
    const visible = SECTIONS.filter((s) => can(...s.perms) || s.key === section);

    return (
        <>
            <div className="bg-bg-white border-b border-otl-divider px-4 sm:px-6 py-4 space-y-3">
                <Breadcrumb items={[{ label: 'Pajakan dan Kontrak', href: '/admin/pjk/kontrak' }, { label }]} />
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">{label}</h2>
            </div>

            <div className="bg-bg-white border-b border-otl-divider">
                <nav aria-label="Navigasi pajakan" className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto">
                    {visible.map((s) => (
                        <Link
                            key={s.key}
                            href={s.href}
                            className={`whitespace-nowrap border-b-2 py-3 text-body-sm font-medium ${
                                s.key === section ? 'border-primary-600 text-primary-600' : 'border-transparent text-txt-black-500 hover:text-txt-black-900 hover:border-otl-gray-300'
                            }`}
                        >
                            {s.label}
                        </Link>
                    ))}
                </nav>
            </div>

            {children}
        </>
    );
}
