import { useSyncExternalStore } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from './api';

/* MOD-PER client — /api/v1/per (Rekod Pemerolehan, SRS-PER-01..30). */

export type StatusKes =
    | 'permohonan_awam_menunggu_semakan'
    | 'dalam_proses'
    | 'menunggu_kelulusan'
    | 'diluluskan'
    | 'ditolak'
    | 'selesai'
    | 'dibatalkan';

export const STATUS_LABEL: Record<StatusKes, string> = {
    permohonan_awam_menunggu_semakan: 'Permohonan awam menunggu semakan',
    dalam_proses: 'Dalam proses',
    menunggu_kelulusan: 'Menunggu kelulusan',
    diluluskan: 'Diluluskan',
    ditolak: 'Ditolak',
    selesai: 'Selesai',
    dibatalkan: 'Dibatalkan',
};

export const STATUS_VARIANT: Record<StatusKes, 'success' | 'warning' | 'neutral' | 'danger' | 'info'> = {
    permohonan_awam_menunggu_semakan: 'warning',
    dalam_proses: 'info',
    menunggu_kelulusan: 'warning',
    diluluskan: 'success',
    ditolak: 'danger',
    selesai: 'success',
    dibatalkan: 'neutral',
};

export const BAHAGIAN_LABEL: Record<string, string> = {
    hartanah: 'Bahagian Pengurusan Hartanah',
    wakaf: 'Bahagian Wakaf',
    baitulmal: 'Bahagian Baitulmal',
};

export interface Medan {
    kod: string;
    label: string;
    jenis: 'teks' | 'teks_panjang' | 'tarikh' | 'nombor' | 'wang' | 'pilihan';
    pilihan: Array<{ nilai: string; label: string }> | null;
    peringkat: string | null;
    peribadi: boolean;
    mesti: string | null;
}

export interface Saluran {
    id: number;
    kod: string;
    nama: string;
    singkatan: string;
    rujukan_lampiran_h: string;
    ikon: string;
    warna: string;
    bahagian: string;
    nama_bahagian: string;
    tempoh_sasaran_hari: number;
    keterangan: string | null;
    status_aktif: boolean;
}

export interface SaluranPenuh extends Saluran {
    peringkat: Array<{ id: number; kod: string; nama: string; jenis: string; susunan: number; tempoh_sasaran_hari: number; aktif: boolean }>;
    senarai_semak: Array<{ id: number; jenis_dokumen: string; nama_dokumen: string; kategori_dms: string; wajib: boolean; dijana_sistem: boolean; peringkat_diperlukan: string }>;
    medan: Medan[];
    peranan_pihak: Array<{ kod: string; label: string }>;
}

export interface Rujukan {
    id: number;
    nama: string;
}

export interface KesRingkas {
    id: string;
    no_kes: string;
    tajuk: string;
    saluran: Saluran;
    lot: { id: string | null; berdaftar: boolean; no_lot: string | null; pautan: string | null };
    kod_daerah: string | null;
    nama_daerah: string | null;
    kod_mukim: string | null;
    nama_mukim: string | null;
    peringkat_semasa: { kod: string; nama: string; jenis: string; hari: number | null; tempoh_sasaran_hari: number; lewat: boolean; dieskalasi: boolean } | null;
    status: StatusKes;
    status_kelengkapan_dokumen: 'lengkap' | 'tidak_lengkap';
    kod_bahagian: string;
    tarikh_mula_kes: string;
    tarikh_selesai: string | null;
    nilai_pemerolehan: string | null;
    pegawai: Rujukan | null;
    sumber_rekod: string;
    klasifikasi: string;
}

export interface Peringkat {
    id: number;
    kod: string;
    nama: string;
    jenis: string;
    susunan: number;
    status: 'belum' | 'semasa' | 'selesai';
    tarikh_masuk: string | null;
    tarikh_keluar: string | null;
    tempoh_hari: number | null;
    tempoh_sasaran_hari: number;
    lewat: boolean;
    status_eskalasi: string;
    tarikh_eskalasi: string | null;
    pegawai: Rujukan | null;
    catatan: string | null;
}

export interface ItemSemak {
    id: number;
    jenis_dokumen: string;
    nama_dokumen: string;
    kategori_dms: string;
    wajib: boolean;
    dijana_sistem: boolean;
    peringkat_diperlukan: string;
    dipenuhi: boolean;
    dokumen: number[];
}

export interface DokumenKes {
    id: number;
    id_semak: number | null;
    jenis_dokumen: string;
    nama_dokumen: string;
    rujukan_dms: string | null;
    no_versi: number;
    tarikh_dokumen: string | null;
    tarikh_terima: string | null;
    status_dokumen: string;
    penerima: Rujukan | null;
    kod_peti_besi: string | null;
    no_fail: string | null;
    lokasi_rak: string | null;
    status_dokumen_asal: string | null;
    catatan: string | null;
    pergerakan: Array<{ jenis: 'keluar' | 'pulang'; tarikh: string; tujuan: string | null; pegawai: Rujukan | null }>;
}

export interface Pihak {
    id: number;
    peranan_pihak: string;
    label_peranan: string;
    nama: string;
    hubungan: string | null;
    jenis_dokumen_pengenalan: string | null;
    no_dokumen_pengenalan: string | null;
    bahagian_pusaka: string | null;
    status_pengesahan: 'belum_disahkan' | 'disahkan' | 'ditolak';
    sumber_pengesahan: string | null;
    catatan: string | null;
}

export interface Bayaran5a {
    jumlah_perlu_dibayar: string;
    jumlah_disahkan: string;
    jumlah_belum_disahkan: string;
    baki_tertunggak: string;
    lengkap: boolean;
    tarikh_akhir_bayaran: string | null;
    bayaran: Array<{
        id: number;
        jumlah_dibayar: string;
        tarikh_bayaran: string;
        mod_bayaran: string;
        no_resit: string;
        status_pengesahan: string;
        pengesah: Rujukan | null;
        tarikh_pengesahan: string | null;
        catatan: string | null;
        id_pegawai_rekod: number | null;
    }>;
}

export interface KesPenuh extends KesRingkas {
    saluran_penuh: SaluranPenuh;
    butiran: Record<string, string | null>;
    peribadi_dikaburkan: boolean;
    nilai_pasaran: string | null;
    sumber_dana: string | null;
    rujukan_penilaian_jpph: string | null;
    tarikh_pemerolehan: string | null;
    catatan: string | null;
    rujukan_luaran: string | null;
    pendaftar: Rujukan | null;
    peringkat: Peringkat[];
    senarai_semak: ItemSemak[];
    kekurangan_dokumen: string[];
    dokumen: DokumenKes[];
    pihak: Pihak[];
    kelulusan: Array<{ id: number; aras: string; tindakan: string; tarikh_masa: string; pelulus: Rujukan | null; jawatan: string | null; justifikasi: string | null }>;
    id_aliran_kerja: string | null;
    sekatan: string[];
    ringkasan_tempoh: { hari_berjalan: number; tempoh_keseluruhan_hari: number | null; tempoh_sasaran_hari: number };
    hak_milik: { id: string; hak_milik?: Record<string, string>; peti_besi?: Record<string, string> } | null;
    bayaran_5a?: Bayaran5a;
    boleh: { kemas_kini: boolean; dokumen: boolean; tutup: boolean; batal: boolean; bayaran: boolean; sahkan_bayaran: boolean; lihat_sulit: boolean };
}

export interface Halaman<T> {
    data: T[];
    meta: { total: number; current_page: number; last_page: number; from: number | null; to: number | null };
}

export interface PenapisKes {
    saluran?: string;
    peringkat?: string;
    daerah?: string;
    status?: string;
    bahagian?: string;
    q?: string;
    lot?: string;
    dokumen?: string;
    lewat?: string;
    page?: number;
}

export interface Laporan {
    jumlah: { kes: number; nilai: number; selesai: number; aktif: number; lewat: number; dokumen_kurang: number };
    mengikut_saluran: Array<{ kod: string; nama: string; singkatan: string; warna: string; bilangan: number; nilai: number; selesai: number; purata_hari_selesai: number | null; tempoh_sasaran_hari: number }>;
    mengikut_daerah: Array<{ kod: string | null; nama: string; bilangan: number; nilai: number; selesai: number }>;
    mengikut_status: Array<{ status: StatusKes; bilangan: number }>;
    mengikut_tahun: Array<{ tahun: number; bilangan: number; nilai: number; saluran: Record<string, number> }>;
    mengikut_sumber_dana: Array<{ sumber_dana: string; bilangan: number; nilai: number }>;
}

function qs(params: object): string {
    const s = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
        if (v !== undefined && v !== null && v !== '') s.set(k, String(v));
    }
    const out = s.toString();
    return out ? `?${out}` : '';
}

export const perApi = {
    saluran: () => api.get<{ data: SaluranPenuh[] }>('/per/saluran'),
    kemasKiniSaluran: (kod: string, body: unknown) => api.put<{ data: SaluranPenuh }>(`/per/saluran/${kod}`, body),
    senarai: (p: PenapisKes) => api.get<Halaman<KesRingkas>>(`/per/kes${qs(p)}`),
    kes: (id: string) => api.get<{ data: KesPenuh }>(`/per/kes/${id}`),
    cipta: (body: unknown) => api.post<{ data: KesPenuh; message: string }>('/per/kes', body),
    kemasKini: (id: string, body: unknown) => api.put<{ data: KesPenuh; message: string }>(`/per/kes/${id}`, body),
    maju: (id: string, body: { catatan?: string; id_pegawai_seterusnya?: number }) => api.post<{ data: KesPenuh; message: string }>(`/per/kes/${id}/maju`, body),
    pegawai: (id: string, idPegawai: number) => api.put<{ data: KesPenuh }>(`/per/kes/${id}/pegawai`, { id_pegawai: idPegawai }),
    pautkanLot: (id: string) => api.post<{ data: KesPenuh; message: string; dipautkan: boolean }>(`/per/kes/${id}/pautkan-lot`),
    semakanAwam: (id: string, terima: boolean, catatan?: string) => api.post<{ data: KesPenuh }>(`/per/kes/${id}/semakan-awam`, { terima, catatan }),
    batal: (id: string, sebab: string) => api.post<{ data: KesPenuh }>(`/per/kes/${id}/batal`, { sebab }),
    tutup: (id: string, body: unknown) => api.post<{ data: KesPenuh; message: string }>(`/per/kes/${id}/tutup`, body),
    garisMasa: (id: string) => api.get<{ data: Array<Record<string, string | number | boolean | null>> }>(`/per/kes/${id}/garis-masa`),
    tambahPihak: (id: string, body: unknown) => api.post<{ data: Pihak }>(`/per/kes/${id}/pihak`, body),
    kemasKiniPihak: (id: string, pihak: number, body: unknown) => api.put<{ data: Pihak }>(`/per/kes/${id}/pihak/${pihak}`, body),
    padamPihak: (id: string, pihak: number) => api.delete<{ message: string }>(`/per/kes/${id}/pihak/${pihak}`),
    pihakPenuh: (id: string, pihak: number) => api.get<{ data: Pihak }>(`/per/kes/${id}/pihak/${pihak}/penuh`),
    eFaraid: (id: string) => api.post<{ data: KesPenuh; message: string }>(`/per/kes/${id}/e-faraid`),
    muatNaik: (id: string, form: FormData) => api.postForm<{ data: DokumenKes; message: string }>(`/per/kes/${id}/dokumen`, form),
    pautDokumen: (id: string, body: { id_semak: number; rujukan_dms: string }) => api.post<{ data: DokumenKes }>(`/per/kes/${id}/dokumen`, body),
    kemasKiniDokumen: (id: string, dok: number, body: unknown) => api.put<{ data: DokumenKes }>(`/per/kes/${id}/dokumen/${dok}`, body),
    pergerakan: (id: string, dok: number, jenis: 'keluar' | 'pulang', tujuan?: string) => api.post<{ data: DokumenKes }>(`/per/kes/${id}/dokumen/${dok}/pergerakan`, { jenis, tujuan }),
    bayaran: (id: string, body: unknown) => api.post<{ data: Bayaran5a; message: string }>(`/per/kes/${id}/bayaran`, body),
    sahkanBayaran: (id: string, bayaran: number, terima: boolean, catatan?: string) =>
        api.post<{ data: Bayaran5a; message: string }>(`/per/kes/${id}/bayaran/${bayaran}/pengesahan`, { terima, catatan }),
    laporan: (p: Record<string, string | undefined>) => api.get<{ data: Laporan }>(`/per/laporan${qs(p)}`),
    laporanKhas: (jenis: string, p: Record<string, string | undefined>) => api.get<{ data: Array<Record<string, unknown>> }>(`/per/laporan/khas/${jenis}${qs(p)}`),
    eksportUrl: (p: Record<string, string | undefined>) => `/api/v1/per/laporan/eksport${qs(p)}`,
    cariPegawai: (q: string) => api.get<{ data: Array<{ id: number; nama: string; jawatan: string | null; bahagian: string | null }> }>(`/per/pegawai${qs({ q })}`),
};

export function ringgit(v: string | number | null | undefined): string {
    if (v === null || v === undefined || v === '') return '—';
    return new Intl.NumberFormat('ms-MY', { style: 'currency', currency: 'MYR', maximumFractionDigits: 2 }).format(Number(v));
}

export function tarikh(v: string | null | undefined): string {
    if (!v) return '—';
    return new Intl.DateTimeFormat('ms-MY', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(v));
}

export function tarikhMasa(v: string | null | undefined): string {
    if (!v) return '—';
    return new Intl.DateTimeFormat('ms-MY', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(v));
}

// ---------------------------------------------------------------- hooks

export function usePerCan() {
    const { data } = useQuery({ queryKey: ['me'], queryFn: () => api.get<{ data: { permissions?: string[] } }>('/user').then((r) => r.data), staleTime: 60_000 });
    // Permissions only exist client-side; until mounted answer "no" so the
    // server and first client render match (no hydration mismatch).
    const mounted = useSyncExternalStore(
        () => () => {},
        () => true,
        () => false,
    );
    const perms = new Set(mounted ? (data?.permissions ?? []) : []);
    return (...keys: string[]) => keys.some((k) => perms.has(k));
}

export function useSaluran() {
    return useQuery({ queryKey: ['per', 'saluran'], queryFn: () => perApi.saluran().then((r) => r.data), staleTime: 300_000 });
}

export function useDaerah() {
    return useQuery({
        queryKey: ['ref', 'daerah'],
        queryFn: () => api.get<{ data: Array<{ kod: string; nama: string }> }>('/ref/daerah').then((r) => r.data),
        staleTime: 3_600_000,
    });
}

export function useMukim(daerah?: string) {
    return useQuery({
        queryKey: ['ref', 'mukim', daerah ?? ''],
        queryFn: () => api.get<{ data: Array<{ kod: string; nama: string; daerah_kod?: string }> }>(`/ref/mukim${daerah ? `?daerah=${daerah}` : ''}`).then((r) => r.data),
        staleTime: 3_600_000,
    });
}

export const PERANAN_DOKUMEN_ASAL: Record<string, string> = {
    asal_dalam_simpanan: 'Asal dalam simpanan',
    salinan_diakui: 'Salinan diakui sah',
    salinan: 'Salinan',
    dikeluarkan: 'Dikeluarkan',
    tiada: 'Tiada',
};
