'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Pagination } from '@/components/ui/Pagination';
import { Table } from '@/components/ui/Table';
import { astApi, ralat, rm, STATUS_KERJA_VARIANT, tarikh, useRujukan } from '@/lib/ast';
import { Card, Field, inputClass, Kosong, Penapis, Select } from '../_components/common';

/** SRS-AST-14/17 work orders — officers in scope; contractors see only their own (enforced by the API). */
export default function SenaraiKerja() {
    const { data: ref } = useRujukan();
    const [f, setF] = useState({ status: 'terbuka', kod_daerah: '', sumber: '', q: '' });
    const [page, setPage] = useState(1);
    const set = (k: keyof typeof f) => (v: string) => { setF((x) => ({ ...x, [k]: v })); setPage(1); };
    const { data, error } = useQuery({ queryKey: ['ast', 'kerja', f, page], queryFn: () => astApi.kerja({ ...f, page }) });

    return (
        <PageContainer>
            <Penapis>
                <Field label="Carian" htmlFor="k-q"><input id="k-q" className={inputClass} value={f.q} onChange={(e) => set('q')(e.target.value)} placeholder="No. rujukan / keterangan" /></Field>
                <Field label="Status" htmlFor="k-s"><Select id="k-s" value={f.status} onChange={set('status')} options={[{ kod: 'terbuka', nama: 'Terbuka' }, { kod: 'tertunggak', nama: 'Terbuka melepasi tarikh' }, ...(ref?.status_kerja ?? [])]} empty="Semua" /></Field>
                <Field label="Daerah" htmlFor="k-d"><Select id="k-d" value={f.kod_daerah} onChange={set('kod_daerah')} options={ref?.daerah ?? []} empty="Semua daerah" /></Field>
                <Field label="Sumber" htmlFor="k-sb"><Select id="k-sb" value={f.sumber} onChange={set('sumber')} options={[{ kod: 'manual', nama: 'Manual' }, { kod: 'jadual', nama: 'Jadual berkala' }, { kod: 'ptn', nama: 'Pemantauan Tanah' }, { kod: 'pemeriksaan', nama: 'Pemeriksaan aset' }]} empty="Semua" /></Field>
            </Penapis>
            <Card title={`Kerja penyelenggaraan (${data?.meta.total ?? 0})`}>
                {error && <p className="text-body-sm text-txt-danger">{ralat(error)}</p>}
                {data && data.data.length === 0 ? <Kosong>Tiada kerja.</Kosong> : (
                    <Table head={<><th>No. rujukan</th><th>Aset</th><th>Jenis</th><th>Dijadual</th><th>Pelaksana</th><th>Kos</th><th>Status</th></>}>
                        {(data?.data ?? []).map((k) => (
                            <tr key={k.id}>
                                <td className="whitespace-nowrap"><Link href={`/admin/ast/kerja/${k.id}`} className="text-txt-primary hover:underline">{k.no_rujukan}</Link></td>
                                <td>{k.aset?.nama_aset ?? '—'}</td>
                                <td>{k.jenis_nama}<span className="block text-body-xs text-txt-black-500">{k.jenis_kerja_nama} · {k.keutamaan_nama}</span></td>
                                <td className="whitespace-nowrap">{tarikh(k.tarikh_dijadual)}</td>
                                <td>{k.nama_syarikat_pelaksana ?? '—'}</td>
                                <td className="whitespace-nowrap">{rm(k.kos ?? k.kos_anggaran)}</td>
                                <td><Badge variant={STATUS_KERJA_VARIANT[k.status] ?? 'neutral'}>{k.status_nama}</Badge>{k.tertunggak && <span className="ml-1"><Badge variant="danger">Lewat</Badge></span>}</td>
                            </tr>
                        ))}
                    </Table>
                )}
                {data && data.meta.last_page > 1 && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />}
            </Card>
        </PageContainer>
    );
}
