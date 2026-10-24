'use client';

import { Suspense, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button, LinkButton } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { ApiError } from '@/lib/api';
import { hkmApi, ralat, useCan, useRujukan } from '@/lib/hkm';
import { Card, Field, inputClass, Select } from '../../_components/common';

const UNIT = [
    { kod: 'm2', nama: 'Meter persegi' },
    { kod: 'hektar', nama: 'Hektar' },
    { kod: 'ekar', nama: 'Ekar' },
    { kod: 'kaki_persegi', nama: 'Kaki persegi' },
];

function Borang() {
    const router = useRouter();
    const params = useSearchParams();
    const can = useCan();
    const { data: ref } = useRujukan();
    const [v, setV] = useState<Record<string, string>>({ lot_id: params.get('lot') ?? '', unit_keluasan: 'm2', status_semasa: 'aktif' });
    const [vault, setVault] = useState<Record<string, string>>({});
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    const set = (k: string) => (value: string) => setV((p) => ({ ...p, [k]: value }));
    const jenis = ref?.jenis_hak_milik.find((j) => j.kod === v.jenis_hak_milik);

    const save = useMutation({
        mutationFn: () =>
            hkmApi.daftar({
                ...Object.fromEntries(Object.entries(v).filter(([, x]) => x !== '')),
                ...(vault.peti_besi_id ? { peti_besi: { ...vault, peti_besi_id: Number(vault.peti_besi_id) } } : {}),
            }),
        onSuccess: (r) => {
            toast.success(r.message);
            router.push(`/admin/hkm/hak-milik/${r.data.id}`);
        },
        onError: (e) => {
            setErrors(e instanceof ApiError ? (e.errors ?? {}) : {});
            toast.error(ralat(e));
        },
    });

    const err = (k: string) => errors[k]?.[0];
    const text = (k: string, label: string, type = 'text', help?: string) => (
        <Field label={label} htmlFor={`d-${k}`} help={err(k) ?? help}>
            <input id={`d-${k}`} type={type} className={inputClass} value={v[k] ?? ''} onChange={(e) => set(k)(e.target.value)} aria-invalid={!!err(k)} />
        </Field>
    );

    return (
        <PageContainer>
            <form
                className="space-y-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    save.mutate();
                }}
            >
                <Card title="Butiran hak milik (SRS-HKM-01/02)">
                    {Object.keys(errors).length > 0 && <Alert type="error">Sila semak medan yang bertanda.</Alert>}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {text('lot_id', 'ID lot (daripada Profil Hartanah)', 'text', 'Daerah, mukim dan keluasan lalai diambil daripada rekod lot.')}
                        {text('no_hak_milik', 'Nombor hak milik')}
                        <Field label="Jenis hak milik" htmlFor="d-jenis" help={err('jenis_hak_milik')}>
                            <Select id="d-jenis" value={v.jenis_hak_milik ?? ''} onChange={set('jenis_hak_milik')} options={ref?.jenis_hak_milik ?? []} empty="Pilih…" required />
                        </Field>
                        <Field label="Kategori tanah" htmlFor="d-kat" help={err('kategori_tanah')}>
                            <Select id="d-kat" value={v.kategori_tanah ?? ''} onChange={set('kategori_tanah')} options={ref?.kategori_tanah ?? []} empty="Ikut rekod lot" />
                        </Field>
                        {text('pemilik_berdaftar', 'Pemilik berdaftar')}
                        <Field label="Status semasa" htmlFor="d-status" help={err('status_semasa')}>
                            <Select id="d-status" value={v.status_semasa ?? ''} onChange={set('status_semasa')} options={(ref?.status_tanah ?? []).filter((s) => s.aktif)} />
                        </Field>
                        {text('keluasan', 'Keluasan', 'number')}
                        <Field label="Unit keluasan" htmlFor="d-unit">
                            <Select id="d-unit" value={v.unit_keluasan ?? 'm2'} onChange={set('unit_keluasan')} options={UNIT} />
                        </Field>
                        {text('tarikh_pendaftaran', 'Tarikh pendaftaran', 'date')}
                        {text('tarikh_tamat_tempoh', 'Tarikh tamat tempoh', 'date', jenis?.sementara ? 'Wajib bagi TOL; digunakan untuk peringatan 180/90/30 hari.' : 'Bagi pajakan / hak milik sementara.')}
                        {text('tempoh_pajakan_tahun', 'Tempoh pajakan (tahun)', 'number')}
                        {text('pejabat_pendaftaran', 'Pejabat pendaftaran')}
                        {text('no_perserahan', 'No. perserahan')}
                        {text('tarikh_perserahan', 'Tarikh perserahan', 'date')}
                        <Field label="Syarat nyata" htmlFor="d-syarat" className="sm:col-span-2">
                            <textarea id="d-syarat" rows={3} className={inputClass} value={v.syarat_nyata ?? ''} onChange={(e) => set('syarat_nyata')(e.target.value)} />
                        </Field>
                    </div>
                </Card>

                {can('hkm.peti_besi.cipta') && (
                    <Card title="Dokumen asal ke peti besi (pilihan, SRS-HKM-03)">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <Field label="Peti besi" htmlFor="v-pb">
                                <Select
                                    id="v-pb"
                                    value={vault.peti_besi_id ?? ''}
                                    onChange={(x) => setVault((p) => ({ ...p, peti_besi_id: x }))}
                                    options={(ref?.peti_besi ?? []).map((p) => ({ kod: String(p.id), nama: `${p.kod_peti_besi} — ${p.nama}` }))}
                                    empty="Tidak sekarang"
                                />
                            </Field>
                            {(['lokasi_rak', 'no_fail', 'no_kotak_pengikat'] as const).map((k) => (
                                <Field key={k} label={{ lokasi_rak: 'Lokasi rak', no_fail: 'No. fail', no_kotak_pengikat: 'No. kotak / pengikat' }[k]} htmlFor={`v-${k}`}>
                                    <input id={`v-${k}`} className={inputClass} value={vault[k] ?? ''} onChange={(e) => setVault((p) => ({ ...p, [k]: e.target.value }))} />
                                </Field>
                            ))}
                        </div>
                    </Card>
                )}

                <div className="flex flex-wrap justify-end gap-2">
                    <LinkButton variant="outline" href="/admin/hkm/hak-milik">
                        Batal
                    </LinkButton>
                    <Button type="submit" disabled={save.isPending}>
                        {save.isPending ? 'Menyimpan…' : 'Daftar hak milik'}
                    </Button>
                </div>
            </form>
        </PageContainer>
    );
}

export default function DaftarHakMilikPage() {
    return (
        <Suspense>
            <Borang />
        </Suspense>
    );
}
