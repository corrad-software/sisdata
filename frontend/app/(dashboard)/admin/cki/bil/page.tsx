'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { Pagination } from '@/components/ui/Pagination';
import { ckiApi, muatTurun, ralat, tarikh, useCan, useRujukan } from '@/lib/cki';
import { Card, Field, inputClass, Kosong, Select, Sintetik, Status } from '../_components/common';
import { Penapis } from '../_components/Penapis';
import { BilManual } from '../_components/BilManual';

/** SRS-CKI-02: tax bills — every source (API / manual / import), filterable, exportable. */
export default function BilListPage() {
    const can = useCan();
    const { data: ref } = useRujukan();
    const [q, setQ] = useState('');
    const [debounced, setDebounced] = useState('');
    const search = useSearchParams();
    const [f, setF] = useState<Record<string, string>>(() => Object.fromEntries(['status', 'kod_daerah', 'jenis_cukai', 'tahun', 'lot_id'].flatMap((k) => (search.get(k) ? [[k, search.get(k) as string]] : []))));
    const [page, setPage] = useState(1);
    const [manual, setManual] = useState(false);

    useEffect(() => {
        const t = setTimeout(() => {
            setDebounced(q);
            setPage(1);
        }, 350);
        return () => clearTimeout(t);
    }, [q]);

    const params = { ...f, q: debounced, page };
    const { data, isLoading, error } = useQuery({ queryKey: ['cki', 'bil', params], queryFn: () => ckiApi.bil(params) });
    const rows = data?.data ?? [];
    const set = (k: string, v: string) => {
        setF((p) => ({ ...p, [k]: v }));
        setPage(1);
    };

    return (
        <PageContainer>
            <Card>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <label htmlFor="cki-q" className="sr-only">
                        Kata kunci
                    </label>
                    <input id="cki-q" type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari no. bil atau no. lot…" className={`${inputClass} sm:flex-1`} />
                    <div className="flex flex-wrap gap-2">
                        {can('cki.bil.cipta') && <Button onClick={() => setManual(true)}>Rekod bil manual</Button>}
                        {can('cki.bil.eksport') && (
                            <Button variant="outline" onClick={() => muatTurun('/cki/bil-eksport', { ...f, q: debounced }, 'bil-cukai.xlsx').catch((e) => toast.error(ralat(e)))}>
                                Eksport Excel
                            </Button>
                        )}
                    </div>
                </div>
                <Penapis value={f} onChange={set} />
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <Field label="Status" htmlFor="b-status">
                        <Select id="b-status" value={f.status ?? ''} onChange={(v) => set('status', v)} options={ref?.status_bayaran ?? []} empty="Semua" />
                    </Field>
                    <Field label="Sumber rekod" htmlFor="b-sumber">
                        <Select id="b-sumber" value={f.sumber ?? ''} onChange={(v) => set('sumber', v)} options={ref?.sumber_rekod ?? []} empty="Semua" />
                    </Field>
                    <div className="flex items-end">
                        <Button size="sm" variant="outline" onClick={() => setF({})}>
                            Kosongkan
                        </Button>
                    </div>
                </div>
                <p className="text-body-sm text-txt-black-500" aria-live="polite">
                    {error ? <span className="text-txt-danger">{ralat(error)}</span> : data ? `${data.meta.total} bil` : 'Memuatkan…'}
                </p>
                <Table
                    head={
                        <>
                            <th>Bil</th>
                            <th className="hidden md:table-cell">Lot / agensi</th>
                            <th className="hidden lg:table-cell">Tarikh akhir</th>
                            <th>Baki</th>
                            <th>Status</th>
                        </>
                    }
                >
                    {isLoading ? (
                        <Kosong colSpan={5}>Memuatkan…</Kosong>
                    ) : rows.length === 0 ? (
                        <Kosong colSpan={5}>Tiada bil sepadan.</Kosong>
                    ) : (
                        rows.map((b) => (
                            <tr key={b.id}>
                                <td className="max-w-[12rem] sm:max-w-xs">
                                    <Link href={`/admin/cki/bil/${b.id}`} className="font-medium text-txt-primary hover:underline break-words">
                                        {b.no_bil}
                                    </Link>{' '}
                                    <Sintetik show={b.sintetik} />
                                    <p className="text-body-xs text-txt-black-500">
                                        {b.jenis_cukai_nama} {b.tahun_taksiran} · {b.sumber_rekod_nama}
                                    </p>
                                </td>
                                <td className="hidden md:table-cell">
                                    <p>{b.no_lot ?? '—'}</p>
                                    <p className="text-body-xs text-txt-black-500">
                                        {b.kod_agensi} · {b.kod_daerah}
                                    </p>
                                </td>
                                <td className="hidden lg:table-cell whitespace-nowrap">{tarikh(b.tarikh_akhir)}</td>
                                <td className="whitespace-nowrap">{b.baki.rm}</td>
                                <td>
                                    <Status kod={b.status_bayaran} nama={b.status_bayaran_nama} />
                                </td>
                            </tr>
                        ))
                    )}
                </Table>
                {data && data.meta.last_page > 1 && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />}
            </Card>
            {manual && <BilManual open={manual} onClose={() => setManual(false)} />}
        </PageContainer>
    );
}
