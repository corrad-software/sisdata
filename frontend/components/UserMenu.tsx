'use client';

import { useEffect, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { logout } from '@/lib/auth';
import type { User } from '@/lib/types';

export function UserMenu({ user }: { user: User }) {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);
    const router = useRouter();

    useEffect(() => {
        function handleClick(e: MouseEvent) {
            if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
        }
        document.addEventListener('click', handleClick);
        return () => document.removeEventListener('click', handleClick);
    }, []);

    async function handleLogout() {
        await logout();
        // No router.refresh() here: /login has no auth-dependent server logic
        // to go stale, and refreshing the still-mounted /admin tree at the same
        // moment races its own server-side redirect (now unauthenticated)
        // against this client push — the two competing navigations can hang.
        router.push('/login');
    }

    return (
        <div className="relative ms-2" ref={ref}>
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-txt-black-500 bg-bg-white hover:text-txt-black-900 focus:outline-none transition ease-in-out duration-150"
            >
                <div>{user.name}</div>
                <div className="ms-1">
                    <svg className="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <path
                            fillRule="evenodd"
                            clipRule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                        />
                    </svg>
                </div>
            </button>

            {open && (
                <div className="absolute right-0 z-50 mt-2 w-48 rounded-md shadow-card bg-bg-white border border-otl-gray-200 py-1">
                    <button
                        type="button"
                        onClick={handleLogout}
                        className="block w-full text-start px-4 py-2 text-body-sm text-txt-black-700 hover:bg-bg-washed"
                    >
                        Log Out
                    </button>
                </div>
            )}
        </div>
    );
}
