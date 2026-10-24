/*
 * MOD-GIS frontend helpers: API client, types and hooks for the map
 * workspace (/admin/gis) and the embeddable <PetaSisdata /> component.
 * Every call is authorised by the API itself (SRS-GIS-32); hiding here is UI only.
 */
import { useQuery } from '@tanstack/react-query';
import { useSyncExternalStore } from 'react';
import { api } from '@/lib/api';
import type { User } from '@/lib/types';

export type Bbox = [number, number, number, number];

export interface EntriLegenda {
    nilai: string;
    label: string;
    warna: string;
}

export interface Legenda {
    medan: string | null;
    entri: EntriLegenda[];
}

export interface MetadataLapisan {
    sumber_data: string | null;
    pemilik_data: string | null;
    tarikh_kemas_kini: string | null;
    status_pengesahan: string;
    sistem_unjuran: string;
    tahap_ketepatan: string | null;
    had_penggunaan: string | null;
    catatan: string | null;
}

export interface Lapisan {
    kod: string;
    nama: string;
    kumpulan: string;
    jenis_geometri: 'polygon' | 'line' | 'point';
    legenda: Legenda;
    klasifikasi: 'awam' | 'dalaman' | 'sulit' | 'rahsia';
    sumber: 'mvt' | 'geojson';
    urutan: number;
    ketelusan: number;
    papar_lalai: boolean;
    status_terbit: string;
    terbit_ogc: boolean;
    gaya: { garis_putus?: string; garis_tebal?: boolean; peta_haba?: string; garis_sahaja?: boolean };
    metadata: MetadataLapisan | null;
}

export interface SlotWajib {
    bil: number;
    kod: string;
    nama: string;
    modul: string;
    lapisan: string[];
    dibenarkan: string[];
    status: 'tersedia' | 'interim' | 'menunggu_modul';
    legenda: EntriLegenda[];
}

export interface PetaAsas {
    kod: string;
    nama: string;
    jenis: 'raster' | 'kosong';
    url: string | null;
    atribusi: string;
    aktif: boolean;
    perlu_lesen: boolean;
    catatan?: string;
}

export interface KatalogLapisan {
    lapisan: Lapisan[];
    wajib: SlotWajib[];
    kumpulan: Array<{ kod: string; nama: string }>;
    peta_asas: PetaAsas[];
}

export interface Ciri {
    type: 'Feature';
    id?: string | number;
    geometry: GeoJSON.Geometry | null;
    properties: Record<string, unknown>;
}

export interface KoleksiCiri {
    type: 'FeatureCollection';
    features: Ciri[];
}

export interface HasilCarian {
    label: string;
    jenis: string;
    lng: number;
    lat: number;
    bbox?: Bbox | null;
    id?: string | null;
    pautan?: string | null;
}

export type JenisAnalisis = 'penimbal' | 'tindihan' | 'pertanyaan' | 'keluasan' | 'kedekatan' | 'kluster';

export interface BarisHasil {
    id: string;
    label: string | null;
    pautan: string | null;
    lapisan: string;
    [k: string]: unknown;
}

export interface HasilAnalisis {
    jenis: JenisAnalisis;
    ringkasan: Record<string, unknown>;
    hasil: BarisHasil[];
    geometri: KoleksiCiri;
}

export interface Senario {
    id: number;
    nama: string;
    jenis_analisis: JenisAnalisis;
    parameter: Record<string, unknown>;
    ringkasan: Record<string, unknown>;
    kebenaran_kongsi: 'peribadi' | 'pengguna' | 'semua';
    milik_saya: boolean;
    dicipta_pada: string | null;
}

export interface PaparanTersimpan {
    id: number;
    nama: string;
    lapisan: string[];
    pusat: [number, number];
    zum: string | number;
    peta_asas: string;
    dikongsi: boolean;
    milik_saya: boolean;
}

export interface LogImport {
    id: string;
    nama_set_data: string;
    nama_fail: string;
    format: string;
    srid_asal: number | null;
    kategori: string;
    bilangan_ciri: number;
    bilangan_sah: number;
    bilangan_ralat: number;
    status: 'disemak' | 'ditolak' | 'sedia' | 'diterima' | 'dibatalkan';
    ralat_fail: string[];
    amaran: string[];
    medan: string[];
    medan_no_lot: string | null;
    klasifikasi: string;
    dicipta_pada: string | null;
    diterima_pada: string | null;
}

export interface RalatKualiti {
    id: number;
    id_lot: string | null;
    id_lot_lain: string | null;
    kod_daerah: string | null;
    kod_mukim: string | null;
    jenis_ralat: string;
    perihal: string;
    cadangan: string | null;
    status_pembetulan: 'baharu' | 'dalam_tindakan' | 'selesai' | 'diabaikan';
    catatan: string | null;
    nilai: number | null;
    pautan: string | null;
    pautan_lain: string | null;
    dikesan_pada: string;
}

export interface Halaman<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface VersiGeometri {
    id: number;
    versi: number;
    semasa: boolean;
    sumber_data: string;
    srid_asal: number;
    status_pengesahan: string;
    keluasan_m2: number;
    dicipta_pada: string;
    pemuat_naik: string | null;
    geometri: GeoJSON.Geometry;
}

export interface BandingVersi {
    versi_a: number;
    versi_b: number;
    luas_a_m2: number;
    luas_b_m2: number;
    beza_luas_m2: number;
    hausdorff_m: number;
    luas_ditambah_m2: number;
    luas_dibuang_m2: number;
    sama: boolean;
    ditambah: GeoJSON.Geometry | null;
    dibuang: GeoJSON.Geometry | null;
}

export interface BarisStatistik {
    kod: string;
    nama: string | null;
    bilangan_lot: number;
    ada_geometri: number;
    luas_m2: number;
    luas_hektar: number;
    wakaf: number;
    baitulmal: number;
    hartanah: number;
    geometri?: GeoJSON.Geometry;
}

export interface Statistik {
    peringkat: 'daerah' | 'mukim';
    baris: BarisStatistik[];
    jumlah: { bilangan_lot: number; ada_geometri: number; luas_hektar: number };
    kategori: Array<{ kod: string; bilangan: number }>;
    bahagian: Array<{ kod: string; bilangan: number }>;
}

const qs = (params: Record<string, string | number | boolean | null | undefined>) => {
    const p = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
        if (v !== null && v !== undefined && v !== '') p.set(k, String(v));
    });
    const s = p.toString();
    return s ? `?${s}` : '';
};

export function penapisQs(filters: Record<string, string> = {}): string {
    return Object.entries(filters)
        .filter(([, v]) => v !== '')
        .map(([k, v]) => `penapis[${encodeURIComponent(k)}]=${encodeURIComponent(v)}`)
        .join('&');
}

export const gisApi = {
    katalog: () => api.get<KatalogLapisan>('/gis/lapisan'),
    ciri: (kod: string, opts: { bbox?: Bbox; zoom?: number; penapis?: Record<string, string> } = {}) => {
        const base = qs({ bbox: opts.bbox?.map((n) => n.toFixed(6)).join(','), zoom: opts.zoom !== undefined ? Math.round(opts.zoom * 10) / 10 : undefined });
        const f = penapisQs(opts.penapis);
        return api.get<KoleksiCiri>(`/gis/lapisan/${kod}/ciri${base}${f ? (base ? '&' : '?') + f : ''}`);
    },
    cari: (q: string) => api.get<{ data: HasilCarian[] }>(`/gis/cari${qs({ q })}`),
    analisis: (jenis: JenisAnalisis, parameter: Record<string, unknown>) => api.post<HasilAnalisis>('/gis/analisis', { jenis, parameter }),
    senarai_senario: () => api.get<{ data: Senario[] }>('/gis/senario'),
    simpanSenario: (body: { nama: string; jenis: JenisAnalisis; parameter: Record<string, unknown>; kebenaran_kongsi: string; dikongsi_dengan?: number[] }) =>
        api.post<{ data: Senario }>('/gis/senario', body),
    bukaSenario: (id: number) => api.get<{ senario: Senario; hasil: HasilAnalisis }>(`/gis/senario/${id}`),
    padamSenario: (id: number) => api.delete(`/gis/senario/${id}`),
    paparan: () => api.get<{ data: PaparanTersimpan[] }>('/gis/paparan'),
    simpanPaparan: (body: Omit<PaparanTersimpan, 'id' | 'milik_saya'>) => api.post<{ data: PaparanTersimpan }>('/gis/paparan', body),
    padamPaparan: (id: number) => api.delete(`/gis/paparan/${id}`),
    statistik: (params: { peringkat?: string; kod_daerah?: string; geometri?: boolean }) => api.get<Statistik>(`/gis/statistik${qs({ ...params, geometri: params.geometri ? 1 : undefined })}`),
    imports: (page = 1) => api.get<Halaman<LogImport>>(`/gis/import${qs({ page })}`),
    import: (id: string) => api.get<{ data: LogImport; ralat_ciri: Array<{ no_ciri: number; ralat: string[]; atribut: Record<string, unknown> }> }>(`/gis/import/${id}`),
    ciriImport: (id: string) => api.get<KoleksiCiri>(`/gis/import/${id}/ciri`),
    muatNaik: (form: FormData) => api.postForm<{ data: LogImport }>('/gis/import', form),
    terima: (id: string, body: Record<string, string>) => api.post<{ data: LogImport }>(`/gis/import/${id}/terima`, body),
    batal: (id: string) => api.post<{ data: LogImport }>(`/gis/import/${id}/batal`),
    kualiti: (params: { status?: string; jenis_ralat?: string; page?: number }) => api.get<Halaman<RalatKualiti> & { ringkasan: Record<string, number> }>(`/gis/kualiti${qs(params)}`),
    ciriKualiti: () => api.get<KoleksiCiri>('/gis/kualiti/ciri'),
    jalankanKualiti: () => api.post<{ dikesan: Record<string, number>; ditutup: number; jumlah_terbuka: number }>('/gis/kualiti/jalankan'),
    kemasKiniKualiti: (id: number, body: { status_pembetulan: string; catatan?: string }) => api.put<{ data: RalatKualiti }>(`/gis/kualiti/${id}`, body),
    cadangan: (id: number) => api.get<{ asal: GeoJSON.Geometry | null; cadangan: GeoJSON.Geometry | null; sebab: string | null; pautan_pembetulan: string | null }>(`/gis/kualiti/${id}/cadangan`),
    versi: (lotId: string) => api.get<{ data: VersiGeometri[] }>(`/gis/lot/${lotId}/versi`),
    banding: (lotId: string, a: number, b: number) => api.get<BandingVersi>(`/gis/lot/${lotId}/versi/banding${qs({ a, b })}`),
    pentadbiranLapisan: () => api.get<{ data: Array<Record<string, unknown>> }>('/gis/pentadbiran/lapisan'),
    kemasKiniLapisan: (kod: string, body: Record<string, unknown>) => api.put(`/gis/pentadbiran/lapisan/${kod}`, body),
    kemasKiniMetadata: (kod: string, body: Record<string, unknown>) => api.put(`/gis/pentadbiran/lapisan/${kod}/metadata`, body),
    ogc: () => api.get<{ wms: string; wfs: string; lapisan: string[]; pengesahan: string }>('/gis/ogc'),
    logCetak: (body: { tajuk: string; format: 'png' | 'jpeg'; lapisan?: string[]; pusat?: number[]; zum?: number }) =>
        api.post<{ dijana_pada: string; dijana_oleh: string }>('/gis/cetak/log', body),
    tambahAset: (body: { nama: string; kategori: string; lng: number; lat: number; id_lot?: string; status_keadaan?: string }) => api.post<{ data: { id: number } }>('/gis/aset', body),
    /** Restore / publish a geometry through MOD-PRO's own spatial API (MOD-GIS never writes lot geometry). */
    hantarKeProfil: (lotId: string, geometri: GeoJSON.Geometry, sumber: string, rujukan: string) =>
        api.post(`/pro/lot/${lotId}/spatial`, { format: 'geojson', geometri: JSON.stringify(geometri), srid: 4326, sumber_data: sumber, rujukan_fail_sumber: rujukan }),
};

/** Downloads (CSV, Excel, PDF, spatial files) go through fetch so the session cookie and CSRF header are sent. */
export async function muatTurun(path: string, init: RequestInit = {}, fallbackName = 'muat-turun'): Promise<void> {
    const headers = new Headers(init.headers);
    if (init.method && init.method !== 'GET') {
        const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
        if (!m) await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
        const xsrf = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
        if (xsrf) headers.set('X-XSRF-TOKEN', decodeURIComponent(xsrf[1]));
        if (init.body && typeof init.body === 'string') headers.set('Content-Type', 'application/json');
    }
    headers.set('Accept', 'application/json, application/octet-stream, */*');
    const res = await fetch(`/api/v1${path}`, { ...init, headers, credentials: 'include' });
    if (!res.ok) {
        let message = 'Muat turun gagal.';
        try {
            message = ((await res.json()) as { message?: string }).message ?? message;
        } catch {
            /* not JSON */
        }
        throw new Error(message);
    }
    const blob = await res.blob();
    const cd = res.headers.get('content-disposition') ?? '';
    const name = /filename="?([^";]+)"?/.exec(cd)?.[1] ?? fallbackName;
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = name;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export function useKatalog() {
    return useQuery({ queryKey: ['gis', 'katalog'], queryFn: gisApi.katalog, staleTime: 60_000 });
}

export function useSaya() {
    return useQuery({ queryKey: ['me'], queryFn: () => api.get<{ data: User }>('/user').then((r) => r.data), staleTime: 60_000 });
}

const noopSubscribe = () => () => {};

/** UI hiding only — the API enforces every permission itself. */
export function useBoleh() {
    const { data } = useSaya();
    const hydrated = useSyncExternalStore(noopSubscribe, () => true, () => false);
    const perms = new Set(hydrated ? (data?.permissions ?? []) : []);
    return (...keys: string[]) => keys.some((k) => perms.has(k));
}

export const LABEL_KLASIFIKASI: Record<string, string> = { awam: 'Awam', dalaman: 'Dalaman', sulit: 'Sulit', rahsia: 'Rahsia' };

export const LABEL_RALAT: Record<string, string> = {
    geometri_tidak_sah: 'Geometri tidak sah',
    geometri_kosong: 'Geometri kosong',
    tiada_geometri: 'Tiada geometri',
    luar_selangor: 'Luar sempadan Selangor',
    luar_mukim: 'Luar sempadan mukim',
    pertindihan: 'Pertindihan lot',
    jurang: 'Jurang antara lot',
};

export const LABEL_STATUS_RALAT: Record<string, string> = { baharu: 'Baharu', dalam_tindakan: 'Dalam tindakan', selesai: 'Selesai', diabaikan: 'Diabaikan' };

export const LABEL_ANALISIS: Record<JenisAnalisis, string> = {
    penimbal: 'Analisis Penimbal (Buffer)',
    tindihan: 'Analisis Tindihan (Overlay)',
    pertanyaan: 'Pertanyaan Spatial',
    keluasan: 'Pengiraan Keluasan',
    kedekatan: 'Analisis Kedekatan (Proximity)',
    kluster: 'Kluster & Peta Haba',
};

export function formatLuas(m2: number | null | undefined): string {
    if (m2 === null || m2 === undefined || Number.isNaN(m2)) return '—';
    if (m2 >= 10000) return `${(m2 / 10000).toLocaleString('ms-MY', { maximumFractionDigits: 4 })} hektar`;
    return `${m2.toLocaleString('ms-MY', { maximumFractionDigits: 2 })} m²`;
}

export function formatJarak(m: number | null | undefined): string {
    if (m === null || m === undefined || Number.isNaN(m)) return '—';
    return m >= 1000 ? `${(m / 1000).toLocaleString('ms-MY', { maximumFractionDigits: 3 })} km` : `${m.toLocaleString('ms-MY', { maximumFractionDigits: 1 })} m`;
}
