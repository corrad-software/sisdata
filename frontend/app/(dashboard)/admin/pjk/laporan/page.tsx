'use client';

import { useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { Table } from '@/components/ui/Table';
import { muatTurun, pjkApi, ralat, useCan, useRujukan } from '@/lib/pjk';
import { Card, Field, inputClass, Kosong, Select } from '../_components/common';

/** SRS-PJK-27 + report list: every report as a table with PDF / Excel export (scoped by the API). */
export default function LaporanPage() {
    const can = useCan();
    const search = useSearchParams();
    const { data: ref } = useRujukan();
    const [jenis, setJenis] = useState(search.get('jenis') ?? 'kontrak');
    const [f, setF] = useState<Record<string, string>>({});
    const { data, isLoading, error } = useQuery({ queryKey: ['pjk', 'laporan', jenis, f], queryFn: () => pjkApi.laporan(jenis, f) });
    const set = (k: string, v: string) => setF((p) => ({ ...p, [k]: v }));

    async function eksport(format: 'pdf' | 'xlsx') {
        try {
            await muatTurun(`/pjk/laporan/${jenis}/eksport`, { ...f, format }, `laporan-${jenis}.${format}`);
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    const ringkasan = Object.entries(data?.ringkasan ?? {}).filter(([, v]) => typeof v !== 'object');

    return (
        <PageContainer>
            <Card>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <Field label="Laporan" htmlFor="lp-jenis" className="sm:col-span-2">
                        <Select id="lp-jenis" value={jenis} onChange={setJenis} options={ref?.jenis_laporan ?? []} />
                    </Field>
                    <Field label="Daerah" htmlFor="lp-daerah">
                        <Select id="lp-daerah" value={f.kod_daerah ?? ''} onChange={(v) => set('kod_daerah', v)} options={ref?.daerah ?? []} empty="Semua" />
                    </Field>
                    <Field label="Kategori" htmlFor="lp-kat">
                        <Select id="lp-kat" value={f.kategori_hartanah ?? ''} onChange={(v) => set('kategori_hartanah', v)} options={ref?.kategori_hartanah ?? []} empty="Semua" />
                    </Field>
                    {jenis === 'kontrak' && (
                        <Field label="Status" htmlFor="lp-status">
                            <Select id="lp-status" value={f.status ?? ''} onChange={(v) => set('status', v)} options={ref?.status_kontrak ?? []} empty="Semua" />
                        </Field>
                    )}
                    {(jenis === 'kontrak' || jenis === 'tunggakan') && (
                        <Field label="Penyewa" htmlFor="lp-penyewa">
                            <input id="lp-penyewa" className={inputClass} value={f.penyewa ?? ''} onChange={(e) => set('penyewa', e.target.value)} />
                        </Field>
                    )}
                    {(jenis === 'kutipan' || jenis === 'kontrak') && (
                        <>
                            <Field label="Dari" htmlFor="lp-dari">
                                <input id="lp-dari" type="date" className={inputClass} value={f.dari ?? ''} onChange={(e) => set('dari', e.target.value)} />
                            </Field>
                            <Field label="Hingga" htmlFor="lp-hingga">
                                <input id="lp-hingga" type="date" className={inputClass} value={f.hingga ?? ''} onChange={(e) => set('hingga', e.target.value)} />
                            </Field>
                        </>
                    )}
                    {jenis === 'hampir_tamat' && (
                        <Field label="Dalam tempoh (hari)" htmlFor="lp-hari">
                            <input id="lp-hari" type="number" className={inputClass} value={f.hari ?? '180'} onChange={(e) => set('hari', e.target.value)} />
                        </Field>
                    )}
                    {jenis === 'agihan' && (
                        <Field label="Tempoh (YYYY-MM)" htmlFor="lp-tempoh">
                            <input id="lp-tempoh" type="month" className={inputClass} value={f.tempoh ?? ''} onChange={(e) => set('tempoh', e.target.value)} />
                        </Field>
                    )}
                </div>
                {can('pjk.laporan.eksport') && (
                    <div className="flex gap-2">
                        <Button size="sm" variant="outline" onClick={() => eksport('pdf')}>
                            Eksport PDF
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => eksport('xlsx')}>
                            Eksport Excel
                        </Button>
                    </div>
                )}
            </Card>

            <Card title={data?.tajuk ?? 'Laporan'}>
                {error && <p className="text-body-sm text-txt-danger">{ralat(error)}</p>}
                {ringkasan.length > 0 && (
                    <dl className="flex flex-wrap gap-x-6 gap-y-1 text-body-sm">
                        {ringkasan.map(([k, v]) => (
                            <div key={k}>
                                <dt className="inline text-txt-black-500">{k.replaceAll('_', ' ')}: </dt>
                                <dd className="inline font-medium">{String(v)}</dd>
                            </div>
                        ))}
                    </dl>
                )}
                <Table head={<>{(data?.kepala ?? []).map((h) => <th key={h}>{h}</th>)}</>}>
                    {isLoading ? (
                        <Kosong colSpan={8}>Memuatkan…</Kosong>
                    ) : (data?.baris ?? []).length === 0 ? (
                        <Kosong colSpan={Math.max(1, data?.kepala.length ?? 1)}>Tiada data.</Kosong>
                    ) : (
                        data?.baris.slice(0, 500).map((r, i) => (
                            <tr key={i}>
                                {r.map((c, j) => (
                                    <td key={j} className="whitespace-nowrap">
                                        {c ?? '—'}
                                    </td>
                                ))}
                            </tr>
                        ))
                    )}
                </Table>
                {(data?.baris.length ?? 0) > 500 && <p className="text-body-xs text-txt-black-500">Memaparkan 500 baris pertama — eksport untuk senarai penuh.</p>}
            </Card>
        </PageContainer>
    );
}
