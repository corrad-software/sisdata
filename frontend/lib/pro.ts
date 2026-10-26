import { useSyncExternalStore } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api, ApiError } from './api';
import type { User } from './types';

/* MOD-PRO · Pangkalan Data Induk dan Profil Hartanah 360° — client for /api/v1/pro/*. */

// ---------------------------------------------------------------- shared

export interface Meta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface Paged<T> {
    data: T[];
    meta: Meta;
}

export interface Ruj {
    kod: string;
    nama: string;
}

export interface Rujukan {
    daerah: Ruj[];
    mukim: Array<Ruj & { daerah_kod: string }>;
    kategori_guna_tanah: Ruj[];
    bahagian_pemilik: Ruj[];
    status_lot: Ruj[];
    klasifikasi: Ruj[];
    unit_keluasan: Ruj[];
    status_pengesahan: Ruj[];
    sumber_spatial: Ruj[];
    srid: Array<{ kod: number | string; nama: string }>;
    medan_kritikal: string[];
}

export type StatusPengesahan = 'belum_disahkan' | 'disahkan_juruukur' | 'disahkan_ptg' | 'bertindih' | 'tiada_geometri';

export interface SpatialFlag {
    status: StatusPengesahan | string;
    disahkan: boolean;
    label: string;
    keluasan_dikira?: number | null;
    peratus_beza_keluasan?: number | null;
}

export interface LotRingkas {
    id: string;
    no_lot: string | null;
    nama_hartanah?: string | null;
    kod_negeri: string | null;
    kod_daerah: string | null;
    nama_daerah: string | null;
    kod_mukim: string | null;
    nama_mukim: string | null;
    keluasan: number | null;
    unit_keluasan: string | null;
    kategori_guna_tanah: string | null;
    nama_kategori_guna_tanah: string | null;
    zoning: string | null;
    bahagian_pemilik: string | null;
    status_lot: string;
    klasifikasi: string;
    versi_rekod: number;
    id_data_steward: number | null;
    sintetik: boolean;
    tag: string[];
    spatial: SpatialFlag;
    dikemas_kini_pada: string | null;
    medan_disamarkan: string[];
}

export interface LotButiran extends LotRingkas {
    syarat_nyata: string | null;
    catatan: string | null;
    id_data_owner: number | null;
    nama_data_owner: string | null;
    nama_data_steward: string | null;
    salasilah: {
        sumber_sistem: string | null;
        fail_asal: string | null;
        kunci_asal: string | null;
        id_migrasi_larian: string | null;
        id_sesi_import: string | null;
    };
    id_lot_gabung: string | null;
    diarkib_pada: string | null;
    dicipta_pada: string | null;
}

export type GeoJsonGeometry = { type: string; coordinates: unknown };

export interface LotFeature {
    type: 'Feature';
    id?: string;
    geometry: GeoJsonGeometry | null;
    properties: Record<string, unknown> & { lot_id?: string; no_lot?: string; status_pengesahan?: string; disahkan?: boolean };
}

export interface LotFeatureCollection {
    type: 'FeatureCollection';
    features: LotFeature[];
}

export interface ProfilItem {
    label?: string;
    nilai?: unknown;
    link?: string;
    [key: string]: unknown;
}

export interface Bendera {
    level: 'danger' | 'warning' | 'info' | 'success' | string;
    label: string;
    seksyen?: string;
}

export interface Seksyen {
    key: string;
    label: string;
    status: 'ada' | 'kosong' | 'belum_tersedia' | 'ralat';
    mesej?: string;
    modul?: string;
    items: ProfilItem[];
    flags: Bendera[];
    link?: string | null;
}

export interface TabProfil {
    kod: string;
    label: string;
    seksyen: Seksyen[];
}

export interface PeristiwaGarisMasa {
    date: string;
    type: string;
    title: string;
    link?: string;
    seksyen: string;
    modul_label: string;
}

export interface BaldiKewangan {
    tempoh: string;
    hasil_sewaan?: number;
    kos_cukai?: number;
    kos_penyelenggaraan?: number;
    kos_guaman?: number;
    jumlah_hasil: number;
    jumlah_kos: number;
    pulangan_bersih: number;
    [key: string]: number | string | undefined;
}

export interface Kewangan {
    tersedia: boolean;
    mesej: string | null;
    jenis?: string[];
    bulanan: BaldiKewangan[];
    tahunan: BaldiKewangan[];
    jumlah: { hasil: number; kos: number; pulangan_bersih: number };
}

export interface Profil {
    lot: LotButiran;
    tab: TabProfil[];
    bendera: Bendera[];
    garis_masa: PeristiwaGarisMasa[];
    kewangan: Kewangan;
    geojson: LotFeature | null;
}

export interface VersiLot {
    id: string;
    no_versi: number | null;
    kritikal: boolean;
    status_pengesahan: string;
    medan: string[];
    pengguna: string | null;
    pengesah_kedua: string | null;
    justifikasi: string | null;
    catatan_pengesahan: string | null;
    tarikh: string | null;
    tarikh_pengesahan: string | null;
}

export interface Perbandingan {
    medan: string;
    label: string;
    sebelum: unknown;
    selepas: unknown;
    disamarkan?: boolean;
}

export interface GeometriVersi {
    id: number;
    versi: number;
    semasa: boolean;
    srid_asal: number;
    format_asal: string | null;
    sumber_data: string;
    rujukan_fail_sumber: string | null;
    id_dokumen_sumber: string | null;
    tarikh_perolehan_data: string | null;
    keluasan_dikira: number | null;
    peratus_beza_keluasan: number | null;
    status_pengesahan: string;
    label_status: string;
    id_pegawai_pengesah: number | null;
    tarikh_pengesahan: string | null;
    catatan_pertindihan: string | null;
    catatan_pengesahan: string | null;
    id_pemuat_naik: number | null;
    dimuat_naik_pada: string | null;
    tarikh_kemas_kini: string | null;
}

export interface Pengguna {
    id: number;
    name: string;
    email: string;
}

export interface KemasKiniLotRespons {
    message: string;
    data: LotButiran;
    diterapkan: string[];
    tertunda: { id: string; medan: string[]; id_aliran_kerja: string | null } | null;
}

// ---- Import

export type EntitiImport = 'lot_tanah' | 'data_spatial';

export interface MedanSasaran {
    medan: string;
    label: string;
    wajib: boolean;
}

export interface RingkasanImport {
    id: string;
    entiti_sasaran: EntitiImport;
    nama_fail: string;
    format_fail: string | null;
    pengguna: string | null;
    tarikh_masa: string | null;
    tarikh_komit: string | null;
    status_sesi: 'pratonton' | 'dikomit' | 'dibatalkan' | string;
    bil_baris_dibaca: number;
    bil_sah: number;
    bil_berjaya: number;
    bil_ditolak: number;
    bil_diabaikan: number;
    lokasi_fail_asal: string | null;
    catatan_fail_asal: string | null;
    pemetaan: Record<string, string> | null;
    lajur_fail: string[] | null;
}

export interface BarisDitolak {
    no_baris: number;
    medan: string | null;
    label_medan: string | null;
    nilai_diberi: string | null;
    kod_ralat: string | null;
    sebab_penolakan: string | null;
}

export interface BarisPratonton {
    no_baris: number;
    status: 'sah' | 'ditolak' | 'diabaikan' | 'dikomit' | string;
    nilai: Record<string, unknown>;
    ralat: Array<{ medan: string | null; sebab: string | null }>;
    amaran: string[];
    id_rekod: string | null;
}

export interface SesiImport {
    ringkasan: RingkasanImport;
    ditolak: BarisDitolak[];
    baris: BarisPratonton[];
    medan_sasaran: MedanSasaran[];
}

export interface PemetaanTersimpan {
    id: number;
    entiti_sasaran: EntitiImport;
    nama: string;
    pemetaan: Record<string, string>;
}

// ---- Duplicates

export interface CadanganDuplikasi {
    id: number;
    skor_padanan: number;
    kriteria_padanan: {
        padanan_no_lot?: number;
        sama_mukim?: boolean;
        beza_keluasan_peratus?: number | null;
        pertindihan_geometri_peratus?: number;
        berat?: Record<string, number>;
    };
    keputusan: 'menunggu' | 'sah' | 'gabung' | 'tolak';
    catatan_keputusan: string | null;
    tarikh_keputusan: string | null;
    lot_a: LotRingkas | null;
    lot_b: LotRingkas | null;
}

// ---- Stewardship / quality

export interface Stewardship {
    id: number;
    komponen_data: string;
    entiti_berkaitan: string;
    jawatan_data_owner: string;
    bahagian_data_owner: string;
    id_data_owner: number | null;
    nama_data_owner: string | null;
    jawatan_data_steward: string;
    bahagian_data_steward: string;
    id_data_steward: number | null;
    nama_data_steward: string | null;
    bahagian_pemilik: string | null;
    kod_daerah: string | null;
    peranan_tanggungjawab: string;
    tarikh_berkuat_kuasa: string;
    status: 'aktif' | 'tidak_aktif';
    bil_tugasan_terbuka: number;
}

export interface KategoriKualiti {
    kod: string;
    label: string;
    entiti: string;
    bilangan: number;
    tersedia: boolean;
}

export interface ButiranKualiti {
    lot_id?: string;
    no_lot?: string | null;
    nama_mukim?: string | null;
    nama_daerah?: string | null;
    bahagian_pemilik?: string | null;
    keterangan?: string | null;
    pautan?: string | null;
    [key: string]: unknown;
}

export interface LaporanKualitiData {
    ringkasan: KategoriKualiti[];
    butiran: Record<string, ButiranKualiti[]>;
}

export interface TugasanKualiti {
    id: number;
    entiti: string;
    id_rekod: string;
    kod_isu: string;
    keterangan: string;
    tahap_keterukan: 'ralat' | 'amaran' | string;
    kod_daerah: string | null;
    status: 'terbuka' | 'selesai' | 'ditolak';
    tindakan_pembetulan: string | null;
    diselesaikan_pada: string | null;
    sumber: string | null;
    no_lot: string | null;
    pautan: string | null;
    nama_data_steward: string | null;
    created_at: string | null;
}

export interface PeraturanKualiti {
    id: number;
    entiti: string;
    medan: string;
    jenis_peraturan: string;
    ungkapan_peraturan: Record<string, unknown> | null;
    mesej_ralat: string;
    tahap_keterukan: 'ralat' | 'amaran';
    status_aktif: boolean;
}

// ---- Dictionary

export interface JadualKamus {
    nama_jadual: string;
    jenis: string;
    komen?: string | null;
    nama_perniagaan: string | null;
    definisi: string | null;
    klasifikasi: string;
    bil_medan: number;
}

export interface MedanKamus {
    nama_jadual: string;
    nama_medan: string;
    nama_perniagaan: string | null;
    definisi: string | null;
    jenis_data: string | null;
    saiz: string | number | null;
    format: string | null;
    nilai_dibenarkan: string | null;
    nilai_lalai: string | null;
    wajib: boolean | null;
    kunci: string[];
    data_owner: string | null;
    data_steward: string | null;
    klasifikasi: string;
    dikurasi: boolean;
}

export interface KlasifikasiData {
    id: number;
    komponen_data: string;
    entiti: string;
    aras_kerahsiaan: string;
    keperluan_encryption: string;
    peranan_dibenarkan: string;
    kebenaran_diperlukan: string | null;
    tarikh_semakan: string;
}

// ---- Archive / API keys

export interface RekodArkib {
    id: string;
    entiti: string;
    id_rekod: string;
    status_arkib: 'dicadang' | 'menunggu_kelulusan' | 'diarkib' | 'ditolak' | 'dipulih' | string;
    id_aliran_kerja: string | null;
    tarikh_arkib: string | null;
    tarikh_layak_lupus: string | null;
    tarikh_pulih: string | null;
    sebab: string | null;
    created_at: string | null;
    lot: { id: string; no_lot: string; kod_mukim: string | null; kod_daerah: string | null; status_lot: string } | null;
}

export interface JadualRetensi {
    id: number;
    kategori_rekod: string;
    keterangan: string;
    entiti: string;
    syarat: { status_lot?: string[] } | null;
    tempoh_retensi_tahun: number;
    tindakan_selepas_retensi: string;
    rujukan_dasar_mais: string | null;
    memerlukan_kelulusan: boolean;
    aktif: boolean;
}

export interface KunciApi {
    id: number;
    nama: string | null;
    sistem_pemanggil: string;
    skop: string[];
    had_kadar_seminit: number;
    tamat_pada: string | null;
    dibatalkan_pada: string | null;
    terakhir_digunakan: string | null;
    pemilik: string | null;
    bil_panggilan: number;
    status: 'aktif' | 'tamat' | 'dibatalkan';
}

export interface PenggunaanApi {
    id_token: number;
    sistem_pemanggil: string;
    bil_panggilan: number;
    bil_gagal: number;
    terakhir: string | null;
}

// ---------------------------------------------------------------- labels

export const MEDAN_LABEL: Record<string, string> = {
    no_lot: 'No. Lot',
    kod_mukim: 'Mukim',
    kod_daerah: 'Daerah',
    keluasan: 'Keluasan',
    unit_keluasan: 'Unit Keluasan',
    kategori_guna_tanah: 'Kategori Guna Tanah',
    zoning: 'Zoning',
    syarat_nyata: 'Syarat Nyata',
    bahagian_pemilik: 'Bahagian Pemilik MAIS',
    status_lot: 'Status Lot',
    klasifikasi: 'Klasifikasi',
    id_data_owner: 'Data Owner',
    id_data_steward: 'Data Steward',
    tag: 'Tag',
    catatan: 'Catatan',
    justifikasi: 'Justifikasi',
};

export const STATUS_LOT_LABEL: Record<string, string> = {
    draf: 'Draf',
    aktif: 'Aktif',
    tidak_aktif: 'Tidak aktif',
    digabung: 'Digabungkan',
    arkib: 'Diarkib',
};

export const BAHAGIAN_LABEL: Record<string, string> = { hartanah: 'Hartanah', wakaf: 'Wakaf', baitulmal: 'Baitulmal' };

export const KLASIFIKASI_LABEL: Record<string, string> = { awam: 'Awam', dalaman: 'Dalaman', sulit: 'Sulit', rahsia: 'Rahsia' };

export const STATUS_SPATIAL_LABEL: Record<string, string> = {
    belum_disahkan: 'Belum disahkan',
    disahkan_juruukur: 'Disahkan juruukur',
    disahkan_ptg: 'Disahkan PTG',
    bertindih: 'Bertindih / perlu semakan',
    tiada_geometri: 'Tiada geometri',
};

export const STATUS_VERSI_LABEL: Record<string, string> = {
    berkuat_kuasa: 'Berkuat kuasa',
    menunggu: 'Menunggu pengesahan',
    lulus: 'Diluluskan',
    tolak: 'Ditolak',
    ditarik_balik: 'Ditarik balik',
    dibatalkan: 'Dibatalkan',
};

export const STATUS_ARKIB_LABEL: Record<string, string> = {
    dicadang: 'Dicadang',
    menunggu_kelulusan: 'Menunggu kelulusan',
    diarkib: 'Diarkib',
    ditolak: 'Ditolak',
    dipulih: 'Dipulih',
};

export const KEPUTUSAN_DUPLIKASI_LABEL: Record<string, string> = {
    menunggu: 'Menunggu',
    sah: 'Sah (bukan duplikasi)',
    gabung: 'Gabung',
    tolak: 'Tolak cadangan',
};

export const STATUS_SESI_LABEL: Record<string, string> = {
    pratonton: 'Pratonton',
    dikomit: 'Dikomit',
    dibatalkan: 'Dibatalkan',
};

export const STATUS_BARIS_LABEL: Record<string, string> = {
    sah: 'Sah',
    ditolak: 'Ditolak',
    diabaikan: 'Diabaikan',
    dikomit: 'Dikomit',
};

export const FORMAT_EKSPORT: Array<{ kod: 'csv' | 'xlsx' | 'pdf' | 'json'; label: string }> = [
    { kod: 'csv', label: 'CSV' },
    { kod: 'xlsx', label: 'Excel (XLSX)' },
    { kod: 'pdf', label: 'PDF' },
    { kod: 'json', label: 'JSON' },
];

export type Tone = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

/** PRO-10: verification flag colour — verified green, unverified amber, overlap/none red. */
export function toneSpatial(status: string | null | undefined): Tone {
    if (!status) return 'neutral';
    if (status.startsWith('disahkan')) return 'success';
    if (status === 'belum_disahkan') return 'warning';
    if (status === 'bertindih' || status === 'tiada_geometri') return 'danger';
    return 'neutral';
}

export function toneKlasifikasi(k: string | null | undefined): Tone {
    return k === 'rahsia' ? 'danger' : k === 'sulit' ? 'warning' : k === 'awam' ? 'success' : 'info';
}

export function toneStatusLot(s: string | null | undefined): Tone {
    return s === 'aktif' ? 'success' : s === 'draf' ? 'info' : s === 'arkib' || s === 'digabung' ? 'neutral' : 'warning';
}

// ---------------------------------------------------------------- formatting

export function fmtTarikh(iso: string | null | undefined, withTime = false): string {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return withTime
        ? d.toLocaleString('ms-MY', { dateStyle: 'medium', timeStyle: 'short' })
        : d.toLocaleDateString('ms-MY', { dateStyle: 'medium' });
}

export function fmtNombor(n: number | null | undefined, digits = 2): string {
    if (n === null || n === undefined || Number.isNaN(n)) return '—';
    return n.toLocaleString('ms-MY', { maximumFractionDigits: digits });
}

export function fmtRM(n: number | null | undefined): string {
    if (n === null || n === undefined) return '—';
    return `RM ${n.toLocaleString('ms-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

/** Render any profile value as text. */
export function fmtNilai(v: unknown): string {
    if (v === null || v === undefined || v === '') return '—';
    if (typeof v === 'boolean') return v ? 'Ya' : 'Tidak';
    if (Array.isArray(v)) return v.length ? v.map(fmtNilai).join(', ') : '—';
    if (typeof v === 'object') return JSON.stringify(v);
    return String(v);
}

export function qs(params: Record<string, string | number | boolean | null | undefined>): string {
    const p = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
        if (v === null || v === undefined || v === '') continue;
        p.set(k, String(v));
    }
    const s = p.toString();
    return s ? `?${s}` : '';
}

export function errorMessage(e: unknown, fallback = 'Permintaan gagal.'): string {
    if (e instanceof ApiError) {
        if (e.status === 403) return e.message || 'Anda tidak mempunyai kebenaran untuk tindakan ini.';
        return e.message || fallback;
    }
    return fallback;
}

/** 422 → first message per field, for showing next to inputs. */
export function fieldErrors(e: unknown): Record<string, string> {
    if (!(e instanceof ApiError) || !e.errors) return {};
    const out: Record<string, string> = {};
    for (const [k, v] of Object.entries(e.errors)) {
        if (v?.[0]) out[k] = v[0];
    }
    return out;
}

/**
 * Frontend links from the API that point at paths this UI doesn't have
 * (the DMS detail page is /admin/dms/{id}, not /admin/dms/dokumen/{id}).
 */
export function pautanUi(link: string | null | undefined): string | null {
    if (!link) return null;
    return link.replace(/^\/admin\/dms\/dokumen\//, '/admin/dms/');
}

// ---------------------------------------------------------------- transport

function getCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
}

async function xsrfHeaders(): Promise<Headers> {
    if (!getCookie('XSRF-TOKEN')) {
        await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
    }
    const headers = new Headers({ Accept: 'application/json' });
    const xsrf = getCookie('XSRF-TOKEN');
    if (xsrf) headers.set('X-XSRF-TOKEN', xsrf);
    return headers;
}

/** DELETE with a JSON body (lib/api's delete takes none). */
async function deleteWithBody<T>(path: string, body: unknown): Promise<T> {
    const headers = await xsrfHeaders();
    headers.set('Content-Type', 'application/json');
    const res = await fetch(`/api/v1${path}`, { method: 'DELETE', headers, credentials: 'include', body: JSON.stringify(body) });
    const data = (res.headers.get('content-type') ?? '').includes('json') ? await res.json() : null;
    if (!res.ok) throw new ApiError(res.status, data);
    return data as T;
}

/** Download a file endpoint (exports, templates, reports) through the session. */
export async function muatTurun(path: string, fallbackName: string): Promise<void> {
    const res = await fetch(`/api/v1${path}`, { credentials: 'include', headers: { Accept: '*/*' } });
    if (!res.ok) {
        const body = (res.headers.get('content-type') ?? '').includes('json') ? await res.json() : null;
        throw new ApiError(res.status, body);
    }
    const blob = await res.blob();
    const disposition = res.headers.get('content-disposition') ?? '';
    const star = /filename\*=UTF-8''([^;]+)/i.exec(disposition)?.[1];
    const plain = /filename="?([^";]+)"?/i.exec(disposition)?.[1];
    const filename = star ? decodeURIComponent(star) : (plain ?? fallbackName);
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

// ---------------------------------------------------------------- API

export type PenapisLot = Partial<{
    q: string;
    kod_daerah: string;
    kod_mukim: string;
    bahagian_pemilik: string;
    status_lot: string;
    kategori_guna_tanah: string;
    status_pengesahan: string;
    klasifikasi: string;
    arkib: '' | '0' | '1' | 'sahaja';
    sort: string;
    per_page: number;
    page: number;
}>;

export type InputLot = Partial<{
    no_lot: string | null;
    kod_mukim: string | null;
    kod_daerah: string | null;
    keluasan: string | number | null;
    unit_keluasan: string | null;
    kategori_guna_tanah: string | null;
    zoning: string | null;
    syarat_nyata: string | null;
    bahagian_pemilik: string | null;
    status_lot: string | null;
    klasifikasi: string | null;
    id_data_owner: number | null;
    id_data_steward: number | null;
    tag: string[];
    catatan: string | null;
    justifikasi: string;
    versi_rekod: number;
}>;

const P = '/pro';

/** Drop "Abaikan" (unmapped) columns — empty strings would arrive as null and fail validation. */
function bersihPemetaan(pemetaan: Record<string, string>): Record<string, string> {
    return Object.fromEntries(Object.entries(pemetaan).filter(([, v]) => v));
}

export const proApi = {
    rujukan: () => api.get<{ data: Rujukan }>(`${P}/rujukan`),
    senaraiLot: (f: PenapisLot) => api.get<Paged<LotRingkas>>(`${P}/lot${qs(f)}`),
    ringkasanLot: (f: PenapisLot) =>
        api.get<{
            data: { jumlah: number; tiada_geometri: number; status_pengesahan: Record<string, number>; bahagian: Record<string, number> };
        }>(`${P}/lot/ringkasan${qs({ ...f, page: undefined, per_page: undefined, sort: undefined })}`),
    geojson: (f: PenapisLot) => api.get<LotFeatureCollection>(`${P}/geojson${qs({ ...f, page: undefined, per_page: undefined, sort: undefined })}`),
    eksportLot: (f: PenapisLot, format: string) =>
        muatTurun(`${P}/lot/eksport${qs({ ...f, page: undefined, per_page: undefined, format })}`, `senarai-lot.${format}`),

    lot: (id: string) => api.get<{ data: LotButiran }>(`${P}/lot/${id}`),
    ciptaLot: (body: InputLot) => api.post<{ message: string; data: LotButiran }>(`${P}/lot`, body),
    kemasKiniLot: (id: string, body: InputLot) => api.put<KemasKiniLotRespons>(`${P}/lot/${id}`, body),
    padamLot: (id: string, sebab: string) => deleteWithBody<{ message: string }>(`${P}/lot/${id}`, { sebab }),
    arkibLot: (id: string, sebab: string) => api.post<{ message: string }>(`${P}/lot/${id}/arkib`, { sebab }),
    pulihLot: (id: string, sebab: string) => api.post<{ message: string }>(`${P}/lot/${id}/pulih`, { sebab }),
    semakKualiti: (id: string) =>
        api.post<{ data: { isu: Array<{ label?: string; mesej?: string; [k: string]: unknown }>; tugasan_baharu: number } }>(`${P}/lot/${id}/semak-kualiti`),

    profil: (id: string) => api.get<{ data: Profil }>(`${P}/lot/${id}/profil`),
    cetak: (id: string) => api.post<{ message: string }>(`${P}/lot/${id}/cetak`),
    eksportProfil: (id: string, format: string) => muatTurun(`${P}/lot/${id}/profil/eksport?format=${format}`, `profil-${id}.${format}`),
    versi: (id: string) => api.get<{ data: VersiLot[] }>(`${P}/lot/${id}/versi`),
    bandingVersi: (id: string, versiId: string) =>
        api.get<{ data: { id: string; no_versi: number | null; status_pengesahan: string; perbandingan: Perbandingan[] } }>(`${P}/lot/${id}/versi/${versiId}`),

    spatial: (id: string) => api.get<{ data: GeometriVersi[]; semasa: LotFeature | null }>(`${P}/lot/${id}/spatial`),
    muatNaikSpatial: (id: string, form: FormData) =>
        api.postForm<{ message: string; data: GeometriVersi; amaran: string[]; geojson: LotFeature | null }>(`${P}/lot/${id}/spatial`, form),
    sahkanSpatial: (spatialId: number, status_pengesahan: string, catatan: string) =>
        api.post<{ message: string; data: GeometriVersi }>(`${P}/spatial/${spatialId}/sahkan`, { status_pengesahan, catatan: catatan || null }),

    pengguna: (q: string) => api.get<{ data: Pengguna[] }>(`${P}/pengguna${qs({ q })}`),

    // Import
    medanImport: (entiti: EntitiImport) => api.get<{ data: MedanSasaran[] }>(`${P}/import/medan/${entiti}`),
    templat: (entiti: EntitiImport, format: 'csv' | 'xlsx') => muatTurun(`${P}/import/templat/${entiti}?format=${format}`, `templat-${entiti}.${format}`),
    pemetaanTersimpan: (entiti: EntitiImport) => api.get<{ data: PemetaanTersimpan[] }>(`${P}/import/pemetaan${qs({ entiti })}`),
    simpanPemetaan: (entiti_sasaran: EntitiImport, nama: string, pemetaan: Record<string, string>) =>
        api.post<{ message: string; data: PemetaanTersimpan }>(`${P}/import/pemetaan`, { entiti_sasaran, nama, pemetaan: bersihPemetaan(pemetaan) }),
    muatNaikImport: (form: FormData) => api.postForm<{ data: SesiImport }>(`${P}/import`, form),
    petaSemula: (id: string, pemetaan: Record<string, string>) => api.post<{ data: SesiImport }>(`${P}/import/${id}/pemetaan`, { pemetaan: bersihPemetaan(pemetaan) }),
    komitImport: (id: string) => api.post<{ message: string; data: SesiImport }>(`${P}/import/${id}/komit`),
    batalImport: (id: string) => api.post<{ message: string }>(`${P}/import/${id}/batal`),
    sesiImport: (id: string, status?: string) => api.get<{ data: SesiImport }>(`${P}/import/${id}${qs({ status })}`),
    senaraiImport: (page: number) => api.get<Paged<RingkasanImport>>(`${P}/import${qs({ page })}`),
    laporanImport: (id: string, format: string) => muatTurun(`${P}/import/${id}/laporan?format=${format}`, `laporan-import.${format}`),

    // Duplicates
    duplikasi: (f: { keputusan?: string; id?: string | null; page?: number }) => api.get<Paged<CadanganDuplikasi>>(`${P}/duplikasi${qs(f)}`),
    kesanDuplikasi: () => api.post<{ data: { diperiksa: number; cadangan_baharu: number } }>(`${P}/duplikasi/kesan`),
    keputusanDuplikasi: (id: number, body: { keputusan: string; catatan?: string; id_rekod_kekal?: string }) =>
        api.post<{ message: string }>(`${P}/duplikasi/${id}/keputusan`, body),
    kriteriaDuplikasi: () => api.get<{ data: Record<string, number>; parameter: string }>(`${P}/duplikasi/kriteria`),

    // Stewardship
    stewardship: () => api.get<{ data: Stewardship[] }>(`${P}/stewardship`),
    simpanStewardship: (body: Record<string, unknown>, id?: number) =>
        id ? api.put<{ message: string }>(`${P}/stewardship/${id}`, body) : api.post<{ message: string }>(`${P}/stewardship`, body),
    eksportStewardship: (format: string) => muatTurun(`${P}/stewardship/eksport?format=${format}`, `daftar-stewardship.${format}`),

    // Quality
    laporanKualiti: (f: Record<string, string>) => api.get<{ data: LaporanKualitiData }>(`${P}/kualiti/laporan${qs(f)}`),
    eksportKualiti: (f: Record<string, string>, format: string) =>
        muatTurun(`${P}/kualiti/laporan/eksport${qs({ ...f, format })}`, `laporan-kualiti-data.${format}`),
    jalankanKualiti: () => api.post<{ data: { tugasan_baharu: number } }>(`${P}/kualiti/jalankan`),
    tugasan: (f: { status?: string; saya?: string; page?: number }) => api.get<Paged<TugasanKualiti>>(`${P}/kualiti/tugasan${qs(f)}`),
    selesaiTugasan: (id: number, tindakan_pembetulan: string, status: string) =>
        api.post<{ message: string }>(`${P}/kualiti/tugasan/${id}/selesai`, { tindakan_pembetulan, status }),
    peraturan: () => api.get<{ data: PeraturanKualiti[] }>(`${P}/kualiti/peraturan`),
    kemasKiniPeraturan: (id: number, body: Partial<PeraturanKualiti>) => api.put<{ message: string }>(`${P}/kualiti/peraturan/${id}`, body),

    // Dictionary
    jadualKamus: () => api.get<{ data: JadualKamus[] }>(`${P}/kamus/jadual`),
    kamus: (f: { jadual?: string; q?: string; klasifikasi?: string }) => api.get<{ data: MedanKamus[] }>(`${P}/kamus${qs(f)}`),
    eksportKamus: (format: string, jadual?: string) => muatTurun(`${P}/kamus/eksport${qs({ format, jadual })}`, `kamus-data.${format}`),
    simpanMetadata: (body: Record<string, unknown>) => api.put<{ message: string }>(`${P}/kamus/metadata`, body),
    klasifikasi: () => api.get<{ data: KlasifikasiData[] }>(`${P}/klasifikasi`),

    // Archive
    arkib: (f: { status?: string; page?: number }) => api.get<Paged<RekodArkib>>(`${P}/arkib${qs(f)}`),
    janaArkib: () => api.post<{ data: { cadangan_baharu: number } }>(`${P}/arkib/jana`),
    hantarArkib: (id: string) => api.post<{ message: string }>(`${P}/arkib/${id}/hantar`),
    jadualRetensi: () => api.get<{ data: JadualRetensi[] }>(`${P}/arkib/jadual`),
    kemasKiniJadual: (id: number, body: Partial<JadualRetensi>) => api.put<{ message: string }>(`${P}/arkib/jadual/${id}`, body),

    // API keys
    kunciApi: () => api.get<{ data: KunciApi[]; skop: Record<string, string> }>(`${P}/kunci-api`),
    ciptaKunci: (body: { nama: string; sistem_pemanggil: string; skop: string[]; tamat_hari: number; had_kadar_seminit: number }) =>
        api.post<{ message: string; data: { id: number; token: string; tamat_pada: string | null } }>(`${P}/kunci-api`, body),
    batalKunci: (id: number) => api.delete<void>(`${P}/kunci-api/${id}`),
    penggunaanApi: () => api.get<{ data: PenggunaanApi[] }>(`${P}/kunci-api/penggunaan`),
};

// ---------------------------------------------------------------- hooks

export function useMe() {
    return useQuery({ queryKey: ['me'], queryFn: () => api.get<{ data: User }>('/user').then((r) => r.data), staleTime: 60_000 });
}

/** UI hiding only — the API enforces every permission itself. */
const noopSubscribe = () => () => {};

export function useCan() {
    const { data } = useMe();
    // Server render never knows the user's permissions; treat them as empty until
    // hydrated so the first client render matches the server HTML (no hydration error).
    const hydrated = useSyncExternalStore(noopSubscribe, () => true, () => false);
    const perms = new Set(hydrated ? (data?.permissions ?? []) : []);
    return (...keys: string[]) => keys.some((k) => perms.has(k));
}

export function useRujukan() {
    return useQuery({ queryKey: ['pro', 'rujukan'], queryFn: () => proApi.rujukan().then((r) => r.data), staleTime: 300_000 });
}

export function namaRuj(list: Ruj[] | undefined, kod: string | null | undefined): string {
    if (!kod) return '—';
    return list?.find((r) => String(r.kod) === String(kod))?.nama ?? kod;
}
