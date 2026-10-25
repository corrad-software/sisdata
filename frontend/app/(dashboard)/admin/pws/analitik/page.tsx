'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { StatCard } from '@/components/ui/StatCard';
import { Table } from '@/components/ui/Table';
import { JENIS_PERMOHONAN_LABEL, pwsApi, ralat } from '@/lib/pws';
import { Card, Field, inputClass, Kosong, Memuat } from '../_components/common';

const iso = (d: Date) => d.toISOString().slice(0, 10);

/** SRS-PWS-27: visits, popular pages, form completion rate, applications by type and status. */
export default function PwsAnalitikPage() {
    const [dari, setDari] = useState(() => iso(new Date(Date.now() - 30 * 86400000)));
    const [hingga, setHingga] = useState(() => iso(new Date()));
    const { data, error, isLoading } = useQuery({ queryKey: ['pws', 'analitik', dari, hingga], queryFn: () => pwsApi.analitik(dari, hingga).then((r) => r.data) });

    if (error) {
        return (
            <PageContainer>
                <Alert type="error">{ralat(error)}</Alert>
            </PageContainer>
        );
    }
    const max = Math.max(1, ...(data?.lawatan_harian ?? []).map((d) => d.lawatan));

    return (
        <PageContainer>
            <div className="flex flex-wrap gap-4">
                <Field label="Dari" htmlFor="a-dari">
                    <input id="a-dari" type="date" className={inputClass} value={dari} onChange={(e) => setDari(e.target.value)} />
                </Field>
                <Field label="Hingga" htmlFor="a-hingga">
                    <input id="a-hingga" type="date" className={inputClass} value={hingga} onChange={(e) => setHingga(e.target.value)} />
                </Field>
            </div>
            {isLoading || !data ? (
                <Memuat />
            ) : (
                <>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <StatCard label="Jumlah lawatan" value={data.jumlah_lawatan.toLocaleString('ms-MY')} />
                        <StatCard label="Kadar penyempurnaan borang" value={data.kadar_penyempurnaan_borang === null ? '—' : `${data.kadar_penyempurnaan_borang}%`} />
                        <StatCard label="Permohonan dalam tempoh" value={String(data.permohonan.reduce((a, p) => a + p.bilangan, 0))} />
                    </div>
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <Card title="Lawatan harian">
                            {data.lawatan_harian.length === 0 ? (
                                <Kosong>Tiada data lawatan. Portal menghantar statistik sekali sehari.</Kosong>
                            ) : (
                                <ul className="space-y-1" aria-label="Lawatan harian">
                                    {data.lawatan_harian.map((d) => (
                                        <li key={d.tarikh} className="flex items-center gap-2 text-body-xs">
                                            <span className="w-20 shrink-0 text-txt-black-500">{d.tarikh}</span>
                                            <span className="h-3 rounded bg-primary-600" style={{ width: `${Math.max(2, (d.lawatan / max) * 100)}%` }} aria-hidden="true" />
                                            <span>{d.lawatan}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                        <Card title="Halaman paling popular">
                            {data.halaman_popular.length === 0 ? (
                                <Kosong>Tiada data.</Kosong>
                            ) : (
                                <Table
                                    head={
                                        <>
                                            <th>Halaman</th>
                                            <th className="text-right">Lawatan</th>
                                        </>
                                    }
                                >
                                    {data.halaman_popular.map((h) => (
                                        <tr key={h.halaman}>
                                            <td className="break-all">{h.halaman}</td>
                                            <td className="text-right">{h.lawatan}</td>
                                        </tr>
                                    ))}
                                </Table>
                            )}
                        </Card>
                    </div>
                    <Card title="Permohonan mengikut jenis dan status">
                        {data.permohonan.length === 0 ? (
                            <Kosong>Tiada permohonan.</Kosong>
                        ) : (
                            <Table
                                head={
                                    <>
                                        <th>Jenis</th>
                                        <th>Status</th>
                                        <th className="text-right">Bilangan</th>
                                    </>
                                }
                            >
                                {data.permohonan.map((p) => (
                                    <tr key={`${p.jenis}-${p.status}`}>
                                        <td>{JENIS_PERMOHONAN_LABEL[p.jenis] ?? p.jenis}</td>
                                        <td>{p.status}</td>
                                        <td className="text-right">{p.bilangan}</td>
                                    </tr>
                                ))}
                            </Table>
                        )}
                    </Card>
                </>
            )}
        </PageContainer>
    );
}
