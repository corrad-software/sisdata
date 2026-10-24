'use client';

import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Table } from '@/components/ui/Table';
import { idempotencyKey, muatTurun, pjkApi, ralat, tarikh, useCan, useRujukan, type Kontrak } from '@/lib/pjk';
import { Field, inputClass, Kosong, ModalBody, Select, Status } from '../../_components/common';

function useRefresh(id: string) {
    const qc = useQueryClient();
    return () => qc.invalidateQueries({ queryKey: ['pjk', 'kontrak', id] });
}

// ---------------------------------------------------------------- charges (SRS-PJK-12)

export function CajPanel({ k }: { k: Kontrak }) {
    const { data, isLoading } = useQuery({ queryKey: ['pjk', 'kontrak', k.id, 'caj'], queryFn: () => pjkApi.caj(k.id) });
    const [semua, setSemua] = useState(false);
    const rows = (data?.data ?? []).filter((c) => semua || c.dikeluarkan);
    const umur = data?.umur_hutang;

    return (
        <div className="space-y-4">
            {umur && (
                <div className="grid grid-cols-2 sm:grid-cols-5 gap-3 text-body-sm">
                    {Object.entries({ '1_30': '1–30 hari', '31_60': '31–60 hari', '61_90': '61–90 hari', lebih_90: '> 90 hari' }).map(([kod, label]) => (
                        <div key={kod} className="rounded-md border border-otl-gray-200 p-3">
                            <p className="text-body-xs text-txt-black-500">{label}</p>
                            <p className="font-semibold">RM {Number(umur.kumpulan[kod] ?? 0).toLocaleString('en-MY', { minimumFractionDigits: 2 })}</p>
                        </div>
                    ))}
                    <div className="rounded-md border border-otl-gray-200 p-3 bg-bg-washed">
                        <p className="text-body-xs text-txt-black-500">Jumlah tunggakan</p>
                        <p className="font-semibold">RM {Number(umur.jumlah).toLocaleString('en-MY', { minimumFractionDigits: 2 })}</p>
                    </div>
                </div>
            )}
            <label className="inline-flex items-center gap-2 text-body-sm">
                <input type="checkbox" className="rounded border-otl-gray-300" checked={semua} onChange={(e) => setSemua(e.target.checked)} />
                Papar juga caj akan datang (dijadualkan)
            </label>
            <Table head={<><th>Tempoh</th><th className="hidden sm:table-cell">Jenis</th><th className="hidden md:table-cell">Tarikh akhir</th><th>Amaun</th><th className="hidden sm:table-cell">Baki</th><th>Status</th></>}>
                {isLoading ? (
                    <Kosong colSpan={6}>Memuatkan…</Kosong>
                ) : rows.length === 0 ? (
                    <Kosong colSpan={6}>Tiada caj.</Kosong>
                ) : (
                    rows.map((c) => (
                        <tr key={c.id}>
                            <td>
                                {c.tempoh}
                                <p className="text-body-xs text-txt-black-500 sm:hidden">{c.kod_caj_nama}</p>
                            </td>
                            <td className="hidden sm:table-cell">{c.kod_caj_nama}</td>
                            <td className="hidden md:table-cell">
                                {tarikh(c.tarikh_akhir)}
                                {c.umur_tunggakan > 0 && <span className="ml-1 text-body-xs text-txt-danger">({c.umur_tunggakan} hari)</span>}
                            </td>
                            <td className="whitespace-nowrap">{c.amaun.rm}</td>
                            <td className="hidden sm:table-cell whitespace-nowrap">{c.baki.rm}</td>
                            <td>
                                <Status kod={c.status_caj} nama={c.status_caj_nama} />
                            </td>
                        </tr>
                    ))
                )}
            </Table>
        </div>
    );
}

// ---------------------------------------------------------------- ledger (SRS-PJK-11/28)

export function LejarPanel({ k }: { k: Kontrak }) {
    const can = useCan();
    const [dari, setDari] = useState('');
    const [hingga, setHingga] = useState('');
    const [pelarasan, setPelarasan] = useState<{ open: boolean; lejarId?: number }>({ open: false });
    const { data, isLoading } = useQuery({ queryKey: ['pjk', 'kontrak', k.id, 'lejar', dari, hingga], queryFn: () => pjkApi.lejar(k.id, { dari, hingga }) });

    async function penyata(format: 'pdf' | 'xlsx') {
        try {
            await muatTurun(`/pjk/kontrak/${k.id}/penyata`, { format, dari, hingga }, `penyata.${format}`);
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-end gap-3">
                <Field label="Dari" htmlFor="l-dari">
                    <input id="l-dari" type="date" className={inputClass} value={dari} onChange={(e) => setDari(e.target.value)} />
                </Field>
                <Field label="Hingga" htmlFor="l-hingga">
                    <input id="l-hingga" type="date" className={inputClass} value={hingga} onChange={(e) => setHingga(e.target.value)} />
                </Field>
                {can('pjk.lejar.eksport') && (
                    <>
                        <Button size="sm" variant="outline" onClick={() => penyata('pdf')}>
                            Penyata PDF
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => penyata('xlsx')}>
                            Penyata Excel
                        </Button>
                    </>
                )}
                {can('pjk.lejar.kemas_kini') && (
                    <Button size="sm" variant="secondary" onClick={() => setPelarasan({ open: true })}>
                        Mohon pelarasan
                    </Button>
                )}
            </div>
            {data && (
                <p className="text-body-sm text-txt-black-700">
                    Baki awal {data.ringkasan.baki_awal.rm} · Debit {data.ringkasan.jumlah_debit.rm} · Kredit {data.ringkasan.jumlah_kredit.rm} · <strong>Baki akhir {data.ringkasan.baki_akhir.rm}</strong>
                </p>
            )}
            <Table head={<><th>Tarikh</th><th>Keterangan</th><th className="hidden sm:table-cell">Debit</th><th className="hidden sm:table-cell">Kredit</th><th>Baki</th><th className="hidden md:table-cell">Resit</th></>}>
                {isLoading ? (
                    <Kosong colSpan={6}>Memuatkan…</Kosong>
                ) : (data?.data ?? []).length === 0 ? (
                    <Kosong colSpan={6}>Tiada transaksi.</Kosong>
                ) : (
                    data?.data.map((l) => (
                        <tr key={l.id}>
                            <td className="whitespace-nowrap">{tarikh(l.tarikh_transaksi)}</td>
                            <td className="max-w-[12rem] sm:max-w-sm">
                                <p className="font-medium">{l.jenis_nama}</p>
                                <p className="text-body-xs text-txt-black-500 break-words">{l.keterangan}</p>
                                <p className="text-body-xs sm:hidden">{l.debit.nilai !== '0.00' ? `+${l.debit.rm}` : `−${l.kredit.rm}`}</p>
                                {can('pjk.lejar.kemas_kini') && l.jenis === 'bayaran' && (
                                    <button type="button" className="text-body-xs text-txt-danger hover:underline" onClick={() => setPelarasan({ open: true, lejarId: l.id })}>
                                        Batalkan bayaran
                                    </button>
                                )}
                            </td>
                            <td className="hidden sm:table-cell whitespace-nowrap">{l.debit.nilai !== '0.00' ? l.debit.rm : ''}</td>
                            <td className="hidden sm:table-cell whitespace-nowrap">{l.kredit.nilai !== '0.00' ? l.kredit.rm : ''}</td>
                            <td className="whitespace-nowrap font-medium">{l.baki_berjalan.rm}</td>
                            <td className="hidden md:table-cell text-body-xs">{l.no_resit ?? ''}</td>
                        </tr>
                    ))
                )}
            </Table>
            {(data?.pelarasan ?? []).length > 0 && (
                <div>
                    <h4 className="font-semibold text-body-md mb-2">Permohonan pelarasan</h4>
                    <ul className="space-y-1 text-body-sm">
                        {data?.pelarasan.map((p) => (
                            <li key={p.id} className="flex flex-wrap items-center gap-2">
                                <Status kod={p.status} nama={p.status} /> {p.jenis_nama} {p.amaun.rm} — {p.justifikasi}
                                {p.ulasan && <span className="text-txt-black-500">({p.ulasan})</span>}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
            <PelarasanModal k={k} state={pelarasan} onClose={() => setPelarasan({ open: false })} />
        </div>
    );
}

function PelarasanModal({ k, state, onClose }: { k: Kontrak; state: { open: boolean; lejarId?: number }; onClose: () => void }) {
    const { data: ref } = useRujukan();
    const refresh = useRefresh(k.id);
    const [f, setF] = useState<Record<string, string>>({ jenis: 'pelarasan_kredit' });
    const [busy, setBusy] = useState(false);
    const jenis = state.lejarId ? 'pembatalan' : f.jenis;

    async function submit() {
        setBusy(true);
        try {
            const r = await pjkApi.mohonPelarasan(k.id, { ...f, jenis, lejar_id_sasaran: state.lejarId });
            toast.success(r.message ?? 'Dihantar.');
            refresh();
            onClose();
        } catch (e) {
            toast.error(ralat(e));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Modal open={state.open} onClose={onClose}>
            <ModalBody
                title={state.lejarId ? 'Pembatalan bayaran / resit' : 'Mohon pelarasan lejar'}
                footer={
                    <>
                        <Button variant="secondary" onClick={onClose}>
                            Batal
                        </Button>
                        <Button disabled={busy} onClick={submit}>
                            Hantar untuk kelulusan pegawai kedua
                        </Button>
                    </>
                }
            >
                <p className="text-body-sm text-txt-black-500">Pelarasan berkuat kuasa hanya selepas diluluskan oleh pegawai lain melalui Modul Workflow (SRS-PJK-28). Pembatalan menjana resit kredit.</p>
                {!state.lejarId && (
                    <>
                        <Field label="Jenis" htmlFor="p-jenis">
                            <Select id="p-jenis" value={f.jenis} onChange={(v) => setF((p) => ({ ...p, jenis: v }))} options={(ref?.jenis_pelarasan ?? []).filter((o) => o.kod !== 'pembatalan')} />
                        </Field>
                        <Field label="Amaun (RM)" htmlFor="p-amaun">
                            <input id="p-amaun" inputMode="decimal" className={inputClass} value={f.amaun ?? ''} onChange={(e) => setF((p) => ({ ...p, amaun: e.target.value }))} />
                        </Field>
                        {jenis === 'pengecualian' && (
                            <Field label="ID caj dikecualikan" htmlFor="p-caj" help="Lihat tab Jadual caj.">
                                <input id="p-caj" inputMode="numeric" className={inputClass} value={f.caj_id ?? ''} onChange={(e) => setF((p) => ({ ...p, caj_id: e.target.value }))} />
                            </Field>
                        )}
                    </>
                )}
                <Field label="Justifikasi bertulis" htmlFor="p-just">
                    <textarea id="p-just" rows={4} className={inputClass} value={f.justifikasi ?? ''} onChange={(e) => setF((p) => ({ ...p, justifikasi: e.target.value }))} />
                </Field>
            </ModalBody>
        </Modal>
    );
}

// ---------------------------------------------------------------- payments (SRS-PJK-14..20)

export function BayaranPanel({ k }: { k: Kontrak }) {
    const can = useCan();
    const { data: ref } = useRujukan();
    const refresh = useRefresh(k.id);
    const { data, isLoading } = useQuery({ queryKey: ['pjk', 'kontrak', k.id, 'bayaran'], queryFn: () => pjkApi.bayaranKontrak(k.id) });
    const [offline, setOffline] = useState(false);
    const [online, setOnline] = useState(false);
    const [f, setF] = useState<Record<string, string>>({ saluran: 'kaunter', tarikh_bayaran: new Date().toISOString().slice(0, 10) });
    const [bukti, setBukti] = useState<File | null>(null);
    const [o, setO] = useState<Record<string, string>>({ saluran: 'fpx' });
    const [busy, setBusy] = useState(false);

    async function rekod() {
        setBusy(true);
        try {
            const fd = new FormData();
            Object.entries(f).forEach(([key, v]) => v && fd.append(key, v));
            if (bukti) fd.append('bukti', bukti);
            const r = await pjkApi.rekodBayaran(k.id, fd);
            toast.success(r.message ?? 'Direkod.');
            setOffline(false);
            refresh();
        } catch (e) {
            toast.error(ralat(e));
        } finally {
            setBusy(false);
        }
    }

    async function bayar() {
        setBusy(true);
        try {
            const r = await pjkApi.bayarDalamTalian(k.id, o, idempotencyKey());
            if (r.data.url_bayaran) window.location.assign(r.data.url_bayaran);
        } catch (e) {
            toast.error(ralat(e));
        } finally {
            setBusy(false);
        }
    }

    async function resit(id: number) {
        try {
            await muatTurun(`/pjk/resit/${id}`, {}, 'resit.pdf');
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    return (
        <div className="space-y-4">
            {can('pjk.bayaran.cipta') && (
                <div className="flex flex-wrap gap-2">
                    <Button size="sm" onClick={() => setOnline(true)}>
                        Bayar dalam talian (FPX / kad)
                    </Button>
                    <Button size="sm" variant="secondary" onClick={() => setOffline(true)}>
                        Rekod bayaran kaunter / EFT / cek
                    </Button>
                </div>
            )}
            <Table head={<><th>Bayaran</th><th className="hidden sm:table-cell">Kaedah</th><th>Amaun</th><th>Status</th><th className="hidden md:table-cell">Resit</th></>}>
                {isLoading ? (
                    <Kosong colSpan={5}>Memuatkan…</Kosong>
                ) : (data?.data ?? []).length === 0 ? (
                    <Kosong colSpan={5}>Tiada bayaran.</Kosong>
                ) : (
                    data?.data.map((b) => (
                        <tr key={b.id}>
                            <td>
                                {b.no_bayaran}
                                <p className="text-body-xs text-txt-black-500">{tarikh(b.tarikh_bayaran)} {b.rujukan_luar ? `· ${b.rujukan_luar}` : ''}</p>
                            </td>
                            <td className="hidden sm:table-cell">{b.saluran_nama}</td>
                            <td className="whitespace-nowrap">{b.amaun.rm}</td>
                            <td>
                                <Status kod={b.status} nama={b.status_nama} />
                            </td>
                            <td className="hidden md:table-cell">
                                {(b.resit ?? []).map((r) => (
                                    <button key={r.id} type="button" className="block text-body-xs text-txt-primary hover:underline" onClick={() => resit(r.id)}>
                                        {r.no_resit}
                                    </button>
                                ))}
                            </td>
                        </tr>
                    ))
                )}
            </Table>
            {(data?.gateway ?? []).length > 0 && (
                <div>
                    <h4 className="font-semibold text-body-md mb-2">Transaksi dalam talian</h4>
                    <Table head={<><th>Rujukan</th><th className="hidden sm:table-cell">Saluran</th><th>Amaun</th><th>Status</th></>}>
                        {data?.gateway.map((t) => (
                            <tr key={t.id}>
                                <td className="text-body-xs">
                                    {t.no_rujukan}
                                    {t.disahkan_melalui && <p className="text-txt-black-500">disahkan: {t.disahkan_melalui.replace('_', ' ')}</p>}
                                </td>
                                <td className="hidden sm:table-cell">{t.saluran_nama}</td>
                                <td className="whitespace-nowrap">{t.amaun.rm}</td>
                                <td>
                                    <Status kod={t.status} nama={t.status_nama} />
                                    {t.url_bayaran && (
                                        <a href={t.url_bayaran} className="block text-body-xs text-txt-primary hover:underline">
                                            Teruskan bayaran
                                        </a>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </Table>
                </div>
            )}

            <Modal open={offline} onClose={() => setOffline(false)}>
                <ModalBody
                    title="Rekod bayaran luar talian"
                    footer={
                        <>
                            <Button variant="secondary" onClick={() => setOffline(false)}>
                                Batal
                            </Button>
                            <Button disabled={busy} onClick={rekod}>
                                Rekod
                            </Button>
                        </>
                    }
                >
                    <p className="text-body-sm text-txt-black-500">Lejar dikreditkan selepas Bahagian Kewangan mengesahkan bayaran (SRS-PJK-19).</p>
                    <Field label="Kaedah" htmlFor="o-saluran">
                        <Select id="o-saluran" value={f.saluran} onChange={(v) => setF((p) => ({ ...p, saluran: v }))} options={ref?.saluran_luar_talian ?? []} />
                    </Field>
                    <Field label="Amaun (RM)" htmlFor="o-amaun">
                        <input id="o-amaun" inputMode="decimal" className={inputClass} value={f.amaun ?? ''} onChange={(e) => setF((p) => ({ ...p, amaun: e.target.value }))} />
                    </Field>
                    <Field label="Tarikh bayaran" htmlFor="o-tarikh">
                        <input id="o-tarikh" type="date" className={inputClass} value={f.tarikh_bayaran} onChange={(e) => setF((p) => ({ ...p, tarikh_bayaran: e.target.value }))} />
                    </Field>
                    <Field label="Rujukan (no. cek / rujukan bank)" htmlFor="o-ruj">
                        <input id="o-ruj" className={inputClass} value={f.rujukan_luar ?? ''} onChange={(e) => setF((p) => ({ ...p, rujukan_luar: e.target.value }))} />
                    </Field>
                    <Field label="E-mel pembayar (untuk resit)" htmlFor="o-emel">
                        <input id="o-emel" type="email" className={inputClass} value={f.emel_pembayar ?? ''} onChange={(e) => setF((p) => ({ ...p, emel_pembayar: e.target.value }))} />
                    </Field>
                    <Field label="Bukti bayaran" htmlFor="o-bukti">
                        <input id="o-bukti" type="file" accept="application/pdf,image/*" className="block w-full text-body-sm" onChange={(e) => setBukti(e.target.files?.[0] ?? null)} />
                    </Field>
                </ModalBody>
            </Modal>

            <Modal open={online} onClose={() => setOnline(false)}>
                <ModalBody
                    title="Bayaran dalam talian"
                    footer={
                        <>
                            <Button variant="secondary" onClick={() => setOnline(false)}>
                                Batal
                            </Button>
                            <Button disabled={busy} onClick={bayar}>
                                Teruskan ke payment gateway
                            </Button>
                        </>
                    }
                >
                    <p className="text-body-sm text-txt-black-500">Maklumat kad dimasukkan di laman payment gateway sahaja — tidak disimpan dalam SISDATA. Kosongkan amaun untuk membayar keseluruhan baki ({k.baki_lejar?.rm}).</p>
                    <Field label="Saluran" htmlFor="g-saluran">
                        <Select id="g-saluran" value={o.saluran} onChange={(v) => setO((p) => ({ ...p, saluran: v }))} options={[{ kod: 'fpx', nama: 'FPX (perbankan internet)' }, { kod: 'kad', nama: 'Kad kredit / debit' }]} />
                    </Field>
                    <Field label="Amaun (RM)" htmlFor="g-amaun">
                        <input id="g-amaun" inputMode="decimal" className={inputClass} value={o.amaun ?? ''} onChange={(e) => setO((p) => ({ ...p, amaun: e.target.value }))} />
                    </Field>
                    <Field label="E-mel pembayar" htmlFor="g-emel">
                        <input id="g-emel" type="email" className={inputClass} value={o.emel_pembayar ?? ''} onChange={(e) => setO((p) => ({ ...p, emel_pembayar: e.target.value }))} />
                    </Field>
                </ModalBody>
            </Modal>
        </div>
    );
}

// ---------------------------------------------------------------- deposit (SRS-PJK-13)

export function DepositPanel({ k }: { k: Kontrak }) {
    const can = useCan();
    const refresh = useRefresh(k.id);
    const { data, isLoading } = useQuery({ queryKey: ['pjk', 'kontrak', k.id, 'deposit'], queryFn: () => pjkApi.deposit(k.id) });
    const [open, setOpen] = useState<number | null>(null);
    const [f, setF] = useState<Record<string, string>>({ amaun_tolakan: '0' });

    async function submit() {
        if (open === null) return;
        try {
            const r = await pjkApi.penyelarasanDeposit(open, f);
            toast.success(r.message ?? 'Dihantar.');
            setOpen(null);
            refresh();
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    return (
        <div className="space-y-3">
            <Table head={<><th>Jenis</th><th>Amaun</th><th>Status</th><th className="hidden sm:table-cell">Tolakan / pulangan</th><th /></>}>
                {isLoading ? (
                    <Kosong colSpan={5}>Memuatkan…</Kosong>
                ) : (data ?? []).length === 0 ? (
                    <Kosong colSpan={5}>Tiada deposit.</Kosong>
                ) : (
                    data?.map((d) => (
                        <tr key={d.id}>
                            <td>
                                {d.jenis.replace('_', ' ')}
                                <p className="text-body-xs text-txt-black-500">Diterima {tarikh(d.tarikh_terima)}</p>
                            </td>
                            <td className="whitespace-nowrap">{d.amaun.rm}</td>
                            <td>
                                <Status kod={d.status} nama={d.status_nama} />
                            </td>
                            <td className="hidden sm:table-cell text-body-xs">
                                {d.amaun_tolakan.rm} / {d.amaun_dipulangkan.rm} {d.tarikh_pulangan ? `(${tarikh(d.tarikh_pulangan)})` : ''}
                            </td>
                            <td>
                                {can('pjk.deposit.kemas_kini') && d.status === 'dipegang' && ['ditamatkan', 'tamat_tempoh'].includes(k.status) && (
                                    <Button size="sm" variant="outline" onClick={() => setOpen(d.id)}>
                                        Selaraskan
                                    </Button>
                                )}
                            </td>
                        </tr>
                    ))
                )}
            </Table>
            <Modal open={open !== null} onClose={() => setOpen(null)}>
                <ModalBody
                    title="Penyelarasan deposit"
                    footer={
                        <>
                            <Button variant="secondary" onClick={() => setOpen(null)}>
                                Batal
                            </Button>
                            <Button onClick={submit}>Hantar kepada Bahagian Kewangan</Button>
                        </>
                    }
                >
                    <Field label="Amaun tolakan (RM)" htmlFor="d-tolakan" help="Tolakan digunakan untuk menjelaskan tunggakan; baki dipulangkan kepada penyewa.">
                        <input id="d-tolakan" inputMode="decimal" className={inputClass} value={f.amaun_tolakan} onChange={(e) => setF((p) => ({ ...p, amaun_tolakan: e.target.value }))} />
                    </Field>
                    <Field label="Justifikasi tolakan" htmlFor="d-just">
                        <textarea id="d-just" rows={3} className={inputClass} value={f.justifikasi_tolakan ?? ''} onChange={(e) => setF((p) => ({ ...p, justifikasi_tolakan: e.target.value }))} />
                    </Field>
                </ModalBody>
            </Modal>
        </div>
    );
}

// ---------------------------------------------------------------- amendments, versions, letters (SRS-PJK-05/07/09/10)

export function PindaanPanel({ k }: { k: Kontrak }) {
    const can = useCan();
    const { data: ref } = useRujukan();
    const refresh = useRefresh(k.id);
    const { data } = useQuery({ queryKey: ['pjk', 'kontrak', k.id, 'pindaan'], queryFn: () => pjkApi.pindaan(k.id) });
    const [open, setOpen] = useState(false);
    const [jenis, setJenis] = useState('pembaharuan');
    const [d, setD] = useState<Record<string, string>>({});
    const [just, setJust] = useState('');
    const [doc, setDoc] = useState<File | null>(null);
    const [busy, setBusy] = useState(false);
    const { data: review } = useQuery({ queryKey: ['pjk', 'kontrak', k.id, 'semakan'], queryFn: () => pjkApi.semakanKadar(k.id), enabled: open && (jenis === 'pembaharuan' || jenis === 'semakan_kadar') });

    async function submit() {
        setBusy(true);
        try {
            const fd = new FormData();
            fd.append('jenis_pindaan', jenis);
            fd.append('justifikasi', just);
            fd.append('data', JSON.stringify(d));
            if (doc) fd.append('dokumen', doc);
            const r = await pjkApi.mohonPindaan(k.id, fd);
            toast.success(r.message ?? 'Dihantar.');
            setOpen(false);
            refresh();
        } catch (e) {
            toast.error(ralat(e));
        } finally {
            setBusy(false);
        }
    }

    const field = (key: string, label: string, type = 'text') => (
        <Field key={key} label={label} htmlFor={`pd-${key}`}>
            {type === 'textarea' ? (
                <textarea id={`pd-${key}`} rows={3} className={inputClass} value={d[key] ?? ''} onChange={(e) => setD((p) => ({ ...p, [key]: e.target.value }))} />
            ) : (
                <input id={`pd-${key}`} type={type} className={inputClass} value={d[key] ?? ''} onChange={(e) => setD((p) => ({ ...p, [key]: e.target.value }))} />
            )}
        </Field>
    );

    return (
        <div className="space-y-5">
            {can('pjk.kontrak.kemas_kini') && ['aktif', 'hampir_tamat', 'tamat_tempoh'].includes(k.status) && !k.pindaan_menunggu && (
                <Button size="sm" onClick={() => setOpen(true)}>
                    Mohon pembaharuan / pindaan / penamatan
                </Button>
            )}
            {k.pindaan_menunggu && <p className="text-body-sm text-txt-warning">Ada permohonan pindaan sedang menunggu kelulusan.</p>}

            <section>
                <h4 className="font-semibold text-body-md mb-2">Permohonan pindaan</h4>
                {(data?.data ?? []).length === 0 ? (
                    <Kosong>Tiada permohonan.</Kosong>
                ) : (
                    <ul className="space-y-2 text-body-sm">
                        {data?.data.map((p) => (
                            <li key={p.id} className="rounded-md border border-otl-gray-200 p-3">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Status kod={p.status} nama={p.status} />
                                    <strong>{p.jenis_pindaan_nama}</strong>
                                    <span className="text-txt-black-500">{tarikh(p.dicipta)}</span>
                                </div>
                                <p className="mt-1 break-words">{p.justifikasi}</p>
                                <p className="text-body-xs text-txt-black-500 break-words">{JSON.stringify(p.data_cadangan)}</p>
                                {p.id_kontrak_baharu && (
                                    <a href={`/admin/pjk/kontrak/${p.id_kontrak_baharu}`} className="text-body-xs text-txt-primary hover:underline">
                                        Perjanjian baharu
                                    </a>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section>
                <h4 className="font-semibold text-body-md mb-2">Versi perjanjian</h4>
                <Table head={<><th>Versi</th><th>Jenis</th><th className="hidden sm:table-cell">Tarikh</th><th className="hidden md:table-cell">Justifikasi</th></>}>
                    {(data?.versi ?? []).length === 0 ? (
                        <Kosong colSpan={4}>Belum diaktifkan.</Kosong>
                    ) : (
                        data?.versi.map((v) => (
                            <tr key={v.id_versi}>
                                <td>v{v.no_versi}</td>
                                <td>{v.jenis_pindaan_nama}</td>
                                <td className="hidden sm:table-cell">{tarikh(v.tarikh_pindaan)}</td>
                                <td className="hidden md:table-cell max-w-xs break-words">{v.justifikasi ?? '—'}</td>
                            </tr>
                        ))
                    )}
                </Table>
            </section>

            <section>
                <h4 className="font-semibold text-body-md mb-2">Sejarah status</h4>
                <ul className="space-y-1 text-body-sm">
                    {data?.sejarah_status.map((s, i) => (
                        <li key={i}>
                            <span className="text-txt-black-500">{new Date(s.masa).toLocaleString('ms-MY')}</span> — <strong>{s.status_nama}</strong> {s.pegawai ? `oleh ${s.pegawai}` : '(sistem)'} {s.catatan ? `· ${s.catatan}` : ''}
                        </li>
                    ))}
                </ul>
            </section>

            <SuratSection k={k} surat={data?.surat ?? []} />

            <Modal open={open} onClose={() => setOpen(false)}>
                <ModalBody
                    title="Permohonan pindaan perjanjian"
                    footer={
                        <>
                            <Button variant="secondary" onClick={() => setOpen(false)}>
                                Batal
                            </Button>
                            <Button disabled={busy} onClick={submit}>
                                Hantar untuk kelulusan
                            </Button>
                        </>
                    }
                >
                    <Field label="Jenis pindaan" htmlFor="pd-jenis">
                        <Select
                            id="pd-jenis"
                            value={jenis}
                            onChange={(v) => {
                                setJenis(v);
                                setD({});
                            }}
                            options={ref?.jenis_pindaan ?? []}
                        />
                    </Field>
                    {jenis === 'pembaharuan' && [field('tarikh_tamat_baharu', 'Tarikh tamat baharu', 'date'), field('kadar_baharu', 'Kadar baharu sebulan (RM)'), field('terma', 'Terma (jika berubah)', 'textarea')]}
                    {jenis === 'semakan_kadar' && [field('kadar_baharu', 'Kadar baharu sebulan (RM)'), field('tarikh_berkuat_kuasa', 'Tarikh berkuat kuasa', 'date')]}
                    {jenis === 'pindaan_terma' && [field('terma', 'Terma baharu', 'textarea'), field('hari_tempoh_bayar', 'Tempoh bayar (hari)', 'number')]}
                    {jenis === 'penamatan' && [field('tarikh_penamatan', 'Tarikh penamatan', 'date'), field('sebab_penamatan', 'Sebab penamatan', 'textarea')]}
                    {review && (
                        <div className="rounded-md bg-bg-washed p-3 text-body-xs space-y-0.5">
                            <p className="font-semibold">Semakan kadar (SRS-PJK-07)</p>
                            <p>Kadar semasa: RM {String(review.kadar_semasa)} · per m²: {String(review.kadar_per_m2 ?? '—')}</p>
                            <p>
                                Median setara ({String(review.bil_perbandingan)} perjanjian): {String(review.median_kadar_setara_per_m2 ?? '—')} / m² → cadangan RM {String(review.kadar_cadangan_setara ?? '—')}
                            </p>
                            {review.penilaian_terhad ? (
                                <p>Nilai penilaian (Rahsia) tidak dipaparkan untuk peranan anda.</p>
                            ) : (
                                <p>
                                    Nilai pasaran: RM {String(review.nilai_pasaran ?? '—')} · hasil tahunan {String(review.hasil_tahunan_peratus ?? '—')}% · cadangan RM {String(review.kadar_cadangan_pasaran ?? '—')}
                                </p>
                            )}
                        </div>
                    )}
                    <Field label="Justifikasi" htmlFor="pd-just">
                        <textarea id="pd-just" rows={3} className={inputClass} value={just} onChange={(e) => setJust(e.target.value)} />
                    </Field>
                    <Field label="Dokumen perjanjian / pindaan (selepas)" htmlFor="pd-doc">
                        <input id="pd-doc" type="file" accept="application/pdf" className="block w-full text-body-sm" onChange={(e) => setDoc(e.target.files?.[0] ?? null)} />
                    </Field>
                </ModalBody>
            </Modal>
        </div>
    );
}

function SuratSection({ k, surat }: { k: Kontrak; surat: Array<{ id: number; no_rujukan_surat: string; tajuk: string; versi_templat: number; masa: string }> }) {
    const can = useCan();
    const refresh = useRefresh(k.id);
    const { data } = useQuery({ queryKey: ['pjk', 'templat'], queryFn: () => pjkApi.templat(), enabled: can('pjk.surat.cipta') });
    const [templat, setTemplat] = useState('');
    const [preview, setPreview] = useState<string | null>(null);

    async function pratonton() {
        if (!templat) return;
        try {
            setPreview((await pjkApi.pratontonSurat(k.id, Number(templat))).kandungan);
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    async function jana() {
        try {
            const r = await pjkApi.janaSurat(k.id, Number(templat));
            toast.success(r.message ?? 'Dijana.');
            setPreview(r.data.kandungan);
            refresh();
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    return (
        <section className="space-y-2">
            <h4 className="font-semibold text-body-md">Surat</h4>
            {can('pjk.surat.cipta') && (
                <div className="flex flex-wrap items-end gap-2">
                    <Field label="Templat" htmlFor="s-templat">
                        <Select id="s-templat" value={templat} onChange={setTemplat} options={(data?.data ?? []).map((t) => ({ kod: String(t.id), nama: `${t.tajuk} (v${t.versi})` }))} empty="— Pilih templat —" />
                    </Field>
                    <Button size="sm" variant="outline" disabled={!templat} onClick={pratonton}>
                        Pratonton
                    </Button>
                    <Button size="sm" disabled={!templat} onClick={jana}>
                        Jana surat
                    </Button>
                </div>
            )}
            {preview && <pre className="whitespace-pre-wrap rounded-md border border-otl-gray-200 bg-bg-washed p-3 text-body-xs">{preview}</pre>}
            <ul className="text-body-sm space-y-1">
                {surat.map((s) => (
                    <li key={s.id}>
                        {s.no_rujukan_surat} — {s.tajuk} (templat v{s.versi_templat}) · {tarikh(s.masa)}
                    </li>
                ))}
            </ul>
        </section>
    );
}
