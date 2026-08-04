import type { Metadata } from 'next';
import type { ReactNode } from 'react';
import './globals.css';
import { Providers } from '@/components/Providers';
import { getServerSettings } from '@/lib/settings-server';

export async function generateMetadata(): Promise<Metadata> {
    const settings = await getServerSettings();

    return {
        title: settings.site_title,
        description: 'MYDS Laravel starter',
    };
}

export default async function RootLayout({ children }: { children: ReactNode }) {
    const settings = await getServerSettings();

    return (
        <html lang="en">
            <head>
                {settings.site_favicon_url && <link rel="icon" href={settings.site_favicon_url} />}
                {/* Rendered after the compiled globals.css stylesheet so the `*` selector
                    override wins by source order — MYDS defines --_primary-* on `*`, not
                    :root, so every element needs its own directly-assigned value. */}
                {settings.theme_css && <style>{settings.theme_css}</style>}
            </head>
            <body className="font-body antialiased">
                <Providers>{children}</Providers>
            </body>
        </html>
    );
}
