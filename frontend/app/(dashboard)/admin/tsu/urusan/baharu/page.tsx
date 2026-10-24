'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useMutation, useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { ralat, tsuApi, useRujukan, type LotCarian } from '@/lib/tsu';
import { Card, Field, inputClass, Kosong, Select } from '../../_components/common';

/** SRS-TSU-01 / process step 1: register an urusan on one or more lots; the stages come from the type's template. */
export default function DaftarUrusan() {
    const router = useRouter();
    const { data: ref } = useRujukan();
    const [jenis, setJenis] = useState('tukar_syarat');
    const [q, setQ] = useState('');
    const [lots, setLots] = useState<LotCarian[]>([]);
    const [f, setF] = useState<Record<string, string>>({ tajuk: '', keterangan: '', pejabat_tanah: '', no_rujukan_etanah: '', kaedah_pelupusan: '', justifikasi: '', kategori_guna_tanah_baharu: '', syarat_nyata_baharu: '', zoning_baharu: '', pihak_penerima: '', bilangan_lot_dicadang: '' });
    const set = (k: string) => (v: string) => setF((x) => ({ ...x, [k]: v }));
    const { data: hasil, isFetching } = useQuery({ queryKey: ['tsu', 'cari-lot', q], queryFn: () => tsuApi.cariLot(q), enabled: q.trim().length >= 1 });

    const m = useMutation({
        mutationFn: () =>
            tsuApi.daftar({
                jenis_urusan: jenis,
                lot_ids: lots.map((l) => l.id),
                tajuk: f.tajuk || undefined,
                keterangan: f.keterangan || undefined,
                pejabat_tanah: f.pejabat_tanah || undefined,
                no_rujukan_etanah: f.no_rujukan_etanah || undefined,
                kaedah_pelupusan: jenis === 'pelupusan' ? f.kaedah_pelupusan || undefined : undefined,
                justifikasi: f.justifikasi || undefined,
                butiran: {
                    kategori_guna_tanah_baharu: f.kategori_guna_tanah_baharu || undefined,
                    syarat_nyata_baharu: f.syarat_nyata_baharu || undefined,
                    zoning_baharu: f.zoning_baharu || undefined,
                    pihak_penerima: f.pihak_penerima || undefined,
                    bilangan_lot_dicadang: f.bilangan_lot_dicadang ? Number(f.bilangan_lot_dicadang) : undefined,
                },
            }),
        onSuccess: (r) => {
            toast.success(r.message);
            router.push(`/admin/tsu/urusan/${r.data.id}`);
        },
        onError: (e) => toast.error(ralat(e)),
    });

    const syarat = ['tukar_syarat', 'serah_balik_beri_milik'].includes(jenis);
    const pecah = ['pecah_sempadan', 'pecah_bahagian', 'penyatuan'].includes(jenis);

    return (
        <PageContainer>
            <form
                className="space-y-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    m.mutate();
                }}
            >
                <Card title="Jenis urusan dan lot terlibat">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Field label="Jenis urusan" htmlFor="d-jenis" help="Peringkat, dokumen wajib dan kategori kos dijana daripada templat aktif jenis ini.">
                            <Select id="d-jenis" value={jenis} onChange={setJenis} options={ref?.jenis_urusan ?? []} required />
                        </Field>
                        <Field label="Cari lot" htmlFor="d-q" help={jenis === 'penyatuan' ? 'Penyatuan memerlukan sekurang-kurangnya dua lot.' : 'Taip nombor lot, contoh "PT 1234" atau "Lot 55".'}>
                            <input id="d-q" type="search" className={inputClass} value={q} onChange={(e) => setQ(e.target.value)} placeholder="No. lot" />
                        </Field>
                    </div>
                    {q && (
                        <div className="rounded-md border border-otl-gray-200 max-h-56 overflow-y-auto divide-y divide-otl-gray-200">
                            {isFetching ? (
                                <Kosong>Mencari…</Kosong>
                            ) : !hasil?.length ? (
                                <Kosong>Tiada lot dijumpai dalam skop anda.</Kosong>
                            ) : (
                                hasil.map((l) => (
                                    <button
                                        key={l.id}
                                        type="button"
                                        disabled={lots.some((x) => x.id === l.id) || l.status_lot === 'arkib'}
                                        onClick={() => setLots((x) => [...x, l])}
                                        className="w-full text-left px-3 py-2 text-body-sm hover:bg-bg-washed disabled:opacity-50"
                                    >
                                        <span className="font-medium">{l.no_lot}</span> · mukim {l.kod_mukim} · {l.kategori_guna_tanah ?? '—'} {l.status_lot === 'arkib' && <Badge>Diarkib</Badge>}
                                    </button>
                                ))
                            )}
                        </div>
                    )}
                    <div className="flex flex-wrap gap-2">
                        {lots.length === 0 && <span className="text-body-sm text-txt-black-500">Belum ada lot dipilih.</span>}
                        {lots.map((l) => (
                            <span key={l.id} className="inline-flex items-center gap-2 rounded-full bg-bg-washed px-3 py-1 text-body-sm">
                                {l.no_lot}
                                <button type="button" aria-label={`Buang ${l.no_lot}`} className="text-txt-danger" onClick={() => setLots((x) => x.filter((y) => y.id !== l.id))}>
                                    ×
                                </button>
                            </span>
                        ))}
                    </div>
                </Card>

                <Card title="Butiran urusan">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Field label="Tajuk (pilihan)" htmlFor="d-tajuk" className="sm:col-span-2">
                            <input id="d-tajuk" className={inputClass} value={f.tajuk} onChange={(e) => set('tajuk')(e.target.value)} />
                        </Field>
                        <Field label="Pejabat Tanah" htmlFor="d-ptd">
                            <input id="d-ptd" className={inputClass} value={f.pejabat_tanah} onChange={(e) => set('pejabat_tanah')(e.target.value)} />
                        </Field>
                        <Field label="No. rujukan e-Tanah (jika ada)" htmlFor="d-et">
                            <input id="d-et" className={inputClass} value={f.no_rujukan_etanah} onChange={(e) => set('no_rujukan_etanah')(e.target.value)} />
                        </Field>
                        {syarat && (
                            <>
                                <Field label="Kategori guna tanah baharu" htmlFor="d-kat">
                                    <input id="d-kat" className={inputClass} value={f.kategori_guna_tanah_baharu} onChange={(e) => set('kategori_guna_tanah_baharu')(e.target.value)} placeholder="cth. BANGUNAN" />
                                </Field>
                                <Field label="Zoning baharu" htmlFor="d-zon">
                                    <input id="d-zon" className={inputClass} value={f.zoning_baharu} onChange={(e) => set('zoning_baharu')(e.target.value)} />
                                </Field>
                                <Field label="Syarat nyata baharu" htmlFor="d-syarat" className="sm:col-span-2">
                                    <textarea id="d-syarat" rows={2} className={inputClass} value={f.syarat_nyata_baharu} onChange={(e) => set('syarat_nyata_baharu')(e.target.value)} />
                                </Field>
                            </>
                        )}
                        {pecah && jenis !== 'penyatuan' && (
                            <Field label="Bilangan lot dicadang" htmlFor="d-bil">
                                <input id="d-bil" type="number" min={1} className={inputClass} value={f.bilangan_lot_dicadang} onChange={(e) => set('bilangan_lot_dicadang')(e.target.value)} />
                            </Field>
                        )}
                        {jenis === 'pelupusan' && (
                            <>
                                <Field label="Kaedah pelupusan" htmlFor="d-kaedah">
                                    <Select id="d-kaedah" value={f.kaedah_pelupusan} onChange={set('kaedah_pelupusan')} options={ref?.kaedah_pelupusan ?? []} empty="Pilih" required />
                                </Field>
                                <Field label="Pihak penerima" htmlFor="d-pen">
                                    <input id="d-pen" className={inputClass} value={f.pihak_penerima} onChange={(e) => set('pihak_penerima')(e.target.value)} />
                                </Field>
                                <Field label="Justifikasi bertulis" htmlFor="d-just" className="sm:col-span-2" help="Wajib sebelum kelulusan pelupusan (SRS-TSU-05). Tanah wakaf melalui semakan Bahagian Wakaf.">
                                    <textarea id="d-just" rows={3} className={inputClass} value={f.justifikasi} onChange={(e) => set('justifikasi')(e.target.value)} />
                                </Field>
                            </>
                        )}
                        <Field label="Keterangan" htmlFor="d-ket" className="sm:col-span-2">
                            <textarea id="d-ket" rows={3} className={inputClass} value={f.keterangan} onChange={(e) => set('keterangan')(e.target.value)} />
                        </Field>
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={m.isPending || lots.length === 0}>
                            Daftar urusan
                        </Button>
                    </div>
                </Card>
            </form>
        </PageContainer>
    );
}
