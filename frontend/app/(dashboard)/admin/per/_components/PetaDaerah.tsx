'use client';

import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Spinner } from '@/components/ui/Spinner';

type Ring = number[][];
interface Feature {
    properties: { kod: string; nama: string };
    geometry: { type: 'Polygon' | 'MultiPolygon'; coordinates: Ring[] | Ring[][] };
}

/**
 * SRS-PER-28 distribution map: a per-district choropleth of Selangor drawn
 * from MOD-REF's boundary GeoJSON (/api/v1/ref/sempadan/daerah). Plain SVG
 * (equirectangular, cos(lat) corrected) — MapLibre GL is not installed yet.
 */
export function PetaDaerah({ data, warna = '#2563EB', label = 'kes' }: { data: Array<{ kod: string | null; nama: string; bilangan: number }>; warna?: string; label?: string }) {
    const { data: geo, isLoading, error } = useQuery({
        queryKey: ['ref', 'sempadan', 'daerah'],
        // application/geo+json — fetched directly (lib/api only parses application/json).
        queryFn: async () => {
            const r = await fetch('/api/v1/ref/sempadan/daerah', { credentials: 'include', headers: { Accept: 'application/json' } });
            if (!r.ok) throw new Error('Sempadan daerah tidak dapat dimuatkan.');
            return (await r.json()) as { features: Feature[]; attribution?: string };
        },
        staleTime: 3_600_000,
    });
    const [hover, setHover] = useState<string | null>(null);
    const counts = useMemo(() => new Map(data.map((d) => [d.kod ?? '', d.bilangan])), [data]);
    const max = Math.max(1, ...data.map((d) => d.bilangan));

    const shapes = useMemo(() => {
        if (!geo) return null;
        const polys = geo.features.map((f) => ({ f, rings: (f.geometry.type === 'Polygon' ? [f.geometry.coordinates] : f.geometry.coordinates) as Ring[][] }));
        let minX = Infinity,
            minY = Infinity,
            maxX = -Infinity,
            maxY = -Infinity;
        for (const p of polys) for (const poly of p.rings) for (const ring of poly) for (const [x, y] of ring) {
            minX = Math.min(minX, x);
            maxX = Math.max(maxX, x);
            minY = Math.min(minY, y);
            maxY = Math.max(maxY, y);
        }
        const k = Math.cos((((minY + maxY) / 2) * Math.PI) / 180);
        const W = 400;
        const scale = W / ((maxX - minX) * k);
        const H = (maxY - minY) * scale;
        const pt = (x: number, y: number) => `${((x - minX) * k * scale).toFixed(1)},${((maxY - y) * scale).toFixed(1)}`;
        return {
            W,
            H,
            items: polys.map(({ f, rings }) => ({
                kod: f.properties.kod,
                nama: f.properties.nama,
                d: rings.map((poly) => poly.map((ring) => 'M' + ring.map(([x, y]) => pt(x, y)).join('L') + 'Z').join('')).join(''),
            })),
        };
    }, [geo]);

    if (isLoading) return <Spinner />;
    if (error || !shapes) return <p className="text-body-sm text-txt-black-500">Peta sempadan daerah tidak dapat dimuatkan.</p>;

    const hovered = hover ? shapes.items.find((s) => s.kod === hover) : null;

    return (
        <figure className="space-y-2">
            <svg viewBox={`0 0 ${shapes.W} ${shapes.H.toFixed(0)}`} className="w-full max-h-[420px]" role="img" aria-label={`Taburan ${label} mengikut daerah`}>
                {shapes.items.map((s) => {
                    const n = counts.get(s.kod) ?? 0;
                    return (
                        <path
                            key={s.kod}
                            d={s.d}
                            fill={warna}
                            fillOpacity={n === 0 ? 0.04 : 0.15 + 0.75 * (n / max)}
                            stroke="currentColor"
                            className="text-otl-gray-300 cursor-pointer"
                            strokeWidth={hover === s.kod ? 2 : 0.8}
                            onMouseEnter={() => setHover(s.kod)}
                            onMouseLeave={() => setHover(null)}
                            onFocus={() => setHover(s.kod)}
                            tabIndex={0}
                        >
                            <title>{`${s.nama}: ${n} ${label}`}</title>
                        </path>
                    );
                })}
            </svg>
            <figcaption className="flex flex-wrap items-center justify-between gap-2 text-body-xs text-txt-black-500">
                <span aria-live="polite">{hovered ? `${hovered.nama}: ${counts.get(hovered.kod) ?? 0} ${label}` : 'Halakan tetikus pada daerah untuk butiran.'}</span>
                <span className="flex items-center gap-1">
                    0
                    <span className="inline-block h-2.5 w-16 rounded" style={{ background: `linear-gradient(to right, ${warna}22, ${warna})` }} />
                    {max}
                </span>
                {geo?.attribution && <span>{geo.attribution}</span>}
            </figcaption>
        </figure>
    );
}
