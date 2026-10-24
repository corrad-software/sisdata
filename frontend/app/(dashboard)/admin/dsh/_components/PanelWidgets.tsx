'use client';

import Link from 'next/link';
import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { PetaSisdata } from '@/components/gis/PetaSisdata';
import { Badge } from '@/components/ui/Badge';
import { Spinner } from '@/components/ui/Spinner';
import { useKatalog } from '@/lib/gis';
import { dshApi, fmtMasa, type Aras, type Insight, type Penapis } from '@/lib/dsh';

/** Thematic layers the dashboard map can switch between (SRS-DSH-04). */
const TEMA = [
    { kod: 'gis.lot', label: 'Lot tanah' },
    { kod: 'cki.status_cukai', label: 'Status cukai' },
    { kod: 'pjk.pajakan', label: 'Status pajakan' },
    { kod: 'gis.risiko', label: 'Risiko hartanah' },
];

/**
 * Portfolio map (SRS-DSH-03/04): the shared <PetaSisdata> with MOD-GIS
 * layers (zoom, pan, click for lot attributes + link to the profile), a
 * thematic layer switch with legend, filtered by the same daerah/mukim as
 * every other widget.
 */
export function PetaWidget({ p }: { p: Penapis }) {
    const { data: katalog } = useKatalog();
    const available = useMemo(() => new Set((katalog?.lapisan ?? []).map((l) => l.kod)), [katalog]);
    const options = TEMA.filter((t) => available.has(t.kod));
    const [tema, setTema] = useState<string | null>(null);
    const active = tema && available.has(tema) ? tema : (options[0]?.kod ?? 'gis.lot');
    const layers = ['gis.sempadan_daerah', active].filter((k, i, a) => a.indexOf(k) === i);
    const filters = useMemo(() => {
        const f: Record<string, string> = {};
        if (p.kod_daerah) f.kod_daerah = p.kod_daerah;
        if (p.kod_mukim) f.kod_mukim = p.kod_mukim;
        return Object.keys(f).length ? Object.fromEntries(TEMA.map((t) => [t.kod, f])) : undefined;
    }, [p.kod_daerah, p.kod_mukim]);

    return (
        <article className="rounded-lg border border-otl-gray-200 bg-bg-white p-4 shadow-card" aria-labelledby="w-peta">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 id="w-peta" className="text-body-sm font-medium text-txt-black-700">
                    Peta portfolio hartanah
                </h3>
                <div role="radiogroup" aria-label="Lapisan tematik" className="flex flex-wrap gap-1">
                    {options.map((t) => (
                        <button
                            key={t.kod}
                            type="button"
                            role="radio"
                            aria-checked={active === t.kod}
                            onClick={() => setTema(t.kod)}
                            className={`rounded-full border px-3 py-1 text-body-xs ${active === t.kod ? 'border-primary-600 bg-primary-50 text-txt-primary' : 'border-otl-gray-300 text-txt-black-700 hover:bg-bg-washed'}`}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>
            </div>
            <PetaSisdata layers={layers} filters={filters} height={360} label="Peta portfolio hartanah dashboard" />
            <p className="mt-2 text-body-xs text-txt-black-500">
                Klik lot untuk atribut dan pautan ke Profil Hartanah 360°. <Link href="/admin/gis" className="text-txt-primary hover:underline">Buka Peta GIS penuh</Link>
            </p>
        </article>
    );
}

const TAHAP: Record<Insight['tahap'], { variant: 'danger' | 'warning' | 'info' | 'success'; label: string }> = {
    kritikal: { variant: 'danger', label: 'Kritikal' },
    perhatian: { variant: 'warning', label: 'Perhatian' },
    maklumat: { variant: 'info', label: 'Maklumat' },
    baik: { variant: 'success', label: 'Baik' },
};

/** Automatic observations from the local InsightGenerator (rule-based by default, SRS-DSH-18). */
export function InsightWidget({ aras, p }: { aras: Aras; p: Penapis }) {
    const q = useQuery({ queryKey: ['dsh', 'insight', aras, p], queryFn: () => dshApi.insight(aras, p), staleTime: 60_000 });
    return (
        <article className="h-full rounded-lg border border-otl-gray-200 bg-bg-white p-4 shadow-card" aria-labelledby="w-insight">
            <div className="flex items-center justify-between gap-2">
                <h3 id="w-insight" className="text-body-sm font-medium text-txt-black-700">
                    Pemerhatian automatik
                </h3>
                {q.data && <span className="text-[11px] text-txt-black-500">Enjin: {q.data.enjin === 'peraturan' ? 'peraturan tempatan' : q.data.enjin}</span>}
            </div>
            {q.isLoading ? (
                <div className="py-6"><Spinner /></div>
            ) : (
                <ul className="mt-3 space-y-2">
                    {(q.data?.data ?? []).map((i, n) => (
                        <li key={n} className="flex items-start gap-2 text-body-sm text-txt-black-900">
                            <Badge variant={TAHAP[i.tahap].variant}>{TAHAP[i.tahap].label}</Badge>
                            <span>{i.teks}</span>
                        </li>
                    ))}
                </ul>
            )}
        </article>
    );
}

const SLA: Record<string, { variant: 'danger' | 'warning' | 'success' | 'neutral'; label: string }> = {
    lewat: { variant: 'danger', label: 'Lewat SLA' },
    hampir: { variant: 'warning', label: 'Hampir tamat' },
    dalam_tempoh: { variant: 'success', label: 'Dalam tempoh' },
    tiada: { variant: 'neutral', label: 'Tiada SLA' },
};

export function SlaBadge({ status }: { status: string }) {
    const s = SLA[status] ?? SLA.tiada;
    return <Badge variant={s.variant}>{s.label}</Badge>;
}

/** Task inbox summary (SRS-DSH-23) on the Operasi / Pengurusan levels. */
export function TugasanWidget() {
    const q = useQuery({ queryKey: ['dsh', 'inbox'], queryFn: dshApi.inbox, staleTime: 30_000 });
    const tasks = q.data?.tugasan ?? [];
    return (
        <article className="h-full rounded-lg border border-otl-gray-200 bg-bg-white p-4 shadow-card" aria-labelledby="w-tugasan">
            <div className="flex items-center justify-between gap-2">
                <h3 id="w-tugasan" className="text-body-sm font-medium text-txt-black-700">
                    Inbox tugasan
                </h3>
                <Link href="/admin/dsh/inbox" className="text-body-xs text-txt-primary hover:underline">
                    Lihat semua
                </Link>
            </div>
            {q.data && (
                <p className="mt-1 text-body-xs text-txt-black-500">
                    {q.data.ringkasan.tugasan} tugasan · {q.data.ringkasan.lewat} lewat · {q.data.ringkasan.notifikasi} notifikasi belum dibaca
                </p>
            )}
            {q.isLoading ? (
                <div className="py-6"><Spinner /></div>
            ) : tasks.length === 0 ? (
                <p className="mt-3 text-body-sm text-txt-black-500">Tiada tugasan menunggu tindakan anda.</p>
            ) : (
                <ul className="mt-3 divide-y divide-otl-divider">
                    {tasks.slice(0, 6).map((t) => (
                        <li key={t.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                            <Link href={t.pautan} className="min-w-0 flex-1 truncate text-body-sm text-txt-black-900 hover:text-txt-primary hover:underline">
                                {t.tajuk}
                            </Link>
                            <span className="flex items-center gap-2 text-body-xs text-txt-black-500">
                                {fmtMasa(t.tarikh_akhir)} <SlaBadge status={t.status_sla} />
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </article>
    );
}
