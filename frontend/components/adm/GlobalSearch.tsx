'use client';

import Link from 'next/link';
import { useEffect, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { globalSearch, type SearchGroup } from '@/lib/adm';

/**
 * SRS-ADM-21: global search box in the dashboard header. Type-ahead shows the
 * top hits per entity (already filtered by the user's access server-side);
 * Enter opens the full results page.
 */
export function GlobalSearch() {
    const router = useRouter();
    const [q, setQ] = useState('');
    const [groups, setGroups] = useState<SearchGroup[]>([]);
    const [open, setOpen] = useState(false);
    const box = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const term = q.trim();
        if (term.length < 2) return;
        let cancelled = false;
        const t = setTimeout(() => {
            globalSearch(term, 5)
                .then((r) => {
                    if (!cancelled) {
                        setGroups(r.data);
                        setOpen(true);
                    }
                })
                .catch(() => !cancelled && setGroups([]));
        }, 300);
        return () => {
            cancelled = true;
            clearTimeout(t);
        };
    }, [q]);

    useEffect(() => {
        function onClick(e: MouseEvent) {
            if (box.current && !box.current.contains(e.target as Node)) setOpen(false);
        }
        document.addEventListener('mousedown', onClick);
        return () => document.removeEventListener('mousedown', onClick);
    }, []);

    const visible = open && q.trim().length >= 2;

    return (
        <>
        <Link
            href="/admin/pentadbiran/carian"
            aria-label="Carian global"
            className="sm:hidden inline-flex rounded-md p-2 text-txt-black-500 hover:text-txt-black-900 hover:bg-bg-washed"
        >
            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
        </Link>
        <div ref={box} className="relative hidden sm:block w-full max-w-md">
            <form
                role="search"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (!q.trim()) return;
                    setOpen(false);
                    router.push(`/admin/pentadbiran/carian?q=${encodeURIComponent(q.trim())}`);
                }}
            >
                <label htmlFor="carian-global" className="sr-only">
                    Carian global
                </label>
                <input
                    id="carian-global"
                    type="search"
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    onFocus={() => groups.length && setOpen(true)}
                    onKeyDown={(e) => e.key === 'Escape' && setOpen(false)}
                    placeholder="Cari lot, hak milik, pemilik…"
                    autoComplete="off"
                    className="block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm"
                />
            </form>

            {visible && (
                <div className="absolute left-0 right-0 z-40 mt-1 max-h-96 overflow-y-auto rounded-md border border-otl-gray-200 bg-bg-white shadow-card" role="listbox">
                    {groups.length === 0 ? (
                        <p className="p-3 text-body-sm text-txt-black-500">Tiada hasil.</p>
                    ) : (
                        groups.map((g) => (
                            <div key={g.entiti}>
                                <p className="bg-bg-washed px-3 py-1.5 text-body-xs font-semibold uppercase text-txt-black-500">{g.label}</p>
                                <ul>
                                    {g.hasil.map((h) => (
                                        <li key={`${g.entiti}-${h.id}`}>
                                            <Link href={h.link} onClick={() => setOpen(false)} className="block px-3 py-2 hover:bg-bg-washed">
                                                <span className="block text-body-sm text-txt-black-900">{h.title}</span>
                                                {h.subtitle && <span className="block text-body-xs text-txt-black-500">{h.subtitle}</span>}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))
                    )}
                    <Link
                        href={`/admin/pentadbiran/carian?q=${encodeURIComponent(q.trim())}`}
                        onClick={() => setOpen(false)}
                        className="block border-t border-otl-gray-200 px-3 py-2 text-body-sm font-medium text-primary-600 hover:bg-bg-washed"
                    >
                        Lihat semua hasil
                    </Link>
                </div>
            )}
        </div>
        </>
    );
}
