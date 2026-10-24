'use client';

import { Suspense, useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Pagination } from '@/components/ui/Pagination';
import { ptnApi, ralat, tarikh, useCan, useRujukan, type Jadual, type LotRingkas } from '@/lib/ptn';
import { Card, Field, inputClass, KodWarnaBadge, Kosong, ModalBody, Penapis, Select, Tahap } from '../_components/common';

/** SRS-PTN-01/02: monitoring plan — annual risk-based generation, manual tasks, assignment to field officers. */
function JadualPage() {
    const sp = useSearchParams();
    const qc = useQueryClient();
    const can = useCan();
    const { data: ref } = useRujukan();
    const [f, setF] = useState({ tahun: String(new Date().getFullYear()), kod_daerah: '', status: '', keutamaan: '', tertunggak: sp.get('tertunggak') === '1', tanpa_pegawai: false });
    const [page, setPage] = useState(1);
    const set = (k: keyof typeof f) => (v: string | boolean) => { setF((x) => ({ ...x, [k]: v })); setPage(1); };
    const { data, error } = useQuery({ queryKey: ['ptn', 'jadual', f, page], queryFn: () => ptnApi.jadual({ ...f, page }) });
    const refresh = () => qc.invalidateQueries({ queryKey: ['ptn', 'jadual'] });

    const [jana, setJana] = useState<{ tahun: string; kod_daerah: string; semula: boolean } | null>(null);
    const [baru, setBaru] = useState<{ q: string; lot: LotRingkas | null; tarikh_sasaran: string; objektif: string; jenis_pemeriksaan: string } | null>(null);
    const [tugas, setTugas] = useState<Jadual | null>(null);
    const [pegawai, setPegawai] = useState('');

    const mJana = useMutation({ mutationFn: () => ptnApi.janaJadual({ tahun: Number(jana?.tahun), kod_daerah: jana?.kod_daerah || undefined, semula: jana?.semula }), onSuccess: (r) => { toast.success(r.message); setJana(null); refresh(); }, onError: (e) => toast.error(ralat(e)) });
    const mAgih = useMutation({ mutationFn: () => ptnApi.agih({ kod_daerah: f.kod_daerah, tahun: f.tahun }), onSuccess: (r) => { toast.success(r.message); refresh(); }, onError: (e) => toast.error(ralat(e)) });
    const mTugas = useMutation({ mutationFn: () => ptnApi.tugaskan(tugas!.id, Number(pegawai)), onSuccess: () => { toast.success('Tugasan diumpukkan.'); setTugas(null); refresh(); }, onError: (e) => toast.error(ralat(e)) });
    const mBaru = useMutation({ mutationFn: () => ptnApi.ciptaJadual({ lot_id: baru?.lot?.id, tarikh_sasaran: baru?.tarikh_sasaran, objektif: baru?.objektif, jenis_pemeriksaan: baru?.jenis_pemeriksaan }), onSuccess: () => { toast.success('Jadual diwujudkan.'); setBaru(null); refresh(); }, onError: (e) => toast.error(ralat(e)) });
    const mBatal = useMutation({ mutationFn: (j: Jadual) => ptnApi.batalJadual(j.id, window.prompt('Sebab pembatalan:') ?? ''), onSuccess: () => refresh(), onError: (e) => toast.error(ralat(e)) });
    const { data: layak } = useQuery({ queryKey: ['ptn', 'layak', tugas?.kod_daerah], queryFn: () => ptnApi.pegawaiLayak(tugas!.kod_daerah), enabled: !!tugas });
    const { data: carian } = useQuery({ queryKey: ['ptn', 'cari-lot', baru?.q], queryFn: () => ptnApi.cariLot(baru!.q), enabled: !!baru && baru.q.length >= 2 });

    return (
        <PageContainer>
            <Penapis>
                <Field label="Tahun" htmlFor="j-tahun"><input id="j-tahun" type="number" className={inputClass} value={f.tahun} onChange={(e) => set('tahun')(e.target.value)} /></Field>
                <Field label="Daerah" htmlFor="j-daerah"><Select id="j-daerah" value={f.kod_daerah} onChange={set('kod_daerah')} options={ref?.daerah ?? []} empty="Semua daerah" /></Field>
                <Field label="Status" htmlFor="j-status"><Select id="j-status" value={f.status} onChange={set('status')} options={ref?.status_jadual ?? []} empty="Semua status" /></Field>
                <Field label="Keutamaan risiko" htmlFor="j-keut"><Select id="j-keut" value={f.keutamaan} onChange={set('keutamaan')} options={ref?.tahap_risiko ?? []} empty="Semua" /></Field>
                <label className="flex items-center gap-2 text-body-sm"><input type="checkbox" checked={f.tertunggak} onChange={(e) => set('tertunggak')(e.target.checked)} /> Tertunggak sahaja</label>
                <label className="flex items-center gap-2 text-body-sm"><input type="checkbox" checked={f.tanpa_pegawai} onChange={(e) => set('tanpa_pegawai')(e.target.checked)} /> Belum diagih</label>
            </Penapis>

            <Card
                title={`Jadual pemantauan (${data?.meta.total ?? 0})`}
                actions={
                    <>
                        {can('ptn.jadual.cipta') && <Button variant="secondary" size="sm" onClick={() => setBaru({ q: '', lot: null, tarikh_sasaran: '', objektif: '', jenis_pemeriksaan: 'khas' })}>Tambah tugasan</Button>}
                        {can('ptn.jadual.cipta') && <Button size="sm" onClick={() => setJana({ tahun: String(new Date().getFullYear() + 1), kod_daerah: '', semula: false })}>Jana jadual tahunan</Button>}
                        {can('ptn.jadual.kemas_kini') && <Button variant="outline" size="sm" disabled={mAgih.isPending} onClick={() => mAgih.mutate()}>Agih automatik</Button>}
                    </>
                }
            >
                {error && <p className="text-body-sm text-txt-danger">{ralat(error)}</p>}
                {data && data.data.length === 0 ? <Kosong>Tiada jadual.</Kosong> : (
                    <Table head={<><th>Tarikh sasaran</th><th>Lot</th><th>Daerah</th><th>Objektif</th><th>Keutamaan</th><th>Pegawai</th><th>Status</th><th>Tempoh</th><th></th></>}>
                        {(data?.data ?? []).map((j) => (
                            <tr key={j.id}>
                                <td className="whitespace-nowrap">{tarikh(j.tarikh_sasaran)}</td>
                                <td><Link href={`/admin/ptn/lot/${j.lot_id}`} className="text-txt-primary hover:underline">{j.no_lot ?? '—'}</Link></td>
                                <td>{j.daerah}</td>
                                <td className="min-w-[14rem]">{j.objektif}{j.sintetik && <Badge>Sintetik</Badge>}</td>
                                <td><Tahap tahap={j.keutamaan_risiko} label={j.keutamaan_nama} /></td>
                                <td>{j.pegawai?.nama ?? <span className="text-txt-black-500">Belum diagih</span>}</td>
                                <td>{j.status_nama}</td>
                                <td>{['dirancang', 'ditugaskan', 'dalam_pelaksanaan'].includes(j.status_pelaksanaan) ? <KodWarnaBadge warna={j.kod_warna} label={j.kod_warna === 'merah' ? 'Tertunggak' : j.kod_warna === 'kuning' ? 'Hampir tempoh' : 'Dalam tempoh'} /> : '—'}</td>
                                <td className="whitespace-nowrap space-x-2">
                                    {can('ptn.jadual.kemas_kini') && ['dirancang', 'ditugaskan'].includes(j.status_pelaksanaan) && (
                                        <>
                                            <Button size="sm" variant="secondary" onClick={() => { setTugas(j); setPegawai(String(j.pegawai?.id ?? '')); }}>Agih</Button>
                                            <Button size="sm" variant="secondary" onClick={() => mBatal.mutate(j)}>Batal</Button>
                                        </>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </Table>
                )}
                {data && data.meta.last_page > 1 && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />}
            </Card>

            <Modal open={!!jana} onClose={() => setJana(null)}>
                <ModalBody title="Jana jadual pemantauan tahunan" footer={<><Button variant="secondary" onClick={() => setJana(null)}>Batal</Button><Button disabled={mJana.isPending} onClick={() => mJana.mutate()}>Jana</Button></>}>
                    <p className="text-body-sm text-txt-black-700">Kekerapan lawatan ditentukan oleh keutamaan risiko setiap lot (skor risiko, sejarah isu, nilai aset, tempoh sejak lawatan terakhir) dan konfigurasi kekerapan mengikut kategori lot.</p>
                    <Field label="Tahun" htmlFor="jn-tahun"><input id="jn-tahun" type="number" className={inputClass} value={jana?.tahun ?? ''} onChange={(e) => setJana((x) => x && { ...x, tahun: e.target.value })} /></Field>
                    <Field label="Daerah (pilihan)" htmlFor="jn-daerah"><Select id="jn-daerah" value={jana?.kod_daerah ?? ''} onChange={(v) => setJana((x) => x && { ...x, kod_daerah: v })} options={ref?.daerah ?? []} empty="Semua daerah dalam skop" /></Field>
                    <label className="flex items-center gap-2 text-body-sm"><input type="checkbox" checked={jana?.semula ?? false} onChange={(e) => setJana((x) => x && { ...x, semula: e.target.checked })} /> Jana semula jadual automatik yang belum diagih</label>
                </ModalBody>
            </Modal>

            <Modal open={!!tugas} onClose={() => setTugas(null)}>
                <ModalBody title={`Agih tugasan — lot ${tugas?.no_lot ?? ''}`} footer={<><Button variant="secondary" onClick={() => setTugas(null)}>Batal</Button><Button disabled={!pegawai || mTugas.isPending} onClick={() => mTugas.mutate()}>Simpan</Button></>}>
                    <Field label="Pegawai lapangan (daerah lot)" htmlFor="tg-peg">
                        <Select id="tg-peg" value={pegawai} onChange={setPegawai} options={(layak ?? []).map((p) => ({ kod: String(p.id), nama: p.nama }))} empty="Pilih pegawai" />
                    </Field>
                    {layak && layak.length === 0 && <p className="text-body-sm text-txt-warning">Tiada pegawai dengan capaian aplikasi lapangan bagi daerah ini.</p>}
                </ModalBody>
            </Modal>

            <Modal open={!!baru} onClose={() => setBaru(null)}>
                <ModalBody title="Tambah tugasan pemantauan" footer={<><Button variant="secondary" onClick={() => setBaru(null)}>Batal</Button><Button disabled={!baru?.lot || !baru.tarikh_sasaran || !baru.objektif || mBaru.isPending} onClick={() => mBaru.mutate()}>Simpan</Button></>}>
                    <Field label="Cari lot" htmlFor="b-q" help={baru?.lot ? `Dipilih: ${baru.lot.no_lot}` : 'Taip no. lot'}>
                        <input id="b-q" className={inputClass} value={baru?.q ?? ''} onChange={(e) => setBaru((x) => x && { ...x, q: e.target.value })} />
                    </Field>
                    {(carian ?? []).length > 0 && !baru?.lot && (
                        <ul className="max-h-40 overflow-y-auto divide-y divide-otl-divider border border-otl-gray-200 rounded-md">
                            {(carian ?? []).map((l) => (
                                <li key={l.id}><button type="button" className="w-full text-left px-3 py-2 text-body-sm hover:bg-bg-washed" onClick={() => setBaru((x) => x && { ...x, lot: l })}>{l.no_lot} · {l.kod_mukim}</button></li>
                            ))}
                        </ul>
                    )}
                    <Field label="Tarikh sasaran" htmlFor="b-t"><input id="b-t" type="date" className={inputClass} value={baru?.tarikh_sasaran ?? ''} onChange={(e) => setBaru((x) => x && { ...x, tarikh_sasaran: e.target.value })} /></Field>
                    <Field label="Jenis pemeriksaan" htmlFor="b-j"><Select id="b-j" value={baru?.jenis_pemeriksaan ?? ''} onChange={(v) => setBaru((x) => x && { ...x, jenis_pemeriksaan: v })} options={ref?.jenis_pemeriksaan ?? []} /></Field>
                    <Field label="Objektif pemantauan" htmlFor="b-o"><textarea id="b-o" className={inputClass} rows={3} value={baru?.objektif ?? ''} onChange={(e) => setBaru((x) => x && { ...x, objektif: e.target.value })} /></Field>
                </ModalBody>
            </Modal>
        </PageContainer>
    );
}

export default function Page() {
    return <Suspense><JadualPage /></Suspense>;
}
