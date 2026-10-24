'use client';

import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Table } from '@/components/ui/Table';
import { ralat, rm, tarikh, tsuApi, useCan, useRujukan, type Kos, type UrusanButiran } from '@/lib/tsu';
import { Field, inputClass, Kosong, ModalBody, Ralat, Select, Status } from './common';

/** SRS-TSU-14: costs per stage and category, payment status and receipt; changes by Bahagian Kewangan only. */
export function TabKos({ u, onChange }: { u: UrusanButiran; onChange: () => void }) {
    const can = useCan();
    const { data: ref } = useRujukan();
    const { data, error, refetch } = useQuery({ queryKey: ['tsu', 'kos', u.id], queryFn: () => tsuApi.kos(u.id), enabled: can('tsu.kos.lihat') });
    const kewangan = can('tsu.kewangan.kemas_kini') && !u.dikunci;
    const [baru, setBaru] = useState({ kategori_kos: '', amaun: '', keterangan: '', no_invois: '', tarikh_invois: '', tarikh_akhir_bayaran: '', peringkat_id: '' });
    const [bayar, setBayar] = useState<(Kos & { _resit: string; _tarikh: string; _dok: string }) | null>(null);
    const done = () => {
        refetch();
        onChange();
    };
    const mBaru = useMutation({
        mutationFn: () => tsuApi.tambahKos(u.id, Object.fromEntries(Object.entries(baru).filter(([, v]) => v !== ''))),
        onSuccess: (r) => {
            toast.success(r.message);
            setBaru({ kategori_kos: '', amaun: '', keterangan: '', no_invois: '', tarikh_invois: '', tarikh_akhir_bayaran: '', peringkat_id: '' });
            done();
        },
        onError: (e) => toast.error(ralat(e)),
    });
    const mBayar = useMutation({
        mutationFn: () => tsuApi.kemaskiniKos(bayar!.id, { status_bayaran: 'dibayar', no_resit: bayar!._resit, tarikh_bayaran: bayar!._tarikh, id_dokumen: bayar!._dok || undefined }),
        onSuccess: (r) => {
            toast.success(r.message);
            setBayar(null);
            done();
        },
        onError: (e) => toast.error(ralat(e)),
    });

    if (!can('tsu.kos.lihat')) return <Kosong>Tiada kebenaran melihat kos urusan.</Kosong>;
    if (error) return <Ralat>{ralat(error)}</Ralat>;
    if (!data) return <Kosong>Memuatkan…</Kosong>;
    const resit = u.dokumen.filter((d) => d.kategori_dokumen === 'resit');

    return (
        <div className="space-y-4 pt-4">
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-body-sm">
                <div className="rounded-md bg-bg-washed p-3"><p className="text-txt-black-500">Jumlah terkumpul</p><p className="font-semibold text-body-lg">{rm(data.ringkasan.jumlah)}</p></div>
                <div className="rounded-md bg-bg-washed p-3"><p className="text-txt-black-500">Telah dibayar</p><p className="font-semibold text-body-lg">{rm(data.ringkasan.dibayar)}</p></div>
                <div className="rounded-md bg-bg-washed p-3"><p className="text-txt-black-500">Belum dibayar</p><p className="font-semibold text-body-lg">{rm(data.ringkasan.belum_dibayar)}</p></div>
            </div>
            {data.ringkasan.mengikut_kategori.length > 0 && (
                <ul className="flex flex-wrap gap-3 text-body-sm">
                    {data.ringkasan.mengikut_kategori.map((k) => (
                        <li key={k.kod} className="rounded-full border border-otl-gray-200 px-3 py-1">{k.nama}: <strong>{rm(k.jumlah)}</strong></li>
                    ))}
                </ul>
            )}
            {kewangan && (
                <form
                    className="rounded-md border border-otl-gray-200 p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        mBaru.mutate();
                    }}
                >
                    <Field label="Kategori kos" htmlFor="k-kat">
                        <Select id="k-kat" value={baru.kategori_kos} onChange={(v) => setBaru((x) => ({ ...x, kategori_kos: v }))} options={ref?.kategori_kos ?? []} empty="Pilih" required />
                    </Field>
                    <Field label="Amaun (RM)" htmlFor="k-amaun">
                        <input id="k-amaun" inputMode="decimal" pattern="^\d{1,12}(\.\d{1,2})?$" className={inputClass} value={baru.amaun} onChange={(e) => setBaru((x) => ({ ...x, amaun: e.target.value }))} required />
                    </Field>
                    <Field label="Peringkat" htmlFor="k-per">
                        <select id="k-per" className={inputClass} value={baru.peringkat_id} onChange={(e) => setBaru((x) => ({ ...x, peringkat_id: e.target.value }))}>
                            <option value="">Ikut kategori</option>
                            {u.peringkat.map((p) => <option key={p.id} value={p.id}>{p.no_urutan}. {p.nama_peringkat}</option>)}
                        </select>
                    </Field>
                    <Field label="No. invois / notis" htmlFor="k-inv">
                        <input id="k-inv" className={inputClass} value={baru.no_invois} onChange={(e) => setBaru((x) => ({ ...x, no_invois: e.target.value }))} />
                    </Field>
                    <Field label="Tarikh invois" htmlFor="k-ti">
                        <input id="k-ti" type="date" className={inputClass} value={baru.tarikh_invois} onChange={(e) => setBaru((x) => ({ ...x, tarikh_invois: e.target.value }))} />
                    </Field>
                    <Field label="Tarikh akhir bayaran" htmlFor="k-ta">
                        <input id="k-ta" type="date" className={inputClass} value={baru.tarikh_akhir_bayaran} onChange={(e) => setBaru((x) => ({ ...x, tarikh_akhir_bayaran: e.target.value }))} />
                    </Field>
                    <Field label="Keterangan" htmlFor="k-ket">
                        <input id="k-ket" className={inputClass} value={baru.keterangan} onChange={(e) => setBaru((x) => ({ ...x, keterangan: e.target.value }))} />
                    </Field>
                    <div className="flex items-end"><Button type="submit" disabled={mBaru.isPending}>Rekod kos</Button></div>
                </form>
            )}
            {data.item.length === 0 ? (
                <Kosong>Tiada kos direkodkan.</Kosong>
            ) : (
                <Table head={<><th>Kategori</th><th>Peringkat</th><th>Amaun</th><th>Invois</th><th>Tarikh akhir</th><th>Status</th><th>Resit</th>{kewangan && <th></th>}</>}>
                    {data.item.map((k) => (
                        <tr key={k.id}>
                            <td>{k.kategori_nama}<div className="text-body-xs text-txt-black-500">{k.keterangan}</div></td>
                            <td>{u.peringkat.find((p) => p.id === k.peringkat_id)?.nama_peringkat ?? '—'}</td>
                            <td className="whitespace-nowrap">{rm(k.amaun)}</td>
                            <td>{k.no_invois ?? '—'}</td>
                            <td>{tarikh(k.tarikh_akhir_bayaran)}</td>
                            <td><Status kod={k.status_bayaran} nama={k.status_bayaran_nama} /></td>
                            <td>{k.no_resit ? `${k.no_resit} (${tarikh(k.tarikh_bayaran)})` : '—'}</td>
                            {kewangan && <td>{k.status_bayaran !== 'dibayar' && <Button size="sm" variant="secondary" onClick={() => setBayar({ ...k, _resit: '', _tarikh: new Date().toISOString().slice(0, 10), _dok: '' })}>Rekod bayaran</Button>}</td>}
                        </tr>
                    ))}
                </Table>
            )}
            <Modal open={!!bayar} onClose={() => setBayar(null)}>
                {bayar && (
                    <ModalBody title={`Rekod bayaran — ${bayar.kategori_nama} ${rm(bayar.amaun)}`} footer={<><Button variant="secondary" onClick={() => setBayar(null)}>Batal</Button><Button onClick={() => mBayar.mutate()} disabled={mBayar.isPending || !bayar._resit}>Simpan</Button></>}>
                        <Field label="No. resit" htmlFor="b-resit"><input id="b-resit" className={inputClass} value={bayar._resit} onChange={(e) => setBayar({ ...bayar, _resit: e.target.value })} /></Field>
                        <Field label="Tarikh bayaran" htmlFor="b-tarikh"><input id="b-tarikh" type="date" className={inputClass} value={bayar._tarikh} onChange={(e) => setBayar({ ...bayar, _tarikh: e.target.value })} /></Field>
                        <Field label="Pautan resit (dokumen urusan)" htmlFor="b-dok">
                            <select id="b-dok" className={inputClass} value={bayar._dok} onChange={(e) => setBayar({ ...bayar, _dok: e.target.value })}>
                                <option value="">Tiada</option>
                                {resit.map((d) => <option key={d.id} value={d.id_dokumen_dms}>{d.nama_fail}</option>)}
                            </select>
                        </Field>
                    </ModalBody>
                )}
            </Modal>
        </div>
    );
}
