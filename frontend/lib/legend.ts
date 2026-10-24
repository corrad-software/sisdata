/**
 * SRS-HKM-20 — the ONE colour legend for title types and land-use categories,
 * used by lists, the lot profile, maps (MOD-GIS) and reports. Keys are MOD-REF
 * codes (`ref_jenis_hak_milik.kod`, `ref_kategori_guna_tanah.kod`); a backend
 * test (ProfileAndSearchTest::test_SRS_HKM_20_…) fails if a reference code has
 * no colour here.
 *
 * Each entry has:
 *   - `warna`  — the main colour (map fill / outline, chart series, swatch)
 *   - `latar`  — a light tint for badges and table chips (text on it: `teks`)
 *   - `teks`   — text colour that passes WCAG AA on `latar`
 *   - `label`  — Malay label for legends
 * Plain TS (no React) so GIS layers, charts and PDF templates can import it.
 */

export interface WarnaLegenda {
    warna: string;
    latar: string;
    teks: string;
    label: string;
}

/** Jenis hak milik (ref_jenis_hak_milik). Permanent titles: blues/greens; temporary: ambers; licences/reserves: greys/purples. */
export const WARNA_JENIS_HAK_MILIK: Record<string, WarnaLegenda> = {
    GRN: { warna: '#1D4ED8', latar: '#DBEAFE', teks: '#1E3A8A', label: 'Geran' },
    PN: { warna: '#0F766E', latar: '#CCFBF1', teks: '#134E4A', label: 'Pajakan Negeri' },
    GM: { warna: '#4338CA', latar: '#E0E7FF', teks: '#312E81', label: 'Geran Mukim' },
    PM: { warna: '#15803D', latar: '#DCFCE7', teks: '#14532D', label: 'Pajakan Mukim' },
    HSD: { warna: '#B45309', latar: '#FEF3C7', teks: '#78350F', label: 'Hakmilik Sementara (Daftar)' },
    HSM: { warna: '#C2410C', latar: '#FFEDD5', teks: '#7C2D12', label: 'Hakmilik Sementara (Mukim)' },
    TOL: { warna: '#BE185D', latar: '#FCE7F3', teks: '#831843', label: 'Lesen Pendudukan Sementara (TOL)' },
    RIZAB: { warna: '#6D28D9', latar: '#EDE9FE', teks: '#4C1D95', label: 'Tanah Rizab' },
};

/** Kategori guna tanah (ref_kategori_guna_tanah, KTN s.52/53). */
export const WARNA_KATEGORI_GUNA_TANAH: Record<string, WarnaLegenda> = {
    PERTANIAN: { warna: '#16A34A', latar: '#DCFCE7', teks: '#14532D', label: 'Pertanian' },
    BANGUNAN: { warna: '#DC2626', latar: '#FEE2E2', teks: '#7F1D1D', label: 'Bangunan' },
    PERUSAHAAN: { warna: '#7C3AED', latar: '#EDE9FE', teks: '#4C1D95', label: 'Perusahaan' },
    TIADA: { warna: '#71717A', latar: '#F4F4F5', teks: '#27272A', label: 'Tiada kategori' },
};

/** Bahagian pemilik lot (hartanah / wakaf / baitulmal) — "jenis tanah" MAIS. */
export const WARNA_BAHAGIAN_PEMILIK: Record<string, WarnaLegenda> = {
    hartanah: { warna: '#2563EB', latar: '#DBEAFE', teks: '#1E3A8A', label: 'Hartanah MAIS' },
    wakaf: { warna: '#059669', latar: '#D1FAE5', teks: '#064E3B', label: 'Wakaf' },
    baitulmal: { warna: '#D97706', latar: '#FEF3C7', teks: '#78350F', label: 'Baitulmal' },
};

/** Fallback for a code added to MOD-REF before it gets a colour here. */
export const WARNA_LALAI: WarnaLegenda = { warna: '#52525B', latar: '#F4F4F5', teks: '#27272A', label: 'Lain-lain' };

export function warnaJenisHakMilik(kod: string | null | undefined): WarnaLegenda {
    return (kod && WARNA_JENIS_HAK_MILIK[kod]) || WARNA_LALAI;
}

export function warnaKategori(kod: string | null | undefined): WarnaLegenda {
    return (kod && WARNA_KATEGORI_GUNA_TANAH[kod]) || WARNA_LALAI;
}

export function warnaBahagian(kod: string | null | undefined): WarnaLegenda {
    return (kod && WARNA_BAHAGIAN_PEMILIK[kod]) || WARNA_LALAI;
}

/** Ordered entries for rendering a legend (map control, report footer…). */
export function senaraiLegenda(peta: Record<string, WarnaLegenda>): Array<WarnaLegenda & { kod: string }> {
    return Object.entries(peta).map(([kod, w]) => ({ kod, ...w }));
}
