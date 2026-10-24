import { useSyncExternalStore } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api, ApiError } from './api';
import type { User } from './types';

/* MOD-CKI client — Pemantauan Cukai Tanah (/api/v1/cki). Labels come from the API (Malay); money is a DECIMAL string + "RM" text. */

export interface Kod {
    kod: string;
    nama: string;
}

export interface Wang {
    nilai: string | null;
    rm: string;
}

export interface Halaman<T> {
    data: T[];
    meta: { total: number; current_page: number; last_page: number; tahun?: number };
}

export interface Rujukan {
    jenis_cukai: Kod[];
    pihak_pengeluar: Kod[];
    status_bayaran: Kod[];
    sumber_rekod: Kod[];
    kaedah_bayaran: Kod[];
    medan_kaedah: Record<string, string[]>;
    status_transaksi: Kod[];
    kumpulan_umur: Kod[];
    jenis_sisihan: Kod[];
    jenis_laporan: Kod[];
    daerah: Kod[];
    mukim: Array<Kod & { daerah_kod: string }>;
    agensi: Array<Kod & { jenis: string }>;
    pbt: Kod[];
}

export interface Obligasi {
    id: string;
    lot_id: string;
    no_lot: string | null;
    id_premis: number | null;
    jenis_cukai: string;
    jenis_cukai_nama: string;
    pihak_pengeluar: string;
    pihak_pengeluar_nama: string;
    kod_pbt: string | null;
    kod_ptd: string | null;
    kod_agensi: string;
    nama_agensi: string | null;
    mod_agensi: string | null;
    no_akaun_cukai: string;
    status_liputan_api: 'dalam' | 'luar';
    kod_daerah: string;
    kod_mukim: string;
    aktif: boolean;
    sintetik: boolean;
    catatan: string | null;
}

export interface Bil {
    id: string;
    lot_id: string;
    no_lot: string | null;
    obligasi_id: string | null;
    jenis_cukai: string;
    jenis_cukai_nama: string;
    no_bil: string | null;
    tahun_taksiran: number | null;
    pihak_pengeluar: string | null;
    pihak_pengeluar_nama: string | null;
    kod_agensi: string | null;
    kod_pbt: string | null;
    kod_daerah: string | null;
    kod_mukim: string | null;
    amaun_pokok: Wang;
    amaun_denda: Wang;
    jumlah: Wang;
    amaun_dibayar: Wang;
    baki: Wang;
    tarikh_bil: string | null;
    tarikh_akhir: string | null;
    tarikh_bayar: string | null;
    status_bayaran: string;
    status_bayaran_nama: string;
    kaedah_bayaran: string | null;
    kaedah_bayaran_nama: string | null;
    no_resit: string | null;
    sumber_rekod: string;
    sumber_rekod_nama: string;
    disegerak_pada: string | null;
    denda_dikenakan: boolean;
    sintetik: boolean;
    versi: number;
}

export interface Kelulusan {
    id: number;
    peringkat: string;
    peringkat_nama: string;
    tahap: number;
    status: string;
    id_aliran: string | null;
    tarikh_kelulusan: string | null;
    justifikasi: string | null;
}

export interface Transaksi {
    id: string;
    no_permohonan: string;
    id_bil: string;
    no_bil?: string | null;
    jenis_cukai_nama?: string | null;
    kaedah_bayaran: string;
    kaedah_bayaran_nama: string;
    amaun: Wang;
    tarikh_bayaran: string | null;
    no_rujukan_eft: string | null;
    no_bank_draf: string | null;
    bank_penerbit: string | null;
    rujukan_fpx: string | null;
    nama_kaunter: string | null;
    no_resit: string | null;
    id_dokumen_resit: string | null;
    id_dokumen_permohonan: string | null;
    status_transaksi: string;
    status_transaksi_nama: string;
    melalui_api: boolean;
    rujukan_grp: string | null;
    status_grp: string | null;
    kod_daerah: string;
    medan_wajib: string[];
    catatan: string | null;
    dicipta: string;
    kelulusan: Kelulusan | null;
}

export interface Resit {
    id: number;
    id_transaksi: string | null;
    id_bil: string | null;
    no_resit: string;
    no_bil_rujukan: string | null;
    amaun: Wang;
    tarikh_resit: string;
    sumber: string;
    id_dokumen: string | null;
    sintetik: boolean;
}

export interface Konflik {
    id: number;
    id_bil: string;
    no_bil: string | null;
    tahun_taksiran: number | null;
    kod_daerah: string | null;
    nilai_manual: Record<string, string | null>;
    nilai_api: Record<string, string | null>;
    medan_berkonflik: string[];
    status_resolusi: string;
    tarikh_resolusi: string | null;
    catatan: string | null;
    dicipta: string;
}

export interface BilDetail extends Bil {
    obligasi: Obligasi | null;
    lejar: Array<{ id: number; tarikh: string; jenis: string; debit: Wang; kredit: Wang; rujukan: string | null; keterangan: string | null }>;
    transaksi: Transaksi[];
    resit: Resit[];
    pelarasan: Array<{ id: number; medan: string; nilai_lama: string | null; nilai_baharu: string | null; justifikasi: string; tarikh: string }>;
    konflik: Konflik[];
    dokumen: Array<{ id: string; name: string; category: string; version: number; classification: string; uploaded_at: string }>;
    boleh_rekod_manual: boolean;
}

export interface Liputan {
    id: number;
    kod_agensi: string;
    jenis_agensi: string;
    nama_agensi: string;
    kod_pbt: string | null;
    kod_ptd: string | null;
    perkhidmatan_semakan: boolean;
    perkhidmatan_bayaran: boolean;
    mod_semasa: 'api' | 'sandaran';
    kegagalan_berturut: number;
    semakan_terakhir: string | null;
    catatan: string | null;
    bil_obligasi: number;
}

export interface Kpi {
    kod: string;
    label: string;
    nilai: number | null;
    unit: string;
    sasaran?: number | null;
    trend?: Array<{ tempoh: string; nilai: number }>;
    pecahan?: Array<{ label: string; nilai: number; kod?: string }>;
    pautan?: string;
}

export interface Kumpulan {
    kod: string;
    nama: string;
    bil: number;
    jumlah: string;
    dibayar: string;
    tertunggak: string;
}

export interface Dashboard {
    kpi: Kpi[];
    jumlah: {
        bil: number;
        jumlah_bil: string;
        telah_dibayar: string;
        tertunggak: string;
        bil_tertunggak: number;
        denda_terkumpul: string;
        risiko_lewat: string;
        bil_risiko_lewat: number;
    };
    mengikut_daerah: Kumpulan[];
    mengikut_pbt: Kumpulan[];
    mengikut_jenis: Kumpulan[];
    umur_tunggakan: Array<{ kod: string; nama: string; bil: number; amaun: string }>;
    anggaran: { tahun_asas: number; tahun_anggaran: number; kadar_peratus: string; jumlah_anggaran: string } | null;
}

export interface Laporan {
    tajuk: string;
    kepala: string[];
    baris: Array<Array<string | number | null>>;
    ringkasan?: Record<string, unknown>;
}

export interface Jadual {
    hari: number;
    hingga: string;
    jumlah: string;
    kumpulan: Array<{
        kod: string;
        nama: string;
        jumlah: string;
        bil: Array<{ id: string; no_bil: string | null; no_lot: string | null; jenis_nama: string; kod_daerah: string | null; kod_agensi: string | null; tarikh_akhir: string | null; baki: string; status_bayaran: string; status_nama: string }>;
    }>;
}

export interface LotRingkas {
    id: string;
    no_lot: string;
    kod_mukim: string;
    kod_daerah: string;
}

type Msg<T> = { data: T; message?: string };

export function qs(params: Record<string, unknown>): string {
    const p = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
        if (v !== undefined && v !== null && v !== '') p.set(k, String(v));
    }
    const s = p.toString();
    return s ? `?${s}` : '';
}

export const ckiApi = {
    rujukan: () => api.get<{ data: Rujukan }>('/cki/rujukan').then((r) => r.data),
    lot: (q: string) => api.get<{ data: LotRingkas[] }>(`/cki/lot${qs({ q })}`).then((r) => r.data),
    dashboard: (p: Record<string, unknown> = {}) => api.get<{ data: Dashboard }>(`/cki/dashboard${qs(p)}`).then((r) => r.data),

    obligasi: (p: Record<string, unknown>) => api.get<Halaman<Obligasi>>(`/cki/obligasi${qs(p)}`),
    obligasiCipta: (body: Record<string, unknown>) => api.post<Msg<Obligasi>>('/cki/obligasi', body),
    obligasiKemasKini: (id: string, body: Record<string, unknown>) => api.put<Msg<Obligasi>>(`/cki/obligasi/${id}`, body),

    bil: (p: Record<string, unknown>) => api.get<Halaman<Bil>>(`/cki/bil${qs(p)}`),
    bilSatu: (id: string) => api.get<{ data: BilDetail }>(`/cki/bil/${id}`).then((r) => r.data),
    bilCipta: (form: FormData) => api.postForm<Msg<Bil>>('/cki/bil', form),
    pelarasan: (id: string, body: Record<string, unknown>) => api.post<Msg<Bil>>(`/cki/bil/${id}/pelarasan`, body),
    dokumen: (id: string, form: FormData) => api.postForm<Msg<{ id: string }>>(`/cki/bil/${id}/dokumen`, form),
    resitBil: (id: string, form: FormData) => api.postForm<Msg<Resit>>(`/cki/bil/${id}/resit`, form),
    garisMasa: (lotId: string) => api.get<{ data: { lot: LotRingkas; pautan_profil: string; bil: Bil[] } }>(`/cki/lot/${lotId}/garis-masa`).then((r) => r.data),

    mohon: (id: string, body: Record<string, unknown>) => api.post<Msg<Transaksi>>(`/cki/bil/${id}/bayaran`, body),
    transaksi: (p: Record<string, unknown>) => api.get<Halaman<Transaksi>>(`/cki/transaksi${qs(p)}`),
    transaksiSatu: (id: string) => api.get<{ data: Transaksi & { resit: Resit[] } }>(`/cki/transaksi/${id}`).then((r) => r.data),
    laksana: (id: string) => api.post<Msg<Transaksi>>(`/cki/transaksi/${id}/laksana`),
    rekod: (id: string, body: Record<string, unknown>) => api.post<Msg<Transaksi>>(`/cki/transaksi/${id}/rekod`, body),
    resit: (id: string, form: FormData) => api.postForm<Msg<Resit>>(`/cki/transaksi/${id}/resit`, form),
    selesai: (id: string) => api.post<Msg<Transaksi>>(`/cki/transaksi/${id}/selesai`),
    semakGrp: (id: string) => api.post<Msg<Transaksi>>(`/cki/transaksi/${id}/semak-grp`),
    resitTanpaBil: (body: Record<string, unknown>) => api.post<Msg<Resit>>('/cki/resit', body),

    jadual: (p: Record<string, unknown>) => api.get<{ data: Jadual }>(`/cki/jadual${qs(p)}`).then((r) => r.data),
    tugasan: (p: Record<string, unknown>) => api.get<Halaman<Obligasi>>(`/cki/tugasan${qs(p)}`),
    konflik: (p: Record<string, unknown>) => api.get<Halaman<Konflik>>(`/cki/konflik${qs(p)}`),
    selesaiKonflik: (id: number, body: Record<string, unknown>) => api.post<Msg<Konflik>>(`/cki/konflik/${id}/selesai`, body),

    integrasi: () =>
        api.get<{ data: { penyedia: string; mock: boolean; mock_turun: boolean; agensi: Liputan[]; mod_aktif: Array<{ id: number; kod_agensi: string; mod: string; tarikh_mula: string; sebab: string | null }> } }>('/cki/integrasi').then((r) => r.data),
    liputanKemasKini: (id: number, body: Record<string, unknown>) => api.put<Msg<Liputan>>(`/cki/integrasi/${id}`, body),
    semak: (body: Record<string, unknown>) =>
        api.post<{ data: { agensi: number; obligasi: number; baharu: number; dikemas_kini: number; konflik: number; tanpa_ubah: number; gagal: string[]; dipulihkan: string[] } }>('/cki/integrasi/semak', body).then((r) => r.data),
    simulasi: (body: Record<string, unknown>) => api.post<Msg<unknown>>('/cki/integrasi/simulasi', body),
    log: (p: Record<string, unknown>) =>
        api.get<
            Halaman<{ id: number; jenis_panggilan: string; kod_agensi: string | null; masa_panggilan: string; masa_tindak_balas: number | null; status_http: number | null; berjaya: boolean; kod_ralat: string | null; ringkasan_payload: Record<string, unknown> | null; rujukan_transaksi: string | null; bilangan_cuba_semula: number }>
        >(`/cki/integrasi/log${qs(p)}`),

    rekonsiliasi: (p: Record<string, unknown>) =>
        api
            .get<{
                data: {
                    larian: Array<{ id: number; dari: string; hingga: string; bil_padan: number; bil_sisihan: number; dicipta: string }>;
                    larian_dipilih: number | null;
                    sisihan: Array<{ id: number; jenis: string; jenis_nama: string; id_bil: string | null; id_transaksi: string | null; id_resit: number | null; rujukan_grp: string | null; amaun_sistem: string | null; amaun_grp: string | null; amaun_resit: string | null; catatan: string | null; status: string; kod_daerah: string | null }>;
                };
            }>(`/cki/rekonsiliasi${qs(p)}`)
            .then((r) => r.data),
    jalankanRekonsiliasi: (body: Record<string, unknown>) => api.post<Msg<{ id: number; bil_padan: number; bil_sisihan: number }>>('/cki/rekonsiliasi', body),

    laporan: (jenis: string, p: Record<string, unknown>) => api.get<{ data: Laporan }>(`/cki/laporan/${jenis}${qs(p)}`).then((r) => r.data),
    import: (form: FormData) => api.postForm<{ data: { jumlah_baris: number; diterima: number; ditolak: number; ralat: Array<{ baris: number; mesej: string }>; simpan: boolean } }>('/cki/import', form).then((r) => r.data),
};

/** Download a PDF / Excel produced by the API. */
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

export function useRujukan() {
    return useQuery({ queryKey: ['cki', 'rujukan'], queryFn: ckiApi.rujukan, staleTime: 300_000 });
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

export function tarikh(d: string | null | undefined): string {
    if (!d) return '—';
    const [y, m, day] = d.slice(0, 10).split('-');
    return `${day}/${m}/${y}`;
}

export function masa(d: string | null | undefined): string {
    if (!d) return '—';
    const x = new Date(d);
    return `${tarikh(d)} ${String(x.getHours()).padStart(2, '0')}:${String(x.getMinutes()).padStart(2, '0')}`;
}

/** "RM 1,234.50" from a DECIMAL string (no float arithmetic on amounts). */
export function rm(v: string | null | undefined): string {
    if (v === null || v === undefined || v === '') return '—';
    const neg = v.startsWith('-');
    const [i, d = '00'] = v.replace('-', '').split('.');
    return `${neg ? '-' : ''}RM ${i.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${d.padEnd(2, '0').slice(0, 2)}`;
}

export const STATUS_VARIANT: Record<string, 'success' | 'warning' | 'danger' | 'neutral' | 'info'> = {
    belum_bayar: 'warning',
    dalam_proses: 'info',
    tertunggak: 'danger',
    dibayar: 'success',
    menunggu_kelulusan: 'info',
    diluluskan: 'info',
    ditolak: 'danger',
    dihantar_grp: 'info',
    selesai: 'success',
    dibatalkan: 'neutral',
    berjalan: 'info',
    lulus: 'success',
    tolak: 'danger',
    menunggu: 'warning',
    terima_api: 'success',
    kekal_manual: 'neutral',
    api: 'success',
    sandaran: 'danger',
    dalam: 'success',
    luar: 'warning',
    sisihan: 'danger',
    padan: 'success',
};

export const MEDAN_LABEL: Record<string, string> = {
    no_bil: 'No. bil',
    amaun_pokok: 'Amaun pokok',
    amaun_denda: 'Denda',
    tarikh_akhir: 'Tarikh akhir',
    tarikh_bil: 'Tarikh bil',
    status_bayaran: 'Status bayaran',
    tarikh_bayar: 'Tarikh bayar',
    no_resit: 'No. resit',
    no_rujukan_eft: 'No. rujukan EFT',
    no_bank_draf: 'No. bank draf',
    bank_penerbit: 'Bank penerbit',
    rujukan_fpx: 'Rujukan transaksi FPX',
    nama_kaunter: 'Nama kaunter',
};
