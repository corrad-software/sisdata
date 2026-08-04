'use client';

import { createContext, useContext, useState } from 'react';
import type { ReactNode } from 'react';

const TabsContext = createContext<string | null>(null);

interface TabsProps {
    tabs: Record<string, string>;
    defaultTab?: string;
    children: ReactNode;
    className?: string;
}

export function Tabs({ tabs, defaultTab, children, className }: TabsProps) {
    const keys = Object.keys(tabs);
    const [active, setActive] = useState(defaultTab ?? keys[0]);

    return (
        <TabsContext.Provider value={active}>
            <div className={className}>
                <div className="border-b border-otl-divider">
                    <nav className="flex flex-wrap gap-6 -mb-px">
                        {keys.map((key) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => setActive(key)}
                                className={`whitespace-nowrap border-b-2 py-3 text-body-sm font-medium ${
                                    active === key
                                        ? 'border-primary-600 text-primary-600'
                                        : 'border-transparent text-txt-black-500 hover:text-txt-black-900 hover:border-otl-gray-300'
                                }`}
                            >
                                {tabs[key]}
                            </button>
                        ))}
                    </nav>
                </div>

                <div className="pt-4">{children}</div>
            </div>
        </TabsContext.Provider>
    );
}

export function TabPanel({ value, children }: { value: string; children: ReactNode }) {
    const active = useContext(TabsContext);
    if (active !== value) return null;
    return <>{children}</>;
}
