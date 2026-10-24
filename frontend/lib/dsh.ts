/*
 * MOD-DSH frontend helpers: API client, types, hooks and the shared filter
 * state of the executive dashboard (/admin) and its sub-pages (/admin/dsh/*).
 * Every call is authorised by the API itself; hiding here is UI only.
 */
import { useQuery } from '@tanstack/react-query';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { useCallback, useMemo, useSyncExternalStore } from 'react';
import { api, ApiError } from '@/lib/api';
import type { User } from '@/lib/types';

// ---------------------------------------------------------------- types

export type Aras = 'eksekutif' | 'pengurusan' | 'operasi';
export type Lampu = 'hijau' | 'kuning' | 'merah';
export type Visual = 'auto' | 'stat' | 'bar' | 'pie' | 'line' | 'progress' | 'heatmap' | 'lampu' | 'peta' | 'senarai' | 'insight';

export const ARAS_LABEL: Record<Aras, string> = { eksekutif: 'Eksekutif', pengurusan: 'Pengurusan', operasi: 'Operasi' };
export const VARIAN_LABEL: Record<string, string> = { negeri: 'Seluruh negeri', daerah: 'Paparan daerah', bahagian: 'Paparan bahagian' };
export const DOMAIN_LABEL: Record<string, string> = { portfolio: 'Portfolio', hasil: 'Hasil', risiko: 'Risiko', operasi: 'Operasi', pematuhan: 'Pematuhan' };
export const LAMPU_LABEL: Record<Lampu, string> = { hijau: 'Hijau — mencapai sasaran', kuning: 'Kuning — perlu dipantau', merah: 'Merah — perlu tindakan' };
export const VISUAL_LABEL: Record<Visual, string> = {
    auto: 'Automatik', stat: 'Nilai', bar: 'Carta bar', pie: 'Carta pai', line: 'Carta garis', progress: 'Bar kemajuan',
    heatmap: 'Peta haba', lampu: 'Lampu isyarat', peta: 'Peta', senarai: 'Senarai', insight: 'Pemerhatian',
};

export interface Pecahan {
    label: string;
    nilai: number;
    kod?: string;
}

export interface TrendTitik {
    tempoh: string;
    nilai: number;
}

export interface Matriks {
    tajuk: string;
    lajur: string[];
    kod_lajur: string[];
    baris: { label: string; kod: string; sel: number[] }[];
}

export interface Ambang {
    arah: 'tinggi' | 'rendah';
    hijau: number;
    kuning: number;
    sumber: string;
}

export interface Kpi {
    key: string;
    label: string;
    domain: string;
    modul: string;
    unit: string;
    nilai: number | null;
    sasaran: number | null;
    trend: TrendTitik[];
    pecahan: Pecahan[];
    matriks: Matriks | null;
    pautan: string | null;
    data_terakhir: string | null;
    lampu: Lampu | null;
    ambang: Ambang | null;
    drill: boolean;
    dikira_pada: string;
    ralat: boolean;
}

export interface Widget {
    kod: string;
    tajuk: string;
    jenis_visual: Visual;
    sumber: string;
    saiz: number;
    susunan: number;
    aras: Aras[];
    kpi: string | null;
    posisi: number;
    tersembunyi: boolean;
}

export interface Penapis {
    tahun?: string;
    kod_daerah?: string;
    kod_mukim?: string;
    bahagian?: string;
    kategori?: string;
    status?: string;
    pegawai?: string;
    modul?: string;
}

export const PENAPIS_KEYS: (keyof Penapis)[] = ['tahun', 'kod_daerah', 'kod_mukim', 'bahagian', 'kategori', 'status', 'pegawai', 'modul'];

export interface KodNama {
    kod: string;
    nama: string;
}

export interface Dashboard {
    aras: Aras;
    aras_tersedia: Aras[];
    aras_lalai: Aras | null;
    varian: 'negeri' | 'daerah' | 'bahagian';
    skop: { daerah: KodNama[] | null; mukim: KodNama[] | null; bahagian: KodNama[] | null };
    penapis: Record<string, string | number>;
    terhad: boolean;
    widget: Widget[];
    kpi: Kpi[];
    dikira_pada: string;
    data_terakhir: string | null;
    ttl_saat: number;
    kebenaran: { eksport: boolean; ai: boolean; langganan: boolean; peta: boolean; tetapan: boolean };
}

export interface PilihanPenapis {
    daerah: KodNama[];
    mukim: (KodNama & { daerah_kod: string })[];
    kategori: KodNama[];
    bahagian: KodNama[];
    modul: KodNama[];
    tahun: number[];
    status: KodNama[];
}

export interface BarisSenarai {
    pautan: string | null;
    sel: Record<string, string | number | null>;
}

export interface Senarai {
    data: BarisSenarai[];
    lajur: { kod: string; label: string }[];
    meta: { kpi: string; label: string; pecahan: string | null; jumlah: number; page: number; per_page: number; last_page: number; dijana_pada: string };
}

export interface Insight {
    tahap: 'kritikal' | 'perhatian' | 'maklumat' | 'baik';
    teks: string;
    kpi?: string;
}

export interface JawapanAi {
    id: number;
    status: 'dijawab' | 'terhad' | 'tidak_difahami';
    enjin: string;
    pertanyaan: { niat: string; kpi: string[]; penapis: Record<string, string | number> };
    jawapan: {
        jenis: 'nilai' | 'carta' | 'jadual' | 'susun_atur' | 'teks';
        teks: string;
        kpi: Kpi[];
        visual: string;
        nota: string[];
        cadangan?: string[];
        jadual?: { lajur: { kod: string; label: string }[]; baris: BarisSenarai[]; jumlah: number };
        susun_atur?: { tindakan: string; widget: string[] };
    };
}

export interface TugasanInbox {
    id: number;
    tajuk: string;
    langkah: string;
    jenis: string;
    modul: string;
    status: string;
    tarikh_akhir: string | null;
    status_sla: 'lewat' | 'hampir' | 'dalam_tempoh' | 'tiada';
    diterima_pada: string;
    pautan: string;
    pautan_rekod: string | null;
}

export interface Inbox {
    tugasan: TugasanInbox[];
    notifikasi: { id: number; subjek: string; keutamaan: string; modul: string; pautan: string | null; tarikh: string }[];
    ringkasan: { tugasan: number; lewat: number; hampir: number; notifikasi: number };
}

export interface Draf {
    id: string;
    modul: string;
    jenis: string;
    tajuk: string;
    pautan: string | null;
    tarikh_simpan: string;
    muatan_json?: Record<string, unknown>;
}

export interface PaparanKegemaran {
    id: number;
    nama_paparan: string;
    aras: Aras | null;
    parameter_penapis: Record<string, string | number>;
    lalai: boolean;
}

export interface Langganan {
    id: number;
    aras: Aras;
    kekerapan: 'harian' | 'mingguan' | 'bulanan';
    penapis: Record<string, string | number>;
    aktif: boolean;
    terakhir_dihantar: string | null;
}

export interface Snapshot {
    id: number;
    aras: Aras;
    penapis: Record<string, string | number>;
    nilai?: Kpi[];
    sumber: string;
    dijana_pada: string;
}

export interface Bantuan {
    panduan: { kod: string; tajuk: string; laluan: string[]; langkah: string[]; berkaitan: boolean }[];
    faq: { soalan: string; jawapan: string }[];
    manual: string | null;
}

export interface PautanPortal {
    label: string;
    href: string;
    kumpulan: string;
}

export interface Tetapan {
    kpi: { key: string; label: string; domain: string; modul: string; kebenaran: string; ambang: Ambang | null }[];
    widget: { id: number; kod_widget: string; tajuk: string | null; jenis_visual: Visual; aras_dashboard: Aras[]; saiz: number; susunan: number; aktif: boolean }[];
    ambang: { id: number; kod_indikator: string; arah: 'tinggi' | 'rendah'; nilai_hijau: string; nilai_kuning: string }[];
    visual: Visual[];
    aras: Aras[];
}

// ---------------------------------------------------------------- api

function qs(params: Record<string, string | number | boolean | null | undefined>): string {
    const s = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
        if (v !== undefined && v !== null && v !== '' && v !== false) s.set(k, String(v));
    });
    const out = s.toString();
    return out ? `?${out}` : '';
}

export function penapisQs(p: Penapis, extra: Record<string, string | number | boolean | null | undefined> = {}): string {
    return qs({ ...p, ...extra });
}

export const dshApi = {
    dashboard: (aras: Aras | undefined, p: Penapis, segar = false) => api.get<{ data: Dashboard }>(`/dsh/dashboard${penapisQs(p, { aras, segar: segar ? 1 : undefined })}`).then((r) => r.data),
    kpi: (p: Penapis, kunci?: string[]) => api.get<{ data: Kpi[]; dikira_pada: string }>(`/dsh/kpi${penapisQs(p, { kunci: kunci?.join(',') })}`),
    senarai: (kpi: string, p: Penapis, pecahan: string | null, page: number) =>
        api.get<Senarai>(`/dsh/kpi/${encodeURIComponent(kpi)}/senarai${penapisQs(p, { pecahan, page })}`),
    pilihan: () => api.get<{ data: PilihanPenapis }>('/dsh/penapis/pilihan').then((r) => r.data),
    eksportUrl: (format: 'pdf' | 'xlsx', aras: Aras, p: Penapis) => `/api/v1/dsh/eksport${penapisQs(p, { format, aras })}`,
    susunAtur: (aras: Aras) => api.get<{ data: Widget[] }>(`/dsh/susun-atur?aras=${aras}`).then((r) => r.data),
    simpanSusunAtur: (aras: Aras, widget: { kod: string; saiz: number; tersembunyi: boolean; jenis_visual: Visual | null }[]) =>
        api.put<{ message: string; data: Widget[] }>('/dsh/susun-atur', { aras, widget }),
    setSemulaSusunAtur: (aras: Aras) => api.delete<{ message: string }>(`/dsh/susun-atur?aras=${aras}`),
    kegemaran: () => api.get<{ data: PaparanKegemaran[] }>('/dsh/kegemaran').then((r) => r.data),
    simpanKegemaran: (body: { nama_paparan: string; aras: Aras; parameter_penapis: Penapis; lalai?: boolean }) => api.post<{ message: string; data: PaparanKegemaran }>('/dsh/kegemaran', body),
    padamKegemaran: (id: number) => api.delete<{ message: string }>(`/dsh/kegemaran/${id}`),
    inbox: () => api.get<{ data: Inbox }>('/dsh/inbox').then((r) => r.data),
    draf: () => api.get<{ data: Draf[] }>('/dsh/draf').then((r) => r.data),
    padamDraf: (id: string) => api.delete<{ message: string }>(`/dsh/draf/${id}`),
    prompt: (prompt: string, aras: Aras, penapis: Penapis) => api.post<{ data: JawapanAi }>('/dsh/ai/prompt', { prompt, aras, penapis }).then((r) => r.data),
    insight: (aras: Aras, p: Penapis) => api.get<{ data: Insight[]; enjin: string }>(`/dsh/ai/insight${penapisQs(p, { aras })}`),
    langganan: () => api.get<{ data: Langganan[] }>('/dsh/langganan').then((r) => r.data),
    simpanLangganan: (body: { aras: Aras; kekerapan: string; penapis: Penapis; aktif: boolean }) => api.put<{ message: string; data: Langganan }>('/dsh/langganan', body),
    padamLangganan: (id: number) => api.delete<{ message: string }>(`/dsh/langganan/${id}`),
    snapshot: (id: number) => api.get<{ data: Snapshot }>(`/dsh/snapshot/${id}`).then((r) => r.data),
    snapshotSenarai: () => api.get<{ data: Snapshot[] }>('/dsh/snapshot').then((r) => r.data),
    janaSnapshot: (aras: Aras, penapis: Penapis) => api.post<{ message: string; data: Snapshot }>('/dsh/snapshot', { aras, penapis }),
    snapshotEksportUrl: (id: number, format: 'pdf' | 'xlsx') => `/api/v1/dsh/snapshot/${id}/eksport?format=${format}`,
    bantuan: (laluan?: string) => api.get<{ data: Bantuan }>(`/dsh/bantuan${qs({ laluan })}`).then((r) => r.data),
    portal: () => api.get<{ data: PautanPortal[] }>('/dsh/portal').then((r) => r.data),
    tetapan: () => api.get<{ data: Tetapan }>('/dsh/tetapan').then((r) => r.data),
    simpanWidget: (kod: string, body: { tajuk?: string | null; jenis_visual: Visual; aras_dashboard: Aras[]; saiz?: number; susunan?: number; aktif?: boolean }) =>
        api.put<{ message: string }>(`/dsh/tetapan/widget/${kod}`, body),
    padamWidget: (kod: string) => api.delete<{ message: string }>(`/dsh/tetapan/widget/${kod}`),
    simpanAmbang: (kod: string, body: { arah: 'tinggi' | 'rendah'; nilai_hijau: number; nilai_kuning: number }) => api.put<{ message: string }>(`/dsh/tetapan/ambang/${kod}`, body),
    padamAmbang: (kod: string) => api.delete<{ message: string }>(`/dsh/tetapan/ambang/${kod}`),
};

// ---------------------------------------------------------------- hooks

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

export function usePilihan() {
    return useQuery({ queryKey: ['dsh', 'pilihan'], queryFn: dshApi.pilihan, staleTime: 300_000 });
}

const STORAGE_KEY = 'dsh.penapis';

/**
 * The dashboard filters (SRS-DSH-08), kept in the URL so they survive moving
 * between widgets, drill-down lists and theme dashboards, and remembered in
 * localStorage for the next visit.
 */
export function usePenapis(): [Penapis, (next: Penapis) => void] {
    const params = useSearchParams();
    const router = useRouter();
    const pathname = usePathname();
    const fromUrl = useMemo(() => {
        const p: Penapis = {};
        PENAPIS_KEYS.forEach((k) => {
            const v = params.get(k);
            if (v) p[k] = v;
        });
        return p;
    }, [params]);

    const set = useCallback(
        (next: Penapis) => {
            const s = new URLSearchParams(params.toString());
            PENAPIS_KEYS.forEach((k) => {
                if (next[k]) s.set(k, String(next[k]));
                else s.delete(k);
            });
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
            } catch {
                /* storage unavailable — URL still carries the filters */
            }
            const q = s.toString();
            router.replace(q ? `${pathname}?${q}` : pathname, { scroll: false });
        },
        [params, pathname, router],
    );

    return [fromUrl, set];
}

/** Restore last-used filters when the URL has none (first visit of the session). */
export function storedPenapis(): Penapis | null {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw) as Penapis;
        return Object.values(parsed).some(Boolean) ? parsed : null;
    } catch {
        return null;
    }
}

/** Append the current filters to an internal link (drill-down keeps the filters). */
export function withPenapis(href: string, p: Penapis, extra: Record<string, string | undefined> = {}): string {
    const [path, query = ''] = href.split('?');
    const s = new URLSearchParams(query);
    PENAPIS_KEYS.forEach((k) => {
        if (p[k] && !s.has(k)) s.set(k, String(p[k]));
    });
    Object.entries(extra).forEach(([k, v]) => {
        if (v) s.set(k, v);
    });
    const q = s.toString();
    return q ? `${path}?${q}` : path;
}

// ---------------------------------------------------------------- format

const nf = new Intl.NumberFormat('ms-MY', { maximumFractionDigits: 1 });
const nfMoney = new Intl.NumberFormat('ms-MY', { maximumFractionDigits: 0 });

export function fmtNilai(v: number | null | undefined, unit: string): string {
    if (v === null || v === undefined) return '—';
    if (unit === 'RM') {
        const abs = Math.abs(v);
        if (abs >= 1_000_000) return `RM ${nf.format(v / 1_000_000)} juta`;
        return `RM ${nfMoney.format(v)}`;
    }
    if (unit === '%') return `${nf.format(v)}%`;
    return `${nf.format(v)}${unit ? ` ${unit}` : ''}`;
}

export function fmtNombor(v: number): string {
    return nf.format(v);
}

export function fmtMasa(iso: string | null | undefined): string {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleString('ms-MY', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

export function errorMessage(e: unknown): string {
    if (e instanceof ApiError) return e.message;
    return 'Ralat tidak dijangka. Sila cuba lagi.';
}

export const LAMPU_KELAS: Record<Lampu, string> = {
    hijau: 'bg-success-600',
    kuning: 'bg-warning-500',
    merah: 'bg-danger-600',
};

export const LAMPU_BADGE: Record<Lampu, 'success' | 'warning' | 'danger'> = { hijau: 'success', kuning: 'warning', merah: 'danger' };

/** Pick a visual for 'auto' from the data (SRS-DSH-09). */
export function visualAuto(k: Kpi): Visual {
    if (k.matriks && k.matriks.baris.length > 0) return 'heatmap';
    if (k.unit === '%' && k.sasaran !== null) return 'progress';
    if (k.trend.length >= 3) return 'line';
    if (k.pecahan.length >= 2 && k.pecahan.length <= 5 && k.domain !== 'portfolio') return 'pie';
    if (k.pecahan.length >= 1) return 'bar';
    return 'stat';
}
