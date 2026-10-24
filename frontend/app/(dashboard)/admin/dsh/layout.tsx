'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { Breadcrumb } from '@/components/Breadcrumb';
import { useCan } from '@/lib/dsh';

/** MOD-DSH section: page header + full-width section nav. */
const SECTIONS: Array<{ key: string; href: string; label: string; perms: string[] | null }> = [
    { key: 'utama', href: '/admin', label: 'Dashboard', perms: ['dsh.dashboard.lihat'] },
    { key: 'tema/cukai', href: '/admin/dsh/tema/cukai', label: 'Cukai', perms: ['cki.laporan.lihat'] },
    { key: 'tema/pajakan', href: '/admin/dsh/tema/pajakan', label: 'Sewaan / Pajakan', perms: ['pjk.laporan.lihat'] },
    { key: 'tema/pemantauan', href: '/admin/dsh/tema/pemantauan', label: 'Pemantauan Tanah', perms: ['dsh.dashboard.lihat'] },
    { key: 'inbox', href: '/admin/dsh/inbox', label: 'Inbox Tugasan', perms: ['dsh.dashboard.lihat'] },
    { key: 'carian', href: '/admin/dsh/carian', label: 'Carian', perms: null },
    { key: 'bantuan', href: '/admin/dsh/bantuan', label: 'Bantuan', perms: null },
    { key: 'tetapan', href: '/admin/dsh/tetapan', label: 'Tetapan', perms: ['dsh.widget.kemas_kini'] },
];

const TITLE: Record<string, string> = {
    senarai: 'Senarai Rekod Indikator',
    snapshot: 'Snapshot KPI',
};

export default function DshLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const can = useCan();
    const parts = pathname.split('/');
    const key = parts[3] === 'tema' ? `tema/${parts[4] ?? ''}` : (parts[3] ?? 'utama');
    const current = SECTIONS.find((s) => s.key === key);
    const label = current?.label ?? TITLE[parts[3] ?? ''] ?? 'Dashboard Eksekutif';
    const visible = SECTIONS.filter((s) => s.perms === null || can(...s.perms) || s.key === key);

    return (
        <>
            <div className="space-y-3 border-b border-otl-divider bg-bg-white px-4 py-4 sm:px-6">
                <Breadcrumb items={[{ label: 'Dashboard Eksekutif', href: '/admin' }, { label }]} />
                <h2 className="font-heading text-heading-2xs font-semibold leading-tight text-txt-black-900">{label}</h2>
            </div>
            <div className="border-b border-otl-divider bg-bg-white">
                <nav aria-label="Navigasi dashboard" className="-mb-px flex flex-nowrap gap-6 overflow-x-auto px-4 sm:px-6 lg:px-8">
                    {visible.map((s) => (
                        <Link
                            key={s.key}
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
