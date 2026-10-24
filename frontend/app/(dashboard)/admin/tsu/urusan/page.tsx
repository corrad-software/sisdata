'use client';

import { Suspense, useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Pagination } from '@/components/ui/Pagination';
import { Badge } from '@/components/ui/Badge';
import { LinkButton } from '@/components/ui/Button';
import { ralat, tarikh, tsuApi, useCan, useRujukan } from '@/lib/tsu';
import { Card, Field, inputClass, Kosong, Ralat, Select, Status } from '../_components/common';

/** SRS-TSU-03/26: register of Land Office matters with current stage and lateness. */
function Senarai() {
    const params = useSearchParams();
    const can = useCan();
    const { data: ref } = useRujukan();
    const [f, setF] = useState<Record<string, string>>({
        q: '',
        jenis_urusan: params.get('jenis_urusan') ?? '',
        status: params.get('status') ?? '',
        terbuka: params.get('terbuka') ?? '',
        lewat: params.get('lewat') ?? '',
        saya: '',
        lot_id: params.get('lot_id') ?? '',
    });
    const [page, setPage] = useState(1);
    const set = (k: string) => (v: string) => {
        setF((x) => ({ ...x, [k]: v }));
        setPage(1);
    };
    const p = { ...f, page };
    const { data, isLoading, error } = useQuery({ queryKey: ['tsu', 'senarai', p], queryFn: () => tsuApi.senarai(p) });

    return (
        <PageContainer>
            <Card title="Urusan tanah" actions={can('tsu.urusan.cipta') && <LinkButton href="/admin/tsu/urusan/baharu" size="sm">Daftar urusan</LinkButton>}>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Field label="Carian" htmlFor="u-q" className="sm:col-span-2">
                        <input id="u-q" type="search" className={inputClass} placeholder="No. rujukan MAIS / PTD atau tajuk" value={f.q} onChange={(e) => set('q')(e.target.value)} />
                    </Field>
                    <Field label="Jenis urusan" htmlFor="u-j">
                        <Select id="u-j" value={f.jenis_urusan} onChange={set('jenis_urusan')} options={ref?.jenis_urusan ?? []} empty="Semua" />
                    </Field>
                    <Field label="Status" htmlFor="u-s">
                        <Select id="u-s" value={f.status} onChange={set('status')} options={ref?.status_urusan ?? []} empty="Semua" />
                    </Field>
                </div>
                <div className="flex flex-wrap gap-4 text-body-sm">
                    {([['terbuka', 'Dalam proses sahaja'], ['lewat', 'Melepasi sasaran peringkat'], ['saya', 'Urusan saya']] as const).map(([k, l]) => (
                        <label key={k} className="inline-flex items-center gap-2">
                            <input type="checkbox" checked={f[k] === '1'} onChange={(e) => set(k)(e.target.checked ? '1' : '')} />
                            {l}
                        </label>
                    ))}
                </div>
                {error && <Ralat>{ralat(error)}</Ralat>}
                {isLoading ? (
                    <Kosong>Memuatkan…</Kosong>
                ) : !data?.data.length ? (
                    <Kosong>Tiada urusan.</Kosong>
                ) : (
                    <>
                        <Table head={<><th>No. rujukan</th><th>Jenis</th><th>Lot</th><th>Peringkat semasa</th><th>Status</th><th>Status PTD</th><th>Sasaran siap</th></>}>
                            {data.data.map((u) => (
                                <tr key={u.id}>
                                    <td>
                                        <Link href={`/admin/tsu/urusan/${u.id}`} className="font-medium text-txt-primary hover:underline">{u.no_rujukan_mais}</Link>
                                        {u.sintetik && <span className="ml-2"><Badge>Sintetik</Badge></span>}
                                    </td>
                                    <td>{u.jenis_nama}</td>
                                    <td>{u.no_lot ?? '—'}</td>
                                    <td>
                                        {u.peringkat_semasa_nama ?? '—'} {u.peringkat_lewat && <Badge variant="danger">Lewat</Badge>}
                                    </td>
                                    <td><Status kod={u.status} nama={u.status_nama} /></td>
                                    <td>{u.status_ptd_nama ?? '—'}</td>
                                    <td>{tarikh(u.tarikh_sasaran_siap)} {u.lewat_sasaran && <Badge variant="warning">Lewat</Badge>}</td>
                                </tr>
                            ))}
                        </Table>
                        <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />
                    </>
                )}
            </Card>
        </PageContainer>
    );
}

export default function Page() {
    return (
        <Suspense>
            <Senarai />
        </Suspense>
    );
}
