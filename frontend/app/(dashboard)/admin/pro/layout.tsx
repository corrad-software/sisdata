'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { Breadcrumb } from '@/components/Breadcrumb';
import { useCan } from '@/lib/pro';

/** MOD-PRO section: page header + full-width section nav (Kitchen Sink pattern). */
const SECTIONS: Array<{ key: string; label: string; perms: string[] }> = [
    { key: 'lot', label: 'Lot Tanah', perms: ['pro.lot.lihat'] },
    { key: 'import', label: 'Import Data', perms: ['pro.import.cipta'] },
    { key: 'duplikasi', label: 'Duplikasi', perms: ['pro.duplikasi.lihat'] },
    { key: 'kualiti', label: 'Kualiti Data', perms: ['pro.kualiti.lihat', 'pro.stewardship.lihat'] },
    { key: 'stewardship', label: 'Stewardship', perms: ['pro.stewardship.lihat'] },
    { key: 'kamus', label: 'Kamus Data', perms: ['pro.kamus.lihat'] },
    { key: 'arkib', label: 'Arkib & Retensi', perms: ['pro.arkib.lihat'] },
    { key: 'kunci-api', label: 'Kunci API', perms: ['pro.api.cipta'] },
];

const UUID = /^[0-9a-fA-F-]{36}$/;

export default function ProLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const can = useCan();
    const parts = pathname.split('/');
    const section = parts[3] ?? 'lot';

    // The 360° profile renders its own header + tab bar.
    if (section === 'lot' && parts[4] && UUID.test(parts[4]) && !parts[5]) {
        return <>{children}</>;
    }

    const current = SECTIONS.find((s) => s.key === section);
    const sub =
        section === 'lot' && parts[4] === 'baru'
            ? 'Tambah Lot'
            : section === 'lot' && parts[5] === 'kemas-kini'
              ? 'Kemas Kini Lot'
              : section === 'import' && parts[4]
                ? 'Laporan Sesi Import'
                : null;
    const title = sub ?? current?.label ?? 'Profil Hartanah';
    const visible = SECTIONS.filter((s) => can(...s.perms) || s.key === section);

    return (
        <>
            <div className="bg-bg-white border-b border-otl-divider px-4 sm:px-6 py-4 space-y-3">
                <Breadcrumb
                    items={[
                        { label: 'Profil Hartanah', href: '/admin/pro/lot' },
                        ...(sub && current ? [{ label: current.label, href: `/admin/pro/${current.key}` }] : []),
                        { label: title },
                    ]}
                />
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">{title}</h2>
            </div>

            <div className="bg-bg-white border-b border-otl-divider print:hidden">
                <nav aria-label="Navigasi Profil Hartanah" className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto">
                    {visible.map((s) => (
                        <Link
                            key={s.key}
                            href={`/admin/pro/${s.key}`}
                            aria-current={s.key === section ? 'page' : undefined}
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
