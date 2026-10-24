import { useSyncExternalStore } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api, ApiError } from './api';
import type { User } from './types';

/* MOD-AST client — Inventori Aset Fizikal (/api/v1/ast). Labels come from the API (Malay). */

export interface Kod {
    kod: string;
    nama: string;
}

export interface Meta {
    current_page: number;
    last_page: number;
    total: number;
}

export interface Paged<T> {
    data: T[];
    meta: Meta;
}

export interface Rujukan {
    daerah: Kod[];
    mukim: Kod[];
    kategori: Array<{ id: number; kod: string; nama: string; jenis_lalai: string }>;
    jenis_aset: Kod[];
    kondisi: Kod[];
    status_aset: Kod[];
    unit_ukuran: Kod[];
    jenis_penyelenggaraan: Kod[];
    jenis_kerja: Kod[];
    keutamaan: Kod[];
    status_kerja: Kod[];
    kaedah_penilaian: Kod[];
    kategori_dokumen: Kod[];
    kategori_gambar: Kod[];
    jenis_pelupusan: Kod[];
    kaedah_pelupusan: Kod[];
    senarai_semak: Kod[];
    templat_label: Kod[];
    tag_berstruktur: string[];
    laporan: Kod[];
}

export interface Aset {
    id: string;
    no_rujukan_aset: string;
    qr_ref: string;
    lot_id: string;
    no_lot: string | null;
    id_aset_induk: string | null;
    id_kategori: number;
    kategori: string | null;
    jenis_aset: string;
    jenis_nama: string;
    nama_aset: string;
    alamat: string | null;
    latitud: number | null;
    longitud: number | null;
    kod_daerah: string;
    daerah: string | null;
    kod_mukim: string;
    mukim: string | null;
    status_kondisi: Kondisi;
    kondisi_nama: string;
    warna_kondisi: string;
    nilai_semasa: string | null;
    tarikh_nilai_semasa: string | null;
    tarikh_pemeriksaan_terakhir: string | null;
    status_aset: string;
    status_nama: string;
    status_kelulusan: string;
    kelulusan_nama: string;
    status_arkib: boolean;
    id_premis_pjk: number | null;
    sintetik: boolean;
}

export type Kondisi = 'baik' | 'perlu_perhatian' | 'kritikal';

export interface SusutNilai {
    kos: string;
    nilai_sisa: string;
    susut_tahunan: string;
    umur_tahun: number;
    susut_terkumpul: string;
    nilai_buku: string;
}

export interface AsetPenuh extends Aset {
    keterangan: string | null;
    tahun_pembinaan: number | null;
    tahun_perolehan: number | null;
    tarikh_perolehan: string | null;
    saiz_keluasan: string | null;
    unit_ukuran: string;
    kos_perolehan: string | null;
    pegawai: { id: number; nama: string } | null;
    id_pegawai_bertanggungjawab: number | null;
    tarikh_arkib: string | null;
    sebab_arkib: string | null;
    induk: { id: string; no_rujukan_aset: string; nama_aset: string } | null;
    lot: { id: string; no_lot: string; kod_mukim: string; kod_daerah: string } | null;
    pautan_lot: string;
    susut_nilai: SusutNilai | null;
    hayat_guna_tahun: number | null;
    tempoh_pemeriksaan_bulan: number;
    pemeriksaan_lewat: boolean;
    tag: Array<{ nama: string; jenis: string }>;
    boleh: Record<'kemas_kini' | 'nilai' | 'pelupusan' | 'qr' | 'label' | 'pemeriksaan' | 'penyelenggaraan' | 'imbasan', boolean>;
    subaset: Aset[];
    premis_pjk: { id: number; kod_premis: string; nama_premis: string; status_premis: string; pautan: string } | null;
    qr: Array<{ qr_ref: string; pautan_awam: string; status_aktif: boolean; tarikh_jana: string; bilangan_cetakan: number; sebab_nyahaktif: string | null }>;
}

export interface Jadual {
    id: number;
    id_aset: string;
    jenis_penyelenggaraan: string;
    jenis_nama: string;
    kitaran_bulan: number;
    tarikh_jatuh_tempoh: string;
    tempoh_amaran_hari: number;
    status: string;
    tertunggak: boolean;
    hampir: boolean;
    pegawai: { id: number; nama: string } | null;
    dijana_automatik: boolean;
    tarikh_terakhir_dilaksana: string | null;
    tarikh_eskalasi: string | null;
    aset: { id: string; no_rujukan_aset: string; nama_aset: string } | null;
}

export interface Kerja {
    id: string;
    no_rujukan: string;
    id_aset: string;
    id_jadual: number | null;
    jenis_kerja: string;
    jenis_kerja_nama: string;
    jenis_penyelenggaraan: string;
    jenis_nama: string;
    keterangan: string;
    keutamaan: string;
    keutamaan_nama: string;
    tarikh_dijadual: string | null;
    tarikh_mula: string | null;
    tarikh_siap: string | null;
    kos_anggaran: string | null;
    kos: string | null;
    id_kontraktor: number | null;
    nama_syarikat_pelaksana: string | null;
    status: string;
    status_nama: string;
    id_aliran: string | null;
    sumber: string;
    sumber_nama: string;
    id_keperluan_ptn: number | null;
    pegawai: { id: number; nama: string } | null;
    catatan_penyelesaian: string | null;
    tertunggak: boolean;
    aset: { id: string; no_rujukan_aset: string; nama_aset: string } | null;
}

export interface KerjaButiran extends Kerja {
    kontraktor: Kontraktor | null;
    gambar: Array<{ id: number; kategori_gambar: string; kategori_nama: string; tarikh_gambar: string; keterangan: string | null }>;
    jadual: Jadual | null;
    boleh_urus: boolean;
    kontraktor_sahaja: boolean;
}

export interface Kontraktor {
    id: number;
    nama_syarikat: string;
    no_pendaftaran: string | null;
    no_telefon: string | null;
    emel: string | null;
    bidang: string[];
    tarikh_tamat_pendaftaran: string | null;
    id_pengguna: number | null;
    status_aktif: boolean;
    bilangan_kerja?: number;
    kerja_siap?: number;
    jumlah_kos?: string;
}

export interface Pemeriksaan {
    id: string;
    id_aset: string;
    tarikh_pemeriksaan: string;
    pegawai: string | null;
    kaedah: string;
    kaedah_nama: string;
    status_kondisi_dilapor: Kondisi;
    kondisi_nama: string;
    isu_dikenalpasti: string | null;
    tindakan_disyorkan: string | null;
    senarai_semak: Array<{ kod: string; status: string; catatan?: string }>;
    latitud: number | null;
    longitud: number | null;
    jarak_dari_aset_m: number | null;
    di_lokasi?: boolean | null;
    status_penyegerakan: string;
}

export interface Gambar {
    id: number | string;
    sumber: 'aset' | 'ptn';
    tarikh_gambar: string;
    latitud: number | null;
    longitud: number | null;
    perakam: string | null;
    kategori_gambar: string;
    kategori_nama: string;
    keterangan: string | null;
    imej_kecil: string | null;
    pautan: string | null;
}

export interface Laporan {
    tajuk: string;
    lajur: Array<{ kod: string; label: string }>;
    baris: Array<Record<string, string | number | null> & { _pautan?: string | null }>;
    ringkasan: Array<{ label: string; nilai: string | number }>;
    kumpulan?: Record<string, Array<{ kod: string; label: string; bilangan: number; nilai: string }>>;
}

export interface Ringkasan {
    jumlah_aset: number;
    kondisi: Array<{ kod: string; label: string; bilangan: number; nilai: string }>;
    kategori: Array<{ kod: string; label: string; bilangan: number; nilai: string }>;
    jumlah_nilai: string | null;
    tanpa_pemeriksaan: number;
    menunggu_kelulusan: number;
    jadual_tertunggak: number | null;
    kerja_terbuka: number | null;
}

export interface BundleLapangan {
    dijana_pada: string;
    aset: Array<{
        id: string;
        qr_ref: string;
        no_rujukan_aset: string;
        nama_aset: string;
        kategori: string;
        no_lot: string;
        daerah: string | null;
        mukim: string | null;
        status_kondisi: Kondisi;
        latitud: number | null;
        longitud: number | null;
        tarikh_pemeriksaan_terakhir: string | null;
        lewat: boolean;
    }>;
    senarai_semak: Kod[];
    kondisi: Kod[];
    jenis_penyelenggaraan: Kod[];
    toleransi_lokasi_m: number;
    boleh_cipta_kerja: boolean;
}

export interface ImbasAwam {
    paparan: 'minimum' | 'penuh' | 'tidak_aktif';
    no_rujukan_aset: string;
    nama_aset: string;
    kategori: string | null;
    jenis: string;
    daerah: string | null;
    mukim: string | null;
    pemilik: string;
    status: string;
    mesej: string;
    log_masuk: boolean;
    id?: string;
    pautan?: string;
    status_kondisi?: Kondisi;
    kondisi_nama?: string;
    tarikh_pemeriksaan_terakhir?: string | null;
    tindakan?: string[];
}

type Params = Record<string, string | number | boolean | null | undefined>;

export function qs(params: Params): string {
    const p = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
        if (v !== undefined && v !== null && v !== '' && v !== false) p.set(k, String(v === true ? 1 : v));
    });
    const s = p.toString();
    return s ? `?${s}` : '';
}

const B = '/ast';

export const astApi = {
    rujukan: () => api.get<{ data: Rujukan }>(`${B}/rujukan`).then((r) => r.data),
    ringkasan: (p: Params = {}) => api.get<{ data: Ringkasan }>(`${B}/ringkasan${qs(p)}`).then((r) => r.data),
    aset: (p: Params) => api.get<Paged<Aset>>(`${B}/aset${qs(p)}`),
    asetButiran: (id: string) => api.get<{ data: AsetPenuh }>(`${B}/aset/${id}`).then((r) => r.data),
    daftar: (b: unknown) => api.post<{ data: AsetPenuh; message: string }>(`${B}/aset`, b),
    kemaskini: (id: string, b: unknown) => api.put<{ data: AsetPenuh; message: string }>(`${B}/aset/${id}`, b),
    kondisi: (id: string, b: { status_kondisi: string; catatan?: string }) => api.post<{ message: string }>(`${B}/aset/${id}/kondisi`, b),
    nilai: (id: string, b: unknown) => api.post<{ message: string }>(`${B}/aset/${id}/nilai`, b),
    sejarahNilai: (id: string) => api.get<{ data: { sejarah: Array<Record<string, string | number | null>>; susut_nilai: Array<{ tahun: number; susut: string; susut_terkumpul: string; nilai_buku: string }> } }>(`${B}/aset/${id}/nilai`).then((r) => r.data),
    sejarah: (id: string) => api.get<{ data: { kondisi: Array<Record<string, string | number | null>>; audit: Array<Record<string, string | number | null>> } }>(`${B}/aset/${id}/sejarah`).then((r) => r.data),
    gambar: (id: string) => api.get<{ data: Gambar[] }>(`${B}/aset/${id}/gambar`).then((r) => r.data),
    urlGambar: (id: string, g: number | string) => api.get<{ data: { url: string | null } }>(`${B}/aset/${id}/gambar/${g}/url`).then((r) => r.data.url),
    simpanGambar: (id: string, fd: FormData) => api.postForm<{ message: string }>(`${B}/aset/${id}/gambar`, fd),
    dokumen: (id: string) => api.get<{ data: Array<{ id: number; kategori_nama: string; tajuk: string | null; tarikh_luput: string | null; luput: boolean; tarikh_muat_naik: string }> }>(`${B}/aset/${id}/dokumen`).then((r) => r.data),
    urlDokumen: (id: string, d: number) => api.get<{ data: { url: string | null } }>(`${B}/aset/${id}/dokumen/${d}/url`).then((r) => r.data.url),
    simpanDokumen: (id: string, fd: FormData) => api.postForm<{ message: string }>(`${B}/aset/${id}/dokumen`, fd),
    insurans: (id: string) => api.get<{ data: { insurans: Array<Record<string, string | number | boolean | null>>; jaminan: Array<Record<string, string | number | boolean | null>> } }>(`${B}/aset/${id}/insurans`).then((r) => r.data),
    simpanInsurans: (id: string, b: unknown) => api.post<{ message: string }>(`${B}/aset/${id}/insurans`, b),
    simpanJaminan: (id: string, b: unknown) => api.post<{ message: string }>(`${B}/aset/${id}/jaminan`, b),
    pemeriksaan: (id: string) => api.get<{ data: Pemeriksaan[] }>(`${B}/aset/${id}/pemeriksaan`).then((r) => r.data),
    rekodPemeriksaan: (id: string, b: unknown) => api.post<{ message: string }>(`${B}/aset/${id}/pemeriksaan`, b),
    penyelenggaraanAset: (id: string) => api.get<{ data: { jadual: Jadual[]; kerja: Kerja[] } }>(`${B}/aset/${id}/penyelenggaraan`).then((r) => r.data),
    simpanJadual: (id: string, b: unknown) => api.post<{ message: string }>(`${B}/aset/${id}/jadual`, b),
    kemaskiniJadual: (id: number, b: unknown) => api.put<{ message: string }>(`${B}/jadual/${id}`, b),
    pelupusan: (id: string) => api.get<{ data: Array<Record<string, string | number | null>> }>(`${B}/aset/${id}/pelupusan`).then((r) => r.data),
    mohonPelupusan: (id: string, b: unknown) => api.post<{ message: string }>(`${B}/aset/${id}/pelupusan`, b),
    imbasan: (id: string, page = 1) => api.get<Paged<{ id: number; qr_ref: string; cap_masa: string; pengguna: string | null; peranan: string | null; latitud: number | null; longitud: number | null; jenis_paparan: string }>>(`${B}/aset/${id}/imbasan${qs({ page })}`),
    janaSemulaQr: (id: string, sebab: string) => api.post<{ message: string }>(`${B}/aset/${id}/qr/jana-semula`, { sebab }),
    statusQr: (ref: string, status_aktif: boolean, sebab?: string) => api.post<{ message: string }>(`${B}/qr/${ref}/status`, { status_aktif, sebab }),
    qrSvgUrl: (id: string) => `/api/v1${B}/aset/${id}/qr.svg`,
    cariLot: (q: string) => api.get<{ data: Array<{ id: string; no_lot: string; kod_mukim: string; kod_daerah: string; daerah?: string; mukim?: string }> }>(`${B}/cari-lot${qs({ q })}`).then((r) => r.data),
    pegawai: (q: string) => api.get<{ data: Array<{ id: number; nama: string }> }>(`${B}/pegawai${qs({ q })}`).then((r) => r.data),
    premis: (lot_id: string, kecuali?: string) => api.get<{ data: Array<{ id: number; kod_premis: string; nama_premis: string; status_premis: string }> }>(`${B}/premis${qs({ lot_id, kecuali })}`).then((r) => r.data),
    tag: () => api.get<{ data: Array<{ nama: string; jenis: string; bilangan: number }> }>(`${B}/tag`).then((r) => r.data),
    jadual: (p: Params) => api.get<Paged<Jadual>>(`${B}/jadual${qs(p)}`),
    kalendar: (p: Params) => api.get<{ data: { bulan: string; acara: Array<{ tarikh: string; jenis: 'jadual' | 'kerja'; id: string; tajuk: string; status: string; pautan: string }> } }>(`${B}/kalendar${qs(p)}`).then((r) => r.data),
    kerja: (p: Params) => api.get<Paged<Kerja>>(`${B}/kerja${qs(p)}`),
    kerjaButiran: (id: string) => api.get<{ data: KerjaButiran }>(`${B}/kerja/${id}`).then((r) => r.data),
    ciptaKerja: (b: unknown) => api.post<{ data: Kerja; message: string }>(`${B}/kerja`, b),
    kemaskiniKerja: (id: string, b: unknown) => api.put<{ data: Kerja; message: string }>(`${B}/kerja/${id}`, b),
    gambarKerja: (id: string, fd: FormData) => api.postForm<{ message: string }>(`${B}/kerja/${id}/gambar`, fd),
    keperluanPtn: () => api.get<{ data: Array<{ id: number; lot_id: string; jenis: string; jenis_nama: string; keterangan: string; keutamaan: string; tarikh_dikenal_pasti: string }> }>(`${B}/ptn/keperluan`).then((r) => r.data),
    tarikPtn: () => api.post<{ message: string }>(`${B}/ptn/tarik`),
    pautPtn: (id: number, id_aset: string) => api.post<{ message: string }>(`${B}/ptn/keperluan/${id}/paut`, { id_aset }),
    kontraktor: (p: Params = {}) => api.get<{ data: Kontraktor[] }>(`${B}/kontraktor${qs(p)}`).then((r) => r.data),
    simpanKontraktor: (b: Partial<Kontraktor>, id?: number) => (id ? api.put<{ message: string }>(`${B}/kontraktor/${id}`, b) : api.post<{ message: string }>(`${B}/kontraktor`, b)),
    laporan: (kod: string, p: Params) => api.get<{ data: Laporan }>(`${B}/laporan/${kod}${qs(p)}`).then((r) => r.data),
    eksportUrl: (kod: string, p: Params) => `/api/v1${B}/laporan/${kod}/eksport${qs(p)}`,
    kategori: () => api.get<{ data: Array<Record<string, string | number | boolean | string[] | null>> }>(`${B}/kategori`).then((r) => r.data),
    simpanKategori: (b: unknown, id?: number) => (id ? api.put<{ message: string }>(`${B}/kategori/${id}`, b) : api.post<{ message: string }>(`${B}/kategori`, b)),
    bundle: (p: Params = {}) => api.get<{ data: BundleLapangan }>(`${B}/lapangan/bundle${qs(p)}`).then((r) => r.data),
    pemeriksaanSaya: () => api.get<{ data: Array<Pemeriksaan & { nama_aset: string | null; no_rujukan_aset: string | null }> }>(`${B}/lapangan/pemeriksaan`).then((r) => r.data),
    imbasAwam: (ref: string, b: { latitud?: number; longitud?: number; ketepatan_m?: number }) => api.post<{ data: ImbasAwam }>(`${B}/awam/qr/${ref}/imbas`, b).then((r) => r.data),
};

/** SRS-AST-07: POST the label sheet and open the returned PDF. */
export async function cetakLabel(ids: string[], templat: string): Promise<void> {
    const xsrf = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    if (!xsrf) await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
    const token = decodeURIComponent((document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/) ?? [])[1] ?? '');
    const res = await fetch(`/api/v1${B}/label`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', Accept: 'application/pdf, application/json', 'X-XSRF-TOKEN': token },
        body: JSON.stringify({ id_aset: ids, templat }),
    });
    if (!res.ok) throw new Error(`Cetakan label gagal (${res.status}).`);
    const url = URL.createObjectURL(await res.blob());
    window.open(url, '_blank', 'noopener');
    setTimeout(() => URL.revokeObjectURL(url), 60_000);
}

export function useRujukan() {
    return useQuery({ queryKey: ['ast', 'rujukan'], queryFn: astApi.rujukan, staleTime: 300_000 });
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

export function masa(value: string | null | undefined): string {
    if (!value) return '—';
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? value : d.toLocaleString('ms-MY', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

/** Money from a DECIMAL string — formatted, never re-computed in floating point. */
export function rm(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') return '—';
    const s = String(value);
    const [int, dec = '00'] = s.replace('-', '').split('.');
    const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return `${s.startsWith('-') ? '-' : ''}RM ${grouped}.${dec.padEnd(2, '0').slice(0, 2)}`;
}

/** SRS-AST-05 condition: colour always with a text label (never colour alone). */
export const KONDISI_VARIANT: Record<string, 'success' | 'warning' | 'danger'> = { baik: 'success', perlu_perhatian: 'warning', kritikal: 'danger' };

export const KONDISI_LABEL: Record<string, string> = { baik: 'Baik', perlu_perhatian: 'Perlu perhatian', kritikal: 'Kritikal' };

export const STATUS_KERJA_VARIANT: Record<string, 'success' | 'warning' | 'danger' | 'info' | 'neutral'> = {
    dirancang: 'neutral', menunggu_kelulusan: 'warning', diluluskan: 'info', dalam_pelaksanaan: 'info', selesai: 'success', ditolak: 'danger', dibatalkan: 'neutral',
};
