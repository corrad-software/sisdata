'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { StatCard } from '@/components/ui/StatCard';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { HorizontalBarChart } from '@/components/ui/charts/HorizontalBarChart';
import { ckiApi, ralat, rm, type Kumpulan } from '@/lib/cki';
import { Card, Kosong } from '../_components/common';
import { Penapis } from '../_components/Penapis';

function nombor(v: string): number {
    return Number(v); // display only (chart widths) — amounts themselves stay DECIMAL strings
}

function JadualKumpulan({ rows, label }: { rows: Kumpulan[]; label: string }) {
    return (
        <Table
            head={
                <>
                    <th>{label}</th>
                    <th className="hidden sm:table-cell">Bil.</th>
                    <th>Jumlah</th>
                    <th className="hidden md:table-cell">Dibayar</th>
                    <th>Tertunggak</th>
                </>
            }
        >
            {rows.length === 0 ? (
                <Kosong colSpan={5}>Tiada data.</Kosong>
            ) : (
                rows.map((r) => (
                    <tr key={r.kod}>
                        <td className="max-w-[10rem] sm:max-w-xs break-words">{r.nama}</td>
                        <td className="hidden sm:table-cell">{r.bil}</td>
                        <td className="whitespace-nowrap">{rm(r.jumlah)}</td>
                        <td className="hidden md:table-cell whitespace-nowrap">{rm(r.dibayar)}</td>
                        <td className="whitespace-nowrap text-txt-danger">{rm(r.tertunggak)}</td>
                    </tr>
                ))
            )}
        </Table>
    );
}

/** SRS-CKI-21/22: payment performance dashboard and portfolio tax position, filterable by district, mukim, PBT, type and year. */
export default function RingkasanPage() {
    const [f, setF] = useState<Record<string, string>>({});
    const { data, isLoading, error } = useQuery({ queryKey: ['cki', 'dashboard', f], queryFn: () => ckiApi.dashboard(f) });
    const j = data?.jumlah;

    return (
        <PageContainer>
            <Card title="Penapis" actions={<Button size="sm" variant="outline" onClick={() => setF({})}>Kosongkan</Button>}>
                <Penapis value={f} onChange={(k, v) => setF((p) => ({ ...p, [k]: v }))} />
            </Card>
            {error && <p className="text-txt-danger">{ralat(error)}</p>}
            {isLoading || !data || !j ? (
                <p className="text-body-sm text-txt-black-500">Memuatkan…</p>
            ) : (
                <>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <StatCard label={`Jumlah bil (${j.bil})`} value={rm(j.jumlah_bil)} />
                        <StatCard label="Telah dibayar" value={rm(j.telah_dibayar)} trend="up" change={j.jumlah_bil !== '0.00' ? `${((100 * nombor(j.telah_dibayar)) / nombor(j.jumlah_bil)).toFixed(1)}%` : undefined} />
                        <StatCard label={`Tertunggak (${j.bil_tertunggak} bil)`} value={rm(j.tertunggak)} trend="down" />
                        <StatCard label="Denda terkumpul" value={rm(j.denda_terkumpul)} />
                    </div>
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <Card title="Risiko kelewatan">
                            <p className="font-heading text-heading-xs font-semibold text-txt-warning">{rm(j.risiko_lewat)}</p>
                            <p className="text-body-sm text-txt-black-500">{j.bil_risiko_lewat} bil belum dibayar akan matang dalam tempoh terdekat.</p>
                            <Link href="/admin/cki/jadual" className="text-body-sm text-txt-primary hover:underline">
                                Lihat jadual bayaran
                            </Link>
                        </Card>
                        <Card title="Umur tunggakan">
                            <HorizontalBarChart color="bg-danger-600" data={data.umur_tunggakan.map((u) => ({ label: u.nama, value: Math.round(nombor(u.amaun)) }))} />
                        </Card>
                        <Card title="Anggaran obligasi (bajet)">
                            {data.anggaran ? (
                                <>
                                    <p className="font-heading text-heading-xs font-semibold text-txt-black-900">{rm(data.anggaran.jumlah_anggaran)}</p>
                                    <p className="text-body-sm text-txt-black-500">
                                        Anggaran tahun {data.anggaran.tahun_anggaran} berdasarkan pokok {data.anggaran.tahun_asas} + {data.anggaran.kadar_peratus}%.
                                    </p>
                                </>
                            ) : (
                                <Kosong>Tiada data.</Kosong>
                            )}
                        </Card>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        {data.kpi.map((k) => (
                            <StatCard key={k.kod} label={k.label} value={k.nilai === null ? '—' : k.unit === 'RM' ? `RM ${k.nilai.toLocaleString('ms-MY', { minimumFractionDigits: 2 })}` : `${k.nilai}${k.unit}`} />
                        ))}
                    </div>
                    <Card title="Mengikut daerah">
                        <JadualKumpulan rows={data.mengikut_daerah} label="Daerah" />
                    </Card>
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <Card title="Mengikut agensi (PTD / PBT)">
                            <JadualKumpulan rows={data.mengikut_pbt} label="Agensi" />
                        </Card>
                        <Card title="Mengikut jenis cukai">
                            <JadualKumpulan rows={data.mengikut_jenis} label="Jenis" />
                        </Card>
                    </div>
                </>
            )}
        </PageContainer>
    );
}
