'use client';

import 'maplibre-gl/dist/maplibre-gl.css';
import Link from 'next/link';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { Map as MapLibreMap, MapGeoJSONFeature } from 'maplibre-gl';
import { gisApi, useKatalog, type Bbox, type KoleksiCiri, type Lapisan } from '@/lib/gis';
import { addLapisan, applyOrder, basemapStyle, boundsOf, idsFor, mapBbox, setBasemap, setOpacity, setVisible, WARNA_SOROTAN } from './gaya';
import { Legenda } from './Legenda';

/**
 * The SISDATA map, embeddable by any module (SRS-GIS-25/34):
 *
 *     <PetaSisdata layers={['gis.lot', 'gis.sempadan_daerah']} focus={{ lotId }} height={360} />
 *
 * Layers come from the MOD-GIS catalogue (only those the user may see); big
 * layers are vector tiles, others GeoJSON reloaded per view. Clicking a
 * feature shows its attributes with a link to its record (SRS-GIS-09), or
 * calls `onFeatureClick`. maplibre-gl is loaded lazily, browser only.
 */
export interface KlikCiri {
    lapisan: Lapisan;
    properties: Record<string, unknown>;
    lngLat: [number, number];
}

export interface PetaSisdataProps {
    /** Layer keys to show; omitted → the catalogue's default-visible layers. */
    layers?: string[];
    focus?: { lotId?: string; bbox?: Bbox; center?: [number, number]; zoom?: number };
    height?: number | string;
    basemap?: string;
    /** Per-layer opacity overrides (0–1). */
    opacity?: Record<string, number>;
    /** Bottom→top stacking; defaults to the catalogue order. */
    order?: string[];
    /** Per-layer equality filters, e.g. { 'gis.lot': { kod_daerah: '1008' } }. */
    filters?: Record<string, Record<string, string>>;
    /** Analysis results / selections drawn on top (feature properties `warna`, `garis`, `ketelusan` override the highlight style — e.g. a choropleth). */
    overlay?: KoleksiCiri | null;
    showLegend?: boolean;
    label?: string;
    className?: string;
    onFeatureClick?: (hit: KlikCiri) => void;
    onReady?: (map: MapLibreMap, lib: typeof import('maplibre-gl')) => void;
    onMove?: (map: MapLibreMap) => void;
}

export function PetaSisdata({
    layers,
    focus,
    height = 420,
    basemap = 'osm',
    opacity,
    order,
    filters,
    overlay,
    showLegend = true,
    label = 'Peta SISDATA',
    className,
    onFeatureClick,
    onReady,
    onMove,
}: PetaSisdataProps) {
    const container = useRef<HTMLDivElement>(null);
    const mapRef = useRef<MapLibreMap | null>(null);
    const [ready, setReady] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [popup, setPopup] = useState<KlikCiri | null>(null);
    const { data: katalog, error: katalogError } = useKatalog();
    const callbacks = useRef({ onFeatureClick, onReady, onMove });
    useEffect(() => {
        callbacks.current = { onFeatureClick, onReady, onMove };
    }, [onFeatureClick, onReady, onMove]);

    const byKod = useMemo(() => new Map((katalog?.lapisan ?? []).map((l) => [l.kod, l])), [katalog]);
    const shown = useMemo(
        () => (layers ?? (katalog?.lapisan ?? []).filter((l) => l.papar_lalai).map((l) => l.kod)).filter((k) => byKod.has(k)),
        [layers, katalog, byKod],
    );
    const shownRef = useRef<string[]>([]);
    const filtersRef = useRef(filters);
    useEffect(() => {
        shownRef.current = shown;
        filtersRef.current = filters;
    }, [shown, filters]);

    // Create the map once the catalogue (basemaps) is known.
    useEffect(() => {
        if (!katalog || mapRef.current) return;
        let cancelled = false;
        let map: MapLibreMap | null = null;
        (async () => {
            try {
                const maplibregl = await import('maplibre-gl');
                if (cancelled || !container.current) return;
                if (!maplibregl.getWorkerUrl()) {
                    maplibregl.setWorkerUrl(process.env.NEXT_PUBLIC_MAPLIBRE_WORKER_URL ?? '/vendor/maplibre/maplibre-gl-worker.mjs');
                }
                map = new maplibregl.Map({
                    container: container.current,
                    style: basemapStyle(katalog.peta_asas, basemap),
                    center: focus?.center ?? [101.52, 3.07],
                    zoom: focus?.zoom ?? 9,
                    attributionControl: { compact: true },
                    canvasContextAttributes: { preserveDrawingBuffer: true },
                    transformRequest: (url) => (url.includes('/api/v1/gis/') ? { url, credentials: 'include' } : { url }),
                });
                map.addControl(new maplibregl.NavigationControl({ visualizePitch: false }), 'top-right');
                map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-left');
                map.on('load', () => {
                    if (!map) return;
                    katalog.lapisan.forEach((l) => addLapisan(map as MapLibreMap, l, filtersRef.current?.[l.kod]));
                    map.addSource('sorotan', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
                    map.addLayer({ id: 'sorotan::fill', type: 'fill', source: 'sorotan', filter: ['in', ['geometry-type'], ['literal', ['Polygon', 'MultiPolygon']]] as never, paint: { 'fill-color': ['coalesce', ['get', 'warna'], WARNA_SOROTAN] as never, 'fill-opacity': ['coalesce', ['get', 'ketelusan'], 0.18] as never } });
                    map.addLayer({ id: 'sorotan::line', type: 'line', source: 'sorotan', paint: { 'line-color': ['coalesce', ['get', 'garis'], ['get', 'warna'], WARNA_SOROTAN] as never, 'line-width': 2.5 } });
                    map.addLayer({ id: 'sorotan::circle', type: 'circle', source: 'sorotan', filter: ['in', ['geometry-type'], ['literal', ['Point', 'MultiPoint']]] as never, paint: { 'circle-color': WARNA_SOROTAN, 'circle-radius': 6, 'circle-stroke-color': '#fff', 'circle-stroke-width': 1.5 } });

                    map.on('click', (e) => {
                        if (!map) return;
                        const ids = shownRef.current.flatMap(idsFor).filter((id) => map?.getLayer(id));
                        // Topmost feature, but reference outlines (boundaries) only when nothing else is under the cursor.
                        const outline = (f: MapGeoJSONFeature) => !!katalog.lapisan.find((l) => l.kod === f.layer.id.split('::')[0])?.gaya.garis_sahaja;
                        const hits = map.queryRenderedFeatures(e.point, { layers: ids });
                        const hit: MapGeoJSONFeature | undefined = hits.find((f) => !outline(f)) ?? hits[0];
                        if (!hit) {
                            setPopup(null);
                            return;
                        }
                        const kod = hit.layer.id.split('::')[0];
                        const lapisan = katalog.lapisan.find((l) => l.kod === kod);
                        if (!lapisan) return;
                        const click: KlikCiri = { lapisan, properties: hit.properties as Record<string, unknown>, lngLat: [e.lngLat.lng, e.lngLat.lat] };
                        if (callbacks.current.onFeatureClick) callbacks.current.onFeatureClick(click);
                        else setPopup(click);
                    });
                    map.on('mousemove', (e) => {
                        if (!map) return;
                        const ids = shownRef.current.flatMap(idsFor).filter((id) => map?.getLayer(id));
                        map.getCanvas().style.cursor = map.queryRenderedFeatures(e.point, { layers: ids }).length ? 'pointer' : '';
                    });
                    map.on('moveend', () => map && callbacks.current.onMove?.(map));
                    mapRef.current = map;
                    // Handle for e2e checks in development builds only.
                    if (process.env.NODE_ENV !== 'production') (window as unknown as { __petaSisdata?: MapLibreMap }).__petaSisdata = map;
                    setReady(true);
                    callbacks.current.onReady?.(map, maplibregl);
                });
                map.on('error', (ev) => {
                    if (process.env.NODE_ENV !== 'production') console.warn('[PetaSisdata]', ev.error?.message);
                    const status = (ev.error as { status?: number } | undefined)?.status;
                    if (status === 403) setError('Sebahagian lapisan tidak dibenarkan untuk anda.');
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
        // eslint-disable-next-line react-hooks/exhaustive-deps -- the map is created once per catalogue
    }, [katalog]);

    // Visibility, order, opacity.
    useEffect(() => {
        const map = mapRef.current;
        if (!ready || !map || !katalog) return;
        katalog.lapisan.forEach((l) => setVisible(map, l.kod, shown.includes(l.kod)));
        katalog.lapisan.forEach((l) => setOpacity(map, l, opacity?.[l.kod] ?? l.ketelusan));
        applyOrder(map, order ?? [...katalog.lapisan].sort((a, b) => a.urutan - b.urutan).map((l) => l.kod));
    }, [ready, katalog, shown, opacity, order]);

    useEffect(() => {
        const map = mapRef.current;
        if (ready && map && katalog) setBasemap(map, katalog.peta_asas, basemap);
    }, [ready, katalog, basemap]);

    // GeoJSON layers: reload for the current view (bbox + zoom simplification).
    useEffect(() => {
        const map = mapRef.current;
        if (!ready || !map) return;
        let timer: ReturnType<typeof setTimeout> | null = null;
        const reload = () => {
            if (timer) clearTimeout(timer);
            timer = setTimeout(() => {
                shownRef.current
                    .map((k) => byKod.get(k))
                    .filter((l): l is Lapisan => !!l && l.sumber === 'geojson')
                    .forEach((l) => {
                        gisApi
                            .ciri(l.kod, { bbox: mapBbox(map), zoom: map.getZoom(), penapis: filtersRef.current?.[l.kod] })
                            .then((fc) => (map.getSource(`src::${l.kod}`) as { setData?: (d: unknown) => void } | undefined)?.setData?.(fc))
                            .catch(() => undefined);
                    });
            }, 250);
        };
        reload();
        map.on('moveend', reload);
        return () => {
            map.off('moveend', reload);
            if (timer) clearTimeout(timer);
        };
    }, [ready, byKod, shown, filters]);

    // Focus: a lot (fetched through the scoped lot layer) or a bbox.
    useEffect(() => {
        const map = mapRef.current;
        if (!ready || !map || !focus) return;
        const fit = (b: Bbox | null) =>
            b &&
            map.fitBounds(
                [
                    [b[0], b[1]],
                    [b[2], b[3]],
                ],
                { padding: 48, maxZoom: 18, duration: 0 },
            );
        if (focus.bbox) fit(focus.bbox);
        else if (focus.lotId && byKod.has('gis.lot')) {
            gisApi
                .ciri('gis.lot', { penapis: { id: focus.lotId } })
                .then((fc) => {
                    fit(boundsOf(fc.features.map((f) => f.geometry)));
                    (map.getSource('sorotan') as { setData?: (d: unknown) => void } | undefined)?.setData?.(fc);
                })
                .catch(() => undefined);
        } else if (focus.center) map.jumpTo({ center: focus.center, zoom: focus.zoom ?? map.getZoom() });
    }, [ready, focus, byKod]);

    // Highlight overlay.
    useEffect(() => {
        const map = mapRef.current;
        if (!ready || !map || !overlay) return;
        (map.getSource('sorotan') as { setData?: (d: unknown) => void } | undefined)?.setData?.(overlay);
    }, [ready, overlay]);

    const visibleLayers = shown.map((k) => byKod.get(k)).filter((l): l is Lapisan => !!l);

    return (
        <div className={`space-y-2 ${className ?? ''}`}>
            <div className="relative overflow-hidden rounded-md border border-otl-gray-200">
                <div ref={container} role="region" aria-label={label} style={{ height }} className="w-full bg-bg-washed" />
                {(error || katalogError) && (
                    <div className="pointer-events-none absolute inset-x-0 top-3 flex justify-center">
                        <span className="rounded-md bg-bg-white/90 px-3 py-1.5 text-body-xs text-txt-black-700 shadow-card">
                            {error ?? 'Katalog lapisan tidak dapat dimuatkan.'}
                        </span>
                    </div>
                )}
                {popup && (
                    <div className="absolute left-2 top-2 z-10 w-72 max-w-[calc(100%-1rem)] rounded-md border border-otl-gray-200 bg-bg-white p-3 text-body-xs shadow-card">
                        <div className="mb-1 flex items-start justify-between gap-2">
                            <div>
                                <p className="text-txt-black-500">{popup.lapisan.nama}</p>
                                <p className="font-semibold text-txt-black-900">{String(popup.properties.label ?? popup.properties.id ?? '—')}</p>
                            </div>
                            <button type="button" className="text-txt-black-500 hover:text-txt-black-900" aria-label="Tutup" onClick={() => setPopup(null)}>
                                ×
                            </button>
                        </div>
                        {typeof popup.properties.pautan === 'string' && popup.properties.pautan && (
                            <Link href={popup.properties.pautan} className="font-medium text-txt-primary hover:underline">
                                Buka rekod →
                            </Link>
                        )}
                    </div>
                )}
            </div>
            {showLegend && visibleLayers.length > 0 && <Legenda layers={visibleLayers} compact />}
        </div>
    );
}
