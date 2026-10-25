'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { AnchorButton } from '@/components/ui/Button';
import { StatCard } from '@/components/ui/StatCard';
import { Table } from '@/components/ui/Table';
import { ASAL_LABEL, jenisLabel, kbaApi, PENILAIAN_LABEL, STATUS_ARTIKEL_LABEL, STATUS_INDEKS_LABEL, tarikhMasa, useCan, type Artikel } from '@/lib/kba';
import { Card, Field, inputClass, KlasifikasiBadge, Kosong, Memuat, RalatAkses } from '../_components/common';

/** SRS-KBA-18 + reports: frequent questions, content gaps, citations, usage by module/role, AI usage, content status, index coverage. */
export default function AnalitikPage() {
    const can = useCan();
    const [dari, setDari] = useState('');
    const [hingga, setHingga] = useState('');
    const { data, error, isLoading } = useQuery({ queryKey: ['kba', 'analitik', dari, hingga], queryFn: () => kbaApi.analitik({ dari, hingga }).then((r) => r.data) });

    if (error) {
        return (
            <PageContainer>
                <RalatAkses error={error} />
            </PageContainer>
        );
    }

    return (
        <PageContainer>
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div className="flex flex-wrap gap-4">
                    <Field label="Dari" htmlFor="an-dari">
                        <input id="an-dari" type="date" className={inputClass} value={dari} onChange={(e) => setDari(e.target.value)} />
                    </Field>
                    <Field label="Hingga" htmlFor="an-hingga">
                        <input id="an-hingga" type="date" className={inputClass} value={hingga} onChange={(e) => setHingga(e.target.value)} />
                    </Field>
                </div>
                {can('kba.kandungan.eksport') && (
                    <div className="flex flex-wrap gap-2">
                        <AnchorButton variant="secondary" href={kbaApi.eksportUrl('markdown')}>
                            Eksport kandungan (Markdown)
                        </AnchorButton>
                        <AnchorButton variant="secondary" href={kbaApi.eksportUrl('html')}>
                            Eksport kandungan (HTML)
                        </AnchorButton>
                    </div>
                )}
            </div>
            {isLoading || !data ? (
                <Memuat />
            ) : (
                <>
                    <p className="text-body-sm text-txt-black-500">
                        Tempoh {data.tempoh.dari} – {data.tempoh.hingga}
                    </p>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard label="Prompt" value={String(data.infrastruktur.ringkasan.prompt)} change={`${data.infrastruktur.ringkasan.ditolak} ditolak`} />
                        <StatCard label="Jurang kandungan" value={String(data.jurang.length)} change="soalan" trend={data.jurang.length > 0 ? 'down' : 'neutral'} />
                        <StatCard label="Token model" value={String(data.infrastruktur.ringkasan.token)} change={`${data.infrastruktur.ringkasan.generatif} gen · ${data.infrastruktur.ringkasan.ekstraktif} ekst`} />
                        <StatCard label="Masa respons purata" value={`${data.infrastruktur.ringkasan.purata_ms} ms`} />
                    </div>

                    <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                        <Card title="Soalan paling kerap ditanya">
                            {data.soalan_kerap.length === 0 ? (
                                <Kosong>Tiada soalan dalam tempoh ini.</Kosong>
                            ) : (
                                <Table
                                    head={
                                        <>
                                            <th>Soalan</th>
                                            <th className="text-right">Bil.</th>
                                            <th className="text-right">Memuaskan</th>
                                            <th>Modul</th>
                                        </>
                                    }
                                >
                                    {data.soalan_kerap.map((s) => (
                                        <tr key={s.soalan}>
                                            <td className="break-words">{s.soalan}</td>
                                            <td className="text-right">{s.bilangan}</td>
                                            <td className="text-right">{s.kadar_memuaskan}%</td>
                                            <td>{s.modul?.toUpperCase() ?? '—'}</td>
                                        </tr>
                                    ))}
                                </Table>
                            )}
                        </Card>
                        <Card title="Jurang kandungan (tiada jawapan memuaskan)">
                            {data.jurang.length === 0 ? (
                                <Kosong>Tiada jurang dikesan.</Kosong>
                            ) : (
                                <Table
                                    head={
                                        <>
                                            <th>Soalan</th>
                                            <th className="text-right">Bil.</th>
                                            <th className="text-right">Tiada sumber</th>
                                            <th className="text-right">Negatif</th>
                                            <th>Terakhir</th>
                                        </>
                                    }
                                >
                                    {data.jurang.map((j) => (
                                        <tr key={j.soalan}>
                                            <td className="break-words" title={j.contoh}>
                                                {j.soalan}
                                            </td>
                                            <td className="text-right">{j.bilangan}</td>
                                            <td className="text-right">{j.tiada_sumber}</td>
                                            <td className="text-right">{j.maklum_balas_negatif}</td>
                                            <td className="whitespace-nowrap text-body-xs">{tarikhMasa(j.terakhir)}</td>
                                        </tr>
                                    ))}
                                </Table>
                            )}
                        </Card>
                        <Card title="Sumber paling dirujuk">
                            {data.sumber_paling_dirujuk.length === 0 ? (
                                <Kosong>Belum ada petikan.</Kosong>
                            ) : (
                                <ul className="space-y-1 text-body-sm">
                                    {data.sumber_paling_dirujuk.map((s) => (
                                        <li key={s.id} className="flex justify-between gap-3">
                                            <Link href={`/admin/kba/sumber/${s.id}`} className="text-txt-primary hover:underline break-words">
                                                {s.tajuk}
                                            </Link>
                                            <span className="font-medium">{s.bilangan}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                        <Card title="Sumber tidak pernah dirujuk">
                            {data.sumber_tidak_dirujuk.length === 0 ? (
                                <Kosong>Semua sumber pernah dirujuk.</Kosong>
                            ) : (
                                <ul className="space-y-1 text-body-sm">
                                    {data.sumber_tidak_dirujuk.map((s) => (
                                        <li key={s.id}>
                                            <Link href={`/admin/kba/sumber/${s.id}`} className="text-txt-primary hover:underline break-words">
                                                {s.tajuk}
                                            </Link>
                                            <span className="text-body-xs text-txt-black-500"> · {jenisLabel(s.jenis_dokumen)}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                        <Card title="Penggunaan mengikut modul">
                            <ul className="space-y-1 text-body-sm">
                                {data.mengikut_modul.map((m) => (
                                    <li key={m.modul} className="flex justify-between gap-3">
                                        <span>{m.modul === '-' ? 'Tidak dikenal pasti' : m.modul.toUpperCase()}</span>
                                        <span>
                                            {m.bilangan} <span className="text-body-xs text-txt-black-500">({m.tiada_sumber} tiada sumber)</span>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                        <Card title="Penggunaan mengikut peranan">
                            <ul className="space-y-1 text-body-sm">
                                {data.mengikut_peranan.map((p) => (
                                    <li key={p.peranan} className="flex justify-between gap-3">
                                        <span className="break-all">{p.peranan === '-' ? 'Tanpa peranan / awam' : p.peranan}</span>
                                        <span>{p.bilangan}</span>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                        <Card title="Status kandungan artikel">
                            <ul className="space-y-1 text-body-sm">
                                {(Object.keys(STATUS_ARTIKEL_LABEL) as Artikel['status'][]).map((s) => (
                                    <li key={s} className="flex justify-between">
                                        <span>{STATUS_ARTIKEL_LABEL[s]}</span>
                                        <span>{data.artikel.status[s] ?? 0}</span>
                                    </li>
                                ))}
                                <li className="flex justify-between text-txt-warning">
                                    <span>Melepasi tarikh semakan</span>
                                    <span>{data.artikel.melepasi_tarikh_semakan}</span>
                                </li>
                                <li className="flex justify-between">
                                    <span>Daripada tiket (semua status)</span>
                                    <span>{Object.values(data.artikel.dari_tiket).reduce((a, b) => a + Number(b), 0)}</span>
                                </li>
                            </ul>
                        </Card>
                        <Card title="Maklum balas pengguna">
                            {data.maklum_balas.length === 0 ? (
                                <Kosong>Tiada maklum balas.</Kosong>
                            ) : (
                                <ul className="space-y-1 text-body-sm">
                                    {data.maklum_balas.map((m) => (
                                        <li key={`${m.penilaian}-${m.status_tindakan}`} className="flex justify-between">
                                            <span>
                                                {PENILAIAN_LABEL[m.penilaian]} · {m.status_tindakan}
                                            </span>
                                            <span>{m.bilangan}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                    </div>

                    <Card title="Liputan pengindeksan">
                        <Table
                            head={
                                <>
                                    <th>Sumber</th>
                                    <th>Versi</th>
                                    <th>Klasifikasi</th>
                                    <th>Status</th>
                                    <th className="text-right">Segmen</th>
                                    <th>Diindeks</th>
                                </>
                            }
                        >
                            {data.liputan.map((s) => (
                                <tr key={s.id}>
                                    <td className="min-w-[12rem] break-words">
                                        {s.tajuk}
                                        <div className="text-body-xs text-txt-black-500">
                                            {ASAL_LABEL[s.asal] ?? s.asal} · {jenisLabel(s.jenis_dokumen)}
                                        </div>
                                    </td>
                                    <td>{s.versi}</td>
                                    <td>
                                        <KlasifikasiBadge k={s.klasifikasi} />
                                    </td>
                                    <td>{STATUS_INDEKS_LABEL[s.status_indeks] ?? s.status_indeks}</td>
                                    <td className="text-right">{s.bilangan_segmen}</td>
                                    <td className="whitespace-nowrap text-body-xs">{tarikhMasa(s.tarikh_indeks_akhir)}</td>
                                </tr>
                            ))}
                        </Table>
                    </Card>
                </>
            )}
        </PageContainer>
    );
}
