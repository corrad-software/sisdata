/** MOD-RBC account status (SRS-RBC-15). */
export type AccountStatus = 'aktif' | 'gantung' | 'nyahaktif' | 'tamat_tempoh';

/** SRS-GEN-08: interactive guide preferences (MOD-RBC `users.tetapan_panduan`). */
export interface TetapanPanduan {
    /** Auto-start tours the first time a page is opened. */
    aktif: boolean;
    /** Tour key → version the user finished or skipped. */
    selesai: Record<string, string>;
}

export interface User {
    id: number;
    name: string;
    email: string;
    /** @deprecated legacy; authorisation uses `peranan` / `permissions`. */
    role: 'admin' | 'user';
    is_active: boolean;
    status_akaun: AccountStatus;
    jenis_pengesahan: 'dalaman' | 'sso';
    bahagian: string | null;
    unit: string | null;
    jawatan: string | null;
    /** Role codes (MOD-RBC). */
    peranan: string[];
    /** UI hiding only — the API enforces access itself. */
    permissions: string[];
    tarikh_log_masuk_akhir: string | null;
    tarikh_tamat_capaian: string | null;
    dikunci: boolean;
    /** SRS-RBC-12: must change password before anything else. */
    perlu_tukar_kata_laluan: boolean;
    /** SRS-GEN-08: own interactive guide preferences (only on the signed-in user's own record). */
    tetapan_panduan?: TetapanPanduan;
    created_at: string;
    updated_at: string;
}

export interface Settings {
    site_title: string;
    footer_text: string | null;
    primary_color: string;
    site_logo_url: string | null;
    site_favicon_url: string | null;
    theme_css: string;
    /** SRS-RBC-01: false when SSO MAIS is the only allowed sign-in method. */
    password_login_enabled?: boolean;
}
