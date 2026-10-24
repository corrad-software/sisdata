'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Panel } from '@/components/ui/Panel';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Alert } from '@/components/ui/Alert';
import { Table } from '@/components/ui/Table';
import { EntriList } from '@/components/gis/Legenda';
import { gisApi, LABEL_KLASIFIKASI, useBoleh, useKatalog, type Lapisan } from '@/lib/gis';
import { api } from '@/lib/api';

const inputCls = 'block w-full rounded-md border border-otl-gray-300 bg-bg-white px-2 py-1.5 text-body-sm';

/**
 * Layer catalogue + metadata (SRS-GIS-11/12/29/32), layer administration
 * (classification, publication, order, opacity) and the OGC service details
 * for external GIS tools (SRS-GIS-25).
 */
export default function Page() {
    const can = useBoleh();
    const { data: katalog } = useKatalog();
    const metadata = useQuery({ queryKey: ['gis', 'metadata'], queryFn: () => api.get<{ data: Array<Record<string, string | null>> }>('/gis/metadata') });
    const ogc = useQuery({ queryKey: ['gis', 'ogc'], queryFn: gisApi.ogc, enabled: can('gis.ogc.lihat'), retry: false });

    return (
        <PageContainer>
            <Panel title="Sepuluh lapisan wajib (SRS-GIS-11)">
                <Table
                    head={
                        <>
                            <th>Bil.</th>
                            <th>Lapisan</th>
                            <th>Modul sumber</th>
                            <th>Status</th>
                            <th>Legenda</th>
                        </>
                    }
                >
                    {katalog?.wajib.map((s) => (
                        <tr key={s.kod}>
                            <td>{s.bil}</td>
                            <td>{s.nama}</td>
                            <td>{s.modul}</td>
                            <td>
                                <Badge variant={s.status === 'menunggu_modul' ? 'warning' : s.status === 'interim' ? 'info' : 'success'}>
                                    {s.status === 'menunggu_modul' ? 'Menunggu modul sumber' : s.status === 'interim' ? 'Interim (MOD-GIS)' : 'Tersedia'}
                                </Badge>
                            </td>
                            <td>
                                <EntriList entri={s.legenda} muted={s.status === 'menunggu_modul'} />
                            </td>
                        </tr>
                    ))}
                </Table>
            </Panel>

            <Panel title="Metadata lapisan & set data (SRS-GIS-29)">
                <Table
                    head={
                        <>
                            <th>Lapisan / set data</th>
                            <th>Sumber</th>
                            <th>Pemilik</th>
                            <th>Kemas kini</th>
                            <th>Pengesahan</th>
                            <th>Unjuran</th>
                            <th>Ketepatan</th>
                            <th>Had penggunaan</th>
                        </>
                    }
                >
                    {metadata.data?.data.map((m) => (
                        <tr key={String(m.kod)}>
                            <td>{m.nama ?? m.kod}</td>
                            <td>{m.sumber_data ?? '—'}</td>
                            <td>{m.pemilik_data ?? '—'}</td>
                            <td>{m.tarikh_kemas_kini ?? '—'}</td>
                            <td>{m.status_pengesahan}</td>
                            <td>{m.sistem_unjuran}</td>
                            <td>{m.tahap_ketepatan ?? '—'}</td>
                            <td>{m.had_penggunaan ?? '—'}</td>
                        </tr>
                    ))}
                    {metadata.data?.data.length === 0 && (
                        <tr>
                            <td colSpan={8} className="text-txt-black-500">
                                Belum ada metadata direkod.
                            </td>
                        </tr>
                    )}
                </Table>
            </Panel>

            {can('gis.lapisan.kemas_kini') && <PentadbiranLapisan layers={katalog?.lapisan ?? []} />}

            {can('gis.ogc.lihat') && (
                <Panel title="Perkhidmatan peta OGC (WMS / WFS)">
                    {ogc.data ? (
                        <div className="space-y-2 text-body-sm">
                            <p>
                                WMS: <code className="break-all">{ogc.data.wms}</code>
                            </p>
                            <p>
                                WFS: <code className="break-all">{ogc.data.wfs}</code>
                            </p>
                            <p>Lapisan diterbitkan: {ogc.data.lapisan.join(', ') || '—'}</p>
                            <p className="text-body-xs text-txt-black-500">{ogc.data.pengesahan}</p>
                        </div>
                    ) : (
                        <p className="text-body-sm text-txt-black-500">Memuatkan…</p>
                    )}
                </Panel>
            )}
        </PageContainer>
    );
}

function PentadbiranLapisan({ layers }: { layers: Lapisan[] }) {
    const qc = useQueryClient();
    const [msg, setMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const [meta, setMeta] = useState<string | null>(null);
    const save = useMutation({
        mutationFn: ({ kod, body }: { kod: string; body: Record<string, unknown> }) => gisApi.kemasKiniLapisan(kod, body),
        onSuccess: () => {
            setMsg({ type: 'success', text: 'Konfigurasi lapisan dikemas kini.' });
            qc.invalidateQueries({ queryKey: ['gis'] });
        },
        onError: (e: Error) => setMsg({ type: 'error', text: e.message }),
    });

    return (
        <Panel title="Pentadbiran lapisan (SRS-GIS-12 / 32)">
            {msg && <Alert type={msg.type} className="mb-3">{msg.text}</Alert>}
            <div className="overflow-x-auto">
                <table className="min-w-full text-body-sm">
                    <thead className="bg-bg-washed">
                        <tr className="[&>th]:px-2 [&>th]:py-2 [&>th]:text-left">
                            <th>Lapisan</th>
                            <th>Klasifikasi</th>
                            <th>Status</th>
                            <th>Urutan</th>
                            <th>Ketelusan</th>
                            <th>Lalai</th>
                            <th>OGC</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-otl-divider">
                        {layers.map((l) => (
                            <BarisLapisan key={l.kod} l={l} onSave={(body) => save.mutate({ kod: l.kod, body })} onMeta={() => setMeta(meta === l.kod ? null : l.kod)} />
                        ))}
                    </tbody>
                </table>
            </div>
            {meta && <BorangMetadata kod={meta} layer={layers.find((l) => l.kod === meta)} onDone={() => qc.invalidateQueries({ queryKey: ['gis'] })} />}
        </Panel>
    );
}

function BarisLapisan({ l, onSave, onMeta }: { l: Lapisan; onSave: (b: Record<string, unknown>) => void; onMeta: () => void }) {
    const [klas, setKlas] = useState(l.klasifikasi);
    const [status, setStatus] = useState(l.status_terbit);
    const [urutan, setUrutan] = useState(String(l.urutan));
    const [ketelusan, setKetelusan] = useState(String(l.ketelusan));
    const [lalai, setLalai] = useState(l.papar_lalai);
    const [ogc, setOgc] = useState(l.terbit_ogc);

    return (
        <tr className="[&>td]:px-2 [&>td]:py-2 align-top">
            <td>
                {l.nama}
                <span className="block text-body-xs text-txt-black-500">{l.kod}</span>
            </td>
            <td>
                <select className={inputCls} value={klas} onChange={(e) => setKlas(e.target.value as Lapisan['klasifikasi'])} aria-label={`Klasifikasi ${l.nama}`}>
                    {Object.entries(LABEL_KLASIFIKASI).map(([k, v]) => (
                        <option key={k} value={k}>
                            {v}
                        </option>
                    ))}
                </select>
            </td>
            <td>
                <select className={inputCls} value={status} onChange={(e) => setStatus(e.target.value)} aria-label={`Status ${l.nama}`}>
                    <option value="terbit">Terbit</option>
                    <option value="draf">Draf</option>
                    <option value="tidak_aktif">Tidak aktif</option>
                </select>
            </td>
            <td>
                <input className={`${inputCls} w-20`} type="number" value={urutan} onChange={(e) => setUrutan(e.target.value)} aria-label={`Urutan ${l.nama}`} />
            </td>
            <td>
                <input className={`${inputCls} w-20`} type="number" step={0.05} min={0} max={1} value={ketelusan} onChange={(e) => setKetelusan(e.target.value)} aria-label={`Ketelusan ${l.nama}`} />
            </td>
            <td>
                <input type="checkbox" checked={lalai} onChange={(e) => setLalai(e.target.checked)} aria-label={`Papar lalai ${l.nama}`} />
            </td>
            <td>
                <input type="checkbox" checked={ogc} onChange={(e) => setOgc(e.target.checked)} aria-label={`Terbit OGC ${l.nama}`} />
            </td>
            <td className="space-x-2 whitespace-nowrap">
                <Button size="sm" onClick={() => onSave({ klasifikasi_keselamatan: klas, status_terbit: status, urutan_paparan: Number(urutan), ketelusan_lalai: Number(ketelusan), papar_lalai: lalai, terbit_ogc: ogc })}>
                    Simpan
                </Button>
                <Button size="sm" variant="secondary" onClick={onMeta}>
                    Metadata
                </Button>
            </td>
        </tr>
    );
}

function BorangMetadata({ kod, layer, onDone }: { kod: string; layer?: Lapisan; onDone: () => void }) {
    const m = layer?.metadata;
    const [form, setForm] = useState<Record<string, string>>({
        sumber_data: m?.sumber_data ?? '',
        pemilik_data: m?.pemilik_data ?? '',
        tarikh_kemas_kini: m?.tarikh_kemas_kini ?? '',
        status_pengesahan: m?.status_pengesahan ?? 'belum_disahkan',
        sistem_unjuran: m?.sistem_unjuran ?? 'EPSG:4326 (WGS 84)',
        tahap_ketepatan: m?.tahap_ketepatan ?? '',
        had_penggunaan: m?.had_penggunaan ?? '',
    });
    const [msg, setMsg] = useState<string | null>(null);
    const field = (k: string, label: string, type = 'text') => (
        <label className="block text-body-sm">
            <span className="mb-1 block font-medium">{label}</span>
            <input className={inputCls} type={type} value={form[k]} onChange={(e) => setForm({ ...form, [k]: e.target.value })} />
        </label>
    );

    return (
        <form
            className="mt-4 grid grid-cols-1 gap-3 rounded-md border border-otl-gray-200 p-4 md:grid-cols-3"
            onSubmit={(e) => {
                e.preventDefault();
                gisApi
                    .kemasKiniMetadata(kod, Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === '' ? null : v])))
                    .then(() => {
                        setMsg('Metadata disimpan.');
                        onDone();
                    })
                    .catch((err: Error) => setMsg(err.message));
            }}
        >
            <p className="md:col-span-3 text-body-sm font-semibold">Metadata: {layer?.nama ?? kod}</p>
            {field('sumber_data', 'Sumber data')}
            {field('pemilik_data', 'Pemilik data')}
            {field('tarikh_kemas_kini', 'Tarikh kemas kini', 'date')}
            <label className="block text-body-sm">
                <span className="mb-1 block font-medium">Status pengesahan</span>
                <select className={inputCls} value={form.status_pengesahan} onChange={(e) => setForm({ ...form, status_pengesahan: e.target.value })}>
                    <option value="belum_disahkan">Belum disahkan</option>
                    <option value="disahkan">Disahkan</option>
                    <option value="ditolak">Ditolak</option>
                </select>
            </label>
            {field('sistem_unjuran', 'Sistem unjuran')}
            {field('tahap_ketepatan', 'Tahap ketepatan')}
            <div className="md:col-span-3">{field('had_penggunaan', 'Had penggunaan')}</div>
            <div className="md:col-span-3 flex items-center gap-3">
                <Button type="submit" size="sm">
                    Simpan metadata
                </Button>
                {msg && <span className="text-body-sm text-txt-black-700">{msg}</span>}
            </div>
        </form>
    );
}
