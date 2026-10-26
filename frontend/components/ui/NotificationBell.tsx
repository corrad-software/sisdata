'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useEffect, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { actOnNotifications, fetchInboxSummary, KEUTAMAAN_LABEL, masaRelatif, type Notifikasi } from '@/lib/ntf';

// MOD-NTF bell: real unread count + latest notifications from /api/v1/ntf,
// polled every 45 s (no websockets). Opening an item marks it read.
const POLL_MS = 45_000;

const DOT: Record<Notifikasi['keutamaan'], string> = {
    kritikal: 'bg-danger-600',
    tinggi: 'bg-warning-600',
    biasa: 'bg-primary-600',
    maklumat: 'bg-bg-black-400',
};

export function NotificationBell() {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);
    const router = useRouter();
    const queryClient = useQueryClient();

    const { data, isError } = useQuery({
        queryKey: ['ntf', 'ringkas'],
        queryFn: fetchInboxSummary,
        refetchInterval: POLL_MS,
        refetchIntervalInBackground: false,
        refetchOnWindowFocus: true,
        retry: 1,
    });

    const unread = data?.data.belum_baca ?? 0;
    const latest = data?.data.terkini ?? [];

    const markRead = useMutation({
        mutationFn: (ids: number[] | 'semua') => actOnNotifications('baca', ids),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['ntf'] }),
    });

    useEffect(() => {
        function onClick(e: MouseEvent) {
            if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
        }
        function onKey(e: KeyboardEvent) {
            if (e.key === 'Escape') setOpen(false);
        }
        document.addEventListener('click', onClick);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('click', onClick);
            document.removeEventListener('keydown', onKey);
        };
    }, []);

    function openItem(item: Notifikasi) {
        if (!item.dibaca) markRead.mutate([item.id]);
        setOpen(false);
        router.push(item.pautan ?? '/admin/notifikasi');
    }

    return (
        <div className="relative" ref={ref} data-panduan="notifikasi">
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className="relative inline-flex items-center justify-center rounded-md p-2 text-txt-black-500 hover:text-txt-black-900 hover:bg-bg-washed focus:outline-none focus-visible:ring-2 focus-visible:ring-fr-primary transition ease-in-out duration-150"
                aria-label={unread > 0 ? `Notifikasi, ${unread} belum dibaca` : 'Notifikasi'}
                title="Notifikasi"
                aria-expanded={open}
                aria-haspopup="true"
                data-testid="ntf-bell"
            >
                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} aria-hidden="true">
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"
                    />
                </svg>

                {unread > 0 && (
                    <span
                        className="absolute -top-0.5 -right-0.5 inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-danger-600 px-1 text-[10px] font-semibold leading-none text-white"
                        data-testid="ntf-bell-count"
                    >
                        {unread > 9 ? '9+' : unread}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute end-0 z-50 mt-2 w-[min(20rem,calc(100vw-2rem))] origin-top-right rounded-md shadow-lg">
                    <div className="rounded-md ring-1 ring-black ring-opacity-5 bg-bg-white">
                        <div className="flex items-center justify-between gap-2 px-4 py-2 border-b border-otl-divider">
                            <span className="text-body-sm font-semibold text-txt-black-900">Notifikasi</span>
                            {unread > 0 && (
                                <button
                                    type="button"
                                    onClick={() => markRead.mutate('semua')}
                                    disabled={markRead.isPending}
                                    className="text-body-xs text-primary-600 hover:underline disabled:opacity-50"
                                >
                                    Tanda semua dibaca
                                </button>
                            )}
                        </div>

                        <div className="max-h-80 overflow-y-auto">
                            {isError ? (
                                <p className="px-4 py-6 text-center text-body-sm text-txt-black-500">Notifikasi tidak dapat dimuatkan.</p>
                            ) : latest.length === 0 ? (
                                <p className="px-4 py-6 text-center text-body-sm text-txt-black-500">Tiada notifikasi.</p>
                            ) : (
                                latest.map((item) => (
                                    <button
                                        key={item.id}
                                        type="button"
                                        onClick={() => openItem(item)}
                                        className={`flex w-full gap-3 px-4 py-3 text-start text-body-sm border-b border-otl-divider last:border-0 hover:bg-bg-washed ${
                                            item.dibaca ? 'text-txt-black-500' : 'text-txt-black-900 bg-bg-primary-50'
                                        }`}
                                    >
                                        <span
                                            className={`mt-1.5 h-2 w-2 flex-shrink-0 rounded-full ${item.dibaca ? 'bg-transparent' : DOT[item.keutamaan]}`}
                                            aria-hidden="true"
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className={`block truncate ${item.dibaca ? '' : 'font-medium'}`}>{item.subjek}</span>
                                            <span className="mt-0.5 block text-body-xs text-txt-black-500">
                                                {KEUTAMAAN_LABEL[item.keutamaan]} · {masaRelatif(item.created_at)}
                                                {!item.dibaca && <span className="sr-only"> (belum dibaca)</span>}
                                            </span>
                                        </span>
                                    </button>
                                ))
                            )}
                        </div>

                        <Link
                            href="/admin/notifikasi"
                            onClick={() => setOpen(false)}
                            className="block border-t border-otl-divider px-4 py-2 text-center text-body-sm font-medium text-primary-600 hover:bg-bg-washed rounded-b-md"
                        >
                            Lihat semua notifikasi
                        </Link>
                    </div>
                </div>
            )}
        </div>
    );
}
