'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { Breadcrumb } from '@/components/Breadcrumb';
import { Badge } from '@/components/ui/Badge';
import { useCan } from '@/lib/kes';

/** MOD-KES section: page header + full-width section nav (same pattern as MOD-ADU / MOD-HKM). */
const SECTIONS: Array<{ key: string; href: string; label: string; perms: string[] }> = [
    { key: 'dashboard', href: '/admin/kes', label: 'Dashboard', perms: ['kes.laporan.lihat'] },
    { key: 'kes', href: '/admin/kes/kes', label: 'Daftar Kes', perms: ['kes.kes.lihat'] },
    { key: 'baharu', href: '/admin/kes/kes/baharu', label: 'Daftar Kes Baharu', perms: ['kes.kes.cipta'] },
    { key: 'kalendar', href: '/admin/kes/kalendar', label: 'Kalendar Perundangan', perms: ['kes.kes.lihat'] },
    { key: 'laporan', href: '/admin/kes/laporan', label: 'Laporan', perms: ['kes.laporan.lihat'] },
    { key: 'log-capaian', href: '/admin/kes/log-capaian', label: 'Log Capaian', perms: ['kes.log_capaian.lihat'] },
    { key: 'konfigurasi', href: '/admin/kes/konfigurasi', label: 'Konfigurasi', perms: ['kes.konfigurasi.lihat'] },
];

export default function KesLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const can = useCan();
    const parts = pathname.split('/');
    const section = parts[3] === undefined ? 'dashboard' : parts[3] === 'kes' && parts[4] === 'baharu' ? 'baharu' : parts[3];
    const current = SECTIONS.find((s) => s.key === section);
    const label = current?.label ?? 'Kes Mahkamah dan Perundangan';
    const visible = SECTIONS.filter((s) => can(...s.perms) || s.key === section);

    return (
        <>
            <div className="bg-bg-white border-b border-otl-divider px-4 sm:px-6 py-4 space-y-3">
                <Breadcrumb items={[{ label: 'Kes Mahkamah dan Perundangan', href: '/admin/kes/kes' }, { label }]} />
                <div className="flex flex-wrap items-center gap-2">
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">{label}</h2>
                    <Badge variant="danger" shape="tag">
                        RAHSIA
                    </Badge>
                </div>
                <p className="text-body-xs text-txt-black-500">Setiap paparan, muat turun, cetakan dan eksport direkodkan dalam log capaian beserta alamat IP dan cap masa.</p>
            </div>

            <div className="bg-bg-white border-b border-otl-divider">
                <nav aria-label="Navigasi kes mahkamah" className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto">
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
