'use client';

import { Suspense, useState } from 'react';
import Link from 'next/link';
import { useParams, useSearchParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Tabs, TabPanel } from '@/components/ui/Tabs';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { luas, masa, ralat, rm, tarikh, tsuApi, useCan } from '@/lib/tsu';
import { Card, Field, InfoGrid, inputClass, Kosong, ModalBody, Ralat, Status } from '../../_components/common';
import { TabDokumen } from '../../_components/TabDokumen';
import { TabKos } from '../../_components/TabKos';
import { TabUkur } from '../../_components/TabUkur';
import { TabPtd } from '../../_components/TabPtd';
import { TabPemindahan } from '../../_components/TabPemindahan';

/** SRS-TSU-03..25: one urusan — stage timeline, documents, costs, survey, Land Office status, record transfer, decisions. */
function Butiran() {
    const { id } = useParams<{ id: string }>();
    const params = useSearchParams();
    const qc = useQueryClient();
    const can = useCan();
    const { data: u, error } = useQuery({ queryKey: ['tsu', 'urusan', id], queryFn: () => tsuApi.butiran(id) });
    const refresh = () => qc.invalidateQueries({ queryKey: ['tsu'] });
    const [catatan, setCatatan] = useState('');
    const [tutup, setTutup] = useState(false);
    const [batal, setBatal] = useState(false);
    const [sebab, setSebab] = useState('');
    const [just, setJust] = useState<string | null>(null);

    const mSelesai = useMutation({ mutationFn: () => tsuApi.selesaiPeringkat(id, catatan || undefined), onSuccess: (r) => { toast.success(r.message); setCatatan(''); refresh(); }, onError: (e) => toast.error(ralat(e)) });
    const mTutup = useMutation({ mutationFn: () => tsuApi.tutup(id, catatan || undefined), onSuccess: (r) => { toast.success(r.message); setTutup(false); refresh(); }, onError: (e) => toast.error(ralat(e)) });
    const mBatal = useMutation({ mutationFn: () => tsuApi.batal(id, sebab), onSuccess: (r) => { toast.success(r.message); setBatal(false); refresh(); }, onError: (e) => toast.error(ralat(e)) });
    const mJust = useMutation({ mutationFn: () => tsuApi.kemaskini(id, { justifikasi: just }), onSuccess: (r) => { toast.success(r.message); setJust(null); refresh(); }, onError: (e) => toast.error(ralat(e)) });

    if (error) return <PageContainer><Ralat>{ralat(error)}</Ralat></PageContainer>;
    if (!u) return <PageContainer><Kosong>Memuatkan…</Kosong></PageContainer>;
    const terbuka = ['aktif', 'menunggu_kelulusan'].includes(u.status);
    const semasa = u.peringkat.find((p) => p.semasa);
    const boleh = can('tsu.urusan.kemas_kini') && !u.dikunci;
    const tabs: Record<string, string> = { garis: 'Garis masa peringkat', dokumen: 'Dokumen', kos: 'Kos', ukur: 'Ukur', ptd: 'Pejabat Tanah', pemindahan: 'Pemindahan rekod', keputusan: 'Sejarah keputusan' };

    return (
        <PageContainer>
            <Card
                title={
                    <span className="flex flex-wrap items-center gap-2">
                        {u.no_rujukan_mais} — {u.jenis_nama} <Status kod={u.status} nama={u.status_nama} />
                        {u.dikunci && <Badge>Dikunci</Badge>}
                        {u.sintetik && <Badge>Sintetik</Badge>}
                    </span>
                }
                actions={
                    boleh && (
                        <>
                            {['selesai', 'ditolak', 'dibatalkan'].includes(u.status) && <Button size="sm" onClick={() => setTutup(true)}>Tutup &amp; kunci urusan</Button>}
                            {terbuka && <Button size="sm" variant="danger" onClick={() => setBatal(true)}>Batal</Button>}
                        </>
                    )
                }
            >
                <p className="text-body-sm text-txt-black-700">{u.tajuk}</p>
                <InfoGrid
                    items={[
                        ['Peringkat semasa', u.peringkat_semasa_nama ?? '—'],
                        ['Tarikh mula', tarikh(u.tarikh_mula)],
                        ['Sasaran siap', <span key="s">{tarikh(u.tarikh_sasaran_siap)} {u.lewat_sasaran && <Badge variant="warning">Lewat</Badge>}</span>],
                        ['No. rujukan PTD', u.no_rujukan_ptd ?? '—'],
                        ['No. rujukan e-Tanah', u.no_rujukan_etanah ?? '—'],
                        ['Status Pejabat Tanah', u.status_ptd_nama ?? '—'],
                        ['Pegawai bertanggungjawab', u.pegawai?.nama ?? '—'],
                        ['Daerah / mukim', `${u.kod_daerah} / ${u.kod_mukim}`],
                        ['Klasifikasi', u.klasifikasi],
                        ['Templat', `${u.templat.nama} (versi ${u.templat.versi})`],
                        ...(u.kaedah_pelupusan ? ([['Kaedah pelupusan', u.kaedah_pelupusan_nama]] as Array<[string, string | null]>) : []),
                        ...(u.jenis_urusan === 'pelupusan'
                            ? ([['Nilai penilaian JPPH', u.nilai_penilaian_tersembunyi ? 'Rahsia — tiada kebenaran' : rm(u.nilai_penilaian)], ['Penilaian terkini', u.penilaian_sah ? 'Sah' : 'Tiada / luput']] as Array<[string, string]>)
                            : []),
                    ]}
                />
                {u.jenis_urusan === 'pelupusan' && (
                    <div className="rounded-md bg-bg-washed p-3 text-body-sm space-y-2">
                        <p className="font-medium">Justifikasi bertulis pelupusan</p>
                        {just === null ? (
                            <p className="whitespace-pre-line">{u.justifikasi ?? '— belum diisi —'} {boleh && terbuka && u.status !== 'menunggu_kelulusan' && <button type="button" className="text-txt-primary hover:underline" onClick={() => setJust(u.justifikasi ?? '')}>Ubah</button>}</p>
                        ) : (
                            <div className="space-y-2">
                                <textarea aria-label="Justifikasi" rows={3} className={inputClass} value={just} onChange={(e) => setJust(e.target.value)} />
                                <div className="flex gap-2"><Button size="sm" onClick={() => mJust.mutate()} disabled={mJust.isPending}>Simpan</Button><Button size="sm" variant="secondary" onClick={() => setJust(null)}>Batal</Button></div>
                            </div>
                        )}
                    </div>
                )}
                <div>
                    <p className="text-body-xs font-medium text-txt-black-500 mb-1">Lot</p>
                    <ul className="flex flex-wrap gap-2">
                        {u.lot.map((l) => (
                            <li key={l.id}>
                                <Link href={l.pautan} className="inline-flex items-center gap-2 rounded-full bg-bg-washed px-3 py-1 text-body-sm hover:underline">
                                    {l.no_lot ?? l.id.slice(0, 8)} <Badge variant={l.peranan_lot === 'baharu' ? 'success' : 'neutral'}>{l.peranan_nama}</Badge> {l.status_lot === 'arkib' && <Badge variant="warning">Diarkib</Badge>} <span className="text-txt-black-500">{l.keluasan ? (l.unit_keluasan === 'm2' ? luas(l.keluasan) : `${l.keluasan} ${l.unit_keluasan ?? ''}`) : '—'}</span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            </Card>

            <Card>
                <Tabs tabs={tabs} defaultTab={params.get('tab') && tabs[params.get('tab') as string] ? (params.get('tab') as string) : 'garis'}>
                    <TabPanel value="garis">
                        <div className="space-y-4 pt-4">
                            {terbuka && semasa && boleh && (
                                <div className="rounded-md border border-otl-gray-200 p-4 space-y-3">
                                    <p className="text-body-sm">
                                        Peringkat semasa: <strong>{semasa.nama_peringkat}</strong> ({semasa.pihak_nama}) — sasaran {tarikh(semasa.tarikh_sasaran)} {semasa.lewat && <Badge variant="danger">Lewat</Badge>}
                                    </p>
                                    {u.dokumen_belum_lengkap.length > 0 && <p className="text-body-sm text-txt-warning">Dokumen wajib belum lengkap: {u.dokumen_belum_lengkap.join(', ')}</p>}
                                    {semasa.catatan && <p className="text-body-sm text-txt-black-500">Catatan: {semasa.catatan}</p>}
                                    {semasa.status === 'aktif' ? (
                                        <div className="flex flex-col sm:flex-row gap-2">
                                            <input aria-label="Catatan peringkat" className={inputClass} placeholder="Catatan (pilihan)" value={catatan} onChange={(e) => setCatatan(e.target.value)} />
                                            <Button onClick={() => mSelesai.mutate()} disabled={mSelesai.isPending}>{semasa.perlu_kelulusan ? 'Hantar untuk kelulusan' : 'Selesaikan peringkat'}</Button>
                                        </div>
                                    ) : (
                                        <p className="text-body-sm"><Badge variant="warning">Menunggu kelulusan di Modul Workflow</Badge> <Link className="text-txt-primary hover:underline" href="/admin/wfe/tugasan">Peti tugasan</Link></p>
                                    )}
                                </div>
                            )}
                            <ol className="relative border-s border-otl-gray-200 ms-3">
                                {u.peringkat.map((p) => (
                                    <li key={p.id} className="mb-6 ms-6 last:mb-0">
                                        <span className={`absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-bg-white text-body-xs font-semibold ${p.semasa ? 'bg-primary-600 text-white' : p.status === 'selesai' ? 'bg-bg-success-50 text-txt-success' : p.status === 'ditolak' ? 'bg-bg-danger-50 text-txt-danger' : 'bg-bg-washed text-txt-black-500'}`} aria-hidden>
                                            {p.no_urutan}
                                        </span>
                                        <div className={`rounded-md p-3 ${p.semasa ? 'border-2 border-primary-600 bg-bg-primary-50' : 'border border-otl-gray-200'}`}>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h4 className="text-body-sm font-semibold text-txt-black-900">{p.nama_peringkat}</h4>
                                                {p.semasa && <Badge variant="info">Peringkat semasa</Badge>}
                                                <Status kod={p.status} nama={p.status_nama} />
                                                {p.lewat && <Badge variant="danger">Melepasi sasaran</Badge>}
                                                {p.perlu_kelulusan && <Badge variant="warning">Perlu kelulusan</Badge>}
                                            </div>
                                            <p className="mt-1 text-body-xs text-txt-black-500">
                                                Pihak: {p.pihak_nama} · Tempoh sasaran {p.tempoh_sasaran_hari} hari · Mula {tarikh(p.tarikh_mula)} · Sasaran {tarikh(p.tarikh_sasaran)} · Selesai {tarikh(p.tarikh_selesai)}
                                            </p>
                                            {p.tindakan_diperlukan && <p className="mt-1 text-body-sm">{p.tindakan_diperlukan}</p>}
                                            {(p.dokumen_wajib.length > 0 || p.kategori_kos.length > 0) && (
                                                <p className="mt-1 text-body-xs text-txt-black-500">
                                                    {p.dokumen_wajib.length > 0 && <>Dokumen wajib: {p.dokumen_wajib.map((d) => d.nama).join(', ')}. </>}
                                                    {p.kategori_kos.length > 0 && <>Kos: {p.kategori_kos.map((d) => d.nama).join(', ')}.</>}
                                                </p>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </TabPanel>
                    <TabPanel value="dokumen">
                        <TabDokumen u={u} boleh={boleh} onChange={refresh} />
                    </TabPanel>
                    <TabPanel value="kos">
                        <TabKos u={u} onChange={refresh} />
                    </TabPanel>
                    <TabPanel value="ukur">
                        <TabUkur u={u} onChange={refresh} />
                    </TabPanel>
                    <TabPanel value="ptd">
                        <TabPtd u={u} onChange={refresh} />
                    </TabPanel>
                    <TabPanel value="pemindahan">
                        <TabPemindahan u={u} onChange={refresh} />
                    </TabPanel>
                    <TabPanel value="keputusan">
                        <ul className="divide-y divide-otl-gray-200 pt-2">
                            {u.keputusan.length === 0 && <Kosong>Tiada keputusan direkodkan.</Kosong>}
                            {[...u.keputusan].reverse().map((k) => (
                                <li key={k.id} className="py-3 text-body-sm">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">{k.jenis_nama}</span>
                                        <Badge>{k.konteks}</Badge>
                                        {k.kod_peringkat_wfe && <span className="text-txt-black-500">peringkat WFE: {k.kod_peringkat_wfe}</span>}
                                        {k.indikator_mfa && <Badge variant="success">MFA</Badge>}
                                        <span className="text-txt-black-500">{masa(k.tarikh_keputusan)} · {k.pelulus ?? 'Sistem'}</span>
                                    </div>
                                    {k.justifikasi && <p className="mt-1 whitespace-pre-line text-txt-black-700">{k.justifikasi}</p>}
                                </li>
                            ))}
                        </ul>
                        <p className="text-body-xs text-txt-black-500">Sejarah keputusan tidak boleh diubah atau dipadam (SRS-TSU-24).</p>
                    </TabPanel>
                </Tabs>
            </Card>

            <Modal open={tutup} onClose={() => setTutup(false)}>
                <ModalBody title="Tutup dan kunci urusan" footer={<><Button variant="secondary" onClick={() => setTutup(false)}>Batal</Button><Button onClick={() => mTutup.mutate()} disabled={mTutup.isPending}>Tutup urusan</Button></>}>
                    <p className="text-body-sm">Selepas ditutup, urusan dan semua rekodnya menjadi rekod audit yang tidak boleh diubah.</p>
                    <Field label="Catatan penutupan" htmlFor="t-cat">
                        <textarea id="t-cat" rows={3} className={inputClass} value={catatan} onChange={(e) => setCatatan(e.target.value)} />
                    </Field>
                </ModalBody>
            </Modal>
            <Modal open={batal} onClose={() => setBatal(false)}>
                <ModalBody title="Batal urusan" footer={<><Button variant="secondary" onClick={() => setBatal(false)}>Kembali</Button><Button variant="danger" onClick={() => mBatal.mutate()} disabled={mBatal.isPending || !sebab.trim()}>Batal urusan</Button></>}>
                    <Field label="Sebab pembatalan" htmlFor="b-seb">
                        <textarea id="b-seb" rows={3} className={inputClass} value={sebab} onChange={(e) => setSebab(e.target.value)} />
                    </Field>
                </ModalBody>
            </Modal>
        </PageContainer>
    );
}

export default function Page() {
    return (
        <Suspense>
            <Butiran />
        </Suspense>
    );
}
