'use client';

import { useEffect } from 'react';
import Link from 'next/link';
import { useParams, useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { ASAL_LABEL, jenisLabel, kbaApi, tarikhMasa } from '@/lib/kba';
import { Card, KlasifikasiBadge, Memuat, RalatAkses } from '../../_components/common';

/** SRS-KBA-04: a cited source opened in full at the cited section — only the segments this user may read. */
export default function SumberPaparanPage() {
    const { id } = useParams<{ id: string }>();
    const segmen = useSearchParams().get('segmen');
    const { data, error, isLoading } = useQuery({ queryKey: ['kba', 'kandungan', id, segmen], queryFn: () => kbaApi.kandungan(id, segmen).then((r) => r.data) });

    useEffect(() => {
        if (data?.segmen_fokus) {
            document.getElementById(`segmen-${data.segmen_fokus}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, [data]);

    if (error) {
        return (
            <PageContainer>
                <RalatAkses error={error} />
            </PageContainer>
        );
    }
    if (isLoading || !data) {
        return (
            <PageContainer>
                <Memuat />
            </PageContainer>
        );
    }

    const aktif = data.segmen.filter((s) => !s.lapuk);
    const lapuk = data.segmen.filter((s) => s.lapuk);

    return (
        <PageContainer>
            <Card
                title={data.tajuk}
                actions={
                    <>
                        {data.pautan_dms && (
                            <Link href={data.pautan_dms} className="text-body-sm font-medium text-txt-primary hover:underline">
                                Buka fail asal dalam DMS
                            </Link>
                        )}
                        {data.pautan_artikel && (
                            <Link href={data.pautan_artikel} className="text-body-sm font-medium text-txt-primary hover:underline">
                                Buka artikel
                            </Link>
                        )}
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2 text-body-sm text-txt-black-700">
                    <KlasifikasiBadge k={data.klasifikasi} />
                    <span>Versi {data.versi}</span>
                    <span>· {jenisLabel(data.jenis_dokumen)}</span>
                    <span>· {ASAL_LABEL[data.asal] ?? data.asal}</span>
                    {data.modul && <span>· Modul {data.modul.toUpperCase()}</span>}
                    <span>· Diindeks {tarikhMasa(data.tarikh_indeks_akhir)}</span>
                </div>
                {lapuk.length > 0 && (
                    <Alert type="warning">
                        Petikan yang dirujuk adalah daripada versi lama sumber ini (indeks v{lapuk[0].no_versi}). Kandungan semasa dipaparkan di bawah.
                    </Alert>
                )}
            </Card>

            {lapuk.map((s) => (
                <section key={s.id} id={`segmen-${s.id}`} className="rounded-lg border-2 border-otl-warning-200 bg-bg-warning-50 p-4 sm:p-6 space-y-2 scroll-mt-24">
                    <p className="text-body-xs font-semibold text-txt-warning">Versi lama · {s.seksyen ?? '—'}</p>
                    <div className="whitespace-pre-wrap break-words text-body-sm text-txt-black-900">{s.kandungan}</div>
                </section>
            ))}

            <article className="rounded-lg border border-otl-gray-200 bg-bg-white p-4 sm:p-6 space-y-4">
                {aktif.map((s, i) => {
                    const heading = i === 0 || aktif[i - 1].laluan !== s.laluan ? s.laluan : null;
                    const fokus = s.id === data.segmen_fokus;
                    return (
                        <section key={s.id} id={`segmen-${s.id}`} className={`scroll-mt-24 space-y-2 rounded-md p-2 ${fokus ? 'bg-bg-primary-50 ring-2 ring-otl-primary-200' : ''}`}>
                            {heading && <h3 className="font-heading text-body-md font-semibold text-txt-black-900 break-words">{heading}</h3>}
                            {s.klasifikasi !== data.klasifikasi && <KlasifikasiBadge k={s.klasifikasi} />}
                            <div className="whitespace-pre-wrap break-words text-body-sm text-txt-black-800 overflow-x-auto">{s.kandungan}</div>
                        </section>
                    );
                })}
            </article>
        </PageContainer>
    );
}
