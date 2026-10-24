'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { ptnApi, ralat, tarikh, useCan, useRujukan, type Ambang, type ItemTemplat, type Templat } from '@/lib/ptn';
import { Card, Field, inputClass, Kosong, Legenda, ModalBody, Select } from '../_components/common';

/** SRS-PTN-02/04/11/15: Pentadbir Sistem configuration — thresholds & weights, visit frequencies, checklist templates. */
export default function Konfigurasi() {
    const qc = useQueryClient();
    const can = useCan();
    const boleh = can('ptn.konfigurasi.kemas_kini');
    const { data: ref } = useRujukan();
    const { data, error } = useQuery({ queryKey: ['ptn', 'konfigurasi'], queryFn: ptnApi.konfigurasi });
    const refresh = () => qc.invalidateQueries({ queryKey: ['ptn'] });
    const [ambang, setAmbang] = useState<Ambang | null>(null);
    const [kek, setKek] = useState<{ kategori_lot: string; tahap_risiko: string; kekerapan_setahun: number } | null>(null);
    const [templat, setTemplat] = useState<{ id?: number; kod: string; nama: string; kategori_lot: string; jenis_pemeriksaan: string; senarai_item: ItemTemplat[] } | null>(null);

    const mAmbang = useMutation({ mutationFn: () => ptnApi.simpanAmbang({ kategori_risiko: ambang?.kategori_risiko, ambang_kuning: ambang?.ambang_kuning ?? null, ambang_merah: ambang?.ambang_merah ?? null, tempoh_kelewatan_kuning: ambang?.tempoh_kelewatan_kuning ?? null, tempoh_kelewatan_merah: ambang?.tempoh_kelewatan_merah ?? null, pemberat: ambang?.pemberat ?? null }), onSuccess: (r) => { toast.success(r.message); setAmbang(null); refresh(); }, onError: (e) => toast.error(ralat(e)) });
    const mKek = useMutation({ mutationFn: () => ptnApi.simpanKekerapan([kek]), onSuccess: () => { toast.success('Kekerapan disimpan.'); setKek(null); refresh(); }, onError: (e) => toast.error(ralat(e)) });
    const mTemplat = useMutation({
        mutationFn: () => {
            const body = { nama: templat?.nama, kategori_lot: templat?.kategori_lot || null, jenis_pemeriksaan: templat?.jenis_pemeriksaan, senarai_item: templat?.senarai_item };
            return templat?.id ? ptnApi.versiTemplat(templat.id, body) : ptnApi.ciptaTemplat({ ...body, kod: templat?.kod });
        },
        onSuccess: () => { toast.success('Templat disimpan (versi baharu).'); setTemplat(null); refresh(); },
        onError: (e) => toast.error(ralat(e)),
    });
    const mStatus = useMutation({ mutationFn: (t: Templat) => ptnApi.statusTemplat(t.id, !t.status_aktif), onSuccess: refresh });
    const num = (v: string) => (v === '' ? null : Number(v));
    const markah = (a: Ambang) => ['tindakan', 'lawatan'].includes(a.kategori_risiko);
    const setItem = (i: number, k: keyof ItemTemplat, v: string) => setTemplat((t) => t && { ...t, senarai_item: t.senarai_item.map((x, j) => (j === i ? { ...x, [k]: v } : x)) });

    if (error) return <PageContainer><p className="text-body-sm text-txt-danger">{ralat(error)}</p></PageContainer>;

    return (
        <PageContainer>
            <Card title="Ambang kod warna dan pemberat risiko" actions={<Legenda />}>
                <p className="text-body-xs text-txt-black-500">Perubahan berkuat kuasa serta-merta pada senarai lot, kad rekod, peta GIS dan dashboard. Sejarah setiap perubahan disimpan dan diaudit.</p>
                <Table head={<><th>Kategori</th><th>Kuning bermula (skor)</th><th>Merah bermula (skor)</th><th>Kuning: hari sebelum tarikh akhir</th><th>Merah: hari selepas tarikh akhir</th><th>Pemberat</th><th>Berkuat kuasa</th><th></th></>}>
                    {(data?.ambang ?? []).map((a) => (
                        <tr key={a.kategori_risiko}>
                            <td>{a.label}</td>
                            <td>{markah(a) ? '—' : a.ambang_kuning ?? '—'}</td>
                            <td>{markah(a) ? '—' : a.ambang_merah ?? '—'}</td>
                            <td>{markah(a) ? a.tempoh_kelewatan_kuning ?? '—' : '—'}</td>
                            <td>{markah(a) ? a.tempoh_kelewatan_merah ?? '—' : '—'}</td>
                            <td>{a.pemberat ?? '—'}{data?.sumber_isyarat[a.kategori_risiko] && data.sumber_isyarat[a.kategori_risiko] !== 'terbina' && <span className="ml-1"><Badge variant="info">Modul</Badge></span>}</td>
                            <td>{tarikh(a.tarikh_berkuat_kuasa)}</td>
                            <td>{boleh && <Button size="sm" variant="secondary" onClick={() => setAmbang(a)}>Ubah</Button>}</td>
                        </tr>
                    ))}
                </Table>
            </Card>

            <Card title="Kekerapan lawatan mengikut kategori lot dan tahap risiko" actions={boleh && <Button size="sm" onClick={() => setKek({ kategori_lot: '*', tahap_risiko: 'rendah', kekerapan_setahun: 1 })}>Tambah / ubah</Button>}>
                <Table head={<><th>Kategori lot</th><th>Tahap risiko</th><th>Lawatan setahun</th><th>Aktif</th><th></th></>}>
                    {(data?.kekerapan ?? []).map((k) => (
                        <tr key={k.id}><td>{k.kategori_lot === '*' ? 'Semua (lalai)' : k.kategori_lot}</td><td>{k.tahap_risiko}</td><td>{k.kekerapan_setahun}</td><td>{k.aktif ? 'Ya' : 'Tidak'}</td><td>{boleh && <Button size="sm" variant="secondary" onClick={() => setKek(k)}>Ubah</Button>}</td></tr>
                    ))}
                </Table>
            </Card>

            <Card title="Templat senarai semak" actions={boleh && <Button size="sm" onClick={() => setTemplat({ kod: '', nama: '', kategori_lot: '', jenis_pemeriksaan: 'rutin', senarai_item: (data?.templat[0]?.senarai_item ?? []).map((x) => ({ ...x })) })}>Templat baharu</Button>}>
                {(data?.templat ?? []).length === 0 ? <Kosong>Tiada templat.</Kosong> : (
                    <Table head={<><th>Kod</th><th>Nama</th><th>Kategori lot</th><th>Jenis</th><th>Versi</th><th>Item</th><th>Status</th><th></th></>}>
                        {(data?.templat ?? []).map((t) => (
                            <tr key={t.id}>
                                <td>{t.kod}</td><td>{t.nama}</td><td>{t.kategori_lot ?? 'Semua'}</td><td>{t.jenis_pemeriksaan}</td><td>v{t.versi}</td><td>{t.bil_item}</td>
                                <td>{t.status_aktif ? <Badge variant="success">Aktif</Badge> : <Badge>Tidak aktif</Badge>}</td>
                                <td className="whitespace-nowrap space-x-2">{boleh && <><Button size="sm" variant="secondary" onClick={() => setTemplat({ id: t.id, kod: t.kod, nama: t.nama, kategori_lot: t.kategori_lot ?? '', jenis_pemeriksaan: t.jenis_pemeriksaan ?? 'rutin', senarai_item: t.senarai_item.map((x) => ({ ...x })) })}>Versi baharu</Button><Button size="sm" variant="secondary" onClick={() => mStatus.mutate(t)}>{t.status_aktif ? 'Nyahaktif' : 'Aktifkan'}</Button></>}</td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>

            <Modal open={!!ambang} onClose={() => setAmbang(null)}>
                <ModalBody title={`Ambang — ${ambang?.label ?? ''}`} footer={<><Button variant="secondary" onClick={() => setAmbang(null)}>Batal</Button><Button disabled={mAmbang.isPending} onClick={() => mAmbang.mutate()}>Simpan</Button></>}>
                    {ambang && !markah(ambang) && (
                        <>
                            <Field label="Kuning bermula pada skor" htmlFor="a-k"><input id="a-k" type="number" className={inputClass} value={ambang.ambang_kuning ?? ''} onChange={(e) => setAmbang({ ...ambang, ambang_kuning: num(e.target.value) })} /></Field>
                            <Field label="Merah bermula pada skor" htmlFor="a-m"><input id="a-m" type="number" className={inputClass} value={ambang.ambang_merah ?? ''} onChange={(e) => setAmbang({ ...ambang, ambang_merah: num(e.target.value) })} /></Field>
                        </>
                    )}
                    {ambang && markah(ambang) && (
                        <>
                            <Field label="Kuning: hari sebelum tarikh akhir" htmlFor="a-tk"><input id="a-tk" type="number" className={inputClass} value={ambang.tempoh_kelewatan_kuning ?? ''} onChange={(e) => setAmbang({ ...ambang, tempoh_kelewatan_kuning: num(e.target.value) })} /></Field>
                            <Field label="Merah: hari selepas tarikh akhir" htmlFor="a-tm"><input id="a-tm" type="number" className={inputClass} value={ambang.tempoh_kelewatan_merah ?? ''} onChange={(e) => setAmbang({ ...ambang, tempoh_kelewatan_merah: num(e.target.value) })} /></Field>
                        </>
                    )}
                    {ambang && !markah(ambang) && ambang.kategori_risiko !== 'keseluruhan' && (
                        <Field label="Pemberat dalam skor keseluruhan" htmlFor="a-p"><input id="a-p" type="number" className={inputClass} value={ambang.pemberat ?? ''} onChange={(e) => setAmbang({ ...ambang, pemberat: num(e.target.value) })} /></Field>
                    )}
                </ModalBody>
            </Modal>

            <Modal open={!!kek} onClose={() => setKek(null)}>
                <ModalBody title="Kekerapan lawatan" footer={<><Button variant="secondary" onClick={() => setKek(null)}>Batal</Button><Button disabled={mKek.isPending} onClick={() => mKek.mutate()}>Simpan</Button></>}>
                    <Field label="Kategori lot" htmlFor="k-k"><Select id="k-k" value={kek?.kategori_lot ?? '*'} onChange={(v) => setKek((x) => x && { ...x, kategori_lot: v })} options={[{ kod: '*', nama: 'Semua (lalai)' }, ...(ref?.kategori_guna_tanah ?? [])]} /></Field>
                    <Field label="Tahap risiko" htmlFor="k-t"><Select id="k-t" value={kek?.tahap_risiko ?? ''} onChange={(v) => setKek((x) => x && { ...x, tahap_risiko: v })} options={ref?.tahap_risiko ?? []} /></Field>
                    <Field label="Lawatan setahun" htmlFor="k-n"><input id="k-n" type="number" min={1} max={52} className={inputClass} value={kek?.kekerapan_setahun ?? 1} onChange={(e) => setKek((x) => x && { ...x, kekerapan_setahun: Number(e.target.value) })} /></Field>
                </ModalBody>
            </Modal>

            <Modal open={!!templat} onClose={() => setTemplat(null)}>
                <ModalBody title={templat?.id ? `Versi baharu — ${templat.kod}` : 'Templat senarai semak baharu'} footer={<><Button variant="secondary" onClick={() => setTemplat(null)}>Batal</Button><Button disabled={mTemplat.isPending} onClick={() => mTemplat.mutate()}>Simpan</Button></>}>
                    {!templat?.id && <Field label="Kod (huruf besar)" htmlFor="tp-k"><input id="tp-k" className={inputClass} value={templat?.kod ?? ''} onChange={(e) => setTemplat((t) => t && { ...t, kod: e.target.value.toUpperCase() })} /></Field>}
                    <Field label="Nama" htmlFor="tp-n"><input id="tp-n" className={inputClass} value={templat?.nama ?? ''} onChange={(e) => setTemplat((t) => t && { ...t, nama: e.target.value })} /></Field>
                    <Field label="Kategori lot" htmlFor="tp-c"><Select id="tp-c" value={templat?.kategori_lot ?? ''} onChange={(v) => setTemplat((t) => t && { ...t, kategori_lot: v })} options={ref?.kategori_guna_tanah ?? []} empty="Semua kategori" /></Field>
                    <Field label="Jenis pemeriksaan" htmlFor="tp-j"><Select id="tp-j" value={templat?.jenis_pemeriksaan ?? 'rutin'} onChange={(v) => setTemplat((t) => t && { ...t, jenis_pemeriksaan: v })} options={ref?.jenis_pemeriksaan ?? []} /></Field>
                    <p className="text-body-xs text-txt-black-500">Mesti merangkumi kelima-lima kategori: keadaan fizikal, tanda pencerobohan, penggunaan semasa, keadaan aset, akses dan sempadan.</p>
                    <div className="space-y-2">
                        {(templat?.senarai_item ?? []).map((it, i) => (
                            <div key={i} className="grid grid-cols-1 sm:grid-cols-[6rem_1fr] gap-2 border border-otl-gray-200 rounded-md p-2">
                                <input aria-label="Kod item" className={inputClass} value={it.kod} onChange={(e) => setItem(i, 'kod', e.target.value)} />
                                <input aria-label="Label item" className={inputClass} value={it.label} onChange={(e) => setItem(i, 'label', e.target.value)} />
                                <select aria-label="Kategori item" className={inputClass} value={it.kategori} onChange={(e) => setItem(i, 'kategori', e.target.value)}>{(ref?.kategori_item ?? []).map((k) => <option key={k.kod} value={k.kod}>{k.nama}</option>)}</select>
                                <div className="flex gap-2">
                                    <select aria-label="Kategori isu lalai" className={inputClass} value={it.kategori_isu ?? 'lain'} onChange={(e) => setItem(i, 'kategori_isu', e.target.value)}>{(ref?.kategori_isu ?? []).map((k) => <option key={k.kod} value={k.kod}>{k.nama}</option>)}</select>
                                    <Button size="sm" variant="secondary" onClick={() => setTemplat((t) => t && { ...t, senarai_item: t.senarai_item.filter((_, j) => j !== i) })}>Buang</Button>
                                </div>
                            </div>
                        ))}
                        <Button size="sm" variant="outline" onClick={() => setTemplat((t) => t && { ...t, senarai_item: [...t.senarai_item, { kod: `IT-${t.senarai_item.length + 1}`, kategori: 'keadaan_fizikal', label: '', kategori_isu: 'lain' }] })}>Tambah item</Button>
                    </div>
                </ModalBody>
            </Modal>
        </PageContainer>
    );
}
