'use client';

import { useState } from 'react';
import type { FormEvent } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { Callout } from '@/components/ui/Callout';
import { Modal } from '@/components/ui/Modal';
import { Table } from '@/components/ui/Table';
import { StatCard } from '@/components/ui/StatCard';
import { InputLabel } from '@/components/ui/InputLabel';
import { StackedBarChart } from '@/components/ui/charts/StackedBarChart';
import { Card, EmptyRow, FieldError, Loading, SelectField, SpatialBadge, Terhad, inputClass } from '@/components/pro/common';
import { LotMap } from '@/components/pro/LotMap';
import {
    MEDAN_LABEL,
    STATUS_VERSI_LABEL,
    errorMessage,
    fieldErrors,
    fmtNilai,
    fmtNombor,
    fmtRM,
    fmtTarikh,
    namaRuj,
    proApi,
    useCan,
    useRujukan,
    type Kewangan,
    type LotButiran,
    type LotFeature,
} from '@/lib/pro';

// ---------------------------------------------------------------- Kewangan

const JENIS_LABEL: Record<string, string> = {
    hasil_sewaan: 'Hasil sewaan',
    kos_cukai: 'Kos cukai',
    kos_penyelenggaraan: 'Kos penyelenggaraan',
    kos_guaman: 'Kos guaman',
};

export function KewanganPanel({ kewangan }: { kewangan: Kewangan }) {
    const [mode, setMode] = useState<'tahunan' | 'bulanan'>('tahunan');

    if (!kewangan.tersedia) {
        return (
            <section className="rounded-lg border border-dashed border-otl-gray-300 bg-bg-washed p-4 sm:p-6 space-y-1">
                <h3 className="font-heading font-semibold text-body-md text-txt-black-500">Kedudukan kewangan</h3>
                <p className="text-body-sm text-txt-black-500">{kewangan.mesej ?? 'Belum tersedia — data hasil dan kos akan dipaparkan apabila modul berkaitan diaktifkan.'}</p>
            </section>
        );
    }

    const rows = mode === 'tahunan' ? kewangan.tahunan : kewangan.bulanan.slice(-12);
    const jenis = (kewangan.jenis ?? Object.keys(JENIS_LABEL)).filter((j) => rows.some((r) => Number(r[j] ?? 0) !== 0));

    return (
        <Card
            title="Kedudukan kewangan"
            actions={
                <div className="inline-flex rounded-md shadow-button" role="group" aria-label="Tempoh">
                    {(['tahunan', 'bulanan'] as const).map((m, i) => (
                        <button
                            key={m}
                            type="button"
                            aria-pressed={mode === m}
                            onClick={() => setMode(m)}
                            className={`px-3 py-1.5 text-body-xs font-medium border border-otl-gray-300 ${i === 0 ? 'rounded-s-md' : 'rounded-e-md -ms-px'} ${
                                mode === m ? 'bg-primary-600 text-white border-primary-600' : 'bg-bg-white text-txt-black-900 hover:bg-bg-washed'
                            }`}
                        >
                            {m === 'tahunan' ? 'Tahunan' : '12 bulan terakhir'}
                        </button>
                    ))}
                </div>
            }
        >
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <StatCard label="Jumlah hasil" value={fmtRM(kewangan.jumlah.hasil)} />
                <StatCard label="Jumlah kos" value={fmtRM(kewangan.jumlah.kos)} />
                <StatCard
                    label="Pulangan bersih"
                    value={fmtRM(kewangan.jumlah.pulangan_bersih)}
                    trend={kewangan.jumlah.pulangan_bersih >= 0 ? 'up' : 'down'}
                    change={kewangan.jumlah.pulangan_bersih >= 0 ? 'untung' : 'rugi'}
                />
            </div>

            <div className="overflow-x-auto">
                <div className="min-w-[20rem]">
                    <StackedBarChart
                        mode="grouped"
                        data={rows.map((r) => ({ label: r.tempoh, values: { Hasil: r.jumlah_hasil, Kos: r.jumlah_kos } }))}
                        colors={{ Hasil: 'bg-success-600', Kos: 'bg-danger-600' }}
                    />
                </div>
            </div>

            <div className="overflow-x-auto">
                <Table
                    head={
                        <>
                            <th>Tempoh</th>
                            {jenis.map((j) => (
                                <th key={j} className="hidden md:table-cell text-right">
                                    {JENIS_LABEL[j] ?? j}
                                </th>
                            ))}
                            <th className="text-right">Hasil</th>
                            <th className="text-right">Kos</th>
                            <th className="text-right">Bersih</th>
                        </>
                    }
                >
                    {rows.map((r) => (
                        <tr key={r.tempoh}>
                            <td className="whitespace-nowrap">{r.tempoh}</td>
                            {jenis.map((j) => (
                                <td key={j} className="hidden md:table-cell text-right whitespace-nowrap">
                                    {fmtRM(Number(r[j] ?? 0))}
                                </td>
                            ))}
                            <td className="text-right whitespace-nowrap">{fmtRM(r.jumlah_hasil)}</td>
                            <td className="text-right whitespace-nowrap">{fmtRM(r.jumlah_kos)}</td>
                            <td className={`text-right whitespace-nowrap font-medium ${r.pulangan_bersih < 0 ? 'text-txt-danger' : 'text-txt-success'}`}>{fmtRM(r.pulangan_bersih)}</td>
                        </tr>
                    ))}
                </Table>
            </div>
        </Card>
    );
}

// ---------------------------------------------------------------- Ruang

export function RuangPanel({ lot, geojson }: { lot: LotButiran; geojson: LotFeature | null }) {
    const can = useCan();
    const queryClient = useQueryClient();
    const { data: ruj } = useRujukan();
    const spatial = useQuery({ queryKey: ['pro', 'lot', lot.id, 'spatial'], queryFn: () => proApi.spatial(lot.id) });
    const semasa = spatial.data?.semasa ?? geojson;
    const current = spatial.data?.data.find((s) => s.semasa) ?? null;
    const archived = lot.status_lot === 'arkib';

    const refresh = () => queryClient.invalidateQueries({ queryKey: ['pro', 'lot', lot.id] });

    return (
        <div className="space-y-6">
            <Card title="Peta lot">
                {!lot.spatial.disahkan && (
                    <Alert type={lot.spatial.status === 'bertindih' || lot.spatial.status === 'tiada_geometri' ? 'error' : 'warning'}>
                        <span className="inline-flex flex-wrap items-center gap-2">
                            Status geometri: <SpatialBadge spatial={lot.spatial} />
                            {lot.spatial.status === 'tiada_geometri' ? 'Muat naik geometri lot di bawah.' : 'Geometri ini belum disahkan dan tidak boleh dijadikan rujukan muktamad.'}
                        </span>
                    </Alert>
                )}
                <LotMap data={semasa} height={380} label={`Peta lot ${lot.no_lot ?? ''}`} />
            </Card>

            {can('pro.spatial.lulus') && current && !archived && <PengesahanForm spatialId={current.id} status={current.status_pengesahan} onDone={refresh} />}

            {can('pro.spatial.cipta') && !archived && <GeometriForm lotId={lot.id} onDone={refresh} />}

            <Card title="Sejarah geometri">
                {spatial.isLoading ? (
                    <Loading />
                ) : spatial.error ? (
                    <Alert type="error">{errorMessage(spatial.error)}</Alert>
                ) : (
                    <div className="overflow-x-auto">
                        <Table
                            head={
                                <>
                                    <th>Versi</th>
                                    <th>Status</th>
                                    <th className="hidden sm:table-cell">Sumber</th>
                                    <th className="hidden md:table-cell">SRID</th>
                                    <th className="hidden md:table-cell text-right">Keluasan dikira</th>
                                    <th className="hidden lg:table-cell text-right">Beza</th>
                                    <th className="hidden lg:table-cell">Dimuat naik</th>
                                    <th className="hidden xl:table-cell">Disahkan</th>
                                </>
                            }
                        >
                            {(spatial.data?.data ?? []).length === 0 ? (
                                <EmptyRow colSpan={8}>Tiada geometri dimuat naik.</EmptyRow>
                            ) : (
                                spatial.data?.data.map((s) => (
                                    <tr key={s.id}>
                                        <td className="whitespace-nowrap">
                                            v{s.versi}{' '}
                                            {s.semasa && (
                                                <Badge variant="info" shape="tag">
                                                    Semasa
                                                </Badge>
                                            )}
                                        </td>
                                        <td>
                                            <SpatialBadge status={s.status_pengesahan} label={s.label_status} />
                                            {s.catatan_pertindihan && <p className="mt-1 text-body-xs text-txt-danger max-w-xs">{s.catatan_pertindihan}</p>}
                                        </td>
                                        <td className="hidden sm:table-cell">
                                            {namaRuj(ruj?.sumber_spatial, s.sumber_data)}
                                            {s.rujukan_fail_sumber && <p className="text-body-xs text-txt-black-500 break-all">{s.rujukan_fail_sumber}</p>}
                                        </td>
                                        <td className="hidden md:table-cell whitespace-nowrap">EPSG:{s.srid_asal}</td>
                                        <td className="hidden md:table-cell text-right whitespace-nowrap">{s.keluasan_dikira !== null ? `${fmtNombor(s.keluasan_dikira)} m²` : '—'}</td>
                                        <td className="hidden lg:table-cell text-right whitespace-nowrap">{s.peratus_beza_keluasan !== null ? `${fmtNombor(s.peratus_beza_keluasan)}%` : '—'}</td>
                                        <td className="hidden lg:table-cell whitespace-nowrap">{fmtTarikh(s.dimuat_naik_pada, true)}</td>
                                        <td className="hidden xl:table-cell whitespace-nowrap">
                                            {fmtTarikh(s.tarikh_pengesahan, true)}
                                            {s.catatan_pengesahan && <p className="text-body-xs text-txt-black-500 whitespace-normal max-w-xs">{s.catatan_pengesahan}</p>}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </Table>
                    </div>
                )}
            </Card>
        </div>
    );
}

function PengesahanForm({ spatialId, status, onDone }: { spatialId: number; status: string; onDone: () => void }) {
    const { data: ruj } = useRujukan();
    const [value, setValue] = useState(status);
    const [catatan, setCatatan] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function submit(e: FormEvent) {
        e.preventDefault();
        setBusy(true);
        setError(null);
        try {
            const res = await proApi.sahkanSpatial(spatialId, value, catatan.trim());
            toast.success(res.message);
            setCatatan('');
            onDone();
        } catch (err) {
            setError(errorMessage(err));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Card title="Pengesahan geometri semasa">
            <form onSubmit={submit} className="grid grid-cols-1 sm:grid-cols-3 gap-4 items-start">
                <SelectField
                    id="sah-status"
                    label="Status pengesahan"
                    value={value}
                    onChange={setValue}
                    options={(ruj?.status_pengesahan ?? []).filter((s) => s.kod !== 'tiada_geometri')}
                    placeholder={null}
                />
                <div className="sm:col-span-2">
                    <InputLabel htmlFor="sah-catatan" className="mb-1.5">
                        Catatan pengesahan
                    </InputLabel>
                    <input id="sah-catatan" className={inputClass} value={catatan} maxLength={1000} onChange={(e) => setCatatan(e.target.value)} />
                </div>
                {error && (
                    <div className="sm:col-span-3">
                        <Alert type="error">{error}</Alert>
                    </div>
                )}
                <div className="sm:col-span-3 flex justify-end">
                    <Button type="submit" disabled={busy}>
                        {busy ? 'Menyimpan…' : 'Rekod pengesahan'}
                    </Button>
                </div>
            </form>
        </Card>
    );
}

const FORMAT_GEOMETRI = [
    { kod: 'geojson', nama: 'GeoJSON' },
    { kod: 'wkt', nama: 'WKT' },
    { kod: 'kml', nama: 'KML' },
    { kod: 'shp', nama: 'Shapefile (.zip)' },
];

function GeometriForm({ lotId, onDone }: { lotId: string; onDone: () => void }) {
    const { data: ruj } = useRujukan();
    const [format, setFormat] = useState('geojson');
    const [mod, setMod] = useState<'teks' | 'fail'>('teks');
    const [geometri, setGeometri] = useState('');
    const [fail, setFail] = useState<File | null>(null);
    const [srid, setSrid] = useState('4326');
    const [sumber, setSumber] = useState('');
    const [rujukan, setRujukan] = useState('');
    const [tarikh, setTarikh] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [error, setError] = useState<string | null>(null);
    const [amaran, setAmaran] = useState<string[]>([]);
    const [busy, setBusy] = useState(false);
    const [fileKey, setFileKey] = useState(0);
    const useFile = mod === 'fail' || format === 'shp';
    const today = new Date().toISOString().slice(0, 10);

    async function submit(e: FormEvent) {
        e.preventDefault();
        setErrors({});
        setError(null);
        setAmaran([]);
        const local: Record<string, string> = {};
        if (!sumber) local.sumber_data = 'Sila pilih sumber data spatial.';
        if (useFile && !fail) local.fail = 'Sila pilih fail geometri.';
        if (!useFile && !geometri.trim()) local.geometri = 'Sila tampal geometri.';
        if (Object.keys(local).length) {
            setErrors(local);
            return;
        }
        const form = new FormData();
        form.append('format', format);
        if (useFile && fail) form.append('fail', fail);
        else form.append('geometri', geometri.trim());
        if (srid) form.append('srid', srid);
        form.append('sumber_data', sumber);
        if (rujukan.trim()) form.append('rujukan_fail_sumber', rujukan.trim());
        if (tarikh) form.append('tarikh_perolehan_data', tarikh);

        setBusy(true);
        try {
            const res = await proApi.muatNaikSpatial(lotId, form);
            toast.success(res.message);
            setAmaran(res.amaran ?? []);
            setGeometri('');
            setFail(null);
            setFileKey((k) => k + 1);
            onDone();
        } catch (err) {
            setErrors(fieldErrors(err));
            setError(errorMessage(err, 'Geometri tidak dapat disimpan.'));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Card title="Muat naik / kemas kini geometri">
            {amaran.length > 0 && (
                <Callout type="warning" title="Geometri disimpan dengan amaran">
                    <ul className="list-disc ps-5 space-y-1">
                        {amaran.map((a) => (
                            <li key={a}>{a}</li>
                        ))}
                    </ul>
                </Callout>
            )}
            <form onSubmit={submit} className="space-y-4" noValidate>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <SelectField id="geo-format" label="Format" value={format} onChange={setFormat} options={FORMAT_GEOMETRI} placeholder={null} error={errors.format} />
                    <SelectField
                        id="geo-srid"
                        label="Sistem unjuran (SRID)"
                        value={srid}
                        onChange={setSrid}
                        options={(ruj?.srid ?? [{ kod: 4326, nama: 'WGS84 (EPSG:4326)' }]).map((s) => ({ kod: String(s.kod), nama: s.nama }))}
                        placeholder={null}
                        error={errors.srid}
                    />
                    <SelectField
                        id="geo-sumber"
                        label="Sumber data spatial"
                        value={sumber}
                        onChange={setSumber}
                        options={ruj?.sumber_spatial ?? []}
                        placeholder="Pilih sumber"
                        required
                        error={errors.sumber_data}
                    />
                    <div>
                        <InputLabel htmlFor="geo-rujukan" className="mb-1.5">
                            Rujukan fail sumber
                        </InputLabel>
                        <input id="geo-rujukan" className={inputClass} value={rujukan} maxLength={255} onChange={(e) => setRujukan(e.target.value)} placeholder="cth. PA 12345" />
                        <FieldError message={errors.rujukan_fail_sumber} />
                    </div>
                    <div>
                        <InputLabel htmlFor="geo-tarikh" className="mb-1.5">
                            Tarikh perolehan data
                        </InputLabel>
                        <input id="geo-tarikh" type="date" max={today} className={inputClass} value={tarikh} onChange={(e) => setTarikh(e.target.value)} />
                        <FieldError message={errors.tarikh_perolehan_data} />
                    </div>
                </div>

                {format !== 'shp' && (
                    <fieldset className="flex flex-wrap gap-4 text-body-sm">
                        <legend className="sr-only">Cara input geometri</legend>
                        {(['teks', 'fail'] as const).map((m) => (
                            <label key={m} className="inline-flex items-center gap-2">
                                <input type="radio" name="geo-mod" checked={mod === m} onChange={() => setMod(m)} className="text-primary-600 focus:ring-fr-primary" />
                                {m === 'teks' ? 'Tampal teks' : 'Muat naik fail'}
                            </label>
                        ))}
                    </fieldset>
                )}

                {useFile ? (
                    <div>
                        <InputLabel htmlFor="geo-fail" className="mb-1.5">
                            Fail geometri <span className="text-danger-600">*</span>
                        </InputLabel>
                        <input
                            key={fileKey}
                            id="geo-fail"
                            type="file"
                            accept={format === 'shp' ? '.zip' : format === 'kml' ? '.kml,.kmz' : format === 'wkt' ? '.wkt,.txt' : '.geojson,.json'}
                            onChange={(e) => setFail(e.target.files?.[0] ?? null)}
                            className="block w-full text-body-sm text-txt-black-700 file:me-3 file:rounded-md file:border file:border-otl-gray-300 file:bg-bg-white file:px-3 file:py-1.5 file:text-body-sm file:font-medium hover:file:bg-bg-washed"
                        />
                        <p className="mt-1 text-body-xs text-txt-black-500">
                            {format === 'shp' ? 'Shapefile mesti dimampatkan dalam satu fail .zip (.shp, .shx, .dbf, .prj).' : 'Maksimum 50 MB.'} Fail sumber disimpan dalam Dokumen Digital.
                        </p>
                        <FieldError message={errors.fail} />
                    </div>
                ) : (
                    <div>
                        <InputLabel htmlFor="geo-teks" className="mb-1.5">
                            Geometri ({format === 'wkt' ? 'WKT' : format === 'kml' ? 'KML' : 'GeoJSON'}) <span className="text-danger-600">*</span>
                        </InputLabel>
                        <textarea
                            id="geo-teks"
                            rows={6}
                            className={`${inputClass} font-mono text-body-xs ${errors.geometri ? 'border-danger-500' : ''}`}
                            value={geometri}
                            onChange={(e) => setGeometri(e.target.value)}
                            placeholder={
                                format === 'wkt'
                                    ? 'POLYGON((101.50 3.10, 101.51 3.10, 101.51 3.11, 101.50 3.11, 101.50 3.10))'
                                    : '{"type":"Polygon","coordinates":[[[101.50,3.10],[101.51,3.10],[101.51,3.11],[101.50,3.11],[101.50,3.10]]]}'
                            }
                        />
                        <FieldError message={errors.geometri} />
                    </div>
                )}

                {error && <Alert type="error">{error}</Alert>}
                <div className="flex justify-end">
                    <Button type="submit" disabled={busy}>
                        {busy ? 'Memuat naik…' : 'Simpan geometri'}
                    </Button>
                </div>
            </form>
        </Card>
    );
}

// ---------------------------------------------------------------- Versi

export function VersiPanel({ lotId }: { lotId: string }) {
    const versi = useQuery({ queryKey: ['pro', 'lot', lotId, 'versi'], queryFn: () => proApi.versi(lotId).then((r) => r.data) });
    const [selected, setSelected] = useState<string | null>(null);
    const banding = useQuery({
        queryKey: ['pro', 'lot', lotId, 'versi', selected],
        queryFn: () => proApi.bandingVersi(lotId, selected as string).then((r) => r.data),
        enabled: !!selected,
    });

    return (
        <Card title="Sejarah versi rekod">
            {versi.isLoading ? (
                <Loading />
            ) : versi.error ? (
                <Alert type="error">{errorMessage(versi.error)}</Alert>
            ) : (
                <div className="overflow-x-auto">
                    <Table
                        head={
                            <>
                                <th>Versi</th>
                                <th>Medan diubah</th>
                                <th>Status</th>
                                <th className="hidden md:table-cell">Oleh</th>
                                <th className="hidden md:table-cell">Tarikh</th>
                                <th>
                                    <span className="sr-only">Tindakan</span>
                                </th>
                            </>
                        }
                    >
                        {(versi.data ?? []).length === 0 ? (
                            <EmptyRow colSpan={6}>Tiada sejarah versi.</EmptyRow>
                        ) : (
                            versi.data?.map((v) => (
                                <tr key={v.id}>
                                    <td className="whitespace-nowrap">
                                        {v.no_versi ? `v${v.no_versi}` : '—'}{' '}
                                        {v.kritikal && (
                                            <Badge variant="warning" shape="tag">
                                                Kritikal
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="min-w-[10rem]">{v.medan.map((m) => MEDAN_LABEL[m] ?? m).join(', ') || '—'}</td>
                                    <td>
                                        <Badge variant={v.status_pengesahan === 'menunggu' ? 'warning' : v.status_pengesahan === 'tolak' ? 'danger' : 'success'}>
                                            {STATUS_VERSI_LABEL[v.status_pengesahan] ?? v.status_pengesahan}
                                        </Badge>
                                    </td>
                                    <td className="hidden md:table-cell">
                                        {v.pengguna ?? '—'}
                                        {v.pengesah_kedua && <p className="text-body-xs text-txt-black-500">Disahkan: {v.pengesah_kedua}</p>}
                                    </td>
                                    <td className="hidden md:table-cell whitespace-nowrap">{fmtTarikh(v.tarikh, true)}</td>
                                    <td>
                                        <Button size="sm" variant="secondary" onClick={() => setSelected(v.id)}>
                                            Banding
                                        </Button>
                                    </td>
                                </tr>
                            ))
                        )}
                    </Table>
                </div>
            )}

            <Modal open={!!selected} onClose={() => setSelected(null)}>
                <div className="p-6 space-y-4">
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">
                        Perbandingan versi {banding.data?.no_versi ? `v${banding.data.no_versi}` : ''}
                    </h2>
                    {(() => {
                        const v = versi.data?.find((x) => x.id === selected);
                        return v ? (
                            <div className="text-body-sm text-txt-black-700 space-y-1">
                                <p>
                                    {STATUS_VERSI_LABEL[v.status_pengesahan] ?? v.status_pengesahan} · {v.pengguna ?? '—'} · {fmtTarikh(v.tarikh, true)}
                                </p>
                                {v.justifikasi && <p>Justifikasi: “{v.justifikasi}”</p>}
                                {v.catatan_pengesahan && <p>Catatan pengesahan: “{v.catatan_pengesahan}”</p>}
                            </div>
                        ) : null;
                    })()}
                    {banding.isLoading ? (
                        <Loading />
                    ) : banding.error ? (
                        <Alert type="error">{errorMessage(banding.error)}</Alert>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-body-sm">
                                <thead>
                                    <tr className="text-left text-txt-black-500 border-b border-otl-gray-200">
                                        <th className="py-2 pe-3 font-medium">Medan</th>
                                        <th className="py-2 pe-3 font-medium">Sebelum</th>
                                        <th className="py-2 font-medium">Selepas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(banding.data?.perbandingan ?? []).map((p) => (
                                        <tr key={p.medan} className="border-b border-otl-gray-200 align-top">
                                            <td className="py-2 pe-3 font-medium">{p.label || MEDAN_LABEL[p.medan] || p.medan}</td>
                                            <td className="py-2 pe-3 break-words">
                                                {p.disamarkan ? <Terhad /> : <span className="rounded bg-bg-danger-50 px-1 text-txt-danger">{fmtNilai(p.sebelum)}</span>}
                                            </td>
                                            <td className="py-2 break-words">
                                                {p.disamarkan ? <Terhad /> : <span className="rounded bg-bg-success-50 px-1 text-txt-success">{fmtNilai(p.selepas)}</span>}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <div className="flex justify-end">
                        <Button variant="secondary" onClick={() => setSelected(null)}>
                            Tutup
                        </Button>
                    </div>
                </div>
            </Modal>
        </Card>
    );
}
