'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Panel } from '@/components/ui/Panel';
import { Button } from '@/components/ui/Button';
import { Table } from '@/components/ui/Table';
import { Pagination } from '@/components/ui/Pagination';
import { Spinner } from '@/components/ui/Spinner';
import { ApiError } from '@/lib/api';
import { gisApi, useBoleh } from '@/lib/gis';
import { BadgeStatusImport } from './status';

const inputCls = 'block w-full rounded-md border border-otl-gray-300 bg-bg-white px-3 py-2 text-body-sm text-txt-black-900 shadow-button focus:outline-none focus:ring focus:ring-fr-primary';

const KATEGORI: Record<string, string> = { umum: 'Umum', lot: 'Lot (pelan ukur / PTG)', zoning: 'Zoning (JPBD / PBT)', infrastruktur: 'Infrastruktur', risiko: 'Kawasan risiko', sempadan: 'Sempadan rasmi (JUPEM)' };
const SRID: Array<[string, string]> = [
    ['', 'Ikut fail (.prj / CRS)'],
    ['4326', 'EPSG:4326 WGS 84'],
    ['4742', 'EPSG:4742 GDM2000 (geografi)'],
    ['3380', 'EPSG:3380 GDM2000 / Selangor Cassini'],
    ['3375', 'EPSG:3375 GDM2000 / Peninsula RSO'],
    ['3168', 'EPSG:3168 Kertau (RSO) / RSO Malaya'],
];

/** SRS-GIS-22: upload spatial files (SHP .zip, GeoJSON, KML/KMZ, DXF, GPKG) with validation report. */
export default function Page() {
    const router = useRouter();
    const can = useBoleh();
    const [page, setPage] = useState(1);
    const list = useQuery({ queryKey: ['gis', 'import', page], queryFn: () => gisApi.imports(page) });
    const [file, setFile] = useState<File | null>(null);
    const [nama, setNama] = useState('');
    const [kategori, setKategori] = useState('umum');
    const [srid, setSrid] = useState('');
    const [medanNoLot, setMedanNoLot] = useState('');
    const [kodMukim, setKodMukim] = useState('');
    const [medanWajib, setMedanWajib] = useState('');
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<string[]>([]);

    const submit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!file) return;
        setBusy(true);
        setErrors([]);
        const fd = new FormData();
        fd.append('fail', file);
        fd.append('nama_set_data', nama);
        fd.append('kategori', kategori);
        if (srid) fd.append('srid', srid);
        if (medanNoLot) fd.append('medan_no_lot', medanNoLot);
        if (kodMukim) fd.append('kod_mukim', kodMukim);
        medanWajib
            .split(',')
            .map((s) => s.trim())
            .filter(Boolean)
            .forEach((m) => fd.append('medan_wajib[]', m));
        try {
            const res = await gisApi.muatNaik(fd);
            router.push(`/admin/gis/import/${res.data.id}`);
        } catch (err) {
            setErrors(err instanceof ApiError && err.errors ? Object.values(err.errors).flat() : [(err as Error).message]);
        } finally {
            setBusy(false);
        }
    };

    return (
        <PageContainer>
            {can('gis.import.cipta') && (
                <Panel title="Muat naik fail spatial">
                    <form onSubmit={submit} className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label className="block text-body-sm md:col-span-2">
                            <span className="mb-1 block font-medium">Fail (Shapefile .zip, GeoJSON, KML/KMZ, DXF, GeoPackage)</span>
                            <input type="file" accept=".zip,.geojson,.json,.kml,.kmz,.dxf,.gpkg" onChange={(e) => setFile(e.target.files?.[0] ?? null)} className="block w-full text-body-sm" required />
                        </label>
                        <label className="block text-body-sm">
                            <span className="mb-1 block font-medium">Nama set data</span>
                            <input className={inputCls} value={nama} onChange={(e) => setNama(e.target.value)} required maxLength={150} />
                        </label>
                        <label className="block text-body-sm">
                            <span className="mb-1 block font-medium">Kategori</span>
                            <select className={inputCls} value={kategori} onChange={(e) => setKategori(e.target.value)}>
                                {Object.entries(KATEGORI).map(([k, v]) => (
                                    <option key={k} value={k}>
                                        {v}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="block text-body-sm">
                            <span className="mb-1 block font-medium">Sistem koordinat sumber</span>
                            <select className={inputCls} value={srid} onChange={(e) => setSrid(e.target.value)}>
                                {SRID.map(([k, v]) => (
                                    <option key={k} value={k}>
                                        {v}
                                    </option>
                                ))}
                            </select>
                            <span className="mt-1 block text-body-xs text-txt-black-500">DXF tiada CRS — pilih unjuran pelan (lazimnya Cassini Selangor).</span>
                        </label>
                        <label className="block text-body-sm">
                            <span className="mb-1 block font-medium">Medan wajib (dipisah koma)</span>
                            <input className={inputCls} value={medanWajib} onChange={(e) => setMedanWajib(e.target.value)} placeholder="cth. no_lot, nama" />
                        </label>
                        <label className="block text-body-sm">
                            <span className="mb-1 block font-medium">Medan nombor lot (padanan lot)</span>
                            <input className={inputCls} value={medanNoLot} onChange={(e) => setMedanNoLot(e.target.value)} placeholder="cth. no_lot" />
                        </label>
                        <label className="block text-body-sm">
                            <span className="mb-1 block font-medium">Kod mukim lalai</span>
                            <input className={inputCls} value={kodMukim} onChange={(e) => setKodMukim(e.target.value)} placeholder="cth. 100803" maxLength={6} />
                        </label>
                        {errors.length > 0 && (
                            <ul className="md:col-span-2 list-disc pl-5 text-body-sm text-txt-danger" role="alert">
                                {errors.map((m) => (
                                    <li key={m}>{m}</li>
                                ))}
                            </ul>
                        )}
                        <div className="md:col-span-2">
                            <Button type="submit" disabled={busy || !file || !nama}>
                                {busy ? 'Mengesahkan…' : 'Muat naik & sahkan'}
                            </Button>
                            <p className="mt-2 text-body-xs text-txt-black-500">
                                Fail diimbas antivirus melalui DMS, diunjurkan ke EPSG:4326, dan setiap ciri disemak (koordinat kosong, geometri tidak sah, luar Selangor, medan wajib, pertindihan). Fail yang mempunyai ralat ditolak dengan laporan.
                            </p>
                        </div>
                    </form>
                </Panel>
            )}

            {list.isLoading && <Spinner />}
            {list.data && (
                <>
                    <Table
                        head={
                            <>
                                <th>Set data</th>
                                <th>Format</th>
                                <th>Kategori</th>
                                <th className="text-right">Ciri</th>
                                <th className="text-right">Ralat</th>
                                <th>Status</th>
                                <th>Tarikh</th>
                            </>
                        }
                    >
                        {list.data.data.map((i) => (
                            <tr key={i.id}>
                                <td>
                                    <Link href={`/admin/gis/import/${i.id}`} className="font-medium text-txt-primary hover:underline">
                                        {i.nama_set_data}
                                    </Link>
                                    <span className="block text-body-xs text-txt-black-500">{i.nama_fail}</span>
                                </td>
                                <td className="uppercase">{i.format}</td>
                                <td>{KATEGORI[i.kategori] ?? i.kategori}</td>
                                <td className="text-right">{i.bilangan_ciri}</td>
                                <td className="text-right">{i.bilangan_ralat}</td>
                                <td>
                                    <BadgeStatusImport status={i.status} />
                                </td>
                                <td className="whitespace-nowrap">{i.dicipta_pada ? new Date(i.dicipta_pada).toLocaleString('ms-MY') : '—'}</td>
                            </tr>
                        ))}
                        {list.data.data.length === 0 && (
                            <tr>
                                <td colSpan={7} className="text-txt-black-500">
                                    Belum ada import.
                                </td>
                            </tr>
                        )}
                    </Table>
                    {list.data.last_page > 1 && (
                        <Pagination currentPage={list.data.current_page} lastPage={list.data.last_page} onPageChange={setPage} firstItem={list.data.from ?? 0} lastItem={list.data.to ?? 0} total={list.data.total} />
                    )}
                </>
            )}
        </PageContainer>
    );
}
