'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { StatCard } from '@/components/ui/StatCard';
import { Table } from '@/components/ui/Table';
import { ralat, tktApi, useRujukan } from '@/lib/tkt';
import { Bar, Card, Field, inputClass, Kosong, Select } from './_components/common';

const pct = (v: number | null | undefined) => (v === null || v === undefined ? '—' : `${v}%`);

/** SRS-TKT-17: support dashboard with drill-down to the list and records. */
export default function PapanPemukaPage() {
    const { data: ref } = useRujukan();
    const [f, setF] = useState({ dari: '', hingga: '', modul: '' });
    const { data, error, isLoading } = useQuery({ queryKey: ['tkt', 'papan', f], queryFn: () => tktApi.papanPemuka(f).then((r) => r.data) });
    const set = (k: keyof typeof f) => (v: string) => setF((x) => ({ ...x, [k]: v }));

    return (
        <PageContainer>
            <Card title="Penapis">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <Field label="Dari" htmlFor="pp-dari">
                        <input id="pp-dari" type="date" className={inputClass} value={f.dari} onChange={(e) => set('dari')(e.target.value)} />
                    </Field>
                    <Field label="Hingga" htmlFor="pp-hingga">
                        <input id="pp-hingga" type="date" className={inputClass} value={f.hingga} onChange={(e) => set('hingga')(e.target.value)} />
                    </Field>
                    <Field label="Modul" htmlFor="pp-modul">
                        <Select id="pp-modul" value={f.modul} onChange={set('modul')} options={ref?.modul ?? []} empty="Semua" />
                    </Field>
                </div>
                {data && <p className="text-body-xs text-txt-black-500">Tempoh pematuhan SLA: {data.tempoh.dari} hingga {data.tempoh.hingga}</p>}
            </Card>

            {error && <Alert type="error">{ralat(error)}</Alert>}
            {isLoading && <Kosong>Memuatkan…</Kosong>}
            {data && (
                <>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <StatCard label="Tiket terbuka" value={String(data.terbuka)}>
                            <Link href="/admin/tkt/tiket?status=terbuka" className="text-body-xs text-txt-primary hover:underline">
                                {data.terbuka_dilepasi_sla} melepasi SLA →
                            </Link>
                        </StatCard>
                        <StatCard label="Pematuhan SLA penyelesaian" value={pct(data.sla.peratus_selesai)}>
                            <span className="text-body-xs text-txt-black-500">Respons: {pct(data.sla.peratus_respons)} · {data.sla.bilangan} diselesaikan</span>
                        </StatCard>
                        <StatCard label="Masa purata penyelesaian" value={data.mttr_jam === null ? '—' : `${data.mttr_jam} jam`}>
                            <span className="text-body-xs text-txt-black-500">Respons purata: {data.purata_respons_jam ?? '—'} jam</span>
                        </StatCard>
                        <StatCard label="Penyelesaian kendiri (KB)" value={pct(data.penyelesaian_kendiri.kadar_penyelesaian_kendiri)}>
                            <span className="text-body-xs text-txt-black-500">Kepuasan purata: {data.kepuasan.purata ?? '—'} / 5</span>
                        </StatCard>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <Card title="Tiket terbuka mengikut keterukan">
                            <Bar rows={data.terbuka_mengikut_keterukan} href={(k) => `/admin/tkt/tiket?status=terbuka&keterukan=${k}`} />
                        </Card>
                        <Card title="Tiket terbuka mengikut modul">
                            <Bar rows={data.terbuka_mengikut_modul} href={(k) => `/admin/tkt/tiket?status=terbuka&modul=${k}`} />
                        </Card>
                    </div>

                    <Card title="Prestasi SLA mengikut tahap keterukan">
                        <div className="overflow-x-auto">
                            <Table head={<><th>Keterukan</th><th>Diselesaikan</th><th>Respons</th><th>Penyelesaian</th><th className="hidden sm:table-cell">MTTR (jam)</th></>}>
                                {data.sla_mengikut_keterukan.map((r) => (
                                    <tr key={r.keterukan}>
                                        <td>
                                            <Link href={`/admin/tkt/tiket?keterukan=${r.keterukan}`} className="text-txt-primary hover:underline">
                                                {r.label}
                                            </Link>
                                        </td>
                                        <td>{r.bilangan}</td>
                                        <td>{pct(r.peratus_respons)}</td>
                                        <td>{pct(r.peratus_selesai)}</td>
                                        <td className="hidden sm:table-cell">{r.mttr_jam ?? '—'}</td>
                                    </tr>
                                ))}
                            </Table>
                        </div>
                    </Card>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <Card title="Modul dengan tiket tertinggi">
                            <Bar rows={data.modul_tertinggi} href={(k) => `/admin/tkt/tiket?modul=${k}`} />
                        </Card>
                        <Card title="Trend masalah berulang">
                            {data.masalah_berulang.gabungan.length === 0 ? (
                                <Kosong>Tiada masalah berulang dalam tempoh ini.</Kosong>
                            ) : (
                                <ul className="space-y-1 text-body-sm">
                                    {data.masalah_berulang.gabungan.map((r) => (
                                        <li key={`${r.modul}-${r.kategori}`} className="flex justify-between gap-2">
                                            <Link href={`/admin/tkt/tiket?modul=${r.modul}&kategori=${r.kategori}`} className="text-txt-primary hover:underline truncate">
                                                {r.modul_nama} · {r.kategori_nama}
                                            </Link>
                                            <span className="font-medium">{r.bilangan}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {data.masalah_berulang.tajuk.length > 0 && (
                                <div>
                                    <p className="mt-2 text-body-xs font-medium text-txt-black-500">Tajuk berulang</p>
                                    <ul className="text-body-xs text-txt-black-700">
                                        {data.masalah_berulang.tajuk.map((r) => (
                                            <li key={`${r.modul}-${r.tajuk}`} className="break-words">
                                                {r.tajuk} ({r.bilangan})
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </Card>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <Card title="Trend 12 bulan">
                            <div className="overflow-x-auto">
                                <Table head={<><th>Bulan</th><th>Dibuka</th><th>Diselesaikan</th></>}>
                                    {data.trend.map((r) => (
                                        <tr key={r.tempoh}>
                                            <td>{r.tempoh}</td>
                                            <td>{r.dibuka}</td>
                                            <td>{r.diselesaikan}</td>
                                        </tr>
                                    ))}
                                </Table>
                            </div>
                        </Card>
                        <Card title="Penyelesaian kendiri & prestasi juruteknik">
                            <p className="text-body-sm text-txt-black-700">
                                {data.penyelesaian_kendiri.sesi} sesi dengan cadangan · {data.penyelesaian_kendiri.diselesaikan_sendiri} diselesaikan sendiri · {data.penyelesaian_kendiri.diteruskan} diteruskan sebagai tiket
                            </p>
                            {data.penyelesaian_kendiri.artikel_tidak_berkesan.length > 0 && (
                                <div>
                                    <p className="text-body-xs font-medium text-txt-black-500">Artikel kurang berkesan (dipapar tetapi tiket tetap dihantar)</p>
                                    <ul className="text-body-xs">
                                        {data.penyelesaian_kendiri.artikel_tidak_berkesan.map((a) => (
                                            <li key={a.pautan} className="break-words">
                                                <a href={a.pautan} className="text-txt-primary hover:underline">
                                                    {a.tajuk ?? a.pautan}
                                                </a>{' '}
                                                — {a.tiket_diteruskan}/{a.dipapar}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            <ul className="text-body-sm">
                                {data.prestasi_juruteknik.map((j) => (
                                    <li key={j.nama} className="flex justify-between gap-2">
                                        <span className="truncate">{j.nama}</span>
                                        <span>
                                            {j.diselesaikan} · {pct(j.peratus_sla)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    </div>
                </>
            )}
        </PageContainer>
    );
}
