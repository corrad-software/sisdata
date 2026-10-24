import { api } from './api';
import type { AccountStatus } from './types';

/* MOD-RBC (Modul 18) API helpers — /api/v1/rbc/* */

export type ScopeMap = Record<string, string[]>;

export interface Paginated<T> {
    data: T[];
    meta: { current_page: number; last_page: number; total: number; per_page: number; belum_disemak?: number };
}

export interface Role {
    id: number;
    kod: string;
    nama: string;
    kategori_rfp: string | null;
    keterangan: string | null;
    status: 'aktif' | 'tidak_aktif';
    boleh_padam: boolean;
    bilangan_pengguna: number;
    bilangan_kebenaran: number;
    kebenaran?: string[];
    skop?: ScopeMap;
}

export interface Catalogue {
    modul: Array<{ modul: string; label: string; kebenaran: Array<{ kunci: string; label: string }> }>;
    tindakan: string[];
    jenis_skop: Record<string, string>;
}

export interface UserAccess {
    id: number;
    name: string;
    email: string;
    status_akaun: AccountStatus;
    jenis_pengesahan: 'dalaman' | 'sso';
    sso: boolean;
    no_pekerja: string | null;
    bahagian: string | null;
    unit: string | null;
    jawatan: string | null;
    tarikh_mula_capaian: string | null;
    tarikh_tamat_capaian: string | null;
    tarikh_log_masuk_akhir: string | null;
    dikunci_hingga: string | null;
    wajib_tukar_kata_laluan: boolean;
    peranan: Array<{ id: number; kod: string; nama: string; status: string; sumber: 'manual' | 'sso' }>;
    skop: Array<{ jenis_skop: string; nilai_skop: string; sumber: 'manual' | 'sso' }>;
    kebenaran: string[];
    skop_berkesan: Record<string, string[] | null>;
    log_status: Array<{ status_lama: AccountStatus; status_baru: AccountStatus; sebab: string | null; pelaku: string | null; created_at: string }>;
    sesi_aktif: Session[];
    log_masuk_terkini: LoginLog[];
}

export interface Session {
    id: string;
    pengguna: { id: number; name: string; email: string } | null;
    kaedah_log_masuk: string | null;
    alamat_ip: string | null;
    pelayar: string;
    platform: string;
    peranti: string;
    tarikh_mula: string;
    tarikh_aktiviti_akhir: string;
    tarikh_tamat: string | null;
    kaedah_tamat: string | null;
    semasa: boolean;
}

export interface LoginLog {
    id: number;
    pengguna: { id: number; name: string; email: string } | null;
    pengecam: string | null;
    kaedah: 'kata_laluan' | 'sso';
    berjaya: boolean;
    sebab: string | null;
    sebab_label: string | null;
    alamat_ip: string | null;
    peranti: string | null;
    pelayar: string | null;
    platform: string | null;
    lokasi: string | null;
    negara: string | null;
    mencurigakan: boolean;
    created_at: string;
}

export interface SecurityAlert {
    id: number;
    jenis: string;
    jenis_label: string;
    pengguna: { id: number; name: string; email: string } | null;
    alamat_ip: string | null;
    butiran: Record<string, unknown>;
    disemak_pada: string | null;
    created_at: string;
}

export interface Matrix {
    dijana_pada: string;
    tindakan: string[];
    peranan: Array<{ id: number; kod: string; nama: string; status: string; kategori_rfp: string | null; bilangan_pengguna: number; skop: ScopeMap }>;
    modul: Array<{ modul: string; label: string; fungsi: Array<{ fungsi: string; kebenaran: Record<string, { kunci: string; label: string }> }> }>;
    pemberian: Record<string, string[]>;
}

export interface ClaimRule {
    id: number;
    jenis_tuntutan: 'roles' | 'bahagian' | 'unit' | 'jawatan';
    nilai: string;
    peranan: { id: number; kod: string; nama: string } | null;
    skop_jenis: string | null;
    skop_nilai: string | null;
    aktif: boolean;
    keutamaan: number;
    keterangan: string | null;
}

export interface PolicyItem {
    kunci: string;
    label: string;
    jenis: 'int' | 'bool' | 'string';
    nilai: number | boolean | string;
    lalai: number | boolean | string;
    min: number | null;
    max: number | null;
    pilihan: string[] | null;
}

export interface RecertCycle {
    id: number;
    nama: string;
    bahagian: string | null;
    tarikh_mula: string;
    tarikh_akhir: string;
    status: 'dibuka' | 'ditutup';
    ditutup_pada: string | null;
    jumlah: number;
    belum: number;
    ditarik: number;
    boleh_semak: boolean;
    keputusan?: Array<{
        id: number;
        pengguna: { id: number; name: string; email: string; bahagian: string | null; jawatan: string | null };
        peranan: { id: number; kod: string; nama: string };
        kebenaran: string[];
        keputusan: 'belum' | 'disahkan' | 'ditarik';
        catatan: string | null;
        penyemak: string | null;
        disemak_pada: string | null;
    }>;
}

export interface AccessReport {
    tempoh_hari: number;
    had_tidak_aktif_hari: number;
    mengikut_status: Record<AccountStatus, number>;
    mengikut_peranan: Array<{ id: number; kod: string; nama: string; jumlah: number; aktif: number }>;
    mengikut_bahagian: Array<{ bahagian: string | null; jumlah: number; aktif: number }>;
    pengguna_tidak_aktif: Array<{ id: number; name: string; email: string; status_akaun: AccountStatus; tarikh_log_masuk_akhir: string | null; tarikh_tamat_capaian: string | null }>;
    kebenaran_peranan: Array<{ kod: string; nama: string; kebenaran: string[] }>;
    gagal_mengikut_pengguna: Array<{ pengecam: string; bilangan: number; terakhir: string }>;
    gagal_mengikut_ip: Array<{ alamat_ip: string; bilangan: number; terakhir: string }>;
}

export interface SessionStatus {
    baki_saat: number | null;
    tamat_minit: number;
    amaran_saat: number;
}

export const STATUS_LABEL: Record<AccountStatus, string> = {
    aktif: 'Aktif',
    gantung: 'Gantung',
    nyahaktif: 'Nyahaktif',
    tamat_tempoh: 'Tamat Tempoh',
};

export const STATUS_VARIANT: Record<AccountStatus, 'success' | 'warning' | 'neutral' | 'danger'> = {
    aktif: 'success',
    gantung: 'warning',
    nyahaktif: 'neutral',
    tamat_tempoh: 'danger',
};

export const ACTION_LABEL: Record<string, string> = {
    lihat: 'Lihat',
    cipta: 'Cipta',
    kemas_kini: 'Kemas kini',
    padam: 'Padam',
    lulus: 'Lulus',
    eksport: 'Eksport',
    akses: 'Akses',
};

export const SESSION_END_LABEL: Record<string, string> = {
    log_keluar: 'Log keluar',
    tamat_masa: 'Tamat masa (tidak aktif)',
    paksa: 'Ditamatkan oleh pentadbir',
    status_akaun: 'Status akaun berubah',
    had_serentak: 'Had sesi serentak',
    ikatan_gagal: 'Pelayar/rangkaian berubah',
};

export function formatDateTime(value: string | null | undefined): string {
    if (!value) return '—';
    return new Date(value).toLocaleString('ms-MY', { dateStyle: 'medium', timeStyle: 'short' });
}

function qs(params: Record<string, string | number | boolean | undefined | null>): string {
    const search = new URLSearchParams();
    for (const [key, value] of Object.entries(params)) {
        if (value !== undefined && value !== null && value !== '' && value !== false) search.set(key, String(value === true ? 1 : value));
    }
    const s = search.toString();
    return s ? `?${s}` : '';
}

/** Download URL for an export (same-origin /api rewrite; the browser sends the session cookie). */
export function exportUrl(path: string, params: Record<string, string | number | boolean | undefined | null> = {}): string {
    return `/api/v1/rbc/${path}${qs(params)}`;
}

export const rbc = {
    catalogue: () => api.get<{ data: Catalogue }>('/rbc/kebenaran'),
    roles: () => api.get<{ data: Role[] }>('/rbc/peranan'),
    role: (id: number | string) => api.get<{ data: Role }>(`/rbc/peranan/${id}`),
    createRole: (payload: { nama: string; kod?: string; keterangan?: string }) => api.post<{ data: Role }>('/rbc/peranan', payload),
    updateRole: (id: number, payload: { nama: string; keterangan: string | null }) => api.put<{ data: Role }>(`/rbc/peranan/${id}`, payload),
    setRolePermissions: (id: number, kebenaran: string[]) => api.put<{ data: Role }>(`/rbc/peranan/${id}/kebenaran`, { kebenaran }),
    setRoleScopes: (id: number, skop: ScopeMap) => api.put<{ data: Role }>(`/rbc/peranan/${id}/skop`, { skop }),
    setRoleStatus: (id: number, status: Role['status']) => api.put<{ data: Role }>(`/rbc/peranan/${id}/status`, { status }),
    cloneRole: (id: number, nama: string) => api.post<{ data: Role }>(`/rbc/peranan/${id}/klon`, { nama }),
    deleteRole: (id: number) => api.delete<void>(`/rbc/peranan/${id}`),

    userAccess: (id: number | string) => api.get<{ data: UserAccess }>(`/rbc/pengguna/${id}`),
    setUserStatus: (id: number, status: AccountStatus, sebab: string) => api.put<{ data: UserAccess }>(`/rbc/pengguna/${id}/status`, { status, sebab }),
    setUserRoles: (id: number, peranan: number[]) => api.put<{ data: UserAccess }>(`/rbc/pengguna/${id}/peranan`, { peranan }),
    setUserScopes: (id: number, skop: ScopeMap) => api.put<{ data: UserAccess }>(`/rbc/pengguna/${id}/skop`, { skop }),
    setUserAccessPeriod: (id: number, payload: Record<string, string | null>) => api.put<{ data: UserAccess }>(`/rbc/pengguna/${id}/capaian`, payload),
    unlockUser: (id: number) => api.post<{ data: UserAccess }>(`/rbc/pengguna/${id}/buka-kunci`),
    endUserSessions: (id: number) => api.delete<{ data: { ditamatkan: number } }>(`/rbc/pengguna/${id}/sesi`),

    sessions: (params: { search?: string; sejarah?: boolean; page?: number }) => api.get<Paginated<Session>>(`/rbc/sesi${qs(params)}`),
    endSession: (id: string) => api.delete<{ data: Session }>(`/rbc/sesi/${id}`),
    sessionStatus: () => api.get<{ data: SessionStatus }>('/rbc/sesi/status'),
    extendSession: () => api.post<{ data: SessionStatus }>('/rbc/sesi/lanjut'),
    changePassword: (payload: { kata_laluan_semasa: string; kata_laluan: string; kata_laluan_confirmation: string }) =>
        api.post<{ message: string }>('/rbc/kata-laluan', payload),

    loginLog: (params: Record<string, string | number | boolean | undefined>) => api.get<Paginated<LoginLog>>(`/rbc/log-masuk${qs(params)}`),
    alerts: (params: { belum_disemak?: boolean; page?: number }) => api.get<Paginated<SecurityAlert>>(`/rbc/amaran${qs(params)}`),
    acknowledgeAlert: (id: number) => api.put<{ data: SecurityAlert }>(`/rbc/amaran/${id}/semak`),

    matrix: () => api.get<{ data: Matrix }>('/rbc/matriks'),
    report: (hari: number) => api.get<{ data: AccessReport }>(`/rbc/laporan${qs({ hari })}`),

    claimRules: () => api.get<{ data: ClaimRule[] }>('/rbc/pemetaan'),
    createClaimRule: (payload: Record<string, unknown>) => api.post<{ data: ClaimRule }>('/rbc/pemetaan', payload),
    updateClaimRule: (id: number, payload: Record<string, unknown>) => api.put<{ data: ClaimRule }>(`/rbc/pemetaan/${id}`, payload),
    deleteClaimRule: (id: number) => api.delete<void>(`/rbc/pemetaan/${id}`),
    previewClaims: (payload: { roles?: string[]; bahagian?: string; unit?: string; jawatan?: string }) =>
        api.post<{ data: { peranan: Array<{ id: number; kod: string; nama: string }>; skop: ScopeMap; peraturan: number[] } }>('/rbc/pemetaan/uji', payload),

    policy: () => api.get<{ data: PolicyItem[] }>('/rbc/polisi'),
    updatePolicy: (nilai: Record<string, number | boolean | string>) => api.put<{ data: PolicyItem[] }>('/rbc/polisi', { nilai }),

    recertCycles: () => api.get<{ data: RecertCycle[] }>('/rbc/recert'),
    recertCycle: (id: number | string, keputusan?: string) => api.get<{ data: RecertCycle }>(`/rbc/recert/${id}${qs({ keputusan })}`),
    openRecert: (payload: { nama: string; bahagian?: string; tarikh_akhir?: string }) => api.post<{ data: RecertCycle }>('/rbc/recert', payload),
    decideRecert: (cycleId: number, decisionId: number, keputusan: 'disahkan' | 'ditarik', catatan?: string) =>
        api.put<{ data: { id: number; keputusan: string } }>(`/rbc/recert/${cycleId}/keputusan/${decisionId}`, { keputusan, catatan }),
    closeRecert: (id: number) => api.post<{ data: RecertCycle }>(`/rbc/recert/${id}/tutup`),
};
