'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button, LinkButton } from '@/components/ui/Button';
import { Pagination } from '@/components/ui/Pagination';
import { pjkApi, ralat, tarikh, useCan, useRujukan } from '@/lib/pjk';
import { Card, Field, inputClass, Kosong, Select, Sintetik, Status } from '../_components/common';

/** SRS-PJK-01/02: contract register with filters by status, location, category, tenant, expiry and arrears. */
export default function KontrakListPage() {
    const can = useCan();
    const { data: ref } = useRujukan();
    const [q, setQ] = useState('');
    const [debounced, setDebounced] = useState('');
    const [f, setF] = useState<Record<string, string>>({});
    const [page, setPage] = useState(1);

    useEffect(() => {
        const t = setTimeout(() => {
            setDebounced(q);
            setPage(1);
        }, 350);
        return () => clearTimeout(t);
    }, [q]);

    const params = { ...f, q: debounced, page };
    const { data, isLoading, error } = useQuery({ queryKey: ['pjk', 'kontrak', params], queryFn: () => pjkApi.kontrak(params) });
    const rows = data?.data ?? [];
    const set = (k: string, v: string) => {
        setF((p) => ({ ...p, [k]: v }));
        setPage(1);
    };

    return (
        <PageContainer>
            <Card>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <label htmlFor="pjk-q" className="sr-only">
                        Kata kunci
                    </label>
                    <input id="pjk-q" data-panduan="pjk-carian" type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari no. perjanjian, penyewa atau premis…" className={`${inputClass} sm:flex-1`} />
                    {can('pjk.kontrak.cipta') && <LinkButton href="/admin/pjk/kontrak/baharu" data-panduan="pjk-daftar">Daftar perjanjian</LinkButton>}
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3" data-panduan="pjk-penapis">
                    <Field label="Status" htmlFor="k-status">
                        <Select id="k-status" value={f.status ?? ''} onChange={(v) => set('status', v)} options={ref?.status_kontrak ?? []} empty="Semua" />
                    </Field>
                    <Field label="Daerah" htmlFor="k-daerah">
                        <Select id="k-daerah" value={f.kod_daerah ?? ''} onChange={(v) => set('kod_daerah', v)} options={ref?.daerah ?? []} empty="Semua" />
                    </Field>
                    <Field label="Kategori hartanah" htmlFor="k-kat">
                        <Select id="k-kat" value={f.kategori_hartanah ?? ''} onChange={(v) => set('kategori_hartanah', v)} options={ref?.kategori_hartanah ?? []} empty="Semua" />
                    </Field>
                    <Field label="Tamat dalam (hari)" htmlFor="k-tamat">
                        <input id="k-tamat" type="number" min={1} className={inputClass} value={f.tamat_dalam_hari ?? ''} onChange={(e) => set('tamat_dalam_hari', e.target.value)} />
                    </Field>
                    <div className="flex items-end gap-3">
                        <label className="inline-flex items-center gap-2 text-body-sm text-txt-black-700">
                            <input type="checkbox" className="rounded border-otl-gray-300" checked={f.tunggakan === '1'} onChange={(e) => set('tunggakan', e.target.checked ? '1' : '')} />
                            Ada tunggakan
                        </label>
                        <Button size="sm" variant="outline" onClick={() => setF({})}>
                            Kosongkan
                        </Button>
                    </div>
                </div>
                <p className="text-body-sm text-txt-black-500" aria-live="polite">
                    {error ? <span className="text-txt-danger">{ralat(error)}</span> : data ? `${data.meta.total} perjanjian` : 'Memuatkan…'}
                </p>
                <Table
                    panduan="pjk-senarai"
                    head={
                        <>
                            <th>Perjanjian</th>
                            <th className="hidden md:table-cell">Premis</th>
                            <th className="hidden lg:table-cell">Tempoh</th>
                            <th className="hidden sm:table-cell">Kadar</th>
                            <th>Status</th>
                        </>
                    }
                >
                    {isLoading ? (
                        <Kosong colSpan={5}>Memuatkan…</Kosong>
                    ) : rows.length === 0 ? (
                        <Kosong colSpan={5}>Tiada perjanjian sepadan.</Kosong>
                    ) : (
                        rows.map((k) => (
                            <tr key={k.id}>
                                <td className="max-w-[13rem] sm:max-w-xs">
                                    <Link href={`/admin/pjk/kontrak/${k.id}`} className="font-medium text-txt-primary hover:underline break-words">
                                        {k.no_kontrak}
                                    </Link>{' '}
                                    <Sintetik show={k.sintetik} />
                                    <p className="text-body-xs text-txt-black-500 break-words">{k.penyewa}</p>
                                </td>
                                <td className="hidden md:table-cell">
                                    <p>{k.premis?.nama_premis ?? '—'}</p>
                                    <p className="text-body-xs text-txt-black-500">
                                        {k.kategori_hartanah_nama} · {k.kod_daerah}
                                    </p>
                                </td>
                                <td className="hidden lg:table-cell whitespace-nowrap">
                                    {tarikh(k.tarikh_mula)} – {tarikh(k.tarikh_tamat)}
                                </td>
                                <td className="hidden sm:table-cell whitespace-nowrap">{k.kadar.rm}</td>
                                <td>
                                    <Status kod={k.status} nama={k.status_nama} />
                                </td>
                            </tr>
                        ))
                    )}
                </Table>
                {data && data.meta.last_page > 1 && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />}
            </Card>
        </PageContainer>
    );
}
