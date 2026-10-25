'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { Breadcrumb } from '@/components/Breadcrumb';
import { useCan } from '@/lib/kba';

/** MOD-KBA section: page header + full-width section nav. */
const SECTIONS: Array<{ key: string; href: string; label: string; perms: string[] }> = [
    { key: '', href: '/admin/kba', label: 'Tanya', perms: ['kba.soalan.lihat'] },
    { key: 'carian', href: '/admin/kba/carian', label: 'Carian', perms: ['kba.soalan.lihat'] },
    { key: 'artikel', href: '/admin/kba/artikel', label: 'Artikel', perms: ['kba.soalan.lihat'] },
    { key: 'sumber', href: '/admin/kba/sumber', label: 'Sumber & Indeks', perms: ['kba.sumber.lihat'] },
    { key: 'analitik', href: '/admin/kba/analitik', label: 'Analitik & Jurang', perms: ['kba.analitik.lihat'] },
    { key: 'maklum-balas', href: '/admin/kba/maklum-balas', label: 'Maklum Balas', perms: ['kba.maklum_balas.lihat'] },
    { key: 'log-prompt', href: '/admin/kba/log-prompt', label: 'Audit Prompt', perms: ['kba.log_prompt.audit'] },
    { key: 'sokongan', href: '/admin/kba/sokongan', label: 'Paparan Sokongan', perms: ['kba.sokongan.lihat'] },
];

export default function KbaLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const can = useCan();
    const key = pathname.split('/')[3] ?? '';
    const current = SECTIONS.find((s) => s.key === key);
    const label = current?.label ?? 'Pangkalan Pengetahuan';
    const visible = SECTIONS.filter((s) => can(...s.perms) || s.key === key);

    return (
        <>
            <div className="space-y-3 border-b border-otl-divider bg-bg-white px-4 py-4 sm:px-6">
                <Breadcrumb items={[{ label: 'Pangkalan Pengetahuan', href: '/admin/kba' }, { label }]} />
                <h2 className="font-heading text-heading-2xs font-semibold leading-tight text-txt-black-900">{label}</h2>
            </div>
            <div className="border-b border-otl-divider bg-bg-white">
                <nav aria-label="Navigasi Pangkalan Pengetahuan" className="-mb-px flex flex-nowrap gap-6 overflow-x-auto px-4 sm:px-6 lg:px-8">
                    {visible.map((s) => (
                        <Link
                            key={s.key || 'utama'}
                            href={s.href}
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
