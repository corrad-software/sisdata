'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useMemo, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Panel } from '@/components/ui/Panel';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Table } from '@/components/ui/Table';
import { Spinner } from '@/components/ui/Spinner';
import { PetaSisdata } from '@/components/gis/PetaSisdata';
import { boundsOf } from '@/components/gis/gaya';
import { formatJarak, formatLuas, gisApi, useBoleh, type KoleksiCiri } from '@/lib/gis';

/**
 * SRS-GIS-30: geometry versions of a lot (MOD-PRO's data_spatial is the
 * version store) — list, compare two versions on the map, restore an older
 * version by re-submitting it through MOD-PRO's spatial API (new version,
 * audited, to be verified again).
 */
export default function Page() {
    const { id } = useParams<{ id: string }>();
    const can = useBoleh();
    const qc = useQueryClient();
    const versions = useQuery({ queryKey: ['gis', 'versi', id], queryFn: () => gisApi.versi(id) });
    const [a, setA] = useState<number | null>(null);
    const [b, setB] = useState<number | null>(null);
    const [msg, setMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const rows = useMemo(() => versions.data?.data ?? [], [versions.data]);
    const va = a ?? rows[1]?.id ?? null;
    const vb = b ?? rows[0]?.id ?? null;
    const diff = useQuery({ queryKey: ['gis', 'banding', id, va, vb], queryFn: () => gisApi.banding(id, va as number, vb as number), enabled: va !== null && vb !== null && va !== vb });

    const overlay = useMemo<KoleksiCiri | null>(() => {
        const sel = rows.filter((r) => r.id === va || r.id === vb);
        if (sel.length === 0) return null;
        return { type: 'FeatureCollection', features: sel.map((r) => ({ type: 'Feature', geometry: r.geometri, properties: { label: `Versi ${r.versi}`, warna: r.id === va ? '#2563EB' : '#E11D48', ketelusan: 0.2 } })) };
    }, [rows, va, vb]);
    const bbox = overlay ? boundsOf(overlay.features.map((f) => f.geometry)) : null;

    const restore = async (versionId: number) => {
        const v = rows.find((r) => r.id === versionId);
        if (!v) return;
        try {
            await gisApi.hantarKeProfil(id, v.geometri, v.sumber_data, `Pemulihan versi ${v.versi} (MOD-GIS)`);
            setMsg({ type: 'success', text: `Versi ${v.versi} dipulihkan sebagai versi baharu (perlu disahkan semula).` });
            qc.invalidateQueries({ queryKey: ['gis', 'versi', id] });
        } catch (e) {
            setMsg({ type: 'error', text: (e as Error).message });
        }
    };

    if (versions.isLoading) return <PageContainer><Spinner /></PageContainer>;
    if (versions.error) return <PageContainer><Alert type="error">Lot tidak dijumpai atau di luar skop anda.</Alert></PageContainer>;

    return (
        <PageContainer>
            <Link href={`/admin/pro/lot/${id}`} className="text-body-sm text-txt-primary hover:underline">
                ← Profil Hartanah 360°
            </Link>
            {msg && <Alert type={msg.type}>{msg.text}</Alert>}
            <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <Panel title="Versi geometri">
                    <Table
                        head={
                            <>
                                <th>Versi</th>
                                <th>A</th>
                                <th>B</th>
                                <th>Keluasan</th>
                                <th>Sumber</th>
                                <th>Tarikh</th>
                                <th />
                            </>
                        }
                    >
                        {rows.map((r) => (
                            <tr key={r.id}>
                                <td>
                                    {r.versi} {r.semasa && <Badge variant="success">Semasa</Badge>}
                                </td>
                                <td>
                                    <input type="radio" name="va" checked={va === r.id} onChange={() => setA(r.id)} aria-label={`Versi ${r.versi} sebagai A`} />
                                </td>
                                <td>
                                    <input type="radio" name="vb" checked={vb === r.id} onChange={() => setB(r.id)} aria-label={`Versi ${r.versi} sebagai B`} />
                                </td>
                                <td>{formatLuas(r.keluasan_m2)}</td>
                                <td>{r.sumber_data}</td>
                                <td className="whitespace-nowrap">
                                    {new Date(r.dicipta_pada).toLocaleString('ms-MY')}
                                    {r.pemuat_naik && <span className="block text-body-xs text-txt-black-500">{r.pemuat_naik}</span>}
                                </td>
                                <td>
                                    {!r.semasa && can('pro.spatial.cipta') && (
                                        <Button size="sm" variant="outline" onClick={() => restore(r.id)}>
                                            Pulihkan
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </Table>
                    {diff.data && (
                        <dl className="mt-4 grid grid-cols-2 gap-2 text-body-sm">
                            <dt className="text-txt-black-500">Beza keluasan (B − A)</dt>
                            <dd>{formatLuas(diff.data.beza_luas_m2)}</dd>
                            <dt className="text-txt-black-500">Ditambah / dibuang</dt>
                            <dd>
                                {formatLuas(diff.data.luas_ditambah_m2)} / {formatLuas(diff.data.luas_dibuang_m2)}
                            </dd>
                            <dt className="text-txt-black-500">Anjakan maksimum (Hausdorff, Cassini)</dt>
                            <dd>{formatJarak(diff.data.hausdorff_m)}</dd>
                            <dt className="text-txt-black-500">Sama</dt>
                            <dd>{diff.data.sama ? 'Ya' : 'Tidak'}</dd>
                        </dl>
                    )}
                </Panel>
                <Panel title="Perbandingan di peta">
                    <PetaSisdata layers={[]} overlay={overlay} focus={bbox ? { bbox } : undefined} height={420} showLegend={false} label="Peta perbandingan versi geometri" />
                    <p className="mt-2 text-body-xs text-txt-black-700">
                        <span className="mr-3"><span className="inline-block h-3 w-3 rounded-sm align-middle" style={{ backgroundColor: '#2563EB' }} aria-hidden="true" /> Versi A</span>
                        <span><span className="inline-block h-3 w-3 rounded-sm align-middle" style={{ backgroundColor: '#E11D48' }} aria-hidden="true" /> Versi B</span>
                    </p>
                </Panel>
            </div>
        </PageContainer>
    );
}
