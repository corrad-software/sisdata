'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { Breadcrumb } from '@/components/Breadcrumb';
import { NAMA_RASMI, useCan } from '@/lib/pws';

/** MOD-PWS section: page header + full-width section nav. */
const SECTIONS: Array<{ key: string; href: string; label: string; perms: string[] }> = [
    { key: '', href: '/admin/pws', label: 'Ringkasan', perms: ['pws.pemantauan.lihat'] },
    { key: 'kandungan', href: '/admin/pws/kandungan', label: 'Kandungan', perms: ['pws.kandungan.lihat'] },
    { key: 'statistik', href: '/admin/pws/statistik', label: 'Statistik Awam', perms: ['pws.statistik.lihat'] },
    { key: 'pemantauan', href: '/admin/pws/pemantauan', label: 'Permohonan & Integrasi', perms: ['pws.pemantauan.lihat'] },
    { key: 'analitik', href: '/admin/pws/analitik', label: 'Analitik Portal', perms: ['pws.pemantauan.lihat'] },
    { key: 'klien', href: '/admin/pws/klien', label: 'Klien API', perms: ['pws.klien.lihat'] },
];

export default function PwsLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const can = useCan();
    const key = pathname.split('/')[3] ?? '';
    const current = SECTIONS.find((s) => s.key === key);
    const label = current?.label ?? NAMA_RASMI;
    const visible = SECTIONS.filter((s) => can(...s.perms) || s.key === key);

    return (
        <>
            <div className="space-y-3 border-b border-otl-divider bg-bg-white px-4 py-4 sm:px-6">
                <Breadcrumb items={[{ label: NAMA_RASMI, href: '/admin/pws' }, { label }]} />
                <h2 className="font-heading text-heading-2xs font-semibold leading-tight text-txt-black-900">{label}</h2>
            </div>
            <div className="border-b border-otl-divider bg-bg-white">
                <nav aria-label={`Navigasi ${NAMA_RASMI}`} className="-mb-px flex flex-nowrap gap-6 overflow-x-auto px-4 sm:px-6 lg:px-8">
                    {visible.map((s) => (
                        <Link
                            key={s.key || 'utama'}
                            href={s.href}
                            aria-current={s.key === key ? 'page' : undefined}
                            className={`whitespace-nowrap border-b-2 py-3 text-body-sm font-medium ${
                                s.key === key ? 'border-primary-600 text-primary-600' : 'border-transparent text-txt-black-500 hover:border-otl-gray-300 hover:text-txt-black-900'
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
