'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { Breadcrumb } from '@/components/Breadcrumb';
import { useCan } from '@/lib/tsu';

/** MOD-TSU section: page header + full-width section nav (same pattern as MOD-ADU / MOD-PTN). */
const SECTIONS: Array<{ key: string; href: string; label: string; perms: string[] }> = [
    { key: 'dashboard', href: '/admin/tsu', label: 'Papan Pemuka', perms: ['tsu.laporan.lihat'] },
    { key: 'urusan', href: '/admin/tsu/urusan', label: 'Urusan Tanah', perms: ['tsu.urusan.lihat'] },
    { key: 'baharu', href: '/admin/tsu/urusan/baharu', label: 'Daftar Urusan', perms: ['tsu.urusan.cipta'] },
    { key: 'panel', href: '/admin/tsu/panel', label: 'Panel Jurukur', perms: ['tsu.panel.lihat'] },
    { key: 'laporan', href: '/admin/tsu/laporan', label: 'Laporan', perms: ['tsu.laporan.lihat'] },
    { key: 'templat', href: '/admin/tsu/templat', label: 'Templat Proses', perms: ['tsu.templat.lihat'] },
    { key: 'jurukur', href: '/admin/tsu/jurukur', label: 'Lantikan Saya (Jurukur)', perms: ['tsu.jurukur.kemas_kini'] },
];

export default function TsuLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const can = useCan();
    const parts = pathname.split('/');
    const section = parts[3] === undefined ? 'dashboard' : parts[3] === 'urusan' && parts[4] === 'baharu' ? 'baharu' : parts[3];
    const current = SECTIONS.find((s) => s.key === section);
    const label = current?.label ?? 'Tukar Syarat, Pelupusan dan Ukur';
    const visible = SECTIONS.filter((s) => can(...s.perms) || s.key === section);

    return (
        <>
            <div className="bg-bg-white border-b border-otl-divider px-4 sm:px-6 py-4 space-y-3">
                <Breadcrumb items={[{ label: 'Tukar Syarat, Pelupusan dan Ukur', href: '/admin/tsu/urusan' }, { label }]} />
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">{label}</h2>
            </div>

            <div className="bg-bg-white border-b border-otl-divider">
                <nav data-panduan="modul-nav" aria-label="Navigasi urusan tanah" className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto">
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
