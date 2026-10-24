'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { Timeline } from '@/components/ui/Timeline';
import { hkmApi, ralat, tarikh, useCan, useRujukan, type Permohonan } from '@/lib/hkm';
import { Card, Field, InfoGrid, inputClass, Kosong, Select } from '../../_components/common';

function useAction<T>(id: string, fn: (body: T) => Promise<unknown>, done = 'Dikemas kini.') {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: fn,
        onSuccess: () => {
            toast.success(done);
            queryClient.invalidateQueries({ queryKey: ['hkm', 'permohonan', id] });
            queryClient.invalidateQueries({ queryKey: ['hkm', 'hak-milik'] });
        },
        onError: (e) => toast.error(ralat(e)),
    });
}

function Tindakan({ a }: { a: Permohonan }) {
    const { data: ref } = useRujukan();
    const can = useCan();
    const [stage, setStage] = useState({ peringkat: '', status: 'selesai', tarikh: new Date().toISOString().slice(0, 10), catatan: '' });
    const [pay, setPay] = useState({ jumlah_bayaran: a.jumlah_bayaran ?? '', tarikh_bayaran: a.tarikh_bayaran ?? '', no_resit: a.no_resit ?? '' });
    const [baru, setBaru] = useState<Record<string, string>>({ tarikh_terima: new Date().toISOString().slice(0, 10) });
    const [vault, setVault] = useState<Record<string, string>>({});

    const semak = useAction(a.id, (list: Permohonan['senarai_semak_dokumen']) => hkmApi.senaraiSemak(a.id, list ?? []), 'Senarai semak dikemas kini.');
    const peringkat = useAction(a.id, () => hkmApi.peringkat(a.id, stage), 'Peringkat direkodkan.');
    const bayaran = useAction(a.id, () => hkmApi.bayaran(a.id, { ...pay, jumlah_bayaran: Number(pay.jumlah_bayaran) }), 'Bayaran direkodkan.');
    const terima = useAction(
        a.id,
        () => hkmApi.terima(a.id, { ...baru, ...(vault.peti_besi_id ? { peti_besi: { ...vault, peti_besi_id: Number(vault.peti_besi_id) } } : {}) }),
        'Hak milik baharu didaftarkan dan dipautkan.',
    );

    const open = a.status_kelulusan === 'dalam_proses' || a.status_kelulusan === 'lulus';
    if (!open || !can('hkm.permohonan.kemas_kini')) return null;
    const list = a.senarai_semak_dokumen ?? [];
    const kekal = (ref?.jenis_hak_milik ?? []).filter((j) => !j.sementara && j.hak_milik);

    return (
        <>
            <Card title="Senarai semak dokumen">
                <ul className="space-y-2">
                    {list.map((item, i) => (
                        <li key={item.kod}>
                            <label className="inline-flex items-center gap-2 text-body-sm">
                                <input
                                    type="checkbox"
                                    checked={item.lengkap}
                                    onChange={(e) => semak.mutate(list.map((x, j) => (j === i ? { ...x, lengkap: e.target.checked } : x)))}
                                    disabled={semak.isPending}
                                />
                                {item.label}
                            </label>
                        </li>
                    ))}
                </ul>
                <p className="text-body-xs text-txt-black-500">Lampirkan salinan dokumen di tab Dokumen hak milik sementara.</p>
            </Card>

            <Card title="Kemas kini peringkat (Pejabat Tanah)">
                <form
                    className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end"
                    onSubmit={(e) => {
                        e.preventDefault();
                        peringkat.mutate(undefined);
                    }}
                >
                    <Field label="Peringkat" htmlFor="st-p">
                        <Select
                            id="st-p"
                            value={stage.peringkat}
                            onChange={(v) => setStage({ ...stage, peringkat: v })}
                            options={(ref?.peringkat_hmk ?? []).filter((p) => !['pendaftaran', 'penerimaan', 'selesai'].includes(p.kod))}
                            empty="Pilih…"
                            required
                        />
                    </Field>
                    <Field label="Keputusan" htmlFor="st-s">
                        <Select
                            id="st-s"
                            value={stage.status}
                            onChange={(v) => setStage({ ...stage, status: v })}
                            options={[
                                { kod: 'dalam_proses', nama: 'Dalam proses' },
                                { kod: 'selesai', nama: 'Selesai / lulus' },
                                { kod: 'ditolak', nama: 'Ditolak' },
                                { kod: 'dibatalkan', nama: 'Dibatalkan' },
                            ]}
                        />
                    </Field>
                    <Field label="Tarikh" htmlFor="st-t">
                        <input id="st-t" type="date" className={inputClass} value={stage.tarikh} onChange={(e) => setStage({ ...stage, tarikh: e.target.value })} />
                    </Field>
                    <Field label="Catatan" htmlFor="st-c">
                        <input id="st-c" className={inputClass} value={stage.catatan} onChange={(e) => setStage({ ...stage, catatan: e.target.value })} />
                    </Field>
                    <div className="sm:col-span-2 lg:col-span-4 flex justify-end">
                        <Button type="submit" size="sm" disabled={peringkat.isPending}>
                            Rekod peringkat
                        </Button>
                    </div>
                </form>
            </Card>

            <Card title="Rekod bayaran">
                <form
                    className="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end"
                    onSubmit={(e) => {
                        e.preventDefault();
                        bayaran.mutate(undefined);
                    }}
                >
                    <Field label="Jumlah (RM)" htmlFor="by-j">
                        <input id="by-j" type="number" step="0.01" min={0} className={inputClass} value={pay.jumlah_bayaran} onChange={(e) => setPay({ ...pay, jumlah_bayaran: e.target.value })} required />
                    </Field>
                    <Field label="Tarikh bayaran" htmlFor="by-t">
                        <input id="by-t" type="date" className={inputClass} value={pay.tarikh_bayaran} onChange={(e) => setPay({ ...pay, tarikh_bayaran: e.target.value })} required />
                    </Field>
                    <Field label="No. resit" htmlFor="by-r">
                        <input id="by-r" className={inputClass} value={pay.no_resit} onChange={(e) => setPay({ ...pay, no_resit: e.target.value })} required />
                    </Field>
                    <div className="sm:col-span-3 flex justify-end">
                        <Button type="submit" size="sm" disabled={bayaran.isPending}>
                            Simpan bayaran
                        </Button>
                    </div>
                </form>
            </Card>

            <Card title="Terima hak milik baharu dan daftar semula">
                {(!a.semak_lengkap || !a.no_resit) && <Alert type="warning">Senarai semak mesti lengkap dan bayaran direkodkan sebelum hak milik baharu boleh diterima.</Alert>}
                <form
                    className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end"
                    onSubmit={(e) => {
                        e.preventDefault();
                        terima.mutate(undefined);
                    }}
                >
                    <Field label="No. hak milik baharu" htmlFor="tb-n">
                        <input id="tb-n" className={inputClass} value={baru.no_hak_milik ?? ''} onChange={(e) => setBaru({ ...baru, no_hak_milik: e.target.value })} required />
                    </Field>
                    <Field label="Jenis hak milik" htmlFor="tb-j">
                        <Select id="tb-j" value={baru.jenis_hak_milik ?? ''} onChange={(v) => setBaru({ ...baru, jenis_hak_milik: v })} options={kekal} empty="Pilih…" required />
                    </Field>
                    <Field label="Tarikh pendaftaran" htmlFor="tb-d">
                        <input id="tb-d" type="date" className={inputClass} value={baru.tarikh_pendaftaran ?? ''} onChange={(e) => setBaru({ ...baru, tarikh_pendaftaran: e.target.value })} />
                    </Field>
                    <Field label="Tarikh diterima" htmlFor="tb-t">
                        <input id="tb-t" type="date" className={inputClass} value={baru.tarikh_terima ?? ''} onChange={(e) => setBaru({ ...baru, tarikh_terima: e.target.value })} required />
                    </Field>
                    {can('hkm.peti_besi.cipta') && (
                        <>
                            <Field label="Simpan dokumen asal di peti besi" htmlFor="tb-pb">
                                <Select
                                    id="tb-pb"
                                    value={vault.peti_besi_id ?? ''}
                                    onChange={(v) => setVault({ ...vault, peti_besi_id: v })}
                                    options={(ref?.peti_besi ?? []).map((p) => ({ kod: String(p.id), nama: p.kod_peti_besi }))}
                                    empty="Tidak sekarang"
                                />
                            </Field>
                            <Field label="Lokasi rak" htmlFor="tb-r">
                                <input id="tb-r" className={inputClass} value={vault.lokasi_rak ?? ''} onChange={(e) => setVault({ ...vault, lokasi_rak: e.target.value })} />
                            </Field>
                            <Field label="No. fail" htmlFor="tb-f">
                                <input id="tb-f" className={inputClass} value={vault.no_fail ?? ''} onChange={(e) => setVault({ ...vault, no_fail: e.target.value })} />
                            </Field>
                        </>
                    )}
                    <div className="sm:col-span-2 lg:col-span-4 flex justify-end">
                        <Button type="submit" size="sm" disabled={terima.isPending || !a.semak_lengkap || !a.no_resit}>
                            Daftar hak milik kekal
                        </Button>
                    </div>
                </form>
            </Card>
        </>
    );
}

/** SRS-HKM-16 (acceptance #7): one application tracked from registration to the new title. */
export default function PermohonanDetailPage() {
    const { id } = useParams<{ id: string }>();
    const { data: a, isLoading, error } = useQuery({ queryKey: ['hkm', 'permohonan', id], queryFn: () => hkmApi.satuPermohonan(id) });

    if (isLoading) return <PageContainer><Kosong>Memuatkan…</Kosong></PageContainer>;
    if (error || !a) return <PageContainer><Alert type="error">{error ? ralat(error) : 'Permohonan tidak ditemui.'}</Alert></PageContainer>;

    return (
        <PageContainer>
            <Card title={`Permohonan ${a.no_permohonan}`} actions={<Badge variant={a.status_kelulusan === 'selesai' ? 'success' : 'info'}>{a.status_kelulusan_nama}</Badge>}>
                <InfoGrid
                    items={[
                        ['Hak milik sementara', <Link key="s" className="text-txt-primary hover:underline" href={`/admin/hkm/hak-milik/${a.hak_milik_sementara_id}`}>{a.no_hak_milik_sementara}</Link>],
                        ['Hak milik baharu', a.hak_milik_baharu_id ? <Link key="b" className="text-txt-primary hover:underline" href={`/admin/hkm/hak-milik/${a.hak_milik_baharu_id}`}>{a.no_hak_milik_baharu}</Link> : '—'],
                        ['Tarikh permohonan', tarikh(a.tarikh_permohonan)],
                        ['Pejabat tanah', a.pejabat_tanah],
                        ['Peringkat semasa', a.peringkat_semasa_nama],
                        ['Tempoh proses', `${a.tempoh_proses_hari} hari`],
                        ['Bayaran', a.no_resit ? `RM ${Number(a.jumlah_bayaran ?? 0).toLocaleString('ms-MY', { minimumFractionDigits: 2 })} · ${a.no_resit} · ${tarikh(a.tarikh_bayaran)}` : 'Belum direkodkan'],
                        ['Diterima', tarikh(a.tarikh_terima_hak_milik_baharu)],
                        ['Senarai semak', a.semak_lengkap ? 'Lengkap' : 'Belum lengkap'],
                    ]}
                />
            </Card>

            <Card title="Sejarah peringkat">
                <Timeline
                    items={(a.sejarah_peringkat ?? []).map((s, i) => ({
                        title: `${i + 1}. ${s.peringkat_nama} — ${s.status}`,
                        time: tarikh(s.tarikh),
                        description: [s.catatan, s.pegawai].filter(Boolean).join(' · ') || undefined,
                        color: s.status === 'ditolak' || s.status === 'dibatalkan' ? 'danger' : s.status === 'selesai' ? 'success' : 'primary',
                    }))}
                />
            </Card>

            <Tindakan a={a} />
        </PageContainer>
    );
}
