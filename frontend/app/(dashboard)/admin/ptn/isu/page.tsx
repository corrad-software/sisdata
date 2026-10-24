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
import { ptnApi, ralat, tarikh, useCan, useRujukan, type LotRingkas } from '@/lib/ptn';
import { Card, Field, inputClass, KodWarnaBadge, Kosong, ModalBody, Penapis, Select, Tahap } from '../_components/common';

/** SRS-PTN-13/22: monitoring issues with their corrective actions (colour-coded). */
function IsuList() {
    const sp = useSearchParams();
    const qc = useQueryClient();
    const can = useCan();
    const { data: ref } = useRujukan();
    const [f, setF] = useState({ kod_daerah: '', status: '', kategori_isu: '', tahap: '', q: '', terbuka: sp.get('terbuka') === '1', saya: false });
    const [page, setPage] = useState(1);
    const set = (k: keyof typeof f) => (v: string | boolean) => { setF((x) => ({ ...x, [k]: v })); setPage(1); };
    const { data, error } = useQuery({ queryKey: ['ptn', 'isu', f, page], queryFn: () => ptnApi.isu({ ...f, page }) });
    const [baru, setBaru] = useState<{ q: string; lot: LotRingkas | null; kategori_isu: string; tahap_keterukan: string; keterangan: string } | null>(null);
    const { data: carian } = useQuery({ queryKey: ['ptn', 'cari-lot', baru?.q], queryFn: () => ptnApi.cariLot(baru!.q), enabled: !!baru && baru.q.length >= 2 });
    const m = useMutation({ mutationFn: () => ptnApi.ciptaIsu({ lot_id: baru?.lot?.id, kategori_isu: baru?.kategori_isu, tahap_keterukan: baru?.tahap_keterukan, keterangan: baru?.keterangan }), onSuccess: () => { toast.success('Isu direkod.'); setBaru(null); qc.invalidateQueries({ queryKey: ['ptn', 'isu'] }); }, onError: (e) => toast.error(ralat(e)) });

    return (
        <PageContainer>
            <Penapis>
                <Field label="No. rujukan" htmlFor="i-q"><input id="i-q" className={inputClass} value={f.q} onChange={(e) => set('q')(e.target.value)} placeholder="ISU-2026-…" /></Field>
                <Field label="Daerah" htmlFor="i-d"><Select id="i-d" value={f.kod_daerah} onChange={set('kod_daerah')} options={ref?.daerah ?? []} empty="Semua daerah" /></Field>
                <Field label="Kategori" htmlFor="i-k"><Select id="i-k" value={f.kategori_isu} onChange={set('kategori_isu')} options={ref?.kategori_isu ?? []} empty="Semua kategori" /></Field>
                <Field label="Tahap keterukan" htmlFor="i-t"><Select id="i-t" value={f.tahap} onChange={set('tahap')} options={ref?.tahap_keterukan ?? []} empty="Semua" /></Field>
                <Field label="Status" htmlFor="i-s"><Select id="i-s" value={f.status} onChange={set('status')} options={ref?.status_isu ?? []} empty="Semua" /></Field>
                <label className="flex items-center gap-2 text-body-sm"><input type="checkbox" checked={f.terbuka} onChange={(e) => set('terbuka')(e.target.checked)} /> Terbuka sahaja</label>
                <label className="flex items-center gap-2 text-body-sm"><input type="checkbox" checked={f.saya} onChange={(e) => set('saya')(e.target.checked)} /> Tindakan saya</label>
            </Penapis>
            <Card title={`Isu pemantauan (${data?.meta.total ?? 0})`} actions={can('ptn.isu.cipta') && <Button size="sm" onClick={() => setBaru({ q: '', lot: null, kategori_isu: 'pencerobohan', tahap_keterukan: 'sederhana', keterangan: '' })}>Rekod isu</Button>}>
                {error && <p className="text-body-sm text-txt-danger">{ralat(error)}</p>}
                {data && data.data.length === 0 ? <Kosong>Tiada isu.</Kosong> : (
                    <Table head={<><th>No.</th><th>Dibuka</th><th>Lot</th><th>Daerah</th><th>Kategori</th><th>Tahap</th><th>Status</th><th>Tindakan</th></>}>
                        {(data?.data ?? []).map((i) => {
                            const worst = (i.tindakan_ringkas ?? []).map((t) => t.kod_warna).sort((a, b) => ['hijau', 'kuning', 'merah'].indexOf(b) - ['hijau', 'kuning', 'merah'].indexOf(a))[0];
                            return (
                                <tr key={i.id}>
                                    <td><Link className="text-txt-primary hover:underline" href={`/admin/ptn/isu/${i.id}`}>{i.no_rujukan}</Link></td>
                                    <td className="whitespace-nowrap">{tarikh(i.tarikh_dibuka)}</td>
                                    <td>{i.no_lot ?? '—'}</td>
                                    <td>{i.daerah}</td>
                                    <td>{i.kategori_nama}{i.id_aduan_berkaitan && <span className="ml-1"><Badge variant="info">Aduan</Badge></span>}</td>
                                    <td><Tahap tahap={i.tahap_keterukan} label={i.tahap_nama} /></td>
                                    <td>{i.status_nama}</td>
                                    <td>{worst ? <KodWarnaBadge warna={worst} label={`${i.tindakan_ringkas?.length} tindakan`} /> : <span className="text-txt-black-500">Tiada</span>}</td>
                                </tr>
                            );
                        })}
                    </Table>
                )}
                {data && data.meta.last_page > 1 && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />}
            </Card>
            <Modal open={!!baru} onClose={() => setBaru(null)}>
                <ModalBody title="Rekod isu pemantauan" footer={<><Button variant="secondary" onClick={() => setBaru(null)}>Batal</Button><Button disabled={!baru?.lot || !baru.keterangan || m.isPending} onClick={() => m.mutate()}>Simpan</Button></>}>
                    <Field label="Cari lot" htmlFor="ib-q" help={baru?.lot ? `Dipilih: ${baru.lot.no_lot}` : undefined}><input id="ib-q" className={inputClass} value={baru?.q ?? ''} onChange={(e) => setBaru((x) => x && { ...x, q: e.target.value })} /></Field>
                    {(carian ?? []).length > 0 && !baru?.lot && (
                        <ul className="max-h-40 overflow-y-auto divide-y divide-otl-divider border border-otl-gray-200 rounded-md">
                            {(carian ?? []).map((l) => <li key={l.id}><button type="button" className="w-full text-left px-3 py-2 text-body-sm hover:bg-bg-washed" onClick={() => setBaru((x) => x && { ...x, lot: l })}>{l.no_lot} · {l.kod_mukim}</button></li>)}
                        </ul>
                    )}
                    <Field label="Kategori isu" htmlFor="ib-k"><Select id="ib-k" value={baru?.kategori_isu ?? ''} onChange={(v) => setBaru((x) => x && { ...x, kategori_isu: v })} options={ref?.kategori_isu ?? []} /></Field>
                    <Field label="Tahap keterukan" htmlFor="ib-t"><Select id="ib-t" value={baru?.tahap_keterukan ?? ''} onChange={(v) => setBaru((x) => x && { ...x, tahap_keterukan: v })} options={ref?.tahap_keterukan ?? []} /></Field>
                    <Field label="Keterangan" htmlFor="ib-ket"><textarea id="ib-ket" rows={3} className={inputClass} value={baru?.keterangan ?? ''} onChange={(e) => setBaru((x) => x && { ...x, keterangan: e.target.value })} /></Field>
                </ModalBody>
            </Modal>
        </PageContainer>
    );
}

export default function Page() {
    return <Suspense><IsuList /></Suspense>;
}
