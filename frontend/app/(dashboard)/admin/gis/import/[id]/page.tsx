'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Panel } from '@/components/ui/Panel';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { Table } from '@/components/ui/Table';
import { Spinner } from '@/components/ui/Spinner';
import { SummaryList } from '@/components/ui/SummaryList';
import { PetaSisdata } from '@/components/gis/PetaSisdata';
import { boundsOf } from '@/components/gis/gaya';
import { gisApi, muatTurun, useBoleh, type Ciri } from '@/lib/gis';
import { BadgeStatusImport } from '../status';

const inputCls = 'block w-full rounded-md border border-otl-gray-300 bg-bg-white px-3 py-2 text-body-sm';

/** Import report (SRS-GIS-22/27): every failing feature named; accept only clean files; publish matched lots via MOD-PRO. */
export default function Page() {
    const { id } = useParams<{ id: string }>();
    const can = useBoleh();
    const qc = useQueryClient();
    const detail = useQuery({ queryKey: ['gis', 'import', id], queryFn: () => gisApi.import(id) });
    const ciri = useQuery({ queryKey: ['gis', 'import', id, 'ciri'], queryFn: () => gisApi.ciriImport(id) });
    const [pemilik, setPemilik] = useState('');
    const [sumber, setSumber] = useState('');
    const [had, setHad] = useState('');
    const [msg, setMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const [published, setPublished] = useState<Record<string, string>>({});

    const terima = useMutation({
        mutationFn: () => gisApi.terima(id, { pemilik_data: pemilik, sumber_data: sumber, had_penggunaan: had }),
        onSuccess: () => {
            setMsg({ type: 'success', text: 'Set data diterima dan diterbitkan sebagai lapisan "Set Data Diimport".' });
            qc.invalidateQueries({ queryKey: ['gis'] });
        },
        onError: (e: Error) => setMsg({ type: 'error', text: e.message }),
    });
    const batal = useMutation({ mutationFn: () => gisApi.batal(id), onSuccess: () => qc.invalidateQueries({ queryKey: ['gis', 'import'] }) });

    const publish = async (f: Ciri) => {
        const lot = String(f.properties.lot_id ?? '');
        if (!lot || !f.geometry) return;
        try {
            await gisApi.hantarKeProfil(lot, f.geometry, detail.data?.data.kategori === 'lot' ? 'juruukur' : 'pbt', `Import GIS ${detail.data?.data.nama_set_data} #${String(f.properties.no_ciri)}`);
            setPublished((p) => ({ ...p, [String(f.id)]: 'Dihantar ke Profil Lot (versi baharu, belum disahkan)' }));
        } catch (e) {
            setPublished((p) => ({ ...p, [String(f.id)]: (e as Error).message }));
        }
    };

    if (detail.isLoading) return <PageContainer><Spinner /></PageContainer>;
    if (!detail.data) return <PageContainer><Alert type="error">Rekod import tidak dijumpai.</Alert></PageContainer>;
    const imp = detail.data.data;
    const features = ciri.data?.features ?? [];
    const bbox = boundsOf(features.map((f) => f.geometry));
    const matched = features.filter((f) => f.properties.lot_id);

    return (
        <PageContainer>
            <div className="flex flex-wrap items-center gap-3">
                <h3 className="text-heading-3xs font-semibold text-txt-black-900">{imp.nama_set_data}</h3>
                <BadgeStatusImport status={imp.status} />
                <Link href="/admin/gis/import" className="text-body-sm text-txt-primary hover:underline">
                    ← Senarai import
                </Link>
            </div>
            {msg && <Alert type={msg.type}>{msg.text}</Alert>}
            {imp.ralat_fail.length > 0 && (
                <Alert type="error">
                    <ul className="list-disc pl-5">
                        {imp.ralat_fail.map((r) => (
                            <li key={r}>{r}</li>
                        ))}
                    </ul>
                </Alert>
            )}
            {imp.amaran.length > 0 && (
                <Alert type="warning">
                    <ul className="list-disc pl-5">
                        {imp.amaran.map((r) => (
                            <li key={r}>{r}</li>
                        ))}
                    </ul>
                </Alert>
            )}
            <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <Panel title="Ringkasan">
                    <SummaryList
                        items={[
                            { label: 'Fail', value: imp.nama_fail },
                            { label: 'Format', value: imp.format.toUpperCase() },
                            { label: 'Sistem koordinat asal', value: imp.srid_asal ? `EPSG:${imp.srid_asal}` : '—' },
                            { label: 'Ciri', value: `${imp.bilangan_ciri} (${imp.bilangan_sah} sah, ${imp.bilangan_ralat} ralat)` },
                            { label: 'Medan', value: imp.medan.join(', ') || '—' },
                            { label: 'Lot dipadankan', value: String(matched.length) },
                        ]}
                    />
                    <div className="mt-4 flex flex-wrap gap-2">
                        <Button size="sm" variant="secondary" onClick={() => muatTurun(`/gis/import/${id}/laporan?format=csv`, {}, 'laporan-import.csv')}>
                            Laporan ralat (CSV)
                        </Button>
                        <Button size="sm" variant="secondary" onClick={() => muatTurun(`/gis/import/${id}/laporan?format=xlsx`, {}, 'laporan-import.xlsx')}>
                            Laporan ralat (Excel)
                        </Button>
                        {can('gis.import.cipta') && imp.status !== 'diterima' && imp.status !== 'dibatalkan' && (
                            <Button size="sm" variant="danger" onClick={() => batal.mutate()}>
                                Batalkan import
                            </Button>
                        )}
                    </div>
                </Panel>
                <Panel title="Pratonton">
                    {features.length > 0 ? (
                        <PetaSisdata layers={['gis.sempadan_mukim']} overlay={ciri.data ?? null} focus={bbox ? { bbox } : undefined} height={320} showLegend={false} label="Pratonton ciri import" />
                    ) : (
                        <p className="text-body-sm text-txt-black-500">Tiada geometri untuk dipaparkan.</p>
                    )}
                </Panel>
            </div>

            {detail.data.ralat_ciri.length > 0 && (
                <Panel title={`Ciri bermasalah (${detail.data.ralat_ciri.length})`}>
                    <Table
                        head={
                            <>
                                <th>No. ciri</th>
                                <th>Ralat</th>
                                <th>Atribut</th>
                            </>
                        }
                    >
                        {detail.data.ralat_ciri.map((r) => (
                            <tr key={r.no_ciri}>
                                <td>#{r.no_ciri}</td>
                                <td>
                                    <ul className="list-disc pl-4 text-txt-danger">
                                        {r.ralat.map((m) => (
                                            <li key={m}>{m}</li>
                                        ))}
                                    </ul>
                                </td>
                                <td className="max-w-xs truncate text-body-xs text-txt-black-500">{JSON.stringify(r.atribut)}</td>
                            </tr>
                        ))}
                    </Table>
                </Panel>
            )}

            {imp.status === 'sedia' && can('gis.import.cipta') && (
                <Panel title="Terima set data (metadata SRS-GIS-29)">
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <input className={inputCls} placeholder="Sumber data (cth. JPBD Selangor)" value={sumber} onChange={(e) => setSumber(e.target.value)} aria-label="Sumber data" />
                        <input className={inputCls} placeholder="Pemilik data" value={pemilik} onChange={(e) => setPemilik(e.target.value)} aria-label="Pemilik data" />
                        <input className={inputCls} placeholder="Had penggunaan" value={had} onChange={(e) => setHad(e.target.value)} aria-label="Had penggunaan" />
                    </div>
                    <Button className="mt-3" onClick={() => terima.mutate()} disabled={terima.isPending}>
                        Terima & terbitkan
                    </Button>
                </Panel>
            )}

            {imp.status === 'diterima' && matched.length > 0 && can('gis.import.cipta') && (
                <Panel title="Terbitkan geometri ke Profil Lot (MOD-PRO)">
                    <p className="mb-3 text-body-sm text-txt-black-700">
                        Geometri lot dikemas kini melalui API spatial MOD-PRO (versi baharu, perlu disahkan pegawai). Memerlukan kebenaran pro.spatial.cipta.
                    </p>
                    <ul className="divide-y divide-otl-divider text-body-sm">
                        {matched.map((f) => (
                            <li key={String(f.id)} className="flex flex-wrap items-center justify-between gap-2 py-2">
                                <span>
                                    #{String(f.properties.no_ciri)} →{' '}
                                    <Link href={`/admin/pro/lot/${String(f.properties.lot_id)}`} className="text-txt-primary hover:underline">
                                        lot
                                    </Link>
                                </span>
                                {published[String(f.id)] ? (
                                    <span className="text-body-xs text-txt-black-500">{published[String(f.id)]}</span>
                                ) : (
                                    <Button size="sm" variant="outline" onClick={() => publish(f)}>
                                        Hantar ke profil lot
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                </Panel>
            )}
        </PageContainer>
    );
}
