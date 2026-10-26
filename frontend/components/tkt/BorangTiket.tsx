'use client';

import { useEffect, useMemo, useRef, useState } from 'react';
import Link from 'next/link';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ralat, sesiBaharu, tangkapKonteks, tangkapSkrin, tktApi, useRujukan, type ArtikelCadangan, type KonteksSkrin } from '@/lib/tkt';

const input = 'block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm';
const DRAF = 'tkt:draf';

interface Draf {
    tajuk: string;
    penerangan: string;
    kategori: string;
    kesan: string;
    kesegeraan: string;
    keterukan: string;
    isu_keselamatan: boolean;
}

const KOSONG: Draf = { tajuk: '', penerangan: '', kategori: 'ralat', kesan: 'seorang', kesegeraan: 'sederhana', keterukan: '', isu_keselamatan: false };

function bacaDraf(): Draf {
    try {
        const raw = sessionStorage.getItem(DRAF);
        return raw ? { ...KOSONG, ...(JSON.parse(raw) as Partial<Draf>) } : KOSONG;
    } catch {
        return KOSONG;
    }
}

/**
 * SRS-TKT-01..07 ticket form: context captured automatically (shown to the
 * user), knowledge-base suggestions while typing (before submit — at least
 * three, each opening in a new tab so work is not lost), "this solved it"
 * deflection, severity suggested from the impact × urgency matrix,
 * consented screenshot, attachments. The draft survives closing the panel.
 */
export function BorangTiket({ konteksAwal, peranan, onSelesai, onNavigasi }: { konteksAwal?: KonteksSkrin | null; peranan?: string[]; onSelesai?: (nombor: string | null, id: string | null) => void; onNavigasi?: () => void }) {
    const { data: ref } = useRujukan();
    const [d, setD] = useState<Draf>(KOSONG);
    const [sesi] = useState(sesiBaharu);
    const [konteks, setKonteks] = useState<KonteksSkrin | null>(konteksAwal ?? null);
    const [artikel, setArtikel] = useState<ArtikelCadangan[]>([]);
    const [serupa, setSerupa] = useState<Array<{ id: string; nombor_rujukan: string; tajuk: string }>>([]);
    const [mencari, setMencari] = useState(false);
    const [setuju, setSetuju] = useState(false);
    const [skrin, setSkrin] = useState<Blob | null>(null);
    const [pratonton, setPratonton] = useState<string | null>(null);
    const [fail, setFail] = useState<File[]>([]);
    const [hantar, setHantar] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [hasil, setHasil] = useState<{ nombor: string; id: string } | null>(null);
    const [kendiri, setKendiri] = useState<string | null>(null);
    const [lihatKonteks, setLihatKonteks] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        // eslint-disable-next-line react-hooks/set-state-in-effect -- restore the unsent draft once, after mount (sessionStorage is client-only)
        setD(bacaDraf());
        // Full-page form: capture this screen's context on the client only (no hydration mismatch).
        if (!konteksAwal) setKonteks(tangkapKonteks());
        // eslint-disable-next-line react-hooks/exhaustive-deps -- once, on mount
    }, []);
    useEffect(() => {
        try {
            sessionStorage.setItem(DRAF, JSON.stringify(d));
        } catch {
            /* ignore */
        }
    }, [d]);
    useEffect(() => () => (pratonton ? URL.revokeObjectURL(pratonton) : undefined), [pratonton]);

    // SRS-TKT-06: suggestions while typing (debounced).
    useEffect(() => {
        if (timer.current) clearTimeout(timer.current);
        const teks = `${d.tajuk} ${d.penerangan}`.trim();
        if (teks.length < 12) return;
        timer.current = setTimeout(() => {
            setMencari(true);
            tktApi
                .cadangan({ teks: d.penerangan || d.tajuk, tajuk: d.tajuk || undefined, sesi, modul: konteks?.modul })
                .then((r) => {
                    setArtikel(r.data.artikel);
                    setSerupa(r.data.tiket_serupa);
                })
                .catch(() => undefined)
                .finally(() => setMencari(false));
        }, 700);
        return () => {
            if (timer.current) clearTimeout(timer.current);
        };
    }, [d.tajuk, d.penerangan, sesi, konteks?.modul]);

    const dicadang = useMemo(() => {
        if (d.isu_keselamatan) return 1;
        if (d.kategori === 'soalan') return 4;
        return ref?.matriks?.[d.kesan]?.[d.kesegeraan] ?? 3;
    }, [ref, d.kesan, d.kesegeraan, d.kategori, d.isu_keselamatan]);
    const keterukan = d.keterukan || String(dicadang);
    const set = <K extends keyof Draf>(k: K, v: Draf[K]) => setD((x) => ({ ...x, [k]: v }));

    async function ambilSkrin() {
        if (!setuju) return;
        const blob = await tangkapSkrin();
        if (!blob) {
            setError('Tangkapan skrin dibatalkan atau tidak disokong oleh pelayar ini. Anda boleh melampirkan fail gambar secara manual.');
            return;
        }
        setError(null);
        setSkrin(blob);
        setPratonton(URL.createObjectURL(blob));
    }

    async function selesaiSendiri() {
        try {
            const r = await tktApi.selesaiSendiri(sesi);
            setKendiri(r.message);
            sessionStorage.removeItem(DRAF);
            setD(KOSONG);
        } catch (e) {
            setError(ralat(e));
        }
    }

    async function submit(e: React.FormEvent) {
        e.preventDefault();
        setHantar(true);
        setError(null);
        const form = new FormData();
        form.set('tajuk', d.tajuk);
        form.set('penerangan', d.penerangan);
        form.set('kategori', d.kategori);
        form.set('kesan', d.kesan);
        form.set('kesegeraan', d.kesegeraan);
        form.set('keterukan', keterukan);
        form.set('isu_keselamatan', d.isu_keselamatan ? '1' : '0');
        form.set('sesi', sesi);
        if (konteks) form.set('konteks', JSON.stringify({ ...konteks, modul: konteks.modul }));
        if (skrin && setuju) {
            form.set('tangkapan_skrin', new File([skrin], 'tangkapan-skrin.png', { type: 'image/png' }));
            form.set('persetujuan_tangkapan_skrin', '1');
        }
        fail.forEach((f) => form.append('fail[]', f));
        try {
            const r = await tktApi.cipta(form);
            setHasil({ nombor: r.data.nombor_rujukan, id: r.data.id });
            try {
                sessionStorage.removeItem(DRAF);
            } catch {
                /* ignore */
            }
            setD(KOSONG);
            setSkrin(null);
            setFail([]);
            onSelesai?.(r.data.nombor_rujukan, r.data.id);
        } catch (err) {
            setError(ralat(err));
        } finally {
            setHantar(false);
        }
    }

    if (hasil) {
        return (
            <div className="space-y-3">
                <Alert type="success">Tiket {hasil.nombor} telah dibuka. Anda akan dimaklumkan tentang perkembangannya.</Alert>
                <Link href={`/admin/tkt/tiket/${hasil.id}`} onClick={onNavigasi} className="text-body-sm font-medium text-txt-primary hover:underline">
                    Lihat tiket {hasil.nombor}
                </Link>
            </div>
        );
    }
    if (kendiri) return <Alert type="success">{kendiri}</Alert>;

    return (
        <form onSubmit={submit} className="space-y-4">
            {error && <Alert type="error">{error}</Alert>}
            <div>
                <label htmlFor="tkt-tajuk" className="mb-1.5 block text-body-sm font-medium text-txt-black-700">
                    Tajuk masalah
                </label>
                <input id="tkt-tajuk" className={input} value={d.tajuk} onChange={(e) => set('tajuk', e.target.value)} maxLength={200} required minLength={5} />
            </div>
            <div>
                <label htmlFor="tkt-penerangan" className="mb-1.5 block text-body-sm font-medium text-txt-black-700">
                    Penerangan
                </label>
                <textarea id="tkt-penerangan" rows={4} className={input} value={d.penerangan} onChange={(e) => set('penerangan', e.target.value)} required minLength={10} placeholder="Apa yang anda cuba lakukan, apa yang berlaku, dan mesej ralat (jika ada)." />
            </div>

            <section aria-live="polite" className="rounded-md border border-otl-gray-200 bg-bg-washed p-3 space-y-2">
                <p className="text-body-sm font-medium text-txt-black-900">Cadangan Pangkalan Pengetahuan {mencari && <span className="text-txt-black-500">(mencari…)</span>}</p>
                {artikel.length === 0 ? (
                    <p className="text-body-xs text-txt-black-500">Taip penerangan untuk melihat artikel berkaitan sebelum menghantar tiket.</p>
                ) : (
                    <ul className="space-y-2">
                        {artikel.map((a) => (
                            <li key={a.pautan} className="min-w-0">
                                <a href={a.pautan} target="_blank" rel="noopener" onClick={() => tktApi.klik(sesi, a.pautan).catch(() => undefined)} className="text-body-sm font-medium text-txt-primary hover:underline break-words">
                                    {a.dokumen}
                                    {a.seksyen ? ` · ${a.seksyen}` : ''}
                                </a>
                                <p className="text-body-xs text-txt-black-500 line-clamp-2 break-words">{a.petikan}</p>
                            </li>
                        ))}
                    </ul>
                )}
                {artikel.length > 0 && (
                    <Button type="button" variant="secondary" size="sm" onClick={selesaiSendiri}>
                        Artikel ini menyelesaikan masalah saya
                    </Button>
                )}
                {serupa.length > 0 && (
                    <p className="text-body-xs text-txt-warning">
                        Anda sudah mempunyai tiket serupa:{' '}
                        {serupa.map((s) => (
                            <Link key={s.id} href={`/admin/tkt/tiket/${s.id}`} onClick={onNavigasi} className="underline mr-2">
                                {s.nombor_rujukan}
                            </Link>
                        ))}
                    </p>
                )}
            </section>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label htmlFor="tkt-kat" className="mb-1.5 block text-body-sm font-medium text-txt-black-700">
                        Kategori
                    </label>
                    <select id="tkt-kat" className={input} value={d.kategori} onChange={(e) => set('kategori', e.target.value)}>
                        {(ref?.kategori ?? []).map((k) => (
                            <option key={k.kod} value={k.kod}>
                                {k.nama}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label htmlFor="tkt-kesan" className="mb-1.5 block text-body-sm font-medium text-txt-black-700">
                        Siapa terjejas?
                    </label>
                    <select id="tkt-kesan" className={input} value={d.kesan} onChange={(e) => set('kesan', e.target.value)}>
                        {(ref?.kesan ?? []).map((k) => (
                            <option key={k.kod} value={k.kod}>
                                {k.nama}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label htmlFor="tkt-segera" className="mb-1.5 block text-body-sm font-medium text-txt-black-700">
                        Kesegeraan
                    </label>
                    <select id="tkt-segera" className={input} value={d.kesegeraan} onChange={(e) => set('kesegeraan', e.target.value)}>
                        {(ref?.kesegeraan ?? []).map((k) => (
                            <option key={k.kod} value={k.kod}>
                                {k.nama}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label htmlFor="tkt-sev" className="mb-1.5 block text-body-sm font-medium text-txt-black-700">
                        Tahap keterukan
                    </label>
                    <select id="tkt-sev" className={input} value={keterukan} onChange={(e) => set('keterukan', e.target.value)} disabled={d.isu_keselamatan}>
                        {(ref?.keterukan ?? []).map((k) => (
                            <option key={k.kod} value={k.kod}>
                                {k.nama}
                                {Number(k.kod) === dicadang ? ' (dicadangkan)' : ''}
                            </option>
                        ))}
                    </select>
                </div>
            </div>
            <label className="flex items-start gap-2 text-body-sm text-txt-black-700">
                <input type="checkbox" className="mt-0.5 rounded border-otl-gray-300" checked={d.isu_keselamatan} onChange={(e) => set('isu_keselamatan', e.target.checked)} />
                Ini isu keselamatan (cth. akaun dicerobohi, data terdedah) — dihadkan kepada pegawai yang dibenarkan dan dianggap tahap keterukan 1.
            </label>

            <section className="rounded-md border border-otl-gray-200 p-3 space-y-2">
                <p className="text-body-sm font-medium text-txt-black-900">Tangkapan skrin (pilihan)</p>
                <label className="flex items-start gap-2 text-body-xs text-txt-black-700">
                    <input type="checkbox" className="mt-0.5 rounded border-otl-gray-300" checked={setuju} onChange={(e) => { setSetuju(e.target.checked); if (!e.target.checked) { setSkrin(null); setPratonton(null); } }} />
                    Saya bersetuju tangkapan skrin dimuat naik bersama tiket ini. Saya faham ia mungkin memaparkan maklumat pada skrin saya; pelayar akan meminta saya memilih skrin/tab yang dikongsi dan imej boleh dipadam sebelum dihantar.
                </label>
                <div className="flex flex-wrap items-center gap-2">
                    <Button type="button" variant="secondary" size="sm" disabled={!setuju} onClick={ambilSkrin}>
                        Ambil tangkapan skrin
                    </Button>
                    {skrin && (
                        <Button type="button" variant="outline" size="sm" onClick={() => { setSkrin(null); setPratonton(null); }}>
                            Buang tangkapan skrin
                        </Button>
                    )}
                </div>
                {/* eslint-disable-next-line @next/next/no-img-element -- local blob preview */}
                {pratonton && <img src={pratonton} alt="Pratonton tangkapan skrin" className="max-h-40 rounded border border-otl-gray-200" />}
                <div>
                    <label htmlFor="tkt-fail" className="mb-1 block text-body-xs font-medium text-txt-black-700">
                        Fail sokongan (maks. 5; jenis, saiz dan imbasan virus disemak oleh sistem)
                    </label>
                    <input id="tkt-fail" type="file" multiple className="block w-full text-body-xs" onChange={(e) => setFail(Array.from(e.target.files ?? []).slice(0, 5))} />
                </div>
            </section>

            {konteks && (
                <section className="rounded-md border border-otl-gray-200 p-3">
                    <button type="button" className="text-body-sm font-medium text-txt-primary" onClick={() => setLihatKonteks((v) => !v)} aria-expanded={lihatKonteks}>
                        {lihatKonteks ? '▾' : '▸'} Maklumat yang ditangkap secara automatik
                    </button>
                    {lihatKonteks && (
                        <dl className="mt-2 grid grid-cols-1 gap-1 text-body-xs text-txt-black-700">
                            <div className="break-words"><dt className="inline font-medium">Skrin: </dt><dd className="inline">{konteks.skrin}</dd></div>
                            <div><dt className="inline font-medium">Modul: </dt><dd className="inline">{konteks.modul}</dd></div>
                            <div className="break-all"><dt className="inline font-medium">URL: </dt><dd className="inline">{konteks.url}</dd></div>
                            <div><dt className="inline font-medium">Kod fungsi: </dt><dd className="inline">{konteks.kod_fungsi}</dd></div>
                            {konteks.rujukan_rekod && <div className="break-all"><dt className="inline font-medium">Rekod: </dt><dd className="inline">{konteks.rujukan_rekod}</dd></div>}
                            {peranan && <div><dt className="inline font-medium">Peranan: </dt><dd className="inline">{peranan.join(', ') || '—'}</dd></div>}
                            <div className="break-words"><dt className="inline font-medium">Pelayar: </dt><dd className="inline">{konteks.pelayar}</dd></div>
                            <div><dt className="inline font-medium">Versi UI / skrin: </dt><dd className="inline">{konteks.versi_ui} · {konteks.saiz_skrin}</dd></div>
                            <div><dt className="inline font-medium">ID permintaan terkini: </dt><dd className="inline">{konteks.id_permintaan.length}</dd></div>
                        </dl>
                    )}
                    <p className="mt-1 text-body-xs text-txt-black-500">ID pengguna, peranan dan versi sistem direkod oleh pelayan. No. KP, e-mel dan nombor telefon disamarkan.</p>
                </section>
            )}

            <div className="flex flex-wrap items-center justify-end gap-2">
                <Badge variant="neutral">Tahap keterukan {keterukan}</Badge>
                <Button type="submit" disabled={hantar || d.tajuk.trim().length < 5 || d.penerangan.trim().length < 10}>
                    {hantar ? 'Menghantar…' : 'Hantar tiket'}
                </Button>
            </div>
        </form>
    );
}
