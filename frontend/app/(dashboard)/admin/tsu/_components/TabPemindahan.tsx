'use client';

import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Button } from '@/components/ui/Button';
import { Table } from '@/components/ui/Table';
import { masa, ralat, tsuApi, useCan, type UrusanButiran } from '@/lib/tsu';
import { Kosong, Ralat, Status } from './common';

/** SRS-TSU-20: record mapping screen — original lot records → new lots, then two-level confirmation in MOD-WFE. */
export function TabPemindahan({ u, onChange }: { u: UrusanButiran; onChange: () => void }) {
    const can = useCan();
    const { data, error, refetch } = useQuery({ queryKey: ['tsu', 'pemindahan', u.id], queryFn: () => tsuApi.pemindahan(u.id) });
    const [peta, setPeta] = useState<Record<string, string>>({});
    const done = () => {
        refetch();
        onChange();
    };
    const mSimpan = useMutation({
        mutationFn: () =>
            tsuApi.petakan(
                u.id,
                (data?.lot_asal ?? []).flatMap((l) =>
                    l.rekod.filter((r) => peta[`${r.jenis_rekod}:${r.id_rekod}`]).map((r) => ({ jenis_rekod: r.jenis_rekod, id_rekod: r.id_rekod, id_lot_asal: l.id, id_lot_baharu: peta[`${r.jenis_rekod}:${r.id_rekod}`] })),
                ),
            ),
        onSuccess: (r) => {
            toast.success(r.message);
            done();
        },
        onError: (e) => toast.error(ralat(e)),
    });
    const mHantar = useMutation({ mutationFn: () => tsuApi.hantarPemindahan(u.id), onSuccess: (r) => { toast.success(r.message); done(); }, onError: (e) => toast.error(ralat(e)) });

    if (error) return <Ralat>{ralat(error)}</Ralat>;
    if (!data) return <Kosong>Memuatkan…</Kosong>;
    if (data.lot_baharu.length === 0) return <Kosong>Pemindahan rekod tersedia selepas lot baharu dicipta (pecah sempadan, pecah bahagian atau penyatuan).</Kosong>;
    const boleh = can('tsu.pemindahan.cipta') && !u.dikunci;
    const nama = (id: string) => [...data.lot_asal, ...data.lot_baharu].find((l) => l.id === id)?.no_lot ?? id.slice(0, 8);
    const draf = data.pemetaan.some((p) => p.status_pemindahan === 'draf');

    return (
        <div className="space-y-4 pt-4">
            {data.lot_asal.map((l) => (
                <div key={l.id} className="space-y-2">
                    <p className="text-body-sm font-medium">Lot asal {l.no_lot}</p>
                    {l.rekod.length === 0 ? (
                        <Kosong>Tiada rekod pada lot ini.</Kosong>
                    ) : (
                        <Table head={<><th>Jenis rekod</th><th>Rekod</th><th>Pindah ke lot baharu</th></>}>
                            {l.rekod.map((r) => {
                                const k = `${r.jenis_rekod}:${r.id_rekod}`;
                                return (
                                    <tr key={k}>
                                        <td>{r.jenis_label}</td>
                                        <td>{r.label}</td>
                                        <td>
                                            <select aria-label={`Lot baharu bagi ${r.label}`} disabled={!boleh} className="rounded-md border-otl-gray-300 text-body-sm" value={peta[k] ?? ''} onChange={(e) => setPeta((x) => ({ ...x, [k]: e.target.value }))}>
                                                <option value="">Kekal / tidak dipindah</option>
                                                {data.lot_baharu.map((b) => <option key={b.id} value={b.id}>{b.no_lot}</option>)}
                                            </select>
                                        </td>
                                    </tr>
                                );
                            })}
                        </Table>
                    )}
                </div>
            ))}
            {boleh && (
                <div className="flex flex-wrap gap-2">
                    <Button variant="secondary" onClick={() => mSimpan.mutate()} disabled={mSimpan.isPending || Object.values(peta).every((v) => !v)}>Simpan pemetaan (draf)</Button>
                    <Button onClick={() => mHantar.mutate()} disabled={mHantar.isPending || !draf}>Hantar untuk pengesahan dua peringkat</Button>
                </div>
            )}
            <p className="text-body-xs text-txt-black-500">Rekod hanya dipindahkan selepas dua pengesah berbeza (bukan pembuat pemetaan) meluluskan di Modul Workflow.</p>
            {data.pemetaan.length > 0 && (
                <Table head={<><th>Rekod</th><th>Dari</th><th>Ke</th><th>Status</th><th>Dipindah</th></>}>
                    {data.pemetaan.map((p) => (
                        <tr key={p.id}>
                            <td>{p.label_rekod ?? p.id_rekod}</td>
                            <td>{nama(p.id_lot_asal)}</td>
                            <td>{nama(p.id_lot_baharu)}</td>
                            <td><Status kod={p.status_pemindahan} nama={p.status_pemindahan.replace('_', ' ')} /></td>
                            <td>{masa(p.tarikh_pemindahan)}</td>
                        </tr>
                    ))}
                </Table>
            )}
        </div>
    );
}
