/*
 * MOD-PWS frontend helpers (/admin/pws/*): governance of Portal Hartanah
 * Wakaf Selangor — API clients of the public gateway, portal content (with
 * local-AI drafts that need WFE approval), public statistics, monitoring and
 * portal analytics. The API authorises every call; hiding here is UI only.
 */
import { useQuery } from '@tanstack/react-query';
import { useCallback, useMemo, useSyncExternalStore } from 'react';
import { api, ApiError } from '@/lib/api';
import type { User } from '@/lib/types';

export const NAMA_RASMI = 'Portal Hartanah Wakaf Selangor';

export interface Klien {
    id: number;
    nama: string;
    id_klien: string;
    skop: string[];
    ip_dibenarkan: string[];
    had_kadar_minit: number;
    tarikh_luput: string | null;
    status: 'aktif' | 'dibatalkan';
    aktif: boolean;
    tarikh_putaran: string | null;
    rahsia_lama_sah_hingga: string | null;
    terakhir_digunakan: string | null;
    dicipta: string;
    client_secret?: string;
}

export type StatusKandungan = 'draf' | 'dalam_semakan' | 'diterbitkan' | 'ditolak' | 'ditarik';

export interface Kandungan {
    id: number;
    jenis: string;
    jenis_nama: string;
    slug: string;
    tajuk_bm: string;
    tajuk_bi: string | null;
    status: StatusKandungan;
    status_nama: string;
    dijana_ai: boolean;
    model_ai: string | null;
    tarikh_terbit: string | null;
    dikemas_kini: string;
    kandungan_bm?: string;
    kandungan_bi?: string | null;
    ringkasan?: string | null;
    kemajuan_peratus?: number | null;
    lokasi?: string | null;
    prompt_ai?: string | null;
    id_aliran?: string | null;
    catatan_semakan?: string | null;
}

export interface Statistik {
    id: number;
    tajuk: string;
    tempoh: string;
    data: StatistikData;
    status: StatusKandungan;
    tarikh_terbit: string | null;
    dicipta: string;
}

export interface StatistikData {
    tempoh: string;
    permohonan: Record<string, { jumlah: number | string; mengikut_status: Record<string, number | string> }>;
    bayaran_dalam_talian: { saluran: string; bilangan: number | string; amaun: string | null }[];
    premis_ditawarkan: number | null;
    agihan_manfaat_disahkan: { kategori_hartanah: string | null; status_perolehan: string | null; agihan: string }[];
    nota: string;
}

export interface Ringkasan {
    permohonan: { jenis: string; status_penghantaran: string; bilangan: number }[];
    bayaran: { saluran: string; status: string; bilangan: number; amaun: string }[];
    integrasi_24j: { arah: string; bilangan: number; gagal: number; purata_ms: number | null }[];
}

export interface PermohonanPortal {
    id: number;
    no_rujukan: string;
    jenis: 'wakaf' | 'sewaan';
    status: string;
    status_nama: string | null;
    status_penghantaran: 'menunggu' | 'dihantar' | 'diterima' | 'gagal';
    bilangan_cuba: number;
    ralat_terakhir: string | null;
    rujukan_sisdata: string | null;
    kod_daerah: string | null;
    dihantar: string;
    tarikh_status: string | null;
}

export interface LogIntegrasi {
    id: number;
    arah: 'masuk' | 'keluar';
    kaedah: string;
    endpoint: string;
    klien: string | null;
    nombor_rujukan: string | null;
    ringkasan_payload: Record<string, unknown> | null;
    kod_status: number | null;
    mesej_ralat: string | null;
    tempoh_ms: number | null;
    bilangan_cuba_semula: number;
    cap_masa: string;
}

export interface Analitik {
    dari: string;
    hingga: string;
    jumlah_lawatan: number;
    kadar_penyempurnaan_borang: number | null;
    halaman_popular: { halaman: string; lawatan: number }[];
    lawatan_harian: { tarikh: string; lawatan: number }[];
    permohonan: { jenis: string; status: string; bilangan: number }[];
}

interface Paged<T> {
    data: T[];
    meta: { total: number; current_page: number; last_page: number };
}

const qs = (p: Record<string, string | number | boolean | undefined | null>) => {
    const s = new URLSearchParams();
    Object.entries(p).forEach(([k, v]) => {
        if (v !== undefined && v !== null && v !== '' && v !== false) s.set(k, String(v));
    });
    const out = s.toString();
    return out ? `?${out}` : '';
};

export const pwsApi = {
    klien: () => api.get<{ data: Klien[]; skop: { kod: string; nama: string }[] }>('/pws/klien'),
    ciptaKlien: (body: { nama: string; skop: string[]; ip_dibenarkan: string[]; had_kadar_minit: number; tarikh_luput?: string | null }) =>
        api.post<{ data: Klien; message: string }>('/pws/klien', body),
    kemasKiniKlien: (id: number, body: Partial<Pick<Klien, 'nama' | 'skop' | 'ip_dibenarkan' | 'had_kadar_minit' | 'tarikh_luput'>>) =>
        api.put<{ data: Klien; message: string }>(`/pws/klien/${id}`, body),
    putarKlien: (id: number) => api.post<{ data: Klien; message: string }>(`/pws/klien/${id}/putar`),
    batalKlien: (id: number, sebab: string) => api.post<{ data: Klien; message: string }>(`/pws/klien/${id}/batal`, { sebab }),

    kandungan: (p: { jenis?: string; status?: string }) =>
        api.get<Paged<Kandungan> & { jenis: { kod: string; nama: string }[] }>(`/pws/kandungan${qs({ ...p, per_page: 50 })}`),
    satuKandungan: (id: number) => api.get<{ data: Kandungan }>(`/pws/kandungan/${id}`),
    ciptaKandungan: (body: Partial<Kandungan>) => api.post<{ data: Kandungan; message: string }>('/pws/kandungan', body),
    kemasKiniKandungan: (id: number, body: Partial<Kandungan>) => api.put<{ data: Kandungan; message: string }>(`/pws/kandungan/${id}`, body),
    janaAi: (body: { jenis: string; arahan: string; bahasa: 'ms' | 'en' }) => api.post<{ data: Kandungan; message: string }>('/pws/kandungan/jana-ai', body),
    statusAi: () => api.get<{ data: { model: string | null; tersedia: boolean; lokal: boolean } }>('/pws/status-ai'),
    hantarKandungan: (id: number) => api.post<{ data: Kandungan; message: string }>(`/pws/kandungan/${id}/hantar`),
    tarikKandungan: (id: number, sebab: string) => api.post<{ data: Kandungan; message: string }>(`/pws/kandungan/${id}/tarik`, { sebab }),

    statistik: () => api.get<{ data: Statistik[] }>('/pws/statistik'),
    pratonton: (tempoh: string) => api.get<{ data: StatistikData }>(`/pws/statistik/pratonton${qs({ tempoh })}`),
    ciptaStatistik: (tajuk: string, tempoh: string) => api.post<{ data: Statistik; message: string }>('/pws/statistik', { tajuk, tempoh }),
    hantarStatistik: (id: number) => api.post<{ data: Statistik; message: string }>(`/pws/statistik/${id}/hantar`),
    tarikStatistik: (id: number) => api.post<{ data: Statistik; message: string }>(`/pws/statistik/${id}/tarik`),

    ringkasan: () => api.get<{ data: Ringkasan }>('/pws/ringkasan'),
    permohonan: (p: { jenis?: string; status_penghantaran?: string; page?: number }) => api.get<Paged<PermohonanPortal>>(`/pws/permohonan${qs({ ...p, per_page: 25 })}`),
    cubaSemula: () => api.post<{ data: { wakaf: number; sewaan: number; gagal: number }; message: string }>('/pws/permohonan/cuba-semula'),
    log: (p: { arah?: string; gagal?: boolean; rujukan?: string; page?: number }) => api.get<Paged<LogIntegrasi>>(`/pws/log-integrasi${qs({ ...p, per_page: 50 })}`),
    bayaran: () =>
        api.get<{ data: { no_rujukan: string; no_kontrak: string; jumlah: string; saluran: string; status: string; disahkan_melalui: string | null; dimulakan: string | null; disahkan: string | null }[] }>(
            '/pws/bayaran',
        ),
    analitik: (dari: string, hingga: string) => api.get<{ data: Analitik }>(`/pws/analitik${qs({ dari, hingga })}`),
};

export const STATUS_KANDUNGAN_LABEL: Record<StatusKandungan, string> = {
    draf: 'Draf',
    dalam_semakan: 'Dalam semakan',
    diterbitkan: 'Diterbitkan',
    ditolak: 'Ditolak',
    ditarik: 'Ditarik balik',
};

export const STATUS_KANDUNGAN_VARIANT: Record<StatusKandungan, 'neutral' | 'warning' | 'success' | 'danger' | 'info'> = {
    draf: 'neutral',
    dalam_semakan: 'warning',
    diterbitkan: 'success',
    ditolak: 'danger',
    ditarik: 'info',
};

export const PENGHANTARAN_LABEL: Record<PermohonanPortal['status_penghantaran'], string> = {
    menunggu: 'Menunggu',
    dihantar: 'Dihantar',
    diterima: 'Diterima SISDATA',
    gagal: 'Gagal',
};

export const JENIS_PERMOHONAN_LABEL: Record<string, string> = { wakaf: 'Hasrat wakaf', sewaan: 'Sewaan / pajakan' };

export function tarikhMasa(iso: string | null | undefined): string {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleString('ms-MY', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

export function ralat(e: unknown): string {
    if (e instanceof ApiError) {
        if (e.status === 403) return e.message && e.message !== 'Permintaan gagal. Sila cuba lagi.' ? e.message : 'Anda tiada kebenaran untuk fungsi ini.';
        if (e.status === 404) return 'Rekod tidak ditemui.';
        const first = e.errors ? Object.values(e.errors)[0]?.[0] : undefined;
        return first ?? e.message;
    }
    return 'Ralat tidak dijangka. Sila cuba lagi.';
}

export function useMe() {
    return useQuery({ queryKey: ['me'], queryFn: () => api.get<{ data: User }>('/user').then((r) => r.data), staleTime: 60_000 });
}

const noopSubscribe = () => () => {};

/** UI hiding only — the API enforces every permission itself. */
export function useCan() {
    const { data } = useMe();
    const hydrated = useSyncExternalStore(noopSubscribe, () => true, () => false);
    const perms = useMemo(() => new Set(hydrated ? (data?.permissions ?? []) : []), [hydrated, data]);
    return useCallback((...keys: string[]) => keys.some((k) => perms.has(k)), [perms]);
}
