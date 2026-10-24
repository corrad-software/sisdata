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
import { kesApi, rm, tarikh, useCan, useRujukan } from '@/lib/kes';
import { Card, Field, inputClass, RalatAkses, Select, StatusKes } from '../_components/common';

const TOGOL: Array<[string, string]> = [
    ['aktif', 'Kes aktif'],
    ['tanpa_tarikh', 'Tanpa tarikh prosiding berikutnya'],
    ['arkib', 'Diarkib'],
];

/** SRS-KES-02/21: case register, filterable. */
function Daftar() {
    const params = useSearchParams();
    const can = useCan();
    const { data: ref } = useRujukan();
    const [f, setF] = useState<Record<string, string>>({
        q: '',
        status: params.get('status') ?? '',
        jenis: params.get('jenis') ?? '',
        kod_daerah: '',
        peringkat_mahkamah: '',
        id_pegawai: '',
        lot_id: params.get('lot_id') ?? '',
        ...Object.fromEntries(TOGOL.map(([k]) => [k, params.get(k) ?? ''])),
    });
    const [page, setPage] = useState(1);
    const set = (k: string) => (v: string) => {
        setF((x) => ({ ...x, [k]: v }));
        setPage(1);
    };
    const p = { ...f, page };
    const { data, isLoading, error } = useQuery({ queryKey: ['kes', 'senarai', p], queryFn: () => kesApi.senarai(p) });

    return (
        <PageContainer>
            <Card
                title="Daftar kes mahkamah"
                actions={
                    can('kes.kes.cipta') && (
                        <LinkButton href="/admin/kes/kes/baharu" size="sm">
                            Daftar kes
                        </LinkButton>
                    )
                }
            >
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Field label="Carian" htmlFor="k-q" className="sm:col-span-2">
                        <input id="k-q" type="search" className={inputClass} placeholder="No. rujukan, no. kes atau tajuk" value={f.q} onChange={(e) => set('q')(e.target.value)} />
                    </Field>
                    <Field label="Status" htmlFor="k-st">
                        <Select id="k-st" value={f.status} onChange={set('status')} options={ref?.status ?? []} empty="Semua" />
                    </Field>
                    <Field label="Jenis kes" htmlFor="k-je">
                        <Select id="k-je" value={f.jenis} onChange={set('jenis')} options={ref?.jenis ?? []} empty="Semua" />
                    </Field>
                    <Field label="Peringkat mahkamah" htmlFor="k-pm">
                        <Select id="k-pm" value={f.peringkat_mahkamah} onChange={set('peringkat_mahkamah')} options={ref?.peringkat_mahkamah ?? []} empty="Semua" />
                    </Field>
                    <Field label="Daerah" htmlFor="k-da">
                        <Select id="k-da" value={f.kod_daerah} onChange={set('kod_daerah')} options={ref?.daerah ?? []} empty="Semua" />
                    </Field>
                    <Field label="Pegawai bertanggungjawab" htmlFor="k-pg">
                        <Select id="k-pg" value={f.id_pegawai} onChange={set('id_pegawai')} options={(ref?.pegawai ?? []).map((u) => ({ kod: String(u.id), nama: u.nama }))} empty="Semua" />
                    </Field>
                </div>
                <div className="flex flex-wrap gap-2" role="group" aria-label="Penapis pantas">
                    {TOGOL.map(([k, l]) => (
                        <button
                            key={k}
                            type="button"
                            aria-pressed={f[k] === '1'}
                            onClick={() => set(k)(f[k] === '1' ? '' : '1')}
                            className={`rounded-full border px-3 py-1 text-body-xs font-medium ${f[k] === '1' ? 'border-primary-600 bg-bg-primary-50 text-txt-primary' : 'border-otl-gray-300 text-txt-black-700 hover:bg-bg-washed'}`}
                        >
                            {l}
                        </button>
                    ))}
                    {f.lot_id && (
                        <button type="button" onClick={() => set('lot_id')('')} className="rounded-full border border-primary-600 bg-bg-primary-50 px-3 py-1 text-body-xs font-medium text-txt-primary">
                            Lot dipilih ✕
                        </button>
                    )}
                </div>
                {error ? (
                    <RalatAkses error={error} />
                ) : (
                    <p className="text-body-sm text-txt-black-500" aria-live="polite">
                        {data ? `${data.meta.total} kes` : 'Memuatkan…'}
                    </p>
                )}
                <div className="overflow-x-auto">
                    <Table
                        head={
                            <>
                                <th>Kes</th>
                                <th className="hidden md:table-cell">Mahkamah</th>
                                <th>Status</th>
                                <th className="hidden sm:table-cell">Prosiding berikutnya</th>
                                <th className="hidden lg:table-cell">Pegawai</th>
                                <th className="hidden lg:table-cell text-right">Kos guaman</th>
                            </>
                        }
                    >
                        {isLoading ? (
                            <tr>
                                <td colSpan={6} className="py-6 text-center text-txt-black-500">
                                    Memuatkan…
                                </td>
                            </tr>
                        ) : (data?.data ?? []).length === 0 ? (
                            <tr>
                                <td colSpan={6} className="py-6 text-center text-txt-black-500">
                                    Tiada kes.
                                </td>
                            </tr>
                        ) : (
                            data?.data.map((k) => (
                                <tr key={k.id}>
                                    <td className="max-w-xs">
                                        <Link href={`/admin/kes/kes/${k.id}`} className="font-medium text-txt-primary hover:underline">
                                            {k.no_rujukan}
                                        </Link>
                                        <p className="text-body-xs text-txt-black-700 break-words">{k.tajuk}</p>
                                        <p className="text-body-xs text-txt-black-500">
                                            {k.jenis_nama} · {k.no_kes}
                                        </p>
                                        {k.sintetik && (
                                            <span className="mt-1 inline-block">
                                                <Badge variant="neutral">Sintetik</Badge>
                                            </span>
                                        )}
                                    </td>
                                    <td className="hidden md:table-cell">
                                        {k.mahkamah}
                                        <p className="text-body-xs text-txt-black-500">{[k.peringkat_nama, k.daerah].filter(Boolean).join(' · ')}</p>
                                    </td>
                                    <td>
                                        <StatusKes k={k} />
                                    </td>
                                    <td className="hidden sm:table-cell whitespace-nowrap">{tarikh(k.tarikh_prosiding_berikutnya)}</td>
                                    <td className="hidden lg:table-cell">{k.pegawai?.nama ?? <span className="text-txt-black-500">Belum ditugaskan</span>}</td>
                                    <td className="hidden lg:table-cell text-right whitespace-nowrap">{rm(k.kos_guaman)}</td>
                                </tr>
                            ))
                        )}
                    </Table>
                </div>
                {data && data.meta.last_page > 1 && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />}
            </Card>
        </PageContainer>
    );
}

export default function DaftarKesPage() {
    return (
        <Suspense>
            <Daftar />
        </Suspense>
    );
}
