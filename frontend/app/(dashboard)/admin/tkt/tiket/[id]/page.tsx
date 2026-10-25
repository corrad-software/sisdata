'use client';

import { use, useState } from 'react';
import Link from 'next/link';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { ralat, tarikhMasa, tempoh, tktApi, useRujukan, type TiketButiran } from '@/lib/tkt';
import { Card, Field, InfoGrid, inputClass, Keterukan, Kosong, ModalBody, Select, SlaBadge, StatusTiket } from '../../_components/common';

type Aksi = null | 'status' | 'tugaskan' | 'serah' | 'eskalasi' | 'ubah' | 'pendua' | 'aduan' | 'kelulusan' | 'vendor' | 'buka_semula' | 'kepuasan';

const PENERIMA = [
    { kod: 'penyelia', nama: 'Penyelia sokongan' },
    { kod: 'pengurus', nama: 'Pengurus projek' },
    { kod: 'peranan:jawatankuasa_projek', nama: 'Jawatankuasa Projek MAIS' },
];

const KATEGORI_ADUAN = [
    { kod: 'PENCEROBOHAN', nama: 'Pencerobohan tanah' },
    { kod: 'BINAAN_HARAM', nama: 'Binaan tanpa kebenaran' },
    { kod: 'PERTIKAIAN_SEMPADAN', nama: 'Pertikaian sempadan' },
    { kod: 'KEROSAKAN_ASET', nama: 'Kerosakan aset / bangunan' },
    { kod: 'LAIN', nama: 'Lain-lain' },
];

/** SRS-TKT-05/11/12/13: ticket record — timeline, internal vs visible notes, support and reporter actions. */
export default function TiketPage({ params }: { params: Promise<{ id: string }> }) {
    const { id } = use(params);
    const qc = useQueryClient();
    const { data: ref } = useRujukan();
    const { data: t, error, isLoading } = useQuery({ queryKey: ['tkt', 'tiket', id], queryFn: () => tktApi.tiket(id).then((r) => r.data) });
    const [aksi, setAksi] = useState<Aksi>(null);
    const [v, setV] = useState<Record<string, string>>({});
    const [mesej, setMesej] = useState<{ ok: boolean; teks: string } | null>(null);
    const [nota, setNota] = useState({ jenis: 'kelihatan_pelapor', kandungan: '' });
    const layak = useQuery({ queryKey: ['tkt', 'layak', id], queryFn: () => tktApi.pegawaiLayak(id).then((r) => r.data), enabled: aksi === 'tugaskan' || aksi === 'serah' });

    const segar = (r: { data: TiketButiran; message: string }) => {
        qc.setQueryData(['tkt', 'tiket', id], r.data);
        qc.invalidateQueries({ queryKey: ['tkt', 'senarai'] });
        setMesej({ ok: true, teks: r.message });
        setAksi(null);
        setV({});
    };
    const m = useMutation({
        mutationFn: ({ action, body }: { action: string; body: Record<string, unknown> }) => (action === 'vendor' ? tktApi.vendor(id, body) : tktApi.tindakan(id, action, body)),
        onSuccess: segar,
        onError: (e) => setMesej({ ok: false, teks: ralat(e) }),
    });
    const fail = useMutation({
        mutationFn: (form: FormData) => tktApi.lampiran(id, form),
        onSuccess: segar,
        onError: (e) => setMesej({ ok: false, teks: ralat(e) }),
    });
    const set = (k: string) => (val: string) => setV((x) => ({ ...x, [k]: val }));
    const buka = (a: Aksi, awal: Record<string, string> = {}) => {
        setV(awal);
        setAksi(a);
    };

    if (isLoading) return <PageContainer><Kosong>Memuatkan…</Kosong></PageContainer>;
    if (error || !t) return <PageContainer><Alert type="error">{ralat(error) || 'Tiket tidak dijumpai.'}</Alert></PageContainer>;

    const statusOpts = (ref?.status ?? []).filter((s) => t.peralihan.includes(s.kod));
    const pasukanOpts = Array.from(new Map((layak.data ?? []).map((p) => [p.id_pasukan, { kod: String(p.id_pasukan), nama: `${p.pasukan} (${p.aras})` }])).values());
    const juruteknikOpts = (layak.data ?? []).filter((p) => !v.id_pasukan || String(p.id_pasukan) === v.id_pasukan).map((p) => ({ kod: String(p.id), nama: `${p.nama} — ${p.peranan}, ${p.beban} tiket terbuka` }));

    function hantarAksi() {
        const body: Record<string, unknown> = { ...v };
        const action = aksi === 'serah' ? 'serah-aras' : aksi === 'aduan' ? 'rujuk-aduan' : aksi === 'kelulusan' ? 'mohon-kelulusan' : aksi === 'buka_semula' ? 'buka-semula' : (aksi as string);
        if (aksi === 'tugaskan' || aksi === 'serah') body.id_pasukan = Number(v.id_pasukan);
        if (aksi === 'tugaskan' && v.id_juruteknik) body.id_juruteknik = Number(v.id_juruteknik);
        if (aksi === 'status' && v.masa_henti_minit) body.masa_henti_minit = Number(v.masa_henti_minit);
        if (aksi === 'ubah' && v.keterukan) body.keterukan = Number(v.keterukan);
        if (aksi === 'kepuasan') body.skor = Number(v.skor);
        m.mutate({ action, body });
    }

    async function caraPendua() {
        const no = (v.nombor ?? '').trim();
        if (!no) return;
        const r = await tktApi.senarai({ q: no, per_halaman: 5 });
        const induk = r.data.find((x) => x.nombor_rujukan === no);
        if (!induk) return setMesej({ ok: false, teks: `Tiket ${no} tidak dijumpai.` });
        m.mutate({ action: 'pendua', body: { id_tiket_induk: induk.id, catatan: v.catatan } });
    }

    async function muatTurun(lampiran: number) {
        try {
            const r = await tktApi.muatTurun(id, lampiran);
            window.open(r.data.url, '_blank', 'noopener');
        } catch (e) {
            setMesej({ ok: false, teks: ralat(e) });
        }
    }

    const terhad = t.peranan_saya.sokongan;

    return (
        <PageContainer>
            <div data-rujukan-rekod={`tkt_tiket:${t.id}`} className="space-y-6">
                {mesej && <Alert type={mesej.ok ? 'success' : 'error'}>{mesej.teks}</Alert>}

                <Card
                    title={
                        <span className="flex flex-wrap items-center gap-2">
                            {t.nombor_rujukan} <StatusTiket t={t} /> <Keterukan t={t} /> {t.klasifikasi === 'sulit' && <Badge variant="danger">Sulit</Badge>}
                            {t.sintetik && <Badge variant="neutral">Sintetik</Badge>}
                        </span>
                    }
                    actions={
                        <>
                            {t.boleh.urus && statusOpts.length > 0 && <Button size="sm" onClick={() => buka('status', { status: statusOpts[0].kod })}>Tukar status</Button>}
                            {t.boleh.urus && t.status === 'disahkan' && <Button size="sm" onClick={() => m.mutate({ action: 'status', body: { status: 'tutup' } })}>Tutup tiket</Button>}
                            {t.boleh.sahkan && <Button size="sm" onClick={() => m.mutate({ action: 'sahkan', body: {} })}>Sahkan penyelesaian</Button>}
                            {t.boleh.buka_semula && <Button size="sm" variant="secondary" onClick={() => buka('buka_semula')}>Buka semula</Button>}
                            {t.boleh.kepuasan && <Button size="sm" variant="secondary" onClick={() => buka('kepuasan', { skor: '5' })}>Beri penilaian</Button>}
                        </>
                    }
                >
                    <h1 className="font-heading text-heading-2xs font-semibold text-txt-black-900 break-words">{t.tajuk}</h1>
                    <p className="whitespace-pre-wrap break-words text-body-sm text-txt-black-700">{t.penerangan}</p>
                    <InfoGrid
                        items={[
                            ['Kategori', t.kategori_nama],
                            ['Modul', t.modul_nama],
                            ['Saluran', t.saluran_nama],
                            ['Pelapor', t.pelapor?.nama ?? t.emel_pelapor ?? '—'],
                            ['Dibuka', tarikhMasa(t.tarikh_buka)],
                            ['Pasukan / aras', `${t.pasukan?.nama ?? '—'} · ${t.aras_sokongan}`],
                            ['Juruteknik', t.juruteknik?.nama ?? 'Belum ditugaskan'],
                            ['Vendor', t.nama_vendor ? `${t.nama_vendor}${t.rujukan_vendor ? ` (${t.rujukan_vendor})` : ''}` : '—'],
                            ['Versi sistem', t.versi_sistem],
                            ['Buka semula', String(t.bilangan_buka_semula)],
                            ['Kelulusan perubahan', t.status_kelulusan ?? '—'],
                            ['Kepuasan', t.kepuasan_skor ? `${t.kepuasan_skor}/5${t.kepuasan_ulasan ? ` — ${t.kepuasan_ulasan}` : ''}` : '—'],
                        ]}
                    />
                    {t.penyelesaian && (
                        <div className="rounded-md bg-bg-success-50 p-3 text-body-sm">
                            <p className="font-medium text-txt-success">Penyelesaian</p>
                            <p className="whitespace-pre-wrap break-words">{t.penyelesaian}</p>
                            {t.punca_akar && <p className="mt-1 text-txt-black-700">Punca akar: {t.punca_akar}</p>}
                        </div>
                    )}
                    {t.pendua_dicadang && <Alert type="info">Mungkin pendua kepada <Link href={`/admin/tkt/tiket/${t.pendua_dicadang.id}`} className="underline">{t.pendua_dicadang.nombor_rujukan}</Link> (skor {t.pendua_dicadang.skor}).</Alert>}
                </Card>

                <Card title={<span className="flex items-center gap-2">SLA <SlaBadge t={t} /></span>}>
                    <InfoGrid
                        items={[
                            ['Sasaran respons', tarikhMasa(t.sla.respons.sasaran)],
                            ['Respons pertama', t.sla.respons.sebenar ? tarikhMasa(t.sla.respons.sebenar) : `Baki ${tempoh(t.sla.respons.baki_minit)}`],
                            ['Sasaran penyelesaian', tarikhMasa(t.sla.selesai.sasaran)],
                            ['Selesai', t.sla.selesai.sebenar ? tarikhMasa(t.sla.selesai.sebenar) : `Baki ${tempoh(t.sla.selesai.baki_minit)}`],
                            ['Waktu perkhidmatan', t.sla.waktu_perkhidmatan === '24x7' ? '24 x 7' : 'Waktu pejabat (cuti umum Selangor dikecualikan)'],
                            ['Jeda (menunggu pelapor)', `${tempoh(t.sla.jeda_minit)}${t.sla.dijeda ? ' — sedang dijeda' : ''}`],
                        ]}
                    />
                    {t.boleh.urus && (
                        <div className="flex flex-wrap gap-2">
                            <Button size="sm" variant="secondary" onClick={() => buka('tugaskan')}>Tugaskan semula</Button>
                            <Button size="sm" variant="secondary" onClick={() => buka('serah')}>Serah aras / vendor</Button>
                            <Button size="sm" variant="secondary" onClick={() => buka('eskalasi', { penerima: 'penyelia' })}>Eskalasi</Button>
                            <Button size="sm" variant="secondary" onClick={() => buka('ubah', { keterukan: String(t.keterukan), kategori: t.kategori, modul: t.modul ?? 'umum', klasifikasi: t.klasifikasi })}>Ubah keterukan / kategori</Button>
                            <Button size="sm" variant="outline" onClick={() => buka('vendor', { rujukan_vendor: t.rujukan_vendor ?? '', nama_vendor: t.nama_vendor ?? '' })}>Rujukan vendor</Button>
                            <Button size="sm" variant="outline" onClick={() => buka('pendua', { nombor: t.pendua_dicadang?.nombor_rujukan ?? '' })}>Tanda pendua</Button>
                            <Button size="sm" variant="outline" onClick={() => buka('aduan', { kategori: 'PENCEROBOHAN' })}>Rujuk ke Modul Aduan</Button>
                            {t.kategori === 'permintaan_perubahan' && ['disiasat', 'dalam_tindakan'].includes(t.status) && <Button size="sm" variant="outline" onClick={() => buka('kelulusan')}>Mohon kelulusan perubahan</Button>}
                        </div>
                    )}
                </Card>

                <Card title="Kronologi">
                    <ol className="space-y-3">
                        {t.kronologi.map((k) => (
                            <li key={k.id} className={`rounded-md border p-3 text-body-sm ${k.jenis === 'dalaman' ? 'border-otl-warning-200 bg-bg-warning-50' : 'border-otl-gray-200'}`}>
                                <div className="flex flex-wrap items-center gap-2 text-body-xs text-txt-black-500">
                                    <Badge variant={k.jenis === 'dalaman' ? 'warning' : k.jenis === 'kelihatan_pelapor' || k.jenis === 'pelapor' ? 'info' : 'neutral'}>{k.jenis === 'dalaman' ? 'Dalaman (tidak dilihat pelapor)' : k.jenis_nama}</Badge>
                                    <span>{k.pengguna}</span>
                                    <span>{tarikhMasa(k.cap_masa)}</span>
                                </div>
                                {(k.status_dari || k.status_ke) && <p className="mt-1 text-txt-black-700">{k.status_dari ? `${k.status_dari} → ` : ''}{k.status_ke}</p>}
                                {k.kandungan && <p className="mt-1 whitespace-pre-wrap break-words text-txt-black-900">{k.kandungan}</p>}
                            </li>
                        ))}
                    </ol>
                    {(t.boleh.urus || t.boleh.balas) && (
                        <form
                            className="space-y-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                if (!nota.kandungan.trim()) return;
                                const req = t.boleh.urus ? { action: 'catatan', body: nota } : { action: 'balas', body: { kandungan: nota.kandungan } };
                                m.mutate(req, { onSuccess: () => setNota((x) => ({ ...x, kandungan: '' })) });
                            }}
                        >
                            {t.boleh.urus && (
                                <div className="flex flex-wrap gap-4 text-body-sm" role="radiogroup" aria-label="Jenis catatan">
                                    {[['kelihatan_pelapor', 'Balasan kepada pelapor'], ['dalaman', 'Catatan dalaman']].map(([k, l]) => (
                                        <label key={k} className="flex items-center gap-1.5">
                                            <input type="radio" name="jenis" checked={nota.jenis === k} onChange={() => setNota((x) => ({ ...x, jenis: k }))} /> {l}
                                        </label>
                                    ))}
                                </div>
                            )}
                            <label htmlFor="nota" className="sr-only">Catatan</label>
                            <textarea id="nota" rows={3} className={inputClass} value={nota.kandungan} onChange={(e) => setNota((x) => ({ ...x, kandungan: e.target.value }))} placeholder={t.boleh.urus ? 'Tulis catatan…' : 'Balas kepada pasukan sokongan…'} />
                            <div className="flex justify-end">
                                <Button type="submit" size="sm" disabled={m.isPending}>Hantar</Button>
                            </div>
                        </form>
                    )}
                </Card>

                <Card title="Lampiran">
                    {t.lampiran.length === 0 ? (
                        <Kosong>Tiada lampiran.</Kosong>
                    ) : (
                        <ul className="divide-y divide-otl-divider text-body-sm">
                            {t.lampiran.map((l) => (
                                <li key={l.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                                    <span className="break-all">
                                        {l.nama_fail} {l.tangkapan_skrin && <Badge variant="info">Tangkapan skrin (dengan persetujuan)</Badge>} {!l.kelihatan_pelapor && <Badge variant="warning">Dalaman</Badge>}
                                    </span>
                                    <Button size="sm" variant="outline" onClick={() => muatTurun(l.id)}>Muat turun</Button>
                                </li>
                            ))}
                        </ul>
                    )}
                    {t.boleh.lampiran && (
                        <form
                            className="flex flex-wrap items-center gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                const form = new FormData(e.currentTarget);
                                if (!(form.get('fail') instanceof File) || (form.get('fail') as File).size === 0) return;
                                fail.mutate(form);
                                e.currentTarget.reset();
                            }}
                        >
                            <label htmlFor="lampiran-fail" className="sr-only">Fail</label>
                            <input id="lampiran-fail" name="fail" type="file" className="text-body-xs" />
                            {t.boleh.urus && (
                                <label className="flex items-center gap-1 text-body-xs">
                                    <input type="checkbox" name="dalaman" value="1" /> Dalaman sahaja
                                </label>
                            )}
                            <Button type="submit" size="sm" variant="secondary" disabled={fail.isPending}>Muat naik</Button>
                        </form>
                    )}
                </Card>

                {terhad && t.konteks && (
                    <Card title="Konteks yang ditangkap">
                        <InfoGrid
                            items={[
                                ['Skrin', t.konteks.skrin],
                                ['Kod fungsi', t.konteks.kod_fungsi],
                                ['URL', <span key="u" className="break-all">{t.konteks.url ?? '—'}</span>],
                                ['Rujukan rekod', t.konteks.rujukan_rekod],
                                ['Peranan', t.konteks.peranan_pengguna.join(', ') || '—'],
                                ['ID pengguna', t.konteks.id_pengguna ? String(t.konteks.id_pengguna) : '—'],
                                ['Pelayar', t.konteks.pelayar],
                                ['Versi sistem / UI', `${t.konteks.versi_sistem ?? '—'} / ${t.konteks.versi_ui ?? '—'}`],
                                ['Skrin / zon masa', `${t.konteks.saiz_skrin ?? '—'} · ${t.konteks.zon_masa ?? '—'}`],
                            ]}
                        />
                    </Card>
                )}

                {terhad && t.log_ralat && (
                    <Card title="Log ralat berkaitan (ID permintaan)">
                        {t.log_ralat.entri.length > 0 ? (
                            <ul className="space-y-2 text-body-sm">
                                {t.log_ralat.entri.map((e) => (
                                    <li key={e.id} className="break-words">
                                        <a href={e.pautan} className="text-txt-primary hover:underline">{e.kod_ralat ?? e.aras}</a> · {e.modul ?? '—'} · {e.tarikh_masa}
                                        <p className="text-body-xs text-txt-black-500">{e.mesej}</p>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-body-sm text-txt-black-500">{t.log_ralat.boleh_lihat ? 'Tiada entri log ralat untuk ID permintaan ini.' : 'Anda tiada kebenaran melihat log ralat; ID korelasi:'}</p>
                        )}
                        {!t.log_ralat.boleh_lihat && <p className="break-all font-mono text-body-xs">{t.log_ralat.id_korelasi.join(', ') || '—'}</p>}
                    </Card>
                )}

                {terhad && (
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <Card title="Eskalasi">
                            {(t.eskalasi ?? []).length === 0 ? <Kosong>Tiada eskalasi.</Kosong> : (
                                <ul className="space-y-1 text-body-sm">
                                    {(t.eskalasi ?? []).map((e, i) => (
                                        <li key={i}>Peringkat {e.peringkat} · {e.sebab_nama} ({e.sasaran_nama}) → {e.penerima ?? '—'} · {tarikhMasa(e.cap_masa)}</li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                        <Card title="Pangkalan Pengetahuan & integrasi">
                            {(t.artikel_kb ?? []).length === 0 ? <p className="text-body-sm text-txt-black-500">Tiada draf artikel lagi (dijana apabila tiket selesai).</p> : (
                                <ul className="text-body-sm">
                                    {(t.artikel_kb ?? []).map((a) => (
                                        <li key={a.id}><Link href={a.pautan} className="text-txt-primary hover:underline">{a.tajuk}</Link> <Badge variant="neutral">{a.status}</Badge></li>
                                    ))}
                                </ul>
                            )}
                            {t.pautan_aduan && <p className="text-body-sm">Sistem Aduan berpusat: <strong>{t.pautan_aduan.nombor_aduan_berpusat}</strong> · penyegerakan {t.pautan_aduan.status_penyegerakan}</p>}
                            {t.id_aduan_adu && <p className="text-body-sm"><Link href={`/admin/adu/aduan/${t.id_aduan_adu}`} className="text-txt-primary hover:underline">Aduan tanah berkaitan</Link></p>}
                        </Card>
                    </div>
                )}
            </div>

            <Modal open={aksi !== null} onClose={() => setAksi(null)}>
                <ModalBody
                    title={{ status: 'Tukar status', tugaskan: 'Tugaskan semula', serah: 'Serah ke aras / vendor', eskalasi: 'Eskalasi manual', ubah: 'Ubah keterukan / kategori', pendua: 'Tanda sebagai pendua', aduan: 'Rujuk ke Modul Aduan', kelulusan: 'Mohon kelulusan permintaan perubahan', vendor: 'Rujukan vendor', buka_semula: 'Buka semula tiket', kepuasan: 'Penilaian kepuasan' }[aksi ?? 'status'] ?? ''}
                    footer={
                        <>
                            <Button variant="secondary" onClick={() => setAksi(null)}>Batal</Button>
                            <Button onClick={() => (aksi === 'pendua' ? caraPendua() : hantarAksi())} disabled={m.isPending}>Simpan</Button>
                        </>
                    }
                >
                    {aksi === 'status' && (
                        <>
                            <Field label="Status baharu" htmlFor="a-st"><Select id="a-st" value={v.status ?? ''} onChange={set('status')} options={statusOpts} /></Field>
                            {v.status === 'selesai' && (
                                <>
                                    <Field label="Punca akar" htmlFor="a-pa"><textarea id="a-pa" rows={2} className={inputClass} value={v.punca_akar ?? t.punca_akar ?? ''} onChange={(e) => set('punca_akar')(e.target.value)} /></Field>
                                    <Field label="Penyelesaian" htmlFor="a-pe"><textarea id="a-pe" rows={3} className={inputClass} value={v.penyelesaian ?? t.penyelesaian ?? ''} onChange={(e) => set('penyelesaian')(e.target.value)} /></Field>
                                    <Field label="Tindakan pembetulan / pencegahan (pilihan)" htmlFor="a-tp"><textarea id="a-tp" rows={2} className={inputClass} value={v.tindakan_pembetulan ?? ''} onChange={(e) => set('tindakan_pembetulan')(e.target.value)} /></Field>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <Field label="Masa henti (minit)" htmlFor="a-mh"><input id="a-mh" type="number" min={0} className={inputClass} value={v.masa_henti_minit ?? ''} onChange={(e) => set('masa_henti_minit')(e.target.value)} /></Field>
                                        <Field label="Sebab melepasi SLA" htmlFor="a-sl"><input id="a-sl" className={inputClass} value={v.sebab_lepas_sla ?? ''} onChange={(e) => set('sebab_lepas_sla')(e.target.value)} /></Field>
                                    </div>
                                    <p className="text-body-xs text-txt-black-500">Draf artikel masalah → punca → penyelesaian akan dihantar ke Pangkalan Pengetahuan untuk semakan.</p>
                                </>
                            )}
                            <Field label="Catatan (pilihan)" htmlFor="a-ct"><textarea id="a-ct" rows={2} className={inputClass} value={v.catatan ?? ''} onChange={(e) => set('catatan')(e.target.value)} /></Field>
                        </>
                    )}
                    {(aksi === 'tugaskan' || aksi === 'serah') && (
                        <>
                            <Field label="Pasukan" htmlFor="a-ps"><Select id="a-ps" value={v.id_pasukan ?? ''} onChange={set('id_pasukan')} options={pasukanOpts} empty="Pilih pasukan" /></Field>
                            {aksi === 'tugaskan' && <Field label="Juruteknik" htmlFor="a-jt" help="Kosongkan untuk penugasan automatik (beban paling ringan)."><Select id="a-jt" value={v.id_juruteknik ?? ''} onChange={set('id_juruteknik')} options={juruteknikOpts} empty="Automatik" /></Field>}
                            {aksi === 'serah' && <Field label="No. rujukan vendor" htmlFor="a-rv"><input id="a-rv" className={inputClass} value={v.rujukan_vendor ?? ''} onChange={(e) => set('rujukan_vendor')(e.target.value)} /></Field>}
                            <Field label={aksi === 'tugaskan' ? 'Sebab penugasan semula' : 'Catatan serahan'} htmlFor="a-sb"><textarea id="a-sb" rows={2} className={inputClass} value={v[aksi === 'tugaskan' ? 'sebab' : 'catatan'] ?? ''} onChange={(e) => set(aksi === 'tugaskan' ? 'sebab' : 'catatan')(e.target.value)} /></Field>
                        </>
                    )}
                    {aksi === 'eskalasi' && (
                        <>
                            <Field label="Kepada" htmlFor="a-pn"><Select id="a-pn" value={v.penerima ?? ''} onChange={set('penerima')} options={PENERIMA} /></Field>
                            <Field label="Sebab" htmlFor="a-es"><textarea id="a-es" rows={2} className={inputClass} value={v.sebab ?? ''} onChange={(e) => set('sebab')(e.target.value)} /></Field>
                        </>
                    )}
                    {aksi === 'ubah' && (
                        <>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <Field label="Keterukan" htmlFor="a-kt"><Select id="a-kt" value={v.keterukan ?? ''} onChange={set('keterukan')} options={ref?.keterukan ?? []} /></Field>
                                <Field label="Kategori" htmlFor="a-kg"><Select id="a-kg" value={v.kategori ?? ''} onChange={set('kategori')} options={ref?.kategori ?? []} /></Field>
                                <Field label="Modul" htmlFor="a-md"><Select id="a-md" value={v.modul ?? ''} onChange={set('modul')} options={ref?.modul ?? []} /></Field>
                                <Field label="Klasifikasi" htmlFor="a-kl"><Select id="a-kl" value={v.klasifikasi ?? ''} onChange={set('klasifikasi')} options={[{ kod: 'dalaman', nama: 'Dalaman' }, { kod: 'sulit', nama: 'Sulit' }]} /></Field>
                            </div>
                            <Field label="Sebab perubahan" htmlFor="a-us"><textarea id="a-us" rows={2} className={inputClass} value={v.sebab ?? ''} onChange={(e) => set('sebab')(e.target.value)} /></Field>
                        </>
                    )}
                    {aksi === 'pendua' && (
                        <>
                            <Field label="No. tiket induk" htmlFor="a-in"><input id="a-in" className={inputClass} value={v.nombor ?? ''} onChange={(e) => set('nombor')(e.target.value)} placeholder="TKT-202611-00001" /></Field>
                            <Field label="Catatan" htmlFor="a-pc"><input id="a-pc" className={inputClass} value={v.catatan ?? ''} onChange={(e) => set('catatan')(e.target.value)} /></Field>
                        </>
                    )}
                    {aksi === 'aduan' && (
                        <>
                            <p className="text-body-sm text-txt-black-700">Gunakan jika laporan ini sebenarnya aduan tanah (bukan isu sistem). Aduan dibuka dalam Modul Aduan dan tiket ini ditutup.</p>
                            <Field label="Kategori aduan" htmlFor="a-ka"><Select id="a-ka" value={v.kategori ?? ''} onChange={set('kategori')} options={KATEGORI_ADUAN} /></Field>
                            <Field label="Kod daerah (pilihan)" htmlFor="a-kd"><input id="a-kd" className={inputClass} value={v.kod_daerah ?? ''} onChange={(e) => set('kod_daerah')(e.target.value)} maxLength={4} /></Field>
                        </>
                    )}
                    {aksi === 'kelulusan' && <Field label="Justifikasi" htmlFor="a-ju"><textarea id="a-ju" rows={3} className={inputClass} value={v.justifikasi ?? ''} onChange={(e) => set('justifikasi')(e.target.value)} /></Field>}
                    {aksi === 'vendor' && (
                        <>
                            <Field label="Nama vendor" htmlFor="a-nv"><input id="a-nv" className={inputClass} value={v.nama_vendor ?? ''} onChange={(e) => set('nama_vendor')(e.target.value)} /></Field>
                            <Field label="No. rujukan vendor" htmlFor="a-rv2"><input id="a-rv2" className={inputClass} value={v.rujukan_vendor ?? ''} onChange={(e) => set('rujukan_vendor')(e.target.value)} /></Field>
                        </>
                    )}
                    {aksi === 'buka_semula' && <Field label="Sebab buka semula" htmlFor="a-bs"><textarea id="a-bs" rows={2} className={inputClass} value={v.sebab ?? ''} onChange={(e) => set('sebab')(e.target.value)} /></Field>}
                    {aksi === 'kepuasan' && (
                        <>
                            <Field label="Skor (1 = sangat tidak puas, 5 = sangat puas)" htmlFor="a-sk"><Select id="a-sk" value={v.skor ?? '5'} onChange={set('skor')} options={['5', '4', '3', '2', '1'].map((s) => ({ kod: s, nama: s }))} /></Field>
                            <Field label="Ulasan (pilihan)" htmlFor="a-ul"><textarea id="a-ul" rows={2} className={inputClass} value={v.ulasan ?? ''} onChange={(e) => set('ulasan')(e.target.value)} /></Field>
                        </>
                    )}
                </ModalBody>
            </Modal>
        </PageContainer>
    );
}
