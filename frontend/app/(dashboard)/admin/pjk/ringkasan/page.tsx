'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { StatCard } from '@/components/ui/StatCard';
import { Table } from '@/components/ui/Table';
import { pjkApi, ralat, rm, useRujukan } from '@/lib/pjk';
import { Card, Field, Kosong, Scroll, Select } from '../_components/common';

function nilai(v: number | null, unit: string): string {
    if (v === null) return '—';
    if (unit === 'RM') return rm(v);
    if (unit === '%') return `${v.toFixed(1)}%`;
    return `${v} ${unit}`;
}

/** SRS-PJK-26 dashboard: KPIs (same providers as MOD-DSH) + occupancy & vacancy. */
export default function RingkasanPage() {
    const { data: ref } = useRujukan();
    const [daerah, setDaerah] = useState('');
    const [bahagian, setBahagian] = useState('');
    const { data, isLoading, error } = useQuery({ queryKey: ['pjk', 'ringkasan', daerah, bahagian], queryFn: () => pjkApi.ringkasan({ kod_daerah: daerah, bahagian }) });
    const occ = data?.penghunian;
    const s = (occ?.ringkasan ?? {}) as Record<string, unknown>;
    const vacant = (s.kosong_melebihi_ambang ?? []) as Array<{ id: number; kod_premis: string; nama_premis: string; kod_daerah: string; hari_kosong: number; kadar_tawaran: string | null }>;

    return (
        <PageContainer>
            <Card>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-xl">
                    <Field label="Daerah" htmlFor="r-daerah">
                        <Select id="r-daerah" value={daerah} onChange={setDaerah} options={ref?.daerah ?? []} empty="Semua daerah" />
                    </Field>
                    <Field label="Bahagian" htmlFor="r-bahagian">
                        <Select id="r-bahagian" value={bahagian} onChange={setBahagian} options={ref?.bahagian ?? []} empty="Semua bahagian" />
                    </Field>
                </div>
                {error && <p className="text-body-sm text-txt-danger">{ralat(error)}</p>}
            </Card>

            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                {isLoading && <p className="text-body-sm text-txt-black-500">Memuatkan…</p>}
                {data?.kpi.map((k) => (
                    <StatCard key={k.kod} label={k.label} value={nilai(k.nilai, k.unit)}>
                        <ul className="mt-2 space-y-0.5 text-body-xs text-txt-black-500">
                            {(k.pecahan ?? []).slice(0, 4).map((p) => (
                                <li key={p.kod ?? p.label} className="flex justify-between gap-2">
                                    <span>{p.label}</span>
                                    <span className="font-medium text-txt-black-700">{k.unit === 'RM' || p.kod === 'dicaj' || p.kod === 'dikutip' ? rm(p.nilai) : k.unit === '%' ? `${p.nilai}%` : p.nilai}</span>
                                </li>
                            ))}
                        </ul>
                        {k.pautan && (
                            <Link href={k.pautan} className="mt-2 inline-block text-body-xs text-txt-primary hover:underline">
                                Lihat laporan
                            </Link>
                        )}
                    </StatCard>
                ))}
            </div>

            <Card title="Penghunian dan kekosongan mengikut daerah dan kategori">
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 text-body-sm">
                    <div>
                        <p className="text-txt-black-500 text-body-xs">Kadar penghunian</p>
                        <p className="font-semibold">{String(s.kadar_penghunian ?? '—')}%</p>
                    </div>
                    <div>
                        <p className="text-txt-black-500 text-body-xs">Purata tempoh kosong</p>
                        <p className="font-semibold">{String(s.purata_hari_kosong ?? '—')} hari</p>
                    </div>
                    <div>
                        <p className="text-txt-black-500 text-body-xs">Hasil berpotensi sebulan (premis kosong)</p>
                        <p className="font-semibold">{rm(String(s.hasil_berpotensi_sebulan ?? '0'))}</p>
                    </div>
                    <div>
                        <p className="text-txt-black-500 text-body-xs">Hasil tidak dijana (terkumpul)</p>
                        <p className="font-semibold">{rm(String(s.hasil_tidak_dijana_terkumpul ?? '0'))}</p>
                    </div>
                </div>
                <Scroll>
                    <Table head={<>{(occ?.kepala ?? []).map((h) => <th key={h}>{h}</th>)}</>}>
                        {(occ?.baris ?? []).length === 0 ? (
                            <Kosong colSpan={7}>Tiada premis.</Kosong>
                        ) : (
                            occ?.baris.map((r, i) => (
                                <tr key={i}>
                                    {r.map((c, j) => (
                                        <td key={j}>{c}</td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </Table>
                </Scroll>
            </Card>

            <Card title={`Premis kosong melebihi ${String(s.ambang_hari_kosong ?? 90)} hari`}>
                <Scroll>
                    <Table head={<><th>Premis</th><th>Daerah</th><th>Hari kosong</th><th className="hidden sm:table-cell">Kadar tawaran</th></>}>
                        {vacant.length === 0 ? (
                            <Kosong colSpan={4}>Tiada premis kosong melebihi ambang.</Kosong>
                        ) : (
                            vacant.map((v) => (
                                <tr key={v.id}>
                                    <td>
                                        <Link href={`/admin/pjk/premis?premis=${v.id}`} className="text-txt-primary hover:underline">
                                            {v.kod_premis}
                                        </Link>
                                        <p className="text-body-xs text-txt-black-500">{v.nama_premis}</p>
                                    </td>
                                    <td>{v.kod_daerah}</td>
                                    <td>{v.hari_kosong}</td>
                                    <td className="hidden sm:table-cell">{rm(v.kadar_tawaran)}</td>
                                </tr>
                            ))
                        )}
                    </Table>
                </Scroll>
            </Card>
        </PageContainer>
    );
}
