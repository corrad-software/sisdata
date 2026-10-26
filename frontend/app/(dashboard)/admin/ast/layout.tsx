'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { Breadcrumb } from '@/components/Breadcrumb';
import { useCan } from '@/lib/ast';

/** MOD-AST section: page header + full-width section nav. The field app (/lapangan) renders its own compact chrome. */
const SECTIONS: Array<{ key: string; href: string; label: string; perms: string[] }> = [
    { key: 'ringkasan', href: '/admin/ast', label: 'Ringkasan', perms: ['ast.aset.lihat'] },
    { key: 'aset', href: '/admin/ast/aset', label: 'Inventori Aset', perms: ['ast.aset.lihat'] },
    { key: 'penyelenggaraan', href: '/admin/ast/penyelenggaraan', label: 'Jadual & Kalendar', perms: ['ast.penyelenggaraan.lihat'] },
    { key: 'kerja', href: '/admin/ast/kerja', label: 'Kerja Penyelenggaraan', perms: ['ast.penyelenggaraan.lihat', 'ast.kerja.kemas_kini'] },
    { key: 'kontraktor', href: '/admin/ast/kontraktor', label: 'Kontraktor', perms: ['ast.kontraktor.lihat'] },
    { key: 'laporan', href: '/admin/ast/laporan', label: 'Laporan', perms: ['ast.laporan.lihat'] },
    { key: 'konfigurasi', href: '/admin/ast/konfigurasi', label: 'Kategori', perms: ['ast.konfigurasi.lihat'] },
    { key: 'lapangan', href: '/admin/ast/lapangan', label: 'Aplikasi Lapangan', perms: ['ast.lapangan.lihat'] },
];

export default function AstLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const can = useCan();
    const section = pathname.split('/')[3] ?? 'ringkasan';
    if (section === 'lapangan') return <>{children}</>;
    const current = SECTIONS.find((s) => s.key === section);
    const label = current?.label ?? 'Inventori Aset Fizikal';
    const visible = SECTIONS.filter((s) => can(...s.perms) || s.key === section);

    return (
        <>
            <div className="bg-bg-white border-b border-otl-divider px-4 sm:px-6 py-4 space-y-3">
                <Breadcrumb items={[{ label: 'Aset Fizikal', href: '/admin/ast' }, { label }]} />
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">{label}</h2>
            </div>
            <div className="bg-bg-white border-b border-otl-divider">
                <nav data-panduan="modul-nav" aria-label="Navigasi aset fizikal" className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto">
                    {visible.map((s) => (
                        <Link
                            key={s.key}
                            href={s.href}
                            aria-current={s.key === section ? 'page' : undefined}
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
