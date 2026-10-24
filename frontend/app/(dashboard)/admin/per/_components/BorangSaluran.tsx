'use client';

import { InputLabel } from '@/components/ui/InputLabel';
import type { Medan, SaluranPenuh } from '@/lib/per';
import { inputClass } from './common';

/**
 * Channel form generated from the field configuration returned by the API
 * (plan slice PER-c: one generator instead of eleven hand-written screens).
 * Fields are grouped by the stage they must be filled by.
 */
export function BorangSaluran({
    saluran,
    values,
    onChange,
    dikaburkan = false,
    readOnly = false,
    errors = {},
}: {
    saluran: SaluranPenuh;
    values: Record<string, string | null>;
    onChange: (kod: string, value: string) => void;
    dikaburkan?: boolean;
    readOnly?: boolean;
    errors?: Record<string, string[]>;
}) {
    const groups: Array<{ key: string; label: string; fields: Medan[] }> = [];
    const stageName = (kod: string | null) => saluran.peringkat.find((p) => p.kod === kod)?.nama ?? 'Maklumat tambahan (pilihan)';
    for (const f of saluran.medan) {
        const key = f.peringkat ?? '_';
        let g = groups.find((x) => x.key === key);
        if (!g) {
            g = { key, label: stageName(f.peringkat), fields: [] };
            groups.push(g);
        }
        g.fields.push(f);
    }
    groups.sort((a, b) => order(saluran, a.key) - order(saluran, b.key));

    return (
        <div className="space-y-6">
            {groups.map((g) => (
                <fieldset key={g.key} className="space-y-3">
                    <legend className="font-heading font-semibold text-body-md text-txt-black-900">
                        {g.label}
                        {g.key !== '_' && <span className="ml-2 text-body-xs font-normal text-txt-black-500">wajib sebelum keluar peringkat ini</span>}
                    </legend>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {g.fields.map((f) => {
                            const id = `medan-${f.kod}`;
                            const value = values[f.kod] ?? '';
                            const locked = readOnly || (f.peribadi && dikaburkan && value !== '');
                            const err = errors[`butiran.${f.kod}`]?.[0] ?? errors[f.kod]?.[0];
                            return (
                                <div key={f.kod} className={f.jenis === 'teks_panjang' ? 'sm:col-span-2' : ''}>
                                    <InputLabel htmlFor={id} className="mb-1.5">
                                        {f.label}
                                        {f.peribadi && <span className="ml-1 text-body-xs text-txt-warning">(Sulit)</span>}
                                    </InputLabel>
                                    {f.jenis === 'pilihan' ? (
                                        <select id={id} className={inputClass} value={value} disabled={locked} onChange={(e) => onChange(f.kod, e.target.value)}>
                                            <option value="">— Pilih —</option>
                                            {f.pilihan?.map((o) => (
                                                <option key={o.nilai} value={o.nilai}>
                                                    {o.label}
                                                </option>
                                            ))}
                                        </select>
                                    ) : f.jenis === 'teks_panjang' ? (
                                        <textarea id={id} rows={3} className={inputClass} value={value} disabled={locked} onChange={(e) => onChange(f.kod, e.target.value)} />
                                    ) : (
                                        <input
                                            id={id}
                                            type={f.jenis === 'tarikh' ? 'date' : f.jenis === 'nombor' || f.jenis === 'wang' ? 'number' : 'text'}
                                            step={f.jenis === 'wang' ? '0.01' : undefined}
                                            min={f.jenis === 'wang' || f.jenis === 'nombor' ? 0 : undefined}
                                            inputMode={f.jenis === 'wang' || f.jenis === 'nombor' ? 'decimal' : undefined}
                                            className={inputClass}
                                            value={value}
                                            disabled={locked}
                                            onChange={(e) => onChange(f.kod, e.target.value)}
                                        />
                                    )}
                                    {f.mesti && <p className="mt-1 text-body-xs text-txt-black-500">Mesti &quot;{f.pilihan?.find((o) => o.nilai === f.mesti)?.label ?? f.mesti}&quot; untuk meneruskan.</p>}
                                    {err && <p className="mt-1 text-body-xs text-txt-danger">{err}</p>}
                                </div>
                            );
                        })}
                    </div>
                </fieldset>
            ))}
        </div>
    );
}

function order(s: SaluranPenuh, key: string): number {
    if (key === '_') return 999;
    return s.peringkat.find((p) => p.kod === key)?.susunan ?? 998;
}
