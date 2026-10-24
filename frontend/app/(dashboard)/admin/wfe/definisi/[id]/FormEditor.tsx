'use client';

import type { ReactNode } from 'react';
import { Button } from '@/components/ui/Button';
import { emptyStage, serialTransitions, type WfeReference, type WfeStage, type WfeStructure, type WfeTransition } from '@/lib/wfe';

const input = 'block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 text-body-sm disabled:bg-bg-washed';

function Field({ label, children, className }: { label: string; children: ReactNode; className?: string }) {
    return (
        <label className={`block text-body-xs font-medium text-txt-black-700 space-y-1 ${className ?? ''}`}>
            <span>{label}</span>
            {children}
        </label>
    );
}

/** Form-based editor (SRS-WFE-03). Edits the same structure as the visual editor. */
export function FormEditor({ value, onChange, reference, readOnly }: { value: WfeStructure; onChange: (s: WfeStructure) => void; reference?: WfeReference; readOnly: boolean }) {
    const set = (patch: Partial<WfeStructure>) => onChange({ ...value, ...patch });
    const setStage = (i: number, patch: Partial<WfeStage>) => set({ peringkat: value.peringkat.map((s, j) => (j === i ? { ...s, ...patch } : s)) });
    const setEdge = (i: number, patch: Partial<WfeTransition>) => set({ peralihan: value.peralihan.map((t, j) => (j === i ? { ...t, ...patch } : t)) });
    const nodes = ['mula', ...value.peringkat.map((s) => s.kod), 'tamat'];
    const nodeLabel = (k: string) => (k === 'mula' ? 'Mula' : k === 'tamat' ? 'Tamat' : (value.peringkat.find((s) => s.kod === k)?.nama ?? k));

    function renameStage(i: number, kod: string) {
        const old = value.peringkat[i].kod;
        const clean = kod.toLowerCase().replace(/[^a-z0-9_]/g, '_');
        onChange({
            ...value,
            peringkat: value.peringkat.map((s, j) => (j === i ? { ...s, kod: clean } : s)),
            peralihan: value.peralihan.map((t) => ({ ...t, dari: t.dari === old ? clean : t.dari, ke: t.ke === old ? clean : t.ke })),
        });
    }

    function addStage() {
        let n = value.peringkat.length + 1;
        while (value.peringkat.some((s) => s.kod === `peringkat_${n}`)) n++;
        const stages = [...value.peringkat, emptyStage(n)];
        const serial = JSON.stringify(value.peralihan) === JSON.stringify(serialTransitions(value.peringkat));
        set({ peringkat: stages, peralihan: serial ? serialTransitions(stages) : value.peralihan });
    }

    function removeStage(i: number) {
        const kod = value.peringkat[i].kod;
        const stages = value.peringkat.filter((_, j) => j !== i);
        const serial = JSON.stringify(value.peralihan) === JSON.stringify(serialTransitions(value.peringkat));
        set({ peringkat: stages, peralihan: serial ? serialTransitions(stages) : value.peralihan.filter((t) => t.dari !== kod && t.ke !== kod) });
    }

    function move(i: number, d: -1 | 1) {
        const j = i + d;
        if (j < 0 || j >= value.peringkat.length) return;
        const stages = [...value.peringkat];
        [stages[i], stages[j]] = [stages[j], stages[i]];
        const serial = JSON.stringify(value.peralihan) === JSON.stringify(serialTransitions(value.peringkat));
        set({ peringkat: stages, peralihan: serial ? serialTransitions(stages) : value.peralihan });
    }

    return (
        <fieldset disabled={readOnly} className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Nama aliran">
                    <input className={input} value={value.nama} onChange={(e) => set({ nama: e.target.value })} />
                </Field>
                <Field label="Kod aliran (tetap)">
                    <input className={input} value={value.kod_aliran} disabled />
                </Field>
                <Field label="Modul">
                    <input className={input} value={value.modul} onChange={(e) => set({ modul: e.target.value })} />
                </Field>
                <Field label="Jenis transaksi">
                    <input className={input} value={value.jenis_transaksi} onChange={(e) => set({ jenis_transaksi: e.target.value })} />
                </Field>
                <Field label="Keterangan" className="sm:col-span-2">
                    <textarea className={input} rows={2} value={value.keterangan ?? ''} onChange={(e) => set({ keterangan: e.target.value })} />
                </Field>
                <label className="flex items-center gap-2 text-body-sm">
                    <input type="checkbox" checked={value.templat} onChange={(e) => set({ templat: e.target.checked })} className="rounded border-otl-gray-300 text-primary-600" />
                    Templat (dalam pustaka templat)
                </label>
            </div>

            <section className="space-y-4">
                <div className="flex items-center justify-between">
                    <h4 className="font-semibold text-body-md text-txt-black-900">Peringkat</h4>
                    {!readOnly && (
                        <Button size="sm" variant="outline" onClick={addStage}>
                            Tambah peringkat
                        </Button>
                    )}
                </div>

                {value.peringkat.map((s, i) => (
                    <div key={i} className="rounded-lg border border-otl-gray-200 p-4 space-y-4" data-testid={`peringkat-${s.kod}`}>
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <p className="font-medium text-txt-black-900">
                                {i + 1}. {s.nama}
                            </p>
                            {!readOnly && (
                                <div className="flex gap-2">
                                    <Button size="sm" variant="secondary" onClick={() => move(i, -1)} aria-label="Naik">↑</Button>
                                    <Button size="sm" variant="secondary" onClick={() => move(i, 1)} aria-label="Turun">↓</Button>
                                    <Button size="sm" variant="danger" onClick={() => removeStage(i)} disabled={value.peringkat.length <= 1}>Buang</Button>
                                </div>
                            )}
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <Field label="Nama peringkat">
                                <input className={input} value={s.nama} onChange={(e) => setStage(i, { nama: e.target.value })} />
                            </Field>
                            <Field label="Kod">
                                <input className={input} value={s.kod} onChange={(e) => renameStage(i, e.target.value)} />
                            </Field>
                            <Field label="Mod kelulusan">
                                <select className={input} value={s.mod_kelulusan} onChange={(e) => setStage(i, { mod_kelulusan: e.target.value as WfeStage['mod_kelulusan'] })}>
                                    {Object.entries(reference?.mod_kelulusan ?? { mana_mana: 'Mana-mana', semua: 'Semua', kuorum: 'Kuorum' }).map(([k, v]) => (
                                        <option key={k} value={k}>{v}</option>
                                    ))}
                                </select>
                            </Field>
                            {s.mod_kelulusan === 'kuorum' && (
                                <Field label="Kuorum">
                                    <input type="number" min={1} className={input} value={s.kuorum ?? 1} onChange={(e) => setStage(i, { kuorum: Number(e.target.value) })} />
                                </Field>
                            )}
                            <Field label="SLA">
                                <div className="flex gap-2">
                                    <input type="number" min={1} className={input} value={s.sla_nilai ?? ''} onChange={(e) => setStage(i, { sla_nilai: e.target.value ? Number(e.target.value) : null })} />
                                    <select className={input} value={s.sla_unit} onChange={(e) => setStage(i, { sla_unit: e.target.value as WfeStage['sla_unit'] })}>
                                        <option value="hari_bekerja">hari bekerja</option>
                                        <option value="jam">jam bekerja</option>
                                    </select>
                                </div>
                            </Field>
                            <label className="flex items-center gap-2 text-body-sm">
                                <input type="checkbox" checked={s.perlu_mfa} onChange={(e) => setStage(i, { perlu_mfa: e.target.checked })} className="rounded border-otl-gray-300 text-primary-600" />
                                Peringkat kritikal (wajib MFA)
                            </label>
                            <label className="flex items-center gap-2 text-body-sm">
                                <input type="checkbox" checked={s.perlu_justifikasi} onChange={(e) => setStage(i, { perlu_justifikasi: e.target.checked })} className="rounded border-otl-gray-300 text-primary-600" />
                                Justifikasi wajib walaupun lulus
                            </label>
                        </div>

                        <div className="space-y-2">
                            <p className="text-body-xs font-semibold text-txt-black-700">Pelulus (setiap baris = satu tugasan selari)</p>
                            {s.pelulus.map((p, k) => (
                                <div key={k} className="grid gap-2 sm:grid-cols-[10rem_1fr_1fr_auto] items-end">
                                    <select className={input} aria-label="Jenis pelulus" value={p.jenis} onChange={(e) => setStage(i, { pelulus: s.pelulus.map((x, m) => (m === k ? { ...x, jenis: e.target.value } : x)) })}>
                                        {Object.entries(reference?.jenis_pelulus ?? {}).map(([kk, v]) => <option key={kk} value={kk}>{v}</option>)}
                                    </select>
                                    {p.jenis === 'kumpulan' ? (
                                        <select className={input} aria-label="Kumpulan" value={p.nilai ?? ''} onChange={(e) => setStage(i, { pelulus: s.pelulus.map((x, m) => (m === k ? { ...x, nilai: e.target.value } : x)) })}>
                                            <option value="">Pilih kumpulan…</option>
                                            {(reference?.kumpulan ?? []).map((g) => <option key={g.kod} value={g.kod}>{g.nama}</option>)}
                                        </select>
                                    ) : (
                                        <input
                                            className={input}
                                            aria-label="Nilai pelulus"
                                            disabled={p.jenis === 'penyelia_pemohon'}
                                            placeholder={p.jenis === 'individu' ? 'ID pengguna' : p.jenis === 'penyelia_pemohon' ? 'automatik' : 'kod peranan / jawatan'}
                                            value={p.nilai ?? ''}
                                            onChange={(e) => setStage(i, { pelulus: s.pelulus.map((x, m) => (m === k ? { ...x, nilai: e.target.value } : x)) })}
                                        />
                                    )}
                                    <input className={input} aria-label="Label" placeholder="Label (pilihan)" value={p.label ?? ''} onChange={(e) => setStage(i, { pelulus: s.pelulus.map((x, m) => (m === k ? { ...x, label: e.target.value } : x)) })} />
                                    {!readOnly && (
                                        <Button size="sm" variant="secondary" disabled={s.pelulus.length <= 1} onClick={() => setStage(i, { pelulus: s.pelulus.filter((_, m) => m !== k) })}>Buang</Button>
                                    )}
                                </div>
                            ))}
                            {!readOnly && (
                                <Button size="sm" variant="secondary" onClick={() => setStage(i, { pelulus: [...s.pelulus, { jenis: 'peranan', nilai: '', label: null }] })}>
                                    + Pelulus
                                </Button>
                            )}
                        </div>

                        <div className="space-y-2">
                            <p className="text-body-xs font-semibold text-txt-black-700">Eskalasi (selepas tarikh akhir SLA)</p>
                            {s.eskalasi.map((e, k) => (
                                <div key={k} className="grid gap-2 grid-cols-2 sm:grid-cols-[4rem_5rem_9rem_10rem_1fr_auto] items-end">
                                    <input type="number" min={1} aria-label="Aras" className={input} value={e.aras} onChange={(ev) => setStage(i, { eskalasi: s.eskalasi.map((x, m) => (m === k ? { ...x, aras: Number(ev.target.value) } : x)) })} />
                                    <input type="number" min={0} aria-label="Tempoh" className={input} value={e.tempoh_nilai} onChange={(ev) => setStage(i, { eskalasi: s.eskalasi.map((x, m) => (m === k ? { ...x, tempoh_nilai: Number(ev.target.value) } : x)) })} />
                                    <select aria-label="Unit tempoh" className={input} value={e.tempoh_unit} onChange={(ev) => setStage(i, { eskalasi: s.eskalasi.map((x, m) => (m === k ? { ...x, tempoh_unit: ev.target.value as 'jam' | 'hari_bekerja' } : x)) })}>
                                        <option value="hari_bekerja">hari bekerja</option>
                                        <option value="jam">jam</option>
                                    </select>
                                    <select aria-label="Penerima" className={input} value={e.jenis_penerima} onChange={(ev) => setStage(i, { eskalasi: s.eskalasi.map((x, m) => (m === k ? { ...x, jenis_penerima: ev.target.value } : x)) })}>
                                        {Object.entries(reference?.jenis_penerima_eskalasi ?? {}).map(([kk, v]) => <option key={kk} value={kk}>{v}</option>)}
                                    </select>
                                    <input aria-label="Nilai penerima" className={input} disabled={e.jenis_penerima === 'penyelia'} value={e.nilai_penerima ?? ''} placeholder={e.jenis_penerima === 'penyelia' ? 'ikut hierarki' : 'kod / ID'} onChange={(ev) => setStage(i, { eskalasi: s.eskalasi.map((x, m) => (m === k ? { ...x, nilai_penerima: ev.target.value } : x)) })} />
                                    {!readOnly && <Button size="sm" variant="secondary" onClick={() => setStage(i, { eskalasi: s.eskalasi.filter((_, m) => m !== k) })}>Buang</Button>}
                                </div>
                            ))}
                            {!readOnly && (
                                <Button size="sm" variant="secondary" onClick={() => setStage(i, { eskalasi: [...s.eskalasi, { aras: s.eskalasi.length + 1, tempoh_nilai: 1, tempoh_unit: 'hari_bekerja', jenis_penerima: 'penyelia', nilai_penerima: null }] })}>
                                    + Aras eskalasi
                                </Button>
                            )}
                        </div>
                    </div>
                ))}
            </section>

            <section className="space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h4 className="font-semibold text-body-md text-txt-black-900">Peralihan &amp; syarat cabangan</h4>
                    {!readOnly && (
                        <div className="flex gap-2">
                            <Button size="sm" variant="secondary" onClick={() => set({ peralihan: serialTransitions(value.peringkat) })}>Jadikan bersiri</Button>
                            <Button size="sm" variant="outline" onClick={() => set({ peralihan: [...value.peralihan, { dari: 'mula', ke: value.peringkat[0]?.kod ?? 'tamat', keutamaan: 1, logik: 'semua', label: null, syarat: [] }] })}>
                                + Peralihan
                            </Button>
                        </div>
                    )}
                </div>
                <p className="text-body-xs text-txt-black-500">
                    Peralihan bersyarat dinilai mengikut keutamaan (kecil dahulu); setiap nod perlu satu peralihan lalai tanpa syarat. Medan syarat merujuk konteks yang dihantar
                    modul (cth. nilai, kategori_tanah, kod_daerah).
                </p>
                {value.peralihan.map((t, i) => (
                    <div key={i} className="rounded-md border border-otl-gray-200 p-3 space-y-2">
                        <div className="grid gap-2 grid-cols-2 sm:grid-cols-[1fr_1fr_6rem_8rem_1fr_auto] items-end">
                            <Field label="Dari">
                                <select className={input} value={t.dari} onChange={(e) => setEdge(i, { dari: e.target.value })}>
                                    {nodes.filter((n) => n !== 'tamat').map((n) => <option key={n} value={n}>{nodeLabel(n)}</option>)}
                                </select>
                            </Field>
                            <Field label="Ke">
                                <select className={input} value={t.ke} onChange={(e) => setEdge(i, { ke: e.target.value })}>
                                    {nodes.filter((n) => n !== 'mula').map((n) => <option key={n} value={n}>{nodeLabel(n)}</option>)}
                                </select>
                            </Field>
                            <Field label="Keutamaan">
                                <input type="number" min={0} className={input} value={t.keutamaan} onChange={(e) => setEdge(i, { keutamaan: Number(e.target.value) })} />
                            </Field>
                            <Field label="Gabungan">
                                <select className={input} value={t.logik} onChange={(e) => setEdge(i, { logik: e.target.value as 'semua' | 'mana_mana' })}>
                                    <option value="semua">Semua syarat</option>
                                    <option value="mana_mana">Mana-mana syarat</option>
                                </select>
                            </Field>
                            <Field label="Label">
                                <input className={input} value={t.label ?? ''} onChange={(e) => setEdge(i, { label: e.target.value || null })} />
                            </Field>
                            {!readOnly && <Button size="sm" variant="danger" onClick={() => set({ peralihan: value.peralihan.filter((_, j) => j !== i) })}>Buang</Button>}
                        </div>
                        <ConditionRows transition={t} reference={reference} readOnly={readOnly} onChange={(syarat) => setEdge(i, { syarat })} />
                    </div>
                ))}
            </section>

            <section className="space-y-2">
                <div className="flex items-center justify-between">
                    <h4 className="font-semibold text-body-md text-txt-black-900">Tindakan susulan selepas keputusan</h4>
                    {!readOnly && (
                        <Button size="sm" variant="outline" onClick={() => set({ susulan: [...value.susulan, { bila: 'lulus', tajuk: 'Kemas kini rekod', jenis_penerima: 'pemohon', nilai_penerima: null, sla_nilai: 3, sla_unit: 'hari_bekerja' }] })}>
                            + Tugasan susulan
                        </Button>
                    )}
                </div>
                {value.susulan.map((f, i) => (
                    <div key={i} className="grid gap-2 grid-cols-2 sm:grid-cols-[7rem_1fr_9rem_1fr_5rem_auto] items-end">
                        <select aria-label="Bila" className={input} value={f.bila} onChange={(e) => set({ susulan: value.susulan.map((x, j) => (j === i ? { ...x, bila: e.target.value as 'lulus' } : x)) })}>
                            <option value="lulus">Jika lulus</option>
                            <option value="tolak">Jika ditolak</option>
                            <option value="semua">Kedua-dua</option>
                        </select>
                        <input aria-label="Tajuk" className={input} value={f.tajuk} onChange={(e) => set({ susulan: value.susulan.map((x, j) => (j === i ? { ...x, tajuk: e.target.value } : x)) })} />
                        <select aria-label="Penerima susulan" className={input} value={f.jenis_penerima} onChange={(e) => set({ susulan: value.susulan.map((x, j) => (j === i ? { ...x, jenis_penerima: e.target.value } : x)) })}>
                            <option value="pemohon">Pemohon</option>
                            <option value="peranan">Peranan</option>
                            <option value="jawatan">Jawatan</option>
                            <option value="individu">Individu</option>
                            <option value="kumpulan">Kumpulan</option>
                        </select>
                        <input aria-label="Nilai penerima susulan" className={input} disabled={f.jenis_penerima === 'pemohon'} value={f.nilai_penerima ?? ''} onChange={(e) => set({ susulan: value.susulan.map((x, j) => (j === i ? { ...x, nilai_penerima: e.target.value } : x)) })} />
                        <input aria-label="SLA susulan" type="number" min={1} className={input} value={f.sla_nilai ?? ''} onChange={(e) => set({ susulan: value.susulan.map((x, j) => (j === i ? { ...x, sla_nilai: e.target.value ? Number(e.target.value) : null } : x)) })} />
                        {!readOnly && <Button size="sm" variant="secondary" onClick={() => set({ susulan: value.susulan.filter((_, j) => j !== i) })}>Buang</Button>}
                    </div>
                ))}
            </section>
        </fieldset>
    );
}

export function ConditionRows({
    transition,
    reference,
    readOnly,
    onChange,
}: {
    transition: WfeTransition;
    reference?: WfeReference;
    readOnly: boolean;
    onChange: (s: WfeTransition['syarat']) => void;
}) {
    const rows = transition.syarat;
    const list = (v: unknown) => (Array.isArray(v) ? v.join(', ') : v === null || v === undefined ? '' : String(v));
    const parse = (op: string, raw: string): unknown => {
        if (op === 'dalam' || op === 'tiada_dalam') return raw.split(',').map((x) => x.trim()).filter(Boolean);
        return raw !== '' && !Number.isNaN(Number(raw)) ? Number(raw) : raw;
    };

    return (
        <div className="space-y-2">
            {rows.length === 0 && <p className="text-body-xs text-txt-black-500">Tiada syarat — peralihan lalai.</p>}
            {rows.map((c, k) => (
                <div key={k} className="grid gap-2 grid-cols-3 sm:grid-cols-[1fr_12rem_1fr_auto] items-end">
                    <input aria-label="Medan" className={input} placeholder="medan, cth. nilai" value={c.medan} onChange={(e) => onChange(rows.map((x, m) => (m === k ? { ...x, medan: e.target.value } : x)))} />
                    <select aria-label="Operator" className={input} value={c.operator} onChange={(e) => onChange(rows.map((x, m) => (m === k ? { ...x, operator: e.target.value, nilai: parse(e.target.value, list(x.nilai)) } : x)))}>
                        {Object.entries(reference?.operator ?? {}).map(([kk, v]) => <option key={kk} value={kk}>{v}</option>)}
                    </select>
                    <input aria-label="Nilai syarat" className={input} placeholder="nilai (senarai: a, b)" disabled={c.operator === 'wujud' || c.operator === 'tiada'} value={list(c.nilai)} onChange={(e) => onChange(rows.map((x, m) => (m === k ? { ...x, nilai: parse(x.operator, e.target.value) } : x)))} />
                    {!readOnly && <Button size="sm" variant="secondary" onClick={() => onChange(rows.filter((_, m) => m !== k))}>Buang</Button>}
                </div>
            ))}
            {!readOnly && (
                <Button size="sm" variant="secondary" onClick={() => onChange([...rows, { medan: 'nilai', operator: 'lebih', nilai: 0 }])}>
                    + Syarat
                </Button>
            )}
        </div>
    );
}
