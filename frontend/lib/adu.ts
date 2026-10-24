import { useSyncExternalStore } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api, ApiError } from './api';
import type { User } from './types';

/* MOD-ADU client — Aduan dan Isu Tanah (/api/v1/adu). Labels come from the API (Malay). */

export interface Kod {
    kod: string;
    nama: string;
}

export interface Sla {
    kitaran: number;
    status_sla: 'dalam_tempoh' | 'hampir_tamat' | 'dilepasi' | 'dipenuhi' | 'tidak_dipenuhi';
    status_sla_nama: string;
    masa_mula: string;
    masa_sasaran_respons: string;
    masa_sasaran_selesai: string;
    masa_respons_sebenar: string | null;
    masa_selesai_sebenar: string | null;
    baki_minit_respons: number | null;
    baki_minit_selesai: number;
    baki_hari_selesai: number;
    peratus_digunakan: number;
    bilangan_eskalasi: number;
}

export interface Aduan {
    id: string;
    no_rujukan_aduan: string;
    saluran_penerimaan: string;
    saluran_nama: string;
    tarikh_terima: string;
    kategori: string;
    kategori_nama: string | null;
    subkategori: string | null;
    subkategori_nama: string | null;
    kategori_dicadang: string | null;
    keterangan: string | null;
    lokasi_teks: string | null;
    lot_id: string | null;
    no_lot: string | null;
    kod_daerah: string | null;
    daerah: string | null;
    kod_mukim: string | null;
    mukim: string | null;
    status: string;
    status_nama: string;
    tahap_keutamaan: string;
    keutamaan_nama: string;
    status_tanpa_nama: boolean;
    status_bukan_tanah_mais: boolean;
    id_aduan_pendua_dicadang: string | null;
    skor_pendua: number | null;
    id_aduan_induk: string | null;
    pegawai: { id: number; nama: string } | null;
    bilangan_buka_semula: number;
    tarikh_tutup: string | null;
    sintetik: boolean;
    lat: number | null;
    lng: number | null;
    sla: Sla | null;
}

export interface PengaduRingkas {
    tanpa_nama: boolean;
    ada: boolean;
    boleh_dedah: boolean;
    bertopeng?: { nama: string | null; no_telefon: string | null; emel: string | null; alamat: string | null };
    saluran_makluman?: string;
    sintetik?: boolean;
}

export interface AduanButiran extends Aduan {
    lot: { id: string; no_lot: string; mukim: string | null; pautan: string } | null;
    kaedah_padanan_lot: string | null;
    jawapan_automatik: string | null;
    agensi_dicadang: Array<{ nama: string; jenis: string }>;
    jawapan_dihantar: boolean;
    pendua_dicadang: { id: string; no_rujukan_aduan: string; status: string } | null;
    induk: { id: string; no_rujukan_aduan: string } | null;
    penyelia: { id: number; nama: string } | null;
    tindakan_susulan: string | null;
    bahagian_dirujuk: string | null;
    id_instans_aliran: string | null;
    lawatan: Array<{ id: number; tarikh_jadual: string; tarikh_lawatan: string | null; status: string; penemuan: string | null; pegawai: string | null }>;
    resolusi: Array<{
        id: number;
        kategori_resolusi: string;
        kategori_nama: string;
        keterangan: string;
        justifikasi: string;
        status: string;
        penyelesai: string | null;
        pelulus: string | null;
        ulasan_pelulus: string | null;
        tarikh_tutup: string | null;
        status_makluman_pengadu: string;
        dicipta: string;
    }>;
    rujukan: Array<{ id: number; jenis_rujukan: string; jenis_nama: string; nama_agensi: string | null; keterangan: string; status: string; pegawai: string | null }>;
    eskalasi: Array<{ id: number; peringkat: number; jenis: string; sebab: string; tarikh: string }>;
    jejak_sla: Sla[];
    pengadu: PengaduRingkas;
    tindakan_dibenarkan: { urus: boolean; tugaskan: boolean; eksport: boolean };
}

export interface Kronologi {
    id: number;
    tarikh_masa: string;
    jenis_tindakan: string;
    jenis_nama: string;
    keterangan: string | null;
    pegawai: string | null;
    status_sebelum: string | null;
    status_selepas: string | null;
    komunikasi_pengadu: boolean;
    id_dokumen: string | null;
}

export interface Rujukan {
    kategori: Kod[];
    subkategori: Record<string, Kod[]>;
    saluran: Kod[];
    saluran_dalaman: Kod[];
    status: Kod[];
    keutamaan: Kod[];
    kategori_resolusi: Kod[];
    jenis_rujukan: Kod[];
    bahagian_kategori: Record<string, string>;
    daerah: Kod[];
    mukim: Array<Kod & { kod_daerah: string }>;
}

export interface Pecahan {
    kod: string | null;
    label: string | null;
    jumlah: number;
    aktif: number;
    ditutup: number;
}

export interface Ringkasan {
    jumlah: number;
    aktif: number;
    tertangguh: number;
    diselesaikan: number;
    pendua: number;
    bukan_tanah_mais: number;
    tanpa_nama: number;
    purata_hari_selesai: number | null;
    purata_jam_respons: number | null;
    peratus_sla_dipenuhi: number | null;
    peratus_aktif_dalam_sla: number | null;
    mengikut_kategori: Pecahan[];
    mengikut_daerah: Pecahan[];
    mengikut_saluran: Pecahan[];
    mengikut_status: Pecahan[];
    trend: Array<{ bulan: string; diterima: number; ditutup: number }>;
    kawasan_panas: Array<{ kod_mukim: string; mukim: string | null; daerah: string | null; jumlah: number; terbuka: number; kategori_utama: string | null; lat: number | null; lng: number | null }>;
}

export interface Paged<T> {
    data: T[];
    meta: { total: number; current_page: number; last_page: number; per_page: number };
}

export interface Dokumen {
    id: string;
    name: string;
    category: string;
    version: number;
    classification: string;
    uploaded_at: string;
}

export interface LotPilihan {
    id: string;
    no_lot: string;
    kod_mukim: string;
    mukim: string | null;
    kod_daerah: string;
    daerah: string | null;
}

export interface PetaFeature {
    type: 'Feature';
    geometry: { type: 'Point'; coordinates: [number, number] };
    properties: { id: string; label: string; pautan: string; kategori: string; status: string; status_nama: string; terbuka: boolean; warna: string };
}

export interface Peta {
    type: 'FeatureCollection';
    features: PetaFeature[];
    legenda: { medan: string | null; entri: Array<{ nilai: string; label: string; warna: string }> };
}

type Body = Record<string, unknown>;
type Msg<T> = { data: T; message: string };

export function qs(params: Record<string, unknown>): string {
    const search = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
        if (v !== undefined && v !== null && v !== '' && v !== false) search.set(k, String(v));
    }
    const s = search.toString();
    return s ? `?${s}` : '';
}

const A = '/adu';

export const aduApi = {
    rujukan: () => api.get<{ data: Rujukan }>(`${A}/rujukan`),
    senarai: (p: Record<string, unknown>) => api.get<Paged<Aduan>>(`${A}/aduan${qs(p)}`),
    aduan: (id: string) => api.get<{ data: AduanButiran }>(`${A}/aduan/${id}`),
    kronologi: (id: string) => api.get<{ data: Kronologi[] }>(`${A}/aduan/${id}/kronologi`),
    dokumen: (id: string) => api.get<{ data: Dokumen[] }>(`${A}/aduan/${id}/dokumen`),
    cipta: (form: FormData) => api.postForm<Msg<AduanButiran>>(`${A}/aduan`, form),
    muatNaik: (id: string, form: FormData) => api.postForm<Msg<{ dokumen: string[]; ditolak: string[] }>>(`${A}/aduan/${id}/dokumen`, form),
    kemasKini: (id: string, body: Body) => api.put<Msg<AduanButiran>>(`${A}/aduan/${id}`, body),
    tindakan: (id: string, action: string, body: Body = {}) => api.post<Msg<AduanButiran>>(`${A}/aduan/${id}/${action}`, body),
    selesaiLawatan: (id: string, lawatan: number, body: Body) => api.post<Msg<AduanButiran>>(`${A}/aduan/${id}/lawatan/${lawatan}/selesai`, body),
    dedah: (id: string, tujuan: string) => api.post<{ data: Record<string, string | null> }>(`${A}/aduan/${id}/pengadu/dedah`, { tujuan }),
    pegawaiLayak: (id: string, q = '') => api.get<{ data: Array<{ id: number; nama: string; jawatan: string | null; bahagian: string | null }> }>(`${A}/aduan/${id}/pegawai-layak${qs({ q })}`),
    cadangKategori: (keterangan: string) => api.post<{ data: { kategori: string | null; kategori_nama: string | null; keutamaan: string | null; kata_kunci: string[] } }>(`${A}/cadang-kategori`, { keterangan }),
    cariLot: (q: string) => api.get<{ data: LotPilihan[] }>(`${A}/cari-lot${qs({ q })}`),
    ringkasan: (f: Record<string, unknown>) => api.get<{ data: Ringkasan }>(`${A}/laporan/ringkasan${qs(f)}`),
    laporan: <T>(jenis: string, f: Record<string, unknown>) => api.get<{ data: T[] }>(`${A}/laporan/${jenis}${qs(f)}`),
    peta: (f: Record<string, unknown>) => api.get<Peta>(`${A}/peta${qs(f)}`),
    konfigurasi: () => api.get<{ data: Konfigurasi }>(`${A}/konfigurasi`),
    simpanKonfigurasi: (jenis: 'sla' | 'subkategori' | 'triaj' | 'penugasan', body: Body, id?: number) =>
        id ? api.put<Msg<unknown>>(`${A}/konfigurasi/${jenis}/${id}`, body) : api.post<Msg<unknown>>(`${A}/konfigurasi/${jenis}`, body),
    importPusat: () => api.post<Msg<{ diterima: number; sedia_ada: number; gagal: number }>>(`${A}/konfigurasi/integrasi/pusat/import`),
    ulangCuba: () => api.post<Msg<{ berjaya: number; gagal: number }>>(`${A}/konfigurasi/integrasi/pusat/ulang-cuba`),
    outbox: () =>
        api.get<Paged<{ id: number; aduan_id: string; id_aduan_sistem_luaran: string; status_aduan: string; status: string; cubaan: number; ralat_terakhir: string | null; dihantar_pada: string | null; dicipta: string | null }>>(
            `${A}/konfigurasi/integrasi/pusat/outbox`,
        ),
};

export interface Konfigurasi {
    sla: Array<{
        id: number;
        kategori: string;
        kategori_nama: string | null;
        subkategori: string | null;
        tempoh_respons: number;
        tempoh_penyelesaian: number;
        unit_masa: string;
        laluan_eskalasi: Array<{ peringkat: number; sasaran: string; peratus: number; penerima: string[] }>;
        tarikh_berkuat_kuasa: string;
        aktif: boolean;
    }>;
    subkategori: Array<{ id: number; kod_kategori: string; kod: string; nama: string; aktif: boolean; susunan: number }>;
    triaj: Array<{ id: number; jenis_peraturan: string; kata_kunci: string[] | null; kategori_cadangan: string | null; keutamaan_cadangan: string | null; tempoh_semakan_berulang: number | null; jejari_meter: number | null; status_aktif: boolean }>;
    penugasan: Array<{ id: number; kategori: string | null; kod_daerah: string | null; id_pegawai: number | null; pegawai: { id: number; name: string } | null; kod_peranan: string | null; keutamaan: number; aktif: boolean }>;
}

export function useRujukan() {
    return useQuery({ queryKey: ['adu', 'rujukan'], queryFn: () => aduApi.rujukan().then((r) => r.data), staleTime: 300_000 });
}

export async function muatTurun(path: string, params: Record<string, unknown>, fallbackName: string): Promise<void> {
    const response = await fetch(`/api/v1${path}${qs(params)}`, { credentials: 'include', headers: { Accept: '*/*' } });
    if (!response.ok) {
        const body = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null;
        throw new ApiError(response.status, body);
    }
    const blob = await response.blob();
    const name = /filename="?([^";]+)"?/.exec(response.headers.get('content-disposition') ?? '')?.[1] ?? fallbackName;
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = name;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

const noop = () => () => {};

export function useCan() {
    const { data } = useQuery({ queryKey: ['me'], queryFn: () => api.get<{ data: User }>('/user').then((r) => r.data), staleTime: 60_000 });
    const hydrated = useSyncExternalStore(noop, () => true, () => false);
    const perms = new Set(hydrated ? (data?.permissions ?? []) : []);
    return (...keys: string[]) => keys.some((k) => perms.has(k));
}

export function ralat(e: unknown): string {
    if (e instanceof ApiError) {
        const first = e.errors ? Object.values(e.errors)[0]?.[0] : undefined;
        return first ?? e.message;
    }
    return e instanceof Error ? e.message : 'Ralat tidak dijangka.';
}

export function tarikh(value: string | null | undefined): string {
    if (!value) return '—';
    const d = new Date(value.length === 10 ? `${value}T00:00:00` : value);
    return Number.isNaN(d.getTime()) ? value : d.toLocaleDateString('ms-MY', { day: '2-digit', month: 'short', year: 'numeric' });
}

export function tarikhMasa(value: string | null | undefined): string {
    if (!value) return '—';
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? value : d.toLocaleString('ms-MY', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

/** "2.5 hari lagi" / "Lewat 1.2 hari" for the SLA column. */
export function bakiSla(sla: Sla | null): { teks: string; variant: 'success' | 'warning' | 'danger' | 'neutral' } {
    if (!sla) return { teks: '—', variant: 'neutral' };
    if (sla.masa_selesai_sebenar) return { teks: sla.status_sla_nama, variant: sla.status_sla === 'dipenuhi' ? 'success' : 'danger' };
    const h = sla.baki_hari_selesai;
    if (h < 0) return { teks: `Lewat ${Math.abs(h).toLocaleString('ms-MY')} hari bekerja`, variant: 'danger' };
    return { teks: `${h.toLocaleString('ms-MY')} hari bekerja lagi`, variant: sla.status_sla === 'hampir_tamat' ? 'warning' : 'success' };
}

export const STATUS_VARIANT: Record<string, 'success' | 'warning' | 'danger' | 'info' | 'neutral'> = {
    baharu: 'info',
    ditugaskan: 'info',
    dalam_siasatan: 'warning',
    dirujuk: 'warning',
    menunggu_kelulusan: 'warning',
    ditutup: 'success',
    pendua: 'neutral',
};

export const KEUTAMAAN_VARIANT: Record<string, 'success' | 'warning' | 'danger' | 'info' | 'neutral'> = {
    rendah: 'neutral',
    sederhana: 'info',
    tinggi: 'warning',
    kritikal: 'danger',
};
