'use client';

import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Table } from '@/components/ui/Table';
import { masa, ralat, tarikh, tsuApi, useCan, useRujukan, type UrusanButiran } from '@/lib/tsu';
import { Field, inputClass, Kosong, Select } from './common';

/** SRS-TSU-22: Land Office status — central API for covered districts, structured manual update with follow-up elsewhere. */
export function TabPtd({ u, onChange }: { u: UrusanButiran; onChange: () => void }) {
    const can = useCan();
    const { data: ref } = useRujukan();
    const boleh = can('tsu.ptd.kemas_kini') && !u.dikunci && ['aktif', 'menunggu_kelulusan'].includes(u.status);
    const [f, setF] = useState({ status_ptd: '', tarikh_status: new Date().toISOString().slice(0, 10), rujukan_ptd: u.no_rujukan_ptd ?? '', catatan: '', tarikh_susulan: '' });
    const ok = (r: { message: string }) => {
        toast.success(r.message);
        onChange();
    };
    const mHantar = useMutation({ mutationFn: () => tsuApi.ptdHantar(u.id), onSuccess: ok, onError: (e) => toast.error(ralat(e)) });
    const mSegerak = useMutation({ mutationFn: () => tsuApi.ptdSegerak(u.id), onSuccess: ok, onError: (e) => toast.error(ralat(e)) });
    const mManual = useMutation({ mutationFn: () => tsuApi.ptdManual(u.id, Object.fromEntries(Object.entries(f).filter(([, v]) => v !== ''))), onSuccess: ok, onError: (e) => toast.error(ralat(e)) });

    return (
        <div className="space-y-4 pt-4">
            <div className="flex flex-wrap items-center gap-3 text-body-sm">
                {u.ptd.liputan_api ? <Badge variant="success">Daerah diliputi API berpusat</Badge> : <Badge variant="warning">Di luar liputan API — kemas kini manual</Badge>}
                <span>Status terkini: <strong>{u.status_ptd_nama ?? 'belum dihantar'}</strong> {u.ptd.sumber && `(${u.ptd.sumber === 'api' ? 'API' : 'manual'}, ${masa(u.ptd.tarikh_status)})`}</span>
                {u.ptd.tarikh_susulan && <span>Susulan seterusnya: <strong>{tarikh(u.ptd.tarikh_susulan)}</strong></span>}
            </div>
            {boleh && u.ptd.liputan_api && (
                <div className="flex flex-wrap gap-2">
                    {!u.no_rujukan_ptd && <Button size="sm" onClick={() => mHantar.mutate()} disabled={mHantar.isPending}>Hantar ke Pejabat Tanah (API)</Button>}
                    {u.no_rujukan_ptd && <Button size="sm" variant="secondary" onClick={() => mSegerak.mutate()} disabled={mSegerak.isPending}>Semak status sekarang</Button>}
                </div>
            )}
            {boleh && (
                <form
                    className="rounded-md border border-otl-gray-200 p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        mManual.mutate();
                    }}
                >
                    <p className="sm:col-span-2 lg:col-span-3 text-body-sm text-txt-black-500">Kemas kini manual berstruktur (luar liputan atau semasa API terganggu). Peringatan susulan dijana secara automatik.</p>
                    <Field label="Status" htmlFor="p-st"><Select id="p-st" value={f.status_ptd} onChange={(v) => setF((x) => ({ ...x, status_ptd: v }))} options={ref?.status_ptd ?? []} empty="Pilih" required /></Field>
                    <Field label="Tarikh status" htmlFor="p-tr"><input id="p-tr" type="date" className={inputClass} value={f.tarikh_status} onChange={(e) => setF((x) => ({ ...x, tarikh_status: e.target.value }))} required /></Field>
                    <Field label="No. rujukan PTD" htmlFor="p-ref"><input id="p-ref" className={inputClass} value={f.rujukan_ptd} onChange={(e) => setF((x) => ({ ...x, rujukan_ptd: e.target.value }))} /></Field>
                    <Field label="Tarikh susulan" htmlFor="p-sus" help="Kosongkan untuk lalai sistem."><input id="p-sus" type="date" className={inputClass} value={f.tarikh_susulan} onChange={(e) => setF((x) => ({ ...x, tarikh_susulan: e.target.value }))} /></Field>
                    <Field label="Catatan" htmlFor="p-cat" className="sm:col-span-2"><input id="p-cat" className={inputClass} value={f.catatan} onChange={(e) => setF((x) => ({ ...x, catatan: e.target.value }))} /></Field>
                    <div className="flex items-end"><Button type="submit" disabled={mManual.isPending}>Rekod status</Button></div>
                </form>
            )}
            {u.ptd.sejarah.length === 0 ? (
                <Kosong>Tiada sejarah status Pejabat Tanah.</Kosong>
            ) : (
                <Table head={<><th>Tarikh</th><th>Status</th><th>Sumber</th><th>Rujukan</th><th>Catatan</th><th>Susulan</th></>}>
                    {u.ptd.sejarah.map((s) => (
                        <tr key={s.id}>
                            <td>{tarikh(s.tarikh_status)}</td>
                            <td>{s.status_nama}</td>
                            <td><Badge variant={s.sumber === 'api' ? 'info' : 'neutral'}>{s.sumber === 'api' ? 'API' : `Manual (${s.pengguna ?? '—'})`}</Badge></td>
                            <td>{s.rujukan_ptd ?? '—'}</td>
                            <td>{s.catatan ?? '—'}</td>
                            <td>{tarikh(s.tarikh_susulan)}</td>
                        </tr>
                    ))}
                </Table>
            )}
        </div>
    );
}
