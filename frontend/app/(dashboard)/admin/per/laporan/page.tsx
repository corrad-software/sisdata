'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { StatCard } from '@/components/ui/StatCard';
import { DonutChart } from '@/components/ui/charts/DonutChart';
import { HorizontalBarChart } from '@/components/ui/charts/HorizontalBarChart';
import { StackedBarChart } from '@/components/ui/charts/StackedBarChart';
import { BarChart } from '@/components/ui/charts/BarChart';
import { Table } from '@/components/ui/Table';
import { InputLabel } from '@/components/ui/InputLabel';
import { AnchorButton } from '@/components/ui/Button';
import { Tabs, TabPanel } from '@/components/ui/Tabs';
import { perApi, ringgit, STATUS_LABEL, useDaerah, usePerCan, useSaluran, type StatusKes } from '@/lib/per';
import { cardClass, inputClass, KELAS_LATAR, KELAS_TEKS, SaluranChip, SectionTitle } from '../_components/common';
import { PetaDaerah } from '../_components/PetaDaerah';

const KHAS: Record<string, string> = {
    dokumen_kurang: 'Dokumen kurang',
    notis_5a: 'Notis 5A belum dibayar',
    keputusan_mmkn: 'Keputusan MMKN',
    kes_lewat: 'Melebihi tempoh sasaran',
    kes_selesai: 'Kes selesai & hak milik',
    portal_wakaf: 'Permohonan portal wakaf',
};

/** SRS-PER-27/28: reports by channel/district/period/status, charts and distribution map. */
export default function LaporanPage() {
    const can = usePerCan();
    const [f, setF] = useState<Record<string, string | undefined>>({});
    const { data: saluran } = useSaluran();
    const { data: daerah } = useDaerah();
    const { data, isLoading, error } = useQuery({ queryKey: ['per', 'laporan', f], queryFn: () => perApi.laporan(f).then((r) => r.data) });
    const set = (k: string, v: string) => setF((x) => ({ ...x, [k]: v || undefined }));
    const mapColour = saluran?.find((s) => s.kod === f.saluran)?.warna ?? '#2563EB';

    return (
        <PageContainer>
            <div className={`${cardClass} grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end`}>
                <div>
                    <InputLabel htmlFor="l-saluran" className="mb-1.5">
                        Saluran
                    </InputLabel>
                    <select id="l-saluran" className={inputClass} value={f.saluran ?? ''} onChange={(e) => set('saluran', e.target.value)}>
                        <option value="">Semua</option>
                        {saluran?.map((s) => (
                            <option key={s.kod} value={s.kod}>
                                {s.nama}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <InputLabel htmlFor="l-daerah" className="mb-1.5">
                        Daerah
                    </InputLabel>
                    <select id="l-daerah" className={inputClass} value={f.daerah ?? ''} onChange={(e) => set('daerah', e.target.value)}>
                        <option value="">Semua</option>
                        {daerah?.map((d) => (
                            <option key={d.kod} value={d.kod}>
                                {d.nama}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <InputLabel htmlFor="l-status" className="mb-1.5">
                        Status
                    </InputLabel>
                    <select id="l-status" className={inputClass} value={f.status ?? ''} onChange={(e) => set('status', e.target.value)}>
                        <option value="">Semua</option>
                        {(Object.keys(STATUS_LABEL) as StatusKes[]).map((s) => (
                            <option key={s} value={s}>
                                {STATUS_LABEL[s]}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <InputLabel htmlFor="l-dari" className="mb-1.5">
                        Dari
                    </InputLabel>
                    <input id="l-dari" type="date" className={inputClass} value={f.dari ?? ''} onChange={(e) => set('dari', e.target.value)} />
                </div>
                <div>
                    <InputLabel htmlFor="l-hingga" className="mb-1.5">
                        Hingga
                    </InputLabel>
                    <input id="l-hingga" type="date" className={inputClass} value={f.hingga ?? ''} onChange={(e) => set('hingga', e.target.value)} />
                </div>
            </div>

            {error && <p className="text-body-sm text-txt-danger">{error.message}</p>}
            {isLoading || !data ? (
                <p className="text-body-sm text-txt-black-500">Memuatkan…</p>
            ) : (
                <>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        <StatCard label="Jumlah kes" value={String(data.jumlah.kes)} />
                        <StatCard label="Nilai pemerolehan" value={new Intl.NumberFormat('ms-MY', { style: 'currency', currency: 'MYR', notation: 'compact', maximumFractionDigits: 1 }).format(data.jumlah.nilai)} />
                        <StatCard label="Kes selesai" value={String(data.jumlah.selesai)} />
                        <StatCard label="Kes aktif" value={String(data.jumlah.aktif)} />
                        <StatCard label="Melebihi tempoh sasaran" value={String(data.jumlah.lewat)} trend={data.jumlah.lewat > 0 ? 'down' : 'neutral'} change={data.jumlah.dokumen_kurang ? `${data.jumlah.dokumen_kurang} dokumen kurang` : undefined} />
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <section className={cardClass}>
                            <SectionTitle>Kes mengikut saluran</SectionTitle>
                            <DonutChart
                                data={data.mengikut_saluran.filter((s) => s.bilangan > 0).map((s) => ({ label: s.nama, value: s.bilangan, color: KELAS_TEKS[s.kod] }))}
                                centerLabel={String(data.jumlah.kes)}
                            />
                        </section>
                        <section className={cardClass}>
                            <SectionTitle>Taburan mengikut daerah</SectionTitle>
                            <PetaDaerah data={data.mengikut_daerah} warna={mapColour} />
                        </section>
                        <section className={cardClass}>
                            <SectionTitle>Kes mengikut tahun dan saluran</SectionTitle>
                            <StackedBarChart
                                data={data.mengikut_tahun.map((y) => ({
                                    label: String(y.tahun),
                                    values: Object.fromEntries(Object.entries(y.saluran).map(([kod, n]) => [saluran?.find((c) => c.kod === kod)?.singkatan ?? kod, n])),
                                }))}
                                colors={Object.fromEntries((saluran ?? []).map((c) => [c.singkatan, KELAS_LATAR[c.kod]]))}
                            />
                        </section>
                        <section className={cardClass}>
                            <SectionTitle>Nilai pemerolehan mengikut daerah (RM ribu)</SectionTitle>
                            <HorizontalBarChart data={data.mengikut_daerah.map((d) => ({ label: d.nama, value: Math.round(d.nilai / 1000) }))} />
                        </section>
                        <section className={cardClass}>
                            <SectionTitle>Nilai terkumpul mengikut sumber dana (RM ribu)</SectionTitle>
                            <BarChart data={data.mengikut_sumber_dana.map((s) => ({ label: s.sumber_dana, value: Math.round(s.nilai / 1000) }))} />
                        </section>
                        <section className={cardClass}>
                            <SectionTitle>Tempoh sebenar berbanding sasaran (kes selesai)</SectionTitle>
                            <div className="overflow-x-auto">
                                <Table
                                    head={
                                        <>
                                            <th>Saluran</th>
                                            <th className="text-right">Selesai</th>
                                            <th className="text-right">Purata hari</th>
                                            <th className="text-right">Sasaran</th>
                                        </>
                                    }
                                >
                                    {data.mengikut_saluran.map((s) => (
                                        <tr key={s.kod}>
                                            <td>
                                                <SaluranChip kod={s.kod} nama={s.nama} warna={s.warna} ringkas />
                                            </td>
                                            <td className="text-right">{s.selesai}</td>
                                            <td className={`text-right ${s.purata_hari_selesai !== null && s.purata_hari_selesai > s.tempoh_sasaran_hari ? 'text-txt-danger' : ''}`}>{s.purata_hari_selesai ?? '—'}</td>
                                            <td className="text-right">{s.tempoh_sasaran_hari}</td>
                                        </tr>
                                    ))}
                                </Table>
                            </div>
                        </section>
                    </div>

                    <section className={`${cardClass} space-y-3`}>
                        <SectionTitle
                            action={
                                can('per.laporan.eksport') ? (
                                    <AnchorButton variant="secondary" size="sm" href={perApi.eksportUrl(f)}>
                                        Eksport CSV
                                    </AnchorButton>
                                ) : undefined
                            }
                        >
                            Laporan khas
                        </SectionTitle>
                        <Tabs tabs={KHAS}>
                            {Object.keys(KHAS).map((k) => (
                                <TabPanel key={k} value={k}>
                                    <Khas jenis={k} f={f} />
                                </TabPanel>
                            ))}
                        </Tabs>
                    </section>
                </>
            )}
        </PageContainer>
    );
}

function Khas({ jenis, f }: { jenis: string; f: Record<string, string | undefined> }) {
    const { data, isLoading } = useQuery({ queryKey: ['per', 'khas', jenis, f], queryFn: () => perApi.laporanKhas(jenis, f).then((r) => r.data) });
    if (isLoading) return <p className="text-body-sm text-txt-black-500">Memuatkan…</p>;
    if (!data || data.length === 0) return <p className="text-body-sm text-txt-black-500">Tiada rekod.</p>;

    const extra = (r: Record<string, unknown>): string => {
        switch (jenis) {
            case 'dokumen_kurang':
                return (r.kekurangan as string[]).join('; ');
            case 'notis_5a':
                return `Baki ${ringgit(r.baki_tertunggak as string)} · ${r.hari_lagi as number} hari lagi`;
            case 'keputusan_mmkn':
                return `${String(r.jenis_keputusan_mmkn ?? '—')} · ${String(r.no_kertas_mmkn ?? '')}`;
            case 'kes_lewat':
                return `${String(r.peringkat)} · ${r.hari as number}/${r.tempoh_sasaran_hari as number} hari${r.dieskalasi ? ' · dieskalasi' : ''}`;
            case 'kes_selesai':
                return `${r.tempoh_keseluruhan_hari as number}/${r.tempoh_sasaran_hari as number} hari · ${String(r.no_hak_milik ?? '—')} · peti besi ${String(r.kod_peti_besi ?? '—')}`;
            case 'portal_wakaf':
                return String(r.rujukan_portal ?? '');
            default:
                return '';
        }
    };

    return (
        <div className="overflow-x-auto">
            <Table
                head={
                    <>
                        <th>Kes</th>
                        <th className="hidden sm:table-cell">Daerah</th>
                        <th>Butiran</th>
                    </>
                }
            >
                {data.map((r) => (
                    <tr key={String(r.id)}>
                        <td>
                            <Link href={`/admin/per/kes/${String(r.id)}`} className="font-medium text-txt-primary hover:underline">
                                {String(r.no_kes)}
                            </Link>
                            <div className="mt-1">
                                <SaluranChip kod={String(r.saluran)} nama={String(r.nama_saluran)} warna={String(r.warna)} ringkas />
                            </div>
                        </td>
                        <td className="hidden sm:table-cell">{String(r.nama_daerah ?? '—')}</td>
                        <td className="text-body-sm break-words">{extra(r)}</td>
                    </tr>
                ))}
            </Table>
        </div>
    );
}
