'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useState } from 'react';
import { useSidebarStore } from '@/store/sidebar';
import { SidebarLink } from './SidebarLink';
import { ApplicationLogo } from './ApplicationLogo';

const KITCHEN_SINK_GROUPS: Array<{ key: string; label: string; href: string }> = [
    { key: 'foundation', label: 'Foundation', href: '/admin/kitchen-sink/foundation/colour' },
    { key: 'components', label: 'Components', href: '/admin/kitchen-sink/components/buttons' },
    { key: 'data-visual', label: 'Data Visual', href: '/admin/kitchen-sink/data-visual/dashboard' },
    { key: 'sample-pages', label: 'Sample Pages', href: '/admin/kitchen-sink/sample-pages/login' },
];

function DashboardIcon({ collapsed }: { collapsed: boolean }) {
    return (
        <svg
            className={`h-4 w-4 transition-all ${collapsed ? 'lg:h-6 lg:w-6' : ''}`}
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={1.5}
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"
            />
        </svg>
    );
}

function UsersIcon({ collapsed }: { collapsed: boolean }) {
    return (
        <svg
            className={`h-4 w-4 transition-all ${collapsed ? 'lg:h-6 lg:w-6' : ''}`}
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={1.5}
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"
            />
        </svg>
    );
}

function SettingsIcon({ collapsed }: { collapsed: boolean }) {
    return (
        <svg
            className={`h-4 w-4 transition-all ${collapsed ? 'lg:h-6 lg:w-6' : ''}`}
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={1.5}
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"
            />
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
    );
}

function KitchenSinkIcon({ collapsed }: { collapsed: boolean }) {
    return (
        <svg
            className={`h-4 w-4 flex-shrink-0 transition-all ${collapsed ? 'lg:h-6 lg:w-6' : ''}`}
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={1.5}
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"
            />
        </svg>
    );
}

function KitchenSinkNavGroup({ collapsed }: { collapsed: boolean }) {
    const pathname = usePathname();
    const isActiveGroup = pathname.startsWith('/admin/kitchen-sink');
    const [open, setOpen] = useState(isActiveGroup);
    const [showTooltip, setShowTooltip] = useState(false);
    const expand = useSidebarStore((s) => s.expand);

    function handleClick() {
        if (collapsed) {
            expand();
            setOpen(true);
        } else {
            setOpen((o) => !o);
        }
    }

    return (
        <div className="relative" onMouseEnter={() => setShowTooltip(true)} onMouseLeave={() => setShowTooltip(false)}>
            <button
                type="button"
                onClick={handleClick}
                className={`flex w-full items-center gap-3 px-3 py-2 rounded-md text-body-sm leading-tight font-medium ${
                    collapsed ? 'lg:justify-center' : ''
                } ${
                    isActiveGroup
                        ? 'bg-white text-primary-700 shadow-sm'
                        : 'text-primary-100 hover:bg-primary-600 hover:text-white'
                }`}
            >
                <KitchenSinkIcon collapsed={collapsed} />
                <span className={`flex-1 text-start truncate ${collapsed ? 'lg:hidden' : ''}`}>Kitchen Sink</span>
                <svg
                    className={`h-4 w-4 flex-shrink-0 transition-transform ${open ? 'rotate-180' : ''} ${collapsed ? 'lg:hidden' : ''}`}
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    strokeWidth={2}
                >
                    <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>

            {showTooltip && collapsed && (
                <div className="hidden lg:block absolute left-full top-1/2 z-20 ms-2 -translate-y-1/2 whitespace-nowrap rounded-md bg-txt-black-900 px-2.5 py-1.5 text-body-xs text-white shadow-card pointer-events-none">
                    Kitchen Sink
                    <span className="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-txt-black-900" />
                </div>
            )}

            {open && (
                <div className={`ms-4 mt-1 space-y-1 border-l border-primary-600 ps-3 ${collapsed ? 'lg:hidden' : ''}`}>
                    {KITCHEN_SINK_GROUPS.map((group) => (
                        <SidebarLink
                            key={group.key}
                            href={group.href}
                            activeMatch={(p) => p.startsWith(`/admin/kitchen-sink/${group.key}`)}
                        >
                            {group.label}
                        </SidebarLink>
                    ))}
                </div>
            )}
        </div>
    );
}

export function Sidebar({
    open,
    onClose,
    siteTitle = 'MYDS-Laravel',
}: {
    open: boolean;
    onClose: () => void;
    siteTitle?: string;
}) {
    const collapsed = useSidebarStore((s) => s.collapsed);
    const toggle = useSidebarStore((s) => s.toggle);

    return (
        <>
            {open && <div onClick={onClose} className="fixed inset-0 z-30 bg-bg-black-900/50 lg:hidden" />}

            <aside
                className={[
                    'fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col bg-primary-700 transform transition-all duration-200 ease-in-out lg:relative lg:translate-x-0',
                    open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                    collapsed ? 'lg:w-20' : 'lg:w-64',
                ].join(' ')}
            >
                <button
                    type="button"
                    onClick={toggle}
                    className="hidden lg:flex absolute -right-3 top-12 h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-primary-600 text-white shadow-card hover:bg-bg-white hover:text-primary-600 z-10"
                    title={collapsed ? 'Expand menu' : 'Collapse menu'}
                >
                    <svg
                        className={`h-3.5 w-3.5 transition-transform ${collapsed ? 'rotate-180' : ''}`}
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        strokeWidth={2}
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                </button>

                <div className="h-16 flex items-center justify-between px-6 border-b border-primary-600">
                    <Link href="/admin" className="flex items-center gap-2 min-w-0">
                        <ApplicationLogo className="h-8 w-auto flex-shrink-0 fill-current text-white" />
                        <span className={`truncate text-body-sm font-semibold text-white ${collapsed ? 'lg:hidden' : ''}`}>
                            {siteTitle}
                        </span>
                    </Link>
                    <button type="button" className="lg:hidden text-primary-100 hover:text-white" onClick={onClose}>
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <nav className="flex-1 px-3 py-4 space-y-1">
                    <SidebarLink
                        href="/admin"
                        label="Dashboard"
                        activeMatch={(p) => p === '/admin'}
                        icon={<DashboardIcon collapsed={collapsed} />}
                    >
                        Dashboard
                    </SidebarLink>

                    <SidebarLink
                        href="/admin/users"
                        label="Users"
                        activeMatch={(p) => p.startsWith('/admin/users')}
                        icon={<UsersIcon collapsed={collapsed} />}
                    >
                        Users
                    </SidebarLink>

                    <SidebarLink
                        href="/admin/settings"
                        label="Settings"
                        activeMatch={(p) => p.startsWith('/admin/settings')}
                        icon={<SettingsIcon collapsed={collapsed} />}
                    >
                        Settings
                    </SidebarLink>

                    <KitchenSinkNavGroup collapsed={collapsed} />
                </nav>
            </aside>
        </>
    );
}
