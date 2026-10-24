'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { StatCard } from '@/components/ui/StatCard';
import { Table } from '@/components/ui/Table';
import { DonutChart } from '@/components/ui/charts/DonutChart';
import { MultiLineChart } from '@/components/ui/charts/MultiLineChart';
import { PetaSisdata } from '@/components/gis/PetaSisdata';
import { ptnApi, ralat, useRujukan, WARNA_LABEL } from '@/lib/ptn';
import { Card, Field, KodWarnaBadge, Kosong, Legenda, Penapis, Select, WARNA_TEKS } from './_components/common';

/** SRS-PTN-24: risk dashboard — colour distribution, high-risk lots, open issues, overdue actions, trend, risk map. */
export default function PtnDashboard() {
    const router = useRouter();
    const { data: ref } = useRujukan();
    const [daerah, setDaerah] = useState('');
    const { data, error, isLoading } = useQuery({ queryKey: ['ptn', 'dashboard', daerah], queryFn: () => ptnApi.dashboard({ kod_daerah: daerah }) });
    const n = (v: number | null | undefined, unit = '') => (v === null || v === undefined ? '—' : `${v.toLocaleString('ms-MY')}${unit}`);

    return (
        <PageContainer>
            <Penapis>
                <Field label="Daerah" htmlFor="d-daerah">
                    <Select id="d-daerah" value={daerah} onChange={setDaerah} options={ref?.daerah ?? []} empty="Semua daerah dalam skop" />
                </Field>
                <div className="sm:col-span-1 lg:col-span-3 flex items-end">
                    <Legenda />
                </div>
            </Penapis>
            {error && <p className="text-body-sm text-txt-danger">{ralat(error)}</p>}
            {isLoading && <p className="text-body-sm text-txt-black-500">Memuatkan…</p>}

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <Link href="/admin/ptn/lot?kod_warna=merah"><StatCard label="Lot berisiko tinggi (merah)" value={n(data?.taburan_warna.merah)} trend={data?.taburan_warna.merah ? 'down' : 'neutral'} /></Link>
                <Link href="/admin/ptn/lot?kod_warna=kuning"><StatCard label="Lot perlu perhatian (kuning)" value={n(data?.taburan_warna.kuning)} /></Link>
                <Link href="/admin/ptn/lot?kod_warna=hijau"><StatCard label="Lot normal (hijau)" value={n(data?.taburan_warna.hijau)} /></Link>
                <Link href="/admin/ptn/isu?terbuka=1"><StatCard label="Isu terbuka" value={n(data?.isu_terbuka)} change={data ? `${data.isu_kritikal_terbuka} kritikal` : undefined} trend={data?.isu_kritikal_terbuka ? 'down' : 'neutral'} /></Link>
                <Link href="/admin/ptn/laporan?laporan=tindakan"><StatCard label="Tindakan tertunggak" value={n(data?.tindakan_tertunggak)} trend={data?.tindakan_tertunggak ? 'down' : 'neutral'} /></Link>
                <Link href="/admin/ptn/jadual?tertunggak=1"><StatCard label="Lawatan tertunggak" value={n(data?.lawatan_tertunggak)} /></Link>
                <Link href="/admin/ptn/laporan?laporan=prestasi"><StatCard label="Lawatan ikut jadual" value={n(data?.peratus_ikut_jadual, '%')} /></Link>
                <StatCard label="Tindakan hampir tarikh akhir (kuning)" value={n(data?.tindakan_mengikut_warna.kuning)} />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card title="Taburan lot mengikut kod warna">
                    <DonutChart
                        data={(['hijau', 'kuning', 'merah'] as const).map((k) => ({ label: WARNA_LABEL[k], value: data?.taburan_warna[k] ?? 0, color: WARNA_TEKS[k] }))}
                        centerLabel="lot"
                    />
                </Card>
                <Card title="Trend risiko 12 bulan">
                    <MultiLineChart
                        labels={(data?.trend ?? []).map((t) => t.tempoh.slice(2))}
                        series={[
                            { name: 'Merah', data: (data?.trend ?? []).map((t) => t.merah), color: WARNA_TEKS.merah },
                            { name: 'Kuning', data: (data?.trend ?? []).map((t) => t.kuning), color: WARNA_TEKS.kuning },
                            { name: 'Hijau', data: (data?.trend ?? []).map((t) => t.hijau), color: WARNA_TEKS.hijau },
                        ]}
                    />
                </Card>
            </div>

            <Card title="Lot berisiko tinggi" actions={<Link href="/admin/ptn/lot" className="text-body-sm text-txt-primary hover:underline">Semua lot</Link>}>
                {data && data.lot_risiko_tinggi.length === 0 ? (
                    <Kosong>Tiada lot dikira.</Kosong>
                ) : (
                    <Table head={<><th>No. lot</th><th>Daerah / Mukim</th><th>Skor</th><th>Kod warna</th></>}>
                        {(data?.lot_risiko_tinggi ?? []).map((l) => (
                            <tr key={l.lot_id}>
                                <td><Link className="text-txt-primary hover:underline" href={`/admin/ptn/lot/${l.lot_id}`}>{l.no_lot}</Link></td>
                                <td>{l.daerah} / {l.mukim}</td>
                                <td>{l.skor.toFixed(1)}</td>
                                <td><KodWarnaBadge warna={l.kod_warna} label={l.paksa_merah ? 'Merah — penemuan kritikal' : undefined} /></td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>

            <Card title="Peta risiko aset tanah">
                <PetaSisdata layers={['ptn.risiko', 'ptn.pemantauan']} height={420} label="Peta risiko dan pemantauan tanah" onFeatureClick={(hit) => {
                    const p = hit.properties.pautan;
                    if (typeof p === 'string') router.push(p);
                }} />
            </Card>
        </PageContainer>
    );
}
