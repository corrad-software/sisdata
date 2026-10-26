'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { AnchorButton, Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Table } from '@/components/ui/Table';
import { PAKEJ_SERAHAN, ralat, tempoh, tktApi, useCan, useRujukan, type SlaKonfig } from '@/lib/tkt';
import { Card, Field, inputClass, Kosong, Select } from '../_components/common';

type Tahap = { peringkat: number; penerima: string; selepas_minit: number };

/** SRS-TKT-08/09/10/15/21: SLA thresholds & escalation hierarchy, teams, assignment rules, central mapping, hand-over. */
export default function KonfigurasiPage() {
    const can = useCan();
    const ubah = can('tkt.konfigurasi.kemas_kini');
    const qc = useQueryClient();
    const { data: ref } = useRujukan();
    const { data, error } = useQuery({ queryKey: ['tkt', 'konfigurasi'], queryFn: () => tktApi.konfigurasi().then((r) => r.data) });
    const [mesej, setMesej] = useState<{ ok: boolean; teks: string } | null>(null);
    const [sla, setSla] = useState<{ keterukan: string; ambang_respons: string; ambang_penyelesaian: string; waktu_perkhidmatan: string; ambang_amaran_peratus: string; tarikh_berkuat_kuasa: string; catatan: string; hierarki: Tahap[] } | null>(null);
    const [pasukan, setPasukan] = useState({ kod: '', nama: '', aras: 'L1', jenis: 'dalaman', nama_vendor: '' });
    const [ahli, setAhli] = useState<{ pasukan: string; q: string; id_pengguna: string; peranan: string }>({ pasukan: '', q: '', id_pengguna: '', peranan: 'juruteknik' });
    const [aturan, setAturan] = useState({ modul: '', kategori: '', keterukan: '', id_pasukan: '', keutamaan: '10' });
    const [peta, setPeta] = useState({ jenis: 'kategori', nilai_tempatan: '', nilai_berpusat: '', arah: 'dua_hala' });
    const [pindah, setPindah] = useState({ dari_pasukan: '', ke_pasukan: '', catatan: '', nyahaktif_sumber: false });
    const cari = useQuery({ queryKey: ['tkt', 'pengguna', ahli.q], queryFn: () => tktApi.pengguna(ahli.q).then((r) => r.data), enabled: ubah && ahli.q.length >= 2 });

    const run = useMutation({
        mutationFn: (fn: () => Promise<{ message: string }>) => fn(),
        onSuccess: (r) => {
            setMesej({ ok: true, teks: r.message });
            qc.invalidateQueries({ queryKey: ['tkt', 'konfigurasi'] });
        },
        onError: (e) => setMesej({ ok: false, teks: ralat(e) }),
    });

    function editSla(c: SlaKonfig) {
        setSla({
            keterukan: String(c.keterukan),
            ambang_respons: String(c.ambang_respons),
            ambang_penyelesaian: String(c.ambang_penyelesaian),
            waktu_perkhidmatan: c.waktu_perkhidmatan,
            ambang_amaran_peratus: String(c.ambang_amaran_peratus),
            tarikh_berkuat_kuasa: new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Kuala_Lumpur' }),
            catatan: '',
            hierarki: c.hierarki_eskalasi,
        });
    }

    if (error) return <PageContainer><Alert type="error">{ralat(error)}</Alert></PageContainer>;
    if (!data) return <PageContainer><Kosong>Memuatkan…</Kosong></PageContainer>;
    const teams = data.pasukan.map((p) => ({ kod: String(p.id), nama: `${p.nama} (${p.aras})` }));
    const semasa = data.sla.filter((s) => s.semasa);

    return (
        <PageContainer>
            {mesej && <Alert type={mesej.ok ? 'success' : 'error'}>{mesej.teks}</Alert>}
            <Alert type="warning">{data.nota_sla}</Alert>

            <Card title="SLA mengikut tahap keterukan">
                <div className="overflow-x-auto">
                    <Table head={<><th>Keterukan</th><th>Respons</th><th>Penyelesaian</th><th className="hidden sm:table-cell">Waktu</th><th className="hidden md:table-cell">Amaran</th><th className="hidden lg:table-cell">Hierarki eskalasi</th><th /></>}>
                        {semasa.map((c) => (
                            <tr key={c.id}>
                                <td>{c.keterukan_nama}<p className="text-body-xs text-txt-black-500">Berkuat kuasa {c.tarikh_berkuat_kuasa}</p></td>
                                <td>{tempoh(c.ambang_respons)}</td>
                                <td>{tempoh(c.ambang_penyelesaian)}</td>
                                <td className="hidden sm:table-cell">{c.waktu_perkhidmatan === '24x7' ? '24 x 7' : 'Waktu pejabat'}</td>
                                <td className="hidden md:table-cell">{c.ambang_amaran_peratus}%</td>
                                <td className="hidden lg:table-cell text-body-xs">{c.hierarki_eskalasi.map((h) => `${h.peringkat}. ${h.penerima} (+${tempoh(h.selepas_minit)})`).join(' → ')}</td>
                                <td>{ubah && <Button size="sm" variant="secondary" onClick={() => editSla(c)}>Versi baharu</Button>}</td>
                            </tr>
                        ))}
                    </Table>
                </div>
                <p className="text-body-xs text-txt-black-500">Ambang dalam minit masa perkhidmatan. Versi lama disimpan ({data.sla.length - semasa.length} versi sejarah). Jam SLA berhenti semasa tiket menunggu maklum balas pelapor.</p>
                {sla && (
                    <form className="space-y-3 rounded-md border border-otl-gray-200 p-3" onSubmit={(e) => {
                        e.preventDefault();
                        run.mutate(() => tktApi.simpan('sla', { keterukan: Number(sla.keterukan), ambang_respons: Number(sla.ambang_respons), ambang_penyelesaian: Number(sla.ambang_penyelesaian), waktu_perkhidmatan: sla.waktu_perkhidmatan, ambang_amaran_peratus: Number(sla.ambang_amaran_peratus), tarikh_berkuat_kuasa: sla.tarikh_berkuat_kuasa, catatan: sla.catatan, hierarki_eskalasi: sla.hierarki }), { onSuccess: () => setSla(null) });
                    }}>
                        <p className="text-body-sm font-medium">Versi baharu — Tahap keterukan {sla.keterukan}</p>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <Field label="Respons (minit)" htmlFor="s-r"><input id="s-r" type="number" min={1} className={inputClass} value={sla.ambang_respons} onChange={(e) => setSla({ ...sla, ambang_respons: e.target.value })} /></Field>
                            <Field label="Penyelesaian (minit)" htmlFor="s-p"><input id="s-p" type="number" min={1} className={inputClass} value={sla.ambang_penyelesaian} onChange={(e) => setSla({ ...sla, ambang_penyelesaian: e.target.value })} /></Field>
                            <Field label="Amaran (% tempoh)" htmlFor="s-a"><input id="s-a" type="number" min={10} max={99} className={inputClass} value={sla.ambang_amaran_peratus} onChange={(e) => setSla({ ...sla, ambang_amaran_peratus: e.target.value })} /></Field>
                            <Field label="Waktu perkhidmatan" htmlFor="s-w"><Select id="s-w" value={sla.waktu_perkhidmatan} onChange={(v) => setSla({ ...sla, waktu_perkhidmatan: v })} options={[{ kod: 'waktu_pejabat', nama: 'Waktu pejabat' }, { kod: '24x7', nama: '24 x 7' }]} /></Field>
                            <Field label="Berkuat kuasa" htmlFor="s-t"><input id="s-t" type="date" className={inputClass} value={sla.tarikh_berkuat_kuasa} onChange={(e) => setSla({ ...sla, tarikh_berkuat_kuasa: e.target.value })} /></Field>
                            <Field label="Catatan" htmlFor="s-c"><input id="s-c" className={inputClass} value={sla.catatan} onChange={(e) => setSla({ ...sla, catatan: e.target.value })} placeholder="cth. Kontrak pindaan 2" /></Field>
                        </div>
                        <p className="text-body-sm font-medium">Hierarki eskalasi (selepas SLA dilepasi)</p>
                        {sla.hierarki.map((h, i) => (
                            <div key={i} className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <Field label={`Peringkat ${h.peringkat} — penerima`} htmlFor={`h-p-${i}`}><input id={`h-p-${i}`} className={inputClass} value={h.penerima} onChange={(e) => setSla({ ...sla, hierarki: sla.hierarki.map((x, j) => (j === i ? { ...x, penerima: e.target.value } : x)) })} /></Field>
                                <Field label="Selepas (minit)" htmlFor={`h-m-${i}`}><input id={`h-m-${i}`} type="number" min={0} className={inputClass} value={h.selepas_minit} onChange={(e) => setSla({ ...sla, hierarki: sla.hierarki.map((x, j) => (j === i ? { ...x, selepas_minit: Number(e.target.value) } : x)) })} /></Field>
                                <div className="flex items-end"><Button type="button" size="sm" variant="outline" onClick={() => setSla({ ...sla, hierarki: sla.hierarki.filter((_, j) => j !== i).map((x, j) => ({ ...x, peringkat: j + 1 })) })}>Buang</Button></div>
                            </div>
                        ))}
                        <p className="text-body-xs text-txt-black-500">Penerima: <code>juruteknik</code>, <code>penyelia</code>, <code>pengurus</code> atau <code>peranan:&lt;kod_peranan&gt;</code>.</p>
                        <div className="flex flex-wrap justify-end gap-2">
                            <Button type="button" size="sm" variant="secondary" onClick={() => setSla({ ...sla, hierarki: [...sla.hierarki, { peringkat: sla.hierarki.length + 1, penerima: 'pengurus', selepas_minit: 60 }] })}>Tambah peringkat</Button>
                            <Button type="button" size="sm" variant="secondary" onClick={() => setSla(null)}>Batal</Button>
                            <Button type="submit" size="sm">Simpan versi</Button>
                        </div>
                    </form>
                )}
            </Card>

            <Card title="Pasukan sokongan (L1 Meja Bantuan MAIS → L2 vendor / BTM)">
                <div className="space-y-4">
                    {data.pasukan.map((p) => (
                        <div key={p.id} className="rounded-md border border-otl-gray-200 p-3">
                            <p className="text-body-sm font-medium">
                                {p.nama} <Badge variant="neutral">{p.aras}</Badge> <Badge variant={p.jenis === 'vendor' ? 'warning' : 'info'}>{p.jenis === 'vendor' ? `Vendor: ${p.nama_vendor ?? '—'}` : 'Dalaman'}</Badge> {!p.aktif && <Badge variant="danger">Tidak aktif</Badge>}
                            </p>
                            <p className="text-body-xs text-txt-black-500">Modul: {p.modul === null ? 'Semua' : p.modul.join(', ')}</p>
                            <ul className="mt-1 text-body-sm">
                                {p.ahli.map((a) => (
                                    <li key={a.id} className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="break-words">{a.nama} · {a.peranan}{!a.aktif && ' (tidak aktif)'}</span>
                                        {ubah && <Button size="sm" variant="outline" onClick={() => run.mutate(() => tktApi.simpanAhli(p.id, { id_pengguna: a.id_pengguna, peranan: a.peranan, aktif: !a.aktif }))}>{a.aktif ? 'Nyahaktif' : 'Aktifkan'}</Button>}
                                    </li>
                                ))}
                                {p.ahli.length === 0 && <li className="text-txt-black-500">Tiada ahli.</li>}
                            </ul>
                        </div>
                    ))}
                </div>
                {ubah && (
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <form className="space-y-2 rounded-md border border-otl-gray-200 p-3" onSubmit={(e) => { e.preventDefault(); if (!ahli.pasukan || !ahli.id_pengguna) return; run.mutate(() => tktApi.simpanAhli(Number(ahli.pasukan), { id_pengguna: Number(ahli.id_pengguna), peranan: ahli.peranan })); }}>
                            <p className="text-body-sm font-medium">Tambah ahli</p>
                            <Field label="Pasukan" htmlFor="ah-p"><Select id="ah-p" value={ahli.pasukan} onChange={(v) => setAhli({ ...ahli, pasukan: v })} options={teams} empty="Pilih" /></Field>
                            <Field label="Cari pengguna" htmlFor="ah-q"><input id="ah-q" className={inputClass} value={ahli.q} onChange={(e) => setAhli({ ...ahli, q: e.target.value })} placeholder="Nama atau e-mel" /></Field>
                            <Field label="Pengguna" htmlFor="ah-u"><Select id="ah-u" value={ahli.id_pengguna} onChange={(v) => setAhli({ ...ahli, id_pengguna: v })} options={(cari.data ?? []).map((u) => ({ kod: String(u.id), nama: `${u.nama} (${u.emel})` }))} empty="Pilih" /></Field>
                            <Field label="Peranan" htmlFor="ah-r"><Select id="ah-r" value={ahli.peranan} onChange={(v) => setAhli({ ...ahli, peranan: v })} options={ref?.peranan_ahli ?? []} /></Field>
                            <div className="flex justify-end"><Button type="submit" size="sm">Tambah</Button></div>
                        </form>
                        <form className="space-y-2 rounded-md border border-otl-gray-200 p-3" onSubmit={(e) => { e.preventDefault(); run.mutate(() => tktApi.simpan('pasukan', { ...pasukan, kod: pasukan.kod.toUpperCase() })); }}>
                            <p className="text-body-sm font-medium">Pasukan baharu</p>
                            <Field label="Kod" htmlFor="pk-k"><input id="pk-k" required className={inputClass} value={pasukan.kod} onChange={(e) => setPasukan({ ...pasukan, kod: e.target.value })} placeholder="BTM_L2" /></Field>
                            <Field label="Nama" htmlFor="pk-n"><input id="pk-n" required className={inputClass} value={pasukan.nama} onChange={(e) => setPasukan({ ...pasukan, nama: e.target.value })} /></Field>
                            <div className="grid grid-cols-2 gap-2">
                                <Field label="Aras" htmlFor="pk-a"><Select id="pk-a" value={pasukan.aras} onChange={(v) => setPasukan({ ...pasukan, aras: v })} options={[{ kod: 'L1', nama: 'L1' }, { kod: 'L2', nama: 'L2' }]} /></Field>
                                <Field label="Jenis" htmlFor="pk-j"><Select id="pk-j" value={pasukan.jenis} onChange={(v) => setPasukan({ ...pasukan, jenis: v })} options={[{ kod: 'dalaman', nama: 'Dalaman (MAIS/BTM)' }, { kod: 'vendor', nama: 'Vendor' }]} /></Field>
                            </div>
                            {pasukan.jenis === 'vendor' && <Field label="Nama vendor" htmlFor="pk-v"><input id="pk-v" className={inputClass} value={pasukan.nama_vendor} onChange={(e) => setPasukan({ ...pasukan, nama_vendor: e.target.value })} /></Field>}
                            <div className="flex justify-end"><Button type="submit" size="sm">Cipta</Button></div>
                        </form>
                    </div>
                )}
            </Card>

            <Card title="Peraturan penugasan automatik">
                <div className="overflow-x-auto">
                    <Table head={<><th>Modul</th><th>Kategori</th><th>Tahap keterukan</th><th>Pasukan</th><th>Keutamaan</th><th /></>}>
                        {data.peraturan.map((r) => (
                            <tr key={r.id} className={r.aktif ? '' : 'opacity-50'}>
                                <td>{r.modul ?? 'Semua'}</td>
                                <td>{r.kategori ?? 'Semua'}</td>
                                <td>{r.keterukan ?? 'Semua'}</td>
                                <td>{r.pasukan}</td>
                                <td>{r.keutamaan}</td>
                                <td>{ubah && <Button size="sm" variant="outline" onClick={() => run.mutate(() => tktApi.simpan('peraturan', { modul: r.modul, kategori: r.kategori, keterukan: r.keterukan, id_pasukan: r.id_pasukan, id_juruteknik: r.id_juruteknik, keutamaan: r.keutamaan, aktif: !r.aktif }, r.id))}>{r.aktif ? 'Nyahaktif' : 'Aktifkan'}</Button>}</td>
                            </tr>
                        ))}
                    </Table>
                </div>
                {ubah && (
                    <form className="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-6 gap-2" onSubmit={(e) => { e.preventDefault(); run.mutate(() => tktApi.simpan('peraturan', { modul: aturan.modul || null, kategori: aturan.kategori || null, keterukan: aturan.keterukan ? Number(aturan.keterukan) : null, id_pasukan: Number(aturan.id_pasukan), keutamaan: Number(aturan.keutamaan) })); }}>
                        <Field label="Modul" htmlFor="r-m"><Select id="r-m" value={aturan.modul} onChange={(v) => setAturan({ ...aturan, modul: v })} options={ref?.modul ?? []} empty="Semua" /></Field>
                        <Field label="Kategori" htmlFor="r-k"><Select id="r-k" value={aturan.kategori} onChange={(v) => setAturan({ ...aturan, kategori: v })} options={ref?.kategori ?? []} empty="Semua" /></Field>
                        <Field label="Tahap keterukan" htmlFor="r-s"><Select id="r-s" value={aturan.keterukan} onChange={(v) => setAturan({ ...aturan, keterukan: v })} options={ref?.keterukan ?? []} empty="Semua" /></Field>
                        <Field label="Pasukan" htmlFor="r-p"><Select id="r-p" value={aturan.id_pasukan} onChange={(v) => setAturan({ ...aturan, id_pasukan: v })} options={teams} empty="Pilih" required /></Field>
                        <Field label="Keutamaan" htmlFor="r-u"><input id="r-u" type="number" className={inputClass} value={aturan.keutamaan} onChange={(e) => setAturan({ ...aturan, keutamaan: e.target.value })} /></Field>
                        <div className="flex items-end"><Button type="submit" size="sm">Tambah</Button></div>
                    </form>
                )}
            </Card>

            <Card title="Pemetaan Sistem Aduan berpusat MAIS">
                <div className="overflow-x-auto">
                    <Table head={<><th>Jenis</th><th>Tempatan</th><th>Berpusat</th><th>Arah</th><th /></>}>
                        {data.pemetaan.map((p) => (
                            <tr key={p.id} className={p.aktif ? '' : 'opacity-50'}>
                                <td>{p.jenis}</td>
                                <td>{p.nilai_tempatan}</td>
                                <td>{p.nilai_berpusat}</td>
                                <td>{p.arah}</td>
                                <td>{ubah && <Button size="sm" variant="outline" onClick={() => run.mutate(() => tktApi.simpan('pemetaan', { ...p, aktif: !p.aktif }, p.id))}>{p.aktif ? 'Nyahaktif' : 'Aktifkan'}</Button>}</td>
                            </tr>
                        ))}
                    </Table>
                </div>
                {ubah && (
                    <form className="grid grid-cols-1 sm:grid-cols-5 gap-2" onSubmit={(e) => { e.preventDefault(); run.mutate(() => tktApi.simpan('pemetaan', peta)); }}>
                        <Field label="Jenis" htmlFor="m-j"><Select id="m-j" value={peta.jenis} onChange={(v) => setPeta({ ...peta, jenis: v })} options={['kategori', 'keterukan', 'status', 'medan'].map((k) => ({ kod: k, nama: k }))} /></Field>
                        <Field label="Tempatan" htmlFor="m-t"><input id="m-t" required className={inputClass} value={peta.nilai_tempatan} onChange={(e) => setPeta({ ...peta, nilai_tempatan: e.target.value })} /></Field>
                        <Field label="Berpusat" htmlFor="m-b"><input id="m-b" required className={inputClass} value={peta.nilai_berpusat} onChange={(e) => setPeta({ ...peta, nilai_berpusat: e.target.value })} /></Field>
                        <Field label="Arah" htmlFor="m-a"><Select id="m-a" value={peta.arah} onChange={(v) => setPeta({ ...peta, arah: v })} options={[{ kod: 'dua_hala', nama: 'Dua hala' }, { kod: 'masuk', nama: 'Masuk' }, { kod: 'keluar', nama: 'Keluar' }]} /></Field>
                        <div className="flex items-end"><Button type="submit" size="sm">Tambah</Button></div>
                    </form>
                )}
            </Card>

            <Card
                title="Serahan sokongan selepas tempoh jaminan (SRS-TKT-21)"
                actions={can('tkt.serahan.eksport') && <AnchorButton href={PAKEJ_SERAHAN} size="sm" variant="secondary">Muat turun pakej serahan (Excel)</AnchorButton>}
            >
                <p className="text-body-sm text-txt-black-500">Pakej mengandungi sejarah tiket dan kronologi, versi konfigurasi SLA, hierarki eskalasi, pasukan, peraturan penugasan, pautan artikel Pangkalan Pengetahuan, isu terbuka dan pemetaan. Pemindahan tidak memadam sebarang data.</p>
                {ubah && (
                    <form className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2" onSubmit={(e) => { e.preventDefault(); run.mutate(() => tktApi.pindah({ ...pindah, dari_pasukan: Number(pindah.dari_pasukan), ke_pasukan: Number(pindah.ke_pasukan) })); }}>
                        <Field label="Dari pasukan" htmlFor="pd-d"><Select id="pd-d" value={pindah.dari_pasukan} onChange={(v) => setPindah({ ...pindah, dari_pasukan: v })} options={teams} empty="Pilih" required /></Field>
                        <Field label="Ke pasukan" htmlFor="pd-k"><Select id="pd-k" value={pindah.ke_pasukan} onChange={(v) => setPindah({ ...pindah, ke_pasukan: v })} options={teams} empty="Pilih" required /></Field>
                        <Field label="Catatan" htmlFor="pd-c"><input id="pd-c" required minLength={5} className={inputClass} value={pindah.catatan} onChange={(e) => setPindah({ ...pindah, catatan: e.target.value })} /></Field>
                        <div className="flex flex-col justify-end gap-2">
                            <label className="flex items-center gap-1 text-body-xs"><input type="checkbox" checked={pindah.nyahaktif_sumber} onChange={(e) => setPindah({ ...pindah, nyahaktif_sumber: e.target.checked })} /> Nyahaktif pasukan sumber</label>
                            <Button type="submit" size="sm" variant="danger">Pindahkan tiket terbuka</Button>
                        </div>
                    </form>
                )}
            </Card>
        </PageContainer>
    );
}
