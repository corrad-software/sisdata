'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { Breadcrumb } from '@/components/Breadcrumb';
import { useCan } from '@/lib/hkm';

/** MOD-HKM section: page header + full-width section nav (same pattern as MOD-WFE). */
const SECTIONS: Array<{ key: string; href: string; label: string; perms: string[] }> = [
    { key: 'hak-milik', href: '/admin/hkm/hak-milik', label: 'Hak Milik', perms: ['hkm.hak_milik.lihat'] },
    { key: 'peti-besi', href: '/admin/hkm/peti-besi', label: 'Peti Besi', perms: ['hkm.peti_besi.lihat'] },
    { key: 'pengeluaran', href: '/admin/hkm/peti-besi/pengeluaran', label: 'Pengeluaran Dokumen', perms: ['hkm.peti_besi.lihat', 'hkm.pemantauan.lihat'] },
    { key: 'permohonan', href: '/admin/hkm/permohonan', label: 'Hak Milik Kekal', perms: ['hkm.permohonan.lihat'] },
    { key: 'perubahan', href: '/admin/hkm/perubahan', label: 'Perubahan', perms: ['hkm.hak_milik.lihat'] },
    { key: 'cadangan', href: '/admin/hkm/cadangan', label: 'Cadangan Luaran', perms: ['hkm.cadangan.lihat'] },
    { key: 'laporan', href: '/admin/hkm/laporan', label: 'Laporan', perms: ['hkm.hak_milik.lihat'] },
];

export default function HkmLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const can = useCan();
    const parts = pathname.split('/');
    const section = parts[3] === 'peti-besi' && parts[4] === 'pengeluaran' ? 'pengeluaran' : (parts[3] ?? 'hak-milik');
    const current = SECTIONS.find((s) => s.key === section);
    const label = current?.label ?? 'Hak Milik Tanah';
    const visible = SECTIONS.filter((s) => can(...s.perms) || s.key === section);

    return (
        <>
            <div className="bg-bg-white border-b border-otl-divider px-4 sm:px-6 py-4 space-y-3">
                <Breadcrumb items={[{ label: 'Hak Milik Tanah', href: '/admin/hkm/hak-milik' }, { label }]} />
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">{label}</h2>
            </div>

            <div className="bg-bg-white border-b border-otl-divider">
                <nav aria-label="Navigasi hak milik" className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto">
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
