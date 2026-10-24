'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { InputLabel } from '@/components/ui/InputLabel';
import { Spinner } from '@/components/ui/Spinner';
import { api } from '@/lib/api';
import type { SearchGroup } from '@/lib/adm';
import { errorMessage, usePilihan } from '@/lib/dsh';
import { inputClass } from '../_components/PenapisBar';

const KATEGORI: Record<string, string> = {
    '': 'Semua kategori',
    lot_tanah: 'Lot tanah',
    hak_milik: 'Hak milik',
    pemerolehan: 'Pemerolehan',
    dokumen: 'Dokumen',
    kontrak_pajakan: 'Pajakan / sewaan',
    aduan: 'Aduan',
    dsh_draf_transaksi: 'Draf saya',
};

interface Borang {
    q: string;
    kategori: string;
    no_lot: string;
    mukim: string;
    daerah: string;
    pemilik: string;
    tahun: string;
}

const KOSONG: Borang = { q: '', kategori: '', no_lot: '', mukim: '', daerah: '', pemilik: '', tahun: '' };

/**
 * Document & reference search (SRS-DSH-24) by category, lot number, mukim,
 * year, owner and keyword — through MOD-ADM's global search, so each module's
 * provider filters by the user's access.
 */
export default function CarianPage() {
    const { data: pilihan } = usePilihan();
    const [f, setF] = useState<Borang>(KOSONG);
    const [hantar, setHantar] = useState<Borang | null>(null);
    const set = (k: keyof Borang, v: string) => setF((x) => ({ ...x, [k]: v, ...(k === 'daerah' ? { mukim: '' } : {}) }));

    const q = useQuery({
        queryKey: ['dsh', 'carian', hantar],
        enabled: hantar !== null,
        queryFn: () => {
            const s = new URLSearchParams();
            const b = hantar!;
            const keyword = [b.q, b.tahun].filter(Boolean).join(' ');
            if (keyword) s.set('q', keyword);
            if (b.kategori) s.append('entiti[]', b.kategori);
            (['no_lot', 'mukim', 'daerah', 'pemilik'] as const).forEach((k) => b[k] && s.set(`tapis[${k}]`, b[k]));
            s.set('had', '20');
            return api.get<{ data: SearchGroup[]; jumlah: number }>(`/adm/carian?${s.toString()}`);
        },
    });
    const mukim = (pilihan?.mukim ?? []).filter((m) => !f.daerah || m.daerah_kod === f.daerah);

    return (
        <PageContainer>
            <form
                className="space-y-3 rounded-lg border border-otl-gray-200 bg-bg-white p-4 shadow-card"
                onSubmit={(e) => {
                    e.preventDefault();
                    setHantar({ ...f });
                }}
            >
                <div>
                    <InputLabel htmlFor="c-q">Kata kunci</InputLabel>
                    <input id="c-q" className={`${inputClass} mt-1`} value={f.q} onChange={(e) => set('q', e.target.value)} placeholder="cth. geran, nama masjid, no. rujukan" />
                </div>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <label className="text-body-sm text-txt-black-900">
                        Kategori
                        <select className={`${inputClass} mt-1`} value={f.kategori} onChange={(e) => set('kategori', e.target.value)}>
                            {Object.entries(KATEGORI).map(([k, v]) => (
                                <option key={k} value={k}>{v}</option>
                            ))}
                        </select>
                    </label>
                    <label className="text-body-sm text-txt-black-900">
                        No. lot
                        <input className={`${inputClass} mt-1`} value={f.no_lot} onChange={(e) => set('no_lot', e.target.value)} />
                    </label>
                    <label className="text-body-sm text-txt-black-900">
                        Pemilik
                        <input className={`${inputClass} mt-1`} value={f.pemilik} onChange={(e) => set('pemilik', e.target.value)} />
                    </label>
                    <label className="text-body-sm text-txt-black-900">
                        Daerah
                        <select className={`${inputClass} mt-1`} value={f.daerah} onChange={(e) => set('daerah', e.target.value)}>
                            <option value="">Semua</option>
                            {(pilihan?.daerah ?? []).map((d) => <option key={d.kod} value={d.kod}>{d.nama}</option>)}
                        </select>
                    </label>
                    <label className="text-body-sm text-txt-black-900">
                        Mukim
                        <select className={`${inputClass} mt-1`} value={f.mukim} onChange={(e) => set('mukim', e.target.value)}>
                            <option value="">Semua</option>
                            {mukim.map((m) => <option key={m.kod} value={m.kod}>{m.nama}</option>)}
                        </select>
                    </label>
                    <label className="text-body-sm text-txt-black-900">
                        Tahun
                        <input className={`${inputClass} mt-1`} inputMode="numeric" maxLength={4} value={f.tahun} onChange={(e) => set('tahun', e.target.value.replace(/\D/g, ''))} />
                    </label>
                </div>
                <div className="flex gap-2">
                    <Button type="submit">Cari</Button>
                    <Button variant="secondary" onClick={() => { setF(KOSONG); setHantar(null); }}>Set semula</Button>
                </div>
            </form>

            {q.isError && <Alert type="error">{errorMessage(q.error)}</Alert>}
            {q.isFetching && <Spinner />}
            {q.data && (
                <div className="space-y-4">
                    <p className="text-body-sm text-txt-black-500">{q.data.jumlah} hasil</p>
                    {q.data.data.filter((g) => g.hasil.length > 0).map((g) => (
                        <section key={g.entiti} className="rounded-lg border border-otl-gray-200 bg-bg-white p-4 shadow-card">
                            <h2 className="text-body-sm font-semibold text-txt-black-900">{g.label}</h2>
                            <ul className="mt-2 divide-y divide-otl-divider">
                                {g.hasil.map((h) => (
                                    <li key={h.id} className="py-2">
                                        <Link href={h.link} className="text-body-sm font-medium text-txt-primary hover:underline">{h.title}</Link>
                                        {h.subtitle && <p className="text-body-xs text-txt-black-500">{h.subtitle}</p>}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
                </div>
            )}
        </PageContainer>
    );
}
