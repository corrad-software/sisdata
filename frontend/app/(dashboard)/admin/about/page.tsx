'use client';

import { useState } from 'react';
import { PageContainer } from '@/components/PageContainer';
import { PageHeader } from '@/components/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Timeline } from '@/components/ui/Timeline';

const TABS: Record<string, string> = { stack: 'Stack', changelog: 'Changelog' };

interface TechItem {
    name: string;
    version: string;
    description: string;
}

const BACKEND_STACK: TechItem[] = [
    { name: 'PHP', version: '^8.3', description: 'Server-side language' },
    { name: 'Laravel Framework', version: '^13.0', description: 'Backend API framework' },
    { name: 'Laravel Sanctum', version: '^4.3', description: 'SPA cookie-based authentication' },
    { name: 'Laravel Tinker', version: '^3.0', description: 'REPL for the application' },
    { name: 'PHPUnit', version: '^12.0', description: 'Backend test suite' },
    { name: 'Laravel Pint', version: '^1.24', description: 'Code style fixer' },
];

const DATABASE_STACK: TechItem[] = [
    { name: 'SQLite', version: '3.51.2', description: 'Application database (config/database.php default connection)' },
];

const FRONTEND_STACK: TechItem[] = [
    { name: 'Next.js', version: '16.3.0', description: 'React framework (App Router)' },
    { name: 'React', version: '19.2.8', description: 'UI library' },
    { name: 'TypeScript', version: '^5.9.3', description: 'Static typing' },
    { name: 'Tailwind CSS', version: '^3.4.19', description: 'Utility-first styling' },
    { name: '@govtechmy/myds-style', version: '^1.0.0', description: 'Malaysian Government Design System (MYDS) preset' },
    { name: 'TanStack React Query', version: '^5.101.4', description: 'Server state management' },
    { name: 'Zustand', version: '^5.0.14', description: 'UI state management (sidebar, etc.)' },
    { name: 'Sonner', version: '^2.0.7', description: 'Toast notifications' },
];

const TOOLING_STACK: TechItem[] = [
    { name: 'Playwright', version: '^1.62.1', description: 'End-to-end test suite' },
    { name: 'ESLint', version: '^9.39.5', description: 'Frontend linting' },
];

const CHANGELOG = [
    {
        title: 'Blade retirement & platform upgrade',
        time: '2026-08-05',
        description:
            'Retired the Blade/Livewire frontend entirely — Next.js is now the sole UI. Upgraded to Laravel 13, PHPUnit 12, and Laravel Tinker 3, and added Playwright end-to-end test coverage for authentication and settings.',
    },
    {
        title: 'Laravel API + Next.js frontend',
        time: '2026-08-04',
        description:
            'Added a Laravel API backend with Sanctum SPA authentication and role-gated admin routes, and the Next.js frontend running alongside the existing Blade app.',
        color: 'primary' as const,
    },
    {
        title: 'MYDS design system build-out',
        time: '2026-07-29',
        description: 'Built out the admin UI, the Kitchen Sink component library, i18n, and notifications on the original Blade/Livewire app.',
        color: 'primary' as const,
    },
    {
        title: 'Initial commit',
        time: '2026-07-28',
        description: 'MYDS Laravel POC starter.',
        color: 'primary' as const,
    },
];

function StackSection({ title, description, items }: { title: string; description: string; items: TechItem[] }) {
    return (
        <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <div>
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">{title}</h3>
                <p className="text-body-sm text-txt-black-500">{description}</p>
            </div>

            <div className="divide-y divide-otl-divider">
                {items.map((item) => (
                    <div key={item.name} className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                        <div className="min-w-0">
                            <p className="text-body-sm font-medium text-txt-black-900">{item.name}</p>
                            <p className="text-body-xs text-txt-black-500">{item.description}</p>
                        </div>
                        <Badge variant="info" shape="tag">
                            {item.version}
                        </Badge>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function AboutPage() {
    const [tab, setTab] = useState<keyof typeof TABS>('stack');

    return (
        <>
            <PageHeader title="About" breadcrumbs={[{ label: 'About' }]} />

            <div className="bg-bg-white border-b border-otl-divider">
                <nav className="flex flex-nowrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto">
                    {Object.entries(TABS).map(([key, label]) => {
                        const active = key === tab;

                        return (
                            <button
                                key={key}
                                type="button"
                                onClick={() => setTab(key)}
                                className={`whitespace-nowrap border-b-2 py-3 text-body-sm font-medium ${
                                    active
                                        ? 'border-primary-600 text-primary-600'
                                        : 'border-transparent text-txt-black-500 hover:text-txt-black-900 hover:border-otl-gray-300'
                                }`}
                            >
                                {label}
                            </button>
                        );
                    })}
                </nav>
            </div>

            <PageContainer>
                {tab === 'stack' && (
                    <>
                        <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6">
                            <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">
                                MYDS-Laravel
                            </h3>
                            <p className="mt-1 text-body-sm text-txt-black-500">
                                A Laravel API backend paired with a Next.js admin frontend, styled with the Malaysian
                                Government Design System (MYDS). Laravel serves as a pure JSON API — authentication,
                                business logic, and data — while Next.js renders the entire UI.
                            </p>
                        </div>

                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <StackSection
                                title="Backend"
                                description="PHP API server — authentication, data, and business logic."
                                items={BACKEND_STACK}
                            />

                            <StackSection
                                title="Frontend"
                                description="Next.js admin UI — the sole interface served to users."
                                items={FRONTEND_STACK}
                            />

                            <StackSection title="Database" description="Persistence layer." items={DATABASE_STACK} />

                            <StackSection title="Tooling" description="Testing and code quality." items={TOOLING_STACK} />
                        </div>
                    </>
                )}

                {tab === 'changelog' && (
                    <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6">
                        <Timeline items={CHANGELOG} />
                    </div>
                )}
            </PageContainer>
        </>
    );
}
