'use client';

import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { ckiApi, ralat, type Obligasi } from '@/lib/cki';
import { Field, inputClass, ModalBody } from './common';

/** SRS-CKI-07: manual (fallback) bill entry — same fields as API records, bill image to DMS. */
export function BilManual({ open, onClose, obligasi }: { open: boolean; onClose: () => void; obligasi?: Obligasi | null }) {
    const qc = useQueryClient();
    const [q, setQ] = useState('');
    const [picked, setPicked] = useState<Obligasi | null>(obligasi ?? null);
    const [f, setF] = useState<Record<string, string>>({ tahun_taksiran: String(new Date().getFullYear()) });
    const [fail, setFail] = useState<File | null>(null);
    const [busy, setBusy] = useState(false);
    const { data: found } = useQuery({ queryKey: ['cki', 'obligasi', 'cari', q], queryFn: () => ckiApi.obligasi({ q, per_page: 8 }), enabled: open && !obligasi && q.length >= 2 });
    const current = obligasi ?? picked;
    const set = (k: string, v: string) => setF((p) => ({ ...p, [k]: v }));

    async function simpan() {
        if (!current) return;
        setBusy(true);
        try {
            const form = new FormData();
            form.append('obligasi_id', current.id);
            Object.entries(f).forEach(([k, v]) => v !== '' && form.append(k, v));
            if (fail) form.append('fail', fail);
            const r = await ckiApi.bilCipta(form);
            toast.success(r.message ?? 'Disimpan.');
            qc.invalidateQueries({ queryKey: ['cki'] });
            onClose();
        } catch (e) {
            toast.error(ralat(e));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Modal open={open} onClose={onClose}>
            <ModalBody
                title="Rekod bil manual (mod sandaran)"
                footer={
                    <>
                        <Button variant="outline" onClick={onClose}>
                            Batal
                        </Button>
                        <Button onClick={simpan} disabled={!current || busy}>
                            {busy ? 'Menyimpan…' : 'Simpan bil'}
                        </Button>
                    </>
                }
            >
                <p className="text-body-sm text-txt-black-500">Hanya bagi lot di luar liputan API atau semasa API tidak tersedia. Rekod ditanda sumber &ldquo;manual&rdquo;.</p>
                {!obligasi && (
                    <Field label="Obligasi (no. akaun atau no. lot)" htmlFor="m-q">
                        <input id="m-q" className={inputClass} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Taip sekurang-kurangnya 2 aksara" />
                        <ul className="mt-2 space-y-1">
                            {(found?.data ?? []).map((o) => (
                                <li key={o.id}>
                                    <button type="button" onClick={() => setPicked(o)} className={`w-full text-left rounded px-2 py-1 text-body-sm ${picked?.id === o.id ? 'bg-bg-primary-50 text-txt-primary' : 'hover:bg-bg-washed'}`}>
                                        {o.no_lot} · {o.jenis_cukai_nama} · {o.no_akaun_cukai} {o.status_liputan_api === 'luar' ? '(luar liputan)' : ''}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </Field>
                )}
                {current && (
                    <p className="text-body-sm">
                        <strong>{current.no_lot}</strong> · {current.jenis_cukai_nama} · {current.nama_agensi ?? current.kod_agensi} · akaun {current.no_akaun_cukai}
                    </p>
                )}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <Field label="Tahun taksiran" htmlFor="m-tahun">
                        <input id="m-tahun" type="number" className={inputClass} value={f.tahun_taksiran ?? ''} onChange={(e) => set('tahun_taksiran', e.target.value)} />
                    </Field>
                    <Field label="No. bil" htmlFor="m-nobil">
                        <input id="m-nobil" className={inputClass} value={f.no_bil ?? ''} onChange={(e) => set('no_bil', e.target.value)} />
                    </Field>
                    <Field label="Amaun pokok (RM)" htmlFor="m-pokok">
                        <input id="m-pokok" inputMode="decimal" className={inputClass} value={f.amaun_pokok ?? ''} onChange={(e) => set('amaun_pokok', e.target.value)} />
                    </Field>
                    <Field label="Denda (RM)" htmlFor="m-denda">
                        <input id="m-denda" inputMode="decimal" className={inputClass} value={f.amaun_denda ?? ''} onChange={(e) => set('amaun_denda', e.target.value)} />
                    </Field>
                    <Field label="Tarikh bil" htmlFor="m-tbil">
                        <input id="m-tbil" type="date" className={inputClass} value={f.tarikh_bil ?? ''} onChange={(e) => set('tarikh_bil', e.target.value)} />
                    </Field>
                    <Field label="Tarikh akhir bayaran" htmlFor="m-takhir">
                        <input id="m-takhir" type="date" className={inputClass} value={f.tarikh_akhir ?? ''} onChange={(e) => set('tarikh_akhir', e.target.value)} />
                    </Field>
                </div>
                <Field label="Imej / salinan bil (pilihan)" htmlFor="m-fail">
                    <input id="m-fail" type="file" accept=".pdf,.jpg,.jpeg,.png" className="block w-full text-body-sm" onChange={(e) => setFail(e.target.files?.[0] ?? null)} />
                </Field>
            </ModalBody>
        </Modal>
    );
}
