'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Tabs, TabPanel } from '@/components/ui/Tabs';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { Modal } from '@/components/ui/Modal';
import { InputLabel } from '@/components/ui/InputLabel';
import { Spinner } from '@/components/ui/Spinner';
import { Table } from '@/components/ui/Table';
import { DocumentAttachments } from '@/components/dms/DocumentAttachments';
import { ApiError } from '@/lib/api';
import { BAHAGIAN_LABEL, perApi, ringgit, tarikh, tarikhMasa, type KesPenuh } from '@/lib/per';
import { BorangSaluran } from '../../_components/BorangSaluran';
import { PihakForm } from '../../_components/PihakForm';
import { SenaraiSemak } from '../../_components/SenaraiSemak';
import { cardClass, ErrorList, inputClass, Row, SaluranChip, SectionTitle, StatusBadge } from '../../_components/common';

const TABS = {
    ringkasan: 'Ringkasan',
    butiran: 'Butiran saluran',
    pihak: 'Pihak',
    dokumen: 'Dokumen & semak',
    peringkat: 'Peringkat & garis masa',
    kelulusan: 'Kelulusan',
};

function errorText(e: unknown): string {
    if (e instanceof ApiError) {
        const list = Object.values(e.errors ?? {}).flat();
        return list.length > 0 && list[0] !== e.message ? `${e.message} ${list.join('; ')}` : e.message;
    }
    return e instanceof Error ? e.message : 'Tindakan gagal.';
}

export default function KesPage() {
    const { id } = useParams<{ id: string }>();
    const queryClient = useQueryClient();
    const { data: kes, isLoading, error } = useQuery({ queryKey: ['per', 'kes', id], queryFn: () => perApi.kes(id).then((r) => r.data) });
    const refresh = () => queryClient.invalidateQueries({ queryKey: ['per', 'kes', id] });

    if (isLoading) {
        return (
            <PageContainer>
                <Spinner />
            </PageContainer>
        );
    }
    if (error || !kes) {
        return (
            <PageContainer>
                <Alert type="error">{error instanceof Error ? error.message : 'Kes tidak ditemui atau di luar skop capaian anda.'}</Alert>
            </PageContainer>
        );
    }

    return (
        <PageContainer>
            <Header kes={kes} onChanged={refresh} />
            <div className={cardClass}>
                <Tabs tabs={TABS}>
                    <TabPanel value="ringkasan">
                        <Ringkasan kes={kes} onChanged={refresh} />
                    </TabPanel>
                    <TabPanel value="butiran">
                        <Butiran key={kes.id + kes.status} kes={kes} onChanged={refresh} />
                    </TabPanel>
                    <TabPanel value="pihak">
                        <PihakTab kes={kes} onChanged={refresh} />
                    </TabPanel>
                    <TabPanel value="dokumen">
                        <div className="space-y-6">
                            <SenaraiSemak kes={kes} onChanged={refresh} />
                            <DocumentAttachments ownerType="pemerolehan" ownerId={kes.id} sourceModule="per" ownerLink={`/admin/per/kes/${kes.id}`} title="Semua dokumen kes dalam DMS" readOnly />
                        </div>
                    </TabPanel>
                    <TabPanel value="peringkat">
                        <PeringkatTab kes={kes} />
                    </TabPanel>
                    <TabPanel value="kelulusan">
                        <KelulusanTab kes={kes} />
                    </TabPanel>
                </Tabs>
            </div>
        </PageContainer>
    );
}

// ------------------------------------------------------------------ header + actions

function Header({ kes, onChanged }: { kes: KesPenuh; onChanged: () => void }) {
    const [busy, setBusy] = useState(false);
    const [closeOpen, setCloseOpen] = useState(false);
    const stage = kes.peringkat_semasa;
    const open = kes.status !== 'selesai' && kes.status !== 'dibatalkan';

    async function run(fn: () => Promise<{ data: unknown; message?: string }>, ok?: string) {
        setBusy(true);
        try {
            const res = await fn();
            toast.success(res.message ?? ok ?? 'Berjaya.');
            onChanged();
        } catch (e) {
            toast.error(errorText(e));
        } finally {
            setBusy(false);
        }
    }

    return (
        <div className={`${cardClass} space-y-4`}>
            <div className="flex flex-col lg:flex-row lg:items-start gap-4 lg:justify-between">
                <div className="min-w-0 space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <SaluranChip kod={kes.saluran.kod} nama={kes.saluran.nama} warna={kes.saluran.warna} ikon={kes.saluran.ikon} />
                        <StatusBadge status={kes.status} />
                        <Badge variant="warning">Sulit</Badge>
                        {kes.sumber_rekod === 'sintetik' && <Badge variant="neutral">Data sintetik</Badge>}
                    </div>
                    <h3 className="font-heading font-semibold text-heading-3xs text-txt-black-900 break-words">
                        {kes.no_kes} — {kes.tajuk}
                    </h3>
                    <p className="text-body-sm text-txt-black-500">
                        Peringkat semasa: <strong className="text-txt-black-900">{stage?.nama ?? '—'}</strong>
                        {stage && (
                            <span className={stage.lewat ? 'text-txt-danger' : ''}>
                                {' '}
                                · {stage.hari ?? 0} / {stage.tempoh_sasaran_hari} hari{stage.dieskalasi ? ' · dieskalasi' : ''}
                            </span>
                        )}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {kes.status === 'permohonan_awam_menunggu_semakan' && kes.boleh.kemas_kini && (
                        <>
                            <Button disabled={busy} onClick={() => run(() => perApi.semakanAwam(kes.id, true), 'Permohonan diterima untuk diproses.')}>
                                Terima permohonan
                            </Button>
                            <Button
                                variant="danger"
                                disabled={busy}
                                onClick={() => {
                                    const c = window.prompt('Sebab permohonan ditolak:');
                                    if (c) run(() => perApi.semakanAwam(kes.id, false, c), 'Permohonan ditolak.');
                                }}
                            >
                                Tolak
                            </Button>
                        </>
                    )}
                    {open && kes.boleh.kemas_kini && stage && stage.jenis !== 'simpanan' && kes.status !== 'menunggu_kelulusan' && kes.status !== 'permohonan_awam_menunggu_semakan' && (
                        <Button disabled={busy || kes.sekatan.length > 0} onClick={() => run(() => perApi.maju(kes.id, {}))}>
                            {stage.jenis === 'kelulusan' ? 'Hantar semula untuk kelulusan' : 'Majukan ke peringkat seterusnya'}
                        </Button>
                    )}
                    {open && stage?.jenis === 'simpanan' && kes.boleh.tutup && (
                        <Button disabled={busy || kes.sekatan.length > 0} onClick={() => setCloseOpen(true)}>
                            Tutup kes & daftar hak milik
                        </Button>
                    )}
                    {open && !kes.lot.berdaftar && kes.boleh.kemas_kini && kes.lot.no_lot && (
                        <Button variant="secondary" disabled={busy} onClick={() => run(() => perApi.pautkanLot(kes.id))}>
                            Semak pendaftaran lot
                        </Button>
                    )}
                    {open && kes.boleh.batal && (
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() => {
                                const s = window.prompt('Sebab pembatalan kes (wajib):');
                                if (s && s.trim().length >= 5) run(() => perApi.batal(kes.id, s), 'Kes dibatalkan.');
                            }}
                        >
                            Batal kes
                        </Button>
                    )}
                </div>
            </div>

            {kes.status === 'menunggu_kelulusan' && (
                <Alert type="info">
                    Kes sedang dalam aliran kelulusan berlapis (Workflow Engine).{' '}
                    {kes.id_aliran_kerja && (
                        <Link href={`/admin/wfe/instans/${kes.id_aliran_kerja}`} className="underline">
                            Lihat aliran
                        </Link>
                    )}
                </Alert>
            )}
            {kes.status === 'ditolak' && <Alert type="error">Kelulusan ditolak. Betulkan kes dan hantar semula untuk kelulusan.</Alert>}
            {open && kes.sekatan.length > 0 && (
                <Alert type="warning">
                    <p className="font-medium">Kes belum boleh dimajukan:</p>
                    <ErrorList items={kes.sekatan} />
                </Alert>
            )}

            <TutupModal kes={kes} open={closeOpen} onClose={() => setCloseOpen(false)} onDone={onChanged} />
        </div>
    );
}

function TutupModal({ kes, open, onClose, onDone }: { kes: KesPenuh; open: boolean; onClose: () => void; onDone: () => void }) {
    const [hm, setHm] = useState({ no_hak_milik: '', jenis_hak_milik: 'geran', tarikh_pendaftaran: new Date().toISOString().slice(0, 10), pejabat_pendaftaran: '' });
    const [pb, setPb] = useState({ kod_peti_besi: '', no_fail: '', lokasi_rak: '', no_kotak_pengikat: '', jenis_dokumen_asal: 'geran_asal' });
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function submit() {
        setBusy(true);
        setError(null);
        try {
            const res = await perApi.tutup(kes.id, { hak_milik: hm, peti_besi: pb });
            toast.success(res.message);
            onDone();
            onClose();
        } catch (e) {
            setError(errorText(e));
        } finally {
            setBusy(false);
        }
    }

    const field = (id: string, label: string, value: string, set: (v: string) => void, type = 'text') => (
        <div>
            <InputLabel htmlFor={id} className="mb-1.5">
                {label}
            </InputLabel>
            <input id={id} type={type} className={inputClass} value={value} onChange={(e) => set(e.target.value)} />
        </div>
    );

    return (
        <Modal open={open} onClose={onClose}>
            <div className="p-4 sm:p-6 space-y-4">
                <h3 className="font-heading font-semibold text-body-lg text-txt-black-900">Tutup kes {kes.no_kes}</h3>
                <p className="text-body-sm text-txt-black-500">Rekod hak milik dicipta dalam Modul Hak Milik Tanah dan dokumen asal didaftarkan dalam daftar peti besi.</p>
                {error && <Alert type="error">{error}</Alert>}
                <fieldset className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <legend className="font-medium text-body-sm mb-2">Hak milik</legend>
                    {field('hm-no', 'No. hak milik', hm.no_hak_milik, (v) => setHm({ ...hm, no_hak_milik: v }))}
                    <div>
                        <InputLabel htmlFor="hm-jenis" className="mb-1.5">
                            Jenis hak milik
                        </InputLabel>
                        <select id="hm-jenis" className={inputClass} value={hm.jenis_hak_milik} onChange={(e) => setHm({ ...hm, jenis_hak_milik: e.target.value })}>
                            <option value="geran">Geran</option>
                            <option value="pajakan_negeri">Pajakan Negeri</option>
                            <option value="hsd">HSD</option>
                            <option value="hsm">HSM</option>
                            <option value="pm">Pajakan Mukim</option>
                        </select>
                    </div>
                    {field('hm-tarikh', 'Tarikh pendaftaran', hm.tarikh_pendaftaran, (v) => setHm({ ...hm, tarikh_pendaftaran: v }), 'date')}
                    {field('hm-pejabat', 'Pejabat pendaftaran', hm.pejabat_pendaftaran, (v) => setHm({ ...hm, pejabat_pendaftaran: v }))}
                </fieldset>
                <fieldset className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <legend className="font-medium text-body-sm mb-2">Daftar peti besi</legend>
                    {field('pb-kod', 'Kod peti besi', pb.kod_peti_besi, (v) => setPb({ ...pb, kod_peti_besi: v }))}
                    {field('pb-fail', 'No. fail', pb.no_fail, (v) => setPb({ ...pb, no_fail: v }))}
                    {field('pb-rak', 'Lokasi rak', pb.lokasi_rak, (v) => setPb({ ...pb, lokasi_rak: v }))}
                    {field('pb-kotak', 'No. kotak pengikat', pb.no_kotak_pengikat, (v) => setPb({ ...pb, no_kotak_pengikat: v }))}
                </fieldset>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    <Button onClick={submit} disabled={busy || !hm.no_hak_milik || !pb.kod_peti_besi}>
                        {busy ? 'Memproses…' : 'Tutup kes'}
                    </Button>
                </div>
            </div>
        </Modal>
    );
}

// ------------------------------------------------------------------ tabs

function Ringkasan({ kes, onChanged }: { kes: KesPenuh; onChanged: () => void }) {
    const t = kes.ringkasan_tempoh;
    return (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <dl>
                <Row label="No. kes">{kes.no_kes}</Row>
                <Row label="Saluran">
                    {kes.saluran.nama} (Lampiran H {kes.saluran.rujukan_lampiran_h})
                </Row>
                <Row label="Bahagian bertanggungjawab">{BAHAGIAN_LABEL[kes.kod_bahagian] ?? kes.kod_bahagian}</Row>
                <Row label="Lot">
                    {kes.lot.berdaftar && kes.lot.pautan ? (
                        <Link href={kes.lot.pautan} className="text-txt-primary hover:underline">
                            {kes.lot.no_lot} (Profil Hartanah)
                        </Link>
                    ) : (
                        <>
                            {kes.lot.no_lot ?? '—'} <Badge variant="warning">Lot dijangka — belum berdaftar</Badge>
                        </>
                    )}
                </Row>
                <Row label="Mukim / Daerah">
                    {kes.nama_mukim ?? '—'} / {kes.nama_daerah ?? '—'}
                </Row>
                <Row label="Pegawai bertanggungjawab">{kes.pegawai?.nama ?? '—'}</Row>
                <Row label="Pendaftar kes">{kes.pendaftar?.nama ?? '—'}</Row>
                {kes.rujukan_luaran && <Row label="Rujukan portal">{kes.rujukan_luaran}</Row>}
            </dl>
            <div className="space-y-4">
                <dl>
                    <Row label="Tarikh mula">{tarikh(kes.tarikh_mula_kes)}</Row>
                    <Row label="Tarikh selesai">{tarikh(kes.tarikh_selesai)}</Row>
                    <Row label="Tempoh">
                        {t.tempoh_keseluruhan_hari ?? t.hari_berjalan} hari berbanding sasaran {t.tempoh_sasaran_hari} hari{' '}
                        {(t.tempoh_keseluruhan_hari ?? t.hari_berjalan) > t.tempoh_sasaran_hari ? <Badge variant="danger">Melebihi sasaran</Badge> : <Badge variant="success">Dalam sasaran</Badge>}
                    </Row>
                    <Row label="Nilai pasaran">{ringgit(kes.nilai_pasaran)}</Row>
                    <Row label="Nilai pemerolehan">{ringgit(kes.nilai_pemerolehan)}</Row>
                    <Row label="Sumber dana">{kes.sumber_dana ?? '—'}</Row>
                    <Row label="Rujukan penilaian JPPH">{kes.rujukan_penilaian_jpph ?? '—'}</Row>
                    <Row label="Dokumen">{kes.status_kelengkapan_dokumen === 'lengkap' ? 'Lengkap' : `${kes.kekurangan_dokumen.length} dokumen wajib belum diterima`}</Row>
                </dl>
                {kes.hak_milik && (
                    <Alert type="success">
                        Hak milik {kes.hak_milik.hak_milik?.no_hak_milik} didaftarkan; disimpan di peti besi {kes.hak_milik.peti_besi?.kod_peti_besi}
                        {kes.hak_milik.peti_besi?.lokasi_rak ? `, rak ${kes.hak_milik.peti_besi.lokasi_rak}` : ''}
                        {kes.hak_milik.peti_besi?.no_fail ? `, fail ${kes.hak_milik.peti_besi.no_fail}` : ''}.
                    </Alert>
                )}
                {kes.boleh.kemas_kini && kes.status !== 'selesai' && kes.status !== 'dibatalkan' && <NilaiForm kes={kes} onChanged={onChanged} />}
            </div>
        </div>
    );
}

function NilaiForm({ kes, onChanged }: { kes: KesPenuh; onChanged: () => void }) {
    const [v, setV] = useState({ nilai_pasaran: kes.nilai_pasaran ?? '', nilai_pemerolehan: kes.nilai_pemerolehan ?? '', sumber_dana: kes.sumber_dana ?? '', rujukan_penilaian_jpph: kes.rujukan_penilaian_jpph ?? '' });
    const [busy, setBusy] = useState(false);
    return (
        <details className="rounded-lg border border-otl-gray-200 p-3">
            <summary className="cursor-pointer text-body-sm font-medium text-txt-primary">Kemas kini nilai pemerolehan</summary>
            <div className="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                {(Object.keys(v) as Array<keyof typeof v>).map((k) => (
                    <div key={k}>
                        <InputLabel htmlFor={`nilai-${k}`} className="mb-1.5">
                            {k.replace(/_/g, ' ')}
                        </InputLabel>
                        <input id={`nilai-${k}`} className={inputClass} value={v[k]} onChange={(e) => setV({ ...v, [k]: e.target.value })} />
                    </div>
                ))}
            </div>
            <div className="mt-3 flex justify-end">
                <Button
                    size="sm"
                    disabled={busy}
                    onClick={async () => {
                        setBusy(true);
                        try {
                            await perApi.kemasKini(kes.id, Object.fromEntries(Object.entries(v).map(([k, x]) => [k, x === '' ? null : x])));
                            toast.success('Nilai dikemas kini.');
                            onChanged();
                        } catch (e) {
                            toast.error(errorText(e));
                        } finally {
                            setBusy(false);
                        }
                    }}
                >
                    Simpan
                </Button>
            </div>
        </details>
    );
}

function Butiran({ kes, onChanged }: { kes: KesPenuh; onChanged: () => void }) {
    const [values, setValues] = useState<Record<string, string | null>>(kes.butiran);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [busy, setBusy] = useState(false);
    const editable = kes.boleh.kemas_kini && kes.status !== 'selesai' && kes.status !== 'dibatalkan';

    async function save() {
        setBusy(true);
        setErrors({});
        const changed = Object.fromEntries(Object.entries(values).filter(([k, v]) => v !== (kes.butiran[k] ?? null) && v !== undefined));
        try {
            await perApi.kemasKini(kes.id, { butiran: changed });
            toast.success('Butiran saluran disimpan.');
            onChanged();
        } catch (e) {
            if (e instanceof ApiError) setErrors(e.errors ?? {});
            toast.error(errorText(e));
        } finally {
            setBusy(false);
        }
    }

    return (
        <div className="space-y-6">
            {kes.peribadi_dikaburkan && <Alert type="info">Maklumat peribadi (Sulit) dikaburkan — anda tiada kebenaran melihatnya.</Alert>}
            <BorangSaluran saluran={kes.saluran_penuh} values={values} onChange={(k, v) => setValues((x) => ({ ...x, [k]: v }))} dikaburkan={kes.peribadi_dikaburkan} readOnly={!editable} errors={errors} />
            {editable && (
                <div className="flex flex-wrap justify-end gap-2">
                    {kes.saluran.kod === 'faraid' && (
                        <Button
                            variant="secondary"
                            disabled={busy}
                            onClick={async () => {
                                setBusy(true);
                                try {
                                    const r = await perApi.eFaraid(kes.id);
                                    toast.success(r.message);
                                    onChanged();
                                } catch (e) {
                                    toast.error(errorText(e));
                                } finally {
                                    setBusy(false);
                                }
                            }}
                        >
                            Sahkan waris melalui e-Faraid
                        </Button>
                    )}
                    <Button onClick={save} disabled={busy}>
                        Simpan butiran
                    </Button>
                </div>
            )}
            {kes.bayaran_5a && <Bayaran5aPanel kes={kes} onChanged={onChanged} />}
        </div>
    );
}

function Bayaran5aPanel({ kes, onChanged }: { kes: KesPenuh; onChanged: () => void }) {
    const b = kes.bayaran_5a!;
    const [form, setForm] = useState({ jumlah_dibayar: '', tarikh_bayaran: new Date().toISOString().slice(0, 10), mod_bayaran: 'eft', no_resit: '' });
    const [busy, setBusy] = useState(false);

    async function act(fn: () => Promise<{ message: string }>) {
        setBusy(true);
        try {
            const r = await fn();
            toast.success(r.message);
            onChanged();
        } catch (e) {
            toast.error(errorText(e));
        } finally {
            setBusy(false);
        }
    }

    return (
        <section className="rounded-lg border border-otl-gray-200 p-4 space-y-4">
            <SectionTitle>Bayaran notis 5A</SectionTitle>
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 text-body-sm">
                <div>
                    <p className="text-txt-black-500">Perlu dibayar</p>
                    <p className="font-semibold">{ringgit(b.jumlah_perlu_dibayar)}</p>
                </div>
                <div>
                    <p className="text-txt-black-500">Disahkan Kewangan</p>
                    <p className="font-semibold">{ringgit(b.jumlah_disahkan)}</p>
                </div>
                <div>
                    <p className="text-txt-black-500">Baki tertunggak</p>
                    <p className={`font-semibold ${b.lengkap ? 'text-txt-success' : 'text-txt-danger'}`}>{ringgit(b.baki_tertunggak)}</p>
                </div>
                <div>
                    <p className="text-txt-black-500">Tarikh akhir bayaran</p>
                    <p className="font-semibold">{tarikh(b.tarikh_akhir_bayaran)}</p>
                </div>
            </div>
            {!b.lengkap && <Alert type="warning">Kes tidak boleh ke peringkat penerimaan hak milik selagi bayaran belum disahkan lengkap oleh Bahagian Kewangan.</Alert>}
            {b.bayaran.length > 0 && (
                <div className="overflow-x-auto">
                    <Table
                        head={
                            <>
                                <th>Tarikh</th>
                                <th>No. resit</th>
                                <th className="text-right">Jumlah</th>
                                <th>Status</th>
                                <th />
                            </>
                        }
                    >
                        {b.bayaran.map((p) => (
                            <tr key={p.id}>
                                <td className="whitespace-nowrap">{tarikh(p.tarikh_bayaran)}</td>
                                <td>
                                    {p.no_resit} <span className="text-body-xs text-txt-black-500">({p.mod_bayaran.toUpperCase()})</span>
                                </td>
                                <td className="text-right whitespace-nowrap">{ringgit(p.jumlah_dibayar)}</td>
                                <td>
                                    <Badge variant={p.status_pengesahan === 'disahkan' ? 'success' : p.status_pengesahan === 'ditolak' ? 'danger' : 'warning'}>
                                        {p.status_pengesahan === 'disahkan' ? 'Disahkan' : p.status_pengesahan === 'ditolak' ? 'Ditolak' : 'Menunggu pengesahan'}
                                    </Badge>
                                </td>
                                <td className="whitespace-nowrap">
                                    {p.status_pengesahan === 'belum_disahkan' && kes.boleh.sahkan_bayaran && (
                                        <div className="flex gap-1">
                                            <Button size="sm" disabled={busy} onClick={() => act(() => perApi.sahkanBayaran(kes.id, p.id, true))}>
                                                Sahkan
                                            </Button>
                                            <Button size="sm" variant="outline" disabled={busy} onClick={() => act(() => perApi.sahkanBayaran(kes.id, p.id, false))}>
                                                Tolak
                                            </Button>
                                        </div>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </Table>
                </div>
            )}
            {kes.boleh.bayaran && kes.status !== 'selesai' && (
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                    <div>
                        <InputLabel htmlFor="b-jumlah" className="mb-1.5">
                            Jumlah (RM)
                        </InputLabel>
                        <input id="b-jumlah" type="number" min={0} step="0.01" className={inputClass} value={form.jumlah_dibayar} onChange={(e) => setForm({ ...form, jumlah_dibayar: e.target.value })} />
                    </div>
                    <div>
                        <InputLabel htmlFor="b-tarikh" className="mb-1.5">
                            Tarikh bayaran
                        </InputLabel>
                        <input id="b-tarikh" type="date" className={inputClass} value={form.tarikh_bayaran} onChange={(e) => setForm({ ...form, tarikh_bayaran: e.target.value })} />
                    </div>
                    <div>
                        <InputLabel htmlFor="b-mod" className="mb-1.5">
                            Mod bayaran
                        </InputLabel>
                        <select id="b-mod" className={inputClass} value={form.mod_bayaran} onChange={(e) => setForm({ ...form, mod_bayaran: e.target.value })}>
                            <option value="eft">EFT</option>
                            <option value="cek">Cek</option>
                            <option value="tunai">Tunai</option>
                            <option value="grp">GRP MAIS</option>
                            <option value="lain">Lain-lain</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel htmlFor="b-resit" className="mb-1.5">
                            No. resit
                        </InputLabel>
                        <input id="b-resit" className={inputClass} value={form.no_resit} onChange={(e) => setForm({ ...form, no_resit: e.target.value })} />
                    </div>
                    <Button disabled={busy || !form.jumlah_dibayar || !form.no_resit} onClick={() => act(() => perApi.bayaran(kes.id, form))}>
                        Rekod bayaran
                    </Button>
                </div>
            )}
        </section>
    );
}

function PihakTab({ kes, onChanged }: { kes: KesPenuh; onChanged: () => void }) {
    const [busy, setBusy] = useState(false);
    const [penuh, setPenuh] = useState<Record<number, string>>({});
    const editable = kes.boleh.kemas_kini && kes.status !== 'selesai' && kes.status !== 'dibatalkan';

    return (
        <div className="space-y-4">
            {kes.peribadi_dikaburkan && <Alert type="info">Nama dan no. pengenalan dikaburkan (Sulit).</Alert>}
            {kes.pihak.length === 0 ? (
                <p className="text-body-sm text-txt-black-500">Tiada pihak berkaitan direkodkan.</p>
            ) : (
                <div className="overflow-x-auto">
                    <Table
                        head={
                            <>
                                <th>Peranan</th>
                                <th>Nama</th>
                                <th className="hidden sm:table-cell">Hubungan / bahagian</th>
                                <th className="hidden md:table-cell">No. pengenalan</th>
                                <th>Pengesahan</th>
                                {editable && <th />}
                            </>
                        }
                    >
                        {kes.pihak.map((p) => (
                            <tr key={p.id}>
                                <td>{p.label_peranan}</td>
                                <td className="break-words">{p.nama}</td>
                                <td className="hidden sm:table-cell">{[p.hubungan, p.bahagian_pusaka].filter(Boolean).join(' · ') || '—'}</td>
                                <td className="hidden md:table-cell whitespace-nowrap">
                                    {penuh[p.id] ?? p.no_dokumen_pengenalan ?? '—'}
                                    {kes.boleh.lihat_sulit && p.no_dokumen_pengenalan && !penuh[p.id] && (
                                        <button
                                            type="button"
                                            className="ml-2 text-body-xs text-txt-primary hover:underline"
                                            onClick={async () => {
                                                try {
                                                    const r = await perApi.pihakPenuh(kes.id, p.id);
                                                    setPenuh((x) => ({ ...x, [p.id]: r.data.no_dokumen_pengenalan ?? '' }));
                                                } catch (e) {
                                                    toast.error(errorText(e));
                                                }
                                            }}
                                        >
                                            Papar (diaudit)
                                        </button>
                                    )}
                                </td>
                                <td>
                                    <Badge variant={p.status_pengesahan === 'disahkan' ? 'success' : p.status_pengesahan === 'ditolak' ? 'danger' : 'warning'}>
                                        {p.status_pengesahan === 'disahkan' ? 'Disahkan' : p.status_pengesahan === 'ditolak' ? 'Ditolak' : 'Belum disahkan'}
                                    </Badge>
                                    {p.sumber_pengesahan === 'e_faraid' && <span className="ml-1 text-body-xs text-txt-black-500">e-Faraid</span>}
                                </td>
                                {editable && (
                                    <td className="whitespace-nowrap">
                                        {p.status_pengesahan !== 'disahkan' && (
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                disabled={busy}
                                                onClick={async () => {
                                                    await perApi.kemasKiniPihak(kes.id, p.id, { status_pengesahan: 'disahkan' }).catch((e) => toast.error(errorText(e)));
                                                    onChanged();
                                                }}
                                            >
                                                Sahkan
                                            </Button>
                                        )}{' '}
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            disabled={busy}
                                            onClick={async () => {
                                                if (!window.confirm('Padam pihak ini?')) return;
                                                await perApi.padamPihak(kes.id, p.id).catch((e) => toast.error(errorText(e)));
                                                onChanged();
                                            }}
                                        >
                                            Padam
                                        </Button>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </Table>
                </div>
            )}
            {editable && (
                <PihakForm
                    roles={kes.saluran_penuh.peranan_pihak}
                    busy={busy}
                    onAdd={async (p) => {
                        setBusy(true);
                        try {
                            await perApi.tambahPihak(kes.id, p);
                            toast.success('Pihak ditambah.');
                            onChanged();
                        } catch (e) {
                            toast.error(errorText(e));
                        } finally {
                            setBusy(false);
                        }
                    }}
                />
            )}
        </div>
    );
}

function PeringkatTab({ kes }: { kes: KesPenuh }) {
    const { data: garis } = useQuery({ queryKey: ['per', 'garis-masa', kes.id, kes.peringkat_semasa?.kod, kes.status], queryFn: () => perApi.garisMasa(kes.id).then((r) => r.data) });

    return (
        <div className="space-y-6">
            <ol className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                {kes.peringkat.map((s) => (
                    <li
                        key={s.id}
                        className={`rounded-lg border p-3 ${s.status === 'semasa' ? 'border-primary-600 bg-bg-primary-50' : s.status === 'selesai' ? 'border-otl-gray-200' : 'border-dashed border-otl-gray-300'}`}
                        aria-current={s.status === 'semasa' ? 'step' : undefined}
                    >
                        <div className="flex items-start justify-between gap-2">
                            <p className="text-body-sm font-medium text-txt-black-900">
                                {s.susunan}. {s.nama}
                            </p>
                            <Badge variant={s.status === 'selesai' ? 'success' : s.status === 'semasa' ? 'info' : 'neutral'}>{s.status === 'selesai' ? 'Selesai' : s.status === 'semasa' ? 'Semasa' : 'Belum'}</Badge>
                        </div>
                        <dl className="mt-2 space-y-0.5 text-body-xs text-txt-black-700">
                            <div>Pegawai: {s.pegawai?.nama ?? '—'}</div>
                            <div>Masuk: {tarikhMasa(s.tarikh_masuk)}</div>
                            <div>Keluar: {tarikhMasa(s.tarikh_keluar)}</div>
                            <div className={s.lewat ? 'text-txt-danger font-medium' : ''}>
                                Tempoh: {s.tempoh_hari ?? '—'} / sasaran {s.tempoh_sasaran_hari} hari
                                {s.status_eskalasi === 'dieskalasi' ? ' · dieskalasi' : ''}
                            </div>
                            {s.jenis === 'kelulusan' && <div className="text-txt-black-500">Melalui Workflow Engine (kelulusan berlapis)</div>}
                        </dl>
                    </li>
                ))}
            </ol>

            <section>
                <SectionTitle>Garis masa</SectionTitle>
                {!garis ? (
                    <Spinner />
                ) : (
                    <ol className="relative border-s border-otl-gray-200 ms-3 space-y-5">
                        {garis.map((g, i) => (
                            <li key={i} className="ms-5">
                                <span className={`absolute -start-1.5 mt-1.5 h-3 w-3 rounded-full ${g.jenis === 'kelulusan' ? 'bg-warning-600' : g.lewat ? 'bg-danger-600' : 'bg-primary-600'}`} />
                                <p className="text-body-sm font-medium text-txt-black-900">
                                    {g.jenis === 'kelulusan' ? `Kelulusan: ${String(g.kod).replace(/_/g, ' ')} — ${g.tajuk}` : String(g.tajuk)}
                                </p>
                                <p className="text-body-xs text-txt-black-500">
                                    {tarikhMasa(String(g.tarikh))}
                                    {g.pegawai ? ` · ${g.pegawai}` : ''}
                                    {g.jenis === 'peringkat' && ` · ${g.tempoh_hari ?? 0}/${g.tempoh_sasaran_hari} hari`}
                                    {g.justifikasi ? ` · ${g.justifikasi}` : ''}
                                </p>
                            </li>
                        ))}
                    </ol>
                )}
            </section>
        </div>
    );
}

function KelulusanTab({ kes }: { kes: KesPenuh }) {
    return (
        <div className="space-y-4">
            <p className="text-body-sm text-txt-black-500">
                Kelulusan berlapis mengikut saluran dan nilai dijalankan oleh Workflow Engine (aliran <code>per.kelulusan</code>). Pendaftar kes tidak boleh meluluskan kes sendiri.
            </p>
            {kes.kelulusan.length === 0 ? (
                <p className="text-body-sm text-txt-black-500">Belum dihantar untuk kelulusan.</p>
            ) : (
                <div className="overflow-x-auto">
                    <Table
                        head={
                            <>
                                <th>Tarikh</th>
                                <th>Aras</th>
                                <th>Tindakan</th>
                                <th className="hidden sm:table-cell">Pegawai</th>
                                <th className="hidden md:table-cell">Justifikasi</th>
                            </>
                        }
                    >
                        {kes.kelulusan.map((k) => (
                            <tr key={k.id}>
                                <td className="whitespace-nowrap">{tarikhMasa(k.tarikh_masa)}</td>
                                <td>{k.aras.replace(/_/g, ' ')}</td>
                                <td>
                                    <Badge variant={k.tindakan === 'lulus' ? 'success' : k.tindakan === 'tolak' ? 'danger' : 'neutral'}>{k.tindakan}</Badge>
                                </td>
                                <td className="hidden sm:table-cell">
                                    {k.pelulus?.nama ?? '—'}
                                    {k.jawatan ? ` (${k.jawatan})` : ''}
                                </td>
                                <td className="hidden md:table-cell break-words">{k.justifikasi ?? '—'}</td>
                            </tr>
                        ))}
                    </Table>
                </div>
            )}
        </div>
    );
}
