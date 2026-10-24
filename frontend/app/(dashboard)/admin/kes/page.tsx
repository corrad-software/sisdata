'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { StatCard } from '@/components/ui/StatCard';
import { Badge } from '@/components/ui/Badge';
import { kesApi, rm, tarikhMasa, useRujukan, type BarisRingkasan } from '@/lib/kes';
import { Card, Field, Kosong, RalatAkses, Select } from './_components/common';

function Bar({ rows, href }: { rows: BarisRingkasan[]; href: (kod: string) => string }) {
    const max = Math.max(1, ...rows.map((r) => r.bil));
    if (!rows.length) return <Kosong>Tiada kes aktif.</Kosong>;
    return (
        <ul className="space-y-2">
            {rows.map((r) => (
                <li key={r.kod || r.label}>
                    <Link href={href(r.kod)} className="block group">
                        <div className="flex justify-between gap-2 text-body-sm">
                            <span className="text-txt-black-700 group-hover:text-txt-primary">{r.label}</span>
                            <span className="font-medium text-txt-black-900">{r.bil}</span>
                        </div>
                        <div className="mt-1 h-2 rounded-full bg-bg-washed" aria-hidden>
                            <div className="h-2 rounded-full bg-primary-600" style={{ width: `${(r.bil / max) * 100}%` }} />
                        </div>
                    </Link>
                </li>
            ))}
        </ul>
    );
}

/** SRS-KES-22: active cases by status and type, upcoming hearings, value of lots involved and legal costs. */
export default function KesDashboardPage() {
    const { data: ref } = useRujukan();
    const [daerah, setDaerah] = useState('');
    const { data, error, isLoading } = useQuery({ queryKey: ['kes', 'dashboard', daerah], queryFn: () => kesApi.dashboard({ kod_daerah: daerah }).then((r) => r.data) });

    return (
        <PageContainer>
            <div className="flex flex-wrap items-end justify-between gap-4">
                <Field label="Daerah" htmlFor="d-daerah" className="w-full sm:w-64">
                    <Select id="d-daerah" value={daerah} onChange={setDaerah} options={ref?.daerah ?? []} empty="Semua daerah" />
                </Field>
            </div>
            {error ? (
                <RalatAkses error={error} />
            ) : isLoading || !data ? (
                <p className="text-body-sm text-txt-black-500">Memuatkan…</p>
            ) : (
                <>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <StatCard label="Kes aktif" value={String(data.bil_aktif)} change={`${data.bil_jumlah} jumlah`} />
                        <StatCard label="Prosiding 30 hari" value={String(data.prosiding_hampir.length)} change={`${data.bil_tanpa_tarikh} tanpa tarikh berikutnya`} trend={data.bil_tanpa_tarikh > 0 ? 'down' : 'neutral'} />
                        <StatCard label="Lot terlibat (kes aktif)" value={String(data.bil_lot_terlibat)} change={data.nilai_lot.boleh_lihat ? rm(data.nilai_lot.nilai) : 'Nilai: Rahsia penilaian'} />
                        <StatCard label={`Kos guaman terkumpul (tahun ini ${rm(data.kos_tahun_ini)})`} value={rm(data.kos_terkumpul)} />
                    </div>
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <Card title="Kes aktif mengikut status">
                            <Bar rows={data.mengikut_status} href={(k) => `/admin/kes/kes?status=${k}`} />
                        </Card>
                        <Card title="Kes aktif mengikut jenis">
                            <Bar rows={data.mengikut_jenis} href={(k) => `/admin/kes/kes?jenis=${k}&aktif=1`} />
                        </Card>
                        <Card
                            title="Prosiding hampir (30 hari)"
                            actions={
                                <Link href="/admin/kes/kalendar" className="text-body-sm font-medium text-primary-600 hover:underline">
                                    Kalendar
                                </Link>
                            }
                        >
                            {data.prosiding_hampir.length === 0 ? (
                                <Kosong>Tiada prosiding dalam tempoh 30 hari.</Kosong>
                            ) : (
                                <ul className="divide-y divide-otl-divider">
                                    {data.prosiding_hampir.map((p) => (
                                        <li key={p.id} className="py-2 flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <Link href={`/admin/kes/kes/${p.kes_id}?tab=prosiding`} className="font-medium text-txt-primary hover:underline">
                                                    {p.no_rujukan}
                                                </Link>
                                                <p className="text-body-xs text-txt-black-500">
                                                    {p.jenis_nama} · {tarikhMasa(p.tarikh)}
                                                </p>
                                            </div>
                                            <Badge variant={p.baki_hari <= 1 ? 'danger' : p.baki_hari <= 7 ? 'warning' : 'info'}>{p.baki_hari === 0 ? 'Hari ini' : `${p.baki_hari} hari`}</Badge>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                    </div>
                </>
            )}
        </PageContainer>
    );
}
