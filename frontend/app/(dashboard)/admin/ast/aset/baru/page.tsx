'use client';

import { useRouter } from 'next/navigation';
import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { Callout } from '@/components/ui/Callout';
import { astApi, ralat, useCan, useRujukan } from '@/lib/ast';
import { Card, Field, inputClass, Select } from '../../_components/common';

interface Borang {
    lot_id: string;
    no_lot: string;
    id_kategori: string;
    jenis_aset: string;
    nama_aset: string;
    keterangan: string;
    alamat: string;
    latitud: string;
    longitud: string;
    tahun_pembinaan: string;
    tahun_perolehan: string;
    tarikh_perolehan: string;
    saiz_keluasan: string;
    unit_ukuran: string;
    kos_perolehan: string;
    nilai_semasa: string;
    id_laporan_penilaian: string;
    id_aset_induk: string;
    id_premis_pjk: string;
    id_pegawai_bertanggungjawab: string;
    tag: string;
}

const KOSONG: Borang = {
    lot_id: '', no_lot: '', id_kategori: '', jenis_aset: '', nama_aset: '', keterangan: '', alamat: '', latitud: '', longitud: '', tahun_pembinaan: '', tahun_perolehan: '',
    tarikh_perolehan: '', saiz_keluasan: '', unit_ukuran: 'm2', kos_perolehan: '', nilai_semasa: '', id_laporan_penilaian: '', id_aset_induk: '', id_premis_pjk: '', id_pegawai_bertanggungjawab: '', tag: '',
};
const DRAF = 'ast-draf-daftar';

/** Flow step 1–2: register an asset on a lot — reference number and QR are generated automatically; draft autosaved (SRS-GEN-07). */
export default function DaftarAset() {
    const router = useRouter();
    const can = useCan();
    const { data: ref } = useRujukan();
    const [b, setB] = useState<Borang>(KOSONG);
    const [cariLot, setCariLot] = useState('');
    const [cariPegawai, setCariPegawai] = useState('');
    const [sibuk, setSibuk] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    useEffect(() => {
        // Restore the autosaved draft (SRS-GEN-07) after hydration.
        Promise.resolve().then(() => {
            try {
                const d = localStorage.getItem(DRAF);
                if (d) setB({ ...KOSONG, ...(JSON.parse(d) as Partial<Borang>) });
            } catch { /* ignore */ }
        });
    }, []);
    useEffect(() => {
        const t = setTimeout(() => { try { localStorage.setItem(DRAF, JSON.stringify(b)); } catch { /* ignore */ } }, 400);
        return () => clearTimeout(t);
    }, [b]);

    const { data: lots } = useQuery({ queryKey: ['ast', 'cari-lot', cariLot], queryFn: () => astApi.cariLot(cariLot), enabled: cariLot.length >= 2 });
    const { data: pegawai } = useQuery({ queryKey: ['ast', 'pegawai', cariPegawai], queryFn: () => astApi.pegawai(cariPegawai), enabled: cariPegawai.length >= 2 });
    const { data: premis } = useQuery({ queryKey: ['ast', 'premis', b.lot_id], queryFn: () => astApi.premis(b.lot_id), enabled: !!b.lot_id });
    const { data: induk } = useQuery({ queryKey: ['ast', 'induk', b.lot_id], queryFn: () => astApi.aset({ lot_id: b.lot_id, per_page: 100 }), enabled: !!b.lot_id });
    const set = (k: keyof Borang) => (v: string) => setB((x) => ({ ...x, [k]: v }));
    const err = (k: string) => errors[k]?.[0];

    const lokasi = () => navigator.geolocation?.getCurrentPosition(
        (p) => setB((x) => ({ ...x, latitud: p.coords.latitude.toFixed(7), longitud: p.coords.longitude.toFixed(7) })),
        () => toast.error('Lokasi GPS tidak dapat diperoleh.'),
        { enableHighAccuracy: true, timeout: 15000 },
    );

    const hantar = async () => {
        setSibuk(true);
        setErrors({});
        const num = (v: string) => (v === '' ? undefined : Number(v));
        try {
            const r = await astApi.daftar({
                lot_id: b.lot_id, id_kategori: num(b.id_kategori), jenis_aset: b.jenis_aset || undefined, nama_aset: b.nama_aset, keterangan: b.keterangan || null, alamat: b.alamat || null,
                latitud: num(b.latitud), longitud: num(b.longitud), tahun_pembinaan: num(b.tahun_pembinaan), tahun_perolehan: num(b.tahun_perolehan), tarikh_perolehan: b.tarikh_perolehan || null,
                saiz_keluasan: num(b.saiz_keluasan), unit_ukuran: b.unit_ukuran, kos_perolehan: num(b.kos_perolehan), nilai_semasa: can('ast.sulit.kemas_kini') ? num(b.nilai_semasa) : undefined,
                id_laporan_penilaian: b.id_laporan_penilaian || null, kaedah_penilaian: b.nilai_semasa ? 'laporan_jpph' : undefined,
                id_aset_induk: b.id_aset_induk || null, id_premis_pjk: num(b.id_premis_pjk) ?? null, id_pegawai_bertanggungjawab: num(b.id_pegawai_bertanggungjawab) ?? null,
                tag: b.tag.split(',').map((t) => t.trim()).filter(Boolean),
            });
            try { localStorage.removeItem(DRAF); } catch { /* ignore */ }
            toast.success(r.message);
            router.push(`/admin/ast/aset/${r.data.id}`);
        } catch (e) {
            const errs = (e as { errors?: Record<string, string[]> }).errors;
            if (errs) setErrors(errs);
            toast.error(ralat(e));
        } finally {
            setSibuk(false);
        }
    };

    return (
        <PageContainer>
            <Callout type="info" title="Pendaftaran aset">Nombor rujukan dan kod QR unik dijana secara automatik. Titik aset diletakkan pada koordinat GPS yang diberikan atau pada pusat lot. Draf disimpan automatik pada pelayar ini.</Callout>
            <Card title="1. Lot induk">
                {b.lot_id ? (
                    <div className="flex flex-wrap items-center gap-3 text-body-sm">
                        <span>Lot dipilih: <strong>{b.no_lot}</strong></span>
                        <Button size="sm" variant="secondary" onClick={() => setB((x) => ({ ...x, lot_id: '', no_lot: '', id_premis_pjk: '', id_aset_induk: '' }))}>Tukar lot</Button>
                    </div>
                ) : (
                    <Field label="Cari lot (no. lot)" htmlFor="b-lot" help={err('lot_id')}>
                        <input id="b-lot" className={inputClass} value={cariLot} onChange={(e) => setCariLot(e.target.value)} placeholder="cth. PT 1234" />
                        <ul className="mt-2 divide-y divide-otl-divider">
                            {(lots ?? []).map((l) => (
                                <li key={l.id}><button type="button" className="w-full text-left py-2 text-body-sm hover:bg-bg-washed" onClick={() => setB((x) => ({ ...x, lot_id: l.id, no_lot: l.no_lot }))}>{l.no_lot} · {l.mukim}, {l.daerah}</button></li>
                            ))}
                        </ul>
                    </Field>
                )}
            </Card>
            <Card title="2. Maklumat aset">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Field label="Nama aset *" htmlFor="b-nama" help={err('nama_aset')}><input id="b-nama" className={inputClass} value={b.nama_aset} onChange={(e) => set('nama_aset')(e.target.value)} /></Field>
                    <Field label="Kategori *" htmlFor="b-kat" help={err('id_kategori')}><Select id="b-kat" value={b.id_kategori} onChange={set('id_kategori')} options={(ref?.kategori ?? []).map((k) => ({ kod: String(k.id), nama: k.nama }))} empty="Pilih kategori" /></Field>
                    <Field label="Jenis aset" htmlFor="b-jenis"><Select id="b-jenis" value={b.jenis_aset} onChange={set('jenis_aset')} options={ref?.jenis_aset ?? []} empty="Ikut kategori" /></Field>
                    <Field label="Alamat" htmlFor="b-alamat" className="sm:col-span-2"><input id="b-alamat" className={inputClass} value={b.alamat} onChange={(e) => set('alamat')(e.target.value)} /></Field>
                    <Field label="Tag (dipisah koma)" htmlFor="b-tag" help={ref?.tag_berstruktur.length ? `Berstruktur: ${ref.tag_berstruktur.join(', ')}` : undefined}><input id="b-tag" className={inputClass} value={b.tag} onChange={(e) => set('tag')(e.target.value)} /></Field>
                    <Field label="Latitud" htmlFor="b-lat" help={err('latitud')}><input id="b-lat" inputMode="decimal" className={inputClass} value={b.latitud} onChange={(e) => set('latitud')(e.target.value)} /></Field>
                    <Field label="Longitud" htmlFor="b-lng"><input id="b-lng" inputMode="decimal" className={inputClass} value={b.longitud} onChange={(e) => set('longitud')(e.target.value)} /></Field>
                    <div className="flex items-end"><Button variant="secondary" size="sm" onClick={lokasi}>Guna lokasi GPS saya</Button></div>
                    <Field label="Tahun pembinaan" htmlFor="b-tb" help={err('tahun_pembinaan')}><input id="b-tb" inputMode="numeric" className={inputClass} value={b.tahun_pembinaan} onChange={(e) => set('tahun_pembinaan')(e.target.value)} /></Field>
                    <Field label="Tahun perolehan" htmlFor="b-tp" help={err('tahun_perolehan')}><input id="b-tp" inputMode="numeric" className={inputClass} value={b.tahun_perolehan} onChange={(e) => set('tahun_perolehan')(e.target.value)} /></Field>
                    <Field label="Tarikh perolehan" htmlFor="b-tkp"><input id="b-tkp" type="date" className={inputClass} value={b.tarikh_perolehan} onChange={(e) => set('tarikh_perolehan')(e.target.value)} /></Field>
                    <Field label="Saiz / keluasan" htmlFor="b-saiz"><input id="b-saiz" inputMode="decimal" className={inputClass} value={b.saiz_keluasan} onChange={(e) => set('saiz_keluasan')(e.target.value)} /></Field>
                    <Field label="Unit ukuran" htmlFor="b-unit"><Select id="b-unit" value={b.unit_ukuran} onChange={set('unit_ukuran')} options={ref?.unit_ukuran ?? []} /></Field>
                    <Field label="Kos perolehan (RM)" htmlFor="b-kos" help={err('kos_perolehan') ?? 'Kos melebihi ambang memerlukan kelulusan perolehan (Workflow).'}><input id="b-kos" inputMode="decimal" className={inputClass} value={b.kos_perolehan} onChange={(e) => set('kos_perolehan')(e.target.value)} /></Field>
                    {can('ast.sulit.kemas_kini') && (
                        <>
                            <Field label="Nilai semasa (RM)" htmlFor="b-nilai" help={err('nilai_semasa') ?? 'Kosong = ikut kos perolehan.'}><input id="b-nilai" inputMode="decimal" className={inputClass} value={b.nilai_semasa} onChange={(e) => set('nilai_semasa')(e.target.value)} /></Field>
                            <Field label="Rujukan laporan penilaian" htmlFor="b-lap"><input id="b-lap" className={inputClass} value={b.id_laporan_penilaian} onChange={(e) => set('id_laporan_penilaian')(e.target.value)} /></Field>
                        </>
                    )}
                    <Field label="Keterangan" htmlFor="b-ket" className="sm:col-span-2 lg:col-span-3"><textarea id="b-ket" rows={2} className={inputClass} value={b.keterangan} onChange={(e) => set('keterangan')(e.target.value)} /></Field>
                </div>
            </Card>
            <Card title="3. Hierarki, premis dan pegawai">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Field label="Aset induk (kompleks / bazar)" htmlFor="b-induk" help={err('id_aset_induk')}>
                        <select id="b-induk" className={inputClass} value={b.id_aset_induk} disabled={!b.lot_id} onChange={(e) => set('id_aset_induk')(e.target.value)}>
                            <option value="">Tiada (aset utama)</option>
                            {(induk?.data ?? []).map((a) => <option key={a.id} value={a.id}>{a.nama_aset} ({a.no_rujukan_aset})</option>)}
                        </select>
                    </Field>
                    <Field label="Premis sewaan (Modul Pajakan)" htmlFor="b-premis" help={err('id_premis_pjk') ?? 'Pautkan jika aset ini ialah premis yang disewakan — tidak diduplikasi.'}>
                        <select id="b-premis" className={inputClass} value={b.id_premis_pjk} disabled={!b.lot_id} onChange={(e) => set('id_premis_pjk')(e.target.value)}>
                            <option value="">Tiada</option>
                            {(premis ?? []).map((p) => <option key={p.id} value={p.id}>{p.nama_premis} ({p.kod_premis})</option>)}
                        </select>
                    </Field>
                    <Field label="Pegawai bertanggungjawab" htmlFor="b-peg" help={b.id_pegawai_bertanggungjawab ? `Dipilih: #${b.id_pegawai_bertanggungjawab}` : 'Kosong = anda sendiri.'}>
                        <input id="b-peg" className={inputClass} value={cariPegawai} onChange={(e) => setCariPegawai(e.target.value)} placeholder="Cari nama pegawai" />
                        <ul className="mt-1">
                            {(pegawai ?? []).slice(0, 6).map((p) => (
                                <li key={p.id}><button type="button" className="text-body-sm text-txt-primary hover:underline" onClick={() => { set('id_pegawai_bertanggungjawab')(String(p.id)); setCariPegawai(p.nama); }}>{p.nama}</button></li>
                            ))}
                        </ul>
                    </Field>
                </div>
            </Card>
            <div className="flex flex-wrap justify-end gap-2">
                <Button variant="secondary" onClick={() => { setB(KOSONG); try { localStorage.removeItem(DRAF); } catch { /* ignore */ } }}>Kosongkan</Button>
                <Button disabled={sibuk || !b.lot_id || !b.nama_aset || !b.id_kategori} onClick={hantar}>{sibuk ? 'Mendaftar…' : 'Daftar aset & jana QR'}</Button>
            </div>
        </PageContainer>
    );
}
