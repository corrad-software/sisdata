/*
 * SRS-GIS-33: compose a printable map image in the browser — title, map,
 * legend, scale, north arrow, projection, timestamp and the generating user —
 * from MapLibre's canvas (created with preserveDrawingBuffer). PNG is
 * downloaded directly (and audited via /gis/cetak/log); PDF is composed by
 * the API from the bare map image.
 */
import type { Map as MapLibreMap } from 'maplibre-gl';
import type { Lapisan } from '@/lib/gis';
import { skalaNisbah } from './gaya';

export interface MaklumatCetak {
    tajuk: string;
    pengguna: string;
    layers: Lapisan[];
}

export const UNJURAN = 'Paparan EPSG:3857 (Web Mercator) · koordinat EPSG:4326 (WGS 84)';

export function skalaTeks(map: MapLibreMap): string {
    return `1 : ${skalaNisbah(map.getCenter().lat, map.getZoom()).toLocaleString('ms-MY')}`;
}

export function imejPeta(map: MapLibreMap): string {
    map.triggerRepaint();
    return map.getCanvas().toDataURL('image/png');
}

export async function gubahPng(map: MapLibreMap, info: MaklumatCetak): Promise<string> {
    const src = map.getCanvas();
    const img = new Image();
    img.src = imejPeta(map);
    await img.decode();

    const pad = 24;
    const legendRows = info.layers.reduce((n, l) => n + 1 + Math.ceil(l.legenda.entri.length / 3), 0);
    const legendH = Math.max(60, legendRows * 18 + 16);
    const w = src.width;
    const h = src.height;
    const canvas = document.createElement('canvas');
    canvas.width = w + pad * 2;
    canvas.height = h + pad * 2 + 48 + legendH + 56;
    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('Kanvas tidak disokong.');

    ctx.fillStyle = '#FFFFFF';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#18181B';
    ctx.font = 'bold 20px Inter, Arial, sans-serif';
    ctx.fillText(info.tajuk, pad, pad + 20);

    const top = pad + 40;
    ctx.drawImage(img, pad, top, w, h);
    ctx.strokeStyle = '#A1A1AA';
    ctx.strokeRect(pad, top, w, h);

    // North arrow (the map is never rotated in the workspace — bearing 0).
    const nx = pad + w - 40;
    const ny = top + 16;
    ctx.fillStyle = 'rgba(255,255,255,0.9)';
    ctx.fillRect(nx - 6, ny - 4, 36, 50);
    ctx.fillStyle = '#18181B';
    ctx.beginPath();
    ctx.moveTo(nx + 12, ny);
    ctx.lineTo(nx + 22, ny + 26);
    ctx.lineTo(nx + 12, ny + 20);
    ctx.lineTo(nx + 2, ny + 26);
    ctx.closePath();
    ctx.fill();
    ctx.font = 'bold 13px Inter, Arial, sans-serif';
    ctx.fillText('U', nx + 7, ny + 42);

    // Scale bar (100 px) + ratio.
    const lat = map.getCenter().lat;
    const mpp = (40075016.686 * Math.cos((lat * Math.PI) / 180)) / (512 * 2 ** map.getZoom());
    const ratio = src.width / (src.clientWidth || src.width);
    const barPx = 120 * ratio;
    const metres = mpp * 120;
    const sy = top + h - 18;
    ctx.fillStyle = 'rgba(255,255,255,0.9)';
    ctx.fillRect(pad + 8, sy - 16, barPx + 90, 26);
    ctx.fillStyle = '#18181B';
    ctx.fillRect(pad + 14, sy, barPx, 4);
    ctx.font = '12px Inter, Arial, sans-serif';
    ctx.fillText(metres >= 1000 ? `${(metres / 1000).toFixed(2)} km` : `${Math.round(metres)} m`, pad + 20 + barPx, sy + 5);

    // Legend.
    let y = top + h + 22;
    ctx.font = 'bold 13px Inter, Arial, sans-serif';
    ctx.fillText('Petunjuk', pad, y);
    y += 6;
    info.layers.forEach((l) => {
        y += 18;
        ctx.font = 'bold 12px Inter, Arial, sans-serif';
        ctx.fillStyle = '#3F3F46';
        ctx.fillText(l.nama, pad, y);
        l.legenda.entri.forEach((e, i) => {
            if (i % 3 === 0) y += 18;
            const x = pad + 12 + (i % 3) * 230;
            ctx.fillStyle = e.warna;
            ctx.fillRect(x, y - 10, 12, 12);
            ctx.fillStyle = '#3F3F46';
            ctx.font = '12px Inter, Arial, sans-serif';
            ctx.fillText(e.label.slice(0, 32), x + 18, y);
        });
    });

    // Footer: scale, projection, timestamp, user.
    const fy = canvas.height - pad - 16;
    ctx.fillStyle = '#52525B';
    ctx.font = '12px Inter, Arial, sans-serif';
    ctx.fillText(`Skala ${skalaTeks(map)} · ${UNJURAN}`, pad, fy);
    ctx.fillText(`Dijana ${new Date().toLocaleString('ms-MY')} oleh ${info.pengguna} · SISDATA MAIS`, pad, fy + 18);

    return canvas.toDataURL('image/png');
}
