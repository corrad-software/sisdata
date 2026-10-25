'use client';

import { useState, type FormEvent } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { JENIS_DOKUMEN, jenisLabel, kbaApi } from '@/lib/kba';
import { Card, Field, inputClass, KlasifikasiBadge, Kosong, Memuat, RalatAkses, Select } from '../_components/common';

const KOSONG = { q: '', modul: '', jenis_dokumen: '', versi: '', dari: '', hingga: '' };

/** SRS-KBA-10: conventional keyword search with filters, as an alternative to the AI prompt. */
export default function CarianPage() {
    const [form, setForm] = useState(KOSONG);
    const [query, setQuery] = useState<typeof KOSONG | null>(null);
    const { data, error, isFetching } = useQuery({
        queryKey: ['kba', 'carian', query],
        queryFn: () => kbaApi.carian(query ?? KOSONG).then((r) => r.data),
        enabled: query !== null && query.q.trim().length >= 2,
    });
    const set = (k: keyof typeof KOSONG) => (v: string) => setForm((f) => ({ ...f, [k]: v }));
    const submit = (e: FormEvent) => {
        e.preventDefault();
        setQuery({ ...form });
    };

    return (
        <PageContainer>
            <Card title="Carian kata kunci">
                <form onSubmit={submit} className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
                    <Field label="Kata kunci" htmlFor="c-q" className="sm:col-span-2">
                        <input id="c-q" className={inputClass} value={form.q} onChange={(e) => set('q')(e.target.value)} required minLength={2} placeholder="cth. retensi audit" />
                    </Field>
                    <Field label="Modul" htmlFor="c-modul">
                        <input id="c-modul" className={inputClass} value={form.modul} onChange={(e) => set('modul')(e.target.value)} maxLength={3} placeholder="cth. dms" />
                    </Field>
                    <Field label="Jenis dokumen" htmlFor="c-jenis">
                        <Select id="c-jenis" value={form.jenis_dokumen} onChange={set('jenis_dokumen')} options={JENIS_DOKUMEN} empty="Semua" />
                    </Field>
                    <Field label="Diindeks dari" htmlFor="c-dari">
                        <input id="c-dari" type="date" className={inputClass} value={form.dari} onChange={(e) => set('dari')(e.target.value)} />
                    </Field>
                    <Field label="Hingga" htmlFor="c-hingga">
                        <input id="c-hingga" type="date" className={inputClass} value={form.hingga} onChange={(e) => set('hingga')(e.target.value)} />
                    </Field>
                    <div className="flex items-end gap-2 sm:col-span-2 lg:col-span-6">
                        <Button type="submit">Cari</Button>
                        <Button
                            variant="secondary"
                            onClick={() => {
                                setForm(KOSONG);
                                setQuery(null);
                            }}
                        >
                            Set semula
                        </Button>
                    </div>
                </form>
            </Card>

            {error ? (
                <RalatAkses error={error} />
            ) : isFetching ? (
                <Memuat />
            ) : data ? (
                <Card title={`${data.length} hasil`}>
                    {data.length === 0 ? (
                        <Kosong>Tiada padanan dalam sumber yang anda dibenarkan baca.</Kosong>
                    ) : (
                        <ul className="divide-y divide-otl-divider">
                            {data.map((h) => (
                                <li key={h.id_segmen} className="py-3 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Link href={h.pautan} className="font-medium text-txt-primary hover:underline break-words">
                                            {h.dokumen}
                                        </Link>
                                        <span className="text-body-xs text-txt-black-500">
                                            {h.versi}
                                            {h.seksyen ? ` · ${h.seksyen}` : ''} · {jenisLabel(h.jenis_dokumen)}
                                            {h.modul ? ` · ${h.modul.toUpperCase()}` : ''}
                                        </span>
                                        {h.klasifikasi !== 'dalaman' && <KlasifikasiBadge k={h.klasifikasi} />}
                                    </div>
                                    <p className="text-body-sm text-txt-black-700 break-words">{h.petikan}</p>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            ) : null}
        </PageContainer>
    );
}
