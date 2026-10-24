import type { Metadata, Viewport } from 'next';
import type { ReactNode } from 'react';

export const metadata: Metadata = {
    title: 'Aplikasi Lapangan · Pemantauan Tanah',
    manifest: '/lapangan.webmanifest',
};

export const viewport: Viewport = { themeColor: '#2563eb' };

export default function LapanganLayout({ children }: { children: ReactNode }) {
    return children;
}
