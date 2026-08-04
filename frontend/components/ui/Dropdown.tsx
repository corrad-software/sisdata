'use client';

import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';

interface DropdownProps {
    trigger: ReactNode;
    children: ReactNode;
    align?: 'left' | 'right';
    width?: string;
    contentClassName?: string;
}

export function Dropdown({ trigger, children, align = 'right', width = 'w-48', contentClassName = 'py-1 bg-bg-white' }: DropdownProps) {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        function handleClick(e: MouseEvent) {
            if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
        }
        document.addEventListener('click', handleClick);
        return () => document.removeEventListener('click', handleClick);
    }, []);

    const alignmentClass = align === 'left' ? 'start-0 origin-top-left' : 'end-0 origin-top-right';

    return (
        <div className="relative" ref={ref}>
            <div onClick={() => setOpen((o) => !o)}>{trigger}</div>

            {open && (
                <div
                    className={`absolute z-50 mt-2 ${width} rounded-md shadow-lg ${alignmentClass}`}
                    onClick={() => setOpen(false)}
                >
                    <div className={`rounded-md ring-1 ring-black ring-opacity-5 ${contentClassName}`}>{children}</div>
                </div>
            )}
        </div>
    );
}

export function DropdownLink({ href = '#', children }: { href?: string; children: ReactNode }) {
    return (
        <a
            href={href}
            className="block w-full px-4 py-2 text-start text-body-sm leading-5 text-txt-black-700 hover:bg-bg-washed focus:outline-none focus:bg-bg-washed transition duration-150 ease-in-out"
        >
            {children}
        </a>
    );
}
