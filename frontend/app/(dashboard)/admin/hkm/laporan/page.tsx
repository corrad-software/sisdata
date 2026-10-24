'use client';

import { Suspense, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { hkmApi, muatTurun, ralat, useCan, useRujukan } from '@/lib/hkm';
import { Card, Field, Kosong, Select } from '../_components/common';

/** MOD-HKM reports ("Laporan yang boleh dijana"), viewable and exportable. */
function LaporanView() {
    const params = useSearchParams();
    const can = useCan();
    const { data: ref } = useRujukan();
    const [jenis, setJenis] = useState(params.get('jenis') ?? 'ringkasan');
    const { data, isLoading, error } = useQuery({ queryKey: ['hkm', 'laporan', jenis], queryFn: () => hkmApi.laporan(jenis) });

    return (
        <PageContainer>
            <Card
                title={data?.tajuk ?? 'Laporan'}
                actions={
                    can('hkm.hak_milik.eksport') &&
                    (['xlsx', 'pdf', 'csv'] as const).map((f) => (
                        <Button key={f} size="sm" variant="outline" onClick={() => muatTurun(`/hkm/laporan/${jenis}`, { format: f }, `laporan-${jenis}.${f}`).catch((e) => toast.error(ralat(e)))}>
                            {f.toUpperCase()}
                        </Button>
                    ))
                }
            >
                <div className="w-full sm:w-96">
                    <Field label="Jenis laporan" htmlFor="lp-j">
                        <Select id="lp-j" value={jenis} onChange={setJenis} options={ref?.laporan ?? []} />
                    </Field>
                </div>
                {error && <Alert type="error">{ralat(error)}</Alert>}
                {isLoading ? (
                    <Kosong>Memuatkan…</Kosong>
                ) : data && data.baris.length === 0 ? (
                    <Kosong>Tiada rekod.</Kosong>
                ) : (
                    data && (
                        <>
                            <p className="text-body-sm text-txt-black-500">{data.baris.length} baris</p>
                            <div className="overflow-x-auto">
                                <Table head={<>{data.lajur.map((l) => <th key={l}>{l}</th>)}</>}>
                                    {data.baris.slice(0, 500).map((row, i) => (
                                        <tr key={i}>
                                            {row.map((cell, j) => (
                                                <td key={j} className="break-words max-w-[14rem]">
                                                    {cell ?? '—'}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </Table>
                            </div>
                            {data.baris.length > 500 && <p className="text-body-xs text-txt-black-500">Memaparkan 500 baris pertama — eksport untuk senarai penuh.</p>}
                        </>
                    )
                )}
            </Card>
        </PageContainer>
    );
}

export default function LaporanPage() {
    return (
        <Suspense>
            <LaporanView />
        </Suspense>
    );
}
