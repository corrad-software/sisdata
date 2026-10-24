'use client';

import { useState } from 'react';
import dynamic from 'next/dynamic';
import { useMutation, useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Table } from '@/components/ui/Table';
import type { LotFeature } from '@/lib/pro';
import { luas, masa, ralat, rm, tarikh, tsuApi, useCan, useRujukan, type HasilUkur, type SebutHarga, type UrusanButiran } from '@/lib/tsu';
import { Card, Field, inputClass, Kosong, ModalBody, Ralat, Select, Status } from './common';

const LotMap = dynamic(() => import('@/components/pro/LotMap').then((m) => m.LotMap), { ssr: false });

/** SRS-TSU-08..13, 16..18: quotation → comparison → approval → appointment → progress → survey result import and verification. */
export function TabUkur({ u, onChange }: { u: UrusanButiran; onChange: () => void }) {
    const can = useCan();
    const { data, error, refetch } = useQuery({ queryKey: ['tsu', 'ukur', u.id], queryFn: () => tsuApi.ukur(u.id), enabled: can('tsu.ukur.lihat') });
    const done = () => {
        refetch();
        onChange();
    };
    const [jemput, setJemput] = useState(false);

    if (!can('tsu.ukur.lihat')) return <Kosong>Tiada kebenaran melihat kerja ukur.</Kosong>;
    if (error) return <Ralat>{ralat(error)}</Ralat>;
    if (!data) return <Kosong>Memuatkan…</Kosong>;
    const terbuka = !u.dikunci && ['aktif', 'menunggu_kelulusan'].includes(u.status);

    return (
        <div className="space-y-6 pt-4">
            <Card title="Sebut harga kerja ukur" actions={terbuka && can('tsu.sebutharga.cipta') && <Button size="sm" onClick={() => setJemput(true)}>Jemput sebut harga</Button>}>
                {data.sebutharga.length === 0 ? <Kosong>Tiada sebut harga.</Kosong> : data.sebutharga.map((s) => <SebutHargaKad key={s.id} s={s} terbuka={terbuka} onChange={done} />)}
            </Card>
            <Card title="Lantikan jurukur dan kemajuan">
                {data.lantikan.length === 0 ? (
                    <Kosong>Tiada lantikan.</Kosong>
                ) : (
                    data.lantikan.map((l) => (
                        <div key={l.id} className="rounded-md border border-otl-gray-200 p-4 space-y-3">
                            <div className="flex flex-wrap items-center gap-2 text-body-sm">
                                <strong>{l.jurukur.nama_firma}</strong> <span className="text-txt-black-500">Lesen {l.jurukur.no_lesen_jurukur}</span>
                                <Status kod={l.status} nama={l.status === 'siap' ? 'Siap' : l.status === 'aktif' ? 'Aktif' : l.status} />
                                {l.lewat && <Badge variant="danger">Terkebelakang</Badge>}
                            </div>
                            <p className="text-body-xs text-txt-black-500">
                                Surat setuju terima {l.no_surat_setuju_terima} · Nilai kontrak {rm(l.nilai_kontrak)} · Mula {tarikh(l.tarikh_mula)} · {l.tempoh_siap_hari} hari · Jangka siap {tarikh(l.tarikh_jangka_siap)} {l.tarikh_siap && `· Siap ${tarikh(l.tarikh_siap)}`}
                            </p>
                            <div aria-label={`Kemajuan ${l.peratus_siap_semasa}% berbanding ${l.peratus_dijangka}% dijangka`}>
                                <div className="h-3 w-full rounded-full bg-bg-washed overflow-hidden relative">
                                    <div className={`h-3 ${l.lewat ? 'bg-danger-600' : 'bg-primary-600'}`} style={{ width: `${l.peratus_siap_semasa}%` }} />
                                    <div className="absolute top-0 h-3 border-r-2 border-txt-black-900" style={{ left: `${l.peratus_dijangka}%` }} />
                                </div>
                                <p className="mt-1 text-body-xs">{l.peratus_siap_semasa}% siap · {l.peratus_dijangka}% dijangka</p>
                            </div>
                            {l.kemajuan.length > 0 && (
                                <ul className="text-body-xs space-y-1">
                                    {l.kemajuan.map((k) => (
                                        <li key={k.id}>{tarikh(k.tarikh_lapor)} — {k.milestone}: {k.peratus_siap}% (dijangka {k.peratus_dijangka}%) {k.lewat && <Badge variant="danger">Lewat</Badge>} {k.pelapor && `· ${k.pelapor}`}</li>
                                    ))}
                                </ul>
                            )}
                            {l.status === 'aktif' && can('tsu.ukur.kemas_kini') && <BorangKemajuan lantikan={l.id} onDone={done} />}
                        </div>
                    ))
                )}
            </Card>
            <HasilUkurKad u={u} hasil={data.hasil} lantikan={data.lantikan.map((l) => ({ id: l.id, nama: l.jurukur.nama_firma }))} terbuka={terbuka} onChange={done} />
            <JemputModal open={jemput} onClose={() => setJemput(false)} urusan={u.id} onDone={done} />
        </div>
    );
}

export function BorangKemajuan({ lantikan, onDone }: { lantikan: string; onDone: () => void }) {
    const [f, setF] = useState({ milestone: '', peratus_siap: '', catatan: '' });
    const m = useMutation({
        mutationFn: () => tsuApi.kemajuan(lantikan, { milestone: f.milestone, peratus_siap: Number(f.peratus_siap), catatan: f.catatan || undefined }),
        onSuccess: (r) => {
            toast.success(r.message);
            setF({ milestone: '', peratus_siap: '', catatan: '' });
            onDone();
        },
        onError: (e) => toast.error(ralat(e)),
    });

    return (
        <form
            className="grid grid-cols-1 sm:grid-cols-4 gap-3"
            onSubmit={(e) => {
                e.preventDefault();
                m.mutate();
            }}
        >
            <Field label="Milestone" htmlFor={`km-${lantikan}`}><input id={`km-${lantikan}`} className={inputClass} value={f.milestone} onChange={(e) => setF((x) => ({ ...x, milestone: e.target.value }))} required /></Field>
            <Field label="% siap" htmlFor={`kp-${lantikan}`}><input id={`kp-${lantikan}`} type="number" min={0} max={100} className={inputClass} value={f.peratus_siap} onChange={(e) => setF((x) => ({ ...x, peratus_siap: e.target.value }))} required /></Field>
            <Field label="Catatan" htmlFor={`kc-${lantikan}`}><input id={`kc-${lantikan}`} className={inputClass} value={f.catatan} onChange={(e) => setF((x) => ({ ...x, catatan: e.target.value }))} /></Field>
            <div className="flex items-end"><Button type="submit" size="sm" disabled={m.isPending}>Lapor kemajuan</Button></div>
        </form>
    );
}

function SebutHargaKad({ s, terbuka, onChange }: { s: SebutHarga; terbuka: boolean; onChange: () => void }) {
    const can = useCan();
    const [tawaran, setTawaran] = useState<{ id: number; harga: string; tempoh: string } | null>(null);
    const [banding, setBanding] = useState(false);
    const [nilai, setNilai] = useState<{ id: string; justifikasi: string }>({ id: '', justifikasi: '' });
    const [lantik, setLantik] = useState<{ sst: string; mula: string } | null>(null);
    const { data: jadual, error } = useQuery({ queryKey: ['tsu', 'perbandingan', s.id], queryFn: () => tsuApi.perbandingan(s.id), enabled: banding });
    const mTawaran = useMutation({ mutationFn: () => tsuApi.rekodTawaran(s.id, tawaran!.id, { harga_tawaran: tawaran!.harga, tempoh_siap_hari: Number(tawaran!.tempoh) }), onSuccess: (r) => { toast.success(r.message); setTawaran(null); onChange(); }, onError: (e) => toast.error(ralat(e)) });
    const mNilai = useMutation({ mutationFn: () => tsuApi.nilai(s.id, { id_tawaran_disyorkan: Number(nilai.id), justifikasi_pemilihan: nilai.justifikasi }), onSuccess: (r) => { toast.success(r.message); setBanding(false); onChange(); }, onError: (e) => toast.error(ralat(e)) });
    const mLantik = useMutation({ mutationFn: () => tsuApi.lantik(s.id, { no_surat_setuju_terima: lantik!.sst, tarikh_mula: lantik!.mula }), onSuccess: (r) => { toast.success(r.message); setLantik(null); onChange(); }, onError: (e) => toast.error(ralat(e)) });

    return (
        <div className="rounded-md border border-otl-gray-200 p-4 space-y-3">
            <div className="flex flex-wrap items-center gap-2 text-body-sm">
                <strong>{s.no_jemputan}</strong> <Status kod={s.status} nama={s.status_nama} />
                <span className="text-txt-black-500">Tutup {masa(s.tarikh_tutup)}</span>
                {!s.ditutup && <Badge variant="warning">Nilai tawaran terkawal sehingga tarikh tutup</Badge>}
                <a className="text-txt-primary hover:underline" href={tsuApi.jemputanPdf(s.id)} target="_blank" rel="noopener">Dokumen jemputan (PDF)</a>
            </div>
            <p className="text-body-sm">{s.skop_kerja}</p>
            <Table head={<><th>Jurukur</th><th>No. lesen</th><th>Status</th><th>Harga</th><th>Tempoh siap</th><th>Markah</th><th></th></>}>
                {s.tawaran.map((t) => (
                    <tr key={t.id}>
                        <td>{t.nama_firma} {t.indikator_disyorkan && <Badge variant="success">Disyorkan</Badge>}</td>
                        <td>{t.no_lesen_jurukur}</td>
                        <td>{t.status}</td>
                        <td>{s.ditutup ? rm(t.harga_tawaran) : 'Terkawal'}</td>
                        <td>{t.tempoh_siap_hari ? `${t.tempoh_siap_hari} hari` : '—'}</td>
                        <td>{t.markah ?? '—'}</td>
                        <td>{terbuka && !s.ditutup && s.status === 'dibuka' && can('tsu.sebutharga.cipta') && <Button size="sm" variant="secondary" onClick={() => setTawaran({ id: t.id, harga: '', tempoh: '' })}>Rekod tawaran</Button>}</td>
                    </tr>
                ))}
            </Table>
            {s.justifikasi_pemilihan && <p className="text-body-sm">Justifikasi pemilihan: {s.justifikasi_pemilihan} {s.penilai && `(penilai: ${s.penilai})`} {s.pelulus && `· pelulus: ${s.pelulus}`}</p>}
            <div className="flex flex-wrap gap-2">
                {s.ditutup && can('tsu.sebutharga.lihat') && <Button size="sm" variant="outline" onClick={() => setBanding(true)}>Jadual perbandingan</Button>}
                {terbuka && s.status === 'diluluskan' && can('tsu.sebutharga.cipta') && <Button size="sm" onClick={() => setLantik({ sst: '', mula: new Date().toISOString().slice(0, 10) })}>Rekod lantikan</Button>}
            </div>

            <Modal open={!!tawaran} onClose={() => setTawaran(null)}>
                {tawaran && (
                    <ModalBody title="Rekod tawaran diterima" footer={<><Button variant="secondary" onClick={() => setTawaran(null)}>Batal</Button><Button onClick={() => mTawaran.mutate()} disabled={mTawaran.isPending}>Simpan</Button></>}>
                        <Field label="Harga tawaran (RM)" htmlFor="tw-h"><input id="tw-h" inputMode="decimal" className={inputClass} value={tawaran.harga} onChange={(e) => setTawaran({ ...tawaran, harga: e.target.value })} /></Field>
                        <Field label="Tempoh siap (hari)" htmlFor="tw-t"><input id="tw-t" type="number" min={1} className={inputClass} value={tawaran.tempoh} onChange={(e) => setTawaran({ ...tawaran, tempoh: e.target.value })} /></Field>
                    </ModalBody>
                )}
            </Modal>
            <Modal open={banding} onClose={() => setBanding(false)}>
                <ModalBody title={`Jadual perbandingan ${s.no_jemputan}`} footer={<Button variant="secondary" onClick={() => setBanding(false)}>Tutup</Button>}>
                    {error && <Ralat>{ralat(error)}</Ralat>}
                    {!jadual ? (
                        <Kosong>Memuatkan…</Kosong>
                    ) : (
                        <>
                            <Table head={<><th>#</th><th>Jurukur</th><th>Harga</th><th>Tempoh</th><th>Pengalaman</th><th>Markah</th></>}>
                                {jadual.jadual.map((r) => (
                                    <tr key={r.id}>
                                        <td>{r.kedudukan ?? '—'}</td>
                                        <td>{r.nama_firma}</td>
                                        <td>{rm(r.harga_tawaran)}</td>
                                        <td>{r.tempoh_siap_hari ?? '—'}</td>
                                        <td>{r.pengalaman_tahun ?? '—'} thn</td>
                                        <td>{r.markah ?? '—'}</td>
                                    </tr>
                                ))}
                            </Table>
                            {terbuka && can('tsu.sebutharga.kemas_kini') && ['dibuka', 'ditutup', 'dinilai', 'ditolak'].includes(s.status) && (
                                <div className="space-y-3">
                                    <Field label="Tawaran disyorkan" htmlFor="nl-id">
                                        <Select id="nl-id" value={nilai.id} onChange={(v) => setNilai((x) => ({ ...x, id: v }))} options={jadual.jadual.filter((r) => r.harga_tawaran).map((r) => ({ kod: String(r.id), nama: `${r.nama_firma} — ${rm(r.harga_tawaran)}` }))} empty="Pilih" />
                                    </Field>
                                    <Field label="Justifikasi pemilihan" htmlFor="nl-j"><textarea id="nl-j" rows={3} className={inputClass} value={nilai.justifikasi} onChange={(e) => setNilai((x) => ({ ...x, justifikasi: e.target.value }))} /></Field>
                                    <Button onClick={() => mNilai.mutate()} disabled={mNilai.isPending || !nilai.id}>Hantar pengesyoran untuk kelulusan</Button>
                                </div>
                            )}
                        </>
                    )}
                </ModalBody>
            </Modal>
            <Modal open={!!lantik} onClose={() => setLantik(null)}>
                {lantik && (
                    <ModalBody title="Rekod lantikan jurukur" footer={<><Button variant="secondary" onClick={() => setLantik(null)}>Batal</Button><Button onClick={() => mLantik.mutate()} disabled={mLantik.isPending || !lantik.sst}>Simpan</Button></>}>
                        <Field label="No. surat setuju terima" htmlFor="lt-s"><input id="lt-s" className={inputClass} value={lantik.sst} onChange={(e) => setLantik({ ...lantik, sst: e.target.value })} /></Field>
                        <Field label="Tarikh mula" htmlFor="lt-m"><input id="lt-m" type="date" className={inputClass} value={lantik.mula} onChange={(e) => setLantik({ ...lantik, mula: e.target.value })} /></Field>
                    </ModalBody>
                )}
            </Modal>
        </div>
    );
}

function JemputModal({ open, onClose, urusan, onDone }: { open: boolean; onClose: () => void; urusan: string; onDone: () => void }) {
    const { data: panel } = useQuery({ queryKey: ['tsu', 'panel', 'layak'], queryFn: () => tsuApi.panel(true), enabled: open });
    const [f, setF] = useState({ tarikh_tutup: '', skop_kerja: '', jurukur_ids: [] as number[] });
    const m = useMutation({
        mutationFn: () => tsuApi.jemput(urusan, { ...f, tarikh_tutup: f.tarikh_tutup ? new Date(f.tarikh_tutup).toISOString() : '' }),
        onSuccess: (r) => {
            toast.success(r.message);
            onClose();
            onDone();
        },
        onError: (e) => toast.error(ralat(e)),
    });

    return (
        <Modal open={open} onClose={onClose}>
            <ModalBody title="Jemputan sebut harga kerja ukur" footer={<><Button variant="secondary" onClick={onClose}>Batal</Button><Button onClick={() => m.mutate()} disabled={m.isPending}>Jana jemputan</Button></>}>
                <Field label="Tarikh & masa tutup" htmlFor="jm-t"><input id="jm-t" type="datetime-local" className={inputClass} value={f.tarikh_tutup} onChange={(e) => setF((x) => ({ ...x, tarikh_tutup: e.target.value }))} /></Field>
                <Field label="Skop kerja" htmlFor="jm-s"><textarea id="jm-s" rows={3} className={inputClass} value={f.skop_kerja} onChange={(e) => setF((x) => ({ ...x, skop_kerja: e.target.value }))} /></Field>
                <fieldset>
                    <legend className="mb-1.5 text-body-sm font-medium text-txt-black-700">Jurukur panel dijemput</legend>
                    <div className="space-y-1 max-h-48 overflow-y-auto">
                        {(panel ?? []).map((p) => (
                            <label key={p.id} className="flex items-center gap-2 text-body-sm">
                                <input type="checkbox" checked={f.jurukur_ids.includes(p.id)} onChange={(e) => setF((x) => ({ ...x, jurukur_ids: e.target.checked ? [...x.jurukur_ids, p.id] : x.jurukur_ids.filter((i) => i !== p.id) }))} />
                                {p.nama_firma} <span className="text-txt-black-500">({p.no_lesen_jurukur})</span>
                            </label>
                        ))}
                        {panel?.length === 0 && <Kosong>Tiada jurukur panel yang layak.</Kosong>}
                    </div>
                </fieldset>
            </ModalBody>
        </Modal>
    );
}

function HasilUkurKad({ u, hasil, lantikan, terbuka, onChange }: { u: UrusanButiran; hasil: HasilUkur[]; lantikan: Array<{ id: string; nama: string }>; terbuka: boolean; onChange: () => void }) {
    const can = useCan();
    const { data: ref } = useRujukan();
    const [f, setF] = useState({ format_fail: 'geojson', sistem_koordinat: '4326', keluasan_dokumen: '', lot_id: '', no_lot_cadangan: '', lantikan_id: '' });
    const [fail, setFail] = useState<File | null>(null);
    const [banding, setBanding] = useState<string | null>(null);
    const [sah, setSah] = useState<{ id: string; status: string; catatan: string; pa: string } | null>(null);
    const { data: cmp } = useQuery({ queryKey: ['tsu', 'banding', banding], queryFn: () => tsuApi.banding(banding!), enabled: !!banding });
    const mImport = useMutation({
        mutationFn: () => {
            const fd = new FormData();
            if (fail) fd.append('fail', fail);
            Object.entries(f).forEach(([k, v]) => v && fd.append(k, v));
            return tsuApi.importHasil(u.id, fd);
        },
        onSuccess: (r) => {
            toast.success(r.message);
            setFail(null);
            onChange();
        },
        onError: (e) => toast.error(ralat(e)),
    });
    const mSah = useMutation({ mutationFn: () => tsuApi.sahkan(sah!.id, { status_pengesahan: sah!.status, catatan: sah!.catatan || undefined, id_dokumen_pelan_akui: sah!.pa || undefined }), onSuccess: (r) => { toast.success(r.message); setSah(null); onChange(); }, onError: (e) => toast.error(ralat(e)) });
    const feat = (g: { geometry: LotFeature['geometry'] | null } | null | undefined, status: string): LotFeature | null => (g?.geometry ? { type: 'Feature', geometry: g.geometry, properties: { status_pengesahan: status } } as unknown as LotFeature : null);
    const pelan = u.dokumen.filter((d) => ['pelan_akui', 'pelan_ukur'].includes(d.kategori_dokumen));

    return (
        <Card title="Hasil ukur (DXF / Shapefile / GeoJSON)">
            {terbuka && can('tsu.hasil_ukur.cipta') && (
                <form
                    className="rounded-md border border-otl-gray-200 p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        mImport.mutate();
                    }}
                >
                    <Field label="Format fail" htmlFor="hu-f"><Select id="hu-f" value={f.format_fail} onChange={(v) => setF((x) => ({ ...x, format_fail: v }))} options={[{ kod: 'geojson', nama: 'GeoJSON' }, { kod: 'dxf', nama: 'DXF (AutoCAD)' }, { kod: 'shp', nama: 'Shapefile (ZIP)' }]} /></Field>
                    <Field label="Sistem koordinat diisytihar" htmlFor="hu-s" help="Disemak dengan CRS dalam fail (.prj / crs) dan sempadan Malaysia."><Select id="hu-s" value={f.sistem_koordinat} onChange={(v) => setF((x) => ({ ...x, sistem_koordinat: v }))} options={ref?.sistem_koordinat ?? []} /></Field>
                    <Field label="Fail" htmlFor="hu-fail"><input id="hu-fail" type="file" accept=".dxf,.zip,.geojson,.json" className="block w-full text-body-sm" onChange={(e) => setFail(e.target.files?.[0] ?? null)} required /></Field>
                    <Field label="Keluasan dokumen rasmi (m²)" htmlFor="hu-k"><input id="hu-k" inputMode="decimal" className={inputClass} value={f.keluasan_dokumen} onChange={(e) => setF((x) => ({ ...x, keluasan_dokumen: e.target.value }))} /></Field>
                    <Field label="Lot diukur semula (jika berkenaan)" htmlFor="hu-l">
                        <select id="hu-l" className={inputClass} value={f.lot_id} onChange={(e) => setF((x) => ({ ...x, lot_id: e.target.value }))}>
                            <option value="">— lot baharu —</option>
                            {u.lot.filter((l) => l.peranan_lot !== 'baharu').map((l) => <option key={l.id} value={l.id}>{l.no_lot}</option>)}
                        </select>
                    </Field>
                    <Field label="No. lot baharu dicadang" htmlFor="hu-n" help="Bagi fail berbilang poligon, gunakan atribut no_lot dalam fail."><input id="hu-n" className={inputClass} value={f.no_lot_cadangan} onChange={(e) => setF((x) => ({ ...x, no_lot_cadangan: e.target.value }))} /></Field>
                    <Field label="Lantikan jurukur" htmlFor="hu-lt"><Select id="hu-lt" value={f.lantikan_id} onChange={(v) => setF((x) => ({ ...x, lantikan_id: v }))} options={lantikan.map((l) => ({ kod: l.id, nama: l.nama }))} empty="Tiada" /></Field>
                    <div className="flex items-end"><Button type="submit" disabled={mImport.isPending || !fail}>Import hasil ukur</Button></div>
                </form>
            )}
            {hasil.length === 0 ? (
                <Kosong>Tiada hasil ukur.</Kosong>
            ) : (
                <Table head={<><th>Lot</th><th>Format / CRS</th><th>Keluasan dikira</th><th>Keluasan dokumen</th><th>Sisihan</th><th>Status</th><th></th></>}>
                    {hasil.map((h) => (
                        <tr key={h.id}>
                            <td>{h.no_lot_cadangan ?? u.lot.find((l) => l.id === h.lot_id)?.no_lot ?? '—'} {h.id_lot_dicipta && <Badge variant="success">Lot dicipta</Badge>}</td>
                            <td>{h.format_fail.toUpperCase()} · EPSG:{h.sistem_koordinat}</td>
                            <td>{luas(h.keluasan_dikira)}</td>
                            <td>{luas(h.keluasan_dokumen)}</td>
                            <td>{h.sisihan_peratus === null ? '—' : <Badge variant={h.dalam_toleransi ? 'success' : 'danger'}>{h.sisihan_peratus}% / ±{h.toleransi_peratus}%</Badge>}</td>
                            <td><Status kod={h.status_pengesahan} nama={h.status_pengesahan === 'menunggu' ? 'Menunggu pengesahan' : h.status_pengesahan === 'diterima' ? 'Diterima' : 'Ditolak'} /></td>
                            <td className="whitespace-nowrap space-x-2">
                                <Button size="sm" variant="secondary" onClick={() => setBanding(h.id)}>Banding</Button>
                                {terbuka && h.status_pengesahan === 'menunggu' && can('tsu.hasil_ukur.lulus') && <Button size="sm" onClick={() => setSah({ id: h.id, status: 'diterima', catatan: '', pa: '' })}>Sahkan</Button>}
                            </td>
                        </tr>
                    ))}
                </Table>
            )}
            <Modal open={!!banding} onClose={() => setBanding(null)}>
                <ModalBody title="Perbandingan sebelah-menyebelah" footer={<Button variant="secondary" onClick={() => setBanding(null)}>Tutup</Button>}>
                    {!cmp ? (
                        <Kosong>Memuatkan…</Kosong>
                    ) : (
                        <>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div><p className="text-body-xs font-medium mb-1">Sempadan asal</p><LotMap data={feat(cmp.asal, 'disahkan_juruukur')} height={220} label="Sempadan asal" /></div>
                                <div><p className="text-body-xs font-medium mb-1">Geometri hasil ukur</p><LotMap data={feat(cmp.baharu, 'belum_disahkan')} height={220} label="Geometri baharu" /></div>
                            </div>
                            <ul className="text-body-sm space-y-1">
                                <li>Keluasan dikira (hasil ukur): <strong>{luas(cmp.keluasan_dikira)}</strong></li>
                                <li>Keluasan dokumen rasmi: <strong>{luas(cmp.keluasan_dokumen)}</strong></li>
                                <li>Keluasan sempadan asal (dikira): {luas(cmp.keluasan_asal_dikira)}</li>
                                <li>Sisihan: {cmp.sisihan_peratus === null ? '—' : <Badge variant={cmp.dalam_toleransi ? 'success' : 'danger'}>{cmp.sisihan_peratus}% (toleransi ±{cmp.toleransi_peratus}%)</Badge>}</li>
                                <li>Pertindihan dengan sempadan asal: {cmp.peratus_bertindih_asal === null ? '—' : `${cmp.peratus_bertindih_asal}%`}</li>
                            </ul>
                        </>
                    )}
                </ModalBody>
            </Modal>
            <Modal open={!!sah} onClose={() => setSah(null)}>
                {sah && (
                    <ModalBody title="Pengesahan hasil ukur" footer={<><Button variant="secondary" onClick={() => setSah(null)}>Batal</Button><Button onClick={() => mSah.mutate()} disabled={mSah.isPending}>Simpan keputusan</Button></>}>
                        <Field label="Keputusan" htmlFor="sh-s"><Select id="sh-s" value={sah.status} onChange={(v) => setSah({ ...sah, status: v })} options={[{ kod: 'diterima', nama: 'Terima' }, { kod: 'ditolak', nama: 'Tolak' }]} /></Field>
                        <Field label="Catatan / justifikasi" htmlFor="sh-c" help="Wajib jika sisihan melebihi toleransi."><textarea id="sh-c" rows={3} className={inputClass} value={sah.catatan} onChange={(e) => setSah({ ...sah, catatan: e.target.value })} /></Field>
                        <Field label="Pelan akui / pelan ukur" htmlFor="sh-p">
                            <select id="sh-p" className={inputClass} value={sah.pa} onChange={(e) => setSah({ ...sah, pa: e.target.value })}>
                                <option value="">Tiada</option>
                                {pelan.map((d) => <option key={d.id} value={d.id_dokumen_dms}>{d.kategori_nama} — {d.nama_fail}</option>)}
                            </select>
                        </Field>
                    </ModalBody>
                )}
            </Modal>
        </Card>
    );
}
