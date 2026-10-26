'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { Breadcrumb } from '@/components/Breadcrumb';
import { usePerCan } from '@/lib/per';

/** MOD-PER section: page header + full-width section nav (Kitchen Sink pattern). */
const SECTIONS: Array<{ key: string; href: string; label: string; perms: string[] }> = [
    { key: '', href: '/admin/per', label: 'Senarai Kes', perms: ['per.kes.lihat'] },
    { key: 'baharu', href: '/admin/per/baharu', label: 'Daftar Kes Baharu', perms: ['per.kes.cipta'] },
    { key: 'laporan', href: '/admin/per/laporan', label: 'Laporan & Statistik', perms: ['per.laporan.lihat'] },
    { key: 'saluran', href: '/admin/per/saluran', label: 'Saluran & Peringkat', perms: ['per.kes.lihat', 'per.saluran.kemas_kini'] },
];

export default function PerLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const can = usePerCan();
    const section = pathname.split('/')[3] ?? '';
    const current = SECTIONS.find((s) => s.key === section);
    const label = current?.label ?? (section === 'kes' ? 'Butiran Kes' : 'Rekod Pemerolehan');
    const visible = SECTIONS.filter((s) => can(...s.perms) || s.key === section);

    return (
        <>
            <div className="bg-bg-white border-b border-otl-divider px-4 sm:px-6 py-4 space-y-3">
                <Breadcrumb items={[{ label: 'Rekod Pemerolehan', href: '/admin/per' }, { label }]} />
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">{label}</h2>
            </div>

            <div className="bg-bg-white border-b border-otl-divider">
                <nav data-panduan="modul-nav" aria-label="Navigasi rekod pemerolehan" className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto">
                    {visible.map((s) => (
                        <Link
                            key={s.key}
                            href={s.href}
                            className={`whitespace-nowrap border-b-2 py-3 text-body-sm font-medium ${
                                s.key === section
                                    ? 'border-primary-600 text-primary-600'
                                    : 'border-transparent text-txt-black-500 hover:text-txt-black-900 hover:border-otl-gray-300'
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
