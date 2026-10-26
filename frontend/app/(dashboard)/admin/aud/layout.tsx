'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/PageHeader';

const TABS = [
    { href: '/admin/aud', label: 'Carian Audit' },
    { href: '/admin/aud/kronologi', label: 'Kronologi Rekod' },
    { href: '/admin/aud/laporan', label: 'Laporan' },
    { href: '/admin/aud/integriti', label: 'Integriti & Retensi' },
];

export default function AuditLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const active = TABS.slice().reverse().find((t) => pathname === t.href || pathname.startsWith(`${t.href}/`)) ?? TABS[0];

    return (
        <>
            <PageHeader title="Audit Trail" breadcrumbs={[{ label: 'Audit Trail', href: '/admin/aud' }, { label: active.label }]} />

            <div className="bg-bg-white border-b border-otl-divider">
                <nav data-panduan="modul-nav" aria-label="Seksyen audit trail" className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto">
                    {TABS.map((tab) => (
                        <Link
                            key={tab.href}
                            href={tab.href}
                            aria-current={tab.href === active.href ? 'page' : undefined}
                            className={`whitespace-nowrap border-b-2 py-3 text-body-sm font-medium ${
                                tab.href === active.href
                                    ? 'border-primary-600 text-primary-600'
                                    : 'border-transparent text-txt-black-500 hover:text-txt-black-900 hover:border-otl-gray-300'
                            }`}
                        >
                            {tab.label}
                        </Link>
                    ))}
                </nav>
            </div>

            {children}
        </>
    );
}
