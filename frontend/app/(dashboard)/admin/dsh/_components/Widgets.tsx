'use client';

import Link from 'next/link';
import { BarChart } from '@/components/ui/charts/BarChart';
import { HorizontalBarChart } from '@/components/ui/charts/HorizontalBarChart';
import { LineChart } from '@/components/ui/charts/LineChart';
import { PieChart } from '@/components/ui/charts/PieChart';
import { Heatmap } from '@/components/ui/charts/Heatmap';
import { Badge } from '@/components/ui/Badge';
import {
    DOMAIN_LABEL,
    LAMPU_BADGE,
    LAMPU_KELAS,
    LAMPU_LABEL,
    fmtMasa,
    fmtNilai,
    fmtNombor,
    visualAuto,
    withPenapis,
    type Kpi,
    type Lampu,
    type Penapis,
    type Visual,
} from '@/lib/dsh';

const PIE_COLORS = ['text-primary-600', 'text-success-600', 'text-warning-500', 'text-danger-600', 'text-primary-300', 'text-txt-black-500'];

/** Where a click on the KPI value goes: the KPI's own drill page (with filters kept). */
export function kpiHref(k: Kpi, p: Penapis): string | null {
    if (k.drill) return withPenapis('/admin/dsh/senarai', p, { kpi: k.key });
    return k.pautan ? withPenapis(k.pautan, p) : null;
}

function sliceHref(k: Kpi, p: Penapis, kod?: string): string | null {
    if (!k.drill || !kod || !kod.includes(':')) return null;
    return withPenapis('/admin/dsh/senarai', p, { kpi: k.key, pecahan: kod });
}

export function LampuDot({ lampu, className = '' }: { lampu: Lampu | null; className?: string }) {
    if (!lampu) return null;
    return (
        <span
            role="img"
            aria-label={`Lampu isyarat: ${LAMPU_LABEL[lampu]}`}
            title={LAMPU_LABEL[lampu]}
            className={`inline-block h-3 w-3 flex-shrink-0 rounded-full ring-2 ring-bg-white ${LAMPU_KELAS[lampu]} ${className}`}
        />
    );
}

function Kesegaran({ k }: { k: Kpi }) {
    return (
        <p className="mt-3 text-[11px] text-txt-black-500">
            Data dikira pada {fmtMasa(k.dikira_pada)}
            {k.data_terakhir ? ` · rekod terkini ${fmtMasa(k.data_terakhir)}` : ''}
        </p>
    );
}

function Progress({ k }: { k: Kpi }) {
    const target = k.sasaran ?? 100;
    const pct = Math.max(0, Math.min(100, ((k.nilai ?? 0) / (k.unit === '%' ? 100 : target || 1)) * 100));
    const bar = k.lampu ? LAMPU_KELAS[k.lampu] : 'bg-primary-600';
    return (
        <div className="space-y-1.5">
            <div
                className="h-3 w-full overflow-hidden rounded-full bg-bg-washed"
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={Math.round(pct)}
                aria-label={k.label}
            >
                <div className={`h-full rounded-full ${bar}`} style={{ width: `${pct}%` }} />
            </div>
            {k.sasaran !== null && <p className="text-body-xs text-txt-black-500">Sasaran {fmtNilai(k.sasaran, k.unit)}</p>}
        </div>
    );
}

function Slices({ k, p, horizontal }: { k: Kpi; p: Penapis; horizontal?: boolean }) {
    const data = k.pecahan.slice(0, 8).map((s) => ({ label: s.label, value: Math.round(s.nilai * 10) / 10 }));
    if (data.length === 0) return <p className="text-body-sm text-txt-black-500">Tiada data untuk penapis ini.</p>;
    return (
        <div className="space-y-2">
            {horizontal || data.length > 5 ? <HorizontalBarChart data={data} /> : <BarChart data={data} height={150} />}
            <SliceLinks k={k} p={p} />
        </div>
    );
}

function SliceLinks({ k, p }: { k: Kpi; p: Penapis }) {
    const links = k.pecahan.slice(0, 8).filter((s) => sliceHref(k, p, s.kod));
    if (links.length === 0) return null;
    return (
        <ul className="flex flex-wrap gap-x-3 gap-y-1 text-body-xs" aria-label={`Pecahan ${k.label}`}>
            {links.map((s) => (
                <li key={s.kod}>
                    <Link href={sliceHref(k, p, s.kod) ?? '#'} className="text-txt-primary underline-offset-2 hover:underline">
                        {s.label} ({fmtNombor(s.nilai)})
                    </Link>
                </li>
            ))}
        </ul>
    );
}

function Pie({ k, p }: { k: Kpi; p: Penapis }) {
    const data = k.pecahan.filter((s) => s.nilai > 0).slice(0, 6).map((s, i) => ({ label: s.label, value: s.nilai, color: PIE_COLORS[i % PIE_COLORS.length] }));
    if (data.length === 0) return <p className="text-body-sm text-txt-black-500">Tiada data untuk penapis ini.</p>;
    return (
        <div className="space-y-2">
            <PieChart data={data} size={120} />
            <SliceLinks k={k} p={p} />
        </div>
    );
}

function Trend({ k }: { k: Kpi }) {
    if (k.trend.length < 2) return <p className="text-body-sm text-txt-black-500">Trend belum tersedia.</p>;
    const data = k.trend.map((t) => ({ label: /^\d{4}-\d{2}$/.test(t.tempoh) ? t.tempoh.slice(5) : t.tempoh, value: t.nilai }));
    return <LineChart data={data} height={110} showLabels={data.length <= 12} />;
}

function HeatmapView({ k, p }: { k: Kpi; p: Penapis }) {
    const m = k.matriks;
    if (!m || m.baris.length === 0) return <Slices k={k} p={p} />;
    const t = k.ambang;
    // Admin thresholds (SRS-DSH-10) colour the cells when set; otherwise a relative scale.
    const scale = t ? ['bg-bg-washed', 'bg-danger-200', 'bg-warning-300', 'bg-success-400', 'bg-success-600'] : undefined;
    const max = t ? Math.max(t.hijau, 1) : undefined;
    return (
        <div className="space-y-2">
            <p className="text-body-xs text-txt-black-500">{m.tajuk}</p>
            <div className="overflow-x-auto">
                <Heatmap data={m.baris.map((r) => ({ label: r.label.slice(0, 9), cells: r.sel }))} colLabels={m.lajur.map((l) => l.slice(0, 2))} colorScale={scale} max={max} />
            </div>
            <p className="text-[11px] text-txt-black-500">Lajur: {m.lajur.join(', ')}</p>
        </div>
    );
}

export function KpiWidget({ k, visual, p, title }: { k: Kpi; visual: Visual; p: Penapis; title?: string }) {
    const v = visual === 'auto' ? visualAuto(k) : visual;
    const href = kpiHref(k, p);
    const value = fmtNilai(k.nilai, k.unit);

    return (
        <article className="flex h-full flex-col rounded-lg border border-otl-gray-200 bg-bg-white p-4 shadow-card" aria-labelledby={`kpi-${k.key}`}>
            <header className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <h3 id={`kpi-${k.key}`} className="text-body-sm font-medium text-txt-black-700">
                        {title || k.label}
                    </h3>
                    <p className="text-[11px] uppercase tracking-wide text-txt-black-500">
                        {DOMAIN_LABEL[k.domain] ?? k.domain} · {k.modul.toUpperCase()}
                    </p>
                </div>
                {k.lampu && <Badge variant={LAMPU_BADGE[k.lampu]}>{k.lampu === 'hijau' ? 'Hijau' : k.lampu === 'kuning' ? 'Kuning' : 'Merah'}</Badge>}
            </header>

            <div className="mt-2 flex items-center gap-2">
                <LampuDot lampu={k.lampu} />
                {k.ralat ? (
                    <p className="text-body-sm text-txt-danger">Data tidak dapat dikira buat masa ini.</p>
                ) : href ? (
                    <Link href={href} className="font-heading text-heading-xs font-semibold text-txt-black-900 underline-offset-4 hover:text-txt-primary hover:underline">
                        {value}
                    </Link>
                ) : (
                    <p className="font-heading text-heading-xs font-semibold text-txt-black-900">{value}</p>
                )}
            </div>

            {!k.ralat && (
                <div className="mt-3 flex-1">
                    {v === 'bar' && <Slices k={k} p={p} />}
                    {v === 'pie' && <Pie k={k} p={p} />}
                    {v === 'line' && <Trend k={k} />}
                    {v === 'progress' && <Progress k={k} />}
                    {v === 'heatmap' && <HeatmapView k={k} p={p} />}
                    {v === 'lampu' && <p className="text-body-sm text-txt-black-700">{k.lampu ? LAMPU_LABEL[k.lampu] : 'Tiada ambang ditetapkan'}</p>}
                    {v === 'stat' && k.pecahan.length > 0 && <SliceLinks k={k} p={p} />}
                </div>
            )}
            <Kesegaran k={k} />
        </article>
    );
}

/** Traffic-light overview of every KPI on the board (SRS-DSH-09). */
export function LampuWidget({ kpis, p }: { kpis: Kpi[]; p: Penapis }) {
    const lit = kpis.filter((k) => k.lampu);
    const count = (l: Lampu) => lit.filter((k) => k.lampu === l).length;
    return (
        <article className="h-full rounded-lg border border-otl-gray-200 bg-bg-white p-4 shadow-card" aria-labelledby="w-lampu">
            <h3 id="w-lampu" className="text-body-sm font-medium text-txt-black-700">
                Penunjuk lampu isyarat
            </h3>
            <div className="mt-3 flex flex-wrap gap-4">
                {(['merah', 'kuning', 'hijau'] as Lampu[]).map((l) => (
                    <div key={l} className="flex items-center gap-2">
                        <LampuDot lampu={l} className="h-4 w-4" />
                        <span className="font-heading text-heading-2xs font-semibold text-txt-black-900">{count(l)}</span>
                        <span className="text-body-xs text-txt-black-500">{l === 'merah' ? 'Merah' : l === 'kuning' ? 'Kuning' : 'Hijau'}</span>
                    </div>
                ))}
            </div>
            {lit.length === 0 ? (
                <p className="mt-3 text-body-sm text-txt-black-500">Tiada indikator dengan ambang pada paparan ini.</p>
            ) : (
                <ul className="mt-3 divide-y divide-otl-divider">
                    {[...lit]
                        .sort((a, b) => ['merah', 'kuning', 'hijau'].indexOf(a.lampu ?? '') - ['merah', 'kuning', 'hijau'].indexOf(b.lampu ?? ''))
                        .slice(0, 8)
                        .map((k) => {
                            const href = kpiHref(k, p);
                            return (
                                <li key={k.key} className="flex items-center justify-between gap-2 py-1.5 text-body-sm">
                                    <span className="flex min-w-0 items-center gap-2">
                                        <LampuDot lampu={k.lampu} />
                                        {href ? (
                                            <Link href={href} className="truncate text-txt-black-900 hover:text-txt-primary hover:underline">
                                                {k.label}
                                            </Link>
                                        ) : (
                                            <span className="truncate">{k.label}</span>
                                        )}
                                    </span>
                                    <span className="flex-shrink-0 text-txt-black-500">{fmtNilai(k.nilai, k.unit)}</span>
                                </li>
                            );
                        })}
                </ul>
            )}
        </article>
    );
}
