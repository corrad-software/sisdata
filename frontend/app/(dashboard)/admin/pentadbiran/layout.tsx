'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { Breadcrumb } from '@/components/Breadcrumb';

/* MOD-ADM · Pentadbiran Sistem — shared header + page-level section nav. */
const TABS: Record<string, string> = {
    '': 'Ringkasan',
    parameter: 'Parameter',
    'kod-rujukan': 'Kod Rujukan',
    templat: 'Templat Laporan',
    kelulusan: 'Kelulusan Konfigurasi',
    'tugasan-berjadual': 'Tugasan Berjadual',
    'log-ralat': 'Log',
    kesihatan: 'Kesihatan Sistem',
    integrasi: 'Integrasi',
    carian: 'Carian',
};

export default function PentadbiranLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const section = pathname.split('/').filter(Boolean)[2] ?? '';
    const label = TABS[section] ?? 'Pentadbiran Sistem';

    return (
        <>
            <div className="bg-bg-white border-b border-otl-divider px-4 sm:px-6 py-4 space-y-3">
                <Breadcrumb
                    items={[
                        { label: 'Pentadbiran Sistem', href: '/admin/pentadbiran' },
                        ...(section ? [{ label }] : []),
                    ]}
                />
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">
                    {section ? label : 'Pentadbiran Sistem'}
                </h2>
            </div>

            <div className="bg-bg-white border-b border-otl-divider">
                <nav aria-label="Seksyen pentadbiran" className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto">
                    {Object.entries(TABS).map(([key, text]) => {
                        const active = key === section;
                        return (
                            <Link
                                key={key || 'ringkasan'}
                                href={`/admin/pentadbiran${key ? `/${key}` : ''}`}
                                aria-current={active ? 'page' : undefined}
                                className={`whitespace-nowrap border-b-2 py-3 text-body-sm font-medium ${
                                    active
                                        ? 'border-primary-600 text-primary-600'
                                        : 'border-transparent text-txt-black-500 hover:text-txt-black-900 hover:border-otl-gray-300'
                                }`}
                            >
                                {text}
                            </Link>
                        );
                    })}
                </nav>
            </div>

            {children}
        </>
    );
}
