'use client';

import { useState } from 'react';
import type { ReactNode } from 'react';
import { Sidebar } from './Sidebar';
import { UserMenu } from './UserMenu';
import { Footer } from './Footer';
import type { User } from '@/lib/types';

export function DashboardShell({ user, children }: { user: User; children: ReactNode }) {
    const [sidebarOpen, setSidebarOpen] = useState(false);

    return (
        <div className="flex flex-1 min-h-0">
            <Sidebar open={sidebarOpen} onClose={() => setSidebarOpen(false)} />

            <div className="flex-1 flex flex-col min-w-0">
                <header className="h-16 flex items-center justify-between gap-4 px-4 sm:px-6 bg-bg-white border-b border-otl-divider">
                    <button type="button" className="lg:hidden text-txt-black-500" onClick={() => setSidebarOpen(true)}>
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <div className="flex-1" />

                    <UserMenu user={user} />
                </header>

                <main className="flex-1">{children}</main>

                <Footer />
            </div>
        </div>
    );
}
