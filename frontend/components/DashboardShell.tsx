'use client';

import { useState } from 'react';
import type { ReactNode } from 'react';
import { Sidebar } from './Sidebar';
import { UserMenu } from './UserMenu';
import { Footer } from './Footer';
import { FontSizeToggle } from './ui/FontSizeToggle';
import { ThemeToggle } from './ui/ThemeToggle';
import { LanguageSwitcher } from './ui/LanguageSwitcher';
import { NotificationBell } from './ui/NotificationBell';
import type { User } from '@/lib/types';

export function DashboardShell({
    user,
    siteTitle,
    footerText,
    children,
}: {
    user: User;
    siteTitle: string;
    footerText: string | null;
    children: ReactNode;
}) {
    const [sidebarOpen, setSidebarOpen] = useState(false);

    return (
        <div className="flex flex-1 min-h-0">
            <Sidebar
                open={sidebarOpen}
                onClose={() => setSidebarOpen(false)}
                siteTitle={siteTitle}
                permissions={user.permissions ?? []}
            />

            <div className="flex-1 flex flex-col min-w-0">
                <header className="h-16 flex items-center justify-between gap-4 px-4 sm:px-6 bg-bg-white border-b border-otl-divider">
                    <button type="button" className="lg:hidden text-txt-black-500" onClick={() => setSidebarOpen(true)}>
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <div className="flex-1" />

                    <div className="flex items-center gap-1">
                        <FontSizeToggle />
                        <ThemeToggle />
                        <LanguageSwitcher />
                        <NotificationBell />
                    </div>

                    <UserMenu user={user} />
                </header>

                <main className="flex-1">{children}</main>

                <Footer siteTitle={siteTitle} footerText={footerText} />
            </div>
        </div>
    );
}
