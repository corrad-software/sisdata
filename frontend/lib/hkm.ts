import { useSyncExternalStore } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api, ApiError } from './api';
import type { User } from './types';

/* MOD-HKM client — Hak Milik Tanah (/api/v1/hkm). Labels come from the API (Malay). */

export interface Kod {
    kod: string;
    nama: string;
}

export interface Bendera {
    kod: string;
    level: 'danger' | 'warning' | 'info';
    label: string;
}

export interface HakMilik {
    id: string;
    lot_id: string;
    no_lot: string | null;
    bahagian_pemilik: string | null;
    no_hak_milik: string;
    jenis_hak_milik: string;
    jenis_hak_milik_nama: string | null;
    sementara: boolean;
    kategori_tanah: string | null;
    kategori_tanah_nama: string | null;
    syarat_nyata: string | null;
    keluasan: string | null;
    unit_keluasan: string;
    kod_mukim: string;
    mukim: string | null;
    kod_daerah: string;
    daerah: string | null;
    pemilik_berdaftar: string;
    tarikh_pendaftaran: string | null;
    tarikh_tamat_tempoh: string | null;
    tempoh_pajakan_tahun: number | null;
    pejabat_pendaftaran: string | null;
    no_perserahan: string | null;
    tarikh_perserahan: string | null;
    status_semasa: string;
    status_semasa_nama: string | null;
    id_hak_milik_digantikan: string | null;
    disahkan: boolean;
    disahkan_pada: string | null;
    klasifikasi: string;
    versi_rekod: number;
    sintetik: boolean;
    bendera: Bendera[];
    dikemas_kini: string;
}

export interface Waris {
    id: number;
    nama: string;
    no_pengenalan: string | null;
    hubungan: string;
    hubungan_nama: string;
    pecahan_bahagian_pembilang: number | null;
    pecahan_bahagian_penyebut: number | null;
    pecahan: string | null;
    sumber_pengesahan: string | null;
    sumber_pengesahan_nama: string | null;
    no_kes_faraid: string | null;
    status_pengesahan: string;
    status_pengesahan_nama: string;
    catatan: string | null;
    sintetik: boolean;
}

export interface Sekatan {
    id: number;
    jenis_sekatan: string;
    jenis_sekatan_nama: string;
    no_rujukan_sekatan: string | null;
    pihak_terlibat: string | null;
    keterangan: string | null;
    tarikh_masuk: string | null;
    tarikh_lupus: string | null;
    status_sekatan: string;
    status_sekatan_nama: string;
    id_kes_mahkamah: string | null;
}

export interface Pengeluaran {
    id: number;
    daftar_peti_besi_id: number;
    tarikh_pengeluaran: string;
    pegawai_keluar: string | null;
    penerima_nama: string;
    penerima_jawatan: string | null;
    tujuan_pengeluaran: string;
    status_semasa_dikeluarkan: string;
    tarikh_jangka_pulang: string;
    tarikh_pulang_sebenar: string | null;
    pegawai_terima_pulang: string | null;
    keadaan_dokumen_pulang: string | null;
    keadaan_dokumen_pulang_nama: string | null;
    catatan_pulang: string | null;
    status_pengeluaran: 'dikeluarkan' | 'dipulangkan';
    lewat: boolean;
    hari_lewat: number;
    kod_peti_besi?: string;
    no_fail?: string | null;
    lokasi_rak?: string | null;
    hak_milik_id?: string;
    no_hak_milik?: string;
}

export interface DaftarPetiBesi {
    id: number;
    peti_besi_id: number;
    kod_peti_besi: string;
    lokasi_peti_besi: string;
    hak_milik_id: string;
    no_hak_milik: string | null;
    lot_id: string;
    jenis_dokumen_asal: string;
    jenis_dokumen_asal_nama: string;
    no_fail: string | null;
    lokasi_rak: string | null;
    no_kotak_pengikat: string | null;
    catatan_lokasi: string | null;
    status_dokumen_asal: string;
    status_dokumen_asal_nama: string;
    dalam_simpanan: boolean;
    tarikh_masuk_simpanan: string;
    pengeluaran_terbuka: Pengeluaran | null;
    sejarah_pengeluaran?: Pengeluaran[];
}

export interface PetiBesi {
    id: number;
    kod_peti_besi: string;
    nama: string;
    lokasi_bangunan: string;
    tingkat: string | null;
    bilik: string | null;
    lokasi: string;
    id_pegawai_penjaga: number | null;
    penjaga: string | null;
    kapasiti: number | null;
    status_peti_besi: string;
    tarikh_pemeriksaan_akhir: string | null;
    catatan: string | null;
    bilangan_dokumen: number | null;
}

export interface Urusan {
    id: number;
    hak_milik_id: string;
    jenis_urusan: string;
    jenis_urusan_nama: string;
    pejabat_pendaftaran: string;
    no_perserahan: string;
    tarikh_perserahan: string;
    status_urusan: string;
    status_urusan_nama: string;
    tarikh_selesai: string | null;
    catatan: string | null;
    tempoh_hari: number;
}

export interface HakMilikPenuh extends HakMilik {
    pautan_lot: string;
    digantikan: { id: string; no_hak_milik: string } | null;
    pengganti: Array<{ id: string; no_hak_milik: string }>;
    pemilikan_belum_selesai: boolean;
    waris: Waris[];
    sekatan: Sekatan[];
    dokumen_asal: DaftarPetiBesi[] | null;
    urusan: Urusan[];
    permohonan_hmk: Array<{ id: string; no_permohonan: string; tarikh_permohonan: string; peringkat_semasa_nama: string; status_kelulusan_nama: string }>;
    perubahan_menunggu: string | null;
}

export interface SejarahStatus {
    id: number;
    status_sebelum: string | null;
    status_sebelum_nama: string | null;
    status_selepas: string;
    status_selepas_nama: string | null;
    tarikh_berkuat_kuasa: string;
    sebab_perubahan: string;
    no_rujukan_dokumen: string | null;
    pegawai: string | null;
    pelulus: string | null;
    tarikh_masa_rekod: string;
}

export interface Kronologi {
    id: number;
    hak_milik_id: string;
    susunan: number;
    pemilik_sebelum: string | null;
    pemilik_selepas: string;
    tarikh_pertukaran: string | null;
    sebab_pertukaran: string;
    sumber: string;
    id_pemerolehan: string | null;
    pegawai: string | null;
    no_hak_milik?: string;
}

export interface Versi {
    versi: number;
    jenis_perubahan: string;
    jenis_perubahan_nama: string;
    data_sebelum: Record<string, unknown> | null;
    data_selepas: Record<string, unknown>;
    medan_berubah: string[];
    justifikasi: string | null;
    no_rujukan_dokumen: string | null;
    id_perubahan: string | null;
    pengguna: string | null;
    pelulus: string | null;
    tarikh: string;
}

export type JenisPerubahan = 'butiran' | 'status' | 'pemilik' | 'sekatan' | 'waris';

export interface Perubahan {
    id: string;
    hak_milik_id: string;
    no_hak_milik: string | null;
    jenis_perubahan: JenisPerubahan;
    jenis_perubahan_nama: string;
    data_cadangan: Record<string, unknown>;
    data_semasa: Record<string, unknown> | null;
    justifikasi: string;
    no_rujukan_dokumen: string | null;
    status: string;
    status_nama: string;
    id_instans_aliran: string | null;
    pemohon: string | null;
    cadangan_perubahan_id: string | null;
    tarikh_keputusan: string | null;
    catatan_keputusan: string | null;
    dicipta: string;
}

export interface Cadangan {
    id: string;
    hak_milik_id: string | null;
    no_hak_milik: string;
    kod_mukim: string | null;
    sumber: string;
    sumber_nama: string;
    rujukan_luaran: string;
    jenis_perubahan: JenisPerubahan;
    jenis_perubahan_nama: string;
    data_cadangan: Record<string, unknown>;
    keterangan: string | null;
    status: 'draf' | 'disahkan' | 'ditolak';
    status_nama: string;
    penyemak: string | null;
    tarikh_semakan: string | null;
    catatan_semakan: string | null;
    id_perubahan: string | null;
    diterima: string;
}

export interface SemakItem {
    kod: string;
    label: string;
    lengkap: boolean;
    id_dokumen?: string | null;
}

export interface Permohonan {
    id: string;
    no_permohonan: string;
    tarikh_permohonan: string;
    pejabat_tanah: string;
    hak_milik_sementara_id: string;
    no_hak_milik_sementara: string | null;
    hak_milik_baharu_id: string | null;
    no_hak_milik_baharu: string | null;
    lot_id: string;
    peringkat_semasa: string;
    peringkat_semasa_nama: string;
    status_kelulusan: string;
    status_kelulusan_nama: string;
    tarikh_terima_hak_milik_baharu: string | null;
    tempoh_proses_hari: number;
    senarai_semak_dokumen?: SemakItem[];
    semak_lengkap?: boolean;
    jumlah_bayaran?: string | null;
    tarikh_bayaran?: string | null;
    no_resit?: string | null;
    catatan?: string | null;
    sejarah_peringkat?: Array<{ peringkat: string; peringkat_nama: string; status: string; tarikh: string; catatan: string | null; pegawai: string | null }>;
}

export interface Rujukan {
    jenis_hak_milik: Array<Kod & { singkatan: string; sementara: boolean; hak_milik: boolean }>;
    kategori_tanah: Kod[];
    daerah: Kod[];
    mukim: Array<Kod & { daerah_kod: string }>;
    status_tanah: Array<Kod & { aktif: boolean }>;
    status_dokumen: Kod[];
    jenis_dokumen_asal: Kod[];
    keadaan_pulang: Kod[];
    jenis_sekatan: Kod[];
    status_sekatan: Kod[];
    hubungan: Kod[];
    sumber_pengesahan: Kod[];
    status_pengesahan_waris: Kod[];
    jenis_urusan: Kod[];
    status_urusan: Kod[];
    peringkat_hmk: Kod[];
    status_hmk: Kod[];
    jenis_perubahan: Kod[];
    status_perubahan: Kod[];
    sumber_cadangan: Kod[];
    status_peti_besi: Kod[];
    laporan: Kod[];
    peti_besi: Array<{ id: number; kod_peti_besi: string; nama: string }>;
}

export interface Halaman<T> {
    data: T[];
    meta: { total: number; current_page: number; last_page: number; per_page: number };
}

export function qs(params: Record<string, unknown>): string {
    const search = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
        if (v !== undefined && v !== null && v !== '' && v !== false) search.set(k, String(v));
    }
    const s = search.toString();
    return s ? `?${s}` : '';
}

export const hkmApi = {
    rujukan: () => api.get<{ data: Rujukan }>('/hkm/rujukan').then((r) => r.data),
    cari: (p: Record<string, unknown>) => api.get<Halaman<HakMilik>>(`/hkm/hak-milik${qs(p)}`),
    hakMilik: (id: string) => api.get<{ data: HakMilikPenuh }>(`/hkm/hak-milik/${id}`).then((r) => r.data),
    sejarah: (id: string) => api.get<{ data: { status: SejarahStatus[]; kronologi: Kronologi[]; versi: Versi[] } }>(`/hkm/hak-milik/${id}/sejarah`).then((r) => r.data),
    kronologiLot: (lotId: string) => api.get<{ data: Kronologi[] }>(`/hkm/lot/${lotId}/kronologi`).then((r) => r.data),
    daftar: (body: Record<string, unknown>) => api.post<{ data: HakMilik; message: string }>('/hkm/hak-milik', body),
    sahkan: (id: string) => api.post<{ data: HakMilik; message: string }>(`/hkm/hak-milik/${id}/sahkan`),
    mohonPerubahan: (id: string, body: { jenis_perubahan: JenisPerubahan; data: Record<string, unknown>; justifikasi: string; no_rujukan_dokumen?: string }) =>
        api.post<{ data: Perubahan; message: string }>(`/hkm/hak-milik/${id}/perubahan`, body),
    perubahan: (p: Record<string, unknown>) => api.get<Halaman<Perubahan>>(`/hkm/perubahan${qs(p)}`),
    satuPerubahan: (id: string) => api.get<{ data: Perubahan }>(`/hkm/perubahan/${id}`).then((r) => r.data),
    rekodUrusan: (id: string, body: Record<string, unknown>) => api.post<{ data: Urusan; message: string }>(`/hkm/hak-milik/${id}/urusan`, body),
    kemasKiniUrusan: (id: number, body: Record<string, unknown>) => api.put<{ data: Urusan; message: string }>(`/hkm/urusan/${id}`, body),

    petiBesi: () => api.get<{ data: PetiBesi[] }>('/hkm/peti-besi').then((r) => r.data),
    daftarPetiBesiBaharu: (body: Record<string, unknown>) => api.post<{ data: PetiBesi; message: string }>('/hkm/peti-besi', body),
    kemasKiniPetiBesi: (id: number, body: Record<string, unknown>) => api.put<{ data: PetiBesi; message: string }>(`/hkm/peti-besi/${id}`, body),
    entri: (p: Record<string, unknown>) => api.get<Halaman<DaftarPetiBesi>>(`/hkm/daftar-peti-besi${qs(p)}`),
    satuEntri: (id: number) => api.get<{ data: DaftarPetiBesi }>(`/hkm/daftar-peti-besi/${id}`).then((r) => r.data),
    simpan: (hakMilikId: string, body: Record<string, unknown>) => api.post<{ data: DaftarPetiBesi; message: string }>(`/hkm/hak-milik/${hakMilikId}/daftar-peti-besi`, body),
    kemasKiniEntri: (id: number, body: Record<string, unknown>) => api.put<{ data: DaftarPetiBesi; message: string }>(`/hkm/daftar-peti-besi/${id}`, body),
    keluar: (id: number, body: Record<string, unknown>) => api.post<{ data: Pengeluaran; message: string }>(`/hkm/daftar-peti-besi/${id}/keluar`, body),
    pulang: (id: number, body: Record<string, unknown>) => api.post<{ data: Pengeluaran; message: string }>(`/hkm/pengeluaran/${id}/pulang`, body),
    pengeluaran: (p: Record<string, unknown>) => api.get<Halaman<Pengeluaran>>(`/hkm/pengeluaran${qs(p)}`),

    permohonan: (p: Record<string, unknown>) => api.get<Halaman<Permohonan>>(`/hkm/permohonan-hmk${qs(p)}`),
    satuPermohonan: (id: string) => api.get<{ data: Permohonan }>(`/hkm/permohonan-hmk/${id}`).then((r) => r.data),
    mohonHmk: (hakMilikId: string, body: Record<string, unknown>) => api.post<{ data: Permohonan; message: string }>(`/hkm/hak-milik/${hakMilikId}/permohonan-hmk`, body),
    senaraiSemak: (id: string, senarai: SemakItem[]) => api.put<{ data: Permohonan }>(`/hkm/permohonan-hmk/${id}/senarai-semak`, { senarai }),
    peringkat: (id: string, body: Record<string, unknown>) => api.post<{ data: Permohonan }>(`/hkm/permohonan-hmk/${id}/peringkat`, body),
    bayaran: (id: string, body: Record<string, unknown>) => api.post<{ data: Permohonan }>(`/hkm/permohonan-hmk/${id}/bayaran`, body),
    terima: (id: string, body: Record<string, unknown>) => api.post<{ data: Permohonan }>(`/hkm/permohonan-hmk/${id}/terima`, body),

    cadangan: (p: Record<string, unknown>) => api.get<Halaman<Cadangan>>(`/hkm/cadangan${qs(p)}`),
    sahkanCadangan: (id: string, body: Record<string, unknown>) => api.post<{ data: Cadangan; message: string }>(`/hkm/cadangan/${id}/sahkan`, body),
    tolakCadangan: (id: string, catatan: string) => api.post<{ data: Cadangan; message: string }>(`/hkm/cadangan/${id}/tolak`, { catatan }),

    laporan: (jenis: string, p: Record<string, unknown> = {}) =>
        api.get<{ data: { jenis: string; tajuk: string; lajur: string[]; baris: Array<Array<string | number | null>> } }>(`/hkm/laporan/${jenis}${qs(p)}`).then((r) => r.data),
};

/** Download a CSV/Excel/PDF produced by the API (search export, inventory print, reports). */
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

// ---------------------------------------------------------------- hooks

export function useRujukan() {
    return useQuery({ queryKey: ['hkm', 'rujukan'], queryFn: hkmApi.rujukan, staleTime: 300_000 });
}

/** `can('a', 'b')` = holds any of them. UI hint only — the API enforces access. */
const noop = () => () => {};

export function useCan() {
    const { data } = useQuery({ queryKey: ['me'], queryFn: () => api.get<{ data: User }>('/user').then((r) => r.data), staleTime: 60_000 });
    // Server render and hydration see "no permissions" so the markup matches; real values apply right after.
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

export function keluasan(h: Pick<HakMilik, 'keluasan' | 'unit_keluasan'>): string {
    if (h.keluasan === null) return '—';
    const unit = { m2: 'm²', hektar: 'hektar', ekar: 'ekar', kaki_persegi: 'kaki²' }[h.unit_keluasan] ?? h.unit_keluasan;
    return `${Number(h.keluasan).toLocaleString('ms-MY', { maximumFractionDigits: 4 })} ${unit}`;
}

export const LABEL_MEDAN: Record<string, string> = {
    no_hak_milik: 'No. hak milik',
    jenis_hak_milik: 'Jenis hak milik',
    kategori_tanah: 'Kategori tanah',
    syarat_nyata: 'Syarat nyata',
    keluasan: 'Keluasan',
    unit_keluasan: 'Unit keluasan',
    kod_mukim: 'Mukim',
    kod_daerah: 'Daerah',
    pemilik_berdaftar: 'Pemilik berdaftar',
    tarikh_pendaftaran: 'Tarikh pendaftaran',
    tarikh_tamat_tempoh: 'Tarikh tamat tempoh',
    tempoh_pajakan_tahun: 'Tempoh pajakan (tahun)',
    pejabat_pendaftaran: 'Pejabat pendaftaran',
    no_perserahan: 'No. perserahan',
    tarikh_perserahan: 'Tarikh perserahan',
    status_semasa: 'Status semasa',
    id_hak_milik_digantikan: 'Hak milik digantikan',
    disahkan: 'Disahkan',
    sekatan: 'Sekatan kepentingan',
    waris: 'Waris / pemilik bersama',
    status_selepas: 'Status baharu',
    tarikh_berkuat_kuasa: 'Tarikh berkuat kuasa',
    pemilik_baharu: 'Pemilik baharu',
    tarikh_pertukaran: 'Tarikh pertukaran',
    sebab_pertukaran: 'Sebab pertukaran',
    tindakan: 'Tindakan',
};
