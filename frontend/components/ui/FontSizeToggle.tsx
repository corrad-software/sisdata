'use client';

import { useEffect, useState } from 'react';
import { Dropdown } from './Dropdown';

const SIZES: Record<string, string> = { normal: 'Lalai', large: 'Besar', xlarge: 'Sangat Besar' };

export function FontSizeToggle() {
    const [size, setSizeState] = useState('normal');

    useEffect(() => {
        // eslint-disable-next-line react-hooks/set-state-in-effect -- localStorage is SSR-unsafe, see CookiesBanner
        setSizeState(localStorage.getItem('fontSize') || 'normal');
    }, []);

    function setSize(s: string) {
        setSizeState(s);
        localStorage.setItem('fontSize', s);
        document.documentElement.classList.remove('font-size-large', 'font-size-xlarge');
        if (s !== 'normal') document.documentElement.classList.add(`font-size-${s}`);
    }

    return (
        <Dropdown
            align="right"
            width="w-48"
            contentClassName="bg-bg-white"
            trigger={
                <button
                    type="button"
                    className="inline-flex items-center justify-center rounded-md p-2 text-txt-black-500 hover:text-txt-black-900 hover:bg-bg-washed focus:outline-none transition ease-in-out duration-150"
                    aria-label="Saiz teks" title="Saiz teks"
                >
                    <span className="flex h-5 w-5 items-center justify-center text-sm font-bold leading-none">Aa</span>
                </button>
            }
        >
            {Object.entries(SIZES).map(([value, label]) => (
                <button
                    key={value}
                    type="button"
                    onClick={() => setSize(value)}
                    className="flex w-full items-center justify-between px-4 py-2 text-start text-body-sm leading-5 text-txt-black-700 hover:bg-bg-washed focus:outline-none focus:bg-bg-washed transition duration-150 ease-in-out"
                    aria-current={size === value}
                >
                    <span>{label}</span>
                    {size === value && (
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
