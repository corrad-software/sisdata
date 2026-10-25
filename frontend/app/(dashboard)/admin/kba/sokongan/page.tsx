'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { kbaApi, tarikhMasa, type SokonganItem } from '@/lib/kba';
import { Card, Field, Kosong, Memuat, RalatAkses, Select } from '../_components/common';

function Senarai({ items }: { items: SokonganItem[] }) {
    if (!items.length) return <Kosong>Tiada kandungan.</Kosong>;
    return (
        <ul className="divide-y divide-otl-divider">
            {items.map((i) => (
                <li key={i.id_segmen} className="py-2 space-y-0.5">
                    <Link href={i.pautan} className="text-body-sm font-medium text-txt-primary hover:underline break-words">
                        {i.dokumen} · {i.seksyen ?? '—'}
                    </Link>
                    <p className="text-body-xs text-txt-black-500 break-words">{i.ringkasan}</p>
                </li>
            ))}
        </ul>
    );
}

/** SRS-KBA-09: support-team view per module — design, integration, parameters, problem history. */
export default function SokonganPage() {
    const [modul, setModul] = useState('');
    const { data: modules, error } = useQuery({ queryKey: ['kba', 'sokongan'], queryFn: () => kbaApi.sokongan().then((r) => r.data) });
    const { data, isFetching } = useQuery({ queryKey: ['kba', 'sokongan', modul], queryFn: () => kbaApi.sokonganModul(modul).then((r) => r.data), enabled: modul !== '' });

    if (error) {
        return (
            <PageContainer>
                <RalatAkses error={error} />
            </PageContainer>
        );
    }
    return (
        <PageContainer>
            <Field label="Modul" htmlFor="sk-modul" className="w-full sm:w-80" help="Modul dikenal pasti daripada kod MOD-XXX dalam dokumen reka bentuk dan metadata sumber/artikel.">
                <Select
                    id="sk-modul"
                    value={modul}
                    onChange={setModul}
                    empty="Pilih modul…"
                    options={(modules ?? []).map((m) => ({ value: m.modul, label: `${m.modul.toUpperCase()} (${m.segmen} segmen, ${m.artikel} artikel)` }))}
                />
            </Field>
            {modul === '' ? null : isFetching || !data ? (
                <Memuat />
            ) : (
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <Card title="Reka bentuk sistem">
                        <Senarai items={data.reka_bentuk} />
                    </Card>
                    <Card title="Aliran integrasi">
                        <Senarai items={data.integrasi} />
                    </Card>
                    <Card title="Parameter konfigurasi dirujuk">
                        {data.parameter.length === 0 ? (
                            <Kosong>Tiada parameter dirujuk dalam dokumen.</Kosong>
                        ) : (
                            <ul className="flex flex-wrap gap-2">
                                {data.parameter.map((p) => (
                                    <li key={p.kunci}>
                                        <Link href={p.pautan} className="rounded bg-bg-washed px-2 py-1 font-mono text-body-xs text-txt-black-700 hover:underline">
                                            {p.kunci}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                    <Card title="Sejarah masalah (artikel penyelesaian)">
                        {data.sejarah_masalah.length === 0 ? (
                            <Kosong>Tiada artikel diterbitkan untuk modul ini.</Kosong>
                        ) : (
                            <ul className="divide-y divide-otl-divider">
                                {data.sejarah_masalah.map((a) => (
                                    <li key={a.id} className="py-2 text-body-sm">
                                        <Link href={a.pautan} className="font-medium text-txt-primary hover:underline break-words">
                                            {a.tajuk}
                                        </Link>
                                        <div className="text-body-xs text-txt-black-500">
                                            {tarikhMasa(a.tarikh_terbit)}
                                            {a.versi_sistem ? ` · versi ${a.versi_sistem}` : ''}
                                            {a.pautan_tiket && (
                                                <>
                                                    {' · '}
                                                    <Link href={a.pautan_tiket} className="hover:underline">
                                                        tiket {a.id_tiket_asal}
                                                    </Link>
                                                </>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                        {data.soalan_tanpa_jawapan.length > 0 && (
                            <div className="border-t border-otl-divider pt-3">
                                <p className="mb-1 text-body-xs font-semibold text-txt-black-500">Soalan tanpa jawapan bagi modul ini</p>
                                <ul className="list-disc pl-5 text-body-sm">
                                    {data.soalan_tanpa_jawapan.map((s) => (
                                        <li key={s.soalan}>
                                            {s.soalan} ({s.bilangan})
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </Card>
                </div>
            )}
        </PageContainer>
    );
}
