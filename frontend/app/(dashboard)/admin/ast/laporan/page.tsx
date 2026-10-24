'use client';

import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { Suspense, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { AnchorButton } from '@/components/ui/Button';
import { Table } from '@/components/ui/Table';
import { astApi, ralat, rm, useCan, useRujukan } from '@/lib/ast';
import { Card, Field, Kosong, Penapis, Select } from '../_components/common';

const WANG = /(nilai|kos|perubahan)/;

function sel(kod: string, v: unknown): string {
    if (v === null || v === undefined || v === '') return '—';
    if (kod === 'peratus') return `${v}%`;
    if (WANG.test(kod) && /^-?\d+(\.\d+)?$/.test(String(v))) return rm(String(v));
    return String(v);
}

/** SRS-AST-24..26 reports + the SRS list (inventory, portfolio value & year comparison, critical, annual cost, not inspected, schedule, value history, QR log). */
function Kandungan() {
    const sp = useSearchParams();
    const can = useCan();
    const { data: ref } = useRujukan();
    const [kod, setKod] = useState(sp.get('kod') ?? 'inventori');
    const [f, setF] = useState({ kod_daerah: '', id_kategori: '', status_kondisi: '', tahun: String(new Date().getFullYear()) });
    const params = { ...f, tahun: ['kos_penyelenggaraan', 'nilai_portfolio'].includes(kod) ? f.tahun : '' };
    const { data, error, isFetching } = useQuery({ queryKey: ['ast', 'laporan', kod, params], queryFn: () => astApi.laporan(kod, params) });
    const set = (k: keyof typeof f) => (v: string) => setF((x) => ({ ...x, [k]: v }));
    const tahunan = Array.from({ length: 8 }, (_, i) => String(new Date().getFullYear() - i)).map((y) => ({ kod: y, nama: y }));

    return (
        <PageContainer>
            <Penapis>
                <Field label="Laporan" htmlFor="l-k"><Select id="l-k" value={kod} onChange={setKod} options={ref?.laporan ?? []} /></Field>
                <Field label="Daerah" htmlFor="l-d"><Select id="l-d" value={f.kod_daerah} onChange={set('kod_daerah')} options={ref?.daerah ?? []} empty="Semua daerah" /></Field>
                <Field label="Kategori" htmlFor="l-c"><Select id="l-c" value={f.id_kategori} onChange={set('id_kategori')} options={(ref?.kategori ?? []).map((c) => ({ kod: String(c.id), nama: c.nama }))} empty="Semua kategori" /></Field>
                {kod === 'inventori' && <Field label="Kondisi" htmlFor="l-s"><Select id="l-s" value={f.status_kondisi} onChange={set('status_kondisi')} options={ref?.kondisi ?? []} empty="Semua" /></Field>}
                {['kos_penyelenggaraan', 'nilai_portfolio'].includes(kod) && <Field label="Tahun" htmlFor="l-t"><Select id="l-t" value={f.tahun} onChange={set('tahun')} options={tahunan} /></Field>}
            </Penapis>
            <Card title={data?.tajuk ?? 'Laporan'} actions={can('ast.laporan.eksport') ? <AnchorButton size="sm" variant="secondary" href={astApi.eksportUrl(kod, params)}>Eksport CSV</AnchorButton> : undefined}>
                {error && <p className="text-body-sm text-txt-danger">{ralat(error)}</p>}
                {isFetching && !data && <Kosong>Menjana…</Kosong>}
                {data && (
                    <>
                        <dl className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            {data.ringkasan.map((r) => (
                                <div key={r.label} className="rounded-lg border border-otl-gray-200 p-3"><dt className="text-body-xs text-txt-black-500">{r.label}</dt><dd className="font-heading text-heading-2xs font-semibold">{r.label.includes('RM') ? rm(String(r.nilai)) : r.nilai}</dd></div>
                            ))}
                        </dl>
                        {data.kumpulan && (
                            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                {Object.entries(data.kumpulan).map(([nama, rows]) => {
                                    const max = Math.max(1, ...rows.map((r) => Number(r.nilai) || r.bilangan));
                                    return (
                                        <section key={nama} className="rounded-lg border border-otl-gray-200 p-3 space-y-2">
                                            <h4 className="text-body-sm font-semibold capitalize">Pecahan {nama}</h4>
                                            <ul className="space-y-1">
                                                {rows.map((r) => (
                                                    <li key={r.kod} className="text-body-xs">
                                                        <div className="flex justify-between gap-2"><span>{r.label}</span><span className="whitespace-nowrap">{r.bilangan ? `${r.bilangan} · ` : ''}{rm(r.nilai)}</span></div>
                                                        <div className="h-1.5 rounded bg-bg-washed" aria-hidden="true"><div className="h-1.5 rounded bg-primary-600" style={{ width: `${Math.round(((Number(r.nilai) || r.bilangan) / max) * 100)}%` }} /></div>
                                                    </li>
                                                ))}
                                            </ul>
                                        </section>
                                    );
                                })}
                            </div>
                        )}
                        {data.baris.length === 0 ? <Kosong>Tiada rekod.</Kosong> : (
                            <Table head={<>{data.lajur.map((l) => <th key={l.kod}>{l.label}</th>)}</>}>
                                {data.baris.slice(0, 500).map((b, i) => (
                                    <tr key={i}>
                                        {data.lajur.map((l, j) => (
                                            <td key={l.kod} className={WANG.test(l.kod) ? 'whitespace-nowrap' : undefined}>{j === 0 && b._pautan ? <Link href={b._pautan} className="text-txt-primary hover:underline">{sel(l.kod, b[l.kod])}</Link> : sel(l.kod, b[l.kod])}</td>
                                        ))}
                                    </tr>
                                ))}
                            </Table>
                        )}
                        {data.baris.length > 500 && <p className="text-body-xs text-txt-black-500">Memaparkan 500 baris pertama daripada {data.baris.length}. Eksport CSV untuk senarai penuh.</p>}
                    </>
                )}
            </Card>
        </PageContainer>
    );
}

export default function LaporanAset() {
    return (
        <Suspense fallback={<PageContainer><Kosong>Memuatkan…</Kosong></PageContainer>}>
            <Kandungan />
        </Suspense>
    );
}
