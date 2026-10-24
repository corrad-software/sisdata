import { useQuery } from '@tanstack/react-query';
import { api, ApiError } from './api';
import type { User } from './types';

// ---------------------------------------------------------------- types

export interface Paged<T> {
    data: T[];
    meta: { current_page: number; last_page: number; total: number };
}

export interface WfeTask {
    id: number;
    jenis: 'kelulusan' | 'susulan';
    tajuk: string;
    status: 'menunggu' | 'ditangguh' | 'selesai' | 'dibatalkan';
    keputusan: string | null;
    instans: { id: string; tajuk: string; kod_aliran: string; status: string; pemohon: string; pautan: string | null };
    peringkat: { kod: string; nama: string; perlu_mfa: boolean; perlu_justifikasi: boolean; mod_kelulusan: string } | null;
    penerima: { kunci: string; label: string };
    bagi_pihak: string | null;
    tarikh_tugasan: string;
    tarikh_akhir_sla: string | null;
    baki_sla_minit: number | null;
    minit_sehari: number;
    ditangguh_hingga: string | null;
    aras_eskalasi: number;
    diselesaikan_oleh: string | null;
    diselesaikan_pada: string | null;
    boleh_bertindak: boolean;
}

export interface WfeHistory {
    id: number;
    peristiwa: string;
    peringkat: string | null;
    pengguna: string | null;
    bagi_pihak: string | null;
    peranan_semasa: string[] | null;
    justifikasi: string | null;
    alamat_ip: string | null;
    mfa_pada: string | null;
    butiran: Record<string, unknown> | null;
    tarikh_masa: string;
}

export interface WfeInstance {
    id: string;
    tajuk: string;
    kod_aliran: string;
    nama_aliran: string;
    no_versi: number;
    modul: string;
    jenis_subjek: string;
    id_rekod: string;
    pautan: string | null;
    kod_daerah: string | null;
    status: string;
    keadaan: string | null;
    peringkat_semasa: string | null;
    pemohon: { id: number; nama: string };
    tarikh_mula: string;
    tarikh_tamat: string | null;
    ulasan_keputusan: string | null;
    konteks?: Record<string, unknown>;
    sejarah?: WfeHistory[];
    tugasan?: Array<{
        id: number;
        jenis: string;
        tajuk: string | null;
        peringkat: string | null;
        penerima: string;
        status: string;
        keputusan: string | null;
        tarikh_akhir_sla: string | null;
        aras_eskalasi: number;
        diselesaikan_oleh: string | null;
    }>;
    boleh_tarik_balik?: boolean;
    boleh_hantar_semula?: boolean;
    versi_lain?: Array<{ id: number; no_versi: number }>;
    pindah_versi?: Array<{ id: number; status: string; sebab: string; id_definisi_baru: number; created_at: string }>;
}

export interface MfaState {
    sah: boolean;
    pada: string | null;
    tempoh_minit: number;
    sso: boolean;
    tamat_pada?: string | null;
}

export interface WfeApprover {
    jenis: string;
    nilai: string | null;
    label?: string | null;
}

export interface WfeEscalation {
    aras: number;
    tempoh_nilai: number;
    tempoh_unit: 'hari_bekerja' | 'jam';
    jenis_penerima: string;
    nilai_penerima: string | null;
}

export interface WfeStage {
    kod: string;
    nama: string;
    mod_kelulusan: 'semua' | 'mana_mana' | 'kuorum';
    kuorum: number | null;
    sla_nilai: number | null;
    sla_unit: 'hari_bekerja' | 'jam';
    perlu_mfa: boolean;
    perlu_justifikasi: boolean;
    pelulus: WfeApprover[];
    eskalasi: WfeEscalation[];
}

export interface WfeCondition {
    medan: string;
    operator: string;
    nilai: unknown;
}

export interface WfeTransition {
    dari: string;
    ke: string;
    keutamaan: number;
    logik: 'semua' | 'mana_mana';
    label: string | null;
    syarat: WfeCondition[];
}

export interface WfeFollowUp {
    bila: 'lulus' | 'tolak' | 'semua';
    tajuk: string;
    jenis_penerima: string;
    nilai_penerima: string | null;
    sla_nilai: number | null;
    sla_unit: 'hari_bekerja' | 'jam';
}

export interface WfeStructure {
    id?: number;
    kod_aliran: string;
    nama: string;
    keterangan: string | null;
    modul: string;
    jenis_transaksi: string;
    templat: boolean;
    no_versi?: number;
    status?: string;
    kaedah_cipta?: string;
    peringkat: WfeStage[];
    peralihan: WfeTransition[];
    susulan: WfeFollowUp[];
    susun_atur: { nodes?: Record<string, { x: number; y: number }> } | null;
}

export interface WfeDefinitionDetail extends WfeStructure {
    id: number;
    no_versi: number;
    status: string;
    kaedah_cipta: string;
    versi: Array<{ id: number; no_versi: number; status: string; tarikh_berkuat_kuasa: string | null; instans_berjalan: number }>;
    log: Array<{ tindakan: string; kaedah: string; arahan: string | null; pengguna: string | null; created_at: string }>;
    diminta_aktif_oleh: number | null;
    tarikh_berkuat_kuasa: string | null;
}

export interface WfeDefinitionRow {
    id: number;
    kod_aliran: string;
    nama: string;
    modul: string;
    jenis_transaksi: string;
    no_versi: number;
    status: string;
    templat: boolean;
    kaedah_cipta: string;
    bil_peringkat: number;
    pencipta: string | null;
    versi_aktif: number | null;
    updated_at: string;
}

export interface WfeReference {
    operator: Record<string, string>;
    jenis_pelulus: Record<string, string>;
    jenis_penerima_eskalasi: Record<string, string>;
    mod_kelulusan: Record<string, string>;
    unit_sla: Record<string, string>;
    kumpulan: Array<{ kod: string; nama: string }>;
    aliran: Array<{ kod_aliran: string; nama: string; modul: string; jenis_subjek: string; medan_konteks: string[] }>;
}

// ---------------------------------------------------------------- labels

export const STATUS_LABEL: Record<string, string> = {
    berjalan: 'Berjalan',
    lulus: 'Lulus',
    tolak: 'Ditolak',
    ditarik_balik: 'Ditarik balik',
    dibatalkan: 'Dibatalkan',
    menunggu: 'Menunggu',
    ditangguh: 'Ditangguh',
    selesai: 'Selesai',
    draf: 'Draf',
    menunggu_pengaktifan: 'Menunggu pengaktifan',
    aktif: 'Aktif',
    arkib: 'Arkib',
    menunggu_pemohon: 'Menunggu pembetulan pemohon',
};

export const STATUS_VARIANT: Record<string, 'success' | 'danger' | 'warning' | 'info' | 'neutral'> = {
    berjalan: 'info',
    lulus: 'success',
    tolak: 'danger',
    ditarik_balik: 'neutral',
    dibatalkan: 'neutral',
    menunggu: 'info',
    ditangguh: 'warning',
    selesai: 'success',
    draf: 'neutral',
    menunggu_pengaktifan: 'warning',
    aktif: 'success',
    arkib: 'neutral',
};

export const EVENT_LABEL: Record<string, string> = {
    mula: 'Permohonan dimulakan',
    masuk_peringkat: 'Masuk peringkat',
    tugasan: 'Tugasan diwujudkan',
    lulus: 'Diluluskan',
    tolak: 'Ditolak',
    kembali: 'Dikembalikan',
    tangguh: 'Ditangguhkan',
    sambung: 'Tangguhan tamat',
    eskalasi: 'Eskalasi',
    peringkat_selesai: 'Peringkat selesai',
    selesai: 'Aliran tamat',
    tarik_balik: 'Ditarik balik oleh pemohon',
    hantar_semula: 'Dihantar semula',
    susulan: 'Tugasan susulan diwujudkan',
    susulan_selesai: 'Tugasan susulan selesai',
    mohon_pindah_versi: 'Pindah versi dimohon',
    pindah_versi: 'Dipindah ke versi baharu',
    tolak_pindah_versi: 'Pindah versi ditolak',
};

export const ACTION_LABEL: Record<string, string> = {
    lulus: 'Lulus',
    tolak: 'Tolak',
    kembali: 'Kembalikan',
    tangguh: 'Tangguh',
};

export function fmtDateTime(iso: string | null | undefined): string {
    if (!iso) return '-';
    return new Date(iso).toLocaleString('ms-MY', {
        timeZone: 'Asia/Kuala_Lumpur',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function fmtDate(iso: string | null | undefined): string {
    if (!iso) return '-';
    return new Date(iso).toLocaleDateString('ms-MY', { timeZone: 'Asia/Kuala_Lumpur', day: '2-digit', month: 'short', year: 'numeric' });
}

/** "2 hari 3 jam" / "lewat 5 jam" — working time remaining. */
export function slaText(minutes: number | null, minutesPerDay = 540): { text: string; tone: 'success' | 'warning' | 'danger' | 'neutral' } {
    if (minutes === null) return { text: 'Tiada SLA', tone: 'neutral' };
    const abs = Math.abs(minutes);
    const days = Math.floor(abs / minutesPerDay);
    const hours = Math.floor((abs % minutesPerDay) / 60);
    const mins = abs % 60;
    const parts = [days ? `${days} hari` : '', hours ? `${hours} jam` : '', !days && !hours ? `${mins} minit` : ''].filter(Boolean).join(' ');
    if (minutes < 0) return { text: `Lewat ${parts}`, tone: 'danger' };
    return { text: `${parts} lagi`, tone: minutes <= 8 * 60 ? 'warning' : 'success' };
}

export function errorMessage(error: unknown, fallback = 'Permintaan gagal.'): string {
    if (error instanceof ApiError) {
        const first = error.errors ? Object.values(error.errors)[0]?.[0] : undefined;
        return first ?? error.message ?? fallback;
    }
    return fallback;
}

/** Full-page navigation to the SSO MAIS step-up (Laravel web route via the /auth rewrite). */
export function stepUpUrl(returnPath: string): string {
    return `/auth/wfe/stepup?kembali=${encodeURIComponent(returnPath)}`;
}

export const MFA_RESULT: Record<string, { type: 'success' | 'error'; text: string }> = {
    ok: { type: 'success', text: 'Pengesahan MFA berjaya. Anda boleh meneruskan tindakan.' },
    gagal: { type: 'error', text: 'Pengesahan MFA gagal atau dibatalkan.' },
    tiada_otp: { type: 'error', text: 'Log masuk semula tidak menunjukkan OTP. Sila konfigurasikan OTP pada akaun SSO MAIS anda.' },
    tiada_sso: {
        type: 'error',
        text: 'Akaun anda bukan akaun SSO MAIS. Tindakan pada peringkat kritikal memerlukan log masuk SSO MAIS dengan MFA.',
    },
};

// ---------------------------------------------------------------- hooks

export function useMe() {
    return useQuery({ queryKey: ['me'], queryFn: () => api.get<{ data: User }>('/user').then((r) => r.data), staleTime: 60_000 });
}

export function useCan() {
    const { data } = useMe();
    const perms = new Set(data?.permissions ?? []);
    return (...keys: string[]) => keys.some((k) => perms.has(k));
}

export function useReference() {
    return useQuery({
        queryKey: ['wfe', 'rujukan'],
        queryFn: () => api.get<{ data: WfeReference }>('/wfe/rujukan').then((r) => r.data),
        staleTime: 300_000,
    });
}

export function useMfa() {
    return useQuery({ queryKey: ['wfe', 'mfa'], queryFn: () => api.get<{ data: MfaState }>('/wfe/mfa').then((r) => r.data) });
}

export function emptyStage(index: number): WfeStage {
    return {
        kod: `peringkat_${index}`,
        nama: `Peringkat ${index}`,
        mod_kelulusan: 'mana_mana',
        kuorum: null,
        sla_nilai: 2,
        sla_unit: 'hari_bekerja',
        perlu_mfa: false,
        perlu_justifikasi: false,
        pelulus: [{ jenis: 'peranan', nilai: '', label: null }],
        eskalasi: [
            { aras: 1, tempoh_nilai: 0, tempoh_unit: 'hari_bekerja', jenis_penerima: 'penyelia', nilai_penerima: null },
            { aras: 2, tempoh_nilai: 1, tempoh_unit: 'hari_bekerja', jenis_penerima: 'peranan', nilai_penerima: 'admin' },
        ],
    };
}

/** Serial chain mula → stage… → tamat (the form editor's default routing). */
export function serialTransitions(stages: WfeStage[]): WfeTransition[] {
    const nodes = ['mula', ...stages.map((s) => s.kod), 'tamat'];
    return nodes.slice(0, -1).map((dari, i) => ({ dari, ke: nodes[i + 1], keutamaan: 0, logik: 'semua', label: null, syarat: [] }));
}
