'use client';

import { useState } from 'react';

const RADII = [
    { className: 'rounded-xs', name: 'rounded-xs', px: '4px' },
    { className: 'rounded-sm', name: 'rounded-sm', px: '6px' },
    { className: 'rounded-md', name: 'rounded-md', px: '8px' },
    { className: 'rounded-lg', name: 'rounded-lg', px: '12px' },
    { className: 'rounded-xl', name: 'rounded-xl', px: '14px' },
    { className: 'rounded-full', name: 'rounded-full', px: '9999px' },
];

const SHADOWS = [
    { className: 'shadow-button', name: 'shadow-button' },
    { className: 'shadow-card', name: 'shadow-card' },
    { className: 'shadow-context-menu', name: 'shadow-context-menu' },
];

const ICON_SIZES = [
    { className: 'h-4 w-4', label: '16px' },
    { className: 'h-5 w-5', label: '20px (base)' },
    { className: 'h-6 w-6', label: '24px' },
    { className: 'h-8 w-8', label: '32px' },
    { className: 'h-[42px] w-[42px]', label: '42px' },
];

export default function StylePage() {
    const [hover, setHover] = useState(false);
    // Replaying a CSS keyframe animation on click is done by remounting the
    // animated element via a changing `key`, rather than toggling a class.
    const [expireKey, setExpireKey] = useState(0);
    const [slideUpKey, setSlideUpKey] = useState(0);
    const [slideDownKey, setSlideDownKey] = useState(0);

    return (
        <div className="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Radius Scale</h3>

                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                    {RADII.map((item) => (
                        <div key={item.name} className="text-center">
                            <div className={`${item.className} h-16 w-full border-2 border-primary-600 bg-bg-primary-50`} />
                            <p className="mt-2 font-mono text-body-xs text-txt-black-500">{item.name}</p>
                            <p className="font-mono text-body-xs text-txt-black-500">{item.px}</p>
                        </div>
                    ))}
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Shadow Scale</h3>
                <p className="text-body-sm text-txt-black-500">
                    Only three named shadows exist — there is no sm–xl scale like default Tailwind.
                </p>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    {SHADOWS.map((item) => (
                        <div key={item.name} className={`rounded-lg bg-bg-white p-4 text-center ${item.className}`}>
                            <p className="font-mono text-body-xs text-txt-black-500">{item.name}</p>
                        </div>
                    ))}
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-6">
                <div>
                    <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Motion</h3>
                    <p className="text-body-sm text-txt-black-500">
                        Named durations (200/400/600ms) and a few of the built-in keyframe animations. Click Replay to
                        trigger each again.
                    </p>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="rounded-md border border-otl-gray-200 p-4">
                        <p className="mb-2 text-body-xs font-medium text-txt-black-500">duration-200 / 400 / 600</p>
                        <div className="flex items-center gap-2">
                            <div
                                className={`h-8 w-8 rounded-md bg-primary-600 transition-transform duration-200 ${hover ? '-translate-y-1' : ''}`}
                                onMouseEnter={() => setHover(true)}
                                onMouseLeave={() => setHover(false)}
                            />
                        </div>
                        <p className="mt-2 text-body-xs text-txt-black-500">Hover the square (duration-200).</p>
                    </div>

                    <div className="rounded-md border border-otl-gray-200 p-4 overflow-hidden">
                        <p className="mb-2 text-body-xs font-medium text-txt-black-500">animate-shimmer</p>
                        <div className="h-8 w-full rounded-md bg-gradient-to-r from-bg-washed via-primary-100 to-bg-washed bg-[length:200%_100%] animate-shimmer" />
                    </div>

                    <div className="rounded-md border border-otl-gray-200 p-4 space-y-2">
                        <p className="text-body-xs font-medium text-txt-black-500">animate-expire</p>
                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-bg-washed">
                            <div key={expireKey} className="h-full bg-primary-600 animate-expire" />
                        </div>
                        <button
                            type="button"
                            className="text-body-xs font-medium text-primary-600 hover:text-primary-700"
                            onClick={() => setExpireKey((k) => k + 1)}
                        >
                            Replay
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="rounded-md border border-otl-gray-200 p-4 space-y-2 overflow-hidden">
                        <p className="text-body-xs font-medium text-txt-black-500">animate-slide-up</p>
                        <div className="h-10 flex items-end overflow-hidden">
                            <div key={slideUpKey} className="h-8 w-full rounded-md bg-primary-600 animate-slide-up" />
                        </div>
                        <button
                            type="button"
                            className="text-body-xs font-medium text-primary-600 hover:text-primary-700"
                            onClick={() => setSlideUpKey((k) => k + 1)}
                        >
                            Replay
                        </button>
                    </div>

                    <div className="rounded-md border border-otl-gray-200 p-4 space-y-2 overflow-hidden">
                        <p className="text-body-xs font-medium text-txt-black-500">animate-slide-down</p>
                        <div className="h-10 flex items-start overflow-hidden">
                            <div key={slideDownKey} className="h-8 w-full rounded-md bg-primary-600 animate-slide-down" />
                        </div>
                        <button
                            type="button"
                            className="text-body-xs font-medium text-primary-600 hover:text-primary-700"
                            onClick={() => setSlideDownKey((k) => k + 1)}
                        >
                            Replay
                        </button>
                    </div>
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <div>
                    <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Icon Sizes</h3>
                    <p className="text-body-sm text-txt-black-500">
                        Icons are drawn on a 20×20 base grid with a 1.5px stroke, then scaled to these recommended sizes.
                    </p>
                </div>

                <div className="flex flex-wrap items-end gap-8">
                    {ICON_SIZES.map((item) => (
                        <div key={item.label} className="text-center">
                            <svg
                                className={`${item.className} text-primary-600`}
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                strokeWidth={1.5}
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            <p className="mt-2 font-mono text-body-xs text-txt-black-500">{item.label}</p>
                        </div>
                    ))}
                </div>
            </section>
        </div>
    );
}
