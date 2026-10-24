'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Callout } from '@/components/ui/Callout';
import { Modal } from '@/components/ui/Modal';
import { TabPanel, Tabs } from '@/components/ui/Tabs';
import { PetaSisdata } from '@/components/gis/PetaSisdata';
import { astApi, ralat, rm, tarikh, useCan, useRujukan, type AsetPenuh } from '@/lib/ast';
import { Card, Field, InfoGrid, inputClass, KondisiBadge, Kosong, ModalBody, Select } from '../../_components/common';
import { PanelDokumen, PanelGambar, PanelNilai, PanelPelupusan, PanelPemeriksaan, PanelPenyelenggaraan, PanelQr, PanelSejarah } from '../../_components/panels';

function UbahKondisi({ a, onClose }: { a: AsetPenuh; onClose: () => void }) {
    const qc = useQueryClient();
    const { data: ref } = useRujukan();
    const [s, setS] = useState<string>(a.status_kondisi);
    const [c, setC] = useState('');
    const m = useMutation({ mutationFn: () => astApi.kondisi(a.id, { status_kondisi: s, catatan: c || undefined }), onSuccess: (r) => { toast.success(r.message); qc.invalidateQueries({ queryKey: ['ast', 'aset', a.id] }); onClose(); }, onError: (e) => toast.error(ralat(e)) });
    return (
        <ModalBody title="Kemas kini status kondisi" footer={<><Button variant="secondary" onClick={onClose}>Batal</Button><Button disabled={m.isPending || s === a.status_kondisi} onClick={() => m.mutate()}>Simpan</Button></>}>
            <Field label="Status kondisi" htmlFor="k-s"><Select id="k-s" value={s} onChange={setS} options={ref?.kondisi ?? []} /></Field>
            <Field label="Catatan" htmlFor="k-c"><textarea id="k-c" rows={2} className={inputClass} value={c} onChange={(e) => setC(e.target.value)} /></Field>
        </ModalBody>
    );
}

function Sunting({ a, onClose }: { a: AsetPenuh; onClose: () => void }) {
    const qc = useQueryClient();
    const { data: ref } = useRujukan();
    const [f, setF] = useState({
        nama_aset: a.nama_aset, id_kategori: String(a.id_kategori), jenis_aset: a.jenis_aset, alamat: a.alamat ?? '', keterangan: a.keterangan ?? '',
        tahun_pembinaan: a.tahun_pembinaan ? String(a.tahun_pembinaan) : '', saiz_keluasan: a.saiz_keluasan ?? '', status_aset: ['aktif', 'dalam_penyelenggaraan', 'tidak_aktif'].includes(a.status_aset) ? a.status_aset : 'aktif',
        latitud: a.latitud !== null ? String(a.latitud) : '', longitud: a.longitud !== null ? String(a.longitud) : '', tag: a.tag.map((t) => t.nama).join(', '), sebab: '',
    });
    const m = useMutation({
        mutationFn: () => astApi.kemaskini(a.id, {
            ...f, id_kategori: Number(f.id_kategori), tahun_pembinaan: f.tahun_pembinaan ? Number(f.tahun_pembinaan) : null, saiz_keluasan: f.saiz_keluasan || null,
            latitud: f.latitud ? Number(f.latitud) : undefined, longitud: f.longitud ? Number(f.longitud) : undefined, tag: f.tag.split(',').map((t) => t.trim()).filter(Boolean), sebab: f.sebab || null,
        }),
        onSuccess: (r) => { toast.success(r.message); qc.invalidateQueries({ queryKey: ['ast', 'aset', a.id] }); onClose(); },
        onError: (e) => toast.error(ralat(e)),
    });
    const s = (k: keyof typeof f) => (v: string) => setF({ ...f, [k]: v });
    return (
        <ModalBody title="Kemas kini rekod aset" footer={<><Button variant="secondary" onClick={onClose}>Batal</Button><Button disabled={m.isPending} onClick={() => m.mutate()}>Simpan</Button></>}>
            <Field label="Nama aset" htmlFor="s-n"><input id="s-n" className={inputClass} value={f.nama_aset} onChange={(e) => s('nama_aset')(e.target.value)} /></Field>
            <Field label="Kategori" htmlFor="s-k"><Select id="s-k" value={f.id_kategori} onChange={s('id_kategori')} options={(ref?.kategori ?? []).map((k) => ({ kod: String(k.id), nama: k.nama }))} /></Field>
            <Field label="Jenis aset" htmlFor="s-j"><Select id="s-j" value={f.jenis_aset} onChange={s('jenis_aset')} options={ref?.jenis_aset ?? []} /></Field>
            <Field label="Status aset" htmlFor="s-st"><Select id="s-st" value={f.status_aset} onChange={s('status_aset')} options={(ref?.status_aset ?? []).filter((x) => ['aktif', 'dalam_penyelenggaraan', 'tidak_aktif'].includes(x.kod))} /></Field>
            <Field label="Alamat" htmlFor="s-a"><input id="s-a" className={inputClass} value={f.alamat} onChange={(e) => s('alamat')(e.target.value)} /></Field>
            <div className="grid grid-cols-2 gap-2">
                <Field label="Latitud" htmlFor="s-lat"><input id="s-lat" inputMode="decimal" className={inputClass} value={f.latitud} onChange={(e) => s('latitud')(e.target.value)} /></Field>
                <Field label="Longitud" htmlFor="s-lng"><input id="s-lng" inputMode="decimal" className={inputClass} value={f.longitud} onChange={(e) => s('longitud')(e.target.value)} /></Field>
            </div>
            <Field label="Tahun pembinaan" htmlFor="s-tb"><input id="s-tb" inputMode="numeric" className={inputClass} value={f.tahun_pembinaan} onChange={(e) => s('tahun_pembinaan')(e.target.value)} /></Field>
            <Field label="Saiz / keluasan" htmlFor="s-sz"><input id="s-sz" inputMode="decimal" className={inputClass} value={f.saiz_keluasan} onChange={(e) => s('saiz_keluasan')(e.target.value)} /></Field>
            <Field label="Tag (dipisah koma)" htmlFor="s-t"><input id="s-t" className={inputClass} value={f.tag} onChange={(e) => s('tag')(e.target.value)} /></Field>
            <Field label="Keterangan" htmlFor="s-ket"><textarea id="s-ket" rows={2} className={inputClass} value={f.keterangan} onChange={(e) => s('keterangan')(e.target.value)} /></Field>
            <Field label="Sebab perubahan" htmlFor="s-sb"><input id="s-sb" className={inputClass} value={f.sebab} onChange={(e) => s('sebab')(e.target.value)} /></Field>
        </ModalBody>
    );
}

/** Asset profile (SRS-AST-02..23): details, map, gallery, inspections, maintenance, value, documents, history, QR. */
export default function ProfilAset() {
    const { id } = useParams<{ id: string }>();
    const can = useCan();
    const { data: ref } = useRujukan();
    const { data: a, error } = useQuery({ queryKey: ['ast', 'aset', id], queryFn: () => astApi.asetButiran(id) });
    const [modal, setModal] = useState<'kondisi' | 'sunting' | null>(null);
    if (error) return <PageContainer><p className="text-body-sm text-txt-danger">{ralat(error)}</p></PageContainer>;
    if (!a) return <PageContainer><Kosong>Memuatkan…</Kosong></PageContainer>;

    const tabs: Record<string, string> = { gambar: 'Galeri gambar' };
    if (can('ast.pemeriksaan.lihat')) tabs.pemeriksaan = 'Pemeriksaan';
    if (can('ast.penyelenggaraan.lihat')) tabs.penyelenggaraan = 'Penyelenggaraan';
    tabs.nilai = 'Nilai & susut nilai';
    tabs.dokumen = 'Dokumen & insurans';
    tabs.sejarah = 'Sejarah & audit';
    tabs.qr = 'Kod QR & imbasan';
    tabs.pelupusan = 'Pelupusan';

    return (
        <PageContainer>
            {a.status_arkib && <Callout type="warning" title="Aset diarkibkan">{a.status_nama} pada {tarikh(a.tarikh_arkib)} — {a.sebab_arkib}. Rekod kekal untuk rujukan dan audit; tidak boleh diubah.</Callout>}
            {a.status_kelulusan === 'menunggu' && <Callout type="info" title="Menunggu kelulusan perolehan">Pendaftaran aset ini sedang menunggu kelulusan melalui Modul Workflow.</Callout>}
            <Card
                title={<span className="flex flex-wrap items-center gap-2">{a.nama_aset} <KondisiBadge kondisi={a.status_kondisi} label={a.kondisi_nama} />{a.sintetik && <Badge>Sintetik</Badge>}</span>}
                actions={<>
                    {a.boleh.kemas_kini && <Button size="sm" variant="secondary" onClick={() => setModal('kondisi')}>Ubah kondisi</Button>}
                    {a.boleh.kemas_kini && <Button size="sm" variant="secondary" onClick={() => setModal('sunting')}>Kemas kini</Button>}
                </>}
            >
                <InfoGrid items={[
                    ['No. rujukan aset', <span key="r" className="font-mono">{a.no_rujukan_aset}</span>],
                    ['Kategori / jenis', `${a.kategori ?? '—'} / ${a.jenis_nama}`],
                    ['Lot', a.lot ? <Link key="l" href={a.pautan_lot} className="text-txt-primary hover:underline">{a.lot.no_lot} (Profil 360°)</Link> : '—'],
                    ['Daerah / mukim', `${a.daerah ?? '—'} / ${a.mukim ?? '—'}`],
                    ['Alamat', a.alamat],
                    ['Koordinat GPS', a.latitud !== null ? `${a.latitud.toFixed(6)}, ${a.longitud?.toFixed(6)}` : '—'],
                    ['Tahun pembinaan / perolehan', `${a.tahun_pembinaan ?? '—'} / ${a.tahun_perolehan ?? '—'}`],
                    ['Saiz', a.saiz_keluasan ? `${a.saiz_keluasan} ${a.unit_ukuran}` : '—'],
                    ['Kos perolehan', rm(a.kos_perolehan)],
                    ['Nilai semasa', `${rm(a.nilai_semasa)} (${tarikh(a.tarikh_nilai_semasa)})`],
                    ['Nilai buku (susut nilai)', a.susut_nilai ? rm(a.susut_nilai.nilai_buku) : '—'],
                    ['Pegawai bertanggungjawab', a.pegawai?.nama],
                    ['Pemeriksaan terakhir', <span key="p">{tarikh(a.tarikh_pemeriksaan_terakhir)} {a.pemeriksaan_lewat && <Badge variant="danger">Melebihi tempoh</Badge>}</span>],
                    ['Status', a.status_nama],
                    ['Aset induk', a.induk ? <Link key="i" href={`/admin/ast/aset/${a.induk.id}`} className="text-txt-primary hover:underline">{a.induk.nama_aset}</Link> : '—'],
                    ['Premis sewaan (MOD-PJK)', a.premis_pjk ? <Link key="pr" href={a.premis_pjk.pautan} className="text-txt-primary hover:underline">{a.premis_pjk.nama_premis} ({a.premis_pjk.kod_premis})</Link> : '—'],
                    ['Tag', a.tag.length ? <span key="t" className="flex flex-wrap gap-1">{a.tag.map((t) => <Badge key={t.nama} variant={t.jenis === 'berstruktur' ? 'info' : 'neutral'}>{t.nama}</Badge>)}</span> : '—'],
                ]} />
                {a.keterangan && <p className="text-body-sm text-txt-black-700">{a.keterangan}</p>}
                {a.subaset.length > 0 && (
                    <div className="space-y-1">
                        <p className="text-body-sm font-medium">Subaset ({a.subaset.length})</p>
                        <ul className="flex flex-wrap gap-2">{a.subaset.map((s) => <li key={s.id}><Link href={`/admin/ast/aset/${s.id}`} className="text-body-sm text-txt-primary hover:underline">{s.nama_aset}</Link> <KondisiBadge kondisi={s.status_kondisi} /></li>)}</ul>
                    </div>
                )}
            </Card>
            {can('ast.peta.lihat') && a.lot && (
                <Card title="Lokasi aset">
                    <PetaSisdata layers={['gis.lot', 'ast.aset_fizikal']} focus={{ lotId: a.lot_id }} height={320} label={`Lokasi ${a.nama_aset}`} />
                </Card>
            )}
            <Card>
                <Tabs tabs={tabs}>
                    <TabPanel value="gambar"><PanelGambar a={a} rj={ref} /></TabPanel>
                    <TabPanel value="pemeriksaan"><PanelPemeriksaan a={a} rj={ref} /></TabPanel>
                    <TabPanel value="penyelenggaraan"><PanelPenyelenggaraan a={a} rj={ref} /></TabPanel>
                    <TabPanel value="nilai"><PanelNilai a={a} rj={ref} /></TabPanel>
                    <TabPanel value="dokumen"><PanelDokumen a={a} rj={ref} /></TabPanel>
                    <TabPanel value="sejarah"><PanelSejarah a={a} rj={ref} /></TabPanel>
                    <TabPanel value="qr"><PanelQr a={a} rj={ref} /></TabPanel>
                    <TabPanel value="pelupusan"><PanelPelupusan a={a} rj={ref} /></TabPanel>
                </Tabs>
            </Card>
            <Modal open={modal !== null} onClose={() => setModal(null)}>
                {modal === 'kondisi' && <UbahKondisi a={a} onClose={() => setModal(null)} />}
                {modal === 'sunting' && <Sunting a={a} onClose={() => setModal(null)} />}
            </Modal>
        </PageContainer>
    );
}
