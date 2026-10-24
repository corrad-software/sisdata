'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { ralat, tarikh, tsuApi, useCan, type Panel } from '@/lib/tsu';
import { Card, Field, inputClass, Kosong, ModalBody, Ralat, Select } from '../_components/common';

type Borang = { id?: number; nama_firma: string; no_lesen_jurukur: string; nama_jurukur_bertanggungjawab: string; pengalaman_tahun: string; status_panel: string; tarikh_tamat_lesen: string; emel: string; telefon: string };
const KOSONG: Borang = { nama_firma: '', no_lesen_jurukur: '', nama_jurukur_bertanggungjawab: '', pengalaman_tahun: '', status_panel: 'aktif', tarikh_tamat_lesen: '', emel: '', telefon: '' };

/** SRS-TSU-08/10: panel of licensed surveyors (licence number and expiry). */
export default function PanelJurukur() {
    const can = useCan();
    const qc = useQueryClient();
    const { data, error } = useQuery({ queryKey: ['tsu', 'panel'], queryFn: () => tsuApi.panel() });
    const [b, setB] = useState<Borang | null>(null);
    const m = useMutation({
        mutationFn: () =>
            tsuApi.simpanPanel(
                {
                    nama_firma: b!.nama_firma, no_lesen_jurukur: b!.no_lesen_jurukur, nama_jurukur_bertanggungjawab: b!.nama_jurukur_bertanggungjawab,
                    pengalaman_tahun: b!.pengalaman_tahun ? Number(b!.pengalaman_tahun) : null, status_panel: b!.status_panel, tarikh_tamat_lesen: b!.tarikh_tamat_lesen,
                    maklumat_perhubungan: { emel: b!.emel || null, telefon: b!.telefon || null },
                },
                b!.id,
            ),
        onSuccess: (r) => {
            toast.success(r.message);
            setB(null);
            qc.invalidateQueries({ queryKey: ['tsu', 'panel'] });
        },
        onError: (e) => toast.error(ralat(e)),
    });
    const ubah = (p: Panel) => setB({ id: p.id, nama_firma: p.nama_firma, no_lesen_jurukur: p.no_lesen_jurukur, nama_jurukur_bertanggungjawab: p.nama_jurukur_bertanggungjawab, pengalaman_tahun: p.pengalaman_tahun?.toString() ?? '', status_panel: p.status_panel, tarikh_tamat_lesen: p.tarikh_tamat_lesen, emel: p.maklumat_perhubungan.emel ?? '', telefon: p.maklumat_perhubungan.telefon ?? '' });
    const set = (k: keyof Borang) => (v: string) => setB((x) => (x ? { ...x, [k]: v } : x));

    return (
        <PageContainer>
            <Card title="Panel jurukur berlesen" actions={can('tsu.panel.kemas_kini') && <Button size="sm" onClick={() => setB(KOSONG)}>Tambah jurukur</Button>}>
                {error && <Ralat>{ralat(error)}</Ralat>}
                {!data ? (
                    <Kosong>Memuatkan…</Kosong>
                ) : data.length === 0 ? (
                    <Kosong>Tiada jurukur dalam panel.</Kosong>
                ) : (
                    <Table head={<><th>Firma</th><th>No. lesen</th><th>Jurukur bertanggungjawab</th><th>Pengalaman</th><th>Lesen tamat</th><th>Status</th>{can('tsu.panel.kemas_kini') && <th></th>}</>}>
                        {data.map((p) => (
                            <tr key={p.id}>
                                <td>{p.nama_firma} {p.sintetik && <Badge>Sintetik</Badge>}</td>
                                <td>{p.no_lesen_jurukur}</td>
                                <td>{p.nama_jurukur_bertanggungjawab}</td>
                                <td>{p.pengalaman_tahun ?? '—'} tahun</td>
                                <td>{tarikh(p.tarikh_tamat_lesen)}</td>
                                <td><Badge variant={p.layak ? 'success' : 'warning'}>{p.layak ? 'Layak' : p.status_panel === 'aktif' ? 'Lesen tamat' : p.status_panel}</Badge></td>
                                {can('tsu.panel.kemas_kini') && <td><Button size="sm" variant="secondary" onClick={() => ubah(p)}>Ubah</Button></td>}
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>
            <Modal open={!!b} onClose={() => setB(null)}>
                {b && (
                    <ModalBody title={b.id ? 'Ubah jurukur panel' : 'Tambah jurukur panel'} footer={<><Button variant="secondary" onClick={() => setB(null)}>Batal</Button><Button onClick={() => m.mutate()} disabled={m.isPending}>Simpan</Button></>}>
                        <Field label="Nama firma" htmlFor="pn-f"><input id="pn-f" className={inputClass} value={b.nama_firma} onChange={(e) => set('nama_firma')(e.target.value)} /></Field>
                        <Field label="No. lesen jurukur" htmlFor="pn-l"><input id="pn-l" className={inputClass} value={b.no_lesen_jurukur} onChange={(e) => set('no_lesen_jurukur')(e.target.value)} /></Field>
                        <Field label="Jurukur bertanggungjawab" htmlFor="pn-j"><input id="pn-j" className={inputClass} value={b.nama_jurukur_bertanggungjawab} onChange={(e) => set('nama_jurukur_bertanggungjawab')(e.target.value)} /></Field>
                        <Field label="Pengalaman (tahun)" htmlFor="pn-p"><input id="pn-p" type="number" min={0} className={inputClass} value={b.pengalaman_tahun} onChange={(e) => set('pengalaman_tahun')(e.target.value)} /></Field>
                        <Field label="Tarikh tamat lesen" htmlFor="pn-t"><input id="pn-t" type="date" className={inputClass} value={b.tarikh_tamat_lesen} onChange={(e) => set('tarikh_tamat_lesen')(e.target.value)} /></Field>
                        <Field label="Status panel" htmlFor="pn-s"><Select id="pn-s" value={b.status_panel} onChange={set('status_panel')} options={[{ kod: 'aktif', nama: 'Aktif' }, { kod: 'digantung', nama: 'Digantung' }, { kod: 'tamat', nama: 'Tamat' }]} /></Field>
                        <Field label="E-mel" htmlFor="pn-e"><input id="pn-e" type="email" className={inputClass} value={b.emel} onChange={(e) => set('emel')(e.target.value)} /></Field>
                        <Field label="Telefon" htmlFor="pn-tel"><input id="pn-tel" className={inputClass} value={b.telefon} onChange={(e) => set('telefon')(e.target.value)} /></Field>
                    </ModalBody>
                )}
            </Modal>
        </PageContainer>
    );
}
