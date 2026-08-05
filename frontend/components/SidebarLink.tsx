'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { useSidebarStore } from '@/store/sidebar';

interface SidebarLinkProps {
    href: string;
    icon?: ReactNode;
    label?: string;
    activeMatch?: (pathname: string) => boolean;
    children: ReactNode;
}

export function SidebarLink({ href, icon, label, activeMatch, children }: SidebarLinkProps) {
    const pathname = usePathname();
    const collapsed = useSidebarStore((s) => s.collapsed);
    const [showTooltip, setShowTooltip] = useState(false);

    const active = activeMatch ? activeMatch(pathname) : pathname === href;

    const classes = `flex w-full items-center gap-3 px-3 py-2 rounded-md text-body-sm leading-tight font-medium ${
        collapsed ? 'lg:justify-center' : ''
    } ${
        active
            ? 'bg-white text-primary-700 shadow-sm'
            : 'text-primary-100 hover:bg-primary-600 hover:text-white'
    }`;

    return (
        <div className="relative" onMouseEnter={() => setShowTooltip(true)} onMouseLeave={() => setShowTooltip(false)}>
            <Link href={href} className={classes}>
                {icon && <span className="flex-shrink-0">{icon}</span>}
                <span className={`truncate ${collapsed ? 'lg:hidden' : ''}`}>{children}</span>
            </Link>

            {label && showTooltip && collapsed && (
                <div className="hidden lg:block absolute left-full top-1/2 z-20 ms-2 -translate-y-1/2 whitespace-nowrap rounded-md bg-txt-black-900 px-2.5 py-1.5 text-body-xs text-white shadow-card pointer-events-none">
                    {label}
                    <span className="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-txt-black-900" />
                </div>
            )}
        </div>
    );
}
