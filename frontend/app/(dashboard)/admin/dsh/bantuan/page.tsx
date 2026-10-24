'use client';

import { Suspense } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useSearchParams } from 'next/navigation';
import { PageContainer } from '@/components/PageContainer';
import { Accordion } from '@/components/ui/Accordion';
import { Alert } from '@/components/ui/Alert';
import { AnchorButton } from '@/components/ui/Button';
import { Spinner } from '@/components/ui/Spinner';
import { dshApi, errorMessage } from '@/lib/dsh';

/** Online help (SRS-DSH-25): step-by-step guides (the current screen's first), FAQ and the user manual. */
function Bantuan() {
    const params = useSearchParams();
    const laluan = params.get('laluan') ?? undefined;
    const q = useQuery({ queryKey: ['dsh', 'bantuan', laluan], queryFn: () => dshApi.bantuan(laluan), staleTime: 300_000 });
    if (q.isLoading) return <Spinner />;
    if (q.isError || !q.data) return <Alert type="error">{errorMessage(q.error)}</Alert>;
    const b = q.data;

    return (
        <>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h1 className="font-heading text-heading-xs font-semibold text-txt-black-900">Bantuan dalam talian</h1>
                {b.manual && (
                    <AnchorButton href={b.manual} variant="secondary" size="sm" target="_blank" rel="noopener">
                        Manual pengguna
                    </AnchorButton>
                )}
            </div>
            <section aria-labelledby="h-panduan" className="space-y-3">
                <h2 id="h-panduan" className="text-body-md font-semibold text-txt-black-900">Panduan langkah demi langkah</h2>
                <div className="grid gap-4 md:grid-cols-2">
                    {b.panduan.map((g) => (
                        <article key={g.kod} className={`rounded-lg border bg-bg-white p-4 shadow-card ${g.berkaitan ? 'border-primary-600' : 'border-otl-gray-200'}`}>
                            <h3 className="text-body-sm font-semibold text-txt-black-900">
                                {g.tajuk}
                                {g.berkaitan ? ' — skrin ini' : ''}
                            </h3>
                            <ol className="mt-2 list-decimal space-y-1 ps-5 text-body-sm text-txt-black-700">
                                {g.langkah.map((l, i) => (
                                    <li key={i}>{l}</li>
                                ))}
                            </ol>
                        </article>
                    ))}
                </div>
            </section>
            <section aria-labelledby="h-faq" className="space-y-3">
                <h2 id="h-faq" className="text-body-md font-semibold text-txt-black-900">Soalan lazim (FAQ)</h2>
                <div className="rounded-lg border border-otl-gray-200 bg-bg-white">
                    <Accordion items={b.faq.map((f) => ({ title: f.soalan, content: f.jawapan }))} />
                </div>
            </section>
        </>
    );
}

export default function BantuanPage() {
    return (
        <PageContainer>
            <Suspense fallback={<Spinner />}>
                <Bantuan />
            </Suspense>
        </PageContainer>
    );
}
