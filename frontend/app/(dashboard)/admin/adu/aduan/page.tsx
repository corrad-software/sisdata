'use client';

import { Suspense, useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Pagination } from '@/components/ui/Pagination';
import { LinkButton } from '@/components/ui/Button';
import { aduApi, ralat, tarikhMasa, useCan, useRujukan } from '@/lib/adu';
import { BakiSla, BenderaAduan, Card, Field, inputClass, Keutamaan, Select, StatusAduan } from '../_components/common';

const TOGOL: Array<[string, string]> = [
    ['tugasan_saya', 'Tugasan saya'],
    ['belum_ditugaskan', 'Belum ditugaskan'],
    ['melepasi_sla', 'Melepasi SLA'],
    ['tanpa_nama', 'Tanpa nama'],
    ['bukan_tanah_mais', 'Bukan tanah MAIS'],
];

/** SRS-ADU-01/12: the single complaint queue with remaining SLA time. */
function Barisan() {
    const params = useSearchParams();
    const can = useCan();
    const { data: ref } = useRujukan();
    const [f, setF] = useState<Record<string, string>>({
        q: '',
        status: params.get('status') ?? 'terbuka',
        kategori: '',
        kod_daerah: '',
        saluran: '',
        keutamaan: '',
        susun: 'sla',
        lot_id: params.get('lot_id') ?? '',
        ...Object.fromEntries(TOGOL.map(([k]) => [k, params.get(k) ?? ''])),
    });
    const [page, setPage] = useState(1);
    const set = (k: string) => (v: string) => {
        setF((x) => ({ ...x, [k]: v }));
        setPage(1);
    };
    const p = { ...f, page };
    const { data, isLoading, error } = useQuery({ queryKey: ['adu', 'senarai', p], queryFn: () => aduApi.senarai(p) });
    const statusOptions = [{ kod: 'terbuka', nama: 'Semua terbuka' }, ...(ref?.status ?? [])];

    return (
        <PageContainer>
            <Card
                title="Barisan aduan"
                actions={
                    can('adu.aduan.cipta') && (
                        <LinkButton href="/admin/adu/aduan/baharu" size="sm" data-panduan="adu-rekod">
                            Rekod aduan
                        </LinkButton>
                    )
                }
            >
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Field label="Carian" htmlFor="b-q" className="sm:col-span-2">
                        <input id="b-q" data-panduan="adu-carian" type="search" className={inputClass} placeholder="No. rujukan, keterangan atau lokasi" value={f.q} onChange={(e) => set('q')(e.target.value)} />
                    </Field>
                    <Field label="Status" htmlFor="b-st">
                        <Select id="b-st" value={f.status} onChange={set('status')} options={statusOptions} empty="Semua" />
                    </Field>
                    <Field label="Susun" htmlFor="b-su">
                        <Select
                            id="b-su"
                            value={f.susun}
                            onChange={set('susun')}
                            options={[
                                { kod: 'sla', nama: 'Baki SLA (paling hampir)' },
                                { kod: 'keutamaan', nama: 'Keutamaan' },
                                { kod: 'tarikh_terima', nama: 'Terkini diterima' },
                            ]}
                        />
                    </Field>
                    <Field label="Kategori" htmlFor="b-kat">
                        <Select id="b-kat" value={f.kategori} onChange={set('kategori')} options={ref?.kategori ?? []} empty="Semua" />
                    </Field>
                    <Field label="Daerah" htmlFor="b-dae">
                        <Select id="b-dae" value={f.kod_daerah} onChange={set('kod_daerah')} options={ref?.daerah ?? []} empty="Semua" />
                    </Field>
                    <Field label="Saluran" htmlFor="b-sal">
                        <Select id="b-sal" value={f.saluran} onChange={set('saluran')} options={ref?.saluran ?? []} empty="Semua" />
                    </Field>
                    <Field label="Keutamaan" htmlFor="b-ku">
                        <Select id="b-ku" value={f.keutamaan} onChange={set('keutamaan')} options={ref?.keutamaan ?? []} empty="Semua" />
                    </Field>
                </div>
                <div className="flex flex-wrap gap-2" role="group" aria-label="Penapis pantas" data-panduan="adu-penapis-pantas">
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
                <p className="text-body-sm text-txt-black-500" aria-live="polite">
                    {error ? <span className="text-txt-danger">{ralat(error)}</span> : data ? `${data.meta.total} aduan` : 'Memuatkan…'}
                </p>
                <div className="overflow-x-auto">
                    <Table
                        panduan="adu-senarai"
                        head={
                            <>
                                <th>Aduan</th>
                                <th className="hidden md:table-cell">Lokasi</th>
                                <th>Status</th>
                                <th className="hidden sm:table-cell">Baki SLA</th>
                                <th className="hidden lg:table-cell">Pegawai</th>
                            </>
                        }
                    >
                        {isLoading ? (
                            <tr>
                                <td colSpan={5} className="py-6 text-center text-txt-black-500">
                                    Memuatkan…
                                </td>
                            </tr>
                        ) : (data?.data ?? []).length === 0 ? (
                            <tr>
                                <td colSpan={5} className="py-6 text-center text-txt-black-500">
                                    Tiada aduan.
                                </td>
                            </tr>
                        ) : (
                            data?.data.map((a) => (
                                <tr key={a.id}>
                                    <td className="max-w-xs">
                                        <Link href={`/admin/adu/aduan/${a.id}`} className="font-medium text-txt-primary hover:underline">
                                            {a.no_rujukan_aduan}
                                        </Link>
                                        <p className="text-body-xs text-txt-black-700">
                                            {a.kategori_nama} · {a.saluran_nama}
                                        </p>
                                        <p className="text-body-xs text-txt-black-500">{tarikhMasa(a.tarikh_terima)}</p>
                                        <div className="mt-1 flex flex-wrap gap-1">
                                            <Keutamaan a={a} />
                                            <BenderaAduan a={a} />
                                        </div>
                                        <p className="sm:hidden mt-1">
                                            <BakiSla a={a} />
                                        </p>
                                    </td>
                                    <td className="hidden md:table-cell">
                                        {a.no_lot ?? (a.status_bukan_tanah_mais ? 'Luar lot MAIS' : '—')}
                                        <p className="text-body-xs text-txt-black-500">{[a.mukim, a.daerah].filter(Boolean).join(', ') || '—'}</p>
                                    </td>
                                    <td>
                                        <StatusAduan a={a} />
                                    </td>
                                    <td className="hidden sm:table-cell whitespace-nowrap">{a.status === 'ditutup' || a.status === 'pendua' ? '—' : <BakiSla a={a} />}</td>
                                    <td className="hidden lg:table-cell">{a.pegawai?.nama ?? <span className="text-txt-black-500">Belum ditugaskan</span>}</td>
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

export default function BarisanPage() {
    return (
        <Suspense>
            <Barisan />
        </Suspense>
    );
}
