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

function AboutIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"
            />
        </svg>
    );
}

function PortalIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z"
            />
        </svg>
    );
}

function TicketIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z"
            />
        </svg>
    );
}

function ReportIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"
            />
        </svg>
    );
}

function KnowledgeIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"
            />
        </svg>
    );
}

function AssetIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21"
            />
        </svg>
    );
}

function SurveyIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08M15.75 18H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5A3.375 3.375 0 006.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0015 2.25h-1.5a2.251 2.251 0 00-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 00-9-9z"
            />
        </svg>
    );
}

function GavelIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 01-2.031.352 5.989 5.989 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971z"
            />
        </svg>
    );
}

function InboxIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H6.911a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661z"
            />
        </svg>
    );
}

function InspectIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z M15 12a3 3 0 11-6 0 3 3 0 016 0z"
            />
        </svg>
    );
}

function TaxIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185zM9.75 9h.008v.008H9.75V9zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 4.5h.008v.008h-.008V13.5zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"
            />
        </svg>
    );
}

function LeaseIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"
            />
        </svg>
    );
}

function GlobeIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"
            />
        </svg>
    );
}

function ComplaintIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
            />
        </svg>
    );
}

function MapIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"
            />
        </svg>
    );
}

function TitleIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"
            />
        </svg>
    );
}

function AcquisitionIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z"
            />
        </svg>
    );
}

function AdminIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"
            />
        </svg>
    );
}

function WorkflowIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"
            />
        </svg>
    );
}

function DocumentIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
            />
        </svg>
    );
}

function BellIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"
            />
        </svg>
    );
}

function AuditIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"
            />
        </svg>
    );
}

function AccessIcon({ collapsed }: { collapsed: boolean }) {
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
                d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"
            />
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
    permissions,
}: {
    open: boolean;
    onClose: () => void;
    siteTitle?: string;
    // Only decides what's shown; the API enforces access on its own.
    permissions: string[];
}) {
    const can = (permission: string) => permissions.includes(permission);
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

                    {can('dsh.dashboard.lihat') && (
                        <SidebarLink
                            href="/admin/dsh/inbox"
                            label="Inbox Tugasan"
                            activeMatch={(p) => p.startsWith('/admin/dsh')}
                            icon={<InboxIcon collapsed={collapsed} />}
                        >
                            Inbox Tugasan
                        </SidebarLink>
                    )}

                    {can('rbc.pengguna.lihat') && (
                        <SidebarLink
                            href="/admin/users"
                            label="Users"
                            activeMatch={(p) => p.startsWith('/admin/users')}
                            icon={<UsersIcon collapsed={collapsed} />}
                        >
                            Users
                        </SidebarLink>
                    )}

                    {can('rbc.peranan.lihat') && (
                        <SidebarLink
                            href="/admin/rbc/peranan"
                            label="Capaian"
                            activeMatch={(p) => p.startsWith('/admin/rbc')}
                            icon={<AccessIcon collapsed={collapsed} />}
                        >
                            Capaian
                        </SidebarLink>
                    )}

                    {/* settings.manage = RBC alias of rbc.pentadbiran.akses, which the Settings API requires */}
                    {can('settings.manage') && (
                        <SidebarLink
                            href="/admin/settings"
                            label="Settings"
                            activeMatch={(p) => p.startsWith('/admin/settings')}
                            icon={<SettingsIcon collapsed={collapsed} />}
                        >
                            Settings
                        </SidebarLink>
                    )}

                    {can('gis.peta.lihat') && (
                        <SidebarLink
                            href="/admin/gis"
                            label="Peta GIS"
                            activeMatch={(p) => p.startsWith('/admin/gis')}
                            icon={<GlobeIcon collapsed={collapsed} />}
                        >
                            Peta GIS
                        </SidebarLink>
                    )}

                    {can('pro.lot.lihat') && (
                        <SidebarLink
                            href="/admin/pro"
                            label="Profil Hartanah"
                            activeMatch={(p) => p.startsWith('/admin/pro')}
                            icon={<MapIcon collapsed={collapsed} />}
                        >
                            Profil Hartanah
                        </SidebarLink>
                    )}

                    {can('hkm.hak_milik.lihat') && (
                        <SidebarLink
                            href="/admin/hkm/hak-milik"
                            label="Hak Milik Tanah"
                            activeMatch={(p) => p.startsWith('/admin/hkm')}
                            icon={<TitleIcon collapsed={collapsed} />}
                        >
                            Hak Milik Tanah
                        </SidebarLink>
                    )}

                    {can('per.kes.lihat') && (
                        <SidebarLink
                            href="/admin/per"
                            label="Rekod Pemerolehan"
                            activeMatch={(p) => p.startsWith('/admin/per')}
                            icon={<AcquisitionIcon collapsed={collapsed} />}
                        >
                            Rekod Pemerolehan
                        </SidebarLink>
                    )}

                    {can('pjk.kontrak.lihat') && (
                        <SidebarLink
                            href="/admin/pjk/kontrak"
                            label="Pajakan & Kontrak"
                            activeMatch={(p) => p.startsWith('/admin/pjk')}
                            icon={<LeaseIcon collapsed={collapsed} />}
                        >
                            Pajakan & Kontrak
                        </SidebarLink>
                    )}

                    {can('cki.bil.lihat') && (
                        <SidebarLink
                            href="/admin/cki/ringkasan"
                            label="Cukai Tanah"
                            activeMatch={(p) => p.startsWith('/admin/cki')}
                            icon={<TaxIcon collapsed={collapsed} />}
                        >
                            Cukai Tanah
                        </SidebarLink>
                    )}

                    {can('ptn.lawatan.lihat') && (
                        <SidebarLink
                            href="/admin/ptn"
                            label="Pemantauan Tanah"
                            activeMatch={(p) => p.startsWith('/admin/ptn')}
                            icon={<InspectIcon collapsed={collapsed} />}
                        >
                            Pemantauan Tanah
                        </SidebarLink>
                    )}

                    {can('ast.aset.lihat') && (
                        <SidebarLink
                            href="/admin/ast"
                            label="Aset Fizikal"
                            activeMatch={(p) => p.startsWith('/admin/ast')}
                            icon={<AssetIcon collapsed={collapsed} />}
                        >
                            Aset Fizikal
                        </SidebarLink>
                    )}

                    {can('tsu.urusan.lihat') && (
                        <SidebarLink
                            href="/admin/tsu/urusan"
                            label="Tukar Syarat & Ukur"
                            activeMatch={(p) => p.startsWith('/admin/tsu')}
                            icon={<SurveyIcon collapsed={collapsed} />}
                        >
                            Tukar Syarat & Ukur
                        </SidebarLink>
                    )}

                    {can('kes.kes.lihat') && (
                        <SidebarLink
                            href="/admin/kes/kes"
                            label="Kes Mahkamah"
                            activeMatch={(p) => p.startsWith('/admin/kes')}
                            icon={<GavelIcon collapsed={collapsed} />}
                        >
                            Kes Mahkamah
                        </SidebarLink>
                    )}

                    {can('adu.aduan.lihat') && (
                        <SidebarLink
                            href="/admin/adu"
                            label="Aduan & Isu Tanah"
                            activeMatch={(p) => p.startsWith('/admin/adu')}
                            icon={<ComplaintIcon collapsed={collapsed} />}
                        >
                            Aduan & Isu Tanah
                        </SidebarLink>
                    )}

                    {can('lap.laporan.lihat') && (
                        <SidebarLink
                            href="/admin/lap"
                            label="Laporan & Analitik"
                            activeMatch={(p) => p.startsWith('/admin/lap')}
                            icon={<ReportIcon collapsed={collapsed} />}
                        >
                            Laporan & Analitik
                        </SidebarLink>
                    )}

                    {can('pws.pemantauan.lihat') && (
                        <SidebarLink
                            href="/admin/pws"
                            label="Portal Wakaf"
                            activeMatch={(p) => p.startsWith('/admin/pws')}
                            icon={<PortalIcon collapsed={collapsed} />}
                        >
                            Portal Wakaf
                        </SidebarLink>
                    )}

                    {can('tkt.tiket.lihat') && (
                        <SidebarLink
                            href="/admin/tkt/tiket"
                            label="Tiket Sokongan"
                            activeMatch={(p) => p.startsWith('/admin/tkt')}
                            icon={<TicketIcon collapsed={collapsed} />}
                        >
                            Tiket Sokongan
                        </SidebarLink>
                    )}

                    {can('kba.soalan.lihat') && (
                        <SidebarLink
                            href="/admin/kba"
                            label="Pangkalan Pengetahuan"
                            activeMatch={(p) => p.startsWith('/admin/kba')}
                            icon={<KnowledgeIcon collapsed={collapsed} />}
                        >
                            Pangkalan Pengetahuan
                        </SidebarLink>
                    )}

                    {can('wfe.tugasan.lihat') && (
                        <SidebarLink
                            href="/admin/wfe/tugasan"
                            label="Workflow"
                            activeMatch={(p) => p.startsWith('/admin/wfe')}
                            icon={<WorkflowIcon collapsed={collapsed} />}
                        >
                            Workflow
                        </SidebarLink>
                    )}

                    {can('dms.dokumen.lihat') && (
                        <SidebarLink
                            href="/admin/dms"
                            label="Dokumen Digital"
                            activeMatch={(p) => p.startsWith('/admin/dms')}
                            icon={<DocumentIcon collapsed={collapsed} />}
                        >
                            Dokumen Digital
                        </SidebarLink>
                    )}

                    <SidebarLink
                        href="/admin/notifikasi"
                        label="Notifikasi"
                        activeMatch={(p) => p.startsWith('/admin/notifikasi')}
                        icon={<BellIcon collapsed={collapsed} />}
                    >
                        Notifikasi
                    </SidebarLink>

                    {can('aud.rekod.lihat') && (
                        <SidebarLink
                            href="/admin/aud"
                            label="Audit Trail"
                            activeMatch={(p) => p.startsWith('/admin/aud')}
                            icon={<AuditIcon collapsed={collapsed} />}
                        >
                            Audit Trail
                        </SidebarLink>
                    )}

                    {can('adm.parameter.lihat') && (
                        <SidebarLink
                            href="/admin/pentadbiran"
                            label="Pentadbiran Sistem"
                            activeMatch={(p) => p.startsWith('/admin/pentadbiran')}
                            icon={<AdminIcon collapsed={collapsed} />}
                        >
                            Pentadbiran Sistem
                        </SidebarLink>
                    )}

                    <KitchenSinkNavGroup collapsed={collapsed} />

                    <SidebarLink
                        href="/admin/about"
                        label="About"
                        activeMatch={(p) => p.startsWith('/admin/about')}
                        icon={<AboutIcon collapsed={collapsed} />}
                    >
                        About
                    </SidebarLink>
                </nav>
            </aside>
        </>
    );
}
