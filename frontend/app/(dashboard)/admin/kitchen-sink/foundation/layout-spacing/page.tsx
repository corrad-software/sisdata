const SPACING_SCALE = [
    { token: '1', w: 'w-1', px: '4px' },
    { token: '2', w: 'w-2', px: '8px' },
    { token: '2.5', w: 'w-2.5', px: '10px' },
    { token: '3', w: 'w-3', px: '12px' },
    { token: '4', w: 'w-4', px: '16px' },
    { token: '4.5', w: 'w-4.5', px: '18px' },
    { token: '5', w: 'w-5', px: '20px' },
    { token: '6', w: 'w-6', px: '24px' },
    { token: '8', w: 'w-8', px: '32px' },
    { token: '10', w: 'w-10', px: '40px' },
    { token: '10.5', w: 'w-10.5', px: '42px' },
    { token: '12', w: 'w-12', px: '48px' },
    { token: '16', w: 'w-16', px: '64px' },
    { token: '20', w: 'w-20', px: '80px' },
    { token: '24', w: 'w-24', px: '96px' },
];

const GRID_CELLS = Array.from({ length: 12 }, (_, i) => i + 1);

export default function LayoutSpacingPage() {
    return (
        <div className="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <div>
                    <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">12-8-4 Grid System</h3>
                    <p className="text-body-sm text-txt-black-500">
                        MYDS uses a responsive column grid: 12 columns on desktop, 8 on tablet, 4 on mobile. Resize your
                        browser to see it change live.
                    </p>
                </div>

                <div className="rounded-md border border-otl-gray-200 bg-bg-washed p-2">
                    <p className="md:hidden text-body-sm font-medium text-primary-600">
                        Mobile — 4 columns, 18px gap, ≤767px
                    </p>
                    <p className="hidden md:inline-block lg:hidden text-body-sm font-medium text-primary-600">
                        Tablet — 8 columns, 24px gap, 768–1023px
                    </p>
                    <p className="hidden lg:inline-block text-body-sm font-medium text-primary-600">
                        Desktop — 12 columns, 24px gap, max-width 1280px, ≥1024px
                    </p>
                </div>

                <div className="max-w-[1280px] mx-auto">
                    <div className="grid grid-cols-4 md:grid-cols-8 lg:grid-cols-12 gap-[18px] md:gap-6">
                        {GRID_CELLS.map((i) => (
                            <div
                                key={i}
                                className="flex h-12 items-center justify-center rounded-md bg-primary-100 text-body-xs font-medium text-primary-700"
                            >
                                {i}
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <div>
                    <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Spacing Scale</h3>
                    <p className="text-body-sm text-txt-black-500">
                        Base 4px scale, with three MYDS-specific additions (2.5, 4.5, 10.5) shown alongside the defaults.
                    </p>
                </div>

                <div className="space-y-2">
                    {SPACING_SCALE.map((item) => (
                        <div key={item.token} className="flex items-center gap-4">
                            <p className="w-16 flex-shrink-0 font-mono text-body-xs text-txt-black-500">{item.token}</p>
                            <div className={`${item.w} h-4 flex-shrink-0 rounded bg-primary-600`} />
                            <p className="font-mono text-body-xs text-txt-black-500">{item.px}</p>
                        </div>
                    ))}
                </div>
            </section>
        </div>
    );
}
