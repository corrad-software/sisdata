'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { kesApi, ralat, useCan, useRujukan } from '@/lib/kes';
import { Card, Field, inputClass, RalatAkses, Select } from '../../_components/common';

type PihakBaris = { nama_pihak: string; jenis_pihak: string; peranan: string; no_pengenalan: string; maklumat_perhubungan: string; status_wakilan: string };
type PeguamBaris = { nama_peguam: string; nama_firma: string; no_telefon: string; emel: string; pihak_indeks: string };
type LotPilih = { id: string; no_lot: string; mukim: string | null; daerah: string | null };

const PIHAK_MAIS: PihakBaris = { nama_pihak: 'Majlis Agama Islam Selangor', jenis_pihak: 'mais', peranan: 'plaintif', no_pengenalan: '', maklumat_perhubungan: '', status_wakilan: 'diwakili' };
const PIHAK_KOSONG: PihakBaris = { nama_pihak: '', jenis_pihak: 'individu', peranan: 'defendan', no_pengenalan: '', maklumat_perhubungan: '', status_wakilan: 'diwakili' };

/** SRS-KES-02..05: register a case with all A-12.02 fields, parties, counsel and lots. */
export default function KesBaharuPage() {
    const router = useRouter();
    const can = useCan();
    const { data: ref, error: refError } = useRujukan();
    const [f, setF] = useState<Record<string, string>>({ no_kes: '', tajuk: '', jenis: '', mahkamah: '', peringkat_mahkamah: '', tarikh_pemfailan: '', ringkasan_tuntutan: '', nilai_tuntutan: '', peranan_mais: 'plaintif', id_pegawai_bertanggungjawab: '' });
    const [pihak, setPihak] = useState<PihakBaris[]>([PIHAK_MAIS, PIHAK_KOSONG]);
    const [peguam, setPeguam] = useState<PeguamBaris[]>([]);
    const [lots, setLots] = useState<LotPilih[]>([]);
    const [cari, setCari] = useState('');
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<string | null>(null);
    const set = (k: string) => (v: string) => setF((x) => ({ ...x, [k]: v }));
    const { data: hasil } = useQuery({ queryKey: ['kes', 'cari-lot', cari], queryFn: () => kesApi.cariLot(cari).then((r) => r.data), enabled: cari.trim().length >= 2 });

    if (refError) {
        return (
            <PageContainer>
                <RalatAkses error={refError} />
            </PageContainer>
        );
    }

    async function hantar(e: React.FormEvent) {
        e.preventDefault();
        setBusy(true);
        setErrors(null);
        try {
            const body = {
                ...Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '')),
                lot_ids: lots.map((l) => l.id),
                pihak: pihak.filter((p) => p.nama_pihak.trim()).map((p) => ({ ...p, adalah_mais: p.jenis_pihak === 'mais', no_pengenalan: p.no_pengenalan || null, maklumat_perhubungan: p.maklumat_perhubungan || null })),
                peguam: peguam.filter((p) => p.nama_peguam.trim()).map((p) => ({ ...p, pihak_indeks: p.pihak_indeks === '' ? null : Number(p.pihak_indeks), emel: p.emel || null })),
            };
            const res = await kesApi.cipta(body);
            toast.success(res.message);
            router.push(`/admin/kes/kes/${res.data.id}`);
        } catch (err) {
            setErrors(ralat(err));
        } finally {
            setBusy(false);
        }
    }

    const opsyenPihak = pihak.map((p, i) => ({ kod: String(i), nama: `${p.nama_pihak || 'Pihak ' + (i + 1)} (${ref?.peranan.find((r) => r.kod === p.peranan)?.nama ?? p.peranan})` }));

    return (
        <PageContainer>
            <form onSubmit={hantar} className="space-y-6">
                <Card title="Maklumat kes">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <Field label="Tajuk kes" htmlFor="f-tajuk" className="sm:col-span-2">
                            <input id="f-tajuk" className={inputClass} required value={f.tajuk} onChange={(e) => set('tajuk')(e.target.value)} placeholder="cth. MAIS lwn ..." />
                        </Field>
                        <Field label="No. kes mahkamah" htmlFor="f-no" help="Kosongkan jika belum difailkan">
                            <input id="f-no" className={inputClass} value={f.no_kes} onChange={(e) => set('no_kes')(e.target.value)} />
                        </Field>
                        <Field label="Jenis kes" htmlFor="f-jenis">
                            <Select id="f-jenis" value={f.jenis} onChange={set('jenis')} options={(ref?.jenis ?? []).filter((j) => j.aktif)} empty="Pilih" required />
                        </Field>
                        <Field label="Nama mahkamah" htmlFor="f-mah">
                            <input id="f-mah" className={inputClass} required value={f.mahkamah} onChange={(e) => set('mahkamah')(e.target.value)} />
                        </Field>
                        <Field label="Peringkat mahkamah" htmlFor="f-per">
                            <Select id="f-per" value={f.peringkat_mahkamah} onChange={set('peringkat_mahkamah')} options={ref?.peringkat_mahkamah ?? []} empty="Pilih" required />
                        </Field>
                        <Field label="Tarikh pemfailan" htmlFor="f-tf">
                            <input id="f-tf" type="date" className={inputClass} value={f.tarikh_pemfailan} onChange={(e) => set('tarikh_pemfailan')(e.target.value)} />
                        </Field>
                        <Field label="Nilai tuntutan (RM)" htmlFor="f-nt">
                            <input id="f-nt" inputMode="decimal" pattern="^\d{1,14}(\.\d{1,2})?$" className={inputClass} value={f.nilai_tuntutan} onChange={(e) => set('nilai_tuntutan')(e.target.value)} />
                        </Field>
                        <Field label="Peranan MAIS" htmlFor="f-pm">
                            <Select id="f-pm" value={f.peranan_mais} onChange={set('peranan_mais')} options={ref?.peranan ?? []} required />
                        </Field>
                        {can('kes.kes.lulus') && (
                            <Field label="Pegawai bertanggungjawab" htmlFor="f-pg">
                                <Select id="f-pg" value={f.id_pegawai_bertanggungjawab} onChange={set('id_pegawai_bertanggungjawab')} options={(ref?.pegawai ?? []).map((u) => ({ kod: String(u.id), nama: u.nama }))} empty="Tugaskan kemudian" />
                            </Field>
                        )}
                        <Field label="Ringkasan tuntutan" htmlFor="f-rt" className="sm:col-span-2 lg:col-span-3">
                            <textarea id="f-rt" rows={4} className={inputClass} required value={f.ringkasan_tuntutan} onChange={(e) => set('ringkasan_tuntutan')(e.target.value)} />
                        </Field>
                    </div>
                </Card>

                <Card title="Lot berkaitan" actions={<span className="text-body-xs text-txt-black-500">Satu kes boleh dipaut kepada berbilang lot</span>}>
                    <Field label="Cari lot" htmlFor="f-lot">
                        <input id="f-lot" type="search" className={inputClass} placeholder="No. lot, cth. PT 1234" value={cari} onChange={(e) => setCari(e.target.value)} />
                    </Field>
                    {(hasil ?? []).length > 0 && (
                        <ul className="divide-y divide-otl-divider rounded-md border border-otl-gray-200">
                            {(hasil ?? []).map((l) => (
                                <li key={l.id} className="flex items-center justify-between gap-2 px-3 py-2 text-body-sm">
                                    <span>
                                        {l.no_lot} <span className="text-txt-black-500">· {[l.mukim, l.daerah].filter(Boolean).join(', ')}</span>
                                    </span>
                                    <Button size="sm" variant="secondary" disabled={lots.some((x) => x.id === l.id)} onClick={() => setLots((x) => [...x, l])}>
                                        Pilih
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                    <div className="flex flex-wrap gap-2">
                        {lots.map((l) => (
                            <button key={l.id} type="button" onClick={() => setLots((x) => x.filter((y) => y.id !== l.id))} className="rounded-full border border-primary-600 bg-bg-primary-50 px-3 py-1 text-body-xs font-medium text-txt-primary">
                                {l.no_lot} ✕
                            </button>
                        ))}
                        {lots.length === 0 && <span className="text-body-sm text-txt-black-500">Tiada lot dipilih.</span>}
                    </div>
                </Card>

                <Card
                    title="Pihak terlibat"
                    actions={
                        <Button size="sm" variant="secondary" onClick={() => setPihak((x) => [...x, PIHAK_KOSONG])}>
                            Tambah pihak
                        </Button>
                    }
                >
                    <p className="text-body-xs text-txt-black-500">Nama, no. pengenalan dan maklumat perhubungan disulitkan dan dipaparkan bertopeng (Rahsia).</p>
                    {pihak.map((p, i) => {
                        const up = (k: keyof PihakBaris) => (v: string) => setPihak((x) => x.map((y, j) => (j === i ? { ...y, [k]: v } : y)));
                        return (
                            <div key={i} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 border-t border-otl-divider pt-3">
                                <Field label="Nama pihak" htmlFor={`p-n-${i}`} className="lg:col-span-2">
                                    <input id={`p-n-${i}`} className={inputClass} value={p.nama_pihak} onChange={(e) => up('nama_pihak')(e.target.value)} />
                                </Field>
                                <Field label="Jenis" htmlFor={`p-j-${i}`}>
                                    <Select id={`p-j-${i}`} value={p.jenis_pihak} onChange={up('jenis_pihak')} options={ref?.jenis_pihak ?? []} />
                                </Field>
                                <Field label="Peranan" htmlFor={`p-r-${i}`}>
                                    <Select id={`p-r-${i}`} value={p.peranan} onChange={up('peranan')} options={ref?.peranan ?? []} />
                                </Field>
                                <Field label="No. pengenalan / pendaftaran" htmlFor={`p-k-${i}`}>
                                    <input id={`p-k-${i}`} className={inputClass} value={p.no_pengenalan} onChange={(e) => up('no_pengenalan')(e.target.value)} />
                                </Field>
                                <Field label="Status wakilan" htmlFor={`p-w-${i}`}>
                                    <Select id={`p-w-${i}`} value={p.status_wakilan} onChange={up('status_wakilan')} options={ref?.status_wakilan ?? []} />
                                </Field>
                                <Field label="Maklumat perhubungan" htmlFor={`p-h-${i}`} className="sm:col-span-2 lg:col-span-5">
                                    <input id={`p-h-${i}`} className={inputClass} value={p.maklumat_perhubungan} onChange={(e) => up('maklumat_perhubungan')(e.target.value)} />
                                </Field>
                                <div className="flex items-end">
                                    <Button size="sm" variant="secondary" onClick={() => setPihak((x) => x.filter((_, j) => j !== i))}>
                                        Buang
                                    </Button>
                                </div>
                            </div>
                        );
                    })}
                </Card>

                <Card
                    title="Peguam pihak"
                    actions={
                        <Button size="sm" variant="secondary" onClick={() => setPeguam((x) => [...x, { nama_peguam: '', nama_firma: '', no_telefon: '', emel: '', pihak_indeks: '' }])}>
                            Tambah peguam
                        </Button>
                    }
                >
                    <p className="text-body-xs text-txt-black-500">
                        Panel peguam MAIS dilantik dari halaman kes selepas pendaftaran (tertakluk kelulusan MAIS). <Badge variant="info">Modul Workflow</Badge>
                    </p>
                    {peguam.map((p, i) => {
                        const up = (k: keyof PeguamBaris) => (v: string) => setPeguam((x) => x.map((y, j) => (j === i ? { ...y, [k]: v } : y)));
                        return (
                            <div key={i} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 border-t border-otl-divider pt-3">
                                <Field label="Nama peguam" htmlFor={`g-n-${i}`}>
                                    <input id={`g-n-${i}`} className={inputClass} value={p.nama_peguam} onChange={(e) => up('nama_peguam')(e.target.value)} />
                                </Field>
                                <Field label="Firma" htmlFor={`g-f-${i}`}>
                                    <input id={`g-f-${i}`} className={inputClass} value={p.nama_firma} onChange={(e) => up('nama_firma')(e.target.value)} />
                                </Field>
                                <Field label="Telefon" htmlFor={`g-t-${i}`}>
                                    <input id={`g-t-${i}`} className={inputClass} value={p.no_telefon} onChange={(e) => up('no_telefon')(e.target.value)} />
                                </Field>
                                <Field label="E-mel" htmlFor={`g-e-${i}`}>
                                    <input id={`g-e-${i}`} type="email" className={inputClass} value={p.emel} onChange={(e) => up('emel')(e.target.value)} />
                                </Field>
                                <Field label="Mewakili" htmlFor={`g-p-${i}`}>
                                    <Select id={`g-p-${i}`} value={p.pihak_indeks} onChange={up('pihak_indeks')} options={opsyenPihak} empty="—" />
                                </Field>
                            </div>
                        );
                    })}
                </Card>

                {errors && <p className="text-body-sm text-txt-danger" role="alert">{errors}</p>}
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={() => router.back()}>
                        Batal
                    </Button>
                    <Button type="submit" disabled={busy}>
                        {busy ? 'Menyimpan…' : 'Daftar kes'}
                    </Button>
                </div>
            </form>
        </PageContainer>
    );
}
