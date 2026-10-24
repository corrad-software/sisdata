'use client';

import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Spinner } from '@/components/ui/Spinner';
import { dshApi, type PautanPortal } from '@/lib/dsh';

/** Welcome + internal-portal links for users without dashboard permissions (SRS-DSH-19). */
export function PortalSelamatDatang({ name }: { name: string }) {
    const q = useQuery({ queryKey: ['dsh', 'portal'], queryFn: dshApi.portal, staleTime: 300_000 });
    const groups = (q.data ?? []).reduce<Record<string, PautanPortal[]>>((acc, l) => {
        (acc[l.kumpulan] ??= []).push(l);
        return acc;
    }, {});

    return (
        <PageContainer>
            <section className="rounded-lg border border-otl-gray-200 bg-bg-white p-6 shadow-card">
                <h1 className="font-heading text-heading-2xs font-semibold text-txt-black-900">Selamat datang ke SISDATA{name ? `, ${name}` : ''}</h1>
                <p className="mt-2 text-body-sm text-txt-black-500">
                    Sistem Pengurusan Data Hartanah MAIS. Pilih fungsi di bawah atau melalui menu sisi; fungsi yang dipaparkan mengikut peranan anda.
                </p>
            </section>
            {q.isLoading ? (
                <div className="flex justify-center py-8">
                    <Spinner />
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {Object.entries(groups).map(([g, links]) => (
                        <section key={g} className="rounded-lg border border-otl-gray-200 bg-bg-white p-4 shadow-card">
                            <h2 className="text-body-sm font-semibold text-txt-black-900">{g}</h2>
                            <ul className="mt-2 space-y-1">
                                {links.map((l) => (
                                    <li key={l.href}>
                                        <Link href={l.href} className="text-body-sm text-txt-primary hover:underline">
                                            {l.label}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
                </div>
            )}
        </PageContainer>
    );
}
