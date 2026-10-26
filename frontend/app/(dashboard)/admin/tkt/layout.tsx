'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { Breadcrumb } from '@/components/Breadcrumb';
import { useCan } from '@/lib/tkt';

/** MOD-TKT section: page header + full-width section nav (same pattern as MOD-ADU). */
const SECTIONS: Array<{ key: string; href: string; label: string; perms: string[] }> = [
    { key: 'dashboard', href: '/admin/tkt', label: 'Papan Pemuka Sokongan', perms: ['tkt.laporan.lihat'] },
    { key: 'tiket', href: '/admin/tkt/tiket', label: 'Tiket', perms: ['tkt.tiket.lihat', 'tkt.sokongan.lihat', 'tkt.semua.pantau'] },
    { key: 'baharu', href: '/admin/tkt/tiket/baharu', label: 'Buka Tiket', perms: ['tkt.tiket.cipta'] },
    { key: 'laporan', href: '/admin/tkt/laporan', label: 'Laporan Bulanan', perms: ['tkt.laporan.lihat'] },
    { key: 'integrasi', href: '/admin/tkt/integrasi', label: 'Integrasi Sistem Aduan', perms: ['tkt.konfigurasi.kemas_kini'] },
    { key: 'konfigurasi', href: '/admin/tkt/konfigurasi', label: 'Konfigurasi', perms: ['tkt.konfigurasi.lihat'] },
];

export default function TktLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const can = useCan();
    const parts = pathname.split('/');
    const section = parts[3] === undefined ? 'dashboard' : parts[3] === 'tiket' && parts[4] === 'baharu' ? 'baharu' : parts[3];
    const current = SECTIONS.find((s) => s.key === section);
    const label = current?.label ?? 'Tiket Sokongan';
    const visible = SECTIONS.filter((s) => can(...s.perms) || s.key === section);

    return (
        <>
            <div className="bg-bg-white border-b border-otl-divider px-4 sm:px-6 py-4 space-y-3">
                <Breadcrumb items={[{ label: 'Tiket Sokongan Sistem', href: '/admin/tkt/tiket' }, { label }]} />
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">{label}</h2>
            </div>

            <div className="bg-bg-white border-b border-otl-divider">
                <nav data-panduan="modul-nav" aria-label="Navigasi tiket sokongan" className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto">
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
