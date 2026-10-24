import { useSyncExternalStore } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api, ApiError } from './api';
import type { User } from './types';

/* MOD-PTN client — Pemantauan Tanah (/api/v1/ptn). Labels come from the API (Malay). */

export interface Kod {
    kod: string;
    nama: string;
}

export type KodWarna = 'hijau' | 'kuning' | 'merah';

export interface Warna {
    kod: KodWarna;
    label: string;
    hex: string;
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
    kategori_guna_tanah: Kod[];
    tahap_risiko: Kod[];
    status_jadual: Kod[];
    status_lawatan: Kod[];
    jenis_pemeriksaan: Kod[];
    status_item: Kod[];
    kategori_item: Kod[];
    keadaan_tapak: Kod[];
    kategori_isu: Kod[];
    tahap_keterukan: Kod[];
    status_isu: Kod[];
    status_tindakan: Kod[];
    jenis_penyelenggaraan: Kod[];
    medan_cadangan: Kod[];
    kategori_risiko: Kod[];
    warna: Warna[];
}

export interface Pegawai {
    id: number;
    nama: string;
}

export interface Jadual {
    id: string;
    lot_id: string;
    no_lot: string | null;
    kod_daerah: string;
    daerah: string | null;
    mukim: string | null;
    tahun: number;
    kekerapan: number;
    bil_dalam_tahun: number;
    tarikh_sasaran: string;
    objektif: string;
    jenis_pemeriksaan: string;
    jenis_nama: string;
    keutamaan_risiko: string;
    keutamaan_nama: string;
    skor_keutamaan: number;
    faktor_keutamaan: Record<string, unknown>;
    status_pelaksanaan: string;
    status_nama: string;
    pegawai: Pegawai | null;
    id_templat: number | null;
    dijana_automatik: boolean;
    versi: number;
    sintetik: boolean;
    kod_warna: KodWarna;
    warna: Warna;
}

export interface Lawatan {
    id: string;
    no_rujukan: string;
    id_jadual: string | null;
    lot_id: string;
    no_lot: string | null;
    kod_daerah: string;
    daerah: string | null;
    mukim: string | null;
    tarikh_lawatan: string;
    masa_mula: string | null;
    masa_tamat: string | null;
    pegawai: Pegawai | null;
    keadaan_tapak: string | null;
    keadaan_nama: string | null;
    ringkasan_penemuan: string | null;
    isu_pencerobohan: string | null;
    isu_penyelenggaraan: string | null;
    cadangan_tindakan: string | null;
    lat: number | null;
    lng: number | null;
    ketepatan_gps_m: number | null;
    jarak_dari_lot_m: number | null;
    dalam_lingkungan: boolean | null;
    status_lawatan: string;
    status_nama: string;
    status_pengesahan: string;
    dikunci: boolean;
    sebab_konflik: string | null;
    pengesah: Pegawai | null;
    tarikh_disahkan: string | null;
    sumber: string;
    disegerak_pada: string | null;
    sintetik: boolean;
}

export interface Gambar {
    id: number;
    id_dokumen: string | null;
    tarikh_masa: string | null;
    lat: number | null;
    lng: number | null;
    arah_kamera: number | null;
    ketepatan_m: number | null;
    sumber_gambar: 'kamera' | 'galeri';
    lingkungan: 'dalam' | 'luar' | 'tiada_koordinat';
    jarak_dari_lot_m: number | null;
    perlu_semakan: boolean;
    keputusan_semakan: 'diterima' | 'ditolak' | null;
    catatan_semakan: string | null;
    kod_item: string | null;
    jenis: string;
    kapsyen: string | null;
    nama_fail: string | null;
    imej_kecil: string | null;
}

export interface ItemTemplat {
    kod: string;
    kategori: string;
    label: string;
    kategori_isu?: string;
}

export interface Templat {
    id: number;
    kod: string;
    nama: string;
    versi: number;
    senarai_item: ItemTemplat[];
    kategori_lot?: string | null;
    jenis_pemeriksaan?: string;
    status_aktif?: boolean;
    bil_item?: number;
}

export interface ItemJawapan {
    kod_item: string;
    status_item: 'baik' | 'isu' | 'kritikal';
    catatan?: string | null;
    kategori_isu?: string | null;
    tahap_keterukan?: string | null;
    kategori_item?: string;
    label?: string;
}

export interface Isu {
    id: string;
    no_rujukan: string;
    id_lawatan: string | null;
    lot_id: string;
    no_lot: string | null;
    daerah: string | null;
    mukim: string | null;
    kod_item: string | null;
    kategori_isu: string;
    kategori_nama: string;
    tahap_keterukan: string;
    tahap_nama: string;
    keterangan: string;
    tarikh_dibuka: string;
    tarikh_ditutup: string | null;
    status_isu: string;
    status_nama: string;
    id_aduan_berkaitan: string | null;
    id_kes_berkaitan: string | null;
    sintetik: boolean;
    tindakan_ringkas?: Tindakan[];
}

export interface Tindakan {
    id: number;
    id_isu: string;
    langkah_tindakan: string;
    pegawai: Pegawai | null;
    tarikh_sasaran: string;
    tarikh_sasaran_asal: string;
    tarikh_siap: string | null;
    status_kemajuan: string;
    status_nama: string;
    peratus_kemajuan: number;
    kod_warna: KodWarna;
    warna: Warna;
    justifikasi_lanjutan: string | null;
    catatan_kemajuan: string | null;
    tarikh_disahkan: string | null;
    hari_berbaki: number;
    boleh_urus?: boolean;
}

export interface LaporanLawatan {
    id: string;
    jenis: 'asal' | 'tambahan';
    id_laporan_asal: string | null;
    status: string;
    status_kunci: boolean;
    tarikh_jana: string;
    tarikh_sah: string | null;
    pengesah: string | null;
    id_dokumen_dms: string | null;
    id_aliran: string | null;
    cincangan_kandungan: string;
    catatan_pembetulan: string | null;
}

export interface LotRingkas {
    id: string;
    no_lot: string;
    kod_mukim: string;
    kod_daerah: string;
    keluasan: string | null;
    unit_keluasan: string;
    kategori_guna_tanah: string | null;
    status_lot: string;
    klasifikasi: string;
}

export interface GeoGeom {
    type: string;
    coordinates: unknown;
}

export interface LawatanButiran extends Lawatan {
    lot: (LotRingkas & { pautan: string; geometri: GeoGeom | null }) | null;
    templat: { id: number; nama: string; versi: number | null; senarai_item: ItemTemplat[] } | null;
    item: ItemJawapan[];
    gambar: Gambar[];
    isu: Isu[];
    laporan: LaporanLawatan[];
    cadangan: Array<{ id: number; medan: string; nilai_semasa: string | null; nilai_dicadang: string; justifikasi: string; status: string; ulasan_keputusan: string | null }>;
    penyelenggaraan: Array<{ id: number; jenis: string; keterangan: string; keutamaan: string; status: string; tarikh_jadual: string | null }>;
    boleh: { semak: boolean; konflik: boolean; tambahan: boolean; ubah: boolean };
}

export interface Perbandingan {
    semasa: { id: string; no_rujukan: string; tarikh_lawatan: string; keadaan_tapak: string | null; pegawai: string | null } | null;
    sebelum: { id: string; no_rujukan: string; tarikh_lawatan: string; keadaan_tapak: string | null; pegawai: string | null } | null;
    item: Array<{ kod_item: string; label: string; sebelum: string | null; catatan_sebelum: string | null; semasa: string | null; catatan_semasa: string | null; perubahan: 'sama' | 'merosot' | 'bertambah_baik' | 'baharu' }>;
    sejarah: Array<{ id: string; no_rujukan: string; tarikh_lawatan: string }>;
    gambar_semasa: Gambar[];
    gambar_sebelum: Gambar[];
}

export interface Risiko {
    lot_id: string;
    skor_keseluruhan: number;
    kod_warna: KodWarna;
    warna: Warna;
    paksa_merah: boolean;
    tarikh_kiraan: string;
    kategori?: Array<{ kod: string; label: string; skor: number; kod_warna: KodWarna; pemberat: number; butiran: string | null; sumber: string }>;
}

export interface LotRisiko extends Risiko {
    no_lot: string;
    kategori_guna_tanah: string | null;
    daerah: string | null;
    mukim: string | null;
    lawatan_terakhir: string | null;
    isu_terbuka: number;
}

export interface LotButiran {
    lot: LotRingkas & { daerah: string | null; mukim: string | null; pautan: string };
    risiko: Risiko | null;
    jadual: Jadual[];
    lawatan: Lawatan[];
    isu: Isu[];
}

export interface Dashboard {
    taburan_warna: Record<KodWarna, number>;
    lot_risiko_tinggi: Array<{ lot_id: string; no_lot: string; daerah: string | null; mukim: string | null; skor: number; kod_warna: KodWarna; paksa_merah: boolean }>;
    isu_terbuka: number;
    isu_kritikal_terbuka: number;
    tindakan_tertunggak: number;
    tindakan_mengikut_warna: Record<KodWarna, number>;
    lawatan_tertunggak: number;
    peratus_ikut_jadual: number | null;
    trend: Array<{ tempoh: string; hijau: number; kuning: number; merah: number; purata: number }>;
    legenda: Warna[];
}

export interface Ambang {
    kategori_risiko: string;
    label: string;
    ambang_kuning?: number | null;
    ambang_merah?: number | null;
    tempoh_kelewatan_kuning?: number | null;
    tempoh_kelewatan_merah?: number | null;
    pemberat?: number | null;
    tarikh_berkuat_kuasa?: string;
}

export interface Konfigurasi {
    templat: Templat[];
    kekerapan: Array<{ id: number; kategori_lot: string; tahap_risiko: string; kekerapan_setahun: number; aktif: boolean }>;
    ambang: Ambang[];
    sejarah_ambang: Array<Ambang & { id: number }>;
    sumber_isyarat: Record<string, string>;
}

export interface Tugasan extends Jadual {
    lot: LotRingkas;
    geometri: GeoGeom | null;
    pusat: { lat: number; lng: number } | null;
    lawatan_terdahulu: { id: string; tarikh_lawatan: string; keadaan_tapak: string | null; ringkasan_penemuan: string | null; item: ItemJawapan[] } | null;
}

export interface BundleLapangan {
    dijana_pada: string;
    pengguna: Pegawai;
    tugasan: Tugasan[];
    templat: Templat[];
    toleransi_lingkungan_m: number;
    rujukan: {
        keadaan_tapak: Record<string, string>;
        kategori_isu: Record<string, string>;
        status_item: Record<string, string>;
        kategori_item: Record<string, string>;
        jenis_penyelenggaraan: Record<string, string>;
        medan_cadangan: Record<string, string>;
    };
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

const B = '/ptn';

export const ptnApi = {
    rujukan: () => api.get<{ data: Rujukan }>(`${B}/rujukan`).then((r) => r.data),
    cariLot: (q: string) => api.get<{ data: LotRingkas[] }>(`${B}/cari-lot${qs({ q })}`).then((r) => r.data),
    jadual: (p: Params) => api.get<Paged<Jadual>>(`${B}/jadual${qs(p)}`),
    ciptaJadual: (b: unknown) => api.post<{ data: Jadual }>(`${B}/jadual`, b),
    janaJadual: (b: { tahun: number; kod_daerah?: string; semula?: boolean }) => api.post<{ data: { dijana: number; lot: number; dilangkau: number }; message: string }>(`${B}/jadual/jana`, b),
    tugaskan: (id: string, id_pegawai: number) => api.post<{ data: Jadual }>(`${B}/jadual/${id}/tugaskan`, { id_pegawai }),
    batalJadual: (id: string, sebab: string) => api.post<{ data: Jadual }>(`${B}/jadual/${id}/batal`, { sebab }),
    agih: (b: Params) => api.post<{ data: { ditugaskan: number }; message: string }>(`${B}/jadual-agih`, b),
    pegawaiLayak: (kod_daerah: string) => api.get<{ data: Pegawai[] }>(`${B}/pegawai-layak${qs({ kod_daerah })}`).then((r) => r.data),
    lawatan: (p: Params) => api.get<Paged<Lawatan>>(`${B}/lawatan${qs(p)}`),
    lawatanButiran: (id: string) => api.get<{ data: LawatanButiran }>(`${B}/lawatan/${id}`).then((r) => r.data),
    perbandingan: (id: string, banding?: string) => api.get<{ data: Perbandingan }>(`${B}/lawatan/${id}/perbandingan${qs({ banding })}`).then((r) => r.data),
    urlGambar: (id: string, g: number) => api.get<{ data: { url: string | null; imej_kecil: string | null } }>(`${B}/lawatan/${id}/gambar/${g}/url`).then((r) => r.data),
    semakGambar: (id: string, g: number, keputusan: string, catatan?: string) => api.post<{ data: Gambar }>(`${B}/lawatan/${id}/gambar/${g}/semak`, { keputusan, catatan }),
    konflik: (id: string, keputusan: 'terima' | 'tolak', catatan?: string) => api.post<{ data: Lawatan }>(`${B}/lawatan/${id}/konflik`, { keputusan, catatan }),
    pdfUrl: (laporan: string) => `/api/v1${B}/laporan-lawatan/${laporan}/pdf`,
    tambahan: (laporan: string, catatan: string) => api.post<{ data: LaporanLawatan }>(`${B}/laporan-lawatan/${laporan}/tambahan`, { catatan }),
    mulaPengesahan: (laporan: string) => api.post<{ data: LaporanLawatan }>(`${B}/laporan-lawatan/${laporan}/mula-pengesahan`),
    isu: (p: Params) => api.get<Paged<Isu>>(`${B}/isu${qs(p)}`),
    isuButiran: (id: string) => api.get<{ data: Isu & { lot: (LotRingkas & { pautan: string }) | null; lawatan: { id: string; no_rujukan: string; tarikh_lawatan: string } | null; tindakan: Tindakan[] } }>(`${B}/isu/${id}`).then((r) => r.data),
    ciptaIsu: (b: unknown) => api.post<{ data: Isu }>(`${B}/isu`, b),
    tambahTindakan: (isu: string, b: unknown) => api.post<{ data: Tindakan }>(`${B}/isu/${isu}/tindakan`, b),
    kemaskiniTindakan: (id: number, b: unknown) => api.put<{ data: Tindakan }>(`${B}/tindakan/${id}`, b),
    selesaiTindakan: (id: number, catatan?: string) => api.post<{ data: Tindakan }>(`${B}/tindakan/${id}/selesai`, { catatan }),
    eskalasiAduan: (isu: string) => api.post<{ data: { id_aduan: string; pautan: string }; message: string }>(`${B}/isu/${isu}/eskalasi-aduan`),
    eskalasiKes: (isu: string) => api.post<{ data: { id_kes: string } }>(`${B}/isu/${isu}/eskalasi-kes`),
    cariPegawai: (q: string) => api.get<{ data: Pegawai[] }>(`${B}/pegawai${qs({ q })}`).then((r) => r.data),
    lot: (p: Params) => api.get<Paged<LotRisiko>>(`${B}/lot${qs(p)}`),
    lotButiran: (id: string) => api.get<{ data: LotButiran }>(`${B}/lot/${id}`).then((r) => r.data),
    dashboard: (p: Params) => api.get<{ data: Dashboard }>(`${B}/risiko/dashboard${qs(p)}`).then((r) => r.data),
    kiraRisiko: (lot_id?: string) => api.post<{ message: string }>(`${B}/risiko/kira`, lot_id ? { lot_id } : {}),
    laporan: <T>(kod: string, p: Params) => api.get<{ data: T }>(`${B}/laporan/${kod}${qs(p)}`).then((r) => r.data),
    eksportUrl: (kod: string, p: Params, format: 'csv' | 'pdf') => `/api/v1${B}/laporan/${kod}/eksport${qs({ ...p, format })}`,
    konfigurasi: () => api.get<{ data: Konfigurasi }>(`${B}/konfigurasi`).then((r) => r.data),
    simpanAmbang: (b: unknown) => api.post<{ message: string }>(`${B}/konfigurasi/ambang`, b),
    simpanKekerapan: (baris: unknown[]) => api.put<{ data: unknown }>(`${B}/konfigurasi/kekerapan`, { baris }),
    ciptaTemplat: (b: unknown) => api.post<{ data: Templat }>(`${B}/konfigurasi/templat`, b),
    versiTemplat: (id: number, b: unknown) => api.post<{ data: Templat }>(`${B}/konfigurasi/templat/${id}/versi`, b),
    statusTemplat: (id: number, status_aktif: boolean) => api.post<{ data: Templat }>(`${B}/konfigurasi/templat/${id}/status`, { status_aktif }),
    // Field app
    tugasan: () => api.get<{ data: BundleLapangan }>(`${B}/lapangan/tugasan`).then((r) => r.data),
    lawatanSaya: () => api.get<{ data: Array<Lawatan & { item: ItemJawapan[] }> }>(`${B}/lapangan/lawatan`).then((r) => r.data),
};

export function useRujukan() {
    return useQuery({ queryKey: ['ptn', 'rujukan'], queryFn: ptnApi.rujukan, staleTime: 300_000 });
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

/** SRS-PTN-16: one colour → badge variant + text label, used everywhere. */
export const WARNA_LABEL: Record<KodWarna, string> = {
    hijau: 'Hijau — normal',
    kuning: 'Kuning — perlu perhatian',
    merah: 'Merah — kritikal / lewat',
};

export const WARNA_HEX: Record<KodWarna, string> = { hijau: '#16A34A', kuning: '#EAB308', merah: '#DC2626' };

export const WARNA_VARIANT: Record<KodWarna, 'success' | 'warning' | 'danger'> = { hijau: 'success', kuning: 'warning', merah: 'danger' };

export const STATUS_ITEM_VARIANT: Record<string, 'success' | 'warning' | 'danger'> = { baik: 'success', isu: 'warning', kritikal: 'danger' };

export const TAHAP_VARIANT: Record<string, 'neutral' | 'info' | 'warning' | 'danger'> = { rendah: 'neutral', sederhana: 'info', tinggi: 'warning', kritikal: 'danger' };

export const LINGKUNGAN_LABEL: Record<string, string> = { dalam: 'Dalam sempadan', luar: 'Di luar sempadan', tiada_koordinat: 'Tiada koordinat' };

/** Download a binary endpoint (PDF/CSV) with the session cookie, or open the DMS short-lived URL it returns. */
export async function muatTurun(url: string, filename: string): Promise<void> {
    const res = await fetch(url, { credentials: 'include', headers: { Accept: 'application/json, application/pdf, text/csv' } });
    if (!res.ok) throw new Error(`Muat turun gagal (${res.status}).`);
    const type = res.headers.get('content-type') ?? '';
    if (type.includes('application/json')) {
        const body = (await res.json()) as { data?: { url?: string } };
        if (body.data?.url) {
            window.open(body.data.url, '_blank', 'noopener');
            return;
        }
        throw new Error('Tiada fail.');
    }
    const blob = await res.blob();
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 10_000);
}
