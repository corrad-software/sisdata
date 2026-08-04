'use client';

import { useState } from 'react';

interface AccordionItem {
    title: string;
    content: string;
}

export function Accordion({ items, className }: { items: AccordionItem[]; className?: string }) {
    const [openIndex, setOpenIndex] = useState<number | null>(null);

    return (
        <div className={`divide-y divide-otl-gray-200 rounded-md border border-otl-gray-200 bg-bg-white ${className ?? ''}`}>
            {items.map((item, index) => {
                const open = openIndex === index;

                return (
                    <div key={item.title}>
                        <button
                            type="button"
                            onClick={() => setOpenIndex(open ? null : index)}
                            aria-expanded={open}
                            className="flex w-full items-center justify-between gap-3 px-4 py-3 text-start text-body-sm font-medium text-txt-black-900 hover:bg-bg-washed"
                        >
                            <span>{item.title}</span>
                            <svg
                                className={`h-4 w-4 flex-shrink-0 transition-transform ${open ? 'rotate-180' : ''}`}
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                strokeWidth={2}
                            >
                                <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        {open && <div className="px-4 pb-3 text-body-sm text-txt-black-500">{item.content}</div>}
                    </div>
                );
            })}
        </div>
    );
}
