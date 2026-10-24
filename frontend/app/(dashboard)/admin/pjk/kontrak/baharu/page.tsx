'use client';

import { useEffect, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { pjkApi, ralat, useRujukan } from '@/lib/pjk';
import { Card, Field, inputClass, Select } from '../../_components/common';

const DRAF_KEY = 'pjk.kontrak.draf';

/** SRS-PJK-01: register an agreement as a draft (auto-saved locally, SRS-GEN-07), then submit for WFE approval. */
export default function KontrakBaharuPage() {
    const router = useRouter();
    const search = useSearchParams();
    const { data: ref } = useRujukan();
    const [form, setForm] = useState<Record<string, string>>({ jenis_perjanjian: 'sewaan', kitaran_caj: 'bulanan', premis_id: search.get('premis') ?? '' });
    const [loaded, setLoaded] = useState(false);
    const [qPremis, setQPremis] = useState('');
    const [qPenyewa, setQPenyewa] = useState('');
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<string | null>(null);

    // SRS-GEN-07: the draft survives a lost session (restored once, then saved on every change).
    useEffect(() => {
        try {
            const saved = window.localStorage.getItem(DRAF_KEY);
            // eslint-disable-next-line react-hooks/set-state-in-effect -- one-time restore from storage after mount
            if (saved) setForm((p) => ({ ...p, ...(JSON.parse(saved) as Record<string, string>) }));
        } catch {
            /* storage unavailable */
        }
        setLoaded(true);
    }, []);

    useEffect(() => {
        if (!loaded) return;
        try {
            window.localStorage.setItem(DRAF_KEY, JSON.stringify(form));
        } catch {
            /* storage unavailable */
        }
    }, [form, loaded]);

    const premis = useQuery({ queryKey: ['pjk', 'premis-pilih', qPremis], queryFn: () => pjkApi.premis({ q: qPremis, status_premis: 'kosong', per_page: 20 }) });
    const penyewa = useQuery({ queryKey: ['pjk', 'penyewa-pilih', qPenyewa], queryFn: () => pjkApi.penyewa({ q: qPenyewa, per_page: 20 }) });
    const set = (k: string, v: string) => setForm((p) => ({ ...p, [k]: v }));

    async function save(submit: boolean) {
        setBusy(true);
        setErrors(null);
        try {
            const res = await pjkApi.kontrakCipta(form);
            if (submit) await pjkApi.hantar(res.data.id);
            window.localStorage.removeItem(DRAF_KEY);
            toast.success(submit ? 'Perjanjian didaftarkan dan dihantar untuk kelulusan.' : (res.message ?? 'Disimpan.'));
            router.push(`/admin/pjk/kontrak/${res.data.id}`);
        } catch (e) {
            setErrors(ralat(e));
            toast.error(ralat(e));
        } finally {
            setBusy(false);
        }
    }

    return (
        <PageContainer>
            <Card title="Premis dan penyewa">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div className="space-y-2">
                        <Field label="Cari premis kosong" htmlFor="b-qpremis">
                            <input id="b-qpremis" type="search" className={inputClass} value={qPremis} onChange={(e) => setQPremis(e.target.value)} placeholder="Nama atau kod premis" />
                        </Field>
                        <Field label="Premis" htmlFor="b-premis">
                            <select id="b-premis" className={inputClass} value={form.premis_id ?? ''} onChange={(e) => set('premis_id', e.target.value)} required>
                                <option value="">— Pilih premis —</option>
                                {(premis.data?.data ?? []).map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.kod_premis} · {p.nama_premis} ({p.kategori_hartanah_nama}, {p.kadar_tawaran.rm})
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </div>
                    <div className="space-y-2">
                        <Field label="Cari penyewa" htmlFor="b-qpenyewa">
                            <input id="b-qpenyewa" type="search" className={inputClass} value={qPenyewa} onChange={(e) => setQPenyewa(e.target.value)} placeholder="Nama atau no. pengenalan" />
                        </Field>
                        <Field label="Penyewa" htmlFor="b-penyewa" help="Daftar penyewa baharu di tab Penyewa jika belum wujud.">
                            <select id="b-penyewa" className={inputClass} value={form.penyewa_id ?? ''} onChange={(e) => set('penyewa_id', e.target.value)} required>
                                <option value="">— Pilih penyewa —</option>
                                {(penyewa.data?.data ?? []).map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.nama} ({p.no_pengenalan ?? p.no_pendaftaran_syarikat ?? '—'})
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </div>
                </div>
            </Card>

            <Card title="Terma perjanjian">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Field label="Jenis perjanjian" htmlFor="b-jenis">
                        <Select id="b-jenis" value={form.jenis_perjanjian ?? ''} onChange={(v) => set('jenis_perjanjian', v)} options={ref?.jenis_perjanjian ?? []} />
                    </Field>
                    <Field label="Tarikh mula" htmlFor="b-mula">
                        <input id="b-mula" type="date" className={inputClass} value={form.tarikh_mula ?? ''} onChange={(e) => set('tarikh_mula', e.target.value)} required />
                    </Field>
                    <Field label="Tarikh tamat" htmlFor="b-tamat">
                        <input id="b-tamat" type="date" className={inputClass} value={form.tarikh_tamat ?? ''} onChange={(e) => set('tarikh_tamat', e.target.value)} required />
                    </Field>
                    <Field label="Kadar sewaan sebulan (RM)" htmlFor="b-kadar">
                        <input id="b-kadar" inputMode="decimal" className={inputClass} value={form.kadar ?? ''} onChange={(e) => set('kadar', e.target.value)} placeholder="0.00" required />
                    </Field>
                    <Field label="Deposit / cagaran (RM)" htmlFor="b-deposit">
                        <input id="b-deposit" inputMode="decimal" className={inputClass} value={form.deposit ?? ''} onChange={(e) => set('deposit', e.target.value)} placeholder="0.00" />
                    </Field>
                    <Field label="Kitaran caj" htmlFor="b-kitaran">
                        <Select id="b-kitaran" value={form.kitaran_caj ?? ''} onChange={(v) => set('kitaran_caj', v)} options={ref?.kitaran_caj ?? []} />
                    </Field>
                    <Field label="Terma khas" htmlFor="b-terma" className="sm:col-span-2 lg:col-span-3">
                        <textarea id="b-terma" rows={4} className={inputClass} value={form.terma ?? ''} onChange={(e) => set('terma', e.target.value)} />
                    </Field>
                </div>
                {errors && <p className="text-body-sm text-txt-danger">{errors}</p>}
                <div className="flex flex-wrap justify-end gap-2">
                    <Button variant="secondary" disabled={busy} onClick={() => save(false)}>
                        Simpan draf
                    </Button>
                    <Button disabled={busy} onClick={() => save(true)}>
                        Simpan & hantar untuk kelulusan
                    </Button>
                </div>
            </Card>
        </PageContainer>
    );
}
