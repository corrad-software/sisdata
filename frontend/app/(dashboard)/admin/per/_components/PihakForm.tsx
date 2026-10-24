'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { InputLabel } from '@/components/ui/InputLabel';
import { inputClass } from './common';

export interface PihakInput {
    peranan_pihak: string;
    nama: string;
    hubungan?: string;
    jenis_dokumen_pengenalan?: string;
    no_dokumen_pengenalan?: string;
    bahagian_pusaka?: string;
    status_pengesahan?: string;
}

const KOSONG: PihakInput = { peranan_pihak: '', nama: '', hubungan: '', jenis_dokumen_pengenalan: 'mykad', no_dokumen_pengenalan: '', bahagian_pusaka: '', status_pengesahan: 'belum_disahkan' };

/** SRS-PER-23: one related party, roles limited to those valid for the channel. */
export function PihakForm({ roles, onAdd, busy = false }: { roles: Array<{ kod: string; label: string }>; onAdd: (p: PihakInput) => void | Promise<void>; busy?: boolean }) {
    const [p, setP] = useState<PihakInput>({ ...KOSONG, peranan_pihak: roles[0]?.kod ?? '' });
    const set = (k: keyof PihakInput, v: string) => setP((x) => ({ ...x, [k]: v }));

    return (
        <div className="rounded-lg border border-otl-gray-200 p-3 sm:p-4 space-y-3">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div>
                    <InputLabel htmlFor="pihak-peranan" className="mb-1.5">
                        Peranan
                    </InputLabel>
                    <select id="pihak-peranan" className={inputClass} value={p.peranan_pihak} onChange={(e) => set('peranan_pihak', e.target.value)}>
                        {roles.map((r) => (
                            <option key={r.kod} value={r.kod}>
                                {r.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="sm:col-span-1 lg:col-span-2">
                    <InputLabel htmlFor="pihak-nama" className="mb-1.5">
                        Nama (Sulit)
                    </InputLabel>
                    <input id="pihak-nama" className={inputClass} value={p.nama} onChange={(e) => set('nama', e.target.value)} />
                </div>
                <div>
                    <InputLabel htmlFor="pihak-hubungan" className="mb-1.5">
                        Hubungan
                    </InputLabel>
                    <input id="pihak-hubungan" className={inputClass} value={p.hubungan} onChange={(e) => set('hubungan', e.target.value)} placeholder="cth. Anak, Isteri" />
                </div>
                <div>
                    <InputLabel htmlFor="pihak-jenis-id" className="mb-1.5">
                        Jenis dokumen pengenalan
                    </InputLabel>
                    <select id="pihak-jenis-id" className={inputClass} value={p.jenis_dokumen_pengenalan} onChange={(e) => set('jenis_dokumen_pengenalan', e.target.value)}>
                        <option value="mykad">MyKad</option>
                        <option value="pasport">Pasport</option>
                        <option value="no_syarikat">No. pendaftaran syarikat</option>
                        <option value="lain">Lain-lain</option>
                    </select>
                </div>
                <div>
                    <InputLabel htmlFor="pihak-id" className="mb-1.5">
                        No. pengenalan (Sulit)
                    </InputLabel>
                    <input id="pihak-id" className={inputClass} value={p.no_dokumen_pengenalan} onChange={(e) => set('no_dokumen_pengenalan', e.target.value)} autoComplete="off" />
                </div>
                <div>
                    <InputLabel htmlFor="pihak-bahagian" className="mb-1.5">
                        Bahagian pusaka
                    </InputLabel>
                    <input id="pihak-bahagian" className={inputClass} value={p.bahagian_pusaka} onChange={(e) => set('bahagian_pusaka', e.target.value)} placeholder="cth. 1/8" />
                </div>
                <div>
                    <InputLabel htmlFor="pihak-status" className="mb-1.5">
                        Status pengesahan
                    </InputLabel>
                    <select id="pihak-status" className={inputClass} value={p.status_pengesahan} onChange={(e) => set('status_pengesahan', e.target.value)}>
                        <option value="belum_disahkan">Belum disahkan</option>
                        <option value="disahkan">Disahkan</option>
                        <option value="ditolak">Ditolak</option>
                    </select>
                </div>
            </div>
            <div className="flex justify-end">
                <Button
                    size="sm"
                    disabled={busy || !p.nama.trim() || !p.peranan_pihak}
                    onClick={async () => {
                        await onAdd(Object.fromEntries(Object.entries(p).filter(([, v]) => v !== '')) as unknown as PihakInput);
                        setP({ ...KOSONG, peranan_pihak: p.peranan_pihak });
                    }}
                >
                    Tambah pihak
                </Button>
            </div>
        </div>
    );
}
