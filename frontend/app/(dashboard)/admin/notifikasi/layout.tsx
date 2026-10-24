'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Breadcrumb } from '@/components/Breadcrumb';
import { fetchAbilities, type NtfAbilities } from '@/lib/ntf';

const TABS: Array<{ href: string; label: string; permission?: keyof NtfAbilities }> = [
    { href: '/admin/notifikasi', label: 'Peti Masuk' },
    { href: '/admin/notifikasi/tetapan', label: 'Tetapan Saya' },
    { href: '/admin/notifikasi/jenis', label: 'Jenis & Templat', permission: 'ntf.jenis.lihat' },
    { href: '/admin/notifikasi/pemantauan', label: 'Pemantauan', permission: 'ntf.pemantauan.lihat' },
    { href: '/admin/notifikasi/pengumuman', label: 'Pengumuman', permission: 'ntf.pengumuman.cipta' },
];

function activeTab(pathname: string) {
    return [...TABS].sort((a, b) => b.href.length - a.href.length).find((t) => pathname === t.href || pathname.startsWith(`${t.href}/`));
}

export default function NotifikasiLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const { data } = useQuery({ queryKey: ['ntf', 'kebenaran'], queryFn: fetchAbilities, staleTime: 60_000 });
    const abilities = data?.data;
    const current = activeTab(pathname);
    const tabs = TABS.filter((t) => !t.permission || abilities?.[t.permission]);

    return (
        <>
            <div className="bg-bg-white border-b border-otl-divider px-4 sm:px-6 py-4 space-y-3">
                <Breadcrumb
                    items={[
                        { label: 'Notifikasi', href: '/admin/notifikasi' },
                        ...(current && current.href !== '/admin/notifikasi' ? [{ label: current.label }] : []),
                    ]}
                />
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">
                    {current?.href === '/admin/notifikasi' || !current ? 'Notifikasi' : current.label}
                </h2>
            </div>

            <div className="bg-bg-white border-b border-otl-divider">
                <nav className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto" aria-label="Bahagian notifikasi">
                    {tabs.map((tab) => {
                        const active = tab.href === current?.href;

                        return (
                            <Link
                                key={tab.href}
                                href={tab.href}
                                aria-current={active ? 'page' : undefined}
                                className={`whitespace-nowrap border-b-2 py-3 text-body-sm font-medium ${
                                    active
                                        ? 'border-primary-600 text-primary-600'
                                        : 'border-transparent text-txt-black-500 hover:text-txt-black-900 hover:border-otl-gray-300'
                                }`}
                            >
                                {tab.label}
                            </Link>
                        );
                    })}
                </nav>
            </div>

            {children}
        </>
    );
}
