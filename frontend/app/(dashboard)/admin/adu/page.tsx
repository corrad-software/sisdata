'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { StatCard } from '@/components/ui/StatCard';
import { HorizontalBarChart } from '@/components/ui/charts/HorizontalBarChart';
import { MultiLineChart } from '@/components/ui/charts/MultiLineChart';
import { Table } from '@/components/ui/Table';
import { aduApi, ralat, useRujukan } from '@/lib/adu';
import { Card, Field, inputClass, Select } from './_components/common';
import { PetaAduan } from './_components/PetaAduan';

/** SRS-ADU-26: complaint dashboard — active, pending, resolved, average resolution time; filters period/district/category/channel. */
export default function AduDashboard() {
    const router = useRouter();
    const { data: ref } = useRujukan();
    const [f, setF] = useState({ dari: '', hingga: '', kod_daerah: '', kategori: '', saluran: '' });
    const set = (k: keyof typeof f) => (v: string) => setF((x) => ({ ...x, [k]: v }));
    const { data, error } = useQuery({ queryKey: ['adu', 'ringkasan', f], queryFn: () => aduApi.ringkasan(f).then((r) => r.data) });
    const { data: peta } = useQuery({ queryKey: ['adu', 'peta', f.kod_daerah, f.kategori], queryFn: () => aduApi.peta({ kod_daerah: f.kod_daerah, kategori: f.kategori }) });
    const angka = (v: number | null | undefined, unit = '') => (v === null || v === undefined ? '—' : `${v.toLocaleString('ms-MY')}${unit}`);

    return (
        <PageContainer>
            <Card>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4" data-panduan="adu-penapis">
                    <Field label="Dari" htmlFor="d-dari">
                        <input id="d-dari" type="date" className={inputClass} value={f.dari} onChange={(e) => set('dari')(e.target.value)} />
                    </Field>
                    <Field label="Hingga" htmlFor="d-hingga">
                        <input id="d-hingga" type="date" className={inputClass} value={f.hingga} onChange={(e) => set('hingga')(e.target.value)} />
                    </Field>
                    <Field label="Daerah" htmlFor="d-daerah">
                        <Select id="d-daerah" value={f.kod_daerah} onChange={set('kod_daerah')} options={ref?.daerah ?? []} empty="Semua daerah" />
                    </Field>
                    <Field label="Kategori" htmlFor="d-kat">
                        <Select id="d-kat" value={f.kategori} onChange={set('kategori')} options={ref?.kategori ?? []} empty="Semua kategori" />
                    </Field>
                    <Field label="Saluran" htmlFor="d-sal">
                        <Select id="d-sal" value={f.saluran} onChange={set('saluran')} options={ref?.saluran ?? []} empty="Semua saluran" />
                    </Field>
                </div>
            </Card>

            {error && <p className="text-body-sm text-txt-danger">{ralat(error)}</p>}

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4" data-panduan="adu-ringkasan">
                <Link href="/admin/adu/aduan?status=terbuka">
                    <StatCard label="Aduan aktif" value={angka(data?.aktif)} />
                </Link>
                <Link href="/admin/adu/aduan?melepasi_sla=1">
                    <StatCard label="Tertangguh (melepasi SLA)" value={angka(data?.tertangguh)} trend={data?.tertangguh ? 'down' : 'neutral'} />
                </Link>
                <Link href="/admin/adu/aduan?status=ditutup">
                    <StatCard label="Diselesaikan" value={angka(data?.diselesaikan)} />
                </Link>
                <StatCard label="Purata masa penyelesaian" value={angka(data?.purata_hari_selesai, ' hari')} />
                <StatCard label="Selesai dalam SLA" value={angka(data?.peratus_sla_dipenuhi, '%')} />
                <StatCard label="Aktif masih dalam SLA" value={angka(data?.peratus_aktif_dalam_sla, '%')} />
                <StatCard label="Purata masa respons" value={angka(data?.purata_jam_respons, ' jam')} />
                <StatCard label="Bukan tanah MAIS" value={angka(data?.bukan_tanah_mais)} />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card title="Mengikut kategori">
                    <HorizontalBarChart data={(data?.mengikut_kategori ?? []).map((r) => ({ label: r.label ?? '—', value: r.jumlah }))} />
                </Card>
                <Card title="Mengikut daerah">
                    <HorizontalBarChart data={(data?.mengikut_daerah ?? []).map((r) => ({ label: r.label ?? '—', value: r.jumlah }))} color="bg-primary-400" />
                </Card>
                <Card title="Tren 12 bulan">
                    <MultiLineChart
                        labels={(data?.trend ?? []).map((t) => t.bulan.slice(2))}
                        series={[
                            { name: 'Diterima', data: (data?.trend ?? []).map((t) => t.diterima) },
                            { name: 'Ditutup', data: (data?.trend ?? []).map((t) => t.ditutup), color: '#16a34a' },
                        ]}
                    />
                </Card>
                <Card title="Mengikut saluran">
                    <HorizontalBarChart data={(data?.mengikut_saluran ?? []).map((r) => ({ label: r.label ?? '—', value: r.jumlah }))} color="bg-primary-300" />
                </Card>
            </div>

            <Card title="Taburan aduan (peta)">
                <PetaAduan points={peta?.features ?? []} legenda={peta?.legenda.entri} onSelect={(href) => router.push(href)} height={380} label="Peta taburan aduan" />
            </Card>

            <Card title="Kawasan panas (mukim)" actions={<Link href="/admin/adu/laporan?tab=kawasan_panas" className="text-body-sm text-txt-primary hover:underline">Analitik penuh</Link>}>
                <div className="overflow-x-auto">
                    <Table
                        head={
                            <>
                                <th>Mukim</th>
                                <th className="hidden sm:table-cell">Daerah</th>
                                <th>Jumlah</th>
                                <th>Terbuka</th>
                                <th className="hidden md:table-cell">Kategori utama</th>
                            </>
                        }
                    >
                        {(data?.kawasan_panas ?? []).map((k) => (
                            <tr key={k.kod_mukim}>
                                <td>{k.mukim}</td>
                                <td className="hidden sm:table-cell">{k.daerah}</td>
                                <td>{k.jumlah}</td>
                                <td>{k.terbuka}</td>
                                <td className="hidden md:table-cell">{k.kategori_utama}</td>
                            </tr>
                        ))}
                    </Table>
                </div>
            </Card>
        </PageContainer>
    );
}
