'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { Breadcrumb } from '@/components/Breadcrumb';
import { useCan } from '@/lib/cki';

/** MOD-CKI section: page header + full-width section nav (same pattern as MOD-PJK / MOD-HKM). */
const SECTIONS: Array<{ key: string; href: string; label: string; perms: string[] }> = [
    { key: 'ringkasan', href: '/admin/cki/ringkasan', label: 'Ringkasan', perms: ['cki.laporan.lihat'] },
    { key: 'bil', href: '/admin/cki/bil', label: 'Bil Cukai', perms: ['cki.bil.lihat'] },
    { key: 'obligasi', href: '/admin/cki/obligasi', label: 'Daftar Obligasi', perms: ['cki.obligasi.lihat'] },
    { key: 'jadual', href: '/admin/cki/jadual', label: 'Jadual Bayaran', perms: ['cki.bil.lihat'] },
    { key: 'tugasan', href: '/admin/cki/tugasan', label: 'Rekod Manual', perms: ['cki.bil.cipta'] },
    { key: 'konflik', href: '/admin/cki/konflik', label: 'Konflik Segerak', perms: ['cki.konflik.kemas_kini'] },
    { key: 'integrasi', href: '/admin/cki/integrasi', label: 'Integrasi API', perms: ['cki.integrasi.lihat'] },
    { key: 'rekonsiliasi', href: '/admin/cki/rekonsiliasi', label: 'Rekonsiliasi', perms: ['cki.rekonsiliasi.lihat'] },
    { key: 'import', href: '/admin/cki/import', label: 'Import Bil', perms: ['cki.bil.cipta'] },
    { key: 'laporan', href: '/admin/cki/laporan', label: 'Laporan', perms: ['cki.laporan.lihat'] },
];

export default function CkiLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const can = useCan();
    const section = pathname.split('/')[3] ?? 'ringkasan';
    const current = SECTIONS.find((s) => s.key === section);
    const label = current?.label ?? (section === 'lot' ? 'Garis Masa Bayaran Lot' : 'Pemantauan Cukai Tanah');
    const visible = SECTIONS.filter((s) => can(...s.perms) || s.key === section);

    return (
        <>
            <div className="bg-bg-white border-b border-otl-divider px-4 sm:px-6 py-4 space-y-3">
                <Breadcrumb items={[{ label: 'Pemantauan Cukai Tanah', href: '/admin/cki/ringkasan' }, { label }]} />
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">{label}</h2>
            </div>

            <div className="bg-bg-white border-b border-otl-divider">
                <nav aria-label="Navigasi cukai" className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto">
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
