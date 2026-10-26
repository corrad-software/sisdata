'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { Breadcrumb } from '@/components/Breadcrumb';
import { useCan } from '@/lib/wfe';

/** MOD-WFE section: page header + full-width section nav (Kitchen Sink pattern). */
const SECTIONS: Array<{ key: string; label: string; perms: string[] }> = [
    { key: 'tugasan', label: 'Tugasan Saya', perms: ['wfe.tugasan.lihat'] },
    { key: 'permohonan', label: 'Permohonan Saya', perms: ['wfe.permohonan.lihat'] },
    { key: 'pemantauan', label: 'Pemantauan', perms: ['wfe.pemantauan.lihat', 'wfe.definisi.lulus'] },
    { key: 'laporan', label: 'Laporan Prestasi', perms: ['wfe.laporan.lihat'] },
    { key: 'definisi', label: 'Definisi Aliran', perms: ['wfe.definisi.lihat'] },
    { key: 'kumpulan', label: 'Kumpulan Pelulus', perms: ['wfe.kumpulan.lihat', 'wfe.kumpulan.kemas_kini'] },
    { key: 'kalendar', label: 'Kalendar Cuti', perms: ['wfe.kalendar.lihat', 'wfe.kalendar.kemas_kini'] },
    { key: 'pemangku', label: 'Pemangku', perms: ['wfe.pemangku.cipta', 'wfe.pemangku.kemas_kini'] },
];

const EXTRA: Record<string, string> = { instans: 'Butiran Instans' };

export default function WfeLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const can = useCan();
    const section = pathname.split('/')[3] ?? 'tugasan';
    const current = SECTIONS.find((s) => s.key === section);
    const label = current?.label ?? EXTRA[section] ?? 'Workflow';
    const visible = SECTIONS.filter((s) => can(...s.perms) || s.key === section);

    return (
        <>
            <div className="bg-bg-white border-b border-otl-divider px-4 sm:px-6 py-4 space-y-3">
                <Breadcrumb items={[{ label: 'Workflow dan Kelulusan', href: '/admin/wfe/tugasan' }, { label }]} />
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">{label}</h2>
            </div>

            <div className="bg-bg-white border-b border-otl-divider">
                <nav data-panduan="modul-nav" aria-label="Navigasi workflow" className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto">
                    {visible.map((s) => (
                        <Link
                            key={s.key}
                            href={`/admin/wfe/${s.key}`}
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
