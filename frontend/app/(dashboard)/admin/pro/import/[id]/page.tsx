'use client';

import { useState } from 'react';
import { useParams } from 'next/navigation';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Button, LinkButton } from '@/components/ui/Button';
import { Card, ExportMenu, InfoList, Loading } from '@/components/pro/common';
import { BarisTable, DitolakTable, ENTITI_LABEL, RingkasanStats, SesiBadge, StatusFilter } from '@/components/pro/ImportBits';
import { errorMessage, fmtTarikh, proApi, useCan } from '@/lib/pro';

export default function ImportSesiPage() {
    const { id } = useParams<{ id: string }>();
    const can = useCan();
    const queryClient = useQueryClient();
    const [status, setStatus] = useState('');
    const [busy, setBusy] = useState(false);
    const { data, isLoading, error } = useQuery({ queryKey: ['pro', 'import', id, 'baris', status], queryFn: () => proApi.sesiImport(id, status || undefined).then((r) => r.data) });

    const r = data?.ringkasan;
    const committed = r?.status_sesi === 'dikomit';

    async function act(fn: () => Promise<{ message: string }>, confirmText: string) {
        if (!window.confirm(confirmText)) return;
        setBusy(true);
        try {
            const res = await fn();
            toast.success(res.message);
            queryClient.invalidateQueries({ queryKey: ['pro'] });
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setBusy(false);
        }
    }

    return (
        <PageContainer>
            {isLoading && !data ? (
                <Loading />
            ) : error ? (
                <Alert type="error">{errorMessage(error)}</Alert>
            ) : r && data ? (
                <>
                    <RingkasanStats r={r} committed={committed} />
                    <Card
                        title="Ringkasan sesi"
                        actions={
                            <>
                                <ExportMenu label="Eksport laporan" onExport={(f) => proApi.laporanImport(id, f)} />
                                {r.status_sesi === 'pratonton' && can('pro.import.cipta') && (
                                    <>
                                        <Button variant="secondary" disabled={busy} onClick={() => act(() => proApi.batalImport(id), 'Batalkan sesi import ini?')}>
                                            Batal sesi
                                        </Button>
                                        <Button
                                            disabled={busy || r.bil_sah === 0}
                                            onClick={() => act(() => proApi.komitImport(id), `Komit ${r.bil_sah} baris sah ke pangkalan data induk?`)}
                                        >
                                            Komit baris sah ({r.bil_sah})
                                        </Button>
                                    </>
                                )}
                            </>
                        }
                    >
                        <InfoList
                            items={[
                                { label: 'Fail', value: r.nama_fail },
                                { label: 'Entiti sasaran', value: ENTITI_LABEL[r.entiti_sasaran] ?? r.entiti_sasaran },
                                { label: 'Status', value: <SesiBadge status={r.status_sesi} /> },
                                { label: 'Dimuat naik oleh', value: `${r.pengguna ?? '—'} · ${fmtTarikh(r.tarikh_masa, true)}` },
                                { label: 'Tarikh komit', value: fmtTarikh(r.tarikh_komit, true) },
                                { label: 'Fail asal', value: r.catatan_fail_asal ?? r.lokasi_fail_asal ?? '—' },
                                {
                                    label: 'Pemetaan lajur',
                                    value: r.pemetaan ? (
                                        <ul className="space-y-0.5">
                                            {Object.entries(r.pemetaan).map(([col, f]) => (
                                                <li key={col} className="text-body-xs">
                                                    <span className="font-mono">{col}</span> → {data.medan_sasaran.find((m) => m.medan === f)?.label ?? f}
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        '—'
                                    ),
                                },
                            ]}
                        />
                    </Card>

                    <Card title="Baris ditolak">
                        <DitolakTable rows={data.ditolak} />
                    </Card>

                    <Card title="Baris">
                        <StatusFilter value={status} onChange={setStatus} committed={committed} />
                        <BarisTable rows={data.baris} medan={data.medan_sasaran} />
                        <p className="text-body-xs text-txt-black-500">Memaparkan sehingga 500 baris.</p>
                    </Card>

                    <div>
                        <LinkButton variant="secondary" href="/admin/pro/import">
                            Kembali ke import
                        </LinkButton>
                    </div>
                </>
            ) : null}
        </PageContainer>
    );
}
