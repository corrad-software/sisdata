'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { ReactNode } from 'react';
import { Breadcrumb } from '@/components/Breadcrumb';

const TAB_GROUPS: Record<string, Record<string, string>> = {
    foundation: {
        colour: 'Colour',
        typography: 'Typography',
        'layout-spacing': 'Layout & Spacing',
        style: 'Style & Icon',
    },
    components: {
        buttons: 'Buttons',
        forms: 'Forms',
        'data-display': 'Data Display',
        feedback: 'Feedback',
        content: 'Content',
        navigation: 'Navigation',
        overlays: 'Overlays',
        progress: 'Progress',
    },
    'data-visual': {
        dashboard: 'Dashboard',
        'stats-view': 'Stats View',
    },
    'sample-pages': {
        login: 'Login',
        register: 'Register',
        'not-found': '404 Page',
        'empty-state': 'Empty State',
    },
};

const GROUP_LABELS: Record<string, string> = {
    foundation: 'Foundation',
    components: 'Components',
    'data-visual': 'Data Visual',
    'sample-pages': 'Sample Pages',
};

export default function KitchenSinkLayout({ children }: { children: ReactNode }) {
    const pathname = usePathname();
    const segments = pathname.split('/').filter(Boolean);
    const group = segments[2] ?? 'components';
    const activePage = segments[3] ?? '';
    const tabs = TAB_GROUPS[group] ?? {};
    const pageLabel = tabs[activePage];
    const groupFirstKey = Object.keys(tabs)[0];

    return (
        <>
            <div className="bg-bg-white border-b border-otl-divider px-4 sm:px-6 py-4 space-y-3">
                <Breadcrumb
                    items={[
                        { label: 'Kitchen Sink', href: '/admin/kitchen-sink/components/buttons' },
                        pageLabel
                            ? { label: GROUP_LABELS[group], href: `/admin/kitchen-sink/${group}/${groupFirstKey}` }
                            : { label: GROUP_LABELS[group] },
                        ...(pageLabel ? [{ label: pageLabel }] : []),
                    ]}
                />
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">
                    {pageLabel ?? GROUP_LABELS[group]}
                </h2>
            </div>

            <div className="bg-bg-white border-b border-otl-divider">
                <nav className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto">
                    {Object.entries(tabs).map(([key, label]) => {
                        const active = key === activePage;

                        return (
                            <Link
                                key={key}
                                href={`/admin/kitchen-sink/${group}/${key}`}
                                className={`whitespace-nowrap border-b-2 py-3 text-body-sm font-medium ${
                                    active
                                        ? 'border-primary-600 text-primary-600'
                                        : 'border-transparent text-txt-black-500 hover:text-txt-black-900 hover:border-otl-gray-300'
                                }`}
                            >
                                {label}
                            </Link>
                        );
                    })}
                </nav>
            </div>

            {children}
        </>
    );
}
