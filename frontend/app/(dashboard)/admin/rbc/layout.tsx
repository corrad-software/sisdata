'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { Breadcrumb } from '@/components/Breadcrumb';

/** MOD-RBC section nav — the full-width tab bar under the page header (CLAUDE.md pattern). */
const TABS: Array<{ key: string; label: string; title: string }> = [
    { key: 'peranan', label: 'Peranan', title: 'Peranan & Kebenaran' },
    { key: 'matriks', label: 'Matriks Capaian', title: 'Matriks Capaian' },
    { key: 'log-masuk', label: 'Log Masuk', title: 'Log Masuk & Amaran' },
    { key: 'sesi', label: 'Sesi Aktif', title: 'Sesi Aktif' },
    { key: 'recert', label: 'Pengesahan Semula', title: 'Pengesahan Semula Capaian' },
    { key: 'pemetaan', label: 'Pemetaan SSO', title: 'Pemetaan Tuntutan SSO MAIS' },
    { key: 'laporan', label: 'Laporan', title: 'Laporan Capaian' },
    { key: 'polisi', label: 'Polisi Keselamatan', title: 'Polisi Keselamatan' },
];

export default function RbcLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const active = pathname.split('/')[3] ?? 'peranan';
    const tab = TABS.find((t) => t.key === active) ?? TABS[0];

    return (
        <>
            <div className="bg-bg-white border-b border-otl-divider px-4 sm:px-6 py-4 space-y-3">
                <Breadcrumb items={[{ label: 'Capaian', href: '/admin/rbc/peranan' }, { label: tab.title }]} />
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">{tab.title}</h2>
            </div>

            <div className="bg-bg-white border-b border-otl-divider">
                <nav data-panduan="modul-nav" aria-label="Menu capaian" className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto">
                    {TABS.map(({ key, label }) => (
                        <Link
                            key={key}
                            href={`/admin/rbc/${key}`}
                            aria-current={key === active ? 'page' : undefined}
                            className={`whitespace-nowrap border-b-2 py-3 text-body-sm font-medium ${
                                key === active
                                    ? 'border-primary-600 text-primary-600'
                                    : 'border-transparent text-txt-black-500 hover:text-txt-black-900 hover:border-otl-gray-300'
                            }`}
                        >
                            {label}
                        </Link>
                    ))}
                </nav>
            </div>

            {children}
        </>
    );
}
