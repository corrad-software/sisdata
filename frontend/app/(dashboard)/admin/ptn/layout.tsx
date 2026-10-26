'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { Breadcrumb } from '@/components/Breadcrumb';
import { useCan } from '@/lib/ptn';

/** MOD-PTN section: page header + full-width section nav. The field app (/lapangan) renders its own compact chrome. */
const SECTIONS: Array<{ key: string; href: string; label: string; perms: string[] }> = [
    { key: 'dashboard', href: '/admin/ptn', label: 'Dashboard Risiko', perms: ['ptn.risiko.lihat'] },
    { key: 'jadual', href: '/admin/ptn/jadual', label: 'Perancangan & Jadual', perms: ['ptn.jadual.lihat'] },
    { key: 'lawatan', href: '/admin/ptn/lawatan', label: 'Rekod Lawatan', perms: ['ptn.lawatan.lihat'] },
    { key: 'isu', href: '/admin/ptn/isu', label: 'Isu & Tindakan', perms: ['ptn.isu.lihat'] },
    { key: 'lot', href: '/admin/ptn/lot', label: 'Lot & Risiko', perms: ['ptn.lawatan.lihat'] },
    { key: 'laporan', href: '/admin/ptn/laporan', label: 'Laporan', perms: ['ptn.laporan.lihat'] },
    { key: 'konfigurasi', href: '/admin/ptn/konfigurasi', label: 'Konfigurasi', perms: ['ptn.konfigurasi.lihat'] },
    { key: 'lapangan', href: '/admin/ptn/lapangan', label: 'Aplikasi Lapangan', perms: ['ptn.lapangan.lihat'] },
];

export default function PtnLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const can = useCan();
    const section = pathname.split('/')[3] ?? 'dashboard';
    if (section === 'lapangan') return <>{children}</>;
    const current = SECTIONS.find((s) => s.key === section);
    const label = current?.label ?? 'Pemantauan Tanah';
    const visible = SECTIONS.filter((s) => can(...s.perms) || s.key === section);

    return (
        <>
            <div className="bg-bg-white border-b border-otl-divider px-4 sm:px-6 py-4 space-y-3">
                <Breadcrumb items={[{ label: 'Pemantauan Tanah', href: '/admin/ptn' }, { label }]} />
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">{label}</h2>
            </div>
            <div className="bg-bg-white border-b border-otl-divider">
                <nav data-panduan="modul-nav" aria-label="Navigasi pemantauan tanah" className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto">
                    {visible.map((s) => (
                        <Link
                            key={s.key}
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
