'use client';

import 'maplibre-gl/dist/maplibre-gl.css';
import { useEffect, useRef, useState } from 'react';
import type { Map as MapLibreMap, Marker } from 'maplibre-gl';
import type { PetaFeature } from '@/lib/adu';

/**
 * MapLibre map for MOD-ADU (SRS-ADU-02/16): complaint points coloured by
 * category, and/or a draggable pin the user places by tapping the map (intake
 * form). maplibre-gl loads lazily, browser only — same set-up as components/pro/LotMap.
 */
export function PetaAduan({
    points = [],
    pin,
    onPin,
    onSelect,
    height = 320,
    label = 'Peta aduan',
    legenda,
}: {
    points?: PetaFeature[];
    pin?: { lat: number; lng: number } | null;
    onPin?: (p: { lat: number; lng: number }) => void;
    onSelect?: (pautan: string) => void;
    height?: number;
    label?: string;
    legenda?: Array<{ nilai: string; label: string; warna: string }>;
}) {
    const container = useRef<HTMLDivElement>(null);
    const mapRef = useRef<MapLibreMap | null>(null);
    const markerRef = useRef<Marker | null>(null);
    const libRef = useRef<typeof import('maplibre-gl') | null>(null);
    const onPinRef = useRef(onPin);
    const onSelectRef = useRef(onSelect);
    const [ready, setReady] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        onPinRef.current = onPin;
        onSelectRef.current = onSelect;
    }, [onPin, onSelect]);

    useEffect(() => {
        let cancelled = false;
        let map: MapLibreMap | null = null;
        (async () => {
            try {
                const maplibregl = await import('maplibre-gl');
                if (cancelled || !container.current) return;
                libRef.current = maplibregl;
                if (!maplibregl.getWorkerUrl()) {
                    maplibregl.setWorkerUrl(process.env.NEXT_PUBLIC_MAPLIBRE_WORKER_URL ?? '/vendor/maplibre/maplibre-gl-worker.mjs');
                }
                map = new maplibregl.Map({
                    container: container.current,
                    style: {
                        version: 8,
                        sources: { osm: { type: 'raster', tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'], tileSize: 256, maxzoom: 19, attribution: '© OpenStreetMap contributors' } },
                        layers: [{ id: 'osm', type: 'raster', source: 'osm' }],
                    },
                    center: [101.5, 3.1],
                    zoom: 9,
                    attributionControl: { compact: true },
                });
                map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
                map.on('load', () => {
                    if (!map) return;
                    map.addSource('aduan', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
                    map.addLayer({
                        id: 'aduan-titik',
                        type: 'circle',
                        source: 'aduan',
                        paint: {
                            'circle-radius': ['case', ['get', 'terbuka'], 7, 5] as never,
                            'circle-color': ['get', 'warna'] as never,
                            'circle-stroke-color': '#ffffff',
                            'circle-stroke-width': 1.5,
                            'circle-opacity': ['case', ['get', 'terbuka'], 0.95, 0.5] as never,
                        },
                    });
                    map.on('click', 'aduan-titik', (e) => {
                        const link = e.features?.[0]?.properties?.pautan;
                        if (typeof link === 'string') onSelectRef.current?.(link);
                    });
                    map.on('click', (e) => {
                        if (onPinRef.current && !map?.queryRenderedFeatures(e.point, { layers: ['aduan-titik'] }).length) {
                            onPinRef.current({ lat: Number(e.lngLat.lat.toFixed(6)), lng: Number(e.lngLat.lng.toFixed(6)) });
                        }
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

    useEffect(() => {
        const map = mapRef.current;
        if (!ready || !map) return;
        (map.getSource('aduan') as { setData?: (d: unknown) => void } | undefined)?.setData?.({ type: 'FeatureCollection', features: points });
        if (points.length > 0 && !pin) {
            const xs = points.map((p) => p.geometry.coordinates[0]);
            const ys = points.map((p) => p.geometry.coordinates[1]);
            map.fitBounds(
                [
                    [Math.min(...xs), Math.min(...ys)],
                    [Math.max(...xs), Math.max(...ys)],
                ],
                { padding: 40, maxZoom: 16, duration: 0 },
            );
        }
    }, [points, ready, pin]);

    useEffect(() => {
        const map = mapRef.current;
        const lib = libRef.current;
        if (!ready || !map || !lib) return;
        if (!pin) {
            markerRef.current?.remove();
            markerRef.current = null;
            return;
        }
        if (!markerRef.current) {
            markerRef.current = new lib.Marker({ color: '#dc2626', draggable: Boolean(onPinRef.current) }).setLngLat([pin.lng, pin.lat]).addTo(map);
            markerRef.current.on('dragend', () => {
                const ll = markerRef.current?.getLngLat();
                if (ll) onPinRef.current?.({ lat: Number(ll.lat.toFixed(6)), lng: Number(ll.lng.toFixed(6)) });
            });
        } else {
            markerRef.current.setLngLat([pin.lng, pin.lat]);
        }
        map.easeTo({ center: [pin.lng, pin.lat], zoom: Math.max(map.getZoom(), 15), duration: 300 });
    }, [pin, ready]);

    return (
        <div className="space-y-2">
            <div className="relative overflow-hidden rounded-md border border-otl-gray-200">
                <div ref={container} role="region" aria-label={label} style={{ height }} className="w-full" />
                {error && (
                    <div className="pointer-events-none absolute inset-x-0 top-3 flex justify-center">
                        <span className="rounded-md bg-bg-white/90 px-3 py-1.5 text-body-xs text-txt-black-700 shadow-card">{error}</span>
                    </div>
                )}
            </div>
            {legenda && legenda.length > 0 && (
                <ul className="flex flex-wrap gap-x-4 gap-y-1 text-body-xs text-txt-black-700" aria-label="Petunjuk warna kategori">
                    {legenda.map((l) => (
                        <li key={l.nilai} className="flex items-center gap-1.5">
                            <span className="inline-block h-3 w-3 rounded-full" style={{ backgroundColor: l.warna }} aria-hidden="true" />
                            {l.label}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
