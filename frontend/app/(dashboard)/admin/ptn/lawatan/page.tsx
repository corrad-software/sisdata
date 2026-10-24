'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Pagination } from '@/components/ui/Pagination';
import { ptnApi, ralat, tarikh, useRujukan } from '@/lib/ptn';
import { Card, Field, inputClass, Kosong, Penapis, Select } from '../_components/common';

/** Visit records in the viewer's scope (supervisor / district validating officer). */
export default function LawatanList() {
    const { data: ref } = useRujukan();
    const [f, setF] = useState({ kod_daerah: '', status: '', q: '', perlu_semakan: false });
    const [page, setPage] = useState(1);
    const set = (k: keyof typeof f) => (v: string | boolean) => { setF((x) => ({ ...x, [k]: v })); setPage(1); };
    const { data, error } = useQuery({ queryKey: ['ptn', 'lawatan', f, page], queryFn: () => ptnApi.lawatan({ ...f, page }) });
    const variant = (s: string) => ({ disahkan: 'success', dihantar: 'info', konflik: 'danger', dikembalikan: 'warning' } as const)[s as 'disahkan'] ?? 'neutral';

    return (
        <PageContainer>
            <Penapis>
                <Field label="No. rujukan" htmlFor="l-q"><input id="l-q" className={inputClass} value={f.q} onChange={(e) => set('q')(e.target.value)} placeholder="PTN-2026-…" /></Field>
                <Field label="Daerah" htmlFor="l-d"><Select id="l-d" value={f.kod_daerah} onChange={set('kod_daerah')} options={ref?.daerah ?? []} empty="Semua daerah" /></Field>
                <Field label="Status" htmlFor="l-s"><Select id="l-s" value={f.status} onChange={set('status')} options={ref?.status_lawatan ?? []} empty="Semua status" /></Field>
                <label className="flex items-end gap-2 text-body-sm pb-2"><input type="checkbox" checked={f.perlu_semakan} onChange={(e) => set('perlu_semakan')(e.target.checked)} /> Gambar perlu semakan penyelia</label>
            </Penapis>
            <Card title={`Rekod lawatan (${data?.meta.total ?? 0})`}>
                {error && <p className="text-body-sm text-txt-danger">{ralat(error)}</p>}
                {data && data.data.length === 0 ? <Kosong>Tiada rekod lawatan.</Kosong> : (
                    <Table head={<><th>No. rujukan</th><th>Tarikh</th><th>Lot</th><th>Daerah</th><th>Pegawai</th><th>Keadaan</th><th>Dalam sempadan</th><th>Status</th></>}>
                        {(data?.data ?? []).map((l) => (
                            <tr key={l.id}>
                                <td><Link className="text-txt-primary hover:underline" href={`/admin/ptn/lawatan/${l.id}`}>{l.no_rujukan}</Link></td>
                                <td className="whitespace-nowrap">{tarikh(l.tarikh_lawatan)}</td>
                                <td>{l.no_lot ?? '—'}</td>
                                <td>{l.daerah}</td>
                                <td>{l.pegawai?.nama ?? '—'}</td>
                                <td>{l.keadaan_nama ?? '—'}</td>
                                <td>{l.dalam_lingkungan === null ? '—' : l.dalam_lingkungan ? <Badge variant="success">Ya</Badge> : <Badge variant="danger">Tidak ({l.jarak_dari_lot_m} m)</Badge>}</td>
                                <td><Badge variant={variant(l.status_lawatan)}>{l.status_nama}</Badge>{l.sintetik && <span className="ml-1"><Badge>Sintetik</Badge></span>}</td>
                            </tr>
                        ))}
                    </Table>
                )}
                {data && data.meta.last_page > 1 && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />}
            </Card>
        </PageContainer>
    );
}
