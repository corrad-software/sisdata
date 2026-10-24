/*
 * Drawing / measuring on the map (SRS-GIS-10 measure, GIS-17 draw polygon):
 * click to add vertices, double-click or "Selesai" to finish. Rendered in a
 * dedicated GeoJSON source on top of every layer.
 */
import type { Map as MapLibreMap } from 'maplibre-gl';

export type ModLukis = 'jarak' | 'luas' | 'poligon' | 'titik' | null;

export const WARNA_LUKIS = '#2563EB';

export function pasangLukisan(map: MapLibreMap): void {
    if (map.getSource('lukisan')) return;
    map.addSource('lukisan', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
    map.addLayer({ id: 'lukisan::fill', type: 'fill', source: 'lukisan', filter: ['==', ['geometry-type'], 'Polygon'] as never, paint: { 'fill-color': WARNA_LUKIS, 'fill-opacity': 0.15 } });
    map.addLayer({ id: 'lukisan::line', type: 'line', source: 'lukisan', paint: { 'line-color': WARNA_LUKIS, 'line-width': 2, 'line-dasharray': [2, 1] } });
    map.addLayer({ id: 'lukisan::pt', type: 'circle', source: 'lukisan', filter: ['==', ['geometry-type'], 'Point'] as never, paint: { 'circle-color': '#fff', 'circle-radius': 4, 'circle-stroke-color': WARNA_LUKIS, 'circle-stroke-width': 2 } });
}

export function geometriLukisan(mod: ModLukis, points: Array<[number, number]>): GeoJSON.Geometry | null {
    if (points.length === 0) return null;
    if (mod === 'titik') return { type: 'Point', coordinates: points[0] };
    if ((mod === 'luas' || mod === 'poligon') && points.length >= 3) return { type: 'Polygon', coordinates: [[...points, points[0]]] };
    if (points.length >= 2) return { type: 'LineString', coordinates: points };

    return { type: 'Point', coordinates: points[0] };
}

export function lukis(map: MapLibreMap, mod: ModLukis, points: Array<[number, number]>): void {
    const g = geometriLukisan(mod, points);
    const features: GeoJSON.Feature[] = points.map((p) => ({ type: 'Feature', properties: {}, geometry: { type: 'Point', coordinates: p } }));
    if (g && g.type !== 'Point') features.unshift({ type: 'Feature', properties: {}, geometry: g });
    (map.getSource('lukisan') as { setData?: (d: unknown) => void } | undefined)?.setData?.({ type: 'FeatureCollection', features });
}
