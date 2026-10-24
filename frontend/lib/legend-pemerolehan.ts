/*
 * SRS-PER-28: one visual identity per acquisition channel, used everywhere a
 * channel appears (case list, lot profile, map, reports). The backend seeds
 * the same colour/icon into saluran_pemerolehan (app/Modules/Per/Support/
 * ChannelCatalog.php) and returns it in every API response; this file is the
 * frontend fallback + the icon paths. If a shared frontend/lib/legend.ts is
 * introduced later, merge this into it.
 */

export type KodSaluran =
    | 'pemberimilikan_5a'
    | 'serah_milik'
    | 'faraid'
    | 'daftar_kematian'
    | 'perintah_arb'
    | 'serahan_hakmilik'
    | 'perintah_mahkamah'
    | 'pusaka_jkptgs'
    | 'wakaf'
    | 'pembelian_terus'
    | 'tanah_warta';

export interface LegendSaluran {
    kod: KodSaluran;
    nama: string;
    singkatan: string;
    warna: string;
    ikon: string;
}

export const LEGEND_SALURAN: Record<KodSaluran, LegendSaluran> = {
    pemberimilikan_5a: { kod: 'pemberimilikan_5a', nama: 'Pemberimilikan Tanah (Seksyen 5A)', singkatan: '5A', warna: '#2563EB', ikon: 'dokumen_tanah' },
    serah_milik: { kod: 'serah_milik', nama: 'Serah Milik', singkatan: 'SM', warna: '#0891B2', ikon: 'serah' },
    faraid: { kod: 'faraid', nama: 'Faraid', singkatan: 'FRD', warna: '#7C3AED', ikon: 'timbangan' },
    daftar_kematian: { kod: 'daftar_kematian', nama: 'Daftar Kematian', singkatan: 'DK', warna: '#475569', ikon: 'daftar' },
    perintah_arb: { kod: 'perintah_arb', nama: 'Perintah ARB', singkatan: 'ARB', warna: '#B45309', ikon: 'cop' },
    serahan_hakmilik: { kod: 'serahan_hakmilik', nama: 'Serahan Hakmilik', singkatan: 'SH', warna: '#0D9488', ikon: 'kunci' },
    perintah_mahkamah: { kod: 'perintah_mahkamah', nama: 'Perintah Mahkamah (LA/Probet)', singkatan: 'PM', warna: '#BE123C', ikon: 'mahkamah' },
    pusaka_jkptgs: { kod: 'pusaka_jkptgs', nama: 'Pusaka JKPTGS', singkatan: 'JKPTG', warna: '#C2410C', ikon: 'pusaka' },
    wakaf: { kod: 'wakaf', nama: 'Wakaf', singkatan: 'WKF', warna: '#15803D', ikon: 'masjid' },
    pembelian_terus: { kod: 'pembelian_terus', nama: 'Pembelian Terus', singkatan: 'PT', warna: '#A21CAF', ikon: 'wang' },
    tanah_warta: { kod: 'tanah_warta', nama: 'Tanah Warta', singkatan: 'TW', warna: '#4D7C0F', ikon: 'warta' },
};

/** 24×24 outline icon paths (Heroicons-style strokes) per `ikon` key. */
export const IKON_SALURAN: Record<string, string> = {
    dokumen_tanah:
        'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
    serah: 'M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5',
    timbangan:
        'M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 01-2.031.352 5.989 5.989 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971z',
    daftar: 'M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z',
    cop: 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
    kunci: 'M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z',
    mahkamah:
        'M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z',
    pusaka: 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
    masjid: 'M12 2.25c-1.5 1.5-3 2.25-3 4.5a3 3 0 006 0c0-2.25-1.5-3-3-4.5zM4.5 21V12a7.5 7.5 0 0115 0v9M3 21h18M9.75 21v-4.5a2.25 2.25 0 014.5 0V21',
    wang: 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z',
    warta: 'M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25M16.5 7.5V18a2.25 2.25 0 002.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 002.25 2.25h13.5M6 7.5h3v3H6v-3z',
};

export function legendFor(kod: string): LegendSaluran | undefined {
    return LEGEND_SALURAN[kod as KodSaluran];
}

/** Translucent background for a chip in the channel colour (hex + alpha). */
export function warnaLatar(hex: string, alpha = 0.12): string {
    const n = parseInt(hex.replace('#', ''), 16);
    return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
}
