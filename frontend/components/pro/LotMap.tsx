'use client';

import 'maplibre-gl/dist/maplibre-gl.css';
import { useEffect, useRef, useState } from 'react';
import type { Map as MapLibreMap } from 'maplibre-gl';
import type { LotFeature, LotFeatureCollection } from '@/lib/pro';

/**
 * MapLibre map of one lot or a FeatureCollection of lots, on OSM raster tiles.
 * Polygons are coloured by status_pengesahan (PRO-10): disahkan_* green,
 * belum_disahkan amber, bertindih red. maplibre-gl is imported lazily so it
 * only ever loads in the browser.
 */

const WARNA = { disahkan: '#16a34a', belum: '#d97706', bertindih: '#dc2626', lain: '#6b7280' };

type Data = LotFeature | LotFeatureCollection | null | undefined;

function toCollection(data: Data): LotFeatureCollection {
    if (!data) return { type: 'FeatureCollection', features: [] };
    if (data.type === 'FeatureCollection') return { ...data, features: data.features.filter((f) => f.geometry) };
    return { type: 'FeatureCollection', features: data.geometry ? [data] : [] };
}

/** [minLng, minLat, maxLng, maxLat] across all coordinates, or null. */
function bounds(fc: LotFeatureCollection): [number, number, number, number] | null {
    let b: [number, number, number, number] | null = null;
    const visit = (c: unknown) => {
        if (!Array.isArray(c)) return;
        if (typeof c[0] === 'number' && typeof c[1] === 'number') {
            const [x, y] = c as [number, number];
            b = b ? [Math.min(b[0], x), Math.min(b[1], y), Math.max(b[2], x), Math.max(b[3], y)] : [x, y, x, y];
            return;
        }
        c.forEach(visit);
    };
    fc.features.forEach((f) => {
        const g = f.geometry as { coordinates?: unknown; geometries?: Array<{ coordinates?: unknown }> } | null;
        if (g?.coordinates) visit(g.coordinates);
        g?.geometries?.forEach((x) => visit(x.coordinates));
    });
    return b;
}

const colourExpr = [
    'case',
    ['==', ['slice', ['coalesce', ['get', 'status_pengesahan'], ''], 0, 8], 'disahkan'],
    WARNA.disahkan,
    ['==', ['get', 'status_pengesahan'], 'belum_disahkan'],
    WARNA.belum,
    ['==', ['get', 'status_pengesahan'], 'bertindih'],
    WARNA.bertindih,
    WARNA.lain,
];

export function LotMap({
    data,
    height = 360,
    onSelect,
    label = 'Peta lot',
}: {
    data: Data;
    height?: number;
    /** Called with properties.lot_id when a polygon is clicked (list map). */
    onSelect?: (lotId: string) => void;
    label?: string;
}) {
    const container = useRef<HTMLDivElement>(null);
    const mapRef = useRef<MapLibreMap | null>(null);
    const onSelectRef = useRef(onSelect);
    const [ready, setReady] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        onSelectRef.current = onSelect;
    }, [onSelect]);

    // Create the map once.
    useEffect(() => {
        let cancelled = false;
        let map: MapLibreMap | null = null;

        (async () => {
            try {
                const maplibregl = await import('maplibre-gl');
                if (cancelled || !container.current) return;
                // v6 resolves its module worker relative to its own file, which a
                // bundler moves; serve a self-hosted copy (public/vendor/maplibre —
                // re-copy from node_modules/maplibre-gl/dist when bumping the package).
                if (!maplibregl.getWorkerUrl()) {
                    maplibregl.setWorkerUrl(process.env.NEXT_PUBLIC_MAPLIBRE_WORKER_URL ?? '/vendor/maplibre/maplibre-gl-worker.mjs');
                }
                map = new maplibregl.Map({
                    container: container.current,
                    style: {
                        version: 8,
                        sources: {
                            osm: {
                                type: 'raster',
                                tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
                                tileSize: 256,
                                maxzoom: 19,
                                attribution: '© OpenStreetMap contributors',
                            },
                        },
                        layers: [{ id: 'osm', type: 'raster', source: 'osm' }],
                    },
                    center: [101.5, 3.1],
                    zoom: 9,
                    attributionControl: { compact: true },
                });
                map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
                map.on('load', () => {
                    if (!map) return;
                    map.addSource('lot', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
                    map.addLayer({ id: 'lot-fill', type: 'fill', source: 'lot', paint: { 'fill-color': colourExpr as never, 'fill-opacity': 0.35 } });
                    map.addLayer({ id: 'lot-line', type: 'line', source: 'lot', paint: { 'line-color': colourExpr as never, 'line-width': 2 } });
                    map.on('click', 'lot-fill', (e) => {
                        const id = e.features?.[0]?.properties?.lot_id;
                        if (typeof id === 'string') onSelectRef.current?.(id);
                    });
                    map.on('mouseenter', 'lot-fill', () => {
                        if (map && onSelectRef.current) map.getCanvas().style.cursor = 'pointer';
                    });
                    map.on('mouseleave', 'lot-fill', () => {
                        if (map) map.getCanvas().style.cursor = '';
                    });
                    mapRef.current = map;
                    setReady(true);
                });
            } catch {
                if (!cancelled) setError('Peta tidak dapat dimuatkan.');
            }
        })();

        return () => {
            cancelled = true;
            mapRef.current = null;
            map?.remove();
        };
    }, []);

    // Push data + fit bounds whenever it changes.
    useEffect(() => {
        const map = mapRef.current;
        if (!ready || !map) return;
        const fc = toCollection(data);
        const source = map.getSource('lot') as { setData?: (d: unknown) => void } | undefined;
        source?.setData?.(fc);
        const b = bounds(fc);
        if (b) {
            map.fitBounds(
                [
                    [b[0], b[1]],
                    [b[2], b[3]],
                ],
                { padding: 40, maxZoom: 18, duration: 0 },
            );
        }
    }, [data, ready]);

    const empty = toCollection(data).features.length === 0;

    return (
        <div className="space-y-2">
            <div className="relative overflow-hidden rounded-md border border-otl-gray-200">
                <div ref={container} role="region" aria-label={label} style={{ height }} className="w-full" />
                {(error || (ready && empty)) && (
                    <div className="pointer-events-none absolute inset-x-0 top-3 flex justify-center">
                        <span className="rounded-md bg-bg-white/90 px-3 py-1.5 text-body-xs text-txt-black-700 shadow-card">{error ?? 'Tiada geometri untuk dipaparkan.'}</span>
                    </div>
                )}
            </div>
            <ul className="flex flex-wrap gap-x-4 gap-y-1 text-body-xs text-txt-black-700" aria-label="Petunjuk warna">
                {[
                    [WARNA.disahkan, 'Disahkan'],
                    [WARNA.belum, 'Belum disahkan'],
                    [WARNA.bertindih, 'Bertindih'],
                ].map(([c, l]) => (
                    <li key={l} className="flex items-center gap-1.5">
                        <span className="inline-block h-3 w-3 rounded-sm" style={{ backgroundColor: c, opacity: 0.8 }} aria-hidden="true" />
                        {l}
                    </li>
                ))}
            </ul>
        </div>
    );
}
