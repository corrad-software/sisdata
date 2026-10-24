'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { InputLabel } from '@/components/ui/InputLabel';
import { usePilihan, type KodNama, type Penapis } from '@/lib/dsh';

export const inputClass =
    'block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm disabled:bg-bg-washed disabled:opacity-60';

function Pilih({ id, label, value, options, onChange, semua = 'Semua' }: { id: string; label: string; value?: string; options: KodNama[]; onChange: (v: string) => void; semua?: string }) {
    return (
        <div className="min-w-0">
            <InputLabel htmlFor={id} className="mb-1 !text-body-xs !text-txt-black-700">
                {label}
            </InputLabel>
            <select id={id} className={inputClass} value={value ?? ''} onChange={(e) => onChange(e.target.value)}>
                <option value="">{semua}</option>
                {options.map((o) => (
                    <option key={o.kod} value={o.kod}>
                        {o.nama}
                    </option>
                ))}
            </select>
        </div>
    );
}

/**
 * Combined dashboard filters (SRS-DSH-08): tahun, daerah, mukim, bahagian,
 * kategori tanah, status, modul. Options come from the API already limited to
 * the user's scope; the values live in the URL so every widget and page shares them.
 */
export function PenapisBar({ value, onChange, showModul = true }: { value: Penapis; onChange: (p: Penapis) => void; showModul?: boolean }) {
    const { data } = usePilihan();
    const [open, setOpen] = useState(false);
    const set = (k: keyof Penapis, v: string) => {
        const next: Penapis = { ...value, [k]: v || undefined };
        if (k === 'kod_daerah') next.kod_mukim = undefined;
        onChange(next);
    };
    const mukim = (data?.mukim ?? []).filter((m) => !value.kod_daerah || m.daerah_kod === value.kod_daerah);
    const active = Object.values(value).filter(Boolean).length;

    return (
        <section aria-label="Penapis dashboard" className="rounded-lg border border-otl-gray-200 bg-bg-white p-4 shadow-card">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-body-sm font-semibold text-txt-black-900">
                    Penapis{active > 0 ? ` (${active} aktif)` : ''}
                </h2>
                <div className="flex gap-2">
                    {active > 0 && (
                        <Button variant="secondary" size="sm" onClick={() => onChange({})}>
                            Kosongkan
                        </Button>
                    )}
                    <Button variant="secondary" size="sm" className="sm:hidden" onClick={() => setOpen((o) => !o)} aria-expanded={open}>
                        {open ? 'Sembunyi' : 'Tunjuk'}
                    </Button>
                </div>
            </div>
            <div className={`${open ? 'grid' : 'hidden'} mt-3 grid-cols-2 gap-3 sm:grid md:grid-cols-4 xl:grid-cols-7`}>
                <Pilih id="f-tahun" label="Tahun" value={value.tahun} onChange={(v) => set('tahun', v)} semua="Tahun semasa" options={(data?.tahun ?? []).map((t) => ({ kod: String(t), nama: String(t) }))} />
                <Pilih id="f-daerah" label="Daerah" value={value.kod_daerah} onChange={(v) => set('kod_daerah', v)} options={data?.daerah ?? []} />
                <Pilih id="f-mukim" label="Mukim" value={value.kod_mukim} onChange={(v) => set('kod_mukim', v)} options={mukim} />
                <Pilih id="f-bahagian" label="Bahagian" value={value.bahagian} onChange={(v) => set('bahagian', v)} options={data?.bahagian ?? []} />
                <Pilih id="f-kategori" label="Kategori tanah" value={value.kategori} onChange={(v) => set('kategori', v)} options={data?.kategori ?? []} />
                <Pilih id="f-status" label="Status lot" value={value.status} onChange={(v) => set('status', v)} options={data?.status ?? []} />
                {showModul && <Pilih id="f-modul" label="Modul" value={value.modul} onChange={(v) => set('modul', v)} options={data?.modul ?? []} />}
            </div>
        </section>
    );
}
