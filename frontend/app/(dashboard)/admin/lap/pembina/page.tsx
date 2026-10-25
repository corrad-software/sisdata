'use client';

import { Suspense, useEffect, useMemo, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useMutation, useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { Callout } from '@/components/ui/Callout';
import { FUNGSI_LABEL, lapApi, OP_LABEL, opsFor, PRATETAP_LABEL, ralat, type Agregat, type Definisi, type Penapis } from '@/lib/lap';
import { Card, Field, inputClass, LabelCadangan } from '../_components/common';
import { Viewer } from '../_components/Viewer';

const BUTIRAN: Record<string, string> = { bulan: 'Bulan', suku: 'Suku tahun', tahun: 'Tahun', hari: 'Hari' };

/** SRS-LAP-03 ad hoc builder + SRS-LAP-13 AI prompt (rule-based, local). */
function Pembina() {
    const router = useRouter();
    const params = useSearchParams();
    const editId = params.get('definisi');
    const sets = useQuery({ queryKey: ['lap', 'set-data'], queryFn: () => lapApi.setData() });
    const asal = useQuery({ queryKey: ['lap', 'definisi', editId], queryFn: () => lapApi.definisiSatu(editId as string).then((r) => r.data), enabled: !!editId });

    const [def, setDef] = useState<Definisi>({ set: '', kumpulan: [], agregat: [{ fungsi: 'bilangan' }], penapis: [], medan: [], carta: 'bar' });
    const [mod, setMod] = useState<'kumpulan' | 'rekod'>('kumpulan');
    const [pratonton, setPratonton] = useState<Definisi | null>(null);
    const [prompt, setPrompt] = useState('');
    const [penjelasan, setPenjelasan] = useState<string[]>([]);
    const [label, setLabel] = useState<string | null>(null);
    const [nama, setNama] = useState('');
    const [kongsi, setKongsi] = useState<'peribadi' | 'peranan' | 'semua'>('peribadi');
    const [peranan, setPeranan] = useState('');
    const [sumberAi, setSumberAi] = useState(false);

    useEffect(() => {
        if (!asal.data) return;
        const d = asal.data;
        // eslint-disable-next-line react-hooks/set-state-in-effect -- load a saved definition once
        setDef(d.pertanyaan);
        setMod(d.pertanyaan.kumpulan?.length || d.pertanyaan.hierarki?.length ? 'kumpulan' : 'rekod');
        setNama(d.milik_saya ? d.nama : `${d.nama} (salinan)`);
        setKongsi(d.milik_saya ? d.status_kongsi : 'peribadi');
        setPeranan(d.kongsi_peranan.join(', '));
    }, [asal.data]);

    const set = useMemo(() => sets.data?.data.find((s) => s.kunci === def.set), [sets.data, def.set]);
    const medan = set?.medan ?? [];
    const jenisOf = (k: string) => medan.find((m) => m.kunci === k.split(':')[0])?.jenis ?? 'teks';
    const kumpulBoleh = medan.filter((m) => ['kod', 'teks', 'bool', 'tarikh'].includes(m.jenis));
    const nomborBoleh = medan.filter((m) => ['nombor', 'wang'].includes(m.jenis));

    const ai = useMutation({
        mutationFn: () => lapApi.prompt(prompt).then((r) => r.data),
        onSuccess: (r) => {
            setPenjelasan(r.penjelasan);
            setLabel(r.label_cadangan);
            if (r.difahami && r.definisi) {
                setDef({ medan: [], penapis: [], kumpulan: [], ...r.definisi });
                setMod(r.definisi.kumpulan?.length ? 'kumpulan' : 'rekod');
                setPratonton({ ...r.definisi });
                setSumberAi(true);
                if (!nama) setNama(prompt.slice(0, 120));
            }
        },
        onError: (e) => toast.error(ralat(e)),
    });

    const akhir = (): Definisi => {
        const d: Definisi = { ...def, hierarki: [] };
        if (mod === 'rekod') {
            d.kumpulan = [];
            d.agregat = [];
        } else {
            d.medan = [];
            if (!d.agregat?.length) d.agregat = [{ fungsi: 'bilangan' }];
        }
        if (d.tempoh && !d.tempoh.pratetap && !d.tempoh.dari && !d.tempoh.hingga) d.tempoh = null;
        return d;
    };

    const simpan = useMutation({
        mutationFn: () => {
            const body = {
                nama, pertanyaan: akhir(), status_kongsi: kongsi, sumber: sumberAi ? 'ai_prompt' : 'pembina',
                kongsi_peranan: kongsi === 'peranan' ? peranan.split(',').map((s) => s.trim()).filter(Boolean) : null,
            };
            return lapApi.simpanDefinisi(body, asal.data?.milik_saya ? asal.data.id : undefined).then((r) => r.data);
        },
        onSuccess: (d) => {
            toast.success('Definisi laporan disimpan.');
            router.push(`/admin/lap/definisi/${d.id}`);
        },
        onError: (e) => toast.error(ralat(e)),
    });

    const ubahPenapis = (i: number, p: Partial<Penapis>) => setDef({ ...def, penapis: (def.penapis ?? []).map((x, j) => (j === i ? { ...x, ...p } : x)) });
    const ubahAgregat = (i: number, a: Partial<Agregat>) => setDef({ ...def, agregat: (def.agregat ?? []).map((x, j) => (j === i ? { ...x, ...a } : x)) });

    return (
        <PageContainer>
            <Card title="Minta laporan dalam bahasa biasa">
                <form
                    className="flex flex-col sm:flex-row gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (prompt.trim().length >= 3) ai.mutate();
                    }}
                >
                    <label htmlFor="prompt" className="sr-only">
                        Permintaan laporan
                    </label>
                    <input id="prompt" className={inputClass} value={prompt} onChange={(e) => setPrompt(e.target.value)} placeholder='cth. "jumlah baki cukai tertunggak mengikut daerah tahun ini berbanding tahun lepas"' />
                    <Button type="submit" disabled={ai.isPending}>
                        {ai.isPending ? 'Memproses…' : 'Jana'}
                    </Button>
                </form>
                {penjelasan.length > 0 && (
                    <ul className="list-disc pl-5 text-body-sm text-txt-black-700">
                        {penjelasan.map((p) => (
                            <li key={p}>{p}</li>
                        ))}
                    </ul>
                )}
                <LabelCadangan teks={label} />
            </Card>

            <Card title="Pembina laporan ad hoc">
                {sets.isError && <Callout type="danger">{ralat(sets.error)}</Callout>}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <Field label="Set data" htmlFor="set">
                        <select id="set" className={inputClass} value={def.set} onChange={(e) => setDef({ set: e.target.value, kumpulan: [], agregat: [{ fungsi: 'bilangan' }], penapis: [], medan: [], carta: 'bar' })}>
                            <option value="">Pilih set data…</option>
                            {sets.data?.data.map((s) => (
                                <option key={s.kunci} value={s.kunci}>
                                    {s.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Bentuk laporan" htmlFor="mod">
                        <select id="mod" className={inputClass} value={mod} onChange={(e) => setMod(e.target.value as 'kumpulan' | 'rekod')}>
                            <option value="kumpulan">Ringkasan berkumpulan</option>
                            <option value="rekod">Senarai rekod</option>
                        </select>
                    </Field>
                    <Field label="Carta" htmlFor="carta">
                        <select id="carta" className={inputClass} value={def.carta ?? 'bar'} onChange={(e) => setDef({ ...def, carta: e.target.value as Definisi['carta'] })}>
                            <option value="bar">Bar</option>
                            <option value="garis">Garis (trend)</option>
                            <option value="pai">Pai</option>
                            <option value="jadual">Jadual sahaja</option>
                        </select>
                    </Field>
                </div>

                {set && mod === 'rekod' && (
                    <fieldset className="space-y-2">
                        <legend className="text-body-sm font-medium text-txt-black-700">Medan dipaparkan</legend>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            {medan.map((m) => (
                                <label key={m.kunci} className="flex items-center gap-2 text-body-sm">
                                    <input
                                        type="checkbox"
                                        checked={def.medan?.includes(m.kunci) ?? false}
                                        onChange={(e) => setDef({ ...def, medan: e.target.checked ? [...(def.medan ?? []), m.kunci] : (def.medan ?? []).filter((x) => x !== m.kunci) })}
                                    />
                                    {m.label}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                )}

                {set && mod === 'kumpulan' && (
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <fieldset className="space-y-2">
                            <legend className="text-body-sm font-medium text-txt-black-700">Kumpulkan mengikut (maks. 3)</legend>
                            {(def.kumpulan ?? []).map((g, i) => {
                                const [base, grain] = g.split(':');
                                return (
                                    <div key={i} className="flex gap-2">
                                        <select aria-label={`Kumpulan ${i + 1}`} className={inputClass} value={base} onChange={(e) => setDef({ ...def, kumpulan: (def.kumpulan ?? []).map((x, j) => (j === i ? (jenisOf(e.target.value) === 'tarikh' ? `${e.target.value}:bulan` : e.target.value) : x)) })}>
                                            {kumpulBoleh.map((m) => (
                                                <option key={m.kunci} value={m.kunci}>
                                                    {m.label}
                                                </option>
                                            ))}
                                        </select>
                                        {jenisOf(base) === 'tarikh' && (
                                            <select aria-label="Butiran tarikh" className={inputClass} value={grain ?? 'bulan'} onChange={(e) => setDef({ ...def, kumpulan: (def.kumpulan ?? []).map((x, j) => (j === i ? `${base}:${e.target.value}` : x)) })}>
                                                {Object.entries(BUTIRAN).map(([k, v]) => (
                                                    <option key={k} value={k}>
                                                        {v}
                                                    </option>
                                                ))}
                                            </select>
                                        )}
                                        <Button variant="outline" size="sm" onClick={() => setDef({ ...def, kumpulan: (def.kumpulan ?? []).filter((_, j) => j !== i) })}>
                                            Buang
                                        </Button>
                                    </div>
                                );
                            })}
                            {(def.kumpulan?.length ?? 0) < 3 && kumpulBoleh.length > 0 && (
                                <Button variant="secondary" size="sm" onClick={() => setDef({ ...def, kumpulan: [...(def.kumpulan ?? []), kumpulBoleh[0].jenis === 'tarikh' ? `${kumpulBoleh[0].kunci}:bulan` : kumpulBoleh[0].kunci] })}>
                                    + Tambah kumpulan
                                </Button>
                            )}
                        </fieldset>
                        <fieldset className="space-y-2">
                            <legend className="text-body-sm font-medium text-txt-black-700">Nilai dikira</legend>
                            {(def.agregat ?? []).map((a, i) => (
                                <div key={i} className="flex gap-2">
                                    <select aria-label={`Fungsi ${i + 1}`} className={inputClass} value={a.fungsi} onChange={(e) => ubahAgregat(i, { fungsi: e.target.value, medan: e.target.value === 'bilangan' ? undefined : a.medan ?? (['bilangan_benar', 'peratus_benar'].includes(e.target.value) ? medan.find((m) => m.jenis === 'bool')?.kunci : nomborBoleh[0]?.kunci) })}>
                                        {Object.entries(FUNGSI_LABEL).map(([k, v]) => (
                                            <option key={k} value={k}>
                                                {v}
                                            </option>
                                        ))}
                                    </select>
                                    {a.fungsi !== 'bilangan' && (
                                        <select aria-label="Medan" className={inputClass} value={a.medan ?? ''} onChange={(e) => ubahAgregat(i, { medan: e.target.value })}>
                                            {medan
                                                .filter((m) => (['bilangan_benar', 'peratus_benar'].includes(a.fungsi) ? m.jenis === 'bool' : ['min', 'maks'].includes(a.fungsi) ? ['nombor', 'wang', 'tarikh'].includes(m.jenis) : ['nombor', 'wang'].includes(m.jenis)))
                                                .map((m) => (
                                                    <option key={m.kunci} value={m.kunci}>
                                                        {m.label}
                                                    </option>
                                                ))}
                                        </select>
                                    )}
                                    <Button variant="outline" size="sm" onClick={() => setDef({ ...def, agregat: (def.agregat ?? []).filter((_, j) => j !== i) })}>
                                        Buang
                                    </Button>
                                </div>
                            ))}
                            <Button variant="secondary" size="sm" onClick={() => setDef({ ...def, agregat: [...(def.agregat ?? []), { fungsi: 'bilangan' }] })}>
                                + Tambah nilai
                            </Button>
                        </fieldset>
                    </div>
                )}

                {set && (
                    <fieldset className="space-y-2">
                        <legend className="text-body-sm font-medium text-txt-black-700">Penapis</legend>
                        {(def.penapis ?? []).map((p, i) => {
                            const jenis = jenisOf(p.medan);
                            const tanpaNilai = ['kosong', 'tidak_kosong', 'benar', 'palsu'].includes(p.op);
                            const nilai = Array.isArray(p.nilai) ? p.nilai : p.nilai ? [p.nilai] : [];
                            return (
                                <div key={i} className="grid grid-cols-1 sm:grid-cols-4 gap-2">
                                    <select aria-label={`Medan penapis ${i + 1}`} className={inputClass} value={p.medan} onChange={(e) => ubahPenapis(i, { medan: e.target.value, op: opsFor(jenisOf(e.target.value))[0], nilai: null })}>
                                        {medan.map((m) => (
                                            <option key={m.kunci} value={m.kunci}>
                                                {m.label}
                                            </option>
                                        ))}
                                    </select>
                                    <select aria-label="Operator" className={inputClass} value={p.op} onChange={(e) => ubahPenapis(i, { op: e.target.value, nilai: e.target.value === 'antara' ? ['', ''] : null })}>
                                        {opsFor(jenis).map((o) => (
                                            <option key={o} value={o}>
                                                {OP_LABEL[o]}
                                            </option>
                                        ))}
                                    </select>
                                    {tanpaNilai ? (
                                        <span />
                                    ) : p.op === 'antara' ? (
                                        <div className="flex gap-2">
                                            {[0, 1].map((k) => (
                                                <input key={k} aria-label={k ? 'Hingga' : 'Dari'} type={jenis === 'tarikh' ? 'date' : 'text'} className={inputClass} value={nilai[k] ?? ''} onChange={(e) => ubahPenapis(i, { nilai: k ? [nilai[0] ?? '', e.target.value] : [e.target.value, nilai[1] ?? ''] })} />
                                            ))}
                                        </div>
                                    ) : (
                                        <input
                                            aria-label="Nilai"
                                            type={jenis === 'tarikh' ? 'date' : 'text'}
                                            className={inputClass}
                                            value={p.op === 'dalam' ? nilai.join(', ') : String(p.nilai ?? '')}
                                            placeholder={p.op === 'dalam' ? 'nilai1, nilai2' : ''}
                                            onChange={(e) => ubahPenapis(i, { nilai: p.op === 'dalam' ? e.target.value.split(',').map((s) => s.trim()).filter(Boolean) : e.target.value })}
                                        />
                                    )}
                                    <Button variant="outline" size="sm" onClick={() => setDef({ ...def, penapis: (def.penapis ?? []).filter((_, j) => j !== i) })}>
                                        Buang
                                    </Button>
                                </div>
                            );
                        })}
                        {medan.length > 0 && (
                            <Button variant="secondary" size="sm" onClick={() => setDef({ ...def, penapis: [...(def.penapis ?? []), { medan: medan[0].kunci, op: opsFor(medan[0].jenis)[0], nilai: null }] })}>
                                + Tambah penapis
                            </Button>
                        )}
                    </fieldset>
                )}

                {set && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        <Field label="Medan tempoh" htmlFor="tmedan">
                            <select id="tmedan" className={inputClass} value={def.tempoh?.medan ?? set.tarikh ?? ''} onChange={(e) => setDef({ ...def, tempoh: { ...(def.tempoh ?? {}), medan: e.target.value } })}>
                                {medan.filter((m) => m.jenis === 'tarikh').map((m) => (
                                    <option key={m.kunci} value={m.kunci}>
                                        {m.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Tempoh" htmlFor="tpra">
                            <select id="tpra" className={inputClass} value={def.tempoh?.pratetap ?? ''} onChange={(e) => setDef({ ...def, tempoh: { medan: def.tempoh?.medan ?? set.tarikh ?? undefined, pratetap: e.target.value || undefined } })}>
                                <option value="">Tiada / tarikh khusus</option>
                                {Object.entries(PRATETAP_LABEL).map(([k, v]) => (
                                    <option key={k} value={k}>
                                        {v}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Dari" htmlFor="tdari">
                            <input id="tdari" type="date" className={inputClass} value={def.tempoh?.dari ?? ''} disabled={!!def.tempoh?.pratetap} onChange={(e) => setDef({ ...def, tempoh: { ...(def.tempoh ?? {}), medan: def.tempoh?.medan ?? set.tarikh ?? undefined, dari: e.target.value || undefined } })} />
                        </Field>
                        <Field label="Hingga" htmlFor="thingga">
                            <input id="thingga" type="date" className={inputClass} value={def.tempoh?.hingga ?? ''} disabled={!!def.tempoh?.pratetap} onChange={(e) => setDef({ ...def, tempoh: { ...(def.tempoh ?? {}), medan: def.tempoh?.medan ?? set.tarikh ?? undefined, hingga: e.target.value || undefined } })} />
                        </Field>
                        <Field label="Bandingan" htmlFor="band">
                            <select id="band" className={inputClass} value={def.bandingan ?? ''} onChange={(e) => setDef({ ...def, bandingan: e.target.value ? 'tahun_lepas' : null })}>
                                <option value="">Tiada</option>
                                <option value="tahun_lepas">Tahun ke tahun</option>
                            </select>
                        </Field>
                    </div>
                )}

                {set && (
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <Field label="Susun mengikut" htmlFor="susun">
                            <select
                                id="susun"
                                className={inputClass}
                                value={def.susunan?.[0]?.medan ?? ''}
                                onChange={(e) => setDef({ ...def, susunan: e.target.value ? [{ medan: e.target.value, arah: def.susunan?.[0]?.arah ?? 'desc' }] : [] })}
                            >
                                <option value="">Lalai</option>
                                {(mod === 'kumpulan' ? [...(def.kumpulan ?? []).map((g) => ({ k: g, l: medan.find((m) => m.kunci === g.split(':')[0])?.label ?? g })), ...(def.agregat ?? []).map((a) => ({ k: a.fungsi === 'bilangan' ? 'bilangan' : a.fungsi === 'bilangan_benar' ? `bilangan_${a.medan}` : a.fungsi === 'peratus_benar' ? `peratus_${a.medan}` : `${a.fungsi}_${a.medan}`, l: `${FUNGSI_LABEL[a.fungsi]} ${a.medan ? medan.find((m) => m.kunci === a.medan)?.label ?? '' : ''}` }))] : (def.medan?.length ? def.medan : medan.map((m) => m.kunci)).map((k) => ({ k, l: medan.find((m) => m.kunci === k)?.label ?? k }))).map((o) => (
                                    <option key={o.k} value={o.k}>
                                        {o.l}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Arah" htmlFor="arah">
                            <select id="arah" className={inputClass} value={def.susunan?.[0]?.arah ?? 'desc'} onChange={(e) => setDef({ ...def, susunan: def.susunan?.[0] ? [{ ...def.susunan[0], arah: e.target.value as 'asc' | 'desc' }] : [] })}>
                                <option value="desc">Menurun</option>
                                <option value="asc">Menaik</option>
                            </select>
                        </Field>
                        <div className="flex items-end">
                            <Button onClick={() => setPratonton(akhir())} disabled={!def.set}>
                                Pratonton laporan
                            </Button>
                        </div>
                    </div>
                )}
            </Card>

            {pratonton && pratonton.set && <Viewer key={JSON.stringify(pratonton)} sumber={{ adhoc: pratonton, tajuk: nama || undefined }} />}

            {def.set && (
                <Card title="Simpan definisi">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <Field label="Nama laporan" htmlFor="nama">
                            <input id="nama" className={inputClass} value={nama} onChange={(e) => setNama(e.target.value)} />
                        </Field>
                        <Field label="Perkongsian" htmlFor="kongsi" help="Penerima hanya melihat data dalam skop dan kebenaran mereka sendiri.">
                            <select id="kongsi" className={inputClass} value={kongsi} onChange={(e) => setKongsi(e.target.value as typeof kongsi)}>
                                <option value="peribadi">Peribadi</option>
                                <option value="peranan">Peranan tertentu</option>
                                <option value="semua">Semua pengguna laporan</option>
                            </select>
                        </Field>
                        {kongsi === 'peranan' && (
                            <Field label="Kod peranan (dipisah koma)" htmlFor="kperanan">
                                <input id="kperanan" className={inputClass} value={peranan} onChange={(e) => setPeranan(e.target.value)} />
                            </Field>
                        )}
                    </div>
                    <div className="flex justify-end">
                        <Button onClick={() => simpan.mutate()} disabled={!nama || simpan.isPending}>
                            {asal.data?.milik_saya ? 'Kemas kini' : 'Simpan'}
                        </Button>
                    </div>
                </Card>
            )}
        </PageContainer>
    );
}

export default function PembinaPage() {
    return (
        <Suspense>
            <Pembina />
        </Suspense>
    );
}
