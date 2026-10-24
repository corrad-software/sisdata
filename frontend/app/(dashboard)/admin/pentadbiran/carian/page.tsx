'use client';

import Link from 'next/link';
import { Suspense, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { errorText, globalSearch } from '@/lib/adm';

/* SRS-ADM-21: global search results, grouped by entity, already filtered by the user's access. */
export default function CarianPage() {
    return (
        <Suspense fallback={<PageContainer>Memuatkan…</PageContainer>}>
            <CarianContent />
        </Suspense>
    );
}

function CarianContent() {
    const params = useSearchParams();
    const router = useRouter();
    const q = params.get('q') ?? '';
    const [text, setText] = useState(q);

    const { data, isFetching, error } = useQuery({
        queryKey: ['adm', 'carian', q],
        queryFn: () => globalSearch(q, 25),
        enabled: q.trim().length > 0,
    });

    return (
        <PageContainer>
            <form
                role="search"
                className="flex flex-col gap-2 sm:flex-row"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.push(`/admin/pentadbiran/carian?q=${encodeURIComponent(text.trim())}`);
                }}
            >
                <input
                    type="search"
                    aria-label="Carian global"
                    value={text}
                    onChange={(e) => setText(e.target.value)}
                    placeholder="No. lot, mukim, daerah, no. hak milik, pemilik, kata kunci…"
                    className="flex-1 rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 text-body-sm"
                />
                <Button type="submit">Cari</Button>
            </form>

            {!q && <p className="text-body-sm text-txt-black-500">Masukkan kata carian. Hasil dihadkan mengikut kebenaran capaian anda.</p>}
            {error && <Alert type="error">{errorText(error)}</Alert>}
            {isFetching && <p className="text-body-sm text-txt-black-500">Mencari…</p>}

            {data && (
                <>
                    <p className="text-body-sm text-txt-black-500">
                        {data.jumlah} hasil untuk &quot;{q}&quot;
                        {data.istilah.length > 1 && <> (termasuk sinonim: {data.istilah.slice(1).join(', ')})</>}
                    </p>
                    {data.data.map((g) => (
                        <section key={g.entiti} className="rounded-lg border border-otl-gray-200 bg-bg-white shadow-card" aria-labelledby={`g-${g.entiti}`}>
                            <h3 id={`g-${g.entiti}`} className="border-b border-otl-gray-200 px-4 py-3 font-heading font-semibold text-body-md text-txt-black-900">
                                {g.label} <span className="font-normal text-txt-black-500 text-body-sm">({g.hasil.length})</span>
                            </h3>
                            <ul className="divide-y divide-otl-gray-200">
                                {g.hasil.map((h) => (
                                    <li key={`${g.entiti}-${h.id}`}>
                                        <Link href={h.link} className="block px-4 py-3 hover:bg-bg-washed focus:outline-none focus-visible:ring-2 focus-visible:ring-fr-primary">
                                            <span className="block text-body-sm font-medium text-primary-600">{h.title}</span>
                                            {h.subtitle && <span className="block text-body-xs text-txt-black-500">{h.subtitle}</span>}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
                    {data.jumlah === 0 && <p className="text-body-sm text-txt-black-500">Tiada hasil.</p>}
                </>
            )}
        </PageContainer>
    );
}
