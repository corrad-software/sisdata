'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Table } from '@/components/ui/Table';
import { astApi, masa, ralat, rm, STATUS_KERJA_VARIANT, tarikh, type AsetPenuh, type Rujukan } from '@/lib/ast';
import { Field, inputClass, KondisiBadge, Kosong, ModalBody, Select } from './common';

type P = { a: AsetPenuh; rj: Rujukan | undefined };

const dikemas = (qc: ReturnType<typeof useQueryClient>, id: string) => qc.invalidateQueries({ queryKey: ['ast', 'aset', id] });

/** SRS-AST-12: chronological gallery (own photos + MOD-PTN visit photos), upload with date/GPS. */
export function PanelGambar({ a, rj: ref }: P) {
    const qc = useQueryClient();
    const { data, error } = useQuery({ queryKey: ['ast', 'aset', a.id, 'gambar'], queryFn: () => astApi.gambar(a.id) });
    const [buka, setBuka] = useState<{ url: string; tajuk: string } | null>(null);
    const [kategori, setKategori] = useState('umum');
    const [ket, setKet] = useState('');
    const muatNaik = async (files: FileList | null) => {
        if (!files?.length) return;
        const pos = await new Promise<GeolocationPosition | null>((r) => (navigator.geolocation ? navigator.geolocation.getCurrentPosition(r, () => r(null), { timeout: 8000 }) : r(null)));
        for (const file of Array.from(files)) {
            const fd = new FormData();
            fd.append('fail', file);
            fd.append('kategori_gambar', kategori);
            fd.append('tarikh_gambar', new Date(file.lastModified || Date.now()).toISOString());
            if (ket) fd.append('keterangan', ket);
            if (pos) { fd.append('latitud', String(pos.coords.latitude)); fd.append('longitud', String(pos.coords.longitude)); }
            try { await astApi.simpanGambar(a.id, fd); } catch (e) { toast.error(ralat(e)); return; }
        }
        toast.success('Gambar disimpan.');
        qc.invalidateQueries({ queryKey: ['ast', 'aset', a.id, 'gambar'] });
    };
    const lihat = async (id: number | string, tajuk: string) => {
        const url = await astApi.urlGambar(a.id, id).catch(() => null);
        if (url) setBuka({ url, tajuk }); else toast.error('Gambar tidak dapat dibuka.');
    };

    return (
        <div className="space-y-4">
            {a.boleh.kemas_kini && (
                <div className="flex flex-wrap items-end gap-3">
                    <Field label="Kategori gambar" htmlFor="g-k"><Select id="g-k" value={kategori} onChange={setKategori} options={(ref?.kategori_gambar ?? []).filter((k) => k.kod !== 'pemeriksaan')} /></Field>
                    <Field label="Keterangan" htmlFor="g-ket"><input id="g-ket" className={inputClass} value={ket} onChange={(e) => setKet(e.target.value)} /></Field>
                    <label className="inline-flex items-center rounded-md bg-primary-600 text-white px-4 py-2 text-body-sm cursor-pointer">Muat naik gambar<input type="file" accept="image/*" multiple className="sr-only" onChange={(e) => muatNaik(e.target.files)} /></label>
                </div>
            )}
            {error && <p className="text-body-sm text-txt-danger">{ralat(error)}</p>}
            {data && data.length === 0 && <Kosong>Tiada gambar.</Kosong>}
            <ol className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                {(data ?? []).map((g) => (
                    <li key={g.id} className="rounded-lg border border-otl-gray-200 p-3 space-y-1 text-body-sm">
                        {g.imej_kecil
                            // eslint-disable-next-line @next/next/no-img-element -- base64 thumbnail from MOD-PTN
                            ? <img src={g.imej_kecil} alt={g.keterangan ?? g.kategori_nama} className="w-full h-32 object-cover rounded" />
                            : <button type="button" className="w-full h-32 rounded bg-bg-washed text-txt-primary hover:underline" onClick={() => lihat(g.id, g.kategori_nama)}>Lihat gambar</button>}
                        <p className="font-medium">{masa(g.tarikh_gambar)}</p>
                        <p className="text-body-xs text-txt-black-500">{g.kategori_nama}{g.perakam ? ` · ${g.perakam}` : ''}{g.latitud !== null ? ` · ${g.latitud.toFixed(5)}, ${g.longitud?.toFixed(5)}` : ' · tiada GPS'}</p>
                        {g.keterangan && <p>{g.keterangan}</p>}
                        {g.pautan && <Link href={g.pautan} className="text-body-xs text-txt-primary hover:underline">Buka lawatan pemantauan</Link>}
                    </li>
                ))}
            </ol>
            <Modal open={!!buka} onClose={() => setBuka(null)}>
                <ModalBody title={buka?.tajuk ?? ''} footer={<Button variant="secondary" onClick={() => setBuka(null)}>Tutup</Button>}>
                    {/* eslint-disable-next-line @next/next/no-img-element -- short-lived DMS URL */}
                    {buka && <img src={buka.url} alt={buka.tajuk} className="w-full rounded" />}
                </ModalBody>
            </Modal>
        </div>
    );
}

/** SRS-AST-20/21: inspections, recorded online (manual) or from the field app. */
export function PanelPemeriksaan({ a, rj: ref }: P) {
    const qc = useQueryClient();
    const { data } = useQuery({ queryKey: ['ast', 'aset', a.id, 'pemeriksaan'], queryFn: () => astApi.pemeriksaan(a.id) });
    const [buka, setBuka] = useState(false);
    const [f, setF] = useState({ tarikh_pemeriksaan: new Date().toLocaleDateString('sv-SE'), status_kondisi_dilapor: a.status_kondisi as string, isu_dikenalpasti: '', tindakan_disyorkan: '', cipta_kerja: false });
    const simpan = useMutation({
        mutationFn: () => astApi.rekodPemeriksaan(a.id, f),
        onSuccess: (r) => { toast.success(r.message); setBuka(false); dikemas(qc, a.id); },
        onError: (e) => toast.error(ralat(e)),
    });
    return (
        <div className="space-y-4">
            <div className="flex flex-wrap gap-2 items-center justify-between">
                <p className="text-body-sm">Pemeriksaan terakhir: <strong>{tarikh(a.tarikh_pemeriksaan_terakhir)}</strong> · tempoh ditetapkan {a.tempoh_pemeriksaan_bulan} bulan {a.pemeriksaan_lewat && <Badge variant="danger">Melebihi tempoh</Badge>}</p>
                <div className="flex gap-2">
                    {a.boleh.pemeriksaan && <Button size="sm" onClick={() => setBuka(true)}>Rekod pemeriksaan</Button>}
                    <Link href={`/admin/ast/lapangan?qr=${a.qr_ref}`} className="text-body-sm text-txt-primary hover:underline self-center">Aplikasi lapangan</Link>
                </div>
            </div>
            {data && data.length === 0 ? <Kosong>Belum pernah diperiksa.</Kosong> : (
                <Table head={<><th>Tarikh</th><th>Pegawai</th><th>Kaedah</th><th>Kondisi dilapor</th><th>Isu</th><th>Lokasi</th></>}>
                    {(data ?? []).map((p) => (
                        <tr key={p.id}>
                            <td className="whitespace-nowrap">{tarikh(p.tarikh_pemeriksaan)}</td>
                            <td>{p.pegawai ?? '—'}</td>
                            <td>{p.kaedah_nama}</td>
                            <td><KondisiBadge kondisi={p.status_kondisi_dilapor} label={p.kondisi_nama} /></td>
                            <td className="max-w-xs">{p.isu_dikenalpasti ?? '—'}{p.tindakan_disyorkan && <span className="block text-body-xs text-txt-black-500">Syor: {p.tindakan_disyorkan}</span>}</td>
                            <td>{p.jarak_dari_aset_m === null ? '—' : p.di_lokasi ? <Badge variant="success">Di lokasi</Badge> : <Badge variant="warning">{Math.round(p.jarak_dari_aset_m)} m dari aset</Badge>}</td>
                        </tr>
                    ))}
                </Table>
            )}
            <Modal open={buka} onClose={() => setBuka(false)}>
                <ModalBody title="Rekod pemeriksaan aset" footer={<><Button variant="secondary" onClick={() => setBuka(false)}>Batal</Button><Button disabled={simpan.isPending} onClick={() => simpan.mutate()}>Simpan</Button></>}>
                    <Field label="Tarikh pemeriksaan" htmlFor="p-t"><input id="p-t" type="date" className={inputClass} value={f.tarikh_pemeriksaan} onChange={(e) => setF({ ...f, tarikh_pemeriksaan: e.target.value })} /></Field>
                    <Field label="Status kondisi" htmlFor="p-k"><Select id="p-k" value={f.status_kondisi_dilapor} onChange={(v) => setF({ ...f, status_kondisi_dilapor: v })} options={ref?.kondisi ?? []} /></Field>
                    <Field label="Isu dikenal pasti" htmlFor="p-i"><textarea id="p-i" rows={2} className={inputClass} value={f.isu_dikenalpasti} onChange={(e) => setF({ ...f, isu_dikenalpasti: e.target.value })} /></Field>
                    <Field label="Tindakan disyorkan" htmlFor="p-s"><textarea id="p-s" rows={2} className={inputClass} value={f.tindakan_disyorkan} onChange={(e) => setF({ ...f, tindakan_disyorkan: e.target.value })} /></Field>
                    {a.boleh.penyelenggaraan && <label className="flex items-center gap-2 text-body-sm"><input type="checkbox" checked={f.cipta_kerja} onChange={(e) => setF({ ...f, cipta_kerja: e.target.checked })} /> Wujudkan kerja pembaikan daripada pemeriksaan ini</label>}
                </ModalBody>
            </Modal>
        </div>
    );
}

/** SRS-AST-14/15/17: schedules + work history and costs of this asset. */
export function PanelPenyelenggaraan({ a, rj: ref }: P) {
    const qc = useQueryClient();
    const { data } = useQuery({ queryKey: ['ast', 'aset', a.id, 'penyelenggaraan'], queryFn: () => astApi.penyelenggaraanAset(a.id) });
    const [kerja, setKerja] = useState(false);
    const [jadual, setJadual] = useState(false);
    const [k, setK] = useState({ jenis_kerja: 'pembaikan', jenis_penyelenggaraan: 'bangunan', keterangan: '', keutamaan: 'sederhana', tarikh_dijadual: '', kos_anggaran: '' });
    const [j, setJ] = useState({ jenis_penyelenggaraan: 'pemeriksaan_struktur', kitaran_bulan: '12', tarikh_jatuh_tempoh: '', tempoh_amaran_hari: '14' });
    const segar = () => qc.invalidateQueries({ queryKey: ['ast', 'aset', a.id, 'penyelenggaraan'] });
    const ciptaK = useMutation({ mutationFn: () => astApi.ciptaKerja({ ...k, id_aset: a.id, kos_anggaran: k.kos_anggaran || null, tarikh_dijadual: k.tarikh_dijadual || null }), onSuccess: (r) => { toast.success(r.message); setKerja(false); segar(); }, onError: (e) => toast.error(ralat(e)) });
    const ciptaJ = useMutation({ mutationFn: () => astApi.simpanJadual(a.id, { ...j, kitaran_bulan: Number(j.kitaran_bulan), tempoh_amaran_hari: Number(j.tempoh_amaran_hari) }), onSuccess: (r) => { toast.success(r.message); setJadual(false); segar(); }, onError: (e) => toast.error(ralat(e)) });
    const jumlah = (data?.kerja ?? []).filter((x) => x.status === 'selesai' && x.kos).reduce((s, x) => s + Math.round(Number(x.kos) * 100), 0);

    return (
        <div className="space-y-6">
            <section className="space-y-2">
                <div className="flex items-center justify-between gap-2"><h4 className="font-semibold">Jadual penyelenggaraan berkala</h4>{a.boleh.penyelenggaraan && <Button size="sm" variant="secondary" onClick={() => setJadual(true)}>Tambah jadual</Button>}</div>
                {data && data.jadual.length === 0 ? <Kosong>Tiada jadual.</Kosong> : (
                    <Table head={<><th>Jenis</th><th>Kitaran</th><th>Jatuh tempoh</th><th>Amaran</th><th>Terakhir dilaksana</th><th>Status</th></>}>
                        {(data?.jadual ?? []).map((x) => (
                            <tr key={x.id}>
                                <td>{x.jenis_nama}</td><td>{x.kitaran_bulan} bulan</td><td className="whitespace-nowrap">{tarikh(x.tarikh_jatuh_tempoh)}</td><td>{x.tempoh_amaran_hari} hari</td>
                                <td className="whitespace-nowrap">{tarikh(x.tarikh_terakhir_dilaksana)}</td>
                                <td>{x.status !== 'aktif' ? <Badge>Tidak aktif</Badge> : x.tertunggak ? <Badge variant="danger">Tertunggak</Badge> : x.hampir ? <Badge variant="warning">Hampir tempoh</Badge> : <Badge variant="success">Dalam jadual</Badge>}</td>
                            </tr>
                        ))}
                    </Table>
                )}
            </section>
            <section className="space-y-2">
                <div className="flex items-center justify-between gap-2"><h4 className="font-semibold">Sejarah kerja penyelenggaraan · jumlah kos siap {rm((jumlah / 100).toFixed(2))}</h4>{a.boleh.penyelenggaraan && <Button size="sm" onClick={() => setKerja(true)}>Wujudkan kerja</Button>}</div>
                {data && data.kerja.length === 0 ? <Kosong>Tiada rekod penyelenggaraan.</Kosong> : (
                    <Table head={<><th>No. rujukan</th><th>Jenis</th><th>Tarikh</th><th>Kontraktor</th><th>Kos</th><th>Sumber</th><th>Status</th></>}>
                        {(data?.kerja ?? []).map((x) => (
                            <tr key={x.id}>
                                <td className="whitespace-nowrap"><Link href={`/admin/ast/kerja/${x.id}`} className="text-txt-primary hover:underline">{x.no_rujukan}</Link></td>
                                <td>{x.jenis_nama}<span className="block text-body-xs text-txt-black-500">{x.jenis_kerja_nama}</span></td>
                                <td className="whitespace-nowrap">{tarikh(x.tarikh_siap ?? x.tarikh_dijadual)}</td>
                                <td>{x.nama_syarikat_pelaksana ?? '—'}</td>
                                <td className="whitespace-nowrap">{rm(x.kos ?? x.kos_anggaran)}{!x.kos && x.kos_anggaran && <span className="block text-body-xs text-txt-black-500">anggaran</span>}</td>
                                <td>{x.sumber_nama}</td>
                                <td><Badge variant={STATUS_KERJA_VARIANT[x.status] ?? 'neutral'}>{x.status_nama}</Badge>{x.tertunggak && <span className="ml-1"><Badge variant="danger">Lewat</Badge></span>}</td>
                            </tr>
                        ))}
                    </Table>
                )}
            </section>
            <Modal open={kerja} onClose={() => setKerja(false)}>
                <ModalBody title="Kerja penyelenggaraan baharu" footer={<><Button variant="secondary" onClick={() => setKerja(false)}>Batal</Button><Button disabled={!k.keterangan || ciptaK.isPending} onClick={() => ciptaK.mutate()}>Simpan</Button></>}>
                    <Field label="Jenis kerja" htmlFor="k-jk"><Select id="k-jk" value={k.jenis_kerja} onChange={(v) => setK({ ...k, jenis_kerja: v })} options={ref?.jenis_kerja ?? []} /></Field>
                    <Field label="Jenis penyelenggaraan" htmlFor="k-jp"><Select id="k-jp" value={k.jenis_penyelenggaraan} onChange={(v) => setK({ ...k, jenis_penyelenggaraan: v })} options={ref?.jenis_penyelenggaraan ?? []} /></Field>
                    <Field label="Keterangan" htmlFor="k-ket"><textarea id="k-ket" rows={2} className={inputClass} value={k.keterangan} onChange={(e) => setK({ ...k, keterangan: e.target.value })} /></Field>
                    <Field label="Keutamaan" htmlFor="k-ku"><Select id="k-ku" value={k.keutamaan} onChange={(v) => setK({ ...k, keutamaan: v })} options={ref?.keutamaan ?? []} /></Field>
                    <Field label="Tarikh dijadual" htmlFor="k-t"><input id="k-t" type="date" className={inputClass} value={k.tarikh_dijadual} onChange={(e) => setK({ ...k, tarikh_dijadual: e.target.value })} /></Field>
                    <Field label="Anggaran kos (RM)" htmlFor="k-kos" help="Melebihi ambang → kelulusan melalui Workflow sebelum dilaksanakan."><input id="k-kos" inputMode="decimal" className={inputClass} value={k.kos_anggaran} onChange={(e) => setK({ ...k, kos_anggaran: e.target.value })} /></Field>
                </ModalBody>
            </Modal>
            <Modal open={jadual} onClose={() => setJadual(false)}>
                <ModalBody title="Jadual penyelenggaraan berkala" footer={<><Button variant="secondary" onClick={() => setJadual(false)}>Batal</Button><Button disabled={!j.tarikh_jatuh_tempoh || ciptaJ.isPending} onClick={() => ciptaJ.mutate()}>Simpan</Button></>}>
                    <Field label="Jenis penyelenggaraan" htmlFor="j-jp"><Select id="j-jp" value={j.jenis_penyelenggaraan} onChange={(v) => setJ({ ...j, jenis_penyelenggaraan: v })} options={ref?.jenis_penyelenggaraan ?? []} /></Field>
                    <Field label="Kitaran (bulan)" htmlFor="j-k"><input id="j-k" inputMode="numeric" className={inputClass} value={j.kitaran_bulan} onChange={(e) => setJ({ ...j, kitaran_bulan: e.target.value })} /></Field>
                    <Field label="Tarikh jatuh tempoh pertama" htmlFor="j-t"><input id="j-t" type="date" className={inputClass} value={j.tarikh_jatuh_tempoh} onChange={(e) => setJ({ ...j, tarikh_jatuh_tempoh: e.target.value })} /></Field>
                    <Field label="Tempoh amaran (hari)" htmlFor="j-a"><input id="j-a" inputMode="numeric" className={inputClass} value={j.tempoh_amaran_hari} onChange={(e) => setJ({ ...j, tempoh_amaran_hari: e.target.value })} /></Field>
                </ModalBody>
            </Modal>
        </div>
    );
}

/** SRS-AST-18/19: value history by year + book value (straight-line). Update only for Finance / authorised Unit Aset. */
export function PanelNilai({ a, rj: ref }: P) {
    const qc = useQueryClient();
    const { data } = useQuery({ queryKey: ['ast', 'aset', a.id, 'nilai'], queryFn: () => astApi.sejarahNilai(a.id) });
    const [buka, setBuka] = useState(false);
    const [f, setF] = useState({ nilai: '', tarikh_penilaian: new Date().toLocaleDateString('sv-SE'), kaedah_penilaian: 'laporan_jpph', id_laporan_penilaian: '', catatan: '' });
    const simpan = useMutation({ mutationFn: () => astApi.nilai(a.id, f), onSuccess: (r) => { toast.success(r.message); setBuka(false); dikemas(qc, a.id); }, onError: (e) => toast.error(ralat(e)) });
    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-body-sm">Nilai semasa <strong>{rm(a.nilai_semasa)}</strong> ({tarikh(a.tarikh_nilai_semasa)}){a.susut_nilai && <> · nilai buku {new Date().getFullYear()} <strong>{rm(a.susut_nilai.nilai_buku)}</strong></>}</p>
                {a.boleh.nilai && <Button size="sm" onClick={() => setBuka(true)}>Kemas kini nilai</Button>}
            </div>
            <section className="space-y-2">
                <h4 className="font-semibold">Sejarah nilai mengikut tahun</h4>
                <Table head={<><th>Tahun</th><th>Tarikh penilaian</th><th>Nilai sebelum</th><th>Nilai</th><th>Kaedah</th><th>Rujukan laporan</th><th>Direkod oleh</th></>}>
                    {(data?.sejarah ?? []).map((n) => (
                        <tr key={String(n.id)}>
                            <td>{n.tahun}</td><td className="whitespace-nowrap">{tarikh(String(n.tarikh_penilaian))}</td><td className="whitespace-nowrap">{rm(n.nilai_sebelum as string | null)}</td>
                            <td className="whitespace-nowrap font-medium">{rm(n.nilai as string)}</td><td>{n.kaedah_nama}</td><td>{n.id_laporan_penilaian ?? '—'}</td><td>{n.pengguna ?? '—'}</td>
                        </tr>
                    ))}
                </Table>
            </section>
            {(data?.susut_nilai.length ?? 0) > 0 && (
                <section className="space-y-2">
                    <h4 className="font-semibold">Jadual susut nilai (garis lurus · hayat {a.hayat_guna_tahun} tahun)</h4>
                    <Table head={<><th>Tahun</th><th>Susut nilai</th><th>Susut terkumpul</th><th>Nilai buku</th></>}>
                        {(data?.susut_nilai ?? []).slice(-12).map((s) => (
                            <tr key={s.tahun}><td>{s.tahun}</td><td>{rm(s.susut)}</td><td>{rm(s.susut_terkumpul)}</td><td>{rm(s.nilai_buku)}</td></tr>
                        ))}
                    </Table>
                </section>
            )}
            <Modal open={buka} onClose={() => setBuka(false)}>
                <ModalBody title="Kemas kini nilai semasa" footer={<><Button variant="secondary" onClick={() => setBuka(false)}>Batal</Button><Button disabled={!f.nilai || simpan.isPending} onClick={() => simpan.mutate()}>Simpan</Button></>}>
                    <Field label="Nilai (RM)" htmlFor="n-n"><input id="n-n" inputMode="decimal" className={inputClass} value={f.nilai} onChange={(e) => setF({ ...f, nilai: e.target.value })} /></Field>
                    <Field label="Tarikh penilaian" htmlFor="n-t"><input id="n-t" type="date" className={inputClass} value={f.tarikh_penilaian} onChange={(e) => setF({ ...f, tarikh_penilaian: e.target.value })} /></Field>
                    <Field label="Kaedah penilaian" htmlFor="n-k"><Select id="n-k" value={f.kaedah_penilaian} onChange={(v) => setF({ ...f, kaedah_penilaian: v })} options={ref?.kaedah_penilaian ?? []} /></Field>
                    <Field label="No. rujukan laporan penilaian" htmlFor="n-r"><input id="n-r" className={inputClass} value={f.id_laporan_penilaian} onChange={(e) => setF({ ...f, id_laporan_penilaian: e.target.value })} /></Field>
                    <Field label="Catatan" htmlFor="n-c"><textarea id="n-c" rows={2} className={inputClass} value={f.catatan} onChange={(e) => setF({ ...f, catatan: e.target.value })} /></Field>
                </ModalBody>
            </Modal>
        </div>
    );
}

/** SRS-AST-22: documents (DMS), insurance and warranties. */
export function PanelDokumen({ a, rj: ref }: P) {
    const qc = useQueryClient();
    const { data: dok } = useQuery({ queryKey: ['ast', 'aset', a.id, 'dokumen'], queryFn: () => astApi.dokumen(a.id) });
    const { data: ins } = useQuery({ queryKey: ['ast', 'aset', a.id, 'insurans'], queryFn: () => astApi.insurans(a.id) });
    const [kat, setKat] = useState('pelan_bangunan');
    const [luput, setLuput] = useState('');
    const [polisi, setPolisi] = useState({ no_polisi: '', penanggung: '', tarikh_mula: '', tarikh_tamat: '', nilai_dilindungi: '' });
    const [jaminan, setJaminan] = useState({ perkara: '', pembekal: '', tarikh_mula: '', tarikh_tamat: '' });
    const segar = () => { qc.invalidateQueries({ queryKey: ['ast', 'aset', a.id, 'dokumen'] }); qc.invalidateQueries({ queryKey: ['ast', 'aset', a.id, 'insurans'] }); };
    const muat = async (files: FileList | null) => {
        if (!files?.length) return;
        const fd = new FormData();
        fd.append('fail', files[0]);
        fd.append('kategori_dokumen', kat);
        if (luput) fd.append('tarikh_luput', luput);
        try { const r = await astApi.simpanDokumen(a.id, fd); toast.success(r.message); segar(); } catch (e) { toast.error(ralat(e)); }
    };
    const buka = async (id: number) => { const u = await astApi.urlDokumen(a.id, id).catch(() => null); if (u) window.open(u, '_blank', 'noopener'); else toast.error('Dokumen tidak dapat dibuka.'); };
    const simpanP = useMutation({ mutationFn: () => astApi.simpanInsurans(a.id, { ...polisi, nilai_dilindungi: polisi.nilai_dilindungi || null }), onSuccess: (r) => { toast.success(r.message); segar(); }, onError: (e) => toast.error(ralat(e)) });
    const simpanJ = useMutation({ mutationFn: () => astApi.simpanJaminan(a.id, jaminan), onSuccess: (r) => { toast.success(r.message); segar(); }, onError: (e) => toast.error(ralat(e)) });

    return (
        <div className="space-y-6">
            <section className="space-y-2">
                <h4 className="font-semibold">Dokumen sokongan (DMS)</h4>
                {a.boleh.kemas_kini && (
                    <div className="flex flex-wrap items-end gap-3">
                        <Field label="Kategori" htmlFor="d-k"><Select id="d-k" value={kat} onChange={setKat} options={ref?.kategori_dokumen ?? []} /></Field>
                        <Field label="Tarikh luput" htmlFor="d-l"><input id="d-l" type="date" className={inputClass} value={luput} onChange={(e) => setLuput(e.target.value)} /></Field>
                        <label className="inline-flex items-center rounded-md bg-primary-600 text-white px-4 py-2 text-body-sm cursor-pointer">Muat naik<input type="file" className="sr-only" onChange={(e) => muat(e.target.files)} /></label>
                    </div>
                )}
                {dok && dok.length === 0 ? <Kosong>Tiada dokumen.</Kosong> : (
                    <Table head={<><th>Kategori</th><th>Tajuk</th><th>Tarikh luput</th><th>Dimuat naik</th><th></th></>}>
                        {(dok ?? []).map((d) => (
                            <tr key={d.id}><td>{d.kategori_nama}</td><td>{d.tajuk ?? '—'}</td><td>{tarikh(d.tarikh_luput)} {d.luput && <Badge variant="danger">Luput</Badge>}</td><td>{masa(d.tarikh_muat_naik)}</td><td><Button size="sm" variant="secondary" onClick={() => buka(d.id)}>Buka</Button></td></tr>
                        ))}
                    </Table>
                )}
            </section>
            <section className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div className="space-y-2">
                    <h4 className="font-semibold">Insurans</h4>
                    {(ins?.insurans ?? []).map((i) => (
                        <p key={String(i.id)} className="text-body-sm">{String(i.no_polisi)} · {String(i.penanggung)} · {tarikh(String(i.tarikh_mula))} – {tarikh(String(i.tarikh_tamat))} {i.aktif ? <Badge variant="success">Aktif</Badge> : <Badge variant="danger">Tamat</Badge>}</p>
                    ))}
                    {a.boleh.kemas_kini && (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <input aria-label="No. polisi" placeholder="No. polisi" className={inputClass} value={polisi.no_polisi} onChange={(e) => setPolisi({ ...polisi, no_polisi: e.target.value })} />
                            <input aria-label="Penanggung" placeholder="Penanggung insurans / takaful" className={inputClass} value={polisi.penanggung} onChange={(e) => setPolisi({ ...polisi, penanggung: e.target.value })} />
                            <label className="text-body-xs">Mula<input type="date" className={inputClass} value={polisi.tarikh_mula} onChange={(e) => setPolisi({ ...polisi, tarikh_mula: e.target.value })} /></label>
                            <label className="text-body-xs">Tamat<input type="date" className={inputClass} value={polisi.tarikh_tamat} onChange={(e) => setPolisi({ ...polisi, tarikh_tamat: e.target.value })} /></label>
                            <input aria-label="Nilai dilindungi" placeholder="Nilai dilindungi (RM)" inputMode="decimal" className={inputClass} value={polisi.nilai_dilindungi} onChange={(e) => setPolisi({ ...polisi, nilai_dilindungi: e.target.value })} />
                            <Button size="sm" variant="secondary" disabled={!polisi.no_polisi || !polisi.tarikh_tamat || simpanP.isPending} onClick={() => simpanP.mutate()}>Tambah polisi</Button>
                        </div>
                    )}
                </div>
                <div className="space-y-2">
                    <h4 className="font-semibold">Jaminan / waranti</h4>
                    {(ins?.jaminan ?? []).map((j) => (
                        <p key={String(j.id)} className="text-body-sm">{String(j.perkara)}{j.pembekal ? ` · ${String(j.pembekal)}` : ''} · tamat {tarikh(String(j.tarikh_tamat))} {j.aktif ? <Badge variant="success">Aktif</Badge> : <Badge variant="danger">Tamat</Badge>}</p>
                    ))}
                    {a.boleh.kemas_kini && (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <input aria-label="Perkara" placeholder="Perkara (cth. bumbung, lif)" className={inputClass} value={jaminan.perkara} onChange={(e) => setJaminan({ ...jaminan, perkara: e.target.value })} />
                            <input aria-label="Pembekal" placeholder="Pembekal" className={inputClass} value={jaminan.pembekal} onChange={(e) => setJaminan({ ...jaminan, pembekal: e.target.value })} />
                            <label className="text-body-xs">Mula<input type="date" className={inputClass} value={jaminan.tarikh_mula} onChange={(e) => setJaminan({ ...jaminan, tarikh_mula: e.target.value })} /></label>
                            <label className="text-body-xs">Tamat<input type="date" className={inputClass} value={jaminan.tarikh_tamat} onChange={(e) => setJaminan({ ...jaminan, tarikh_tamat: e.target.value })} /></label>
                            <Button size="sm" variant="secondary" disabled={!jaminan.perkara || !jaminan.tarikh_tamat || simpanJ.isPending} onClick={() => simpanJ.mutate()}>Tambah jaminan</Button>
                        </div>
                    )}
                </div>
            </section>
        </div>
    );
}

/** SRS-AST-05/23: condition history + audit trail of value / condition / officer. */
export function PanelSejarah({ a }: P) {
    const { data } = useQuery({ queryKey: ['ast', 'aset', a.id, 'sejarah'], queryFn: () => astApi.sejarah(a.id) });
    return (
        <div className="space-y-6">
            <section className="space-y-2">
                <h4 className="font-semibold">Sejarah status kondisi</h4>
                <Table head={<><th>Tarikh</th><th>Daripada</th><th>Kepada</th><th>Pegawai</th><th>Catatan</th></>}>
                    {(data?.kondisi ?? []).map((k) => (
                        <tr key={String(k.id)}><td className="whitespace-nowrap">{masa(String(k.tarikh_perubahan))}</td><td>{k.status_lama ? <KondisiBadge kondisi={String(k.status_lama)} /> : '—'}</td><td><KondisiBadge kondisi={String(k.status_baharu)} /></td><td>{k.pengguna ?? '—'}</td><td>{k.catatan ?? '—'}</td></tr>
                    ))}
                </Table>
            </section>
            <section className="space-y-2">
                <h4 className="font-semibold">Jejak audit (nilai, kondisi, pegawai bertanggungjawab)</h4>
                {data && data.audit.length === 0 ? <Kosong>Tiada perubahan direkod.</Kosong> : (
                    <Table head={<><th>Masa</th><th>Medan</th><th>Sebelum</th><th>Selepas</th><th>Pengguna</th><th>Sebab</th></>}>
                        {(data?.audit ?? []).map((x) => (
                            <tr key={String(x.id)}><td className="whitespace-nowrap">{masa(String(x.cap_masa))}</td><td>{x.medan_nama}</td><td>{x.nilai_sebelum ?? '—'}</td><td>{x.nilai_selepas ?? '—'}</td><td>{x.pengguna ?? '—'}</td><td>{x.sebab ?? '—'}</td></tr>
                        ))}
                    </Table>
                )}
            </section>
        </div>
    );
}

/** SRS-AST-06..10: QR (view, print, regenerate, deactivate) + scan log. */
export function PanelQr({ a, rj: ref }: P) {
    const qc = useQueryClient();
    const [page, setPage] = useState(1);
    const { data: log } = useQuery({ queryKey: ['ast', 'aset', a.id, 'imbasan', page], queryFn: () => astApi.imbasan(a.id, page), enabled: a.boleh.imbasan });
    const [templat, setTemplat] = useState('besar');
    const semula = useMutation({ mutationFn: (sebab: string) => astApi.janaSemulaQr(a.id, sebab), onSuccess: (r) => { toast.success(r.message); dikemas(qc, a.id); }, onError: (e) => toast.error(ralat(e)) });
    const status = useMutation({ mutationFn: ({ ref: r, aktif, sebab }: { ref: string; aktif: boolean; sebab?: string }) => astApi.statusQr(r, aktif, sebab), onSuccess: (r) => { toast.success(r.message); dikemas(qc, a.id); }, onError: (e) => toast.error(ralat(e)) });
    const semasa = a.qr.find((q) => q.qr_ref === a.qr_ref);
    return (
        <div className="space-y-6">
            <div className="flex flex-wrap gap-6 items-start">
                {/* eslint-disable-next-line @next/next/no-img-element -- QR SVG from the API */}
                <img src={astApi.qrSvgUrl(a.id)} alt={`Kod QR ${a.qr_ref}`} width={180} height={180} className="border border-otl-gray-200 rounded" />
                <div className="space-y-2 text-body-sm min-w-0">
                    <p>Rujukan QR: <strong className="font-mono">{a.qr_ref}</strong> {semasa?.status_aktif ? <Badge variant="success">Aktif</Badge> : <Badge variant="danger">Tidak aktif</Badge>}</p>
                    <p className="break-all">Pautan awam: <a className="text-txt-primary hover:underline" href={semasa?.pautan_awam} target="_blank" rel="noreferrer">{semasa?.pautan_awam}</a></p>
                    <p>Dicetak {semasa?.bilangan_cetakan ?? 0} kali.</p>
                    <div className="flex flex-wrap gap-2">
                        {a.boleh.label && !a.status_arkib && (
                            <>
                                <label htmlFor="q-t" className="sr-only">Templat label</label>
                                <select id="q-t" className={`${inputClass} w-auto`} value={templat} onChange={(e) => setTemplat(e.target.value)}>{(ref?.templat_label ?? []).map((t) => <option key={t.kod} value={t.kod}>{t.nama}</option>)}</select>
                                <Button size="sm" variant="secondary" onClick={() => import('@/lib/ast').then((m) => m.cetakLabel([a.id], templat)).catch((e) => toast.error(ralat(e)))}>Cetak label</Button>
                            </>
                        )}
                        {a.boleh.qr && !a.status_arkib && <Button size="sm" variant="secondary" onClick={() => { const s = window.prompt('Sebab jana semula (cth. label rosak / hilang):'); if (s) semula.mutate(s); }}>Jana semula QR</Button>}
                        {a.boleh.qr && semasa && semasa.status_aktif && <Button size="sm" variant="danger" onClick={() => { const s = window.prompt('Sebab nyahaktif:'); if (s) status.mutate({ ref: semasa.qr_ref, aktif: false, sebab: s }); }}>Nyahaktif</Button>}
                        {a.boleh.qr && semasa && !semasa.status_aktif && !a.status_arkib && <Button size="sm" variant="secondary" onClick={() => status.mutate({ ref: semasa.qr_ref, aktif: true })}>Aktifkan semula</Button>}
                    </div>
                </div>
            </div>
            {a.qr.length > 1 && (
                <section className="space-y-2">
                    <h4 className="font-semibold">Rujukan QR terdahulu (dikhaskan, tidak diguna semula)</h4>
                    <ul className="text-body-sm space-y-1">{a.qr.filter((q) => q.qr_ref !== a.qr_ref).map((q) => <li key={q.qr_ref}><span className="font-mono">{q.qr_ref}</span> · {masa(q.tarikh_jana)} · {q.sebab_nyahaktif ?? 'tidak aktif'}</li>)}</ul>
                </section>
            )}
            {a.boleh.imbasan && (
                <section className="space-y-2">
                    <h4 className="font-semibold">Log imbasan ({log?.meta.total ?? 0})</h4>
                    <Table head={<><th>Masa</th><th>Pengimbas</th><th>Peranan</th><th>Paparan</th><th>Lokasi peranti</th></>}>
                        {(log?.data ?? []).map((l) => (
                            <tr key={l.id}><td className="whitespace-nowrap">{masa(l.cap_masa)}</td><td>{l.pengguna ?? 'Tanpa akaun'}</td><td>{l.peranan ?? '—'}</td><td>{{ minimum: 'Minimum', penuh: 'Penuh', tidak_aktif: 'QR tidak aktif' }[l.jenis_paparan] ?? l.jenis_paparan}</td><td>{l.latitud !== null ? `${l.latitud.toFixed(5)}, ${l.longitud?.toFixed(5)}` : '—'}</td></tr>
                        ))}
                    </Table>
                    {log && log.meta.last_page > 1 && (
                        <div className="flex gap-2"><Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>Sebelum</Button><Button size="sm" variant="secondary" disabled={page >= log.meta.last_page} onClick={() => setPage(page + 1)}>Seterusnya</Button></div>
                    )}
                </section>
            )}
        </div>
    );
}

/** Flow step 12: disposal / write-off / demolition request (WFE) and history. */
export function PanelPelupusan({ a, rj: ref }: P) {
    const qc = useQueryClient();
    const { data } = useQuery({ queryKey: ['ast', 'aset', a.id, 'pelupusan'], queryFn: () => astApi.pelupusan(a.id) });
    const [f, setF] = useState({ jenis: 'pelupusan', sebab: '', kaedah: 'tender', nilai_pelupusan: '' });
    const hantar = useMutation({ mutationFn: () => astApi.mohonPelupusan(a.id, { ...f, nilai_pelupusan: f.nilai_pelupusan || null }), onSuccess: (r) => { toast.success(r.message); qc.invalidateQueries({ queryKey: ['ast', 'aset', a.id] }); }, onError: (e) => toast.error(ralat(e)) });
    return (
        <div className="space-y-4">
            {a.status_arkib && <p className="text-body-sm">Aset diarkibkan pada {tarikh(a.tarikh_arkib)} — {a.sebab_arkib}. Rekod kekal untuk rujukan audit.</p>}
            {(data ?? []).length > 0 && (
                <Table head={<><th>Tarikh</th><th>Jenis</th><th>Sebab</th><th>Pemohon</th><th>Status</th><th>Ulasan</th></>}>
                    {(data ?? []).map((p) => (
                        <tr key={String(p.id)}><td>{masa(String(p.created_at))}</td><td>{p.jenis_nama}</td><td className="max-w-xs">{p.sebab}</td><td>{p.pemohon ?? '—'}</td><td><Badge variant={p.status === 'lulus' ? 'success' : p.status === 'menunggu' ? 'warning' : 'neutral'}>{p.status_nama}</Badge></td><td>{p.ulasan ?? '—'}</td></tr>
                    ))}
                </Table>
            )}
            {a.boleh.pelupusan && (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-2xl">
                    <Field label="Jenis" htmlFor="l-j"><Select id="l-j" value={f.jenis} onChange={(v) => setF({ ...f, jenis: v })} options={ref?.jenis_pelupusan ?? []} /></Field>
                    <Field label="Kaedah" htmlFor="l-k"><Select id="l-k" value={f.kaedah} onChange={(v) => setF({ ...f, kaedah: v })} options={ref?.kaedah_pelupusan ?? []} /></Field>
                    <Field label="Sebab / justifikasi" htmlFor="l-s" className="sm:col-span-2"><textarea id="l-s" rows={3} className={inputClass} value={f.sebab} onChange={(e) => setF({ ...f, sebab: e.target.value })} /></Field>
                    <Field label="Nilai pelupusan dijangka (RM)" htmlFor="l-n"><input id="l-n" inputMode="decimal" className={inputClass} value={f.nilai_pelupusan} onChange={(e) => setF({ ...f, nilai_pelupusan: e.target.value })} /></Field>
                    <div className="flex items-end"><Button variant="danger" disabled={f.sebab.length < 10 || hantar.isPending} onClick={() => { if (window.confirm('Hantar permohonan untuk kelulusan? Aset akan diarkibkan selepas diluluskan.')) hantar.mutate(); }}>Hantar untuk kelulusan</Button></div>
                </div>
            )}
        </div>
    );
}
