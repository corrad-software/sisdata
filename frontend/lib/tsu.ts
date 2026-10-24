import { useSyncExternalStore } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api, ApiError } from './api';
import type { User } from './types';

/* MOD-TSU client — Tukar Syarat, Pelupusan dan Ukur (/api/v1/tsu). Labels come from the API (Malay). */

export interface Kod {
    kod: string;
    nama: string;
}

export interface Meta {
    current_page: number;
    last_page: number;
    total: number;
}

export interface Rujukan {
    jenis_urusan: Kod[];
    pihak: Kod[];
    kategori_kos: Kod[];
    kategori_dokumen: Kod[];
    status_urusan: Kod[];
    status_peringkat: Kod[];
    status_bayaran: Kod[];
    status_ptd: Kod[];
    kaedah_pelupusan: Kod[];
    status_sebutharga: Kod[];
    sistem_koordinat: Kod[];
    kesan: Kod[];
}

export interface Urusan {
    id: string;
    no_rujukan_mais: string;
    no_rujukan_ptd: string | null;
    no_rujukan_etanah: string | null;
    jenis_urusan: string;
    jenis_nama: string;
    tajuk: string;
    kod_daerah: string;
    kod_mukim: string;
    bahagian_pemilik: string | null;
    klasifikasi: string;
    status: string;
    status_nama: string;
    peringkat_semasa: number | null;
    peringkat_semasa_nama: string | null;
    peringkat_lewat: boolean;
    status_ptd: string | null;
    status_ptd_nama: string | null;
    tarikh_mula: string;
    tarikh_sasaran_siap: string;
    tarikh_siap: string | null;
    lewat_sasaran: boolean;
    dikunci: boolean;
    sintetik: boolean;
    no_lot: string | null;
    pegawai: { id: number; nama: string | null } | null;
}

export interface Peringkat {
    id: number;
    no_urutan: number;
    kod: string;
    nama_peringkat: string;
    tindakan_diperlukan: string | null;
    pihak_bertanggungjawab: string;
    pihak_nama: string;
    tempoh_sasaran_hari: number;
    tarikh_mula: string | null;
    tarikh_sasaran: string | null;
    tarikh_selesai: string | null;
    status: string;
    status_nama: string;
    semasa: boolean;
    lewat: boolean;
    perlu_kelulusan: boolean;
    kesan: string | null;
    dokumen_wajib: Kod[];
    kategori_kos: Kod[];
    catatan: string | null;
    aras_eskalasi: number;
    id_aliran_kerja: string | null;
}

export interface Dokumen {
    id: number;
    peringkat_id: number | null;
    id_dokumen_dms: string;
    kategori_dokumen: string;
    kategori_nama: string;
    nama_fail: string | null;
    tarikh_dokumen: string | null;
    tarikh_luput: string | null;
    sah: boolean;
    tarikh_muat_naik: string;
    pemuat_naik: string | null;
}

export interface Keputusan {
    id: number;
    konteks: string;
    jenis_keputusan: string;
    jenis_nama: string;
    kod_peringkat_wfe: string | null;
    tarikh_keputusan: string;
    justifikasi: string | null;
    pelulus: string | null;
    indikator_mfa: boolean;
    peringkat_id: number | null;
}

export interface StatusPtd {
    id: number;
    sumber: 'api' | 'manual';
    status_ptd: string;
    status_nama: string;
    tarikh_status: string;
    catatan: string | null;
    rujukan_ptd: string | null;
    tarikh_susulan: string | null;
    pengguna: string | null;
    created_at: string;
}

export interface RingkasanKos {
    jumlah: string;
    dibayar: string;
    belum_dibayar: string;
    mengikut_kategori: Array<{ kod: string; nama: string; jumlah: string }>;
}

export interface UrusanButiran extends Urusan {
    keterangan: string | null;
    butiran: Record<string, string | number | null>;
    pejabat_tanah: string | null;
    kaedah_pelupusan: string | null;
    kaedah_pelupusan_nama: string | null;
    justifikasi: string | null;
    tarikh_tutup: string | null;
    nilai_penilaian: string | null;
    nilai_penilaian_tersembunyi: boolean;
    penilaian_sah: boolean;
    templat: { id: number; versi: number; nama: string };
    lot: Array<{ id: string; peranan_lot: string; peranan_nama: string; no_lot: string | null; kod_mukim: string | null; keluasan: string | null; unit_keluasan: string | null; kategori_guna_tanah: string | null; status_lot: string | null; pautan: string }>;
    peringkat: Peringkat[];
    dokumen_belum_lengkap: string[];
    dokumen: Dokumen[];
    keputusan: Keputusan[];
    kos: RingkasanKos | null;
    ptd: { liputan_api: boolean; sumber: string | null; tarikh_status: string | null; tarikh_susulan: string | null; sejarah: StatusPtd[] };
    sejarah_pecahan: Array<{ id_lot_asal: string; id_lot_baharu: string; jenis_perubahan: string; tarikh: string; urusan_id: string }>;
}

export interface Kos {
    id: number;
    peringkat_id: number | null;
    kategori_kos: string;
    kategori_nama: string;
    keterangan: string | null;
    amaun: string;
    status_bayaran: string;
    status_bayaran_nama: string;
    no_invois: string | null;
    tarikh_invois: string | null;
    tarikh_akhir_bayaran: string | null;
    tarikh_bayaran: string | null;
    no_resit: string | null;
    id_dokumen: string | null;
    rujukan_grp: string | null;
}

export interface Panel {
    id: number;
    nama_firma: string;
    no_lesen_jurukur: string;
    nama_jurukur_bertanggungjawab: string;
    maklumat_perhubungan: { emel?: string; telefon?: string; alamat?: string };
    pengalaman_tahun: number | null;
    status_panel: string;
    tarikh_tamat_lesen: string;
    layak: boolean;
    ada_akaun: boolean;
    sintetik: boolean;
}

export interface Tawaran {
    id: number;
    jurukur_id: number;
    nama_firma: string;
    no_lesen_jurukur: string;
    status: string;
    tarikh_terima: string | null;
    harga_tawaran: string | null;
    tempoh_siap_hari: number | null;
    pengalaman_tahun: number | null;
    markah: number | null;
    indikator_disyorkan: boolean;
    catatan_penilaian: string | null;
}

export interface SebutHarga {
    id: string;
    no_jemputan: string;
    tarikh_jemputan: string;
    tarikh_tutup: string;
    ditutup: boolean;
    skop_kerja: string;
    status: string;
    status_nama: string;
    id_dokumen_jemputan: string | null;
    id_tawaran_disyorkan: number | null;
    justifikasi_pemilihan: string | null;
    penilai: string | null;
    pelulus: string | null;
    tarikh_kelulusan: string | null;
    tawaran: Tawaran[];
}

export interface BarisPerbandingan extends Tawaran {
    markah_dikira: number | null;
    kedudukan: number | null;
}

export interface Kemajuan {
    id: number;
    milestone: string;
    peratus_siap: number;
    peratus_dijangka: number;
    lewat: boolean;
    tarikh_lapor: string;
    catatan: string | null;
    pelapor: string | null;
}

export interface Lantikan {
    id: string;
    urusan_id: string;
    jurukur: { id: number; nama_firma: string; no_lesen_jurukur: string };
    no_surat_setuju_terima: string;
    tarikh_surat_setuju_terima: string | null;
    nilai_kontrak: string;
    tarikh_mula: string;
    tempoh_siap_hari: number;
    tarikh_jangka_siap: string;
    skop_kerja: string;
    status: string;
    tarikh_siap: string | null;
    peratus_siap_semasa: number;
    peratus_dijangka: number;
    lewat: boolean;
    kemajuan: Kemajuan[];
    no_rujukan_urusan?: string;
    jenis_urusan?: string;
}

export interface HasilUkur {
    id: string;
    lantikan_id: string | null;
    lot_id: string | null;
    no_lot_cadangan: string | null;
    format_fail: string;
    sistem_koordinat: number;
    keluasan_dikira: string;
    keluasan_dokumen: string | null;
    sisihan_peratus: number | null;
    toleransi_peratus: number;
    dalam_toleransi: boolean | null;
    status_pengesahan: 'menunggu' | 'diterima' | 'ditolak';
    catatan_pengesahan: string | null;
    pemuat_naik: string | null;
    pegawai_sah: string | null;
    tarikh_sah: string | null;
    id_lot_dicipta: string | null;
    id_spatial_dicadang: number | null;
    id_dokumen_pelan_akui: string | null;
    created_at: string;
}

export type GeoFeature = { type: 'Feature'; geometry: { type: string; coordinates: unknown } | null; properties: Record<string, unknown> };

export interface Perbandingan {
    hasil: HasilUkur;
    baharu: GeoFeature;
    asal: GeoFeature | null;
    keluasan_dikira: string;
    keluasan_dokumen: string | null;
    keluasan_asal_dikira: number | null;
    sisihan_peratus: number | null;
    toleransi_peratus: number;
    dalam_toleransi: boolean | null;
    peratus_bertindih_asal: number | null;
}

export interface SkrinPemindahan {
    lot_asal: Array<{ id: string; no_lot: string | null; rekod: Array<{ jenis_rekod: string; jenis_label: string; id_rekod: string; label: string }> }>;
    lot_baharu: Array<{ id: string; no_lot: string | null }>;
    jenis: Kod[];
    pemetaan: Array<{ id: number; jenis_rekod: string; id_rekod: string; label_rekod: string | null; id_lot_asal: string; id_lot_baharu: string; status_pemindahan: string; id_pengesah_1: number | null; id_pengesah_2: number | null; tarikh_pemindahan: string | null; catatan: string | null }>;
}

export interface Templat {
    id: number;
    jenis_urusan: string;
    jenis_nama: string;
    versi: number;
    nama: string;
    keterangan: string | null;
    aliran_kelulusan: string | null;
    status_aktif: boolean;
    jumlah_hari: number;
    peringkat: Array<{ no_urutan: number; kod: string; nama_peringkat: string; tindakan_diperlukan: string | null; pihak_bertanggungjawab: string; tempoh_sasaran_hari: number; senarai_dokumen_wajib: string[]; senarai_kategori_kos: string[]; perlu_kelulusan: boolean; kesan: string | null }>;
}

export interface Ringkasan {
    aktif_mengikut_jenis: Array<Kod & { nilai: number }>;
    aktif_mengikut_daerah: Array<Kod & { nilai: number }>;
    aktif_mengikut_peringkat: Array<{ jenis_urusan: string; jenis_nama: string; peringkat: string; nilai: number }>;
    purata_tempoh: Array<Kod & { purata_hari: number | null; bilangan_selesai: number }>;
    kos_tahunan: Array<Record<string, string | number>>;
    tertunggak_pihak_luar: Array<{ id: string; no_rujukan_mais: string; jenis_nama: string; kod_daerah: string; peringkat: string; pihak: string; tarikh_sasaran: string; hari_lewat: number }>;
    prestasi_jurukur: Array<{ id: number; nama_firma: string; no_lesen_jurukur: string; bilangan: number; siap: number; purata_hari_sebenar: number | null; purata_hari_sasaran: number; lewat: number; jumlah_kontrak: string }>;
    sejarah_pecahan: Array<{ id: number; no_rujukan_mais: string; jenis_perubahan: string; lot_asal: string; lot_baharu: string; tarikh: string; id_lot_asal: string; id_lot_baharu: string }>;
    rekod_dipindah: Array<{ id: number; jenis_rekod: string; label_rekod: string | null; lot_asal: string; lot_baharu: string; tarikh: string | null }>;
}

export interface BarisKerja {
    id: string;
    no_rujukan_mais: string;
    jenis_urusan: string;
    jenis_nama: string;
    kod_daerah: string;
    kod_mukim: string;
    status: string;
    status_nama: string;
    peringkat_semasa: string | null;
    tarikh_mula: string;
    tarikh_siap: string | null;
    tempoh_hari: number;
    lewat_sasaran: boolean;
    jurukur: string | null;
    jumlah_kos: string;
    status_ptd: string | null;
}

export interface LotCarian {
    id: string;
    no_lot: string;
    kod_mukim: string;
    kod_daerah: string;
    keluasan: string | null;
    unit_keluasan: string;
    kategori_guna_tanah: string | null;
    bahagian_pemilik: string | null;
    status_lot: string;
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

const B = '/tsu';

export const tsuApi = {
    rujukan: () => api.get<{ data: Rujukan }>(`${B}/rujukan`).then((r) => r.data),
    cariLot: (q: string) => api.get<{ data: LotCarian[] }>(`${B}/cari-lot${qs({ q })}`).then((r) => r.data),
    senarai: (p: Params) => api.get<{ data: Urusan[]; meta: Meta }>(`${B}/urusan${qs(p)}`),
    butiran: (id: string) => api.get<{ data: UrusanButiran }>(`${B}/urusan/${id}`).then((r) => r.data),
    daftar: (b: unknown) => api.post<{ data: Urusan; message: string }>(`${B}/urusan`, b),
    kemaskini: (id: string, b: unknown) => api.put<{ data: Urusan; message: string }>(`${B}/urusan/${id}`, b),
    selesaiPeringkat: (id: string, catatan?: string) => api.post<{ data: { status: string; id_aliran_kerja: string | null }; message: string }>(`${B}/urusan/${id}/peringkat/selesai`, { catatan }),
    tutup: (id: string, catatan?: string) => api.post<{ message: string }>(`${B}/urusan/${id}/tutup`, { catatan }),
    batal: (id: string, sebab: string) => api.post<{ message: string }>(`${B}/urusan/${id}/batal`, { sebab }),
    muatNaik: (id: string, fd: FormData) => api.postForm<{ data: Dokumen; message: string }>(`${B}/urusan/${id}/dokumen`, fd),
    urlDokumen: (id: string, dok: number) => api.get<{ data: { url: string } }>(`${B}/urusan/${id}/dokumen/${dok}/url`).then((r) => r.data.url),
    kos: (id: string) => api.get<{ data: { item: Kos[]; ringkasan: RingkasanKos } }>(`${B}/urusan/${id}/kos`).then((r) => r.data),
    tambahKos: (id: string, b: unknown) => api.post<{ data: Kos; message: string }>(`${B}/urusan/${id}/kos`, b),
    kemaskiniKos: (kos: number, b: unknown) => api.put<{ data: Kos; message: string }>(`${B}/kos/${kos}`, b),
    ptdHantar: (id: string) => api.post<{ message: string }>(`${B}/urusan/${id}/ptd/hantar`),
    ptdSegerak: (id: string) => api.post<{ message: string }>(`${B}/urusan/${id}/ptd/segerak`),
    ptdManual: (id: string, b: unknown) => api.post<{ message: string }>(`${B}/urusan/${id}/ptd/manual`, b),
    ukur: (id: string) => api.get<{ data: { sebutharga: SebutHarga[]; lantikan: Lantikan[]; hasil: HasilUkur[] } }>(`${B}/urusan/${id}/ukur`).then((r) => r.data),
    panel: (layak?: boolean) => api.get<{ data: Panel[] }>(`${B}/panel${qs({ layak })}`).then((r) => r.data),
    simpanPanel: (b: unknown, id?: number) => (id ? api.put<{ data: Panel; message: string }>(`${B}/panel/${id}`, b) : api.post<{ data: Panel; message: string }>(`${B}/panel`, b)),
    jemput: (id: string, b: unknown) => api.post<{ data: SebutHarga; message: string }>(`${B}/urusan/${id}/sebutharga`, b),
    rekodTawaran: (sh: string, t: number, b: unknown) => api.post<{ message: string }>(`${B}/sebutharga/${sh}/tawaran/${t}`, b),
    perbandingan: (sh: string) => api.get<{ data: { sebutharga: SebutHarga; jadual: BarisPerbandingan[] } }>(`${B}/sebutharga/${sh}/perbandingan`).then((r) => r.data),
    nilai: (sh: string, b: unknown) => api.post<{ message: string }>(`${B}/sebutharga/${sh}/nilai`, b),
    lantik: (sh: string, b: unknown) => api.post<{ message: string }>(`${B}/sebutharga/${sh}/lantik`, b),
    jemputanPdf: (sh: string) => `/api/v1${B}/sebutharga/${sh}/jemputan.pdf`,
    lantikanSaya: () => api.get<{ data: Lantikan[] }>(`${B}/jurukur/lantikan`).then((r) => r.data),
    kemajuan: (lantikan: string, b: unknown) => api.post<{ message: string }>(`${B}/lantikan/${lantikan}/kemajuan`, b),
    importHasil: (id: string, fd: FormData) => api.postForm<{ data: HasilUkur[]; message: string }>(`${B}/urusan/${id}/hasil-ukur`, fd),
    banding: (h: string) => api.get<{ data: Perbandingan }>(`${B}/hasil-ukur/${h}/banding`).then((r) => r.data),
    sahkan: (h: string, b: unknown) => api.post<{ message: string }>(`${B}/hasil-ukur/${h}/sahkan`, b),
    pemindahan: (id: string) => api.get<{ data: SkrinPemindahan }>(`${B}/urusan/${id}/pemindahan`).then((r) => r.data),
    petakan: (id: string, pemetaan: unknown[]) => api.post<{ message: string }>(`${B}/urusan/${id}/pemindahan`, { pemetaan }),
    hantarPemindahan: (id: string) => api.post<{ message: string }>(`${B}/urusan/${id}/pemindahan/hantar`),
    templat: () => api.get<{ data: Templat[] }>(`${B}/templat`).then((r) => r.data),
    simpanTemplat: (jenis: string, b: unknown) => api.post<{ data: Templat; message: string }>(`${B}/templat/${jenis}`, b),
    ringkasan: (p: Params) => api.get<{ data: Ringkasan }>(`${B}/laporan/ringkasan${qs(p)}`).then((r) => r.data),
    kerja: (p: Params) => api.get<{ data: BarisKerja[] }>(`${B}/laporan/kerja${qs(p)}`).then((r) => r.data),
    eksportKerjaUrl: (p: Params) => `/api/v1${B}/laporan/kerja/eksport${qs(p)}`,
};

export function useRujukan() {
    return useQuery({ queryKey: ['tsu', 'rujukan'], queryFn: tsuApi.rujukan, staleTime: 300_000 });
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

/** RM with 2 decimals from a DECIMAL string — formatting only, no arithmetic. */
export function rm(value: string | null | undefined): string {
    if (value === null || value === undefined || value === '') return '—';
    const [int, dec = '00'] = value.split('.');
    return `RM ${int.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${dec.padEnd(2, '0').slice(0, 2)}`;
}

export function luas(m2: string | number | null | undefined): string {
    if (m2 === null || m2 === undefined || m2 === '') return '—';
    const n = typeof m2 === 'number' ? m2 : Number(m2);
    return `${n.toLocaleString('ms-MY', { maximumFractionDigits: 2 })} m²`;
}

export const STATUS_VARIANT: Record<string, 'success' | 'danger' | 'warning' | 'info' | 'neutral'> = {
    aktif: 'info',
    menunggu_kelulusan: 'warning',
    ditolak: 'danger',
    selesai: 'success',
    ditutup: 'neutral',
    dibatalkan: 'neutral',
    belum_mula: 'neutral',
    dilangkau: 'neutral',
    belum_bayar: 'warning',
    dalam_proses: 'info',
    dibayar: 'success',
    menunggu: 'warning',
    diterima: 'success',
    dibuka: 'info',
    dinilai: 'info',
    diluluskan: 'success',
    siap: 'success',
    draf: 'neutral',
    menunggu_pengesahan: 'warning',
    dilaksanakan: 'success',
    gagal: 'danger',
};
