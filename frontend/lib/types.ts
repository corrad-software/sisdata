export interface User {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'user';
    is_active: boolean;
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
}
