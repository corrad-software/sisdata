'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { Breadcrumb } from '@/components/Breadcrumb';
import { useBoleh } from '@/lib/gis';

/** MOD-GIS section: page header + full-width section nav (Kitchen Sink pattern). */
const SECTIONS: Array<{ key: string; href: string; label: string; perms: string[] }> = [
    { key: '', href: '/admin/gis', label: 'Peta', perms: ['gis.peta.lihat'] },
    { key: 'statistik', href: '/admin/gis/statistik', label: 'Taburan Aset', perms: ['gis.peta.lihat'] },
    { key: 'import', href: '/admin/gis/import', label: 'Import Data Spatial', perms: ['gis.import.lihat', 'gis.import.cipta'] },
    { key: 'kualiti', href: '/admin/gis/kualiti', label: 'Kualiti Spatial', perms: ['gis.kualiti.lihat'] },
    { key: 'lapisan', href: '/admin/gis/lapisan', label: 'Lapisan & Metadata', perms: ['gis.peta.lihat'] },
];

export default function GisLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const can = useBoleh();
    const parts = pathname.split('/');
    const section = parts[3] ?? '';
    const current = SECTIONS.find((s) => s.key === section);
    const title = section === 'lot' ? 'Versi Geometri Lot' : section === 'import' && parts[4] ? 'Laporan Import' : (current?.label === 'Peta' ? 'Peta GIS' : current?.label) ?? 'Peta GIS';
    const visible = SECTIONS.filter((s) => can(...s.perms) || s.key === section);

    return (
        <>
            <div className="bg-bg-white border-b border-otl-divider px-4 sm:px-6 py-4 space-y-3 print:hidden">
                <Breadcrumb items={[{ label: 'GIS & Spatial', href: '/admin/gis' }, { label: title }]} />
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">{title}</h2>
            </div>
            <div className="bg-bg-white border-b border-otl-divider print:hidden">
                <nav aria-label="Navigasi GIS" className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto">
                    {visible.map((s) => (
                        <Link
                            key={s.href}
                            href={s.href}
                            aria-current={s.key === section ? 'page' : undefined}
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
