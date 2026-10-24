'use client';

import { Suspense, use } from 'react';
import { useQuery } from '@tanstack/react-query';
import { notFound } from 'next/navigation';
import { PageContainer } from '@/components/PageContainer';
import { PetaSisdata } from '@/components/gis/PetaSisdata';
import { Alert } from '@/components/ui/Alert';
import { Spinner } from '@/components/ui/Spinner';
import { dshApi, errorMessage, fmtMasa, useCan, usePenapis, type Kpi } from '@/lib/dsh';
import { PenapisBar } from '../../_components/PenapisBar';
import { KpiWidget, LampuWidget } from '../../_components/Widgets';

/**
 * Theme dashboards built from the registered KPIs of the owning modules:
 * cukai (SRS-DSH-05, MOD-CKI), sewaan/pajakan (SRS-DSH-06, MOD-PJK) and
 * pemantauan tanah (SRS-DSH-07, MOD-PTN + site issues from MOD-ADU).
 */
const TEMA: Record<string, { tajuk: string; modul: string[]; lapisan: string; keterangan: string }> = {
    cukai: { tajuk: 'Dashboard Cukai', modul: ['cki'], lapisan: 'cki.status_cukai', keterangan: 'Cukai dibayar, tertunggak, hampir jatuh tempo, penalti dan trend bayaran mengikut tahun dipilih.' },
    pajakan: { tajuk: 'Dashboard Sewaan / Pajakan', modul: ['pjk'], lapisan: 'pjk.pajakan', keterangan: 'Premis dan tanah kosong, pajakan aktif dan hampir tamat, tunggakan sewaan serta hasil sewaan terkumpul.' },
    pemantauan: { tajuk: 'Dashboard Pemantauan Tanah', modul: ['ptn', 'adu'], lapisan: 'gis.risiko', keterangan: 'Jadual pemantauan, isu tapak, tindakan terbuka dan tertutup serta penarafan risiko setiap lot atau kawasan.' },
};

function Tema({ tema }: { tema: string }) {
    const t = TEMA[tema];
    const [p, setP] = usePenapis();
    const can = useCan();
    const q = useQuery({ queryKey: ['dsh', 'tema', tema, p], queryFn: () => dshApi.kpi(p), placeholderData: (prev) => prev });
    if (!t) notFound();
    const kpis: Kpi[] = (q.data?.data ?? []).filter((k) => t.modul.includes(k.modul));
    const filters = p.kod_daerah ? { [t.lapisan]: { kod_daerah: p.kod_daerah } } : undefined;

    return (
        <>
            <div>
                <h1 className="font-heading text-heading-xs font-semibold text-txt-black-900">{t.tajuk}</h1>
                <p className="mt-1 text-body-sm text-txt-black-500">{t.keterangan}</p>
                {q.data && <p className="mt-1 text-body-xs text-txt-black-500">Data dikira pada {fmtMasa(q.data.dikira_pada)}</p>}
            </div>
            <PenapisBar value={p} onChange={setP} showModul={false} />
            {q.isError && <Alert type="error">{errorMessage(q.error)}</Alert>}
            {q.isLoading ? (
                <div className="flex justify-center py-10">
                    <Spinner />
                </div>
            ) : kpis.length === 0 ? (
                <Alert type="info">Tiada indikator tersedia untuk anda bagi tema ini (modul sumber belum mendaftarkan indikator, atau anda tiada kebenaran laporan modul tersebut).</Alert>
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div className="sm:col-span-2">
                        <LampuWidget kpis={kpis} p={p} />
                    </div>
                    {kpis.map((k) => (
                        <div key={k.key} className="min-w-0">
                            <KpiWidget k={k} p={p} visual="auto" />
                        </div>
                    ))}
                </div>
            )}
            {can('gis.peta.lihat') && (
                <section className="rounded-lg border border-otl-gray-200 bg-bg-white p-4 shadow-card">
                    <h2 className="mb-3 text-body-sm font-medium text-txt-black-700">Peta tematik</h2>
                    <PetaSisdata layers={['gis.sempadan_daerah', t.lapisan]} filters={filters} height={360} label={`Peta ${t.tajuk}`} />
                </section>
            )}
        </>
    );
}

export default function TemaPage({ params }: { params: Promise<{ tema: string }> }) {
    const { tema } = use(params);
    return (
        <PageContainer>
            <Suspense fallback={<Spinner />}>
                <Tema tema={tema} />
            </Suspense>
        </PageContainer>
    );
}
