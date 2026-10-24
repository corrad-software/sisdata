import { useSyncExternalStore } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api, ApiError } from './api';
import type { User } from './types';

/* MOD-KES client — Kes Mahkamah dan Perundangan (/api/v1/kes). Rahsia: every read is logged server-side. */

export interface Kod {
    kod: string;
    nama: string;
}

export interface Kes {
    id: string;
    no_rujukan: string;
    no_kes: string;
    tajuk: string | null;
    jenis: string | null;
    jenis_nama: string | null;
    mahkamah: string | null;
    peringkat_mahkamah: string | null;
    peringkat_nama: string | null;
    tarikh_pemfailan: string | null;
    peranan_mais: string | null;
    peranan_mais_nama: string | null;
    status: string;
    status_nama: string | null;
    aktif: boolean;
    tarikh_status: string | null;
    nilai_tuntutan: string | null;
    kos_guaman: string;
    kod_daerah: string | null;
    daerah: string | null;
    kod_mukim: string | null;
    mukim: string | null;
    pegawai: { id: number; nama: string } | null;
    tarikh_prosiding_berikutnya: string | null;
    tarikh_keputusan: string | null;
    status_arkib: boolean;
    sumber: string;
    klasifikasi: string;
    sintetik: boolean;
    created_at: string;
}

export interface Pihak {
    id: number;
    nama_pihak: string;
    no_pengenalan: string | null;
    maklumat_perhubungan: string | null;
    bertopeng: boolean;
    jenis_pihak: string;
    jenis_pihak_nama: string | null;
    peranan: string;
    peranan_nama: string | null;
    status_wakilan: string;
    status_wakilan_nama: string | null;
    adalah_mais: boolean;
}

export interface Peguam {
    id: number;
    kes_pihak_id: number | null;
    kes_panel_peguam_id: number | null;
    nama_peguam: string;
    nama_firma: string | null;
    pegawai_penghubung: string | null;
    no_telefon: string | null;
    emel: string | null;
    indikator_panel_mais: boolean;
    status_lantikan: string;
    status_lantikan_nama: string | null;
}

export interface Prosiding {
    id: number;
    tarikh_prosiding: string;
    jenis_prosiding: string;
    jenis_nama: string | null;
    status: string;
    status_nama: string | null;
    hasil: string | null;
    arahan_mahkamah: string | null;
    tarikh_berikutnya: string | null;
    jenis_berikutnya: string | null;
    catatan: string | null;
    sumber: string;
    pegawai_rekod: string | null;
}

export interface Kos {
    id: number;
    kategori_kos: string;
    kategori_nama: string | null;
    keterangan: string | null;
    amaun: string;
    tarikh: string;
    no_resit: string | null;
    status_bayaran: string;
    status_bayaran_nama: string | null;
    rujukan_grp: string | null;
    tarikh_bayar: string | null;
}

export interface Tindakan {
    id: number;
    kes_keputusan_id: number | null;
    jenis_tindakan: string;
    jenis_nama: string | null;
    keterangan: string | null;
    id_tugasan_workflow: string | null;
    tarikh_sasaran: string | null;
    status: string;
    status_nama: string | null;
    id_lot_terjejas: string | null;
    pegawai: string | null;
}

export interface Dokumen {
    id: number;
    id_dokumen_dms: string;
    kategori_dokumen: string;
    kategori_nama: string | null;
    nama_fail: string | null;
    versi: number;
    tarikh_muat_naik: string;
    sumber: string;
    pengguna: string | null;
}

export interface KesButiran extends Kes {
    ringkasan_tuntutan: string | null;
    sebab_arkib: string | null;
    tarikh_arkib: string | null;
    aliran_pemfailan: { id: string; status: string } | null;
    lot: Array<{
        lot_id: string;
        no_lot: string | null;
        daerah: string | null;
        mukim: string | null;
        kategori_guna_tanah: string | null;
        peranan_nama: string | null;
        boleh_buka: boolean;
        pautan: string | null;
        dalam_tindakan: boolean;
    }>;
    pihak: Pihak[];
    peguam: Peguam[];
    prosiding: Prosiding[];
    tarikh_penting: Array<{ id: number; jenis: string; jenis_nama: string | null; tarikh: string; keterangan: string | null; status: string }>;
    kos: Kos[];
    kos_mengikut_kategori: Array<{ kod: string; nama: string; jumlah: string }>;
    keputusan: Array<{ id: number; jenis_keputusan: string; jenis_nama: string | null; tarikh_keputusan: string; ringkasan_perintah: string | null; implikasi: string | null; id_dokumen_penghakiman: string | null }>;
    tindakan: Tindakan[];
    rayuan: Array<{ id: number; pihak_merayu: string; mahkamah_rayuan: string | null; no_kes_rayuan: string | null; tarikh_had_failan: string | null; tarikh_failan: string | null; alasan: string | null; status: string; status_nama: string | null; keputusan_nama: string | null }>;
    penyelesaian: Array<{ id: number; terma: string; nilai_penyelesaian: string | null; tarikh_cadangan: string; tarikh_persetujuan: string | null; status: string; status_nama: string | null }>;
    dokumen: Dokumen[];
    sejarah_status: Array<{ status_lama: string | null; status_baharu: string | null; tarikh: string; pengguna: string | null; catatan: string | null }>;
    sejarah_pegawai: Array<{ lama: string | null; baharu: string | null; tarikh: string; justifikasi: string }>;
    kronologi: Array<{ tarikh: string; jenis: string; tajuk: string; butiran: string | null }>;
    boleh: { kemas_kini: boolean; lulus: boolean; dedah_pihak: boolean };
}

export interface Rujukan {
    jenis: Array<Kod & { aktif: boolean }>;
    status: Array<Kod & { terminal: boolean; aktif: boolean }>;
    peringkat_mahkamah: Kod[];
    peranan: Kod[];
    jenis_pihak: Kod[];
    status_wakilan: Kod[];
    jenis_prosiding: Kod[];
    status_prosiding: Kod[];
    jenis_tarikh: Kod[];
    kategori_kos: Kod[];
    status_bayaran: Kod[];
    jenis_keputusan: Kod[];
    jenis_tindakan: Kod[];
    kategori_dokumen: Kod[];
    peranan_lot: Kod[];
    jenis_log: Kod[];
    panel: Array<{ id: number; nama_firma: string }>;
    pegawai: Array<{ id: number; nama: string }>;
    daerah: Kod[];
}

export interface EntriKalendar {
    id: string;
    jenis_entri: 'prosiding' | 'tarikh_penting';
    tarikh: string;
    jenis: string;
    jenis_nama: string;
    status: string;
    status_nama: string;
    kes_id: string;
    no_rujukan: string | null;
    no_kes: string | null;
    mahkamah: string | null;
    jenis_kes: string | null;
    kod_daerah: string | null;
    pegawai: string | null;
}

export interface BarisRingkasan {
    kod: string;
    label: string;
    bil: number;
    aktif: number;
    nilai_tuntutan: string;
    kos: string;
}

export interface Dashboard {
    bil_aktif: number;
    bil_jumlah: number;
    mengikut_status: BarisRingkasan[];
    mengikut_jenis: BarisRingkasan[];
    prosiding_hampir: Array<{ id: number; kes_id: string; no_rujukan: string | null; tarikh: string; jenis_nama: string; baki_hari: number }>;
    bil_tanpa_tarikh: number;
    bil_lot_terlibat: number;
    nilai_lot: { boleh_lihat: boolean; nilai: string | null; bil_lot_dinilai: number };
    kos_terkumpul: string;
    kos_tahun_ini: string;
}

export interface Paged<T> {
    data: T[];
    meta: { total: number; current_page: number; last_page: number; per_page?: number };
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

const K = '/kes';

export const kesApi = {
    rujukan: () => api.get<{ data: Rujukan }>(`${K}/rujukan`),
    senarai: (p: Record<string, unknown>) => api.get<Paged<Kes>>(`${K}/kes${qs(p)}`),
    kes: (id: string) => api.get<{ data: KesButiran }>(`${K}/kes/${id}`),
    cipta: (body: Body) => api.post<Msg<Kes>>(`${K}/kes`, body),
    kemasKini: (id: string, body: Body) => api.put<Msg<Kes>>(`${K}/kes/${id}`, body),
    tindakan: <T = unknown>(id: string, path: string, body: Body = {}) => api.post<Msg<T>>(`${K}/kes/${id}/${path}`, body),
    kemasKiniSub: <T = unknown>(id: string, path: string, body: Body) => api.put<Msg<T>>(`${K}/kes/${id}/${path}`, body),
    nyahpautLot: (id: string, lot: string) => api.delete<Msg<unknown>>(`${K}/kes/${id}/lot/${lot}`),
    dedahPihak: (id: string, pihak: number) => api.get<{ data: Pihak }>(`${K}/kes/${id}/pihak/${pihak}/dedah`),
    muatNaik: (id: string, form: FormData) => api.postForm<Msg<Dokumen>>(`${K}/kes/${id}/dokumen`, form),
    muatTurun: (id: string, dok: number) => api.get<{ data: { url: string } }>(`${K}/kes/${id}/dokumen/${dok}/muat-turun`),
    dashboard: (f: Record<string, unknown>) => api.get<{ data: Dashboard }>(`${K}/dashboard${qs(f)}`),
    kalendar: (f: Record<string, unknown>) => api.get<{ data: EntriKalendar[] }>(`${K}/kalendar${qs(f)}`),
    laporan: <T>(jenis: string, f: Record<string, unknown>) => api.get<{ data: T }>(`${K}/laporan/${jenis}${qs(f)}`),
    logCapaian: (f: Record<string, unknown>) =>
        api.get<Paged<{ id: number; cap_masa: string; pengguna: string | null; jenis_tindakan: string; jenis_nama: string; kes_id: string | null; no_rujukan: string | null; id_dokumen: string | null; alamat_ip: string | null; keterangan: string | null }>>(
            `${K}/log-capaian${qs(f)}`,
        ),
    konfigurasi: () => api.get<{ data: Konfigurasi }>(`${K}/konfigurasi`),
    simpanKonfigurasi: (jenis: 'jenis' | 'status' | 'panel', body: Body, id?: number) =>
        id ? api.put<Msg<unknown>>(`${K}/konfigurasi/${jenis}/${id}`, body) : api.post<Msg<unknown>>(`${K}/konfigurasi/${jenis}`, body),
    cariLot: (q: string) => api.get<{ data: Array<{ id: string; no_lot: string; kod_mukim: string; kod_daerah: string; mukim: string | null; daerah: string | null }> }>(`${K}/cari-lot${qs({ q })}`),
};

export interface Konfigurasi {
    jenis: Array<{ id: number; kod: string; nama: string; keterangan: string | null; susunan: number; aktif: boolean }>;
    status: Array<{ id: number; kod: string; nama: string; susunan: number; terminal: boolean; aktif: boolean }>;
    panel: Array<{ id: number; nama_firma: string; no_pendaftaran: string | null; pegawai_penghubung: string | null; no_telefon: string | null; emel: string | null; status: string; tarikh_lantikan: string | null; tarikh_tamat: string | null; id_pengguna: number | null; sintetik: boolean }>;
}

export function useRujukan() {
    return useQuery({ queryKey: ['kes', 'rujukan'], queryFn: () => kesApi.rujukan().then((r) => r.data), staleTime: 300_000 });
}

export async function muatTurunFail(path: string, params: Record<string, unknown>, fallbackName: string): Promise<void> {
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

/** The API's 403 for a password session: Rahsia records need an SSO MAIS (MFA) login. */
export function perluMfa(e: unknown): boolean {
    return e instanceof ApiError && e.status === 403 && /MFA/i.test(e.message);
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

export function rm(v: string | number | null | undefined): string {
    if (v === null || v === undefined || v === '') return '—';
    const n = typeof v === 'number' ? v : Number(v);
    return Number.isNaN(n) ? String(v) : `RM ${n.toLocaleString('ms-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

export const STATUS_VARIANT: Record<string, 'success' | 'warning' | 'danger' | 'info' | 'neutral'> = {
    didaftar: 'info',
    aktif: 'info',
    sebutan: 'warning',
    dalam_bicara: 'warning',
    tangguh: 'neutral',
    keputusan: 'success',
    rayuan: 'danger',
    selesai: 'success',
};
