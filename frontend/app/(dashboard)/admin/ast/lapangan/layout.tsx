import type { Metadata, Viewport } from 'next';
import type { ReactNode } from 'react';

export const metadata: Metadata = {
    title: 'Aplikasi Lapangan · Aset Fizikal',
    manifest: '/aset-lapangan.webmanifest',
};

export const viewport: Viewport = { themeColor: '#2563eb' };

export default function LapanganAsetLayout({ children }: { children: ReactNode }) {
    return children;
}
