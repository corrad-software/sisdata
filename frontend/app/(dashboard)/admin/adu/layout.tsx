'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { Breadcrumb } from '@/components/Breadcrumb';
import { useCan } from '@/lib/adu';

/** MOD-ADU section: page header + full-width section nav (same pattern as MOD-HKM). */
const SECTIONS: Array<{ key: string; href: string; label: string; perms: string[] }> = [
    { key: 'dashboard', href: '/admin/adu', label: 'Papan Pemuka', perms: ['adu.laporan.lihat'] },
    { key: 'aduan', href: '/admin/adu/aduan', label: 'Barisan Aduan', perms: ['adu.aduan.lihat'] },
    { key: 'baharu', href: '/admin/adu/aduan/baharu', label: 'Rekod Aduan', perms: ['adu.aduan.cipta'] },
    { key: 'laporan', href: '/admin/adu/laporan', label: 'Laporan & Analitik', perms: ['adu.laporan.lihat'] },
    { key: 'konfigurasi', href: '/admin/adu/konfigurasi', label: 'Konfigurasi', perms: ['adu.konfigurasi.lihat'] },
];

export default function AduLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const can = useCan();
    const parts = pathname.split('/');
    const section = parts[3] === undefined ? 'dashboard' : parts[3] === 'aduan' && parts[4] === 'baharu' ? 'baharu' : parts[3];
    const current = SECTIONS.find((s) => s.key === section);
    const label = current?.label ?? 'Aduan dan Isu Tanah';
    const visible = SECTIONS.filter((s) => can(...s.perms) || s.key === section);

    return (
        <>
            <div className="bg-bg-white border-b border-otl-divider px-4 sm:px-6 py-4 space-y-3">
                <Breadcrumb items={[{ label: 'Aduan dan Isu Tanah', href: '/admin/adu/aduan' }, { label }]} />
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">{label}</h2>
            </div>

            <div className="bg-bg-white border-b border-otl-divider">
                <nav data-panduan="modul-nav" aria-label="Navigasi aduan" className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto">
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
