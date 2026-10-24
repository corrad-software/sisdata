export interface User {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'user';
    is_active: boolean;
    permissions: string[];
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
