'use client';

import { Suspense, useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Callout } from '@/components/ui/Callout';
import { Modal } from '@/components/ui/Modal';
import { Pagination } from '@/components/ui/Pagination';
import { StatCard } from '@/components/ui/StatCard';
import { ARAS_LABEL, ARAS_VARIANT, lapApi, papar, ralat, tarikhMasa, useCan, type Cadangan } from '@/lib/lap';
import { Card, Field, inputClass, Kosong, LabelCadangan } from '../_components/common';

const JENIS = [
    ['risiko_tunggakan', 'Risiko tunggakan'],
    ['tanah_tidak_produktif', 'Tanah tidak produktif'],
    ['potensi_hasil', 'Potensi hasil sewaan'],
    ['potensi_pembangunan', 'Potensi pembangunan'],
] as const;

/** SRS-LAP-15..19, 21 — rule-based analytics and recommendations (system suggestions). */
function Analitik() {
    const can = useCan();
    const qc = useQueryClient();
    const params = useSearchParams();
    const [jenis, setJenis] = useState(params.get('jenis') ?? 'risiko_tunggakan');
    const [aras, setAras] = useState('');
    const [halaman, setHalaman] = useState(1);
    const [statusCad, setStatusCad] = useState<'baharu' | 'diterima' | 'ditolak'>('baharu');
    const [putus, setPutus] = useState<Cadangan | null>(null);

    const ringkasan = useQuery({ queryKey: ['lap', 'analitik', 'ringkasan'], queryFn: () => lapApi.ringkasanAnalitik() });
    const hasil = useQuery({ queryKey: ['lap', 'analitik', jenis, aras, halaman], queryFn: () => lapApi.analitik({ jenis, aras, halaman, per_halaman: 25 }) });
    const cadangan = useQuery({ queryKey: ['lap', 'cadangan', statusCad], queryFn: () => lapApi.cadangan({ status: statusCad }) });
    const jalan = useMutation({
        mutationFn: () => lapApi.jalankanAnalitik(),
        onSuccess: () => {
            toast.success('Analitik dikira semula.');
            qc.invalidateQueries({ queryKey: ['lap', 'analitik'] });
            qc.invalidateQueries({ queryKey: ['lap', 'cadangan'] });
        },
        onError: (e) => toast.error(ralat(e)),
    });

    return (
        <PageContainer>
            <LabelCadangan teks={ringkasan.data?.label_cadangan} />
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {ringkasan.data?.data.map((r) => (
                    <button key={r.jenis} type="button" onClick={() => { setJenis(r.jenis); setHalaman(1); }} className={`text-left rounded-lg ${jenis === r.jenis ? 'ring-2 ring-primary-600' : ''}`}>
                        <StatCard label={r.nama} value={r.jumlah.toLocaleString('ms-MY')} change={`${r.aras.kritikal} kritikal · ${r.aras.tinggi} tinggi`} trend={r.aras.kritikal > 0 ? 'down' : 'neutral'} />
                    </button>
                ))}
            </div>

            <Card
                title={`Hasil analitik — ${JENIS.find(([k]) => k === jenis)?.[1] ?? jenis}`}
                actions={
                    can('lap.analitik.kemas_kini') ? (
                        <Button size="sm" onClick={() => jalan.mutate()} disabled={jalan.isPending}>
                            {jalan.isPending ? 'Mengira…' : 'Kira semula'}
                        </Button>
                    ) : null
                }
            >
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <Field label="Jenis analitik" htmlFor="ajenis">
                        <select id="ajenis" className={inputClass} value={jenis} onChange={(e) => { setJenis(e.target.value); setHalaman(1); }}>
                            {JENIS.map(([k, v]) => (
                                <option key={k} value={k}>
                                    {v}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Aras" htmlFor="aaras">
                        <select id="aaras" className={inputClass} value={aras} onChange={(e) => { setAras(e.target.value); setHalaman(1); }}>
                            <option value="">Semua</option>
                            {Object.entries(ARAS_LABEL).map(([k, v]) => (
                                <option key={k} value={k}>
                                    {v}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <p className="text-body-xs text-txt-black-500 sm:mt-7">
                        Model: {ringkasan.data?.versi_model ?? '—'} · Peta: lapisan «Analitik MOD-LAP» dalam{' '}
                        <Link href="/admin/gis" className="text-txt-primary hover:underline">
                            GIS
                        </Link>
                    </p>
                </div>
                {hasil.isError && <Callout type="danger">{ralat(hasil.error)}</Callout>}
                {hasil.data && hasil.data.data.length === 0 && <Kosong>Tiada hasil dalam skop anda. Jalankan analitik untuk mengira.</Kosong>}
                {hasil.data && hasil.data.data.length > 0 && (
                    <>
                        <Table head={<><th>No. lot</th><th>Daerah</th><th className="text-right">Skor</th><th>Aras</th><th>Faktor</th><th>Dikira</th><th /></>}>
                            {hasil.data.data.map((h) => (
                                <tr key={h.id}>
                                    <td className="font-medium">{h.no_lot ?? '—'}</td>
                                    <td>{h.kod_daerah ?? '—'}</td>
                                    <td className="text-right tabular-nums">{h.skor}</td>
                                    <td>
                                        <Badge variant={ARAS_VARIANT[h.aras]}>{ARAS_LABEL[h.aras]}</Badge>
                                    </td>
                                    <td className="text-body-xs text-txt-black-700 max-w-md">
                                        {Object.entries(h.faktor)
                                            .filter(([, v]) => v !== null && v !== '' && typeof v !== 'object')
                                            .slice(0, 5)
                                            .map(([k, v]) => `${k.replaceAll('_', ' ')}: ${k.includes('baki') || k.includes('anggaran') ? `RM ${papar('wang', v)}` : typeof v === 'boolean' ? (v ? 'Ya' : 'Tidak') : String(v)}`)
                                            .join(' · ')}
                                    </td>
                                    <td className="whitespace-nowrap">{tarikhMasa(h.tarikh_kira)}</td>
                                    <td>
                                        <Link href={h.pautan} className="text-txt-primary hover:underline whitespace-nowrap">
                                            Profil lot
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </Table>
                        {hasil.data.meta.halaman_akhir > 1 && <Pagination currentPage={hasil.data.meta.halaman} lastPage={hasil.data.meta.halaman_akhir} onPageChange={setHalaman} total={hasil.data.meta.jumlah} />}
                    </>
                )}
            </Card>

            <Card
                title="Cadangan tindakan (smart recommendation)"
                actions={
                    <select aria-label="Status cadangan" className={inputClass} value={statusCad} onChange={(e) => setStatusCad(e.target.value as typeof statusCad)}>
                        <option value="baharu">Menunggu keputusan</option>
                        <option value="diterima">Diterima</option>
                        <option value="ditolak">Ditolak</option>
                    </select>
                }
            >
                {cadangan.data && cadangan.data.data.length === 0 && <Kosong>Tiada cadangan.</Kosong>}
                {cadangan.data && cadangan.data.data.length > 0 && (
                    <Table head={<><th>No. lot</th><th>Analitik</th><th>Cadangan</th><th>Asas</th><th>{statusCad === 'baharu' ? 'Tindakan' : 'Keputusan'}</th></>}>
                        {cadangan.data.data.map((c) => (
                            <tr key={c.id}>
                                <td>
                                    <Link href={c.pautan} className="text-txt-primary hover:underline">
                                        {c.no_lot ?? '—'}
                                    </Link>
                                </td>
                                <td>{c.jenis_nama}</td>
                                <td className="font-medium">{c.tindakan_nama}</td>
                                <td className="text-body-xs max-w-md">{c.asas}</td>
                                <td className="whitespace-nowrap">
                                    {c.status === 'baharu' ? (
                                        can('lap.analitik.lulus') ? (
                                            <Button size="sm" onClick={() => setPutus(c)}>
                                                Putuskan
                                            </Button>
                                        ) : (
                                            '—'
                                        )
                                    ) : (
                                        <span className="text-body-xs">
                                            {c.pegawai} · {tarikhMasa(c.tarikh_keputusan)}
                                            <br />
                                            {c.justifikasi}
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>
            {putus && <Keputusan c={putus} onClose={() => setPutus(null)} />}
        </PageContainer>
    );
}

function Keputusan({ c, onClose }: { c: Cadangan; onClose: () => void }) {
    const qc = useQueryClient();
    const [keputusan, setKeputusan] = useState<'diterima' | 'ditolak'>('diterima');
    const [justifikasi, setJustifikasi] = useState('');
    const m = useMutation({
        mutationFn: () => lapApi.putuskan(c.id, keputusan, justifikasi),
        onSuccess: () => {
            toast.success('Keputusan dan justifikasi direkod.');
            qc.invalidateQueries({ queryKey: ['lap', 'cadangan'] });
            qc.invalidateQueries({ queryKey: ['lap', 'analitik', 'ringkasan'] });
            onClose();
        },
        onError: (e) => toast.error(ralat(e)),
    });
    return (
        <Modal open onClose={onClose}>
            <div className="p-6 space-y-4">
                <h3 className="font-heading font-semibold text-body-lg">
                    {c.tindakan_nama} — {c.no_lot}
                </h3>
                <p className="text-body-sm text-txt-black-700">{c.asas}</p>
                <p className="text-body-xs text-txt-warning">Cadangan sistem — keputusan rasmi adalah keputusan pegawai MAIS.</p>
                <Field label="Keputusan" htmlFor="kep">
                    <select id="kep" className={inputClass} value={keputusan} onChange={(e) => setKeputusan(e.target.value as typeof keputusan)}>
                        <option value="diterima">Terima</option>
                        <option value="ditolak">Tolak</option>
                    </select>
                </Field>
                <Field label="Justifikasi (wajib, min. 10 aksara)" htmlFor="just">
                    <textarea id="just" rows={4} className={inputClass} value={justifikasi} onChange={(e) => setJustifikasi(e.target.value)} />
                </Field>
                <div className="flex justify-end gap-2">
                    <Button variant="outline" onClick={onClose}>
                        Batal
                    </Button>
                    <Button onClick={() => m.mutate()} disabled={justifikasi.trim().length < 10 || m.isPending}>
                        Rekod keputusan
                    </Button>
                </div>
            </div>
        </Modal>
    );
}

export default function AnalitikPage() {
    return (
        <Suspense>
            <Analitik />
        </Suspense>
    );
}
