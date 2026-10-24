'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Panel } from '@/components/ui/Panel';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Alert } from '@/components/ui/Alert';
import { Table } from '@/components/ui/Table';
import { Pagination } from '@/components/ui/Pagination';
import { Spinner } from '@/components/ui/Spinner';
import { PetaSisdata } from '@/components/gis/PetaSisdata';
import { boundsOf } from '@/components/gis/gaya';
import { formatLuas, gisApi, LABEL_RALAT, LABEL_STATUS_RALAT, muatTurun, useBoleh, type KoleksiCiri, type RalatKualiti } from '@/lib/gis';

/**
 * SRS-GIS-27/28 spatial QA: invalid geometry (with ST_MakeValid suggestion),
 * overlaps, gaps, missing geometry, outside Selangor / mukim. Corrections are
 * made on the lot's spatial page in MOD-PRO (link per finding).
 */
export default function Page() {
    const can = useBoleh();
    const qc = useQueryClient();
    const [status, setStatus] = useState('terbuka');
    const [jenis, setJenis] = useState('');
    const [page, setPage] = useState(1);
    const [focus, setFocus] = useState<KoleksiCiri | null>(null);
    const list = useQuery({ queryKey: ['gis', 'kualiti', status, jenis, page], queryFn: () => gisApi.kualiti({ status, jenis_ralat: jenis || undefined, page }) });
    const all = useQuery({ queryKey: ['gis', 'kualiti', 'ciri'], queryFn: gisApi.ciriKualiti });
    const run = useMutation({ mutationFn: gisApi.jalankanKualiti, onSuccess: () => qc.invalidateQueries({ queryKey: ['gis', 'kualiti'] }) });
    const update = useMutation({
        mutationFn: ({ id, s }: { id: number; s: string }) => gisApi.kemasKiniKualiti(id, { status_pembetulan: s }),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['gis', 'kualiti'] }),
    });

    const focusBbox = focus ? boundsOf(focus.features.map((f) => f.geometry)) : null;

    const show = async (r: RalatKualiti) => {
        const c = await gisApi.cadangan(r.id);
        const features: KoleksiCiri['features'] = [];
        if (c.asal) features.push({ type: 'Feature', geometry: c.asal, properties: { label: 'asal' } });
        if (c.cadangan) features.push({ type: 'Feature', geometry: c.cadangan, properties: { label: 'cadangan' } });
        setFocus({ type: 'FeatureCollection', features });
    };

    return (
        <PageContainer>
            <div className="flex flex-wrap items-end gap-3">
                <label className="text-body-sm">
                    <span className="mb-1 block font-medium">Status</span>
                    <select className="rounded-md border border-otl-gray-300 bg-bg-white px-3 py-2" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }}>
                        <option value="terbuka">Terbuka</option>
                        {Object.entries(LABEL_STATUS_RALAT).map(([k, v]) => (
                            <option key={k} value={k}>
                                {v}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="text-body-sm">
                    <span className="mb-1 block font-medium">Jenis ralat</span>
                    <select className="rounded-md border border-otl-gray-300 bg-bg-white px-3 py-2" value={jenis} onChange={(e) => { setJenis(e.target.value); setPage(1); }}>
                        <option value="">Semua</option>
                        {Object.entries(LABEL_RALAT).map(([k, v]) => (
                            <option key={k} value={k}>
                                {v}
                            </option>
                        ))}
                    </select>
                </label>
                {can('gis.kualiti.kemas_kini') && (
                    <Button onClick={() => run.mutate()} disabled={run.isPending}>
                        {run.isPending ? 'Menyemak…' : 'Jalankan semakan'}
                    </Button>
                )}
                <Button variant="secondary" onClick={() => muatTurun('/gis/kualiti/eksport?format=xlsx', {}, 'laporan-kualiti-spatial.xlsx')}>
                    Laporan ralat (Excel)
                </Button>
            </div>
            {run.data && (
                <Alert type="success">
                    Semakan selesai: {Object.entries(run.data.dikesan).map(([k, n]) => `${LABEL_RALAT[k] ?? k} ${n}`).join(' · ')}. Ditutup automatik: {run.data.ditutup}.
                </Alert>
            )}
            {list.data && (
                <div className="flex flex-wrap gap-2">
                    {Object.entries(list.data.ringkasan).map(([k, n]) => (
                        <Badge key={k} variant="warning">
                            {LABEL_RALAT[k] ?? k}: {n}
                        </Badge>
                    ))}
                </div>
            )}
            <div className="grid grid-cols-1 gap-6 xl:grid-cols-5">
                <div className="xl:col-span-3 space-y-4">
                    {list.isLoading && <Spinner />}
                    {list.data && (
                        <>
                            <Table
                                head={
                                    <>
                                        <th>Jenis</th>
                                        <th>Perihal</th>
                                        <th>Status</th>
                                        <th>Tindakan</th>
                                    </>
                                }
                            >
                                {list.data.data.map((r) => (
                                    <tr key={r.id}>
                                        <td className="whitespace-nowrap">{LABEL_RALAT[r.jenis_ralat] ?? r.jenis_ralat}</td>
                                        <td>
                                            <button type="button" className="text-left hover:underline" onClick={() => show(r)}>
                                                {r.perihal}
                                            </button>
                                            {r.nilai !== null && <span className="block text-body-xs text-txt-black-500">{formatLuas(r.nilai)}</span>}
                                            {r.cadangan && <span className="block text-body-xs text-txt-black-500">{r.cadangan}</span>}
                                        </td>
                                        <td>{LABEL_STATUS_RALAT[r.status_pembetulan]}</td>
                                        <td className="space-y-1">
                                            {r.pautan && (
                                                <Link href={r.pautan} className="block text-body-xs font-medium text-txt-primary hover:underline">
                                                    Betulkan di Profil Lot →
                                                </Link>
                                            )}
                                            {can('gis.kualiti.kemas_kini') && (
                                                <select
                                                    aria-label="Kemas kini status"
                                                    className="rounded border border-otl-gray-300 px-1 py-0.5 text-body-xs"
                                                    value={r.status_pembetulan}
                                                    onChange={(e) => update.mutate({ id: r.id, s: e.target.value })}
                                                >
                                                    {Object.entries(LABEL_STATUS_RALAT).map(([k, v]) => (
                                                        <option key={k} value={k}>
                                                            {v}
                                                        </option>
                                                    ))}
                                                </select>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {list.data.data.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="text-txt-black-500">
                                            Tiada penemuan.
                                        </td>
                                    </tr>
                                )}
                            </Table>
                            {list.data.last_page > 1 && <Pagination currentPage={list.data.current_page} lastPage={list.data.last_page} onPageChange={setPage} firstItem={list.data.from ?? 0} lastItem={list.data.to ?? 0} total={list.data.total} />}
                        </>
                    )}
                </div>
                <Panel title="Peta penemuan" className="xl:col-span-2">
                    <PetaSisdata layers={['gis.lot']} overlay={focus ?? all.data ?? null} focus={focusBbox ? { bbox: focusBbox } : undefined} height={420} showLegend={false} label="Peta penemuan kualiti spatial" />
                    <p className="mt-2 text-body-xs text-txt-black-500">Merah: kawasan bermasalah. Klik perihal untuk melihat geometri asal dan cadangan ST_MakeValid.</p>
                </Panel>
            </div>
        </PageContainer>
    );
}
