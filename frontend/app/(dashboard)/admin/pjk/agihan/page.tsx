'use client';

import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Table } from '@/components/ui/Table';
import { pjkApi, ralat, rm, tarikh, useCan, useRujukan, type Kiraan } from '@/lib/pjk';
import { Card, Field, inputClass, Kosong, ModalBody, Select, Status } from '../_components/common';

/** SRS-PJK-22..25: versioned Dasar Agihan Manfaat rates, monthly calculation, simulation and confirmation. */
export default function AgihanPage() {
    const can = useCan();
    const qc = useQueryClient();
    const { data: ref } = useRujukan();
    const bulanLepas = new Date(new Date().getFullYear(), new Date().getMonth() - 1, 15).toISOString().slice(0, 7);
    const [tempoh, setTempoh] = useState(bulanLepas);
    const [hasil, setHasil] = useState<Kiraan[] | null>(null);
    const [sim, setSim] = useState<Record<string, string>>({});
    const [open, setOpen] = useState(false);
    const [n, setN] = useState<Record<string, string>>({ kategori_hartanah: 'premis', status_perolehan: 'wakaf' });
    const [penerima, setPenerima] = useState([{ kod: '', nama: '', peratus: '' }]);
    const dasar = useQuery({ queryKey: ['pjk', 'dasar'], queryFn: pjkApi.dasar });
    const kiraan = useQuery({ queryKey: ['pjk', 'kiraan', tempoh], queryFn: () => pjkApi.kiraan({ tempoh }) });

    async function kira(simulasi: boolean) {
        try {
            setHasil(simulasi ? await pjkApi.simulasi(tempoh, sim) : await pjkApi.kira(tempoh));
            if (!simulasi) qc.invalidateQueries({ queryKey: ['pjk', 'kiraan'] });
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    async function sahkan() {
        const ids = (kiraan.data ?? []).filter((k) => k.status_pengesahan === 'draf' && k.id).map((k) => k.id as number);
        if (ids.length === 0) return;
        try {
            await pjkApi.sahkanKiraan(ids);
            toast.success('Kiraan disahkan.');
            qc.invalidateQueries({ queryKey: ['pjk', 'kiraan'] });
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    async function simpanDasar() {
        try {
            const r = await pjkApi.dasarCipta({ ...n, penerima_manfaat: penerima.filter((p) => p.nama) });
            await pjkApi.dasarHantar(r.data.id);
            toast.success('Kadar dasar dihantar untuk kelulusan.');
            setOpen(false);
            qc.invalidateQueries({ queryKey: ['pjk', 'dasar'] });
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    const combos = (ref?.kategori_hartanah ?? []).flatMap((k) => (ref?.status_perolehan ?? []).map((s) => ({ key: `${k.kod}:${s.kod}`, label: `${k.nama} · ${s.nama}` })));
    const rows = hasil ?? kiraan.data ?? [];

    return (
        <PageContainer>
            <Card title="Kadar Dasar Agihan Manfaat" actions={can('pjk.dasar.kemas_kini') ? <Button size="sm" onClick={() => setOpen(true)}>Kadar / versi baharu</Button> : undefined}>
                <p className="text-body-xs text-txt-black-500">Kiraan: amaun agihan = hasil kutipan sewa (tidak termasuk deposit) × kadar agihan; setiap penerima mendapat peratusnya daripada amaun agihan. Formula muktamad tertakluk kepada dokumen dasar MAIS.</p>
                <Table head={<><th>Kategori</th><th>Status perolehan</th><th>Versi</th><th>Kadar</th><th className="hidden md:table-cell">Penerima manfaat</th><th className="hidden sm:table-cell">Berkuat kuasa</th><th>Status</th></>}>
                    {(dasar.data ?? []).length === 0 ? (
                        <Kosong colSpan={7}>Tiada kadar direkod.</Kosong>
                    ) : (
                        dasar.data?.map((d) => (
                            <tr key={d.id}>
                                <td>{d.kategori_hartanah_nama}</td>
                                <td>{d.status_perolehan_nama}</td>
                                <td>v{d.versi_dasar}</td>
                                <td>{Number(d.kadar_agihan)}%</td>
                                <td className="hidden md:table-cell text-body-xs">{d.penerima_manfaat.map((p) => `${p.nama} ${Number(p.peratus)}%`).join(', ')}</td>
                                <td className="hidden sm:table-cell text-body-xs">
                                    {tarikh(d.tarikh_mula_kuasa)} – {d.tarikh_tamat ? tarikh(d.tarikh_tamat) : 'kini'}
                                    {d.rujukan_kelulusan && <p className="text-txt-black-500">{d.rujukan_kelulusan}</p>}
                                </td>
                                <td>
                                    <Status kod={d.status} nama={d.status.replace('_', ' ')} />
                                </td>
                            </tr>
                        ))
                    )}
                </Table>
            </Card>

            <Card title="Kiraan agihan mengikut tempat dan tempoh">
                <div className="flex flex-wrap items-end gap-3">
                    <Field label="Tempoh perakaunan" htmlFor="a-tempoh">
                        <input id="a-tempoh" type="month" className={inputClass} value={tempoh} onChange={(e) => { setTempoh(e.target.value); setHasil(null); }} />
                    </Field>
                    {can('pjk.agihan.cipta') && (
                        <Button size="sm" onClick={() => kira(false)}>
                            Kira & simpan (draf)
                        </Button>
                    )}
                    {can('pjk.agihan.lulus') && (
                        <Button size="sm" variant="secondary" onClick={sahkan}>
                            Sahkan semua draf
                        </Button>
                    )}
                </div>
                {can('pjk.agihan.cipta') && (
                    <details className="rounded-md border border-otl-gray-200 p-3">
                        <summary className="cursor-pointer text-body-sm font-medium">Simulasi kadar cadangan (tidak disimpan)</summary>
                        <div className="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2">
                            {combos.map((c) => (
                                <Field key={c.key} label={c.label} htmlFor={`sim-${c.key}`}>
                                    <input id={`sim-${c.key}`} inputMode="decimal" className={inputClass} placeholder="%" value={sim[c.key] ?? ''} onChange={(e) => setSim((p) => ({ ...p, [c.key]: e.target.value }))} />
                                </Field>
                            ))}
                        </div>
                        <Button size="sm" className="mt-3" onClick={() => kira(true)}>
                            Jalankan simulasi
                        </Button>
                    </details>
                )}
                {hasil && <p className="text-body-sm text-txt-warning">{hasil.some((h) => h.simulasi) ? 'Keputusan simulasi — tidak disimpan.' : 'Keputusan kiraan terkini.'}</p>}
                <Table head={<><th>Premis</th><th className="hidden sm:table-cell">Kategori / status</th><th>Hasil</th><th className="hidden sm:table-cell">Kadar</th><th>Agihan</th><th className="hidden lg:table-cell">Pecahan</th><th>Status</th></>}>
                    {rows.length === 0 ? (
                        <Kosong colSpan={7}>Tiada kiraan untuk tempoh ini.</Kosong>
                    ) : (
                        rows.map((r) => (
                            <tr key={`${r.premis_id}-${r.tempoh_perakaunan}`}>
                                <td>{r.kod_premis}</td>
                                <td className="hidden sm:table-cell text-body-xs">
                                    {r.kategori_hartanah ?? '—'} · {r.status_perolehan ?? '—'} {r.versi_dasar ? `(v${r.versi_dasar})` : ''}
                                </td>
                                <td className="whitespace-nowrap">{rm(r.hasil_kutipan)}</td>
                                <td className="hidden sm:table-cell">{r.kadar_agihan ? `${Number(r.kadar_agihan)}%` : '—'}</td>
                                <td className="whitespace-nowrap">{r.ralat ? <span className="text-body-xs text-txt-danger">{r.ralat}</span> : rm(r.amaun_agihan)}</td>
                                <td className="hidden lg:table-cell text-body-xs">{(r.pecahan_penerima ?? []).map((p) => `${p.nama}: ${rm(p.amaun)}`).join('; ')}</td>
                                <td>{r.simulasi ? <Status kod="menunggu" nama="simulasi" /> : r.status_pengesahan ? <Status kod={r.status_pengesahan} nama={r.status_pengesahan} /> : '—'}</td>
                            </tr>
                        ))
                    )}
                </Table>
            </Card>

            <Modal open={open} onClose={() => setOpen(false)}>
                <ModalBody
                    title="Kadar Dasar Agihan Manfaat (versi baharu)"
                    footer={
                        <>
                            <Button variant="secondary" onClick={() => setOpen(false)}>
                                Batal
                            </Button>
                            <Button onClick={simpanDasar}>Simpan & hantar kelulusan</Button>
                        </>
                    }
                >
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <Field label="Kategori hartanah" htmlFor="d-kat">
                            <Select id="d-kat" value={n.kategori_hartanah} onChange={(v) => setN((p) => ({ ...p, kategori_hartanah: v }))} options={ref?.kategori_hartanah ?? []} />
                        </Field>
                        <Field label="Status perolehan" htmlFor="d-status">
                            <Select id="d-status" value={n.status_perolehan} onChange={(v) => setN((p) => ({ ...p, status_perolehan: v }))} options={ref?.status_perolehan ?? []} />
                        </Field>
                        <Field label="Kadar agihan (%)" htmlFor="d-kadar">
                            <input id="d-kadar" inputMode="decimal" className={inputClass} value={n.kadar_agihan ?? ''} onChange={(e) => setN((p) => ({ ...p, kadar_agihan: e.target.value }))} />
                        </Field>
                        <Field label="Tarikh mula berkuat kuasa" htmlFor="d-mula">
                            <input id="d-mula" type="date" className={inputClass} value={n.tarikh_mula_kuasa ?? ''} onChange={(e) => setN((p) => ({ ...p, tarikh_mula_kuasa: e.target.value }))} />
                        </Field>
                        <Field label="Rujukan kelulusan MAIS" htmlFor="d-ruj" className="sm:col-span-2">
                            <input id="d-ruj" className={inputClass} value={n.rujukan_kelulusan ?? ''} onChange={(e) => setN((p) => ({ ...p, rujukan_kelulusan: e.target.value }))} />
                        </Field>
                    </div>
                    <fieldset className="space-y-2">
                        <legend className="text-body-sm font-medium text-txt-black-700">Penerima manfaat (jumlah 100%)</legend>
                        {penerima.map((p, i) => (
                            <div key={i} className="grid grid-cols-3 gap-2">
                                <input aria-label="Kod" className={inputClass} placeholder="Kod" value={p.kod} onChange={(e) => setPenerima((all) => all.map((x, j) => (j === i ? { ...x, kod: e.target.value } : x)))} />
                                <input aria-label="Nama" className={inputClass} placeholder="Nama" value={p.nama} onChange={(e) => setPenerima((all) => all.map((x, j) => (j === i ? { ...x, nama: e.target.value } : x)))} />
                                <input aria-label="Peratus" inputMode="decimal" className={inputClass} placeholder="%" value={p.peratus} onChange={(e) => setPenerima((all) => all.map((x, j) => (j === i ? { ...x, peratus: e.target.value } : x)))} />
                            </div>
                        ))}
                        <Button size="sm" variant="outline" onClick={() => setPenerima((all) => [...all, { kod: '', nama: '', peratus: '' }])}>
                            Tambah penerima
                        </Button>
                    </fieldset>
                </ModalBody>
            </Modal>
        </PageContainer>
    );
}
