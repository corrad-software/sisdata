'use client';

import { useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { ckiApi, muatTurun, ralat, useCan, useRujukan } from '@/lib/cki';
import { Card, Field, Kosong, Select } from '../_components/common';
import { Penapis } from '../_components/Penapis';

/** SRS-CKI-23 and the module's report list — JSON preview, PDF and Excel export. */
export default function LaporanPage() {
    const can = useCan();
    const search = useSearchParams();
    const { data: ref } = useRujukan();
    const [jenis, setJenis] = useState(search.get('jenis') ?? 'tertunggak');
    const [f, setF] = useState<Record<string, string>>({});
    const { data, isLoading, error } = useQuery({ queryKey: ['cki', 'laporan', jenis, f], queryFn: () => ckiApi.laporan(jenis, f) });
    const eksport = (format: 'pdf' | 'xlsx') => muatTurun(`/cki/laporan/${jenis}/eksport`, { ...f, format }, `laporan-cki-${jenis}.${format}`).catch((e) => toast.error(ralat(e)));

    return (
        <PageContainer>
            <Card
                actions={
                    can('cki.laporan.eksport') ? (
                        <>
                            <Button size="sm" variant="outline" onClick={() => eksport('pdf')}>
                                PDF
                            </Button>
                            <Button size="sm" variant="outline" onClick={() => eksport('xlsx')}>
                                Excel
                            </Button>
                        </>
                    ) : undefined
                }
                title="Laporan cukai"
            >
                <Field label="Jenis laporan" htmlFor="l-jenis">
                    <Select id="l-jenis" value={jenis} onChange={setJenis} options={ref?.jenis_laporan ?? []} />
                </Field>
                <Penapis value={f} onChange={(k, v) => setF((p) => ({ ...p, [k]: v }))} />
            </Card>
            <Card title={data?.tajuk ?? 'Laporan'}>
                {error && <p className="text-txt-danger">{ralat(error)}</p>}
                {isLoading || !data ? (
                    <Kosong>Memuatkan…</Kosong>
                ) : (
                    <>
                        <p className="text-body-sm text-txt-black-500">{data.baris.length} baris</p>
                        <div className="overflow-x-auto">
                            <Table head={<>{data.kepala.map((h) => <th key={h}>{h}</th>)}</>}>
                                {data.baris.length === 0 ? (
                                    <Kosong colSpan={data.kepala.length}>Tiada data.</Kosong>
                                ) : (
                                    data.baris.slice(0, 200).map((row, i) => (
                                        <tr key={i}>
                                            {row.map((c, j) => (
                                                <td key={j} className="whitespace-nowrap">
                                                    {c ?? '—'}
                                                </td>
                                            ))}
                                        </tr>
                                    ))
                                )}
                            </Table>
                        </div>
                        {data.baris.length > 200 && <p className="text-body-xs text-txt-black-500">Memaparkan 200 baris pertama — eksport untuk senarai penuh.</p>}
                    </>
                )}
            </Card>
        </PageContainer>
    );
}
