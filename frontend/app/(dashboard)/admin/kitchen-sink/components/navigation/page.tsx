'use client';

import { useState } from 'react';
import { QuickSearch } from '@/components/ui/QuickSearch';
import { FontSizeToggle } from '@/components/ui/FontSizeToggle';
import { ThemeToggle } from '@/components/ui/ThemeToggle';
import { SidebarLink } from '@/components/SidebarLink';
import { Tabs, TabPanel } from '@/components/ui/Tabs';

function SidebarParentDemo() {
    const [open, setOpen] = useState(true);

    return (
        <div>
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className="flex w-full items-center justify-between gap-3 px-3 py-2 rounded-md text-body-sm font-medium text-primary-100 hover:bg-primary-600 hover:text-white"
            >
                <span>Parent with children</span>
                <svg
                    className={`h-4 w-4 transition-transform ${open ? 'rotate-180' : ''}`}
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    strokeWidth={2}
                >
                    <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>

            {open && (
                <div className="ms-4 mt-1 space-y-1 border-l border-primary-600 ps-3">
                    <SidebarLink href="#">Child item one</SidebarLink>
                    <SidebarLink href="#" activeMatch={() => true}>
                        Child item two
                    </SidebarLink>
                    <SidebarLink href="#">Child item three</SidebarLink>
                </div>
            )}
        </div>
    );
}

export default function NavigationPage() {
    return (
        <div className="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Navbar Utilities</h3>
                <p className="text-body-sm text-txt-black-500">
                    A subset of the controls shown in the app header — quick search, text size, and dark mode.
                </p>

                <div className="flex items-center gap-1">
                    <QuickSearch />
                    <FontSizeToggle />
                    <ThemeToggle />
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Sidebar Link</h3>

                <div className="max-w-xs rounded-md bg-primary-700 p-3 space-y-1">
                    <SidebarLink href="#" activeMatch={() => true}>
                        Active item
                    </SidebarLink>
                    <SidebarLink href="#">Inactive item</SidebarLink>

                    <SidebarParentDemo />
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Tabs</h3>
                <p className="text-body-sm text-txt-black-500">
                    Client-side tabs (no page navigation) — unlike this page&apos;s own tab bar above, which routes
                    between subpages. Tabs can be nested, since each instance tracks its own active tab independently.
                </p>

                <Tabs tabs={{ profile: 'Profile', security: 'Security', notifications: 'Notifications' }}>
                    <TabPanel value="profile">
                        <p className="text-body-sm text-txt-black-700">Profile panel content goes here.</p>
                    </TabPanel>

                    <TabPanel value="security">
                        <div className="space-y-4">
                            <p className="text-body-sm text-txt-black-700">
                                Security panel content, with a nested set of tabs below:
                            </p>

                            <Tabs tabs={{ password: 'Password', sessions: 'Sessions' }} className="max-w-sm">
                                <TabPanel value="password">
                                    <p className="text-body-sm text-txt-black-500">Password sub-panel content.</p>
                                </TabPanel>
                                <TabPanel value="sessions">
                                    <p className="text-body-sm text-txt-black-500">Sessions sub-panel content.</p>
                                </TabPanel>
                            </Tabs>
                        </div>
                    </TabPanel>

                    <TabPanel value="notifications">
                        <p className="text-body-sm text-txt-black-700">Notifications panel content goes here.</p>
                    </TabPanel>
                </Tabs>
            </section>
        </div>
    );
}
