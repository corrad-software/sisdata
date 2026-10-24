'use client';

import Link from 'next/link';
import { Suspense, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useSearchParams } from 'next/navigation';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Pagination } from '@/components/ui/Pagination';
import { Spinner } from '@/components/ui/Spinner';
import { Table } from '@/components/ui/Table';
import { dshApi, errorMessage, fmtMasa, usePenapis, withPenapis } from '@/lib/dsh';
import { PenapisBar } from '../_components/PenapisBar';

/** Drill-down step 2 (SRS-DSH-11): the records behind a KPI value or slice; each row opens the record (step 3). */
function Senarai() {
    const params = useSearchParams();
    const kpi = params.get('kpi') ?? '';
    const pecahan = params.get('pecahan');
    const [p, setP] = usePenapis();
    const [page, setPage] = useState(1);
    const q = useQuery({ queryKey: ['dsh', 'senarai', kpi, pecahan, p, page], queryFn: () => dshApi.senarai(kpi, p, pecahan, page), enabled: !!kpi, placeholderData: (prev) => prev });

    if (!kpi) return <Alert type="warning">Tiada indikator dipilih.</Alert>;

    return (
        <>
            <div className="flex flex-wrap items-center gap-2">
                <Link href={withPenapis('/admin', p)} className="text-body-sm text-txt-primary hover:underline">
                    ← Kembali ke dashboard
                </Link>
                {q.data && <h1 className="font-heading text-heading-2xs font-semibold text-txt-black-900">{q.data.meta.label}</h1>}
                {pecahan && (
                    <Badge variant="info">
                        Pecahan: {pecahan.split(':')[0]} = {pecahan.split(':')[1]}{' '}
                    </Badge>
                )}
                {pecahan && (
                    <Link href={withPenapis('/admin/dsh/senarai', p, { kpi })} className="text-body-xs text-txt-primary hover:underline">
                        Buang pecahan
                    </Link>
                )}
            </div>
            <PenapisBar value={p} onChange={(n) => { setP(n); setPage(1); }} showModul={false} />
            {q.isError && <Alert type="error">{errorMessage(q.error)}</Alert>}
            {q.isLoading ? (
                <div className="flex justify-center py-10">
                    <Spinner />
                </div>
            ) : q.data ? (
                <>
                    <p className="text-body-xs text-txt-black-500">
                        {q.data.meta.jumlah} rekod · dijana pada {fmtMasa(q.data.meta.dijana_pada)}
                    </p>
                    <Table head={q.data.lajur.map((l) => <th key={l.kod}>{l.label}</th>)}>
                        {q.data.data.length === 0 ? (
                            <tr>
                                <td colSpan={q.data.lajur.length} className="text-center text-txt-black-500">
                                    Tiada rekod untuk penapis ini.
                                </td>
                            </tr>
                        ) : (
                            q.data.data.map((r, i) => (
                                <tr key={i}>
                                    {q.data!.lajur.map((l, j) => (
                                        <td key={l.kod} className="whitespace-nowrap">
                                            {j === 0 && r.pautan ? (
                                                <Link href={r.pautan} className="font-medium text-txt-primary hover:underline">
                                                    {String(r.sel[l.kod] ?? '—')}
                                                </Link>
                                            ) : (
                                                String(r.sel[l.kod] ?? '—')
                                            )}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </Table>
                    {q.data.meta.last_page > 1 && (
                        <Pagination
                            currentPage={q.data.meta.page}
                            lastPage={q.data.meta.last_page}
                            onPageChange={setPage}
                            total={q.data.meta.jumlah}
                            firstItem={(q.data.meta.page - 1) * q.data.meta.per_page + 1}
                            lastItem={Math.min(q.data.meta.jumlah, q.data.meta.page * q.data.meta.per_page)}
                        />
                    )}
                </>
            ) : null}
        </>
    );
}

export default function SenaraiPage() {
    return (
        <PageContainer>
            <Suspense fallback={<Spinner />}>
                <Senarai />
            </Suspense>
        </PageContainer>
    );
}
