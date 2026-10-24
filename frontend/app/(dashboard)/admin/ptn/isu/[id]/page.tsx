'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { DocumentAttachments } from '@/components/dms/DocumentAttachments';
import { ptnApi, ralat, tarikh, useCan, type Tindakan } from '@/lib/ptn';
import { Card, Field, InfoGrid, inputClass, KodWarnaBadge, Kosong, ModalBody, Select, Tahap } from '../../_components/common';

/** SRS-PTN-13/18/19: issue detail — corrective actions until verified, escalation to MOD-ADU / MOD-KES. */
export default function IsuDetail() {
    const { id } = useParams<{ id: string }>();
    const qc = useQueryClient();
    const can = useCan();
    const { data: d, error } = useQuery({ queryKey: ['ptn', 'isu', id], queryFn: () => ptnApi.isuButiran(id) });
    const refresh = () => qc.invalidateQueries({ queryKey: ['ptn', 'isu', id] });
    const [baru, setBaru] = useState<{ langkah_tindakan: string; id_pegawai: string; q: string; tarikh_sasaran: string } | null>(null);
    const [ubah, setUbah] = useState<(Tindakan & { justifikasi: string }) | null>(null);
    const { data: pegawai } = useQuery({ queryKey: ['ptn', 'pegawai', baru?.q], queryFn: () => ptnApi.cariPegawai(baru?.q ?? ''), enabled: !!baru });
    const mBaru = useMutation({ mutationFn: () => ptnApi.tambahTindakan(id, { langkah_tindakan: baru?.langkah_tindakan, id_pegawai_bertanggungjawab: Number(baru?.id_pegawai), tarikh_sasaran: baru?.tarikh_sasaran }), onSuccess: () => { setBaru(null); refresh(); }, onError: (e) => toast.error(ralat(e)) });
    const mUbah = useMutation({ mutationFn: () => ptnApi.kemaskiniTindakan(ubah!.id, { peratus_kemajuan: ubah!.peratus_kemajuan, catatan_kemajuan: ubah!.catatan_kemajuan, status_kemajuan: ubah!.peratus_kemajuan > 0 ? 'dalam_tindakan' : 'belum_mula', tarikh_sasaran: ubah!.tarikh_sasaran, justifikasi_lanjutan: ubah!.justifikasi || undefined }), onSuccess: () => { setUbah(null); refresh(); }, onError: (e) => toast.error(ralat(e)) });
    const mSelesai = useMutation({ mutationFn: (t: Tindakan) => ptnApi.selesaiTindakan(t.id, window.prompt('Catatan penyelesaian:') ?? undefined), onSuccess: () => { toast.success('Dihantar untuk pengesahan penyelesaian.'); refresh(); }, onError: (e) => toast.error(ralat(e)) });
    const mAduan = useMutation({ mutationFn: () => ptnApi.eskalasiAduan(id), onSuccess: (r) => { toast.success(r.message); refresh(); }, onError: (e) => toast.error(ralat(e)) });
    const mKes = useMutation({ mutationFn: () => ptnApi.eskalasiKes(id), onSuccess: () => refresh(), onError: (e) => toast.error(ralat(e)) });

    if (error) return <PageContainer><p className="text-body-sm text-txt-danger">{ralat(error)}</p></PageContainer>;
    if (!d) return <PageContainer><Kosong>Memuatkan…</Kosong></PageContainer>;
    const terbuka = ['terbuka', 'dalam_tindakan'].includes(d.status_isu);

    return (
        <PageContainer>
            <Card title={`${d.no_rujukan} — ${d.kategori_nama}`} actions={can('ptn.isu.kemas_kini') && (
                <>
                    {d.id_aduan_berkaitan ? <Link className="text-body-sm text-txt-primary hover:underline" href={`/admin/adu/aduan/${d.id_aduan_berkaitan}`}>Lihat aduan berkaitan</Link> : <Button size="sm" variant="outline" disabled={mAduan.isPending} onClick={() => mAduan.mutate()}>Eskalasi ke Modul Aduan</Button>}
                    {!d.id_kes_berkaitan && <Button size="sm" variant="secondary" disabled={mKes.isPending} onClick={() => mKes.mutate()}>Eskalasi ke Kes Mahkamah</Button>}
                </>
            )}>
                <InfoGrid items={[
                    ['Lot', d.lot ? <Link className="text-txt-primary hover:underline" href={`/admin/ptn/lot/${d.lot_id}`}>{d.lot.no_lot}</Link> : '—'],
                    ['Daerah / Mukim', `${d.daerah ?? '—'} / ${d.mukim ?? '—'}`],
                    ['Tahap keterukan', <Tahap key="t" tahap={d.tahap_keterukan} label={d.tahap_nama} />],
                    ['Status', d.status_nama],
                    ['Dibuka / ditutup', `${tarikh(d.tarikh_dibuka)} / ${tarikh(d.tarikh_ditutup)}`],
                    ['Lawatan', d.lawatan ? <Link className="text-txt-primary hover:underline" href={`/admin/ptn/lawatan/${d.lawatan.id}`}>{d.lawatan.no_rujukan}</Link> : 'Direkod manual'],
                ]} />
                <p className="text-body-sm text-txt-black-700">{d.keterangan}</p>
            </Card>

            <Card title="Tindakan pembetulan" actions={can('ptn.isu.kemas_kini') && terbuka && <Button size="sm" onClick={() => setBaru({ langkah_tindakan: '', id_pegawai: '', q: '', tarikh_sasaran: '' })}>Tambah tindakan</Button>}>
                {d.tindakan.length === 0 ? <Kosong>Belum ada tindakan.</Kosong> : (
                    <Table head={<><th>Langkah</th><th>Pegawai</th><th>Tarikh sasaran</th><th>Kemajuan</th><th>Status</th><th>Kod warna</th><th></th></>}>
                        {d.tindakan.map((t) => (
                            <tr key={t.id}>
                                <td className="min-w-[14rem]">{t.langkah_tindakan}{t.justifikasi_lanjutan && <p className="text-body-xs text-txt-black-500 whitespace-pre-line">Lanjutan: {t.justifikasi_lanjutan}</p>}</td>
                                <td>{t.pegawai?.nama ?? '—'}</td>
                                <td className="whitespace-nowrap">{tarikh(t.tarikh_sasaran)}{t.tarikh_sasaran !== t.tarikh_sasaran_asal && <p className="text-body-xs text-txt-black-500">asal {tarikh(t.tarikh_sasaran_asal)}</p>}</td>
                                <td>{t.peratus_kemajuan}%</td>
                                <td>{t.status_nama}</td>
                                <td><KodWarnaBadge warna={t.kod_warna} label={t.kod_warna === 'merah' ? `Lewat ${-t.hari_berbaki} hari` : t.kod_warna === 'kuning' ? `${t.hari_berbaki} hari lagi` : undefined} /></td>
                                <td className="whitespace-nowrap space-x-2">
                                    {t.boleh_urus && ['belum_mula', 'dalam_tindakan'].includes(t.status_kemajuan) && (
                                        <>
                                            <Button size="sm" variant="secondary" onClick={() => setUbah({ ...t, justifikasi: '' })}>Kemas kini</Button>
                                            <Button size="sm" onClick={() => mSelesai.mutate(t)}>Selesai</Button>
                                        </>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </Table>
                )}
                {d.tindakan.map((t) => (
                    <details key={t.id} className="text-body-sm">
                        <summary className="cursor-pointer text-txt-primary">Bukti tindakan: {t.langkah_tindakan.slice(0, 40)}</summary>
                        <DocumentAttachments ownerType="tindakan_pembetulan" ownerId={String(t.id)} title="Bukti / gambar selepas tindakan" defaultCategory="gambar_tapak" sourceModule="ptn" ownerLink={`/admin/ptn/isu/${d.id}`} readOnly={!t.boleh_urus} />
                    </details>
                ))}
            </Card>

            <Modal open={!!baru} onClose={() => setBaru(null)}>
                <ModalBody title="Tambah tindakan pembetulan" footer={<><Button variant="secondary" onClick={() => setBaru(null)}>Batal</Button><Button disabled={!baru?.langkah_tindakan || !baru.id_pegawai || !baru.tarikh_sasaran || mBaru.isPending} onClick={() => mBaru.mutate()}>Simpan</Button></>}>
                    <Field label="Langkah tindakan" htmlFor="t-l"><textarea id="t-l" rows={3} className={inputClass} value={baru?.langkah_tindakan ?? ''} onChange={(e) => setBaru((x) => x && { ...x, langkah_tindakan: e.target.value })} /></Field>
                    <Field label="Cari pegawai bertanggungjawab" htmlFor="t-q"><input id="t-q" className={inputClass} value={baru?.q ?? ''} onChange={(e) => setBaru((x) => x && { ...x, q: e.target.value })} /></Field>
                    <Field label="Pegawai" htmlFor="t-p"><Select id="t-p" value={baru?.id_pegawai ?? ''} onChange={(v) => setBaru((x) => x && { ...x, id_pegawai: v })} options={(pegawai ?? []).map((p) => ({ kod: String(p.id), nama: p.nama }))} empty="Pilih" /></Field>
                    <Field label="Tarikh sasaran" htmlFor="t-t"><input id="t-t" type="date" className={inputClass} value={baru?.tarikh_sasaran ?? ''} onChange={(e) => setBaru((x) => x && { ...x, tarikh_sasaran: e.target.value })} /></Field>
                </ModalBody>
            </Modal>
            <Modal open={!!ubah} onClose={() => setUbah(null)}>
                <ModalBody title="Kemas kini kemajuan" footer={<><Button variant="secondary" onClick={() => setUbah(null)}>Batal</Button><Button disabled={mUbah.isPending} onClick={() => mUbah.mutate()}>Simpan</Button></>}>
                    <Field label="Peratus kemajuan" htmlFor="u-p"><input id="u-p" type="number" min={0} max={99} className={inputClass} value={ubah?.peratus_kemajuan ?? 0} onChange={(e) => setUbah((x) => x && { ...x, peratus_kemajuan: Number(e.target.value) })} /></Field>
                    <Field label="Catatan kemajuan" htmlFor="u-c"><textarea id="u-c" rows={2} className={inputClass} value={ubah?.catatan_kemajuan ?? ''} onChange={(e) => setUbah((x) => x && { ...x, catatan_kemajuan: e.target.value })} /></Field>
                    <Field label="Tarikh sasaran" htmlFor="u-t" help="Perubahan tarikh memerlukan justifikasi lanjutan."><input id="u-t" type="date" className={inputClass} value={ubah?.tarikh_sasaran ?? ''} onChange={(e) => setUbah((x) => x && { ...x, tarikh_sasaran: e.target.value })} /></Field>
                    <Field label="Justifikasi lanjutan" htmlFor="u-j"><textarea id="u-j" rows={2} className={inputClass} value={ubah?.justifikasi ?? ''} onChange={(e) => setUbah((x) => x && { ...x, justifikasi: e.target.value })} /></Field>
                </ModalBody>
            </Modal>
        </PageContainer>
    );
}
