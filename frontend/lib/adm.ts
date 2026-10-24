import { api, ApiError } from './api';

/* MOD-ADM · Pentadbiran Sistem — API helpers and types (/api/v1/adm/*). */

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export function qs(params: Record<string, string | number | boolean | null | undefined | string[]>): string {
    const p = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
        if (v === null || v === undefined || v === '') continue;
        if (Array.isArray(v)) v.forEach((x) => p.append(`${k}[]`, x));
        else p.set(k, String(v));
    }
    const s = p.toString();
    return s ? `?${s}` : '';
}

/** Malay error text for a failed call (API messages are already Malay). */
export function errorText(e: unknown, fallback = 'Permintaan gagal.'): string {
    return e instanceof ApiError ? e.message || fallback : fallback;
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) return '—';
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? value : d.toLocaleString('ms-MY', { dateStyle: 'medium', timeStyle: 'short' });
}

/* ---------- Parameter (SRS-ADM-01) ---------- */

export type ParamType = 'int' | 'bool' | 'string' | 'json';

export interface ParameterItem {
    kunci: string;
    label: string;
    jenis: ParamType;
    kritikal: boolean;
    lalai: unknown;
    nilai: unknown;
    ditetapkan: boolean;
    dikemas_kini_pada: string | null;
    id_kelulusan_menunggu: number | null;
}

export interface ParameterGroup {
    modul: string;
    label: string;
    parameter: ParameterItem[];
}

export const fetchParameters = () => api.get<{ data: ParameterGroup[] }>('/adm/parameter');
export const updateParameter = (kunci: string, nilai: unknown, justifikasi: string) =>
    api.put<{ message: string; data: unknown }>(`/adm/parameter/${encodeURIComponent(kunci)}`, { nilai, justifikasi: justifikasi || null });

/* ---------- Kod rujukan (SRS-ADM-02) ---------- */

export interface KodRujukan {
    id: number;
    jenis_kod: string;
    kod: string;
    keterangan: string;
    kod_induk: string | null;
    susunan: number;
    status: 'aktif' | 'tidak_aktif';
    tarikh_mula: string;
    tarikh_tamat: string | null;
    versi: number;
    updated_at: string;
}

export interface JenisKod {
    jenis_kod: string;
    label: string;
    induk: string | null;
    jumlah: number;
    aktif: number;
}

export interface ImportReport {
    diterima: number;
    dikemas_kini: number;
    ditolak: number;
    ralat: { baris: number; kod: string | null; mesej: string[] }[];
}

export const fetchJenisKod = () => api.get<{ data: JenisKod[] }>('/adm/kod-rujukan/jenis');
export const fetchKodRujukan = (p: { jenis?: string; q?: string; status?: string; page?: number }) =>
    api.get<Paginated<KodRujukan>>(`/adm/kod-rujukan${qs(p)}`);
export const fetchKodSejarah = (id: number) =>
    api.get<{ data: { semasa: KodRujukan; versi: { versi: number; data: Record<string, unknown>; created_at: string }[] } }>(
        `/adm/kod-rujukan/${id}/sejarah`,
    );

/* ---------- Templat laporan (SRS-ADM-03) ---------- */

export interface Templat {
    id: number;
    kod: string;
    nama: string;
    jenis: string;
    format_output: string;
    kandungan: string;
    medan: string[] | null;
    versi: number;
    status: string;
    updated_at: string;
}

export const TEMPLAT_FORMATS = ['pdf', 'xlsx', 'csv', 'html', 'docx'];

/* ---------- Kelulusan konfigurasi (SRS-ADM-18/19) ---------- */

export interface Kelulusan {
    id: number;
    jenis_konfig: string;
    kunci: string;
    nilai_sebelum: unknown;
    nilai_selepas: unknown;
    justifikasi: string;
    status: 'menunggu' | 'lulus' | 'tolak' | 'dibatalkan';
    pemohon: { id: number; name?: string } | null;
    pelulus: { id: number; name?: string } | null;
    alamat_ip_pemohon: string | null;
    catatan_pelulus: string | null;
    id_instans_aliran: string | null;
    dimohon_pada: string;
    diputuskan_pada: string | null;
    boleh_putus: boolean;
    boleh_batal: boolean;
}

export const fetchKelulusan = (p: { status?: string; page?: number }) =>
    api.get<Paginated<Kelulusan> & { mod: 'wfe' | 'terbina' }>(`/adm/kelulusan${qs(p)}`);

/* ---------- Tugasan berjadual (SRS-ADM-06..10) ---------- */

export interface Tugasan {
    id: number;
    kod: string;
    nama: string;
    keterangan: string | null;
    kategori: string;
    arahan: string;
    argumen: Record<string, unknown> | null;
    jenis_jadual: 'cron' | 'selang';
    ungkapan_cron: string | null;
    selang_minit: number | null;
    aktif: boolean;
    cubaan_semula: number;
    tempoh_tamat_masa_saat: number;
    toleransi_lewat_minit: number | null;
    sumber: 'adm' | 'sistem';
    terakhir_mula_pada: string | null;
    terakhir_status: string | null;
    seterusnya_pada: string | null;
    kadar_kejayaan_30h?: number | null;
}

export interface TugasanLarian {
    id: number;
    pencetus: string;
    cubaan: number;
    tarikh_mula: string | null;
    tarikh_tamat: string | null;
    tempoh_saat: number | null;
    status: string;
    bilangan_rekod: number | null;
    mesej_ralat: string | null;
    id_korelasi: string | null;
}

export interface TugasanSistem {
    kunci: string;
    perihal: string;
    arahan: string;
    ungkapan: string;
    seterusnya: string | null;
    id_tugasan: number | null;
}

export const KATEGORI_TUGASAN: Record<string, string> = {
    pengemaskinian_data: 'Pengemaskinian data',
    notifikasi: 'Notifikasi berjadual',
    laporan: 'Laporan berkala',
    pengarkiban: 'Pengarkiban dokumen & audit',
    integrasi: 'Integrasi berjadual',
    penyelenggaraan: 'Penyelenggaraan sistem',
};

/* ---------- Log (SRS-ADM-11/14) ---------- */

export interface LogRalat {
    id: number;
    id_korelasi: string | null;
    tarikh_masa: string;
    aras_keterukan: string;
    saluran: string;
    modul: string | null;
    kod_ralat: string | null;
    mesej: string;
    jejak_tindanan?: string | null;
    id_pengguna: number | null;
    alamat_ip: string | null;
    konteks?: Record<string, unknown> | null;
}

export interface LogIntegrasi {
    id: number;
    kod_integrasi: string;
    id_korelasi: string | null;
    tarikh_masa: string;
    arah: string;
    operasi: string | null;
    berjaya: boolean;
    status_http: number | null;
    tempoh_ms: number | null;
    bilangan_rekod: number | null;
    mesej: string | null;
}

/* ---------- Kesihatan (SRS-ADM-16) ---------- */

export type HealthStatus = 'ok' | 'amaran' | 'gagal' | 'tidak_aktif';

export interface Kesihatan {
    status_keseluruhan: HealthStatus;
    perkhidmatan: { kod: string; label: string; status: HealthStatus; mesej: string | null; masa_ms: number; butiran: Record<string, unknown> }[];
    baris_gilir: { saiz?: number; ambang?: number; gagal?: number; horizon_aktif?: boolean | null; sambungan?: string } | null;
    storan: { jumlah_bait?: number; bebas_bait?: number; guna_peratus?: number; ambang?: number } | null;
    pangkalan_data: { saiz_bait?: number; sambungan?: number; sambungan_maks?: number } | null;
    integrasi: {
        kod: string;
        nama: string;
        status: string;
        kegagalan_berturut: number;
        terakhir_berjaya_pada: string | null;
        terakhir_gagal_pada: string | null;
        status_kesihatan: HealthStatus;
    }[];
    ralat_terkini: LogRalat[];
    semakan_terakhir: string | null;
    dijana_pada: string;
}

export const fetchKesihatan = (segar = false) => api.get<{ data: Kesihatan }>(`/adm/kesihatan${segar ? '?segar=1' : ''}`);

export function formatBytes(n: number | undefined): string {
    if (n === undefined || n === null) return '—';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;
    let v = n;
    while (v >= 1024 && i < units.length - 1) {
        v /= 1024;
        i++;
    }
    return `${v.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

/* ---------- Integrasi & rahsia (SRS-ADM-04/28) ---------- */

export interface Integrasi {
    id: number;
    kod: string;
    nama: string;
    jenis: string;
    endpoint: string;
    id_rahsia: string | null;
    tempoh_tamat_masa: number;
    kadar_capaian: number | null;
    jadual: string | null;
    status: 'aktif' | 'tidak_aktif';
    kegagalan_berturut: number;
    terakhir_berjaya_pada: string | null;
    terakhir_gagal_pada: string | null;
    rahsia_wujud: boolean;
}

export interface Rahsia {
    id: number;
    kunci: string;
    keterangan: string | null;
    updated_at: string;
}

/* ---------- Carian (SRS-ADM-20..22) ---------- */

export interface SearchHit {
    id: string;
    title: string;
    subtitle?: string;
    link: string;
    entiti: string;
}

export interface SearchGroup {
    entiti: string;
    label: string;
    pemberat: number;
    hasil: SearchHit[];
}

export const globalSearch = (q: string, had?: number) =>
    api.get<{ data: SearchGroup[]; jumlah: number; istilah: string[] }>(`/adm/carian${qs({ q, had })}`);

export interface IndeksCarian {
    id: number;
    entiti: string;
    label: string | null;
    medan: string[] | null;
    pemberat: number;
    aktif: boolean;
    status_indeks: string | null;
    diindeks_semula_pada: string | null;
    berdaftar: boolean;
}

export interface SinonimCarian {
    id: number;
    istilah: string;
    sinonim: string[];
}

/* ---------- Penyelenggaraan (SRS-ADM-29) ---------- */

export interface Penyelenggaraan {
    aktif: boolean;
    notis: string;
    tamat_dijangka: string | null;
}

export const fetchPenyelenggaraan = () => api.get<{ data: Penyelenggaraan }>('/adm/penyelenggaraan');

export const STATUS_LABEL: Record<string, string> = {
    ok: 'OK',
    amaran: 'Amaran',
    gagal: 'Gagal',
    tidak_aktif: 'Tidak aktif',
    aktif: 'Aktif',
    menunggu: 'Menunggu',
    lulus: 'Lulus',
    tolak: 'Ditolak',
    dibatalkan: 'Dibatalkan',
    beratur: 'Beratur',
    berjalan: 'Berjalan',
    berjaya: 'Berjaya',
    dilangkau: 'Dilangkau',
};

export function statusVariant(status: string | null | undefined): 'success' | 'danger' | 'warning' | 'info' | 'neutral' {
    switch (status) {
        case 'ok':
        case 'aktif':
        case 'lulus':
        case 'berjaya':
            return 'success';
        case 'gagal':
        case 'tolak':
            return 'danger';
        case 'amaran':
        case 'menunggu':
        case 'dilangkau':
            return 'warning';
        case 'berjalan':
        case 'beratur':
            return 'info';
        default:
            return 'neutral';
    }
}
