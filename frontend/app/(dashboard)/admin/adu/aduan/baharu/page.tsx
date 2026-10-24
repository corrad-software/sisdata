'use client';

import { useEffect, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useMutation, useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { Callout } from '@/components/ui/Callout';
import { aduApi, ralat, useRujukan, type LotPilihan } from '@/lib/adu';
import { Card, Field, inputClass, Select } from '../../_components/common';
import { PetaAduan } from '../../_components/PetaAduan';

const DRAF = 'adu:draf-aduan';

interface Borang {
    saluran_penerimaan: string;
    kategori: string;
    subkategori: string;
    tahap_keutamaan: string;
    keterangan: string;
    lokasi_teks: string;
    lat: number | null;
    lng: number | null;
    lot: LotPilihan | null;
    tanpa_nama: boolean;
}

const KOSONG: Borang = { saluran_penerimaan: 'lapangan', kategori: '', subkategori: '', tahap_keutamaan: 'sederhana', keterangan: '', lokasi_teks: '', lat: null, lng: null, lot: null, tanpa_nama: false };

function bacaDraf(): Borang {
    if (typeof window === 'undefined') return KOSONG;
    try {
        const raw = window.localStorage.getItem(DRAF);
        return raw ? { ...KOSONG, ...(JSON.parse(raw) as Partial<Borang>) } : KOSONG;
    } catch {
        return KOSONG;
    }
}

/**
 * Mobile-friendly intake for counter / phone / e-mail / field officers
 * (SRS-ADU-02/06): GPS or tap-on-map pin, lot search, category suggestion
 * from the description (SRS-ADU-09), camera photos, reporter details or
 * anonymous. The draft (without reporter identity) is kept on the device
 * so nothing is lost if the session drops (SRS-GEN-07).
 */
export default function AduanBaharu() {
    const router = useRouter();
    const { data: ref } = useRujukan();
    const [b, setB] = useState<Borang>(KOSONG);
    const [drafDimuat, setDrafDimuat] = useState(false);
    const [pengadu, setPengadu] = useState({ nama: '', no_telefon: '', emel: '', alamat: '', saluran_makluman: 'emel' });
    const [fail, setFail] = useState<File[]>([]);
    const [carianLot, setCarianLot] = useState('');
    const [gps, setGps] = useState<string | null>(null);
    const fileRef = useRef<HTMLInputElement>(null);
    const set = <K extends keyof Borang>(k: K, v: Borang[K]) => setB((x) => ({ ...x, [k]: v }));

    // Restore the device draft after hydration (server render has no localStorage).
    useEffect(() => {
        const draf = bacaDraf();
        // eslint-disable-next-line react-hooks/set-state-in-effect -- one-time restore from browser storage
        setB(draf);
        setDrafDimuat(true);
    }, []);

    useEffect(() => {
        if (!drafDimuat) return;
        try {
            window.localStorage.setItem(DRAF, JSON.stringify(b));
        } catch {
            /* storage unavailable — draft is a convenience only */
        }
    }, [b, drafDimuat]);

    const lots = useQuery({ queryKey: ['adu', 'cari-lot', carianLot], queryFn: () => aduApi.cariLot(carianLot).then((r) => r.data), enabled: carianLot.trim().length >= 2 });
    const cadang = useMutation({
        mutationFn: () => aduApi.cadangKategori(b.keterangan),
        onSuccess: (r) => {
            if (r.data.kategori) {
                setB((x) => ({ ...x, kategori: r.data.kategori ?? x.kategori, subkategori: '', tahap_keutamaan: r.data.keutamaan ?? x.tahap_keutamaan }));
                toast.success(`Cadangan kategori: ${r.data.kategori_nama} (kata kunci: ${r.data.kata_kunci.join(', ')})`);
            } else {
                toast.info('Tiada cadangan kategori daripada keterangan ini.');
            }
        },
        onError: (e) => toast.error(ralat(e)),
    });

    const hantar = useMutation({
        mutationFn: () => {
            const form = new FormData();
            const put = (k: string, v: string | number | null | undefined) => {
                if (v !== null && v !== undefined && v !== '') form.append(k, String(v));
            };
            put('saluran_penerimaan', b.saluran_penerimaan);
            put('kategori', b.kategori);
            put('subkategori', b.subkategori);
            put('tahap_keutamaan', b.tahap_keutamaan);
            put('keterangan', b.keterangan);
            put('lokasi_teks', b.lokasi_teks);
            put('lat', b.lat);
            put('lng', b.lng);
            put('lot_id', b.lot?.id);
            form.append('tanpa_nama', b.tanpa_nama ? '1' : '0');
            if (!b.tanpa_nama) {
                Object.entries(pengadu).forEach(([k, v]) => put(`pengadu[${k}]`, v));
            }
            fail.forEach((f) => form.append('fail[]', f));
            return aduApi.cipta(form);
        },
        onSuccess: (r) => {
            try {
                window.localStorage.removeItem(DRAF);
            } catch {
                /* ignore */
            }
            toast.success(r.message);
            router.push(`/admin/adu/aduan/${r.data.id}`);
        },
        onError: (e) => toast.error(ralat(e)),
    });

    const lokasiSemasa = () => {
        if (!navigator.geolocation) {
            setGps('Peranti tidak menyokong GPS.');
            return;
        }
        setGps('Mendapatkan lokasi…');
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                setB((x) => ({ ...x, lat: Number(pos.coords.latitude.toFixed(6)), lng: Number(pos.coords.longitude.toFixed(6)) }));
                setGps(`Ketepatan ±${Math.round(pos.coords.accuracy)} m`);
            },
            () => setGps('Lokasi tidak dapat diperoleh. Pilih pada peta.'),
            { enableHighAccuracy: true, timeout: 15000 },
        );
    };

    const subOptions = b.kategori ? (ref?.subkategori[b.kategori] ?? []) : [];

    return (
        <PageContainer>
            <form
                className="space-y-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    hantar.mutate();
                }}
            >
                <Card title="Butiran aduan">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Field label="Saluran penerimaan" htmlFor="ab-sal">
                            <Select id="ab-sal" value={b.saluran_penerimaan} onChange={(v) => set('saluran_penerimaan', v)} options={ref?.saluran_dalaman ?? []} required />
                        </Field>
                        <Field label="Keutamaan" htmlFor="ab-ku">
                            <Select id="ab-ku" value={b.tahap_keutamaan} onChange={(v) => set('tahap_keutamaan', v)} options={ref?.keutamaan ?? []} />
                        </Field>
                        <Field label="Keterangan" htmlFor="ab-ket" className="sm:col-span-2" help="Sekurang-kurangnya 10 aksara. Kategori boleh dicadangkan daripada keterangan.">
                            <textarea id="ab-ket" rows={4} required minLength={10} className={inputClass} value={b.keterangan} onChange={(e) => set('keterangan', e.target.value)} />
                        </Field>
                        <Field label="Kategori" htmlFor="ab-kat">
                            <div className="flex gap-2">
                                <Select id="ab-kat" value={b.kategori} onChange={(v) => setB((x) => ({ ...x, kategori: v, subkategori: '' }))} options={ref?.kategori ?? []} empty="Cadangan automatik" />
                                <Button size="sm" variant="outline" onClick={() => cadang.mutate()} disabled={b.keterangan.length < 5 || cadang.isPending}>
                                    Cadang
                                </Button>
                            </div>
                        </Field>
                        <Field label="Subkategori" htmlFor="ab-sub">
                            <Select id="ab-sub" value={b.subkategori} onChange={(v) => set('subkategori', v)} options={subOptions} empty="—" />
                        </Field>
                    </div>
                </Card>

                <Card title="Lokasi">
                    <div className="flex flex-wrap items-center gap-3">
                        <Button size="sm" onClick={lokasiSemasa}>
                            Guna lokasi semasa (GPS)
                        </Button>
                        {b.lat !== null && (
                            <>
                                <span className="text-body-sm text-txt-black-700">
                                    {b.lat}, {b.lng}
                                </span>
                                <Button size="sm" variant="outline" onClick={() => setB((x) => ({ ...x, lat: null, lng: null }))}>
                                    Buang pin
                                </Button>
                            </>
                        )}
                        {gps && <span className="text-body-xs text-txt-black-500">{gps}</span>}
                    </div>
                    <p className="text-body-xs text-txt-black-500">Ketik pada peta untuk meletakkan pin, atau seret pin untuk melaraskan. Sistem akan memadankan pin kepada lot MAIS secara automatik.</p>
                    <PetaAduan pin={b.lat !== null && b.lng !== null ? { lat: b.lat, lng: b.lng } : null} onPin={(p) => setB((x) => ({ ...x, lat: p.lat, lng: p.lng }))} height={300} label="Pilih lokasi aduan pada peta" />
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Field label="Cari lot (pilihan)" htmlFor="ab-lot" help={b.lot ? `Dipilih: ${b.lot.no_lot}, ${b.lot.mukim ?? ''}` : 'Taip nombor lot, cth. "PT 1234".'}>
                            <input id="ab-lot" type="search" className={inputClass} value={carianLot} onChange={(e) => setCarianLot(e.target.value)} autoComplete="off" />
                            {carianLot.trim().length >= 2 && (
                                <ul className="mt-1 max-h-48 overflow-y-auto rounded-md border border-otl-gray-200 bg-bg-white">
                                    {(lots.data ?? []).length === 0 ? (
                                        <li className="px-3 py-2 text-body-xs text-txt-black-500">{lots.isLoading ? 'Mencari…' : 'Tiada lot sepadan.'}</li>
                                    ) : (
                                        lots.data?.map((l) => (
                                            <li key={l.id}>
                                                <button
                                                    type="button"
                                                    className="w-full px-3 py-2 text-left text-body-sm hover:bg-bg-washed"
                                                    onClick={() => {
                                                        set('lot', l);
                                                        setCarianLot('');
                                                    }}
                                                >
                                                    {l.no_lot} <span className="text-txt-black-500">· {l.mukim}, {l.daerah}</span>
                                                </button>
                                            </li>
                                        ))
                                    )}
                                </ul>
                            )}
                            {b.lot && (
                                <button type="button" className="mt-1 text-body-xs text-txt-primary hover:underline" onClick={() => set('lot', null)}>
                                    Buang pilihan lot
                                </button>
                            )}
                        </Field>
                        <Field label="Keterangan lokasi / alamat" htmlFor="ab-lt">
                            <input id="ab-lt" className={inputClass} value={b.lokasi_teks} onChange={(e) => set('lokasi_teks', e.target.value)} maxLength={500} />
                        </Field>
                    </div>
                </Card>

                <Card title="Gambar / dokumen sokongan">
                    <input
                        ref={fileRef}
                        id="ab-fail"
                        type="file"
                        accept="image/jpeg,image/png,application/pdf"
                        capture="environment"
                        multiple
                        className="block w-full text-body-sm"
                        onChange={(e) => setFail(Array.from(e.target.files ?? []).slice(0, 10))}
                        aria-label="Lampirkan gambar atau dokumen"
                    />
                    <p className="text-body-xs text-txt-black-500">JPG, PNG atau PDF. Fail diimbas antivirus dan disimpan dalam Dokumen Digital (DMS).</p>
                    {fail.length > 0 && (
                        <ul className="text-body-sm text-txt-black-700 list-disc ps-5">
                            {fail.map((f) => (
                                <li key={f.name}>{f.name}</li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Card title="Pengadu">
                    <label className="flex items-center gap-2 text-body-sm">
                        <input type="checkbox" checked={b.tanpa_nama} onChange={(e) => set('tanpa_nama', e.target.checked)} />
                        Aduan tanpa nama (tiada maklumat identiti disimpan, tiada makluman status)
                    </label>
                    {!b.tanpa_nama && (
                        <>
                            <Callout type="info">Maklumat pengadu adalah Sulit — disulitkan dan hanya boleh dilihat oleh pegawai ditugaskan, penyelia dan Unit Aduan.</Callout>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <Field label="Nama" htmlFor="ab-pn">
                                    <input id="ab-pn" className={inputClass} value={pengadu.nama} onChange={(e) => setPengadu((x) => ({ ...x, nama: e.target.value }))} autoComplete="off" />
                                </Field>
                                <Field label="No. telefon" htmlFor="ab-pt">
                                    <input id="ab-pt" type="tel" inputMode="tel" className={inputClass} value={pengadu.no_telefon} onChange={(e) => setPengadu((x) => ({ ...x, no_telefon: e.target.value }))} autoComplete="off" />
                                </Field>
                                <Field label="E-mel" htmlFor="ab-pe">
                                    <input id="ab-pe" type="email" className={inputClass} value={pengadu.emel} onChange={(e) => setPengadu((x) => ({ ...x, emel: e.target.value }))} autoComplete="off" />
                                </Field>
                                <Field label="Makluman status melalui" htmlFor="ab-pm">
                                    <Select
                                        id="ab-pm"
                                        value={pengadu.saluran_makluman}
                                        onChange={(v) => setPengadu((x) => ({ ...x, saluran_makluman: v }))}
                                        options={[
                                            { kod: 'emel', nama: 'E-mel' },
                                            { kod: 'sms', nama: 'SMS' },
                                            { kod: 'tiada', nama: 'Tiada makluman' },
                                        ]}
                                    />
                                </Field>
                                <Field label="Alamat" htmlFor="ab-pa" className="sm:col-span-2">
                                    <input id="ab-pa" className={inputClass} value={pengadu.alamat} onChange={(e) => setPengadu((x) => ({ ...x, alamat: e.target.value }))} autoComplete="off" />
                                </Field>
                            </div>
                        </>
                    )}
                </Card>

                <div className="flex flex-wrap justify-end gap-2">
                    <Button
                        variant="outline"
                        onClick={() => {
                            setB(KOSONG);
                            setFail([]);
                            if (fileRef.current) fileRef.current.value = '';
                        }}
                    >
                        Kosongkan borang
                    </Button>
                    <Button type="submit" disabled={hantar.isPending}>
                        {hantar.isPending ? 'Menghantar…' : 'Hantar aduan'}
                    </Button>
                </div>
            </form>
        </PageContainer>
    );
}
