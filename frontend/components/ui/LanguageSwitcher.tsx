'use client';

import { useState } from 'react';
import { Dropdown } from './Dropdown';

const LOCALES: Record<string, string> = {
    en: 'English',
    ms: 'Bahasa Malaysia',
};

// UI-only: no i18n system is wired up behind this yet, so switching just
// updates the indicator — content stays in English either way.
export function LanguageSwitcher() {
    const [locale, setLocale] = useState('en');

    return (
        <Dropdown
            align="right"
            width="w-48"
            contentClassName="bg-bg-white"
            trigger={
                <button
                    type="button"
                    className="inline-flex items-center gap-1.5 rounded-md border border-otl-gray-300 pl-3 pr-2.5 py-1.5 text-txt-black-700 hover:bg-bg-washed focus:outline-none transition ease-in-out duration-150"
                    aria-label="Language"
                >
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M11.5 3a17 17 0 000 18M12.5 3a17 17 0 010 18"
                        />
                    </svg>
                    <span className="text-body-xs font-semibold uppercase">{locale}</span>
                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>
            }
        >
            {Object.entries(LOCALES).map(([code, label]) => (
                <button
                    key={code}
                    type="button"
                    onClick={() => setLocale(code)}
                    className="flex w-full items-center justify-between px-4 py-2 text-start text-body-sm leading-5 text-txt-black-700 hover:bg-bg-washed focus:outline-none focus:bg-bg-washed transition duration-150 ease-in-out"
                    aria-current={locale === code}
                >
                    <span>{label}</span>
                    {locale === code && (
                        <svg className="h-4 w-4 text-primary-600" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                fillRule="evenodd"
                                clipRule="evenodd"
                                d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                            />
                        </svg>
                    )}
                </button>
            ))}
        </Dropdown>
    );
}
