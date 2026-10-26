'use client';

import { useEffect, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { logout } from '@/lib/auth';
import type { User } from '@/lib/types';
import { SessionGuard } from '@/app/(dashboard)/admin/rbc/_components/SessionGuard';
import { ChangePasswordModal } from '@/app/(dashboard)/admin/rbc/_components/ChangePasswordModal';
import { toast } from 'sonner';
import { panduanApi, TETAPAN_LALAI } from '@/lib/panduan';
import { usePanduanStore } from '@/store/panduan';

export function UserMenu({ user }: { user: User }) {
    const [open, setOpen] = useState(false);
    const [changingPassword, setChangingPassword] = useState(false);
    const ref = useRef<HTMLDivElement>(null);
    const router = useRouter();
    const tetapan = usePanduanStore((s) => s.tetapan) ?? user.tetapan_panduan ?? TETAPAN_LALAI;
    const setTetapan = usePanduanStore((s) => s.setTetapan);
    const adaPanduan = usePanduanStore((s) => s.tersedia.length > 0);
    const mintaUlang = usePanduanStore((s) => s.minta);
    const [menyimpan, setMenyimpan] = useState(false);

    // SRS-GEN-08: switch auto-start on/off, or forget every completed tour.
    async function kemasKiniPanduan(body: { aktif?: boolean; set_semula?: boolean }, mesej: string) {
        setMenyimpan(true);
        try {
            setTetapan(await panduanApi.kemasKini(body));
            toast.success(mesej);
        } catch {
            toast.error('Tetapan panduan tidak dapat disimpan. Sila cuba lagi.');
        } finally {
            setMenyimpan(false);
        }
    }

    useEffect(() => {
        function handleClick(e: MouseEvent) {
            if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
        }
        document.addEventListener('click', handleClick);
        return () => document.removeEventListener('click', handleClick);
    }, []);

    async function handleLogout() {
        const result = await logout();
        // SRS-RBC-01: SSO users also end their SSO MAIS session; Keycloak then
        // sends them back to /login (its post-logout redirect URI).
        if (result?.logout_url) {
            window.location.assign(result.logout_url);
            return;
        }
        // No router.refresh() here: /login has no auth-dependent server logic
        // to go stale, and refreshing the still-mounted /admin tree at the same
        // moment races its own server-side redirect (now unauthenticated)
        // against this client push — the two competing navigations can hang.
        router.push('/login');
    }

    return (
        <div className="relative ms-1 sm:ms-2 shrink-0" ref={ref} data-panduan="menu-pengguna">
            {/* SRS-RBC-18 idle warning + SRS-RBC-12 forced password change, on every dashboard page. */}
            <SessionGuard user={user} />

            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                aria-haspopup="menu"
                aria-expanded={open}
                aria-label={`Menu pengguna: ${user.name}`}
                title="Menu pengguna"
                className="inline-flex items-center px-2 sm:px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-txt-black-500 bg-bg-white hover:text-txt-black-900 focus:outline-none transition ease-in-out duration-150"
            >
                <span className="sr-only sm:not-sr-only sm:max-w-[10rem] sm:truncate">{user.name}</span>
                <svg className="h-5 w-5 sm:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                </svg>
                <div className="ms-1">
                    <svg className="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" aria-hidden="true">
                        <path
                            fillRule="evenodd"
                            clipRule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                        />
                    </svg>
                </div>
            </button>

            {open && (
                <div role="menu" className="absolute right-0 z-50 mt-2 w-64 rounded-md shadow-card bg-bg-white border border-otl-gray-200 py-1">
                    <div className="px-4 py-2 text-body-xs text-txt-black-500 border-b border-otl-divider">
                        <div className="truncate">{user.email}</div>
                        {user.jenis_pengesahan === 'sso' && <div>Log masuk melalui SSO MAIS</div>}
                    </div>
                    <div className="border-b border-otl-divider py-1">
                        <button
                            type="button"
                            role="menuitemcheckbox"
                            aria-checked={tetapan.aktif}
                            disabled={menyimpan}
                            data-testid="panduan-togol"
                            onClick={() =>
                                kemasKiniPanduan(
                                    { aktif: !tetapan.aktif },
                                    tetapan.aktif ? 'Panduan interaktif dimatikan.' : 'Panduan interaktif dihidupkan.',
                                )
                            }
                            className="flex w-full items-center justify-between gap-3 px-4 py-2 text-start text-body-sm text-txt-black-700 hover:bg-bg-washed disabled:opacity-60"
                        >
                            <span>Panduan interaktif</span>
                            <span className="flex items-center gap-2 text-body-xs text-txt-black-500">
                                {tetapan.aktif ? 'Hidup' : 'Mati'}
                                <span
                                    aria-hidden="true"
                                    className={`relative inline-flex h-5 w-9 flex-shrink-0 rounded-full transition-colors ${tetapan.aktif ? 'bg-primary-600' : 'bg-otl-gray-300'}`}
                                >
                                    <span
                                        className={`absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-all ${tetapan.aktif ? 'left-[1.125rem]' : 'left-0.5'}`}
                                    />
                                </span>
                            </span>
                        </button>
                        <button
                            type="button"
                            role="menuitem"
                            disabled={!adaPanduan}
                            onClick={() => {
                                setOpen(false);
                                mintaUlang();
                            }}
                            className="block w-full text-start px-4 py-2 text-body-sm text-txt-black-700 hover:bg-bg-washed disabled:cursor-not-allowed disabled:opacity-50"
                            title={adaPanduan ? undefined : 'Tiada panduan untuk halaman ini'}
                        >
                            Ulang panduan halaman ini
                        </button>
                        <button
                            type="button"
                            role="menuitem"
                            disabled={menyimpan}
                            onClick={() => kemasKiniPanduan({ set_semula: true }, 'Semua panduan akan dipaparkan semula.')}
                            className="block w-full text-start px-4 py-2 text-body-sm text-txt-black-700 hover:bg-bg-washed disabled:opacity-60"
                        >
                            Tetapkan semula semua panduan
                        </button>
                    </div>
                    {user.jenis_pengesahan === 'dalaman' && (
                        <button
                            type="button"
                            role="menuitem"
                            onClick={() => {
                                setOpen(false);
                                setChangingPassword(true);
                            }}
                            className="block w-full text-start px-4 py-2 text-body-sm text-txt-black-700 hover:bg-bg-washed"
                        >
                            Tukar Kata Laluan
                        </button>
                    )}
                    <button
                        type="button"
                        role="menuitem"
                        onClick={handleLogout}
                        className="block w-full text-start px-4 py-2 text-body-sm text-txt-black-700 hover:bg-bg-washed"
                    >
                        Log Keluar
                    </button>
                </div>
            )}

            <ChangePasswordModal
                open={changingPassword}
                forced={false}
                onClose={() => setChangingPassword(false)}
                onChanged={() => setChangingPassword(false)}
            />
        </div>
    );
}
