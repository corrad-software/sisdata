'use client';

import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { dshApi, errorMessage, VISUAL_LABEL, type Aras, type Visual, type Widget } from '@/lib/dsh';
import { inputClass } from './PenapisBar';

const KPI_VISUALS: Visual[] = ['auto', 'stat', 'bar', 'pie', 'line', 'progress', 'heatmap', 'lampu'];

type Row = { kod: string; tajuk: string; saiz: number; tersembunyi: boolean; jenis_visual: Visual; kpi: string | null };

/** Personal widget layout (SRS-DSH-16): order, size, visual, show/hide — saved for this user only. */
export function SusunAturModal({ open, onClose, aras, widgets }: { open: boolean; onClose: () => void; aras: Aras; widgets: Widget[] }) {
    const [rows, setRows] = useState<Row[]>(() => widgets.map((w) => ({ kod: w.kod, tajuk: w.tajuk, saiz: w.saiz, tersembunyi: w.tersembunyi, jenis_visual: w.jenis_visual, kpi: w.kpi })));
    const qc = useQueryClient();
    const done = (message: string) => {
        toast.success(message);
        qc.invalidateQueries({ queryKey: ['dsh', 'dashboard'] });
        onClose();
    };
    const save = useMutation({
        mutationFn: () => dshApi.simpanSusunAtur(aras, rows.map((r) => ({ kod: r.kod, saiz: r.saiz, tersembunyi: r.tersembunyi, jenis_visual: r.kpi ? r.jenis_visual : null }))),
        onSuccess: (r) => done(r.message),
        onError: (e) => toast.error(errorMessage(e)),
    });
    const reset = useMutation({ mutationFn: () => dshApi.setSemulaSusunAtur(aras), onSuccess: (r) => done(r.message), onError: (e) => toast.error(errorMessage(e)) });

    const move = (i: number, d: -1 | 1) =>
        setRows((rs) => {
            const j = i + d;
            if (j < 0 || j >= rs.length) return rs;
            const next = [...rs];
            [next[i], next[j]] = [next[j], next[i]];
            return next;
        });
    const patch = (i: number, p: Partial<Row>) => setRows((rs) => rs.map((r, n) => (n === i ? { ...r, ...p } : r)));

    return (
        <Modal open={open} onClose={onClose}>
            <div className="max-h-[80vh] overflow-y-auto p-5">
                <h2 className="font-heading text-heading-2xs font-semibold text-txt-black-900">Ubah susun atur</h2>
                <p className="mt-1 text-body-xs text-txt-black-500">Susun atur ini untuk akaun anda sahaja.</p>
                <ul className="mt-4 divide-y divide-otl-divider">
                    {rows.map((r, i) => (
                        <li key={r.kod} className="space-y-2 py-3">
                            <div className="flex items-center justify-between gap-2">
                                <span className={`text-body-sm ${r.tersembunyi ? 'text-txt-black-500 line-through' : 'text-txt-black-900'}`}>{r.tajuk}</span>
                                <span className="flex gap-1">
                                    <Button size="sm" variant="secondary" onClick={() => move(i, -1)} disabled={i === 0} aria-label={`Naikkan ${r.tajuk}`}>
                                        ↑
                                    </Button>
                                    <Button size="sm" variant="secondary" onClick={() => move(i, 1)} disabled={i === rows.length - 1} aria-label={`Turunkan ${r.tajuk}`}>
                                        ↓
                                    </Button>
                                </span>
                            </div>
                            <div className="grid grid-cols-3 gap-2">
                                <label className="text-body-xs text-txt-black-700">
                                    Saiz
                                    <select className={inputClass} value={r.saiz} onChange={(e) => patch(i, { saiz: Number(e.target.value) })}>
                                        {[1, 2, 3, 4].map((s) => (
                                            <option key={s} value={s}>
                                                {s}/4
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="text-body-xs text-txt-black-700">
                                    Visual
                                    <select className={inputClass} value={r.jenis_visual} disabled={!r.kpi} onChange={(e) => patch(i, { jenis_visual: e.target.value as Visual })}>
                                        {(r.kpi ? KPI_VISUALS : [r.jenis_visual]).map((v) => (
                                            <option key={v} value={v}>
                                                {VISUAL_LABEL[v]}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="flex items-end gap-2 pb-2 text-body-xs text-txt-black-700">
                                    <input type="checkbox" className="rounded border-otl-gray-300" checked={!r.tersembunyi} onChange={(e) => patch(i, { tersembunyi: !e.target.checked })} />
                                    Papar
                                </label>
                            </div>
                        </li>
                    ))}
                </ul>
                <div className="mt-4 flex flex-wrap justify-end gap-2">
                    <Button variant="secondary" onClick={() => reset.mutate()} disabled={reset.isPending}>
                        Set semula
                    </Button>
                    <Button variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    <Button onClick={() => save.mutate()} disabled={save.isPending}>
                        Simpan susun atur
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
