'use client';

import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ApiError } from '@/lib/api';
import { perApi, usePerCan, useSaluran, type SaluranPenuh } from '@/lib/per';
import { cardClass, inputClass, SaluranChip } from '../_components/common';

/** SRS-PER-01/16/17: the 11 channels, their stages with target days and mandatory checklist. */
export default function SaluranPage() {
    const { data, isLoading } = useSaluran();
    const can = usePerCan();

    return (
        <PageContainer>
            {isLoading && <p className="text-body-sm text-txt-black-500">Memuatkan…</p>}
            {data?.map((s) => (
                <SaluranKad key={s.kod} s={s} editable={can('per.saluran.kemas_kini')} />
            ))}
        </PageContainer>
    );
}

function SaluranKad({ s, editable }: { s: SaluranPenuh; editable: boolean }) {
    const queryClient = useQueryClient();
    const [target, setTarget] = useState<Record<number, number>>({});
    const [total, setTotal] = useState(s.tempoh_sasaran_hari);
    const [busy, setBusy] = useState(false);
    const dirty = Object.keys(target).length > 0 || total !== s.tempoh_sasaran_hari;

    async function save() {
        setBusy(true);
        try {
            await perApi.kemasKiniSaluran(s.kod, {
                tempoh_sasaran_hari: total,
                peringkat: Object.entries(target).map(([id, hari]) => ({ id: Number(id), tempoh_sasaran_hari: hari })),
            });
            toast.success('Konfigurasi disimpan; terpakai kepada kes baharu.');
            setTarget({});
            queryClient.invalidateQueries({ queryKey: ['per', 'saluran'] });
        } catch (e) {
            toast.error(e instanceof ApiError ? e.message : 'Gagal menyimpan.');
        } finally {
            setBusy(false);
        }
    }

    return (
        <section className={`${cardClass} space-y-4`} style={{ borderLeft: `4px solid ${s.warna}` }}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="space-y-1">
                    <SaluranChip kod={s.kod} nama={s.nama} warna={s.warna} ikon={s.ikon} />
                    <p className="text-body-xs text-txt-black-500">
                        Lampiran H {s.rujukan_lampiran_h} · {s.nama_bahagian} {!s.status_aktif && <Badge variant="neutral">Tidak aktif</Badge>}
                    </p>
                </div>
                <label className="flex items-center gap-2 text-body-sm">
                    Sasaran keseluruhan
                    <input type="number" min={1} className={`${inputClass} w-24`} value={total} disabled={!editable} onChange={(e) => setTotal(Number(e.target.value))} aria-label={`Tempoh sasaran ${s.nama}`} />
                    hari
                </label>
            </div>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <h4 className="text-body-sm font-semibold mb-2">Peringkat proses</h4>
                    <ol className="space-y-1.5">
                        {s.peringkat.map((p) => (
                            <li key={p.id} className="flex items-center justify-between gap-3 text-body-sm">
                                <span>
                                    {p.susunan}. {p.nama} {p.jenis === 'kelulusan' && <Badge variant="info">WFE</Badge>}
                                </span>
                                <span className="flex items-center gap-1 flex-shrink-0">
                                    <input
                                        type="number"
                                        min={1}
                                        className={`${inputClass} w-20`}
                                        value={target[p.id] ?? p.tempoh_sasaran_hari}
                                        disabled={!editable}
                                        onChange={(e) => setTarget((t) => ({ ...t, [p.id]: Number(e.target.value) }))}
                                        aria-label={`Tempoh sasaran ${p.nama}`}
                                    />
                                    hari
                                </span>
                            </li>
                        ))}
                    </ol>
                </div>
                <div>
                    <h4 className="text-body-sm font-semibold mb-2">Dokumen sokongan wajib</h4>
                    <ul className="list-disc pl-5 space-y-0.5 text-body-sm">
                        {s.senarai_semak.map((d) => (
                            <li key={d.id}>
                                {d.nama_dokumen}
                                {d.dijana_sistem && <span className="text-body-xs text-txt-black-500"> (dijana semasa tutup kes)</span>}
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
            {editable && dirty && (
                <div className="flex justify-end">
                    <Button size="sm" onClick={save} disabled={busy}>
                        Simpan
                    </Button>
                </div>
            )}
        </section>
    );
}
