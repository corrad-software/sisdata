import { useSyncExternalStore } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api, ApiError } from './api';
import type { User } from './types';

/* MOD-TKT client — Tiket Sokongan Sistem (/api/v1/tkt). Labels come from the API (Malay). */

export interface Kod {
    kod: string;
    nama: string;
}

export interface Rujukan {
    kategori: Kod[];
    keterukan: Kod[];
    kesan: Kod[];
    kesegeraan: Kod[];
    matriks: Record<string, Record<string, number>>;
    modul: Kod[];
    status: Kod[];
    saluran: Kod[];
    jenis_aktiviti: Kod[];
    peranan_ahli: Kod[];
}

export interface SlaBahagian {
    sasaran: string | null;
    sebenar: string | null;
    baki_minit: number | null;
    status: 'tiada' | 'dalam_tempoh' | 'dilepasi' | 'dipenuhi' | 'tidak_dipenuhi';
}

export interface Sla {
    waktu_perkhidmatan: string;
    dijeda: boolean;
    jeda_minit: number;
    respons: SlaBahagian;
    selesai: SlaBahagian;
    peratus_digunakan: number | null;
    status: string;
    status_nama: string;
}

export interface Tiket {
    id: string;
    nombor_rujukan: string;
    tajuk: string;
    kategori: string;
    kategori_nama: string;
    keterukan: number;
    keterukan_nama: string;
    keterukan_dicadang: number | null;
    kesan: string | null;
    kesegeraan: string | null;
    modul: string | null;
    modul_nama: string | null;
    status: string;
    status_nama: string;
    saluran: string;
    saluran_nama: string;
    klasifikasi: string;
    isu_keselamatan: boolean;
    pelapor: { id: number; nama: string } | null;
    emel_pelapor: string | null;
    pasukan: { id: number; nama: string; aras: string } | null;
    aras_sokongan: string;
    juruteknik: { id: number; nama: string } | null;
    nama_vendor: string | null;
    rujukan_vendor: string | null;
    tarikh_serah_vendor: string | null;
    tarikh_buka: string;
    tarikh_respons_pertama: string | null;
    tarikh_selesai: string | null;
    tarikh_disahkan: string | null;
    tarikh_tutup: string | null;
    sla: Sla;
    peringkat_eskalasi: number | null;
    bilangan_buka_semula: number;
    kepuasan_skor: number | null;
    pendua_dicadang: { id: string; skor: number | null; nombor_rujukan: string | null } | null;
    id_tiket_induk: string | null;
    status_kelulusan: string | null;
    id_aduan_adu: string | null;
    sintetik: boolean;
    peranan_saya: { sokongan: boolean; pelapor: boolean; urus: boolean };
}

export interface Kronologi {
    id: number;
    jenis: string;
    jenis_nama: string;
    pengguna: string;
    kandungan: string | null;
    status_dari: string | null;
    status_ke: string | null;
    meta: Record<string, unknown> | null;
    cap_masa: string;
}

export interface Lampiran {
    id: number;
    nama_fail: string;
    jenis_fail: string | null;
    saiz: number;
    status_imbasan: string;
    tangkapan_skrin: boolean;
    persetujuan: boolean;
    kelihatan_pelapor: boolean;
    dimuat_naik: string;
}

export interface TiketButiran extends Tiket {
    penerangan: string;
    punca_akar: string | null;
    penyelesaian: string | null;
    tindakan_pembetulan: string | null;
    masa_henti_minit: number | null;
    sebab_lepas_sla: string | null;
    versi_sistem: string | null;
    kepuasan_ulasan: string | null;
    konteks: {
        skrin: string | null;
        modul: string | null;
        kod_fungsi: string | null;
        url: string | null;
        peranan_pengguna: string[];
        id_pengguna: number | null;
        pelayar: string | null;
        versi_sistem: string | null;
        versi_ui: string | null;
        rujukan_rekod: string | null;
        id_permintaan: string[];
        id_permintaan_cipta: string | null;
        saiz_skrin: string | null;
        zon_masa: string | null;
        cap_masa_tangkapan: string;
    } | null;
    kronologi: Kronologi[];
    lampiran: Lampiran[];
    peralihan: string[];
    boleh: { urus: boolean; balas: boolean; sahkan: boolean; buka_semula: boolean; kepuasan: boolean; lampiran: boolean };
    eskalasi?: Array<{ peringkat: number; sasaran: string; sasaran_nama: string; sebab: string; sebab_nama: string; penerima: string | null; jenis_penerima: string | null; catatan: string | null; cap_masa: string }>;
    artikel_kb?: Array<{ id: number; tajuk: string; status: string; pautan: string }>;
    log_ralat?: { id_korelasi: string[]; entri: Array<{ id: number; id_korelasi: string; tarikh_masa: string; aras: string; modul: string | null; kod_ralat: string | null; mesej: string; pautan: string }>; boleh_lihat: boolean };
    pautan_aduan?: { nombor_aduan_berpusat: string; arah_asal: string; status_penyegerakan: string; akhir: string | null } | null;
}

export interface Paged<T> {
    data: T[];
    meta: { total: number; current_page: number; last_page: number; per_page: number };
}

export interface ArtikelCadangan {
    dokumen: string;
    versi: string | null;
    seksyen: string | null;
    petikan: string;
    skor: number;
    pautan: string;
    diklik: boolean;
}

export interface Pecahan {
    kod: string;
    label: string;
    nilai: number;
}

export interface Pematuhan {
    bilangan: number;
    respons_dipenuhi: number;
    selesai_dipenuhi: number;
    peratus_respons: number | null;
    peratus_selesai: number | null;
}

export interface PapanPemuka {
    tempoh: { dari: string; hingga: string };
    terbuka: number;
    terbuka_dilepasi_sla: number;
    terbuka_mengikut_keterukan: Pecahan[];
    terbuka_mengikut_modul: Pecahan[];
    sla: Pematuhan;
    sla_mengikut_keterukan: Array<Pematuhan & { keterukan: number; label: string; mttr_jam: number | null }>;
    mttr_jam: number | null;
    purata_respons_jam: number | null;
    modul_tertinggi: Pecahan[];
    masalah_berulang: { gabungan: Array<{ modul: string; modul_nama: string; kategori: string; kategori_nama: string; bilangan: number }>; tajuk: Array<{ modul: string; tajuk: string; bilangan: number }> };
    trend: Array<{ tempoh: string; dibuka: number; diselesaikan: number }>;
    kepuasan: { bilangan: number; purata: number | null };
    penyelesaian_kendiri: { sesi: number; diteruskan: number; diselesaikan_sendiri: number; tiada_keputusan: number; kadar_penyelesaian_kendiri: number | null; artikel_tidak_berkesan: Array<{ pautan: string; tajuk: string | null; dipapar: number; diklik: number; tiket_diteruskan: number }> };
    prestasi_juruteknik: Array<{ nama: string; diselesaikan: number; peratus_sla: number | null }>;
}

export interface Seksyen {
    tajuk: string;
    lajur: string[];
    baris: Array<Array<string | number | null>>;
}

export interface LaporanBulanan {
    bulan: string;
    ringkasan: Record<string, unknown>;
    seksyen: Seksyen[];
    senarai_bulan: string[];
    arkib: { dijana_pada: string; id_dokumen_pdf: string | null; id_dokumen_xlsx: string | null; ralat: string | null } | null;
}

export interface SlaKonfig {
    id: number;
    keterukan: number;
    keterukan_nama: string;
    ambang_respons: number;
    ambang_penyelesaian: number;
    waktu_perkhidmatan: string;
    ambang_amaran_peratus: number;
    hierarki_eskalasi: Array<{ peringkat: number; penerima: string; selepas_minit: number }>;
    tarikh_berkuat_kuasa: string;
    aktif: boolean;
    catatan: string | null;
    semasa: boolean;
}

export interface PasukanKonfig {
    id: number;
    kod: string;
    nama: string;
    aras: string;
    jenis: string;
    nama_vendor: string | null;
    modul: string[] | null;
    aktif: boolean;
    ahli: Array<{ id: number; id_pengguna: number; nama: string | null; emel: string | null; peranan: string; aktif: boolean }>;
}

export interface Konfigurasi {
    sla: SlaKonfig[];
    pasukan: PasukanKonfig[];
    peraturan: Array<{ id: number; modul: string | null; kategori: string | null; keterukan: number | null; id_pasukan: number; pasukan: string | null; id_juruteknik: number | null; keutamaan: number; aktif: boolean }>;
    pemetaan: Array<{ id: number; jenis: string; nilai_tempatan: string; nilai_berpusat: string; arah: string; aktif: boolean }>;
    nota_sla: string;
}

export interface Rekonsiliasi {
    id_tiket: string;
    nombor_rujukan: string;
    nombor_aduan: string;
    status_semasa: string;
    status_semasa_nama: string;
    status_disegerak: string | null;
    status_disegerak_nama: string | null;
    status_penyegerakan: string;
    cubaan: number;
    ralat: string | null;
    akhir: string | null;
}

export interface LogIntegrasi {
    id: number;
    id_tiket: string | null;
    nombor_rujukan: string | null;
    arah: string;
    endpoint: string;
    kunci_idempotensi: string | null;
    ringkasan: string | null;
    status: string;
    kod_status: number | null;
    cubaan: number;
    cuba_seterusnya_pada: string | null;
    ralat: string | null;
    cap_masa: string;
}

export interface Aktiviti {
    id: number;
    jenis: string;
    jenis_nama: string;
    tarikh: string;
    tajuk: string;
    keterangan: string | null;
    tempoh_minit: number | null;
    punca_akar: string | null;
    rujukan: string | null;
    sintetik: boolean;
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

const T = '/tkt';

export const tktApi = {
    rujukan: () => api.get<{ data: Rujukan }>(`${T}/rujukan`),
    cadangan: (body: { teks: string; tajuk?: string; sesi: string; modul?: string | null }) =>
        api.post<{ data: { artikel: ArtikelCadangan[]; tiket_serupa: Array<{ id: string; nombor_rujukan: string; tajuk: string; status: string; skor: number }> } }>(`${T}/cadangan`, body),
    jawapan: (teks: string) => api.post<{ data: { jawapan: string; cukup_sumber: boolean; sumber: Array<{ dokumen: string; pautan: string }> } }>(`${T}/cadangan/jawapan`, { teks }),
    klik: (sesi: string, pautan: string) => api.post<unknown>(`${T}/cadangan/klik`, { sesi, pautan }),
    selesaiSendiri: (sesi: string) => api.post<Msg<{ direkod: number }>>(`${T}/cadangan/selesai`, { sesi }),
    cipta: (form: FormData) => api.postForm<Msg<TiketButiran>>(`${T}/tiket`, form),
    senarai: (p: Record<string, unknown>) => api.get<Paged<Tiket>>(`${T}/tiket${qs(p)}`),
    tiket: (id: string) => api.get<{ data: TiketButiran }>(`${T}/tiket/${id}`),
    tindakan: (id: string, action: string, body: Body = {}) => api.post<Msg<TiketButiran>>(`${T}/tiket/${id}/${action}`, body),
    vendor: (id: string, body: Body) => api.put<Msg<TiketButiran>>(`${T}/tiket/${id}/vendor`, body),
    lampiran: (id: string, form: FormData) => api.postForm<Msg<TiketButiran>>(`${T}/tiket/${id}/lampiran`, form),
    muatTurun: (id: string, lampiran: number) => api.get<{ data: { url: string } }>(`${T}/tiket/${id}/lampiran/${lampiran}/muat-turun`),
    pegawaiLayak: (id: string) => api.get<{ data: Array<{ id: number; nama: string; pasukan: string; id_pasukan: number; aras: string; peranan: string; beban: number }> }>(`${T}/tiket/${id}/pegawai-layak`),
    papanPemuka: (f: Record<string, unknown>) => api.get<{ data: PapanPemuka }>(`${T}/laporan/papan-pemuka${qs(f)}`),
    bulanan: (bulan: string) => api.get<{ data: LaporanBulanan }>(`${T}/laporan/bulanan${qs({ bulan })}`),
    jana: (bulan: string) => api.post<Msg<{ bulan: string; ralat: string | null }>>(`${T}/laporan/bulanan/jana${qs({ bulan })}`),
    eskalasi: (f: Record<string, unknown>) => api.get<{ data: Array<{ cap_masa: string; id_tiket: string; nombor_rujukan: string | null; keterukan: number | null; peringkat: number; sebab: string; sasaran: string; penerima: string | null }> }>(`${T}/laporan/eskalasi${qs(f)}`),
    aktiviti: (f: Record<string, unknown>) => api.get<{ data: Aktiviti[] }>(`${T}/laporan/aktiviti${qs(f)}`),
    simpanAktiviti: (body: Body) => api.post<Msg<{ id: number }>>(`${T}/aktiviti`, body),
    konfigurasi: () => api.get<{ data: Konfigurasi }>(`${T}/konfigurasi`),
    simpan: (path: string, body: Body, id?: number) => (id ? api.put<Msg<{ id: number }>>(`${T}/konfigurasi/${path}/${id}`, body) : api.post<Msg<{ id: number }>>(`${T}/konfigurasi/${path}`, body)),
    simpanAhli: (pasukan: number, body: Body) => api.post<Msg<{ id: number }>>(`${T}/konfigurasi/pasukan/${pasukan}/ahli`, body),
    nyahaktifSla: (id: number) => api.post<Msg<unknown>>(`${T}/konfigurasi/sla/${id}/nyahaktif`),
    pengguna: (q: string) => api.get<{ data: Array<{ id: number; nama: string; emel: string }> }>(`${T}/konfigurasi/pengguna${qs({ q })}`),
    rekonsiliasi: () => api.get<{ data: Rekonsiliasi[] }>(`${T}/konfigurasi/integrasi/rekonsiliasi`),
    logIntegrasi: (f: Record<string, unknown>) => api.get<Paged<LogIntegrasi>>(`${T}/konfigurasi/integrasi/log${qs(f)}`),
    ulangCuba: () => api.post<Msg<{ berjaya: number; gagal: number }>>(`${T}/konfigurasi/integrasi/ulang-cuba`),
    selaras: () => api.post<Msg<{ diselaras: number }>>(`${T}/konfigurasi/integrasi/selaras`),
    pindah: (body: Body) => api.post<Msg<{ tiket: number; peraturan: number }>>(`${T}/konfigurasi/serahan/pindah`, body),
};

export const EKSPORT_BULANAN = (bulan: string, format: 'pdf' | 'xlsx') => `/api/v1/tkt/laporan/bulanan/eksport${qs({ bulan, format })}`;
export const PAKEJ_SERAHAN = '/api/v1/tkt/serahan/pakej';

export function useRujukan() {
    return useQuery({ queryKey: ['tkt', 'rujukan'], queryFn: () => tktApi.rujukan().then((r) => r.data), staleTime: 300_000 });
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

export function tarikhMasa(value: string | null | undefined): string {
    if (!value) return '—';
    return new Date(value).toLocaleString('ms-MY', { timeZone: 'Asia/Kuala_Lumpur', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

export function tempoh(minit: number | null | undefined): string {
    if (minit === null || minit === undefined) return '—';
    const neg = minit < 0;
    const m = Math.abs(minit);
    const h = Math.floor(m / 60);
    const text = h >= 24 ? `${Math.floor(h / 24)} hari ${h % 24} jam` : h > 0 ? `${h} jam ${m % 60} min` : `${m} min`;
    return neg ? `lewat ${text}` : text;
}

export const STATUS_VARIANT: Record<string, 'success' | 'warning' | 'danger' | 'info' | 'neutral'> = {
    baharu: 'info',
    disiasat: 'info',
    dalam_tindakan: 'warning',
    menunggu_pelapor: 'neutral',
    menunggu_kelulusan: 'neutral',
    selesai: 'success',
    disahkan: 'success',
    tutup: 'neutral',
    pendua: 'neutral',
    dirujuk_aduan: 'neutral',
};

export const KETERUKAN_VARIANT: Record<number, 'success' | 'warning' | 'danger' | 'info' | 'neutral'> = { 1: 'danger', 2: 'warning', 3: 'info', 4: 'neutral' };

export function slaVariant(status: string): 'success' | 'warning' | 'danger' | 'info' | 'neutral' {
    return status === 'dilepasi' || status === 'tidak_dipenuhi' ? 'danger' : status === 'hampir_tamat' ? 'warning' : status === 'dipenuhi' ? 'success' : status === 'dijeda' ? 'neutral' : 'info';
}

/* ---------- SRS-TKT-02: context capture in the browser ---------- */

const PERMINTAAN_TERKINI: string[] = [];
let penjejakDipasang = false;

/**
 * Keep the last API request ids (X-Request-Id, SRS-ADM-12) so a ticket can be
 * correlated with MOD-ADM's error log. Wraps window.fetch once; the wrapper
 * only reads a response header and never changes the request.
 */
export function pasangPenjejakPermintaan(): void {
    if (typeof window === 'undefined' || penjejakDipasang) return;
    penjejakDipasang = true;
    const asal = window.fetch.bind(window);
    window.fetch = async (input: RequestInfo | URL, init?: RequestInit) => {
        const res = await asal(input, init);
        try {
            const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;
            const id = res.headers.get('x-request-id');
            if (id && url.includes('/api/v1/') && !url.includes('/api/v1/tkt/')) {
                PERMINTAAN_TERKINI.unshift(id);
                PERMINTAAN_TERKINI.splice(10);
            }
        } catch {
            /* ignore */
        }
        return res;
    };
}

export interface KonteksSkrin {
    skrin: string;
    modul: string;
    kod_fungsi: string;
    url: string;
    rujukan_rekod: string | null;
    pelayar: string;
    versi_ui: string;
    saiz_skrin: string;
    zon_masa: string;
    id_permintaan: string[];
}

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export function tangkapKonteks(): KonteksSkrin {
    const path = window.location.pathname;
    const parts = path.split('/').filter(Boolean);
    const modul = parts[0] === 'admin' && parts[1] ? parts[1] : 'umum';
    const idIndex = parts.findIndex((p) => UUID.test(p) || /^\d+$/.test(p));
    const heading = document.querySelector('main h1, main h2')?.textContent?.trim();
    const marked = document.querySelector('[data-rujukan-rekod]')?.getAttribute('data-rujukan-rekod');
    return {
        skrin: [heading, document.title].filter(Boolean).join(' — ').slice(0, 200) || path,
        modul,
        kod_fungsi: parts.map((p) => (UUID.test(p) || /^\d+$/.test(p) ? ':id' : p)).join('.').slice(0, 120) || 'utama',
        url: window.location.href,
        rujukan_rekod: marked ?? (idIndex > 0 ? `${parts.slice(1, idIndex).join('/')}:${parts[idIndex]}` : null),
        pelayar: navigator.userAgent,
        versi_ui: process.env.NEXT_PUBLIC_APP_VERSION ?? '0.1.0',
        saiz_skrin: `${window.innerWidth}x${window.innerHeight}`,
        zon_masa: Intl.DateTimeFormat().resolvedOptions().timeZone,
        id_permintaan: [...PERMINTAAN_TERKINI],
    };
}

/**
 * SRS-TKT-03: consented screenshot with the browser's own screen-capture
 * prompt (getDisplayMedia — the user picks what is shared, nothing is sent
 * until they submit). Returns a PNG blob, or null if unsupported/cancelled.
 */
export async function tangkapSkrin(): Promise<Blob | null> {
    const md = navigator.mediaDevices as MediaDevices | undefined;
    if (!md?.getDisplayMedia) return null;
    let stream: MediaStream | null = null;
    try {
        stream = await md.getDisplayMedia({ video: true, audio: false, preferCurrentTab: true } as DisplayMediaStreamOptions);
        const video = document.createElement('video');
        video.srcObject = stream;
        video.muted = true;
        await video.play();
        await new Promise((r) => setTimeout(r, 250));
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d')?.drawImage(video, 0, 0);
        return await new Promise<Blob | null>((resolve) => canvas.toBlob((b) => resolve(b), 'image/png'));
    } catch {
        return null;
    } finally {
        stream?.getTracks().forEach((t) => t.stop());
    }
}

export function sesiBaharu(): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `s-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}
