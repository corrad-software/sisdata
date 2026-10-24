/*
 * MapLibre style helpers for MOD-GIS layers: one source per layer (vector
 * tiles or GeoJSON) and fill / line / dashed-line / circle style layers
 * coloured by the layer's legend (SRS-GIS-12). Plain TS so the workspace and
 * the embeddable <PetaSisdata /> share it.
 */
import type { Map as MapLibreMap, StyleSpecification } from 'maplibre-gl';
import type { Bbox, Lapisan, PetaAsas } from '@/lib/gis';

export const WARNA_LALAI = '#52525B';
export const WARNA_SOROTAN = '#E11D48';

export const SUFFIXES = ['fill', 'line', 'dash', 'circle'] as const;

export function idsFor(kod: string): string[] {
    return SUFFIXES.map((s) => `${kod}::${s}`);
}

/** MapLibre colour expression from a legend (match on the legend field). */
export function colourExpr(l: Lapisan): unknown {
    const entries = l.legenda.entri;
    if (!l.legenda.medan || entries.length === 0) return entries[0]?.warna ?? WARNA_LALAI;
    const match: unknown[] = ['match', ['to-string', ['coalesce', ['get', l.legenda.medan], '']]];
    entries.forEach((e) => match.push(e.nilai, e.warna));
    match.push(WARNA_LALAI);
    return match;
}

export function basemapStyle(asas: PetaAsas[], pilihan: string): StyleSpecification {
    const sources: StyleSpecification['sources'] = {};
    const layers: StyleSpecification['layers'] = [{ id: 'latar', type: 'background', paint: { 'background-color': '#F4F4F5' } }];
    asas.filter((a) => a.jenis === 'raster' && a.aktif && a.url).forEach((a) => {
        sources[`asas-${a.kod}`] = { type: 'raster', tiles: [a.url as string], tileSize: 256, maxzoom: 19, attribution: a.atribusi };
        layers.push({ id: `asas-${a.kod}`, type: 'raster', source: `asas-${a.kod}`, layout: { visibility: a.kod === pilihan ? 'visible' : 'none' } });
    });
    return { version: 8, sources, layers };
}

export function setBasemap(map: MapLibreMap, asas: PetaAsas[], pilihan: string): void {
    asas.forEach((a) => {
        if (map.getLayer(`asas-${a.kod}`)) map.setLayoutProperty(`asas-${a.kod}`, 'visibility', a.kod === pilihan ? 'visible' : 'none');
    });
}

/** Absolute URL template for a layer's vector tiles (same origin → session cookie sent). */
export function tileUrl(kod: string, penapis?: Record<string, string>): string {
    const origin = typeof window !== 'undefined' ? window.location.origin : '';
    const q = penapis ? Object.entries(penapis).map(([k, v]) => `penapis[${encodeURIComponent(k)}]=${encodeURIComponent(v)}`).join('&') : '';
    return `${origin}/api/v1/gis/tiles/${kod}/{z}/{x}/{y}.pbf${q ? `?${q}` : ''}`;
}

/** Add a layer's source + style layers (hidden). Idempotent. */
export function addLapisan(map: MapLibreMap, l: Lapisan, penapis?: Record<string, string>): void {
    const src = `src::${l.kod}`;
    if (map.getSource(src)) return;
    if (l.sumber === 'mvt') {
        map.addSource(src, { type: 'vector', tiles: [tileUrl(l.kod, penapis)], minzoom: 0, maxzoom: 16 });
    } else {
        map.addSource(src, { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
    }
    const common = l.sumber === 'mvt' ? { source: src, 'source-layer': l.kod } : { source: src };
    const colour = colourExpr(l) as never;
    const hidden = { visibility: 'none' as const };
    const outlineOnly = !!l.gaya.garis_sahaja;
    const dashField = l.gaya.garis_putus;
    const polygon = ['in', ['geometry-type'], ['literal', ['Polygon', 'MultiPolygon']]] as never;
    const notPoint = ['!', ['in', ['geometry-type'], ['literal', ['Point', 'MultiPoint']]]];

    map.addLayer({
        id: `${l.kod}::fill`, type: 'fill', ...common, layout: hidden, filter: polygon,
        paint: { 'fill-color': colour, 'fill-opacity': outlineOnly ? 0.01 : l.ketelusan },
    });
    map.addLayer({
        id: `${l.kod}::line`, type: 'line', ...common, layout: hidden,
        filter: (dashField ? ['all', notPoint, ['!=', ['get', dashField], true]] : notPoint) as never,
        paint: { 'line-color': colour, 'line-width': l.gaya.garis_tebal || outlineOnly ? 2.2 : 1.2, 'line-opacity': 0.95 },
    });
    if (dashField) {
        map.addLayer({
            id: `${l.kod}::dash`, type: 'line', ...common, layout: hidden,
            filter: ['all', notPoint, ['==', ['get', dashField], true]] as never,
            paint: { 'line-color': colour, 'line-width': 1.6, 'line-dasharray': [2, 1.5] },
        });
    }
    map.addLayer({
        id: `${l.kod}::circle`, type: 'circle', ...common, layout: hidden, filter: ['in', ['geometry-type'], ['literal', ['Point', 'MultiPoint']]] as never,
        paint: { 'circle-color': colour, 'circle-radius': ['interpolate', ['linear'], ['zoom'], 8, 3, 14, 6] as never, 'circle-stroke-color': '#FFFFFF', 'circle-stroke-width': 1, 'circle-opacity': 0.9 },
    });
}

export function setVisible(map: MapLibreMap, kod: string, visible: boolean): void {
    idsFor(kod).forEach((id) => {
        if (map.getLayer(id)) map.setLayoutProperty(id, 'visibility', visible ? 'visible' : 'none');
    });
}

export function setOpacity(map: MapLibreMap, l: Lapisan, opacity: number): void {
    if (map.getLayer(`${l.kod}::fill`)) map.setPaintProperty(`${l.kod}::fill`, 'fill-opacity', l.gaya.garis_sahaja ? 0.01 : opacity);
    if (map.getLayer(`${l.kod}::circle`)) map.setPaintProperty(`${l.kod}::circle`, 'circle-opacity', Math.max(0.15, opacity + 0.3));
}

/** Stack layers bottom→top in `order`, below the highlight overlay. */
export function applyOrder(map: MapLibreMap, order: string[]): void {
    const before = map.getLayer('sorotan::fill') ? 'sorotan::fill' : undefined;
    order.forEach((kod) => idsFor(kod).forEach((id) => map.getLayer(id) && map.moveLayer(id, before)));
}

export function mapBbox(map: MapLibreMap): Bbox {
    const b = map.getBounds();
    return [b.getWest(), b.getSouth(), b.getEast(), b.getNorth()];
}

/** [minLng, minLat, maxLng, maxLat] over any GeoJSON geometry list. */
export function boundsOf(geoms: Array<GeoJSON.Geometry | null | undefined>): Bbox | null {
    let b: Bbox | null = null;
    const visit = (c: unknown): void => {
        if (!Array.isArray(c)) return;
        if (typeof c[0] === 'number' && typeof c[1] === 'number') {
            const [x, y] = c as [number, number];
            b = b ? [Math.min(b[0], x), Math.min(b[1], y), Math.max(b[2], x), Math.max(b[3], y)] : [x, y, x, y];
            return;
        }
        c.forEach(visit);
    };
    geoms.forEach((g) => {
        if (!g) return;
        if (g.type === 'GeometryCollection') g.geometries.forEach((x) => visit((x as { coordinates?: unknown }).coordinates));
        else visit((g as { coordinates: unknown }).coordinates);
    });
    return b;
}

/** Ground resolution (m / CSS px) at a latitude for MapLibre's 512 px tiles. */
export function metresPerPixel(lat: number, zoom: number): number {
    return (40075016.686 * Math.cos((lat * Math.PI) / 180)) / (512 * 2 ** zoom);
}

/** Print scale 1:N for a 96-dpi screen. */
export function skalaNisbah(lat: number, zoom: number): number {
    return Math.round((metresPerPixel(lat, zoom) * 96) / 0.0254);
}

// ---- Geodesic measurement (SRS-GIS-10) ------------------------------------------

const R = 6371008.8;
const rad = (d: number) => (d * Math.PI) / 180;

export function jarakHaversine(a: [number, number], b: [number, number]): number {
    const dLat = rad(b[1] - a[1]);
    const dLng = rad(b[0] - a[0]);
    const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a[1])) * Math.cos(rad(b[1])) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(h));
}

export function panjangGaris(points: Array<[number, number]>): number {
    let total = 0;
    for (let i = 1; i < points.length; i++) total += jarakHaversine(points[i - 1], points[i]);
    return total;
}

/** Spherical polygon area (m²) — same method as turf.area. */
export function luasPoligon(points: Array<[number, number]>): number {
    if (points.length < 3) return 0;
    const ring = [...points, points[0]];
    let total = 0;
    for (let i = 0; i < ring.length - 1; i++) {
        const [x1, y1] = ring[i];
        const [x2, y2] = ring[i + 1];
        total += rad(x2 - x1) * (2 + Math.sin(rad(y1)) + Math.sin(rad(y2)));
    }
    return Math.abs((total * R * R) / 2);
}
