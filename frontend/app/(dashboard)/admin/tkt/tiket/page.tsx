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
import { ralat, tarikhMasa, tktApi, useCan, useRujukan } from '@/lib/tkt';
import { Card, Field, inputClass, Keterukan, Select, SlaBadge, StatusTiket } from '../_components/common';

const TOGOL: Array<[string, string]> = [
    ['melepasi_sla', 'Melepasi SLA'],
    ['belum_ditugaskan', 'Belum ditugaskan'],
];

/** SRS-TKT-17/20: ticket list — own tickets for reporters, assigned / team queue for support. */
function Senarai() {
    const params = useSearchParams();
    const can = useCan();
    const { data: ref } = useRujukan();
    const support = can('tkt.sokongan.lihat', 'tkt.semua.pantau');
    const [f, setF] = useState<Record<string, string>>({
        q: '',
        skop: params.get('skop') ?? '',
        status: params.get('status') ?? '',
        keterukan: params.get('keterukan') ?? '',
        modul: params.get('modul') ?? '',
        kategori: params.get('kategori') ?? '',
        aras: params.get('aras') ?? '',
        susun: 'tarikh_buka',
        ...Object.fromEntries(TOGOL.map(([k]) => [k, params.get(k) ?? ''])),
    });
    const [page, setPage] = useState(1);
    const set = (k: string) => (v: string) => {
        setF((x) => ({ ...x, [k]: v }));
        setPage(1);
    };
    const p = { ...f, page };
    const { data, isLoading, error } = useQuery({ queryKey: ['tkt', 'senarai', p], queryFn: () => tktApi.senarai(p) });

    return (
        <PageContainer>
            <Card
                title={support ? 'Barisan tiket sokongan' : 'Tiket saya'}
                actions={
                    can('tkt.tiket.cipta') && (
                        <LinkButton href="/admin/tkt/tiket/baharu" size="sm">
                            Buka tiket
                        </LinkButton>
                    )
                }
            >
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Field label="Carian" htmlFor="t-q" className="sm:col-span-2">
                        <input id="t-q" type="search" className={inputClass} placeholder="No. tiket atau tajuk" value={f.q} onChange={(e) => set('q')(e.target.value)} />
                    </Field>
                    {support && (
                        <Field label="Skop" htmlFor="t-skop">
                            <Select id="t-skop" value={f.skop} onChange={set('skop')} empty="Semua yang boleh dilihat" options={[{ kod: 'tugasan', nama: 'Ditugaskan kepada saya' }, { kod: 'pasukan', nama: 'Pasukan saya' }, { kod: 'saya', nama: 'Dilaporkan oleh saya' }]} />
                        </Field>
                    )}
                    <Field label="Status" htmlFor="t-st">
                        <Select id="t-st" value={f.status} onChange={set('status')} empty="Semua" options={[{ kod: 'terbuka', nama: 'Semua terbuka' }, ...(ref?.status ?? [])]} />
                    </Field>
                    <Field label="Keterukan" htmlFor="t-sev">
                        <Select id="t-sev" value={f.keterukan} onChange={set('keterukan')} empty="Semua" options={ref?.keterukan ?? []} />
                    </Field>
                    <Field label="Modul" htmlFor="t-mod">
                        <Select id="t-mod" value={f.modul} onChange={set('modul')} empty="Semua" options={ref?.modul ?? []} />
                    </Field>
                    <Field label="Kategori" htmlFor="t-kat">
                        <Select id="t-kat" value={f.kategori} onChange={set('kategori')} empty="Semua" options={ref?.kategori ?? []} />
                    </Field>
                    <Field label="Susun" htmlFor="t-su">
                        <Select id="t-su" value={f.susun} onChange={set('susun')} options={[{ kod: 'tarikh_buka', nama: 'Terkini dibuka' }, { kod: 'sla', nama: 'Sasaran SLA terdekat' }, { kod: 'keterukan', nama: 'Keterukan' }]} />
                    </Field>
                </div>
                {support && (
                    <div className="flex flex-wrap gap-2" role="group" aria-label="Penapis pantas">
                        {TOGOL.map(([k, l]) => (
                            <button key={k} type="button" aria-pressed={f[k] === '1'} onClick={() => set(k)(f[k] === '1' ? '' : '1')} className={`rounded-full border px-3 py-1 text-body-xs font-medium ${f[k] === '1' ? 'border-primary-600 bg-bg-primary-50 text-txt-primary' : 'border-otl-gray-300 text-txt-black-700 hover:bg-bg-washed'}`}>
                                {l}
                            </button>
                        ))}
                        {['L1', 'L2'].map((a) => (
                            <button key={a} type="button" aria-pressed={f.aras === a} onClick={() => set('aras')(f.aras === a ? '' : a)} className={`rounded-full border px-3 py-1 text-body-xs font-medium ${f.aras === a ? 'border-primary-600 bg-bg-primary-50 text-txt-primary' : 'border-otl-gray-300 text-txt-black-700 hover:bg-bg-washed'}`}>
                                Aras {a}
                            </button>
                        ))}
                    </div>
                )}
                <p className="text-body-sm text-txt-black-500" aria-live="polite">
                    {error ? <span className="text-txt-danger">{ralat(error)}</span> : data ? `${data.meta.total} tiket` : 'Memuatkan…'}
                </p>
                <div className="overflow-x-auto">
                    <Table head={<><th>Tiket</th><th>Status</th><th className="hidden sm:table-cell">SLA</th><th className="hidden lg:table-cell">Ditugaskan</th></>}>
                        {isLoading ? (
                            <tr>
                                <td colSpan={4} className="py-6 text-center text-txt-black-500">Memuatkan…</td>
                            </tr>
                        ) : (data?.data ?? []).length === 0 ? (
                            <tr>
                                <td colSpan={4} className="py-6 text-center text-txt-black-500">Tiada tiket.</td>
                            </tr>
                        ) : (
                            data?.data.map((t) => (
                                <tr key={t.id}>
                                    <td className="max-w-xs">
                                        <Link href={`/admin/tkt/tiket/${t.id}`} className="font-medium text-txt-primary hover:underline">
                                            {t.nombor_rujukan}
                                        </Link>
                                        <p className="text-body-sm text-txt-black-900 break-words">{t.tajuk}</p>
                                        <p className="text-body-xs text-txt-black-500">
                                            {t.kategori_nama} · {t.modul_nama} · {tarikhMasa(t.tarikh_buka)}
                                        </p>
                                        <div className="mt-1 flex flex-wrap gap-1">
                                            <Keterukan t={t} />
                                            {t.klasifikasi === 'sulit' && <Badge variant="danger">Sulit</Badge>}
                                            {t.pendua_dicadang && <Badge variant="info">Mungkin pendua</Badge>}
                                            {t.sintetik && <Badge variant="neutral">Sintetik</Badge>}
                                        </div>
                                        <p className="sm:hidden mt-1">
                                            <SlaBadge t={t} />
                                        </p>
                                    </td>
                                    <td>
                                        <StatusTiket t={t} />
                                    </td>
                                    <td className="hidden sm:table-cell whitespace-nowrap">
                                        <SlaBadge t={t} />
                                    </td>
                                    <td className="hidden lg:table-cell">
                                        {t.juruteknik?.nama ?? <span className="text-txt-black-500">Belum ditugaskan</span>}
                                        <p className="text-body-xs text-txt-black-500">
                                            {t.pasukan?.nama ?? '—'} · {t.aras_sokongan}
                                        </p>
                                    </td>
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

export default function SenaraiTiketPage() {
    return (
        <Suspense>
            <Senarai />
        </Suspense>
    );
}
