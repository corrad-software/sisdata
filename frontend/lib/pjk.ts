import { useSyncExternalStore } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api, ApiError } from './api';
import type { User } from './types';

/* MOD-PJK client — Pajakan dan Kontrak (/api/v1/pjk). Labels come from the API (Malay); money is a DECIMAL string + "RM" text. */

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
    meta: { total: number; current_page: number; last_page: number };
}

export interface Penyewa {
    id: number;
    jenis_entiti: string;
    jenis_entiti_nama: string;
    nama: string;
    no_pengenalan: string | null;
    no_pendaftaran_syarikat: string | null;
    alamat: string | null;
    poskod: string | null;
    telefon: string | null;
    emel: string | null;
    ditutup: boolean;
    sintetik: boolean;
    bil_kontrak?: number;
    boleh_lihat_penuh?: boolean;
    kontrak?: Array<{ id: string; no_kontrak: string; status: string; status_nama: string; tarikh_mula: string; tarikh_tamat: string; kadar: Wang }>;
}

export interface Premis {
    id: number;
    lot_id: string;
    no_lot?: string | null;
    kod_premis: string;
    nama_premis: string;
    jenis_aset: string;
    jenis_aset_nama: string;
    kategori_hartanah: string;
    kategori_hartanah_nama: string;
    saluran_pemerolehan: string | null;
    keluasan: string | null;
    alamat: string | null;
    status_premis: string;
    status_premis_nama: string;
    tarikh_status: string | null;
    hari_status: number | null;
    kadar_tawaran: Wang;
    kod_daerah: string;
    kod_mukim: string;
    bahagian: string | null;
    bahagian_nama: string | null;
    papar_portal: boolean;
    sintetik: boolean;
    status_perolehan?: string;
    sejarah_status?: Array<{ tarikh: string; status_selepas: string; status_nama: string; sebab: string | null }>;
    kontrak?: Array<{ id: string; no_kontrak: string; penyewa: string; status: string; status_nama: string; tarikh_mula: string; tarikh_tamat: string }>;
}

export interface Kontrak {
    id: string;
    no_kontrak: string;
    lot_id: string;
    premis_id: number | null;
    premis: { id: number; kod_premis: string; nama_premis: string } | null;
    penyewa_id: number | null;
    penyewa: string | null;
    jenis_perjanjian: string;
    jenis_perjanjian_nama: string;
    kategori_hartanah: string | null;
    kategori_hartanah_nama: string | null;
    tarikh_mula: string | null;
    tarikh_tamat: string | null;
    baki_hari: number | null;
    kadar: Wang;
    deposit: Wang;
    nilai_kontrak: Wang;
    terma: string | null;
    kitaran_caj: string;
    kitaran_caj_nama: string;
    hari_tempoh_bayar: number;
    status: string;
    status_nama: string;
    tarikh_status: string | null;
    kod_daerah: string | null;
    kod_mukim: string | null;
    bahagian: string | null;
    no_versi: number;
    id_kontrak_asal: string | null;
    id_dokumen_perjanjian: string | null;
    sumber: string;
    tarikh_penamatan: string | null;
    sebab_penamatan: string | null;
    baki_lejar: Wang | null;
    sintetik: boolean;
    penyewa_rekod?: Penyewa | null;
    lot?: { id: string; no_lot: string; kod_mukim: string; pautan: string } | null;
    umur_hutang?: { jumlah: string; kumpulan: Record<string, string> };
    pindaan_menunggu?: boolean;
}

export interface Caj {
    id: number;
    no_caj: string;
    kod_caj: string;
    kod_caj_nama: string;
    tempoh: string;
    tarikh_caj: string;
    tarikh_akhir: string;
    amaun: Wang;
    amaun_dibayar: Wang;
    amaun_dikecualikan: Wang;
    baki: Wang;
    status_caj: string;
    status_caj_nama: string;
    dikeluarkan: boolean;
    umur_tunggakan: number;
    kumpulan_umur: string | null;
    keterangan: string | null;
}

export interface Lejar {
    id: number;
    no_transaksi: string;
    tarikh_transaksi: string;
    jenis: string;
    jenis_nama: string;
    debit: Wang;
    kredit: Wang;
    baki_berjalan: Wang;
    kaedah_bayaran_nama: string | null;
    no_resit: string | null;
    status_pelarasan: string;
    keterangan: string | null;
}

export interface Resit {
    id: number;
    no_resit: string;
    jenis: string;
    amaun: Wang;
    tarikh: string;
    id_dokumen: string | null;
}

export interface Bayaran {
    id: number;
    no_bayaran: string;
    kontrak_id: string;
    saluran: string;
    saluran_nama: string;
    amaun: Wang;
    tarikh_bayaran: string;
    rujukan_luar: string | null;
    id_dokumen_bukti: string | null;
    status: string;
    status_nama: string;
    nama_pembayar: string | null;
    id_perekod: number | null;
    catatan: string | null;
    resit: Resit[] | null;
    kontrak: { id: string; no_kontrak: string; penyewa: string } | null;
}

export interface TransaksiGateway {
    id: string;
    no_rujukan: string;
    kontrak_id: string;
    saluran: string;
    saluran_nama: string;
    amaun: Wang;
    status: string;
    status_nama: string;
    url_bayaran: string | null;
    masa_mula: string;
    masa_pengesahan: string | null;
    kod_respons: string | null;
    disahkan_melalui: string | null;
    no_kontrak?: string;
    nama_pembayar?: string | null;
}

export interface Deposit {
    id: number;
    jenis: string;
    amaun: Wang;
    tarikh_terima: string | null;
    status: string;
    status_nama: string;
    amaun_tolakan: Wang;
    justifikasi_tolakan: string | null;
    amaun_dipulangkan: Wang;
    tarikh_pulangan: string | null;
}

export interface Pindaan {
    id: number;
    jenis_pindaan: string;
    jenis_pindaan_nama: string;
    data_cadangan: Record<string, unknown>;
    justifikasi: string;
    status: string;
    ulasan: string | null;
    dicipta: string;
    tarikh_keputusan: string | null;
    id_kontrak_baharu: string | null;
}

export interface Pelarasan {
    id: number;
    jenis: string;
    jenis_nama: string;
    amaun: Wang;
    justifikasi: string;
    status: string;
    ulasan: string | null;
}

export interface Permohonan {
    id: number;
    no_permohonan: string;
    sumber: string;
    rujukan_portal: string | null;
    premis: { id: number; kod_premis: string; nama_premis: string } | null;
    penyewa_id: number | null;
    pemohon: { jenis_entiti: string | null; nama: string | null; no_pengenalan: string | null; emel: string | null } | null;
    tarikh_mula_dicadang: string | null;
    tempoh_bulan: number;
    kadar_dicadang: Wang;
    tujuan: string | null;
    status: string;
    status_nama: string;
    kontrak_id: string | null;
    catatan: string | null;
    dicipta: string;
}

export interface Dasar {
    id: number;
    versi_dasar: number;
    kategori_hartanah: string;
    kategori_hartanah_nama: string;
    status_perolehan: string;
    status_perolehan_nama: string;
    kadar_agihan: string;
    penerima_manfaat: Array<{ kod: string; nama: string; peratus: string }>;
    tarikh_mula_kuasa: string;
    tarikh_tamat: string | null;
    rujukan_kelulusan: string | null;
    status: string;
}

export interface Kiraan {
    id?: number;
    premis_id: number;
    kod_premis: string;
    tempoh_perakaunan: string;
    hasil_kutipan: string;
    versi_dasar?: number;
    kategori_hartanah?: string;
    status_perolehan?: string;
    kadar_agihan?: string;
    amaun_agihan?: string;
    pecahan_penerima?: Array<{ nama: string; peratus: string; amaun: string }>;
    status_pengesahan?: string;
    simulasi?: boolean;
    ralat?: string;
}

export interface Laporan {
    tajuk: string;
    kepala: string[];
    baris: Array<Array<string | number | null>>;
    ringkasan: Record<string, unknown>;
}

export interface Kpi {
    kod: string;
    label: string;
    domain: string;
    nilai: number | null;
    unit: string;
    sasaran?: number | null;
    pecahan?: Array<{ label: string; nilai: number; kod?: string }>;
    pautan?: string;
}

export interface Rujukan {
    status_kontrak: Kod[];
    status_premis: Kod[];
    kategori_hartanah: Kod[];
    jenis_aset: Kod[];
    jenis_entiti: Kod[];
    jenis_perjanjian: Kod[];
    kitaran_caj: Kod[];
    saluran_bayaran: Kod[];
    saluran_luar_talian: Kod[];
    jenis_pindaan: Kod[];
    jenis_pelarasan: Kod[];
    status_permohonan: Kod[];
    jenis_surat: Kod[];
    status_perolehan: Kod[];
    bahagian: Kod[];
    kumpulan_umur: Kod[];
    jenis_laporan: Kod[];
    daerah: Kod[];
    mukim: Array<Kod & { daerah_kod: string }>;
    gateway: { kod: string; saluran: string[]; ujian: boolean };
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

export const pjkApi = {
    rujukan: () => api.get<{ data: Rujukan }>('/pjk/rujukan').then((r) => r.data),
    ringkasan: (p: Record<string, unknown> = {}) => api.get<{ data: { kpi: Kpi[]; penghunian: Laporan } }>(`/pjk/ringkasan${qs(p)}`).then((r) => r.data),

    kontrak: (p: Record<string, unknown>) => api.get<Halaman<Kontrak>>(`/pjk/kontrak${qs(p)}`),
    kontrakSatu: (id: string) => api.get<{ data: Kontrak }>(`/pjk/kontrak/${id}`).then((r) => r.data),
    kontrakCipta: (body: Record<string, unknown>) => api.post<Msg<Kontrak>>('/pjk/kontrak', body),
    kontrakKemasKini: (id: string, body: Record<string, unknown>) => api.put<Msg<Kontrak>>(`/pjk/kontrak/${id}`, body),
    hantar: (id: string) => api.post<Msg<Kontrak>>(`/pjk/kontrak/${id}/hantar`),
    caj: (id: string) => api.get<{ data: Caj[]; umur_hutang: { jumlah: string; kumpulan: Record<string, string> } }>(`/pjk/kontrak/${id}/caj`),
    lejar: (id: string, p: Record<string, unknown> = {}) =>
        api.get<{ data: Lejar[]; ringkasan: Record<string, Wang>; pelarasan: Pelarasan[] }>(`/pjk/kontrak/${id}/lejar${qs(p)}`),
    bayaranKontrak: (id: string) => api.get<{ data: Bayaran[]; gateway: TransaksiGateway[] }>(`/pjk/kontrak/${id}/bayaran`),
    deposit: (id: string) => api.get<{ data: Deposit[] }>(`/pjk/kontrak/${id}/deposit`).then((r) => r.data),
    pindaan: (id: string) =>
        api.get<{
            data: Pindaan[];
            versi: Array<{ id_versi: number; no_versi: number; jenis_pindaan_nama: string; tarikh_pindaan: string; justifikasi: string | null; data_sebelum: Record<string, unknown> | null; data_selepas: Record<string, unknown>; id_dokumen_sebelum: string | null; id_dokumen_selepas: string | null }>;
            sejarah_status: Array<{ status_nama: string; pegawai: string | null; catatan: string | null; masa: string }>;
            surat: Array<{ id: number; no_rujukan_surat: string; tajuk: string; versi_templat: number; id_dokumen: string | null; masa: string }>;
        }>(`/pjk/kontrak/${id}/pindaan`),
    semakanKadar: (id: string) => api.get<{ data: Record<string, string | number | boolean | null> }>(`/pjk/kontrak/${id}/semakan-kadar`).then((r) => r.data),
    mohonPindaan: (id: string, form: FormData) => api.postForm<Msg<Pindaan>>(`/pjk/kontrak/${id}/pindaan`, form),
    mohonPelarasan: (id: string, body: Record<string, unknown>) => api.post<Msg<Pelarasan>>(`/pjk/kontrak/${id}/pelarasan`, body),
    rekodBayaran: (id: string, form: FormData) => api.postForm<Msg<Bayaran>>(`/pjk/kontrak/${id}/bayaran`, form),
    bayarDalamTalian: (id: string, body: Record<string, unknown>, key: string) =>
        fetchJson<{ data: TransaksiGateway }>(`/pjk/kontrak/${id}/bayaran-dalam-talian`, body, { 'Idempotency-Key': key }),
    penyelarasanDeposit: (id: number, body: Record<string, unknown>) => api.post<Msg<Deposit>>(`/pjk/deposit/${id}/penyelarasan`, body),
    janaSurat: (id: string, templatId: number) => api.post<Msg<{ no_rujukan_surat: string; kandungan: string }>>(`/pjk/kontrak/${id}/surat`, { templat_id: templatId }),
    pratontonSurat: (id: string, templatId: number) => api.get<{ data: { kandungan: string } }>(`/pjk/kontrak/${id}/surat/${templatId}/pratonton`).then((r) => r.data),
    resit: (id: number) => api.get<{ data: { url: string } }>(`/pjk/resit/${id}`),

    penyewa: (p: Record<string, unknown>) => api.get<Halaman<Penyewa>>(`/pjk/penyewa${qs(p)}`),
    penyewaSatu: (id: number | string) => api.get<{ data: Penyewa }>(`/pjk/penyewa/${id}`).then((r) => r.data),
    penyewaCipta: (body: Record<string, unknown>) => api.post<Msg<Penyewa>>('/pjk/penyewa', body),

    premis: (p: Record<string, unknown>) => api.get<Halaman<Premis>>(`/pjk/premis${qs(p)}`),
    premisSatu: (id: number | string) => api.get<{ data: Premis }>(`/pjk/premis/${id}`).then((r) => r.data),
    premisCipta: (body: Record<string, unknown>) => api.post<Msg<Premis>>('/pjk/premis', body),
    premisKemasKini: (id: number, body: Record<string, unknown>) => api.put<Msg<Premis>>(`/pjk/premis/${id}`, body),
    lot: (q: string) => api.get<{ data: Array<{ id: string; no_lot: string; kod_mukim: string; kod_daerah: string; bahagian_pemilik: string | null }> }>(`/pjk/lot${qs({ q })}`).then((r) => r.data),

    permohonan: (p: Record<string, unknown>) => api.get<Halaman<Permohonan>>(`/pjk/permohonan${qs(p)}`),
    permohonanCipta: (body: Record<string, unknown>) => api.post<Msg<Permohonan>>('/pjk/permohonan', body),
    permohonanKontrak: (id: number, body: Record<string, unknown>) => api.post<Msg<Kontrak>>(`/pjk/permohonan/${id}/kontrak`, body),
    permohonanTolak: (id: number, catatan: string) => api.post<Msg<Permohonan>>(`/pjk/permohonan/${id}/tolak`, { catatan }),

    bayaranMenunggu: () => api.get<{ data: Bayaran[] }>('/pjk/bayaran').then((r) => r.data),
    sahkanBayaran: (id: number, terima: boolean, catatan?: string) => api.post<Msg<Bayaran>>(`/pjk/bayaran/${id}/pengesahan`, { terima, catatan }),

    gatewayUjian: (ref: string) => api.get<{ data: TransaksiGateway }>(`/pjk/gateway-ujian/${encodeURIComponent(ref)}`).then((r) => r.data),
    simulasiGateway: (ref: string, keputusan: string) =>
        api.post<{ data: TransaksiGateway; panggilan_balik: string | null }>(`/pjk/gateway-ujian/${encodeURIComponent(ref)}/simulasi`, { keputusan }),

    rekonsiliasi: (p: Record<string, unknown> = {}) =>
        api.get<{
            larian: Array<{ id: number; sumber: string; tarikh: string; bil_padan: number; bil_sisihan: number; status: string; mesej: string | null }>;
            sisihan: Array<{ id: number; larian_id: number; sumber: string; tarikh: string; jenis_nama: string; rujukan: string; kontrak_id: string | null; amaun_sistem: string | null; amaun_luar: string | null; status_tindakan: string; catatan: string | null }>;
        }>(`/pjk/rekonsiliasi${qs(p)}`),
    jalanRekonsiliasi: (sumber: string, tarikh: string) => api.post<{ data: { bil_padan: number; bil_sisihan: number; status: string } }>('/pjk/rekonsiliasi', { sumber, tarikh }),
    muatNaikPenyelesaian: (form: FormData) => api.postForm<{ data: { bil_padan: number; bil_sisihan: number } }>('/pjk/rekonsiliasi/fail', form),
    tindakanSisihan: (id: number, status_tindakan: string, catatan: string) => api.put<{ message: string }>(`/pjk/sisihan/${id}`, { status_tindakan, catatan }),

    dasar: () => api.get<{ data: Dasar[] }>('/pjk/dasar').then((r) => r.data),
    dasarCipta: (body: Record<string, unknown>) => api.post<Msg<Dasar>>('/pjk/dasar', body),
    dasarHantar: (id: number) => api.post<Msg<Dasar>>(`/pjk/dasar/${id}/hantar`),
    kiraan: (p: Record<string, unknown>) => api.get<{ data: Kiraan[] }>(`/pjk/agihan${qs(p)}`).then((r) => r.data),
    kira: (tempoh: string) => api.post<{ data: Kiraan[] }>('/pjk/agihan/kira', { tempoh }).then((r) => r.data),
    simulasi: (tempoh: string, kadar: Record<string, string>) => api.post<{ data: Kiraan[] }>('/pjk/agihan/simulasi', { tempoh, kadar }).then((r) => r.data),
    sahkanKiraan: (ids: number[]) => api.post<{ message: string }>('/pjk/agihan/sahkan', { ids }),

    templat: () =>
        api.get<{ data: Array<{ id: number; kod: string; jenis_surat: string; jenis_surat_nama: string; tajuk: string; kandungan: string; versi: number }>; medan: string[] }>('/pjk/templat'),
    templatKemasKini: (id: number, body: Record<string, unknown>) => api.put<{ message: string }>(`/pjk/templat/${id}`, body),

    laporan: (jenis: string, p: Record<string, unknown>) => api.get<{ data: Laporan }>(`/pjk/laporan/${jenis}${qs(p)}`).then((r) => r.data),
};

/** POST JSON with extra headers (Idempotency-Key) — same CSRF handling as lib/api. */
async function fetchJson<T>(path: string, body: unknown, extra: Record<string, string>): Promise<T> {
    const xsrf = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1];
    if (!xsrf) await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
    const token = decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '');
    const res = await fetch(`/api/v1${path}`, {
        method: 'POST',
        credentials: 'include',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token, ...extra },
        body: JSON.stringify(body),
    });
    const data = res.headers.get('content-type')?.includes('application/json') ? await res.json() : null;
    if (!res.ok) throw new ApiError(res.status, data);
    return data as T;
}

/** Download a PDF / Excel produced by the API. */
export async function muatTurun(path: string, params: Record<string, unknown>, fallbackName: string): Promise<void> {
    const response = await fetch(`/api/v1${path}${qs(params)}`, { credentials: 'include', headers: { Accept: '*/*' } });
    if (!response.ok) {
        const body = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null;
        throw new ApiError(response.status, body);
    }
    if (response.headers.get('content-type')?.includes('application/json')) {
        const json = (await response.json()) as { data?: { url?: string } };
        if (json.data?.url) window.open(json.data.url, '_blank', 'noopener');
        return;
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
    return useQuery({ queryKey: ['pjk', 'rujukan'], queryFn: pjkApi.rujukan, staleTime: 300_000 });
}

const noop = () => () => {};

/** `can('a', 'b')` = holds any of them. UI hint only — the API enforces access. */
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

/** "1234.5" → "RM 1,234.50" (display only; the API already sends `rm`). */
export function rm(v: string | number | null | undefined): string {
    if (v === null || v === undefined || v === '') return '—';
    const n = typeof v === 'number' ? v : Number(v);
    return `RM ${n.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

export function idempotencyKey(): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
}

export const STATUS_VARIANT: Record<string, 'success' | 'warning' | 'danger' | 'neutral' | 'info'> = {
    draf: 'neutral',
    dalam_kelulusan: 'info',
    aktif: 'success',
    hampir_tamat: 'warning',
    tamat_tempoh: 'danger',
    diperbaharui: 'info',
    ditamatkan: 'neutral',
    kosong: 'info',
    disewa: 'success',
    penyelenggaraan: 'warning',
    penguatkuasaan: 'danger',
    tidak_tersedia: 'neutral',
    belum_bayar: 'warning',
    separa: 'warning',
    dibayar: 'success',
    dikecualikan: 'neutral',
    dibatalkan: 'neutral',
    dijadualkan: 'neutral',
    menunggu: 'info',
    menunggu_pengesahan: 'info',
    disahkan: 'success',
    ditolak: 'danger',
    berjaya: 'success',
    gagal: 'danger',
    tamat_masa: 'danger',
    lulus: 'success',
    tolak: 'danger',
    diterima: 'info',
    dalam_semakan: 'info',
    kontrak_disediakan: 'info',
    dipegang: 'success',
    belum_diterima: 'warning',
    dalam_penyelarasan: 'info',
    dipulangkan: 'neutral',
    dilucut_hak: 'neutral',
    baharu: 'warning',
    dalam_tindakan: 'info',
    selesai: 'success',
    digantikan: 'neutral',
};
