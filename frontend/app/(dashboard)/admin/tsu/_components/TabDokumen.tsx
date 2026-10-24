'use client';

import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Table } from '@/components/ui/Table';
import { ralat, tarikh, tsuApi, useCan, useRujukan, type UrusanButiran } from '@/lib/tsu';
import { Field, inputClass, Kosong, Select } from './common';

/** SRS-TSU-25: documents of each stage, stored in MOD-DMS with category, document date and expiry. */
export function TabDokumen({ u, boleh, onChange }: { u: UrusanButiran; boleh: boolean; onChange: () => void }) {
    const can = useCan();
    const { data: ref } = useRujukan();
    const [fail, setFail] = useState<File | null>(null);
    const [f, setF] = useState({ kategori_dokumen: u.dokumen_belum_lengkap.length ? '' : 'lain', peringkat_id: '', tarikh_dokumen: '', tarikh_luput: '', nilai_penilaian: '' });
    const set = (k: keyof typeof f) => (v: string) => setF((x) => ({ ...x, [k]: v }));
    const m = useMutation({
        mutationFn: () => {
            const fd = new FormData();
            if (fail) fd.append('fail', fail);
            Object.entries(f).forEach(([k, v]) => v && fd.append(k, v));
            return tsuApi.muatNaik(u.id, fd);
        },
        onSuccess: (r) => {
            toast.success(r.message);
            setFail(null);
            onChange();
        },
        onError: (e) => toast.error(ralat(e)),
    });
    const buka = useMutation({ mutationFn: (dok: number) => tsuApi.urlDokumen(u.id, dok), onSuccess: (url) => window.open(url, '_blank', 'noopener'), onError: (e) => toast.error(ralat(e)) });
    const penilaian = f.kategori_dokumen === 'laporan_penilaian';

    return (
        <div className="space-y-4 pt-4">
            {boleh && (
                <form
                    className="rounded-md border border-otl-gray-200 p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        m.mutate();
                    }}
                >
                    <Field label="Fail" htmlFor="dk-fail" className="sm:col-span-2 lg:col-span-3">
                        <input id="dk-fail" type="file" className="block w-full text-body-sm" onChange={(e) => setFail(e.target.files?.[0] ?? null)} required />
                    </Field>
                    <Field label="Kategori" htmlFor="dk-kat">
                        <Select id="dk-kat" value={f.kategori_dokumen} onChange={set('kategori_dokumen')} options={(ref?.kategori_dokumen ?? []).filter((k) => k.kod !== 'laporan_penilaian' || can('tsu.rahsia.kemas_kini'))} empty="Pilih" required />
                    </Field>
                    <Field label="Peringkat" htmlFor="dk-per">
                        <select id="dk-per" className={inputClass} value={f.peringkat_id} onChange={(e) => set('peringkat_id')(e.target.value)}>
                            <option value="">Peringkat semasa</option>
                            {u.peringkat.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.no_urutan}. {p.nama_peringkat}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Tarikh dokumen" htmlFor="dk-tarikh">
                        <input id="dk-tarikh" type="date" className={inputClass} value={f.tarikh_dokumen} onChange={(e) => set('tarikh_dokumen')(e.target.value)} required={penilaian} />
                    </Field>
                    <Field label="Tarikh luput (jika ada)" htmlFor="dk-luput">
                        <input id="dk-luput" type="date" className={inputClass} value={f.tarikh_luput} onChange={(e) => set('tarikh_luput')(e.target.value)} />
                    </Field>
                    {penilaian && (
                        <Field label="Nilai penilaian JPPH (RM) — Rahsia" htmlFor="dk-nilai">
                            <input id="dk-nilai" inputMode="decimal" pattern="^\d{1,14}(\.\d{1,2})?$" className={inputClass} value={f.nilai_penilaian} onChange={(e) => set('nilai_penilaian')(e.target.value)} required />
                        </Field>
                    )}
                    <div className="flex items-end">
                        <Button type="submit" disabled={m.isPending || !fail}>Muat naik ke DMS</Button>
                    </div>
                </form>
            )}
            {u.dokumen_belum_lengkap.length > 0 && <p className="text-body-sm text-txt-warning">Dokumen wajib peringkat semasa belum lengkap: {u.dokumen_belum_lengkap.join(', ')}</p>}
            {u.dokumen.length === 0 ? (
                <Kosong>Tiada dokumen.</Kosong>
            ) : (
                <Table head={<><th>Kategori</th><th>Fail</th><th>Peringkat</th><th>Tarikh dokumen</th><th>Luput</th><th>Dimuat naik</th><th></th></>}>
                    {u.dokumen.map((d) => (
                        <tr key={d.id}>
                            <td>{d.kategori_nama}</td>
                            <td className="break-all">{d.nama_fail ?? '—'}</td>
                            <td>{u.peringkat.find((p) => p.id === d.peringkat_id)?.nama_peringkat ?? '—'}</td>
                            <td>{tarikh(d.tarikh_dokumen)}</td>
                            <td>{d.tarikh_luput ? <>{tarikh(d.tarikh_luput)} {!d.sah && <Badge variant="danger">Luput</Badge>}</> : '—'}</td>
                            <td>{tarikh(d.tarikh_muat_naik)} · {d.pemuat_naik ?? '—'}</td>
                            <td>
                                <Button size="sm" variant="secondary" onClick={() => buka.mutate(d.id)}>Buka</Button>
                            </td>
                        </tr>
                    ))}
                </Table>
            )}
        </div>
    );
}
