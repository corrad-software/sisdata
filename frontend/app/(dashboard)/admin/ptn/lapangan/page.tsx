'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Callout } from '@/components/ui/Callout';
import { api, ApiError } from '@/lib/api';
import { ptnApi, ralat, tarikh, type BundleLapangan, type Lawatan, type Templat, type Tugasan } from '@/lib/ptn';
import * as store from '@/lib/ptn-offline';
import { Field, inputClass } from '../_components/common';

/*
 * SRS-PTN-05 field app (PWA, offline). Download assigned visits → record the
 * checklist, GPS, geotagged photos and recommendations offline (encrypted on
 * the device) → sync automatically when back online; synced visits are
 * deleted from the device. Conflicts (schedule changed while offline) are
 * kept on the server for the supervisor — nothing is lost.
 */

type Status = 'draf' | 'sedia' | 'gagal' | 'konflik';

interface Jawapan {
    status_item: 'baik' | 'isu' | 'kritikal';
    catatan?: string;
    kategori_isu?: string;
    tahap_keterukan?: string;
}

interface Draf {
    id: string;
    id_jadual: string;
    versi_jadual: number;
    lot_id: string;
    no_lot: string;
    id_templat: number | null;
    tarikh_lawatan: string;
    masa_mula: string;
    masa_tamat?: string;
    lat?: number;
    lng?: number;
    ketepatan_gps_m?: number;
    keadaan_tapak?: string;
    ringkasan_penemuan?: string;
    isu_pencerobohan?: string;
    isu_penyelenggaraan?: string;
    cadangan_tindakan?: string;
    item: Record<string, Jawapan>;
    penyelenggaraan: Array<{ jenis: string; keterangan: string; keutamaan: string }>;
    cadangan: Array<{ medan: string; nilai_dicadang: string; justifikasi: string }>;
    status: Status;
    mesej?: string;
    id_pelayan?: string;
    dikemaskini: string;
}

interface MetaGambar {
    lat: number | null;
    lng: number | null;
    ketepatan_m: number | null;
    arah_kamera: number | null;
    tarikh_masa: string;
    sumber_gambar: 'kamera' | 'galeri';
    kod_item?: string;
    kapsyen?: string;
    dalam: boolean | null;
    dimuat_naik?: boolean;
    nama: string;
}

function lokasi(): Promise<GeolocationPosition | null> {
    return new Promise((resolve) => {
        if (!('geolocation' in navigator)) return resolve(null);
        navigator.geolocation.getCurrentPosition(resolve, () => resolve(null), { enableHighAccuracy: true, timeout: 15_000, maximumAge: 10_000 });
    });
}

function useDalamTalian() {
    const [online, setOnline] = useState(true);
    useEffect(() => {
        const set = () => setOnline(navigator.onLine);
        set();
        window.addEventListener('online', set);
        window.addEventListener('offline', set);
        return () => {
            window.removeEventListener('online', set);
            window.removeEventListener('offline', set);
        };
    }, []);
    return online;
}

/** Latest compass heading (degrees from north) from DeviceOrientation, when the device has one. */
function useArah() {
    const arah = useRef<number | null>(null);
    useEffect(() => {
        const on = (e: DeviceOrientationEvent & { webkitCompassHeading?: number }) => {
            if (typeof e.webkitCompassHeading === 'number') arah.current = e.webkitCompassHeading;
            else if (e.absolute && typeof e.alpha === 'number') arah.current = (360 - e.alpha) % 360;
        };
        window.addEventListener('deviceorientation', on as EventListener);
        return () => window.removeEventListener('deviceorientation', on as EventListener);
    }, []);
    return arah;
}

function Kunci({ onBuka }: { onBuka: (k: CryptoKey) => void }) {
    const [ada, setAda] = useState<boolean | null>(null);
    const [pin, setPin] = useState('');
    const [pin2, setPin2] = useState('');
    const [sibuk, setSibuk] = useState(false);
    const [err, setErr] = useState<string | null>(null);
    useEffect(() => {
        store.adaPin().then(setAda).catch(() => setAda(false));
    }, []);
    const hantar = async () => {
        setErr(null);
        setSibuk(true);
        try {
            if (!ada && pin !== pin2) throw new Error('PIN tidak sepadan.');
            onBuka(ada ? await store.buka(pin) : await store.tetapkanPin(pin));
        } catch (e) {
            setErr(e instanceof Error ? e.message : 'Ralat.');
        } finally {
            setSibuk(false);
        }
    };
    if (ada === null) return <p className="p-4 text-body-sm">Memuatkan…</p>;
    return (
        <form className="max-w-sm mx-auto p-4 space-y-4" onSubmit={(e) => { e.preventDefault(); hantar(); }}>
            <h1 className="font-heading font-semibold text-heading-2xs">Aplikasi Lapangan — Pemantauan Tanah</h1>
            <p className="text-body-sm text-txt-black-700">{ada ? 'Masukkan PIN peranti untuk membuka data lapangan yang disulitkan.' : 'Tetapkan PIN peranti (6–12 digit). Data lawatan pada peranti ini disulitkan dengan kunci daripada PIN ini.'}</p>
            <Field label="PIN" htmlFor="pin"><input id="pin" type="password" inputMode="numeric" autoComplete="off" className={inputClass} value={pin} onChange={(e) => setPin(e.target.value)} /></Field>
            {!ada && <Field label="Sahkan PIN" htmlFor="pin2"><input id="pin2" type="password" inputMode="numeric" autoComplete="off" className={inputClass} value={pin2} onChange={(e) => setPin2(e.target.value)} /></Field>}
            {err && <p role="alert" className="text-body-sm text-txt-danger">{err}</p>}
            <Button type="submit" className="w-full" disabled={sibuk || pin.length < 6}>{sibuk ? 'Memproses…' : ada ? 'Buka' : 'Tetapkan PIN'}</Button>
            {ada && (
                <button type="button" className="text-body-xs text-txt-danger underline" onClick={async () => { if (window.confirm('Padam SEMUA data lapangan pada peranti ini (termasuk lawatan belum disegerak)?')) { await store.kosongkan(); setAda(false); } }}>
                    Lupa PIN — padam data peranti
                </button>
            )}
        </form>
    );
}

function Borang({ kunci, tugasan, templat, draf, bundle, onTutup }: { kunci: CryptoKey; tugasan: Tugasan; templat: Templat | null; draf: Draf; bundle: BundleLapangan; onTutup: () => void }) {
    const [d, setD] = useState<Draf>(draf);
    const [gambar, setGambar] = useState<Array<{ id: string; meta: MetaGambar; url: string }>>([]);
    const arah = useArah();
    const ref = bundle.rujukan;

    const muatGambar = useCallback(
        () =>
            store.gambarUntuk<MetaGambar>(kunci, d.id).then((rows) =>
                setGambar((old) => {
                    old.forEach((g) => URL.revokeObjectURL(g.url));
                    return rows.map((r) => ({ id: r.id, meta: r.meta, url: URL.createObjectURL(r.blob) }));
                }),
            ),
        [kunci, d.id],
    );
    useEffect(() => {
        store.gambarUntuk<MetaGambar>(kunci, d.id).then((rows) => setGambar(rows.map((r) => ({ id: r.id, meta: r.meta, url: URL.createObjectURL(r.blob) }))));
    }, [kunci, d.id]);

    const simpan = async (next: Draf) => {
        const v = { ...next, dikemaskini: new Date().toISOString() };
        setD(v);
        await store.simpan(kunci, v.id, 'lawatan', v);
    };
    const set = (k: keyof Draf, v: unknown) => simpan({ ...d, [k]: v } as Draf);
    const setItem = (kod: string, patch: Partial<Jawapan>) => simpan({ ...d, item: { ...d.item, [kod]: { ...(d.item[kod] ?? { status_item: 'baik' }), ...patch } } });

    const rekodLokasi = async () => {
        const p = await lokasi();
        if (!p) return toast.error('Lokasi GPS tidak dapat diperoleh.');
        await simpan({ ...d, lat: p.coords.latitude, lng: p.coords.longitude, ketepatan_gps_m: Math.round(p.coords.accuracy) });
        const dalam = store.dalamPoligon(p.coords.longitude, p.coords.latitude, tugasan.geometri);
        toast[dalam === false ? 'warning' : 'success'](dalam === false ? 'Anda kelihatan di luar sempadan lot.' : 'Lokasi direkod.');
    };

    const tambahGambar = async (files: FileList | null, sumber: 'kamera' | 'galeri', kod_item?: string) => {
        if (!files) return;
        for (const file of Array.from(files)) {
            const p = sumber === 'kamera' ? await lokasi() : null;
            const meta: MetaGambar = {
                lat: p?.coords.latitude ?? null, lng: p?.coords.longitude ?? null, ketepatan_m: p ? Math.round(p.coords.accuracy) : null,
                arah_kamera: sumber === 'kamera' ? arah.current : null, tarikh_masa: new Date(sumber === 'galeri' ? file.lastModified : Date.now()).toISOString(),
                sumber_gambar: sumber, kod_item, dalam: p ? store.dalamPoligon(p.coords.longitude, p.coords.latitude, tugasan.geometri) : null, nama: file.name || 'gambar.jpg',
            };
            const blob = sumber === 'kamera' ? await store.kecilkan(file) : file;
            await store.simpanGambar(kunci, store.uuid(), d.id, meta, blob);
        }
        await muatGambar();
    };
    const buangGambar = async (id: string) => { await store.padam(id); await muatGambar(); };

    const items = templat?.senarai_item ?? [];
    const lengkap = items.every((i) => d.item[i.kod]) && !!d.keadaan_tapak && gambar.length > 0;
    const sebelum = tugasan.lawatan_terdahulu;

    return (
        <div className="space-y-4 p-4 pb-24">
            <div className="flex items-center justify-between gap-2">
                <Button variant="secondary" size="sm" onClick={onTutup}>← Senarai</Button>
                <Badge variant={d.status === 'sedia' ? 'info' : d.status === 'gagal' ? 'danger' : 'neutral'}>{{ draf: 'Draf', sedia: 'Sedia disegerak', gagal: 'Gagal disegerak', konflik: 'Konflik' }[d.status]}</Badge>
            </div>
            <div>
                <h2 className="font-heading font-semibold text-body-lg">{tugasan.no_lot} · {tugasan.mukim}</h2>
                <p className="text-body-sm text-txt-black-700">{tugasan.objektif} · sasaran {tarikh(tugasan.tarikh_sasaran)}</p>
                {d.mesej && <p role="alert" className="text-body-sm text-txt-danger">{d.mesej}</p>}
            </div>

            <section className="rounded-lg border border-otl-gray-200 bg-bg-white p-3 space-y-2">
                <h3 className="font-semibold text-body-md">1. Lokasi lawatan</h3>
                <p className="text-body-sm">{d.lat ? `${d.lat.toFixed(6)}, ${d.lng?.toFixed(6)} (±${d.ketepatan_gps_m} m)` : 'Belum direkod'}{d.lat && tugasan.geometri && (store.dalamPoligon(d.lng ?? 0, d.lat, tugasan.geometri) === false ? ' — di luar sempadan lot' : ' — dalam sempadan lot')}</p>
                <Button size="sm" onClick={rekodLokasi}>Rekod lokasi GPS</Button>
            </section>

            <section className="rounded-lg border border-otl-gray-200 bg-bg-white p-3 space-y-3">
                <h3 className="font-semibold text-body-md">2. Senarai semak ({Object.keys(d.item).length}/{items.length})</h3>
                {items.map((it) => {
                    const j = d.item[it.kod];
                    const lalu = sebelum?.item.find((x) => x.kod_item === it.kod);
                    return (
                        <fieldset key={it.kod} className="border-t border-otl-divider pt-2 space-y-2">
                            <legend className="text-body-sm font-medium">{it.kod} · {it.label}</legend>
                            <p className="text-body-xs text-txt-black-500">{ref.kategori_item[it.kategori]}{lalu ? ` · lawatan lalu: ${ref.status_item[lalu.status_item]}` : ''}</p>
                            <div className="flex gap-2" role="radiogroup" aria-label={`Status ${it.kod}`}>
                                {(['baik', 'isu', 'kritikal'] as const).map((s) => (
                                    <label key={s} className={`flex-1 text-center rounded-md border px-2 py-2 text-body-sm cursor-pointer ${j?.status_item === s ? (s === 'baik' ? 'bg-bg-success-50 border-success-600' : s === 'isu' ? 'bg-bg-warning-50 border-warning-600' : 'bg-bg-danger-50 border-danger-600') : 'border-otl-gray-300'}`}>
                                        <input type="radio" className="sr-only" name={`st-${it.kod}`} checked={j?.status_item === s} onChange={() => setItem(it.kod, { status_item: s, kategori_isu: s === 'baik' ? undefined : (j?.kategori_isu ?? it.kategori_isu) })} />
                                        {ref.status_item[s]}
                                    </label>
                                ))}
                            </div>
                            {j && j.status_item !== 'baik' && (
                                <div className="grid grid-cols-1 gap-2">
                                    <select aria-label="Kategori isu" className={inputClass} value={j.kategori_isu ?? it.kategori_isu ?? 'lain'} onChange={(e) => setItem(it.kod, { kategori_isu: e.target.value })}>
                                        {Object.entries(ref.kategori_isu).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                    </select>
                                    {j.status_item === 'isu' && (
                                        <select aria-label="Tahap keterukan" className={inputClass} value={j.tahap_keterukan ?? 'sederhana'} onChange={(e) => setItem(it.kod, { tahap_keterukan: e.target.value })}>
                                            <option value="rendah">Rendah</option><option value="sederhana">Sederhana</option><option value="tinggi">Tinggi</option>
                                        </select>
                                    )}
                                    <label className="text-body-xs">Gambar item <input type="file" accept="image/*" capture="environment" className="block text-body-xs" onChange={(e) => tambahGambar(e.target.files, 'kamera', it.kod)} /></label>
                                </div>
                            )}
                            <textarea aria-label={`Catatan ${it.kod}`} rows={1} placeholder="Catatan" className={inputClass} value={j?.catatan ?? ''} onChange={(e) => setItem(it.kod, { catatan: e.target.value })} />
                        </fieldset>
                    );
                })}
            </section>

            <section className="rounded-lg border border-otl-gray-200 bg-bg-white p-3 space-y-3">
                <h3 className="font-semibold text-body-md">3. Keadaan dan penemuan</h3>
                <Field label="Keadaan tapak" htmlFor="f-k">
                    <select id="f-k" className={inputClass} value={d.keadaan_tapak ?? ''} onChange={(e) => set('keadaan_tapak', e.target.value)}>
                        <option value="">Pilih</option>
                        {Object.entries(ref.keadaan_tapak).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                    </select>
                </Field>
                <Field label="Ringkasan penemuan" htmlFor="f-r"><textarea id="f-r" rows={2} className={inputClass} value={d.ringkasan_penemuan ?? ''} onChange={(e) => set('ringkasan_penemuan', e.target.value)} /></Field>
                <Field label="Isu pencerobohan" htmlFor="f-p"><textarea id="f-p" rows={2} className={inputClass} value={d.isu_pencerobohan ?? ''} onChange={(e) => set('isu_pencerobohan', e.target.value)} /></Field>
                <Field label="Isu penyelenggaraan" htmlFor="f-s"><textarea id="f-s" rows={2} className={inputClass} value={d.isu_penyelenggaraan ?? ''} onChange={(e) => set('isu_penyelenggaraan', e.target.value)} /></Field>
                <Field label="Cadangan tindakan" htmlFor="f-c"><textarea id="f-c" rows={2} className={inputClass} value={d.cadangan_tindakan ?? ''} onChange={(e) => set('cadangan_tindakan', e.target.value)} /></Field>
                <div className="space-y-2">
                    <p className="text-body-sm font-medium">Keperluan penyelenggaraan</p>
                    {d.penyelenggaraan.map((p, i) => (
                        <div key={i} className="grid grid-cols-1 gap-1 border border-otl-gray-200 rounded-md p-2">
                            <select aria-label="Jenis penyelenggaraan" className={inputClass} value={p.jenis} onChange={(e) => set('penyelenggaraan', d.penyelenggaraan.map((x, j) => (j === i ? { ...x, jenis: e.target.value } : x)))}>
                                {Object.entries(ref.jenis_penyelenggaraan).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                            </select>
                            <input aria-label="Keterangan penyelenggaraan" className={inputClass} value={p.keterangan} onChange={(e) => set('penyelenggaraan', d.penyelenggaraan.map((x, j) => (j === i ? { ...x, keterangan: e.target.value } : x)))} />
                            <button type="button" className="text-body-xs text-txt-danger text-left" onClick={() => set('penyelenggaraan', d.penyelenggaraan.filter((_, j) => j !== i))}>Buang</button>
                        </div>
                    ))}
                    <Button size="sm" variant="outline" onClick={() => set('penyelenggaraan', [...d.penyelenggaraan, { jenis: 'pembersihan', keterangan: '', keutamaan: 'sederhana' }])}>Tambah keperluan</Button>
                </div>
                <div className="space-y-2">
                    <p className="text-body-sm font-medium">Cadangan kemas kini rekod tanah (perlu kelulusan pegawai berkuasa)</p>
                    {d.cadangan.map((c, i) => (
                        <div key={i} className="grid grid-cols-1 gap-1 border border-otl-gray-200 rounded-md p-2">
                            <select aria-label="Medan" className={inputClass} value={c.medan} onChange={(e) => set('cadangan', d.cadangan.map((x, j) => (j === i ? { ...x, medan: e.target.value } : x)))}>
                                {Object.entries(ref.medan_cadangan).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                            </select>
                            <input aria-label="Nilai dicadang" placeholder="Nilai dicadang" className={inputClass} value={c.nilai_dicadang} onChange={(e) => set('cadangan', d.cadangan.map((x, j) => (j === i ? { ...x, nilai_dicadang: e.target.value } : x)))} />
                            <input aria-label="Justifikasi" placeholder="Justifikasi" className={inputClass} value={c.justifikasi} onChange={(e) => set('cadangan', d.cadangan.map((x, j) => (j === i ? { ...x, justifikasi: e.target.value } : x)))} />
                            <button type="button" className="text-body-xs text-txt-danger text-left" onClick={() => set('cadangan', d.cadangan.filter((_, j) => j !== i))}>Buang</button>
                        </div>
                    ))}
                    <Button size="sm" variant="outline" onClick={() => set('cadangan', [...d.cadangan, { medan: 'kategori_guna_tanah', nilai_dicadang: '', justifikasi: '' }])}>Tambah cadangan</Button>
                </div>
            </section>

            <section className="rounded-lg border border-otl-gray-200 bg-bg-white p-3 space-y-3">
                <h3 className="font-semibold text-body-md">4. Gambar tapak ({gambar.length})</h3>
                <div className="flex flex-wrap gap-2">
                    <label className="inline-flex items-center rounded-md bg-primary-600 text-white px-4 py-2 text-body-sm cursor-pointer">Ambil gambar<input type="file" accept="image/*" capture="environment" className="sr-only" onChange={(e) => tambahGambar(e.target.files, 'kamera')} /></label>
                    <label className="inline-flex items-center rounded-md border border-otl-gray-300 px-4 py-2 text-body-sm cursor-pointer">Dari galeri<input type="file" accept="image/*" multiple className="sr-only" onChange={(e) => tambahGambar(e.target.files, 'galeri')} /></label>
                </div>
                <div className="grid grid-cols-2 gap-2">
                    {gambar.map((g) => (
                        <figure key={g.id} className="border border-otl-gray-200 rounded-md overflow-hidden">
                            {/* eslint-disable-next-line @next/next/no-img-element -- local object URL of an offline photo */}
                            <img src={g.url} alt={g.meta.kapsyen ?? 'Gambar tapak'} className="w-full h-28 object-cover" />
                            <figcaption className="p-1 text-body-xs space-y-1">
                                <div className="flex flex-wrap gap-1">
                                    {g.meta.dalam === false && <Badge variant="danger">Luar sempadan</Badge>}
                                    {g.meta.lat === null && <Badge variant="warning">Tiada GPS</Badge>}
                                    {g.meta.sumber_gambar === 'galeri' && <Badge variant="warning">Galeri</Badge>}
                                    {g.meta.kod_item && <Badge>{g.meta.kod_item}</Badge>}
                                </div>
                                <button type="button" className="text-txt-danger" onClick={() => buangGambar(g.id)}>Buang</button>
                            </figcaption>
                        </figure>
                    ))}
                </div>
            </section>

            <div className="fixed bottom-0 inset-x-0 bg-bg-white border-t border-otl-divider p-3 flex gap-2 z-10">
                <Button variant="secondary" className="flex-1" onClick={() => simpan({ ...d, status: 'draf' })}>Simpan draf</Button>
                <Button className="flex-1" disabled={!lengkap} onClick={async () => { await simpan({ ...d, status: 'sedia', mesej: undefined, masa_tamat: new Date().toISOString() }); toast.success('Sedia untuk disegerak.'); onTutup(); }}>Selesai lawatan</Button>
            </div>
            {!lengkap && <p className="text-body-xs text-txt-black-500">Lengkapkan semua item senarai semak, keadaan tapak dan sekurang-kurangnya satu gambar.</p>}
        </div>
    );
}

export default function AplikasiLapangan() {
    const online = useDalamTalian();
    const [kunci, setKunci] = useState<CryptoKey | null>(null);
    const [bundle, setBundle] = useState<BundleLapangan | null>(null);
    const [draf, setDraf] = useState<Draf[]>([]);
    const [buka, setBuka] = useState<Draf | null>(null);
    const [pelayan, setPelayan] = useState<Lawatan[]>([]);
    const [segerak, setSegerak] = useState(false);
    const [tab, setTab] = useState<'tugasan' | 'segerak' | 'status'>('tugasan');
    const sedang = useRef(false);

    useEffect(() => {
        if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw-lapangan.js', { scope: '/admin/ptn/lapangan' }).catch(() => undefined);
    }, []);

    const muatSetempat = useCallback(async (k: CryptoKey) => {
        setBundle(await store.baca<BundleLapangan>(k, 'bundle'));
        setDraf((await store.senarai<Draf>(k, 'lawatan')).sort((a, b) => b.dikemaskini.localeCompare(a.dikemaskini)));
    }, []);

    const muatTurun = async () => {
        if (!kunci) return;
        try {
            const b = await ptnApi.tugasan();
            await store.simpan(kunci, 'bundle', 'bundle', b);
            setBundle(b);
            toast.success(`${b.tugasan.length} tugasan dimuat turun untuk kegunaan luar talian.`);
        } catch (e) {
            toast.error(ralat(e));
        }
    };

    const segerakSemua = useCallback(async () => {
        if (!kunci || sedang.current || !navigator.onLine) return;
        sedang.current = true;
        setSegerak(true);
        let berjaya = 0;
        try {
            for (const v of await store.senarai<Draf>(kunci, 'lawatan')) {
                if (v.status !== 'sedia' && v.status !== 'gagal') continue;
                try {
                    const payload = {
                        id_luar_talian: v.id, sumber: 'pwa', id_jadual: v.id_jadual, versi_jadual: v.versi_jadual, tarikh_lawatan: v.tarikh_lawatan, masa_mula: v.masa_mula, masa_tamat: v.masa_tamat,
                        lat: v.lat, lng: v.lng, ketepatan_gps_m: v.ketepatan_gps_m, keadaan_tapak: v.keadaan_tapak, ringkasan_penemuan: v.ringkasan_penemuan,
                        isu_pencerobohan: v.isu_pencerobohan, isu_penyelenggaraan: v.isu_penyelenggaraan, cadangan_tindakan: v.cadangan_tindakan,
                        item: Object.entries(v.item).map(([kod_item, j]) => ({ kod_item, ...j })),
                        penyelenggaraan: v.penyelenggaraan.filter((p) => p.keterangan), cadangan: v.cadangan.filter((c) => c.nilai_dicadang && c.justifikasi),
                    };
                    const r = await api.post<{ data: Lawatan; status_segerak: string }>('/ptn/lapangan/lawatan', payload);
                    const serverId = r.data.id;
                    if (r.status_segerak === 'pendua' && r.data.status_lawatan === 'draf') await api.put(`/ptn/lapangan/lawatan/${serverId}`, payload);
                    for (const g of await store.gambarUntuk<MetaGambar>(kunci, v.id)) {
                        if (g.meta.dimuat_naik) continue;
                        const fd = new FormData();
                        fd.append('fail', g.blob, g.meta.nama.endsWith('.jpg') ? g.meta.nama : `${g.meta.nama}.jpg`);
                        fd.append('id_luar_talian', g.id);
                        Object.entries({ lat: g.meta.lat, lng: g.meta.lng, ketepatan_m: g.meta.ketepatan_m, arah_kamera: g.meta.arah_kamera, tarikh_masa: g.meta.tarikh_masa, sumber_gambar: g.meta.sumber_gambar, kod_item: g.meta.kod_item, kapsyen: g.meta.kapsyen }).forEach(([k, val]) => {
                            if (val !== null && val !== undefined) fd.append(k, String(val));
                        });
                        await api.postForm(`/ptn/lapangan/lawatan/${serverId}/gambar`, fd);
                        await store.kemaskiniMetaGambar(kunci, g.id, { ...g.meta, dimuat_naik: true });
                    }
                    if (r.data.status_lawatan === 'konflik') {
                        // Safe on the server, awaiting the supervisor: remove from the device.
                        await store.padamLawatan(v.id);
                        toast.warning(`${v.no_lot}: konflik — ${r.data.sebab_konflik ?? 'jadual berubah'}. Penyelia akan menyemak.`);
                        continue;
                    }
                    if (['draf', 'dikembalikan'].includes(r.data.status_lawatan)) await api.post(`/ptn/lapangan/lawatan/${serverId}/hantar`);
                    await store.padamLawatan(v.id);
                    berjaya++;
                } catch (e) {
                    if (e instanceof ApiError && e.status === 401) throw e;
                    await store.simpan(kunci, v.id, 'lawatan', { ...v, status: 'gagal', mesej: ralat(e), dikemaskini: new Date().toISOString() });
                }
            }
            if (berjaya) toast.success(`${berjaya} lawatan disegerak dan dipadam daripada peranti.`);
        } catch (e) {
            toast.error(e instanceof ApiError && e.status === 401 ? 'Sesi tamat — log masuk semula untuk menyegerak.' : ralat(e));
        } finally {
            sedang.current = false;
            setSegerak(false);
            await muatSetempat(kunci);
            ptnApi.lawatanSaya().then(setPelayan).catch(() => undefined);
        }
    }, [kunci, muatSetempat]);

    useEffect(() => {
        if (!kunci) return;
        Promise.all([store.baca<BundleLapangan>(kunci, 'bundle'), store.senarai<Draf>(kunci, 'lawatan')]).then(([b, l]) => {
            setBundle(b);
            setDraf(l.sort((x, y) => y.dikemaskini.localeCompare(x.dikemaskini)));
        });
        if (navigator.onLine) ptnApi.lawatanSaya().then(setPelayan).catch(() => undefined);
    }, [kunci]);

    // Automatic sync when the connection comes back (the `online` event), and every 2 minutes while online.
    useEffect(() => {
        if (!kunci) return;
        const kembali = () => { void segerakSemua(); };
        window.addEventListener('online', kembali);
        const t = setInterval(kembali, 120_000);
        const awal = setTimeout(kembali, 0);
        return () => {
            window.removeEventListener('online', kembali);
            clearInterval(t);
            clearTimeout(awal);
        };
    }, [kunci, segerakSemua]);

    if (!kunci) return <Kunci onBuka={setKunci} />;

    const mula = async (t: Tugasan) => {
        const ada = draf.find((x) => x.id_jadual === t.id);
        if (ada) return setBuka(ada);
        const v: Draf = {
            id: store.uuid(), id_jadual: t.id, versi_jadual: t.versi, lot_id: t.lot_id, no_lot: t.no_lot ?? t.lot.no_lot, id_templat: t.id_templat,
            tarikh_lawatan: new Date().toLocaleDateString('sv-SE'), masa_mula: new Date().toISOString(), item: {}, penyelenggaraan: [], cadangan: [], status: 'draf', dikemaskini: new Date().toISOString(),
        };
        await store.simpan(kunci, v.id, 'lawatan', v);
        setDraf((x) => [v, ...x]);
        setBuka(v);
    };

    if (buka && bundle) {
        const t = bundle.tugasan.find((x) => x.id === buka.id_jadual);
        if (t) {
            return <Borang kunci={kunci} tugasan={t} templat={bundle.templat.find((x) => x.id === (t.id_templat ?? buka.id_templat)) ?? null} draf={buka} bundle={bundle} onTutup={() => { setBuka(null); muatSetempat(kunci).then(() => { if (navigator.onLine) void segerakSemua(); }); }} />;
        }
    }

    const belum = draf.filter((x) => x.status !== 'draf').length;

    return (
        <div className="max-w-2xl mx-auto">
            <div className="sticky top-0 z-10 bg-bg-white border-b border-otl-divider px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h1 className="font-heading font-semibold text-body-lg">Aplikasi Lapangan</h1>
                    <p className="text-body-xs text-txt-black-500">Tugasan dimuat turun: {bundle ? new Date(bundle.dijana_pada).toLocaleString('ms-MY') : 'belum'}</p>
                </div>
                <div className="flex items-center gap-2">
                    <Badge variant={online ? 'success' : 'warning'}>{online ? 'Dalam talian' : 'Luar talian'}</Badge>
                    <Button size="sm" variant="secondary" onClick={() => { setKunci(null); setBundle(null); setDraf([]); }}>Kunci</Button>
                </div>
            </div>
            <div className="px-4 pt-3 flex gap-2" role="tablist" aria-label="Paparan aplikasi lapangan">
                {(['tugasan', 'segerak', 'status'] as const).map((k) => (
                    <Button key={k} role="tab" aria-selected={tab === k} size="sm" variant={tab === k ? 'primary' : 'secondary'} onClick={() => setTab(k)}>
                        {{ tugasan: `Tugasan (${bundle?.tugasan.length ?? 0})`, segerak: `Peranti (${draf.length})`, status: 'Status di pelayan' }[k]}
                    </Button>
                ))}
            </div>
            <div className="p-4 space-y-3">
                {!online && <Callout type="warning" title="Mod luar talian">Lawatan disimpan secara bersulit pada peranti dan akan disegerak secara automatik apabila sambungan pulih.</Callout>}
                {tab === 'tugasan' && (
                    <>
                        <Button className="w-full" disabled={!online} onClick={muatTurun}>Muat turun tugasan</Button>
                        {!bundle && <p className="text-body-sm text-txt-black-500">Muat turun tugasan semasa dalam talian sebelum ke lapangan.</p>}
                        {(bundle?.tugasan ?? []).map((t) => {
                            const d = draf.find((x) => x.id_jadual === t.id);
                            return (
                                <button key={t.id} type="button" onClick={() => mula(t)} className="w-full text-left rounded-lg border border-otl-gray-200 bg-bg-white p-3 hover:bg-bg-washed">
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="font-semibold text-body-md">{t.no_lot ?? t.lot.no_lot}</span>
                                        <Badge variant={t.kod_warna === 'merah' ? 'danger' : t.kod_warna === 'kuning' ? 'warning' : 'success'}>{tarikh(t.tarikh_sasaran)}</Badge>
                                    </div>
                                    <p className="text-body-sm text-txt-black-700">{t.mukim}, {t.daerah} · keutamaan {t.keutamaan_nama}</p>
                                    <p className="text-body-xs text-txt-black-500">{t.objektif}</p>
                                    {d && <p className="text-body-xs text-txt-primary">Draf pada peranti ({d.status})</p>}
                                </button>
                            );
                        })}
                    </>
                )}
                {tab === 'segerak' && (
                    <>
                        <Button className="w-full" disabled={!online || segerak || belum === 0} onClick={segerakSemua}>{segerak ? 'Menyegerak…' : `Segerak sekarang (${belum})`}</Button>
                        {draf.length === 0 && <p className="text-body-sm text-txt-black-500">Tiada data lawatan pada peranti.</p>}
                        {draf.map((v) => (
                            <div key={v.id} className="rounded-lg border border-otl-gray-200 bg-bg-white p-3 space-y-1">
                                <div className="flex items-center justify-between"><span className="font-semibold">{v.no_lot}</span><Badge variant={v.status === 'gagal' ? 'danger' : v.status === 'sedia' ? 'info' : 'neutral'}>{{ draf: 'Draf', sedia: 'Menunggu segerak', gagal: 'Gagal', konflik: 'Konflik' }[v.status]}</Badge></div>
                                {v.mesej && <p className="text-body-xs text-txt-danger">{v.mesej}</p>}
                                <div className="flex gap-2">
                                    <Button size="sm" variant="secondary" onClick={() => setBuka(v)}>Buka</Button>
                                    <Button size="sm" variant="danger" onClick={async () => { if (window.confirm('Padam draf ini daripada peranti?')) { await store.padamLawatan(v.id); muatSetempat(kunci); } }}>Padam</Button>
                                </div>
                            </div>
                        ))}
                    </>
                )}
                {tab === 'status' && (
                    <>
                        {pelayan.length === 0 && <p className="text-body-sm text-txt-black-500">{online ? 'Tiada lawatan.' : 'Perlu dalam talian.'}</p>}
                        {pelayan.map((l) => (
                            <div key={l.id} className="rounded-lg border border-otl-gray-200 bg-bg-white p-3">
                                <div className="flex items-center justify-between"><span className="font-semibold">{l.no_rujukan}</span><Badge variant={l.status_lawatan === 'disahkan' ? 'success' : l.status_lawatan === 'konflik' || l.status_lawatan === 'dikembalikan' ? 'danger' : 'info'}>{l.status_nama}</Badge></div>
                                <p className="text-body-xs text-txt-black-500">{l.no_lot} · {tarikh(l.tarikh_lawatan)}{l.sebab_konflik ? ` · ${l.sebab_konflik}` : ''}</p>
                            </div>
                        ))}
                    </>
                )}
            </div>
        </div>
    );
}
