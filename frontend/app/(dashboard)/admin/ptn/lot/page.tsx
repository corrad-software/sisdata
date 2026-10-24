'use client';

import { Suspense, useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Pagination } from '@/components/ui/Pagination';
import { ptnApi, ralat, tarikh, useRujukan, type KodWarna } from '@/lib/ptn';
import { Card, Field, inputClass, KodWarnaBadge, Kosong, Legenda, Penapis, Select } from '../_components/common';

/** SRS-PTN-16: colour-coded lot list (same colours + text as the map, card and dashboard). */
function LotList() {
    const sp = useSearchParams();
    const { data: ref } = useRujukan();
    const [f, setF] = useState({ kod_daerah: '', kod_warna: sp.get('kod_warna') ?? '', q: '' });
    const [page, setPage] = useState(1);
    const set = (k: keyof typeof f) => (v: string) => { setF((x) => ({ ...x, [k]: v })); setPage(1); };
    const { data, error } = useQuery({ queryKey: ['ptn', 'lot', f, page], queryFn: () => ptnApi.lot({ ...f, page }) });

    return (
        <PageContainer>
            <Penapis>
                <Field label="No. lot" htmlFor="o-q"><input id="o-q" className={inputClass} value={f.q} onChange={(e) => set('q')(e.target.value)} /></Field>
                <Field label="Daerah" htmlFor="o-d"><Select id="o-d" value={f.kod_daerah} onChange={set('kod_daerah')} options={ref?.daerah ?? []} empty="Semua daerah" /></Field>
                <Field label="Kod warna" htmlFor="o-w"><Select id="o-w" value={f.kod_warna} onChange={set('kod_warna')} options={(ref?.warna ?? []).map((w) => ({ kod: w.kod, nama: w.label }))} empty="Semua" /></Field>
                <div className="flex items-end"><Legenda /></div>
            </Penapis>
            <Card title={`Lot dipantau (${data?.meta.total ?? 0})`}>
                {error && <p className="text-body-sm text-txt-danger">{ralat(error)}</p>}
                {data && data.data.length === 0 ? <Kosong>Tiada lot.</Kosong> : (
                    <Table head={<><th>No. lot</th><th>Daerah / Mukim</th><th>Kategori</th><th>Skor risiko</th><th>Kod warna</th><th>Lawatan terakhir</th><th>Isu terbuka</th></>}>
                        {(data?.data ?? []).map((l) => (
                            <tr key={l.lot_id}>
                                <td><Link className="text-txt-primary hover:underline" href={`/admin/ptn/lot/${l.lot_id}`}>{l.no_lot}</Link></td>
                                <td>{l.daerah} / {l.mukim}</td>
                                <td>{l.kategori_guna_tanah ?? '—'}</td>
                                <td>{l.skor_keseluruhan.toFixed(1)}</td>
                                <td><KodWarnaBadge warna={l.kod_warna as KodWarna} label={l.paksa_merah ? 'Merah — penemuan kritikal' : undefined} /></td>
                                <td>{tarikh(l.lawatan_terakhir)}</td>
                                <td>{l.isu_terbuka}</td>
                            </tr>
                        ))}
                    </Table>
                )}
                {data && data.meta.last_page > 1 && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />}
            </Card>
        </PageContainer>
    );
}

export default function Page() {
    return <Suspense><LotList /></Suspense>;
}
