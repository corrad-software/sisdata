'use client';

import { useSearchParams } from 'next/navigation';
import { Suspense, useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Callout } from '@/components/ui/Callout';
import { api, ApiError } from '@/lib/api';
import { astApi, ralat, tarikh, type BundleLapangan, type Kondisi } from '@/lib/ast';
import * as store from '@/lib/ast-offline';
import { Field, inputClass, KondisiBadge } from '../_components/common';

/*
 * SRS-AST-20 field app (PWA, offline) — the same approach as MOD-PTN's field
 * app: download the in-scope asset list → scan the asset's QR (camera) or
 * search → fill the inspection form, GPS and geotagged photos offline
 * (encrypted on the device) → automatic sync when back online; synced
 * inspections are deleted from the device. Every write is idempotent on the
 * device-generated id, so retries never duplicate (acceptance #4).
 */

type AsetBundle = BundleLapangan['aset'][number];
type Status = 'draf' | 'sedia' | 'gagal';

interface Draf {
    id: string;
    id_aset: string;
    nama_aset: string;
    no_rujukan_aset: string;
    tarikh_pemeriksaan: string;
    masa_mula: string;
    masa_tamat?: string;
    status_kondisi_dilapor?: Kondisi;
    isu_dikenalpasti?: string;
    tindakan_disyorkan?: string;
    senarai_semak: Record<string, { status: 'baik' | 'isu' | 'kritikal' | 'tiada'; catatan?: string }>;
    latitud?: number;
    longitud?: number;
    ketepatan_gps_m?: number;
    cipta_kerja: boolean;
    jenis_penyelenggaraan?: string;
    status: Status;
    mesej?: string;
    dikemaskini: string;
}

interface MetaGambar {
    latitud: number | null;
    longitud: number | null;
    ketepatan_m: number | null;
    tarikh_gambar: string;
    keterangan?: string;
    dimuat_naik?: boolean;
    nama: string;
}

interface Detector {
    detect: (src: CanvasImageSource) => Promise<Array<{ rawValue: string }>>;
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
            <h1 className="font-heading font-semibold text-heading-2xs">Aplikasi Lapangan — Pemeriksaan Aset</h1>
            <p className="text-body-sm text-txt-black-700">{ada ? 'Masukkan PIN peranti untuk membuka data pemeriksaan yang disulitkan.' : 'Tetapkan PIN peranti (6–12 digit). Borang dan gambar pemeriksaan pada peranti ini disulitkan dengan kunci daripada PIN ini.'}</p>
            <Field label="PIN" htmlFor="pin"><input id="pin" type="password" inputMode="numeric" autoComplete="off" className={inputClass} value={pin} onChange={(e) => setPin(e.target.value)} /></Field>
            {!ada && <Field label="Sahkan PIN" htmlFor="pin2"><input id="pin2" type="password" inputMode="numeric" autoComplete="off" className={inputClass} value={pin2} onChange={(e) => setPin2(e.target.value)} /></Field>}
            {err && <p role="alert" className="text-body-sm text-txt-danger">{err}</p>}
            <Button type="submit" className="w-full" disabled={sibuk || pin.length < 6}>{sibuk ? 'Memproses…' : ada ? 'Buka' : 'Tetapkan PIN'}</Button>
            {ada && (
                <button type="button" className="text-body-xs text-txt-danger underline" onClick={async () => { if (window.confirm('Padam SEMUA data lapangan aset pada peranti ini (termasuk pemeriksaan belum disegerak)?')) { await store.kosongkan(); setAda(false); } }}>
                    Lupa PIN — padam data peranti
                </button>
            )}
        </form>
    );
}

/** In-app QR scan with the camera (BarcodeDetector, where the browser supports it); the phone's own camera app works too (public link). */
function Pengimbas({ onKod }: { onKod: (v: string) => void }) {
    const video = useRef<HTMLVideoElement>(null);
    const [aktif, setAktif] = useState(false);
    const [disokong] = useState(() => typeof window !== 'undefined' && 'BarcodeDetector' in window);
    useEffect(() => {
        if (!aktif) return;
        let stream: MediaStream | null = null;
        let timer: ReturnType<typeof setInterval> | undefined;
        const Ctor = (window as unknown as { BarcodeDetector: new (o: { formats: string[] }) => Detector }).BarcodeDetector;
        const det = new Ctor({ formats: ['qr_code'] });
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then((s) => {
            stream = s;
            if (video.current) {
                video.current.srcObject = s;
                void video.current.play();
            }
            timer = setInterval(async () => {
                if (!video.current || video.current.readyState < 2) return;
                const hits = await det.detect(video.current).catch(() => []);
                if (hits[0]?.rawValue) {
                    setAktif(false);
                    onKod(hits[0].rawValue);
                }
            }, 350);
        }).catch(() => { toast.error('Kamera tidak dapat dibuka.'); setAktif(false); });
        return () => {
            if (timer) clearInterval(timer);
            stream?.getTracks().forEach((t) => t.stop());
        };
    }, [aktif, onKod]);
    if (!disokong) return <p className="text-body-xs text-txt-black-500">Imbasan dalam aplikasi tidak disokong pelayar ini — imbas dengan kamera telefon atau masukkan kod QR di bawah.</p>;
    return (
        <div className="space-y-2">
            {aktif && <video ref={video} className="w-full rounded-lg bg-black aspect-square object-cover" muted playsInline aria-label="Pratonton kamera" />}
            <Button className="w-full" variant={aktif ? 'secondary' : 'primary'} onClick={() => setAktif(!aktif)}>{aktif ? 'Henti imbasan' : 'Imbas QR aset'}</Button>
        </div>
    );
}

function Borang({ kunci, aset, draf, bundle, onTutup }: { kunci: CryptoKey; aset: AsetBundle; draf: Draf; bundle: BundleLapangan; onTutup: () => void }) {
    const [d, setD] = useState<Draf>(draf);
    const [gambar, setGambar] = useState<Array<{ id: string; meta: MetaGambar; url: string }>>([]);
    const muatGambar = useCallback(() => store.gambarUntuk<MetaGambar>(kunci, d.id).then((rows) => setGambar((old) => {
        old.forEach((g) => URL.revokeObjectURL(g.url));
        return rows.map((r) => ({ id: r.id, meta: r.meta, url: URL.createObjectURL(r.blob) }));
    })), [kunci, d.id]);
    useEffect(() => {
        store.gambarUntuk<MetaGambar>(kunci, d.id).then((rows) => setGambar(rows.map((r) => ({ id: r.id, meta: r.meta, url: URL.createObjectURL(r.blob) }))));
    }, [kunci, d.id]);

    const simpan = async (next: Draf) => {
        const v = { ...next, dikemaskini: new Date().toISOString() };
        setD(v);
        await store.simpan(kunci, v.id, 'pemeriksaan', v);
    };
    const set = <K extends keyof Draf>(k: K, v: Draf[K]) => simpan({ ...d, [k]: v });
    const rekodLokasi = async () => {
        const p = await lokasi();
        if (!p) return toast.error('Lokasi GPS tidak dapat diperoleh.');
        await simpan({ ...d, latitud: p.coords.latitude, longitud: p.coords.longitude, ketepatan_gps_m: Math.round(p.coords.accuracy) });
        const m = aset.latitud !== null && aset.longitud !== null ? store.jarakM(p.coords.latitude, p.coords.longitude, aset.latitud, aset.longitud) : null;
        toast[m !== null && m > bundle.toleransi_lokasi_m ? 'warning' : 'success'](m !== null && m > bundle.toleransi_lokasi_m ? `Anda kira-kira ${Math.round(m)} m dari titik aset.` : 'Lokasi direkod.');
    };
    const tambahGambar = async (files: FileList | null, sumber: 'kamera' | 'galeri') => {
        if (!files) return;
        for (const file of Array.from(files)) {
            const p = sumber === 'kamera' ? await lokasi() : null;
            const meta: MetaGambar = {
                latitud: p?.coords.latitude ?? null, longitud: p?.coords.longitude ?? null, ketepatan_m: p ? Math.round(p.coords.accuracy) : null,
                tarikh_gambar: new Date(sumber === 'galeri' ? file.lastModified : Date.now()).toISOString(), nama: file.name || 'gambar.jpg',
            };
            await store.simpanGambar(kunci, store.uuid(), d.id, meta, sumber === 'kamera' ? await store.kecilkan(file) : file);
        }
        await muatGambar();
    };
    const lengkap = !!d.status_kondisi_dilapor && bundle.senarai_semak.every((i) => d.senarai_semak[i.kod]) && gambar.length > 0;

    return (
        <div className="space-y-4 p-4 pb-28">
            <div className="flex items-center justify-between gap-2">
                <Button variant="secondary" size="sm" onClick={onTutup}>← Senarai</Button>
                <Badge variant={d.status === 'sedia' ? 'info' : d.status === 'gagal' ? 'danger' : 'neutral'}>{{ draf: 'Draf', sedia: 'Sedia disegerak', gagal: 'Gagal disegerak' }[d.status]}</Badge>
            </div>
            <div>
                <h2 className="font-heading font-semibold text-body-lg">{aset.nama_aset}</h2>
                <p className="text-body-sm text-txt-black-700">{aset.no_rujukan_aset} · {aset.kategori} · lot {aset.no_lot}, {aset.mukim}</p>
                <p className="text-body-xs text-txt-black-500">Kondisi terakhir: <KondisiBadge kondisi={aset.status_kondisi} /> · diperiksa {tarikh(aset.tarikh_pemeriksaan_terakhir)}</p>
                {d.mesej && <p role="alert" className="text-body-sm text-txt-danger">{d.mesej}</p>}
            </div>
            <section className="rounded-lg border border-otl-gray-200 bg-bg-white p-3 space-y-2">
                <h3 className="font-semibold text-body-md">1. Lokasi</h3>
                <p className="text-body-sm">{d.latitud ? `${d.latitud.toFixed(6)}, ${d.longitud?.toFixed(6)} (±${d.ketepatan_gps_m} m)` : 'Belum direkod'}</p>
                <Button size="sm" onClick={rekodLokasi}>Rekod lokasi GPS</Button>
            </section>
            <section className="rounded-lg border border-otl-gray-200 bg-bg-white p-3 space-y-3">
                <h3 className="font-semibold text-body-md">2. Senarai semak ({Object.keys(d.senarai_semak).length}/{bundle.senarai_semak.length})</h3>
                {bundle.senarai_semak.map((it) => {
                    const j = d.senarai_semak[it.kod];
                    return (
                        <fieldset key={it.kod} className="border-t border-otl-divider pt-2 space-y-2">
                            <legend className="text-body-sm font-medium">{it.nama}</legend>
                            <div className="grid grid-cols-4 gap-1" role="radiogroup" aria-label={`Status ${it.nama}`}>
                                {(['baik', 'isu', 'kritikal', 'tiada'] as const).map((s) => (
                                    <label key={s} className={`text-center rounded-md border px-1 py-2 text-body-xs cursor-pointer ${j?.status === s ? (s === 'baik' ? 'bg-bg-success-50 border-success-600' : s === 'isu' ? 'bg-bg-warning-50 border-warning-600' : s === 'kritikal' ? 'bg-bg-danger-50 border-danger-600' : 'bg-bg-washed border-otl-gray-400') : 'border-otl-gray-300'}`}>
                                        <input type="radio" className="sr-only" name={`ss-${it.kod}`} checked={j?.status === s} onChange={() => set('senarai_semak', { ...d.senarai_semak, [it.kod]: { ...(j ?? {}), status: s } })} />
                                        {{ baik: 'Baik', isu: 'Isu', kritikal: 'Kritikal', tiada: 'Tiada' }[s]}
                                    </label>
                                ))}
                            </div>
                            {j && j.status !== 'baik' && j.status !== 'tiada' && <input aria-label={`Catatan ${it.nama}`} placeholder="Catatan" className={inputClass} value={j.catatan ?? ''} onChange={(e) => set('senarai_semak', { ...d.senarai_semak, [it.kod]: { ...j, catatan: e.target.value } })} />}
                        </fieldset>
                    );
                })}
            </section>
            <section className="rounded-lg border border-otl-gray-200 bg-bg-white p-3 space-y-3">
                <h3 className="font-semibold text-body-md">3. Kondisi dan tindakan</h3>
                <div className="grid grid-cols-3 gap-1" role="radiogroup" aria-label="Status kondisi aset">
                    {bundle.kondisi.map((k) => (
                        <label key={k.kod} className={`text-center rounded-md border px-1 py-2 text-body-sm cursor-pointer ${d.status_kondisi_dilapor === k.kod ? 'border-primary-600 bg-bg-primary-50' : 'border-otl-gray-300'}`}>
                            <input type="radio" className="sr-only" name="kondisi" checked={d.status_kondisi_dilapor === k.kod} onChange={() => set('status_kondisi_dilapor', k.kod as Kondisi)} />
                            {k.nama}
                        </label>
                    ))}
                </div>
                <Field label="Isu dikenal pasti" htmlFor="f-i"><textarea id="f-i" rows={2} className={inputClass} value={d.isu_dikenalpasti ?? ''} onChange={(e) => set('isu_dikenalpasti', e.target.value)} /></Field>
                <Field label="Tindakan disyorkan" htmlFor="f-t"><textarea id="f-t" rows={2} className={inputClass} value={d.tindakan_disyorkan ?? ''} onChange={(e) => set('tindakan_disyorkan', e.target.value)} /></Field>
                {bundle.boleh_cipta_kerja && (
                    <div className="space-y-2">
                        <label className="flex items-center gap-2 text-body-sm"><input type="checkbox" checked={d.cipta_kerja} onChange={(e) => set('cipta_kerja', e.target.checked)} /> Wujudkan kerja pembaikan</label>
                        {d.cipta_kerja && (
                            <select aria-label="Jenis penyelenggaraan" className={inputClass} value={d.jenis_penyelenggaraan ?? 'bangunan'} onChange={(e) => set('jenis_penyelenggaraan', e.target.value)}>
                                {bundle.jenis_penyelenggaraan.map((j) => <option key={j.kod} value={j.kod}>{j.nama}</option>)}
                            </select>
                        )}
                    </div>
                )}
            </section>
            <section className="rounded-lg border border-otl-gray-200 bg-bg-white p-3 space-y-3">
                <h3 className="font-semibold text-body-md">4. Gambar bertarikh ({gambar.length})</h3>
                <div className="flex flex-wrap gap-2">
                    <label className="inline-flex items-center rounded-md bg-primary-600 text-white px-4 py-2 text-body-sm cursor-pointer">Ambil gambar<input type="file" accept="image/*" capture="environment" className="sr-only" onChange={(e) => tambahGambar(e.target.files, 'kamera')} /></label>
                    <label className="inline-flex items-center rounded-md border border-otl-gray-300 px-4 py-2 text-body-sm cursor-pointer">Dari galeri<input type="file" accept="image/*" multiple className="sr-only" onChange={(e) => tambahGambar(e.target.files, 'galeri')} /></label>
                </div>
                <div className="grid grid-cols-2 gap-2">
                    {gambar.map((g) => (
                        <figure key={g.id} className="border border-otl-gray-200 rounded-md overflow-hidden">
                            {/* eslint-disable-next-line @next/next/no-img-element -- local object URL of an offline photo */}
                            <img src={g.url} alt="Gambar pemeriksaan" className="w-full h-28 object-cover" />
                            <figcaption className="p-1 text-body-xs space-y-1">
                                {g.meta.latitud === null && <Badge variant="warning">Tiada GPS</Badge>}
                                <button type="button" className="block text-txt-danger" onClick={async () => { await store.padam(g.id); await muatGambar(); }}>Buang</button>
                            </figcaption>
                        </figure>
                    ))}
                </div>
            </section>
            <div className="fixed bottom-0 inset-x-0 bg-bg-white border-t border-otl-divider p-3 flex gap-2 z-10">
                <Button variant="secondary" className="flex-1" onClick={() => simpan({ ...d, status: 'draf' })}>Simpan draf</Button>
                <Button className="flex-1" disabled={!lengkap} onClick={async () => { await simpan({ ...d, status: 'sedia', mesej: undefined, masa_tamat: new Date().toISOString() }); toast.success('Sedia untuk disegerak.'); onTutup(); }}>Selesai pemeriksaan</Button>
            </div>
            {!lengkap && <p className="text-body-xs text-txt-black-500">Lengkapkan senarai semak, status kondisi dan sekurang-kurangnya satu gambar.</p>}
        </div>
    );
}

function Aplikasi() {
    const sp = useSearchParams();
    const online = useDalamTalian();
    const [kunci, setKunci] = useState<CryptoKey | null>(null);
    const [bundle, setBundle] = useState<BundleLapangan | null>(null);
    const [draf, setDraf] = useState<Draf[]>([]);
    const [buka, setBuka] = useState<Draf | null>(null);
    const [cari, setCari] = useState('');
    const [segerak, setSegerak] = useState(false);
    const [tab, setTab] = useState<'aset' | 'peranti' | 'status'>('aset');
    const [pelayan, setPelayan] = useState<Awaited<ReturnType<typeof astApi.pemeriksaanSaya>>>([]);
    const sedang = useRef(false);
    const qrAwal = useRef(sp.get('qr'));

    useEffect(() => {
        if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw-aset-lapangan.js', { scope: '/admin/ast/lapangan' }).catch(() => undefined);
    }, []);

    const muatSetempat = useCallback(async (k: CryptoKey) => {
        setBundle(await store.baca<BundleLapangan>(k, 'bundle'));
        setDraf((await store.senarai<Draf>(k, 'pemeriksaan')).sort((a, b) => b.dikemaskini.localeCompare(a.dikemaskini)));
    }, []);

    const muatTurun = async () => {
        if (!kunci) return;
        try {
            const b = await astApi.bundle();
            await store.simpan(kunci, 'bundle', 'bundle', b);
            setBundle(b);
            toast.success(`${b.aset.length} aset dimuat turun untuk kegunaan luar talian.`);
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
            for (const v of await store.senarai<Draf>(kunci, 'pemeriksaan')) {
                if (v.status !== 'sedia' && v.status !== 'gagal') continue;
                try {
                    const r = await api.post<{ data: { id: string }; status_segerak: string }>('/ast/lapangan/pemeriksaan', {
                        id_luar_talian: v.id, id_aset: v.id_aset, tarikh_pemeriksaan: v.tarikh_pemeriksaan, masa_mula: v.masa_mula, masa_tamat: v.masa_tamat,
                        status_kondisi_dilapor: v.status_kondisi_dilapor, isu_dikenalpasti: v.isu_dikenalpasti || null, tindakan_disyorkan: v.tindakan_disyorkan || null,
                        senarai_semak: Object.entries(v.senarai_semak).map(([kod, j]) => ({ kod, ...j })), latitud: v.latitud, longitud: v.longitud, ketepatan_gps_m: v.ketepatan_gps_m,
                        cipta_kerja: v.cipta_kerja, jenis_penyelenggaraan: v.cipta_kerja ? (v.jenis_penyelenggaraan ?? 'bangunan') : undefined,
                    });
                    for (const g of await store.gambarUntuk<MetaGambar>(kunci, v.id)) {
                        if (g.meta.dimuat_naik) continue;
                        const fd = new FormData();
                        fd.append('fail', g.blob, g.meta.nama.endsWith('.jpg') ? g.meta.nama : `${g.meta.nama}.jpg`);
                        fd.append('id_luar_talian', g.id);
                        Object.entries({ latitud: g.meta.latitud, longitud: g.meta.longitud, ketepatan_m: g.meta.ketepatan_m, tarikh_gambar: g.meta.tarikh_gambar, keterangan: g.meta.keterangan }).forEach(([k, val]) => {
                            if (val !== null && val !== undefined) fd.append(k, String(val));
                        });
                        await api.postForm(`/ast/lapangan/pemeriksaan/${r.data.id}/gambar`, fd);
                        await store.kemaskiniMetaGambar(kunci, g.id, { ...g.meta, dimuat_naik: true });
                    }
                    await store.padamPemeriksaan(v.id);
                    berjaya++;
                } catch (e) {
                    if (e instanceof ApiError && e.status === 401) throw e;
                    await store.simpan(kunci, v.id, 'pemeriksaan', { ...v, status: 'gagal', mesej: ralat(e), dikemaskini: new Date().toISOString() });
                }
            }
            if (berjaya) toast.success(`${berjaya} pemeriksaan disegerak dan dipadam daripada peranti.`);
        } catch (e) {
            toast.error(e instanceof ApiError && e.status === 401 ? 'Sesi tamat — log masuk semula untuk menyegerak.' : ralat(e));
        } finally {
            sedang.current = false;
            setSegerak(false);
            await muatSetempat(kunci);
            astApi.pemeriksaanSaya().then(setPelayan).catch(() => undefined);
        }
    }, [kunci, muatSetempat]);

    useEffect(() => {
        if (!kunci) return;
        Promise.all([store.baca<BundleLapangan>(kunci, 'bundle'), store.senarai<Draf>(kunci, 'pemeriksaan')]).then(([b, l]) => {
            setBundle(b);
            setDraf(l.sort((x, y) => y.dikemaskini.localeCompare(x.dikemaskini)));
        });
        if (navigator.onLine) astApi.pemeriksaanSaya().then(setPelayan).catch(() => undefined);
    }, [kunci]);

    // Automatic sync when the connection returns, and every 2 minutes while online.
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

    const mula = useCallback(async (a: AsetBundle) => {
        if (!kunci) return;
        const ada = draf.find((x) => x.id_aset === a.id);
        if (ada) return setBuka(ada);
        const v: Draf = {
            id: store.uuid(), id_aset: a.id, nama_aset: a.nama_aset, no_rujukan_aset: a.no_rujukan_aset, tarikh_pemeriksaan: new Date().toLocaleDateString('sv-SE'),
            masa_mula: new Date().toISOString(), senarai_semak: {}, cipta_kerja: false, status: 'draf', dikemaskini: new Date().toISOString(),
        };
        await store.simpan(kunci, v.id, 'pemeriksaan', v);
        setDraf((x) => [v, ...x]);
        setBuka(v);
    }, [kunci, draf]);

    const imbas = useCallback((nilai: string) => {
        const ref = store.rujukanQr(nilai);
        const a = ref ? bundle?.aset.find((x) => x.qr_ref === ref) : undefined;
        if (!a) return toast.error(ref ? `Aset ${ref} tiada dalam senarai dimuat turun (di luar skop atau perlu muat turun semula).` : 'Kod QR tidak dikenali.');
        void mula(a);
    }, [bundle, mula]);

    // Opened from a scanned QR (public page → "Periksa dalam aplikasi lapangan").
    useEffect(() => {
        if (kunci && bundle && qrAwal.current) {
            const q = qrAwal.current;
            qrAwal.current = null;
            imbas(q);
        }
    }, [kunci, bundle, imbas]);

    if (!kunci) return <Kunci onBuka={setKunci} />;

    if (buka && bundle) {
        const a = bundle.aset.find((x) => x.id === buka.id_aset);
        if (a) return <Borang kunci={kunci} aset={a} draf={buka} bundle={bundle} onTutup={() => { setBuka(null); muatSetempat(kunci).then(() => { if (navigator.onLine) void segerakSemua(); }); }} />;
    }

    const belum = draf.filter((x) => x.status !== 'draf').length;
    const q = cari.trim().toLowerCase();
    const senarai = (bundle?.aset ?? []).filter((a) => !q || a.nama_aset.toLowerCase().includes(q) || a.no_rujukan_aset.toLowerCase().includes(q) || a.qr_ref.toLowerCase() === q || a.no_lot.toLowerCase().includes(q))
        .sort((x, y) => Number(y.lewat) - Number(x.lewat)).slice(0, 100);

    return (
        <div className="max-w-2xl mx-auto">
            <div className="sticky top-0 z-10 bg-bg-white border-b border-otl-divider px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h1 className="font-heading font-semibold text-body-lg">Pemeriksaan Aset Lapangan</h1>
                    <p className="text-body-xs text-txt-black-500">Senarai aset: {bundle ? new Date(bundle.dijana_pada).toLocaleString('ms-MY') : 'belum dimuat turun'}</p>
                </div>
                <div className="flex items-center gap-2">
                    <Badge variant={online ? 'success' : 'warning'}>{online ? 'Dalam talian' : 'Luar talian'}</Badge>
                    <Button size="sm" variant="secondary" onClick={() => { setKunci(null); setBundle(null); setDraf([]); }}>Kunci</Button>
                </div>
            </div>
            <div className="px-4 pt-3 flex gap-2" role="tablist" aria-label="Paparan aplikasi lapangan">
                {(['aset', 'peranti', 'status'] as const).map((k) => (
                    <Button key={k} role="tab" aria-selected={tab === k} size="sm" variant={tab === k ? 'primary' : 'secondary'} onClick={() => setTab(k)}>
                        {{ aset: `Aset (${bundle?.aset.length ?? 0})`, peranti: `Peranti (${draf.length})`, status: 'Di pelayan' }[k]}
                    </Button>
                ))}
            </div>
            <div className="p-4 space-y-3">
                {!online && <Callout type="warning" title="Mod luar talian">Pemeriksaan disimpan secara bersulit pada peranti dan disegerak automatik apabila sambungan pulih.</Callout>}
                {tab === 'aset' && (
                    <>
                        <Button className="w-full" variant="secondary" disabled={!online} onClick={muatTurun}>Muat turun senarai aset</Button>
                        {bundle && <Pengimbas onKod={imbas} />}
                        {bundle && (
                            <form className="flex gap-2" onSubmit={(e) => { e.preventDefault(); if (store.rujukanQr(cari)) imbas(cari); }}>
                                <label htmlFor="cari" className="sr-only">Cari aset atau kod QR</label>
                                <input id="cari" className={inputClass} value={cari} onChange={(e) => setCari(e.target.value)} placeholder="Nama, no. rujukan, lot atau kod QR" />
                            </form>
                        )}
                        {!bundle && <p className="text-body-sm text-txt-black-500">Muat turun senarai aset semasa dalam talian sebelum ke lapangan.</p>}
                        {senarai.map((a) => {
                            const d = draf.find((x) => x.id_aset === a.id);
                            return (
                                <button key={a.id} type="button" onClick={() => mula(a)} className="w-full text-left rounded-lg border border-otl-gray-200 bg-bg-white p-3 hover:bg-bg-washed">
                                    <div className="flex items-center justify-between gap-2"><span className="font-semibold text-body-md">{a.nama_aset}</span><KondisiBadge kondisi={a.status_kondisi} /></div>
                                    <p className="text-body-sm text-txt-black-700">{a.no_rujukan_aset} · lot {a.no_lot}, {a.mukim}</p>
                                    <p className="text-body-xs text-txt-black-500">Diperiksa {tarikh(a.tarikh_pemeriksaan_terakhir)} {a.lewat && <Badge variant="danger">Melebihi tempoh</Badge>}</p>
                                    {d && <p className="text-body-xs text-txt-primary">Draf pada peranti ({d.status})</p>}
                                </button>
                            );
                        })}
                    </>
                )}
                {tab === 'peranti' && (
                    <>
                        <Button className="w-full" disabled={!online || segerak || belum === 0} onClick={segerakSemua}>{segerak ? 'Menyegerak…' : `Segerak sekarang (${belum})`}</Button>
                        {draf.length === 0 && <p className="text-body-sm text-txt-black-500">Tiada data pemeriksaan pada peranti.</p>}
                        {draf.map((v) => (
                            <div key={v.id} className="rounded-lg border border-otl-gray-200 bg-bg-white p-3 space-y-1">
                                <div className="flex items-center justify-between"><span className="font-semibold">{v.nama_aset}</span><Badge variant={v.status === 'gagal' ? 'danger' : v.status === 'sedia' ? 'info' : 'neutral'}>{{ draf: 'Draf', sedia: 'Menunggu segerak', gagal: 'Gagal' }[v.status]}</Badge></div>
                                {v.mesej && <p className="text-body-xs text-txt-danger">{v.mesej}</p>}
                                <div className="flex gap-2">
                                    <Button size="sm" variant="secondary" onClick={() => setBuka(v)}>Buka</Button>
                                    <Button size="sm" variant="danger" onClick={async () => { if (window.confirm('Padam draf ini daripada peranti?')) { await store.padamPemeriksaan(v.id); muatSetempat(kunci); } }}>Padam</Button>
                                </div>
                            </div>
                        ))}
                    </>
                )}
                {tab === 'status' && (
                    <>
                        {pelayan.length === 0 && <p className="text-body-sm text-txt-black-500">{online ? 'Tiada pemeriksaan.' : 'Perlu dalam talian.'}</p>}
                        {pelayan.map((p) => (
                            <div key={p.id} className="rounded-lg border border-otl-gray-200 bg-bg-white p-3">
                                <div className="flex items-center justify-between"><span className="font-semibold">{p.nama_aset ?? p.no_rujukan_aset}</span><KondisiBadge kondisi={p.status_kondisi_dilapor} /></div>
                                <p className="text-body-xs text-txt-black-500">{tarikh(p.tarikh_pemeriksaan)} · {p.kaedah_nama}</p>
                            </div>
                        ))}
                    </>
                )}
            </div>
        </div>
    );
}

export default function AplikasiLapanganAset() {
    return (
        <Suspense fallback={<p className="p-4 text-body-sm">Memuatkan…</p>}>
            <Aplikasi />
        </Suspense>
    );
}
