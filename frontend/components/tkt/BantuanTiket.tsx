'use client';

import { useEffect, useState } from 'react';
import { usePathname } from 'next/navigation';
import { pasangPenjejakPermintaan, tangkapKonteks, type KonteksSkrin } from '@/lib/tkt';
import type { User } from '@/lib/types';
import { BorangTiket } from './BorangTiket';

function Ikon({ className }: { className: string }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8} aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
        </svg>
    );
}

/**
 * SRS-TKT-01: help button on every dashboard screen. Opens a side panel over
 * the current page (the page stays mounted, so unsaved work is kept) with
 * the screen's online help (MOD-DSH) and the support-ticket form; the
 * screen context is captured at the moment the panel opens.
 */
export function BantuanTiket({ user }: { user: User }) {
    const pathname = usePathname();
    const [open, setOpen] = useState(false);
    const [konteks, setKonteks] = useState<KonteksSkrin | null>(null);
    const bolehCipta = (user.permissions ?? []).includes('tkt.tiket.cipta');

    useEffect(() => {
        pasangPenjejakPermintaan();
    }, []);
    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [open]);

    function buka() {
        setKonteks(tangkapKonteks());
        setOpen(true);
    }

    return (
        <>
            {/* Header button from sm up; on phones a floating button, so the crowded header never overflows at 375px. */}
            <button type="button" onClick={buka} aria-label="Bantuan" className="hidden sm:inline-flex h-9 items-center gap-1.5 rounded-md px-2 text-body-sm font-medium text-txt-black-700 hover:bg-bg-washed focus:outline-none focus-visible:ring-2 focus-visible:ring-fr-primary" aria-haspopup="dialog" aria-expanded={open}>
                <Ikon className="h-5 w-5" />
                <span className="hidden md:inline">Bantuan</span>
            </button>
            <button type="button" onClick={buka} aria-label="Bantuan" aria-haspopup="dialog" aria-expanded={open} className="sm:hidden fixed bottom-4 right-4 z-40 inline-flex h-12 w-12 items-center justify-center rounded-full bg-primary-600 text-white shadow-card focus:outline-none focus-visible:ring-2 focus-visible:ring-fr-primary">
                <Ikon className="h-6 w-6" />
            </button>

            {open && (
                <div className="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="tkt-bantuan-tajuk">
                    <div className="absolute inset-0 bg-bg-black-900/40" onClick={() => setOpen(false)} />
                    <aside className="absolute inset-y-0 right-0 flex w-full max-w-lg flex-col bg-bg-white shadow-card">
                        <div className="flex items-center justify-between border-b border-otl-divider px-4 py-3">
                            <h2 id="tkt-bantuan-tajuk" className="font-heading text-body-lg font-semibold text-txt-black-900">
                                Bantuan &amp; tiket sokongan
                            </h2>
                            <button type="button" onClick={() => setOpen(false)} className="rounded p-1 text-txt-black-500 hover:bg-bg-washed" aria-label="Tutup panel bantuan">
                                ✕
                            </button>
                        </div>
                        <div className="flex-1 space-y-4 overflow-y-auto px-4 py-4">
                            <a href={`/admin/dsh/bantuan?laluan=${encodeURIComponent(pathname)}`} target="_blank" rel="noopener" className="block rounded-md border border-otl-gray-200 p-3 hover:bg-bg-washed">
                                <span className="text-body-sm font-medium text-txt-primary">Panduan bagi skrin ini ↗</span>
                                <span className="block text-body-xs text-txt-black-500">Panduan langkah demi langkah, soalan lazim dan manual pengguna (dibuka dalam tab baharu).</span>
                            </a>
                            {bolehCipta ? (
                                <BorangTiket konteksAwal={konteks} peranan={user.peranan} onNavigasi={() => setOpen(false)} />
                            ) : (
                                <p className="text-body-sm text-txt-black-500">Anda tidak mempunyai kebenaran membuka tiket sokongan. Sila hubungi pentadbir sistem.</p>
                            )}
                        </div>
                    </aside>
                </div>
            )}
        </>
    );
}
