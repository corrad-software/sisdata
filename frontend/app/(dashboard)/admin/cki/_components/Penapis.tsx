'use client';

import { useRujukan } from '@/lib/cki';
import { Field, inputClass, Select } from './common';

/** Shared filter row: district, mukim, agency (PTD / PBT), tax type and year (SRS-CKI-21 filters). */
export function Penapis({ value, onChange, show = ['kod_daerah', 'kod_mukim', 'kod_agensi', 'jenis_cukai', 'tahun'] }: { value: Record<string, string>; onChange: (k: string, v: string) => void; show?: string[] }) {
    const { data: ref } = useRujukan();
    const mukim = (ref?.mukim ?? []).filter((m) => !value.kod_daerah || m.daerah_kod === value.kod_daerah);

    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            {show.includes('kod_daerah') && (
                <Field label="Daerah" htmlFor="f-daerah">
                    <Select id="f-daerah" value={value.kod_daerah ?? ''} onChange={(v) => onChange('kod_daerah', v)} options={ref?.daerah ?? []} empty="Semua" />
                </Field>
            )}
            {show.includes('kod_mukim') && (
                <Field label="Mukim" htmlFor="f-mukim">
                    <Select id="f-mukim" value={value.kod_mukim ?? ''} onChange={(v) => onChange('kod_mukim', v)} options={mukim} empty="Semua" />
                </Field>
            )}
            {show.includes('kod_agensi') && (
                <Field label="Agensi (PTD / PBT)" htmlFor="f-agensi">
                    <Select id="f-agensi" value={value.kod_agensi ?? ''} onChange={(v) => onChange('kod_agensi', v)} options={ref?.agensi ?? []} empty="Semua" />
                </Field>
            )}
            {show.includes('jenis_cukai') && (
                <Field label="Jenis cukai" htmlFor="f-jenis">
                    <Select id="f-jenis" value={value.jenis_cukai ?? ''} onChange={(v) => onChange('jenis_cukai', v)} options={ref?.jenis_cukai ?? []} empty="Semua" />
                </Field>
            )}
            {show.includes('tahun') && (
                <Field label="Tahun taksiran" htmlFor="f-tahun">
                    <input id="f-tahun" type="number" min={1990} max={2100} className={inputClass} value={value.tahun ?? ''} onChange={(e) => onChange('tahun', e.target.value)} />
                </Field>
            )}
        </div>
    );
}
