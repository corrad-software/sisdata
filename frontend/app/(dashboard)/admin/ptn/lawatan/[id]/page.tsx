'use client';

import { useMemo, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Tabs, TabPanel } from '@/components/ui/Tabs';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Callout } from '@/components/ui/Callout';
import { PetaSisdata } from '@/components/gis/PetaSisdata';
import { DocumentAttachments } from '@/components/dms/DocumentAttachments';
import type { KoleksiCiri } from '@/lib/gis';
import { LINGKUNGAN_LABEL, masa, muatTurun, ptnApi, ralat, tarikh, type Gambar, type LawatanButiran } from '@/lib/ptn';
import { Card, InfoGrid, inputClass, Kosong, StatusItem, Tahap } from '../../_components/common';

function Foto({ g, onSemak, boleh }: { g: Gambar; onSemak?: (keputusan: 'diterima' | 'ditolak') => void; boleh?: boolean }) {
    return (
        <figure className="border border-otl-gray-200 rounded-md overflow-hidden bg-bg-white">
            {g.imej_kecil ? (
                // eslint-disable-next-line @next/next/no-img-element -- data URI thumbnail from the API
                <img src={g.imej_kecil} alt={g.kapsyen ?? 'Gambar tapak'} className="w-full h-40 object-cover" />
            ) : (
                <div className="h-40 flex items-center justify-center text-body-xs text-txt-black-500">Gambar dalam DMS</div>
            )}
            <figcaption className="p-2 space-y-1 text-body-xs text-txt-black-700">
                <div className="flex flex-wrap gap-1">
                    <Badge variant={g.lingkungan === 'dalam' ? 'success' : 'danger'}>{LINGKUNGAN_LABEL[g.lingkungan]}{g.lingkungan === 'luar' && g.jarak_dari_lot_m !== null ? ` (${g.jarak_dari_lot_m} m)` : ''}</Badge>
                    {g.sumber_gambar === 'galeri' && <Badge variant="warning">Dari galeri</Badge>}
                    {g.keputusan_semakan && <Badge variant={g.keputusan_semakan === 'diterima' ? 'success' : 'danger'}>Semakan: {g.keputusan_semakan}</Badge>}
                </div>
                <p>{g.kapsyen ?? g.nama_fail}{g.kod_item ? ` · ${g.kod_item}` : ''}</p>
                <p>{masa(g.tarikh_masa)} · {g.lat !== null ? `${g.lat.toFixed(6)}, ${g.lng?.toFixed(6)}` : 'tiada koordinat'}{g.arah_kamera !== null ? ` · arah ${Math.round(g.arah_kamera)}°` : ''}</p>
                {boleh && g.perlu_semakan && !g.keputusan_semakan && onSemak && (
                    <div className="flex gap-2 pt-1">
                        <Button size="sm" variant="secondary" onClick={() => onSemak('diterima')}>Terima</Button>
                        <Button size="sm" variant="danger" onClick={() => onSemak('ditolak')}>Tolak</Button>
                    </div>
                )}
            </figcaption>
        </figure>
    );
}

function Perbandingan({ id }: { id: string }) {
    const [banding, setBanding] = useState<string | undefined>();
    const { data } = useQuery({ queryKey: ['ptn', 'banding', id, banding], queryFn: () => ptnApi.perbandingan(id, banding) });
    if (!data) return <Kosong>Memuatkan…</Kosong>;
    if (!data.sebelum) return <Kosong>Tiada lawatan terdahulu bagi lot ini untuk dibandingkan.</Kosong>;
    const ubah = { sama: ['neutral', 'Sama'], merosot: ['danger', 'Merosot'], bertambah_baik: ['success', 'Bertambah baik'], baharu: ['info', 'Baharu'] } as const;
    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-2 text-body-sm">
                <span>Banding dengan:</span>
                <select className={`${inputClass} max-w-xs`} value={data.sebelum.id} onChange={(e) => setBanding(e.target.value)} aria-label="Pilih lawatan terdahulu">
                    {data.sejarah.filter((s) => s.id !== id).map((s) => <option key={s.id} value={s.id}>{s.no_rujukan} · {tarikh(s.tarikh_lawatan)}</option>)}
                </select>
            </div>
            <Table head={<><th>Item</th><th>{tarikh(data.sebelum.tarikh_lawatan)}</th><th>{tarikh(data.semasa?.tarikh_lawatan)}</th><th>Perubahan</th></>}>
                {data.item.map((r) => (
                    <tr key={r.kod_item}>
                        <td className="min-w-[12rem]">{r.kod_item} · {r.label}</td>
                        <td><StatusItem status={r.sebelum} />{r.catatan_sebelum && <p className="text-body-xs text-txt-black-500">{r.catatan_sebelum}</p>}</td>
                        <td><StatusItem status={r.semasa} />{r.catatan_semasa && <p className="text-body-xs text-txt-black-500">{r.catatan_semasa}</p>}</td>
                        <td><Badge variant={ubah[r.perubahan][0]}>{ubah[r.perubahan][1]}</Badge></td>
                    </tr>
                ))}
            </Table>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><h4 className="font-semibold text-body-sm mb-2">Sebelum — {data.sebelum.no_rujukan}</h4><div className="grid grid-cols-2 gap-2">{data.gambar_sebelum.map((g) => <Foto key={g.id} g={g} />)}</div></div>
                <div><h4 className="font-semibold text-body-sm mb-2">Semasa — {data.semasa?.no_rujukan}</h4><div className="grid grid-cols-2 gap-2">{data.gambar_semasa.map((g) => <Foto key={g.id} g={g} />)}</div></div>
            </div>
        </div>
    );
}

function overlay(d: LawatanButiran): KoleksiCiri {
    const features: KoleksiCiri['features'] = [];
    if (d.lot?.geometri) features.push({ type: 'Feature', geometry: d.lot.geometri, properties: { warna: '#2563eb', label: d.lot.no_lot } } as KoleksiCiri['features'][number]);
    if (d.lat !== null && d.lng !== null) features.push({ type: 'Feature', geometry: { type: 'Point', coordinates: [d.lng, d.lat] }, properties: { label: 'Lokasi lawatan' } } as KoleksiCiri['features'][number]);
    d.gambar.forEach((g, i) => {
        if (g.lat !== null && g.lng !== null) features.push({ type: 'Feature', geometry: { type: 'Point', coordinates: [g.lng, g.lat] }, properties: { label: `G${i + 1}` } } as KoleksiCiri['features'][number]);
    });
    return { type: 'FeatureCollection', features } as KoleksiCiri;
}

export default function LawatanDetail() {
    const { id } = useParams<{ id: string }>();
    const qc = useQueryClient();
    const { data: d, error } = useQuery({ queryKey: ['ptn', 'lawatan', id], queryFn: () => ptnApi.lawatanButiran(id) });
    const refresh = () => qc.invalidateQueries({ queryKey: ['ptn', 'lawatan', id] });
    const [catatan, setCatatan] = useState('');
    const semak = useMutation({ mutationFn: ({ g, k }: { g: number; k: 'diterima' | 'ditolak' }) => ptnApi.semakGambar(id, g, k, window.prompt('Catatan semakan (pilihan):') ?? undefined), onSuccess: refresh, onError: (e) => toast.error(ralat(e)) });
    const konflik = useMutation({ mutationFn: (k: 'terima' | 'tolak') => ptnApi.konflik(id, k, catatan), onSuccess: () => { toast.success('Konflik diselesaikan.'); refresh(); }, onError: (e) => toast.error(ralat(e)) });
    const tambahan = useMutation({ mutationFn: (laporan: string) => ptnApi.tambahan(laporan, catatan), onSuccess: () => { toast.success('Laporan tambahan dihantar untuk pengesahan.'); setCatatan(''); refresh(); }, onError: (e) => toast.error(ralat(e)) });
    const ov = useMemo(() => (d ? overlay(d) : null), [d]);
    const asal = d?.laporan.find((r) => r.jenis === 'asal' && r.status_kunci);

    if (error) return <PageContainer><p className="text-body-sm text-txt-danger">{ralat(error)}</p></PageContainer>;
    if (!d) return <PageContainer><Kosong>Memuatkan…</Kosong></PageContainer>;

    return (
        <PageContainer>
            {d.dikunci && <Callout type="success" title="Lawatan disahkan dan dikunci">Disahkan oleh {d.pengesah?.nama ?? '—'} pada {masa(d.tarikh_disahkan)}. Pembetulan hanya melalui laporan tambahan.</Callout>}
            {d.status_lawatan === 'konflik' && (
                <Callout type="warning" title="Konflik penyegerakan">
                    <p>{d.sebab_konflik}</p>
                    {d.boleh.konflik && (
                        <div className="mt-3 space-y-2">
                            <textarea className={inputClass} rows={2} placeholder="Catatan keputusan" value={catatan} onChange={(e) => setCatatan(e.target.value)} aria-label="Catatan keputusan konflik" />
                            <div className="flex gap-2"><Button size="sm" onClick={() => konflik.mutate('terima')}>Terima lawatan</Button><Button size="sm" variant="danger" onClick={() => konflik.mutate('tolak')}>Tolak</Button></div>
                        </div>
                    )}
                </Callout>
            )}
            <Card title={`${d.no_rujukan} — ${d.status_nama}`} actions={d.lot && <Link href={`/admin/ptn/lot/${d.lot_id}`} className="text-body-sm text-txt-primary hover:underline">Kad pemantauan lot</Link>}>
                <InfoGrid items={[
                    ['Lot', d.lot ? <Link className="text-txt-primary hover:underline" href={d.lot.pautan}>{d.lot.no_lot}</Link> : '—'],
                    ['Daerah / Mukim', `${d.daerah ?? '—'} / ${d.mukim ?? '—'}`],
                    ['Tarikh lawatan', `${tarikh(d.tarikh_lawatan)} (${masa(d.masa_mula)} – ${masa(d.masa_tamat)})`],
                    ['Pegawai', d.pegawai?.nama],
                    ['Keadaan tapak', d.keadaan_nama],
                    ['Koordinat lawatan', d.lat !== null ? `${d.lat.toFixed(6)}, ${d.lng?.toFixed(6)} (±${d.ketepatan_gps_m ?? '?'} m)` : '—'],
                    ['Dalam sempadan lot', d.dalam_lingkungan === null ? '—' : d.dalam_lingkungan ? 'Ya' : `Tidak — ${d.jarak_dari_lot_m} m dari sempadan`],
                    ['Sumber', d.sumber === 'pwa' ? `Aplikasi lapangan (disegerak ${masa(d.disegerak_pada)})` : 'Web'],
                    ['Senarai semak', d.templat ? `${d.templat.nama} v${d.templat.versi ?? 1}` : '—'],
                ]} />
                <InfoGrid items={[['Ringkasan penemuan', d.ringkasan_penemuan], ['Isu pencerobohan', d.isu_pencerobohan], ['Isu penyelenggaraan', d.isu_penyelenggaraan], ['Cadangan tindakan', d.cadangan_tindakan]]} />
            </Card>

            <Tabs tabs={{ semak: 'Senarai semak', gambar: `Gambar (${d.gambar.length})`, peta: 'Peta', isu: `Penemuan (${d.isu.length})`, banding: 'Perbandingan', laporan: 'Laporan' }}>
                <TabPanel value="semak">
                    <Table head={<><th>Item</th><th>Kategori</th><th>Status</th><th>Catatan</th></>}>
                        {d.item.map((i) => (
                            <tr key={i.kod_item}><td className="min-w-[14rem]">{i.kod_item} · {i.label}</td><td>{i.kategori_item}</td><td><StatusItem status={i.status_item} /></td><td>{i.catatan ?? '—'}</td></tr>
                        ))}
                    </Table>
                </TabPanel>
                <TabPanel value="gambar">
                    {d.gambar.length === 0 ? <Kosong>Tiada gambar.</Kosong> : (
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            {d.gambar.map((g) => <Foto key={g.id} g={g} boleh={d.boleh.semak} onSemak={(k) => semak.mutate({ g: g.id, k })} />)}
                        </div>
                    )}
                </TabPanel>
                <TabPanel value="peta">
                    <PetaSisdata layers={['ptn.pemantauan']} focus={{ lotId: d.lot_id }} overlay={ov} height={380} label="Lokasi lawatan dan gambar" />
                    <p className="text-body-xs text-txt-black-500 mt-2">Poligon = sempadan lot; titik = lokasi lawatan (L) dan gambar (G1…).</p>
                </TabPanel>
                <TabPanel value="isu">
                    {d.isu.length === 0 ? <Kosong>Tiada penemuan isu.</Kosong> : (
                        <Table head={<><th>No.</th><th>Kategori</th><th>Tahap</th><th>Status</th><th>Aduan</th></>}>
                            {d.isu.map((i) => (
                                <tr key={i.id}><td><Link className="text-txt-primary hover:underline" href={`/admin/ptn/isu/${i.id}`}>{i.no_rujukan}</Link></td><td>{i.kategori_nama}</td><td><Tahap tahap={i.tahap_keterukan} label={i.tahap_nama} /></td><td>{i.status_nama}</td><td>{i.id_aduan_berkaitan ? <Link className="text-txt-primary hover:underline" href={`/admin/adu/aduan/${i.id_aduan_berkaitan}`}>Lihat aduan</Link> : '—'}</td></tr>
                            ))}
                        </Table>
                    )}
                    {d.cadangan.length > 0 && (
                        <div className="mt-4"><h4 className="font-semibold text-body-sm mb-2">Cadangan kemas kini rekod tanah (perlu kelulusan)</h4>
                            <ul className="space-y-1 text-body-sm">{d.cadangan.map((c) => <li key={c.id}>{c.medan}: {c.nilai_semasa ?? '—'} → <b>{c.nilai_dicadang}</b> · <Badge variant={c.status === 'lulus' ? 'success' : c.status === 'menunggu' ? 'info' : 'danger'}>{c.status}</Badge></li>)}</ul>
                        </div>
                    )}
                    {d.penyelenggaraan.length > 0 && (
                        <div className="mt-4"><h4 className="font-semibold text-body-sm mb-2">Keperluan penyelenggaraan</h4>
                            <ul className="space-y-1 text-body-sm">{d.penyelenggaraan.map((p) => <li key={p.id}>{p.jenis} — {p.keterangan} ({p.keutamaan}, {p.status})</li>)}</ul>
                        </div>
                    )}
                </TabPanel>
                <TabPanel value="banding"><Perbandingan id={id} /></TabPanel>
                <TabPanel value="laporan">
                    <div className="space-y-4">
                        <Table head={<><th>Jenis</th><th>Dijana</th><th>Status</th><th>Disahkan</th><th></th></>}>
                            {d.laporan.map((r) => (
                                <tr key={r.id}>
                                    <td>{r.jenis === 'asal' ? 'Laporan lawatan' : 'Laporan tambahan'}</td>
                                    <td>{masa(r.tarikh_jana)}</td>
                                    <td>{r.status_kunci ? <Badge variant="success">Disahkan · dikunci</Badge> : <Badge variant={r.status === 'ditolak' ? 'danger' : 'info'}>{r.status}</Badge>}</td>
                                    <td>{r.tarikh_sah ? `${masa(r.tarikh_sah)} · ${r.pengesah ?? ''}` : '—'}</td>
                                    <td><Button size="sm" variant="secondary" onClick={() => muatTurun(ptnApi.pdfUrl(r.id), `laporan-${d.no_rujukan}.pdf`).catch((e) => toast.error(ralat(e)))}>PDF</Button></td>
                                </tr>
                            ))}
                        </Table>
                        {d.boleh.tambahan && asal && (
                            <Card title="Laporan tambahan (pembetulan)">
                                <p className="text-body-xs text-txt-black-500">Laporan yang disahkan tidak boleh diubah. Pembetulan direkod sebagai laporan tambahan yang dipautkan kepada laporan asal dan disahkan semula.</p>
                                <textarea className={inputClass} rows={3} value={catatan} onChange={(e) => setCatatan(e.target.value)} aria-label="Butiran pembetulan" />
                                <Button disabled={catatan.trim().length < 10 || tambahan.isPending} onClick={() => tambahan.mutate(asal.id)}>Hantar laporan tambahan</Button>
                            </Card>
                        )}
                        <DocumentAttachments ownerType="rekod_lawatan" ownerId={d.id} title="Dokumen lawatan (DMS)" sourceModule="ptn" ownerLink={`/admin/ptn/lawatan/${d.id}`} readOnly />
                    </div>
                </TabPanel>
            </Tabs>
        </PageContainer>
    );
}
