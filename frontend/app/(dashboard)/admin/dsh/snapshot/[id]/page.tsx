'use client';

import { use } from 'react';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { AnchorButton } from '@/components/ui/Button';
import { Spinner } from '@/components/ui/Spinner';
import { ARAS_LABEL, dshApi, errorMessage, fmtMasa, type Penapis } from '@/lib/dsh';
import { KpiWidget } from '../../_components/Widgets';

/** A frozen KPI snapshot (scheduled digest / manual) with PDF & Excel export (SRS-DSH-14). */
export default function SnapshotPage({ params }: { params: Promise<{ id: string }> }) {
    const { id } = use(params);
    const q = useQuery({ queryKey: ['dsh', 'snapshot', id], queryFn: () => dshApi.snapshot(Number(id)) });
    if (q.isLoading) return <PageContainer><Spinner /></PageContainer>;
    if (q.isError || !q.data) return <PageContainer><Alert type="error">{errorMessage(q.error)}</Alert></PageContainer>;
    const s = q.data;
    const p = Object.fromEntries(Object.entries(s.penapis).map(([k, v]) => [k, String(v)])) as Penapis;

    return (
        <PageContainer>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h1 className="font-heading text-heading-xs font-semibold text-txt-black-900">Syot kilat aras {ARAS_LABEL[s.aras]}</h1>
                    <p className="text-body-xs text-txt-black-500">
                        Dijana pada {fmtMasa(s.dijana_pada)} ({s.sumber === 'berjadual' ? 'berjadual' : 'manual'}) · penapis:{' '}
                        {Object.keys(s.penapis).length ? Object.entries(s.penapis).map(([k, v]) => `${k}=${v}`).join(', ') : 'tiada'}
                    </p>
                </div>
                <div className="flex gap-2">
                    <AnchorButton size="sm" variant="secondary" href={dshApi.snapshotEksportUrl(s.id, 'pdf')}>Eksport PDF</AnchorButton>
                    <AnchorButton size="sm" variant="secondary" href={dshApi.snapshotEksportUrl(s.id, 'xlsx')}>Eksport Excel</AnchorButton>
                </div>
            </div>
            <Alert type="info">Nilai di bawah dibekukan pada masa snapshot dijana; klik nilai untuk melihat data semasa.</Alert>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {(s.nilai ?? []).map((k) => (
                    <div key={k.key} className="min-w-0">
                        <KpiWidget k={k} p={p} visual="auto" />
                    </div>
                ))}
            </div>
        </PageContainer>
    );
}
