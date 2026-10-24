'use client';

import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Panel } from '@/components/ui/Panel';
import { StatCard } from '@/components/ui/StatCard';
import { Table } from '@/components/ui/Table';
import { Spinner } from '@/components/ui/Spinner';
import { PetaSisdata } from '@/components/gis/PetaSisdata';
import { EntriList } from '@/components/gis/Legenda';
import { gisApi, type KoleksiCiri } from '@/lib/gis';
import { WARNA_BAHAGIAN_PEMILIK, WARNA_KATEGORI_GUNA_TANAH, warnaKategori } from '@/lib/legend';

const KELAS = ['#DBEAFE', '#93C5FD', '#3B82F6', '#1D4ED8', '#1E3A8A'];

/** SRS-GIS-34: distribution of MAIS land by daerah / mukim / category, with a choropleth map. */
export default function Page() {
    const [peringkat, setPeringkat] = useState<'daerah' | 'mukim'>('daerah');
    const { data, isLoading, error } = useQuery({ queryKey: ['gis', 'statistik', peringkat], queryFn: () => gisApi.statistik({ peringkat, geometri: true }) });

    const max = Math.max(1, ...(data?.baris.map((b) => b.bilangan_lot) ?? [1]));
    const kelas = (n: number) => KELAS[Math.min(KELAS.length - 1, Math.floor((n / max) * (KELAS.length - 1) + 0.0001))];
    const overlay = useMemo<KoleksiCiri | null>(
        () =>
            data
                ? {
                      type: 'FeatureCollection',
                      features: data.baris.filter((b) => b.geometri).map((b) => ({ type: 'Feature', geometry: b.geometri ?? null, properties: { label: b.nama ?? b.kod, bilangan: b.bilangan_lot, warna: kelas(b.bilangan_lot), garis: '#1E3A8A', ketelusan: 0.65 } })),
                  }
                : null,
        // eslint-disable-next-line react-hooks/exhaustive-deps -- kelas derives from data
        [data],
    );

    return (
        <PageContainer>
            <div className="flex flex-wrap items-center gap-3">
                <label htmlFor="peringkat" className="text-body-sm font-medium text-txt-black-900">
                    Peringkat
                </label>
                <select id="peringkat" value={peringkat} onChange={(e) => setPeringkat(e.target.value as 'daerah' | 'mukim')} className="rounded-md border border-otl-gray-300 bg-bg-white px-3 py-2 text-body-sm">
                    <option value="daerah">Daerah</option>
                    <option value="mukim">Mukim</option>
                </select>
            </div>
            {isLoading && <Spinner />}
            {error && <p className="text-body-sm text-txt-danger">Statistik tidak dapat dimuatkan.</p>}
            {data && (
                <>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <StatCard label="Jumlah lot" value={data.jumlah.bilangan_lot.toLocaleString('ms-MY')} />
                        <StatCard label="Lot bergeometri" value={data.jumlah.ada_geometri.toLocaleString('ms-MY')} />
                        <StatCard label="Keluasan (hektar)" value={data.jumlah.luas_hektar.toLocaleString('ms-MY', { maximumFractionDigits: 2 })} />
                    </div>
                    <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                        <Panel title={`Taburan lot mengikut ${peringkat}`}>
                            <PetaSisdata layers={['gis.sempadan_daerah']} overlay={overlay} height={380} showLegend={false} label="Peta taburan aset" />
                            <ul className="mt-2 flex flex-wrap gap-3 text-body-xs text-txt-black-700">
                                {data.baris.slice(0, 8).map((b) => (
                                    <li key={b.kod} className="flex items-center gap-1.5">
                                        <span className="inline-block h-3 w-3 rounded-sm" style={{ backgroundColor: kelas(b.bilangan_lot) }} aria-hidden="true" />
                                        {b.nama ?? b.kod}: {b.bilangan_lot}
                                    </li>
                                ))}
                            </ul>
                        </Panel>
                        <div className="space-y-6">
                            <Panel title="Mengikut kategori guna tanah">
                                <Bar rows={data.kategori.map((k) => ({ label: WARNA_KATEGORI_GUNA_TANAH[k.kod]?.label ?? k.kod, n: k.bilangan, warna: warnaKategori(k.kod).warna }))} />
                            </Panel>
                            <Panel title="Mengikut bahagian pemilik">
                                <Bar rows={data.bahagian.map((k) => ({ label: WARNA_BAHAGIAN_PEMILIK[k.kod]?.label ?? k.kod, n: k.bilangan, warna: WARNA_BAHAGIAN_PEMILIK[k.kod]?.warna ?? '#52525B' }))} />
                                <EntriList entri={Object.entries(WARNA_BAHAGIAN_PEMILIK).map(([kod, w]) => ({ nilai: kod, label: w.label, warna: w.warna }))} />
                            </Panel>
                        </div>
                    </div>
                    <Table
                        head={
                            <>
                                <th>{peringkat === 'daerah' ? 'Daerah' : 'Mukim'}</th>
                                <th className="text-right">Lot</th>
                                <th className="text-right">Bergeometri</th>
                                <th className="text-right">Hartanah</th>
                                <th className="text-right">Wakaf</th>
                                <th className="text-right">Baitulmal</th>
                                <th className="text-right">Keluasan (ha)</th>
                            </>
                        }
                    >
                        {data.baris.map((b) => (
                            <tr key={b.kod}>
                                <td>
                                    {b.nama ?? '—'} <span className="text-txt-black-500">({b.kod})</span>
                                </td>
                                <td className="text-right">{b.bilangan_lot}</td>
                                <td className="text-right">{b.ada_geometri}</td>
                                <td className="text-right">{b.hartanah}</td>
                                <td className="text-right">{b.wakaf}</td>
                                <td className="text-right">{b.baitulmal}</td>
                                <td className="text-right">{b.luas_hektar.toLocaleString('ms-MY', { maximumFractionDigits: 2 })}</td>
                            </tr>
                        ))}
                    </Table>
                </>
            )}
        </PageContainer>
    );
}

function Bar({ rows }: { rows: Array<{ label: string; n: number; warna: string }> }) {
    const max = Math.max(1, ...rows.map((r) => r.n));
    return (
        <ul className="space-y-2">
            {rows.map((r) => (
                <li key={r.label} className="text-body-sm">
                    <div className="flex justify-between">
                        <span>{r.label}</span>
                        <span className="text-txt-black-500">{r.n}</span>
                    </div>
                    <div className="mt-1 h-2 rounded bg-bg-washed">
                        <div className="h-2 rounded" style={{ width: `${(r.n / max) * 100}%`, backgroundColor: r.warna }} />
                    </div>
                </li>
            ))}
        </ul>
    );
}
