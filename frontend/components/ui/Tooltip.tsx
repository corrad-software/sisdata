'use client';

import { useState } from 'react';
import type { ReactNode } from 'react';

export function Tooltip({ text, children }: { text: string; children: ReactNode }) {
    const [show, setShow] = useState(false);

    return (
        <span className="relative inline-flex">
            <span
                tabIndex={0}
                onMouseEnter={() => setShow(true)}
                onMouseLeave={() => setShow(false)}
                onFocus={() => setShow(true)}
                onBlur={() => setShow(false)}
            >
                {children}
            </span>

            {show && (
                <span className="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-txt-black-900 px-2.5 py-1.5 text-body-xs text-white shadow-card">
                    {text}
                    <span className="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-txt-black-900" />
                </span>
            )}
        </span>
    );
}
