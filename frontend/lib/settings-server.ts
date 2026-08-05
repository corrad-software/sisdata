import { cache } from 'react';
import type { Settings } from './types';

const backend = process.env.INTERNAL_API_URL ?? 'http://127.0.0.1:8000';

const DEFAULT_SETTINGS: Settings = {
    site_title: 'MYDS-Laravel',
    footer_text: null,
    primary_color: '#2563EB',
    site_logo_url: null,
    site_favicon_url: null,
    theme_css: '',
};

// Wrapped in React's cache() so the root layout's generateMetadata() and the
// layout component itself — which both need settings — share one backend
// call per request instead of firing it twice.
export const getServerSettings = cache(async (): Promise<Settings> => {
    try {
        const response = await fetch(`${backend}/api/settings`, {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });

        if (!response.ok) return DEFAULT_SETTINGS;

        const json = await response.json();
        return json.data as Settings;
    } catch {
        return DEFAULT_SETTINGS;
    }
});
