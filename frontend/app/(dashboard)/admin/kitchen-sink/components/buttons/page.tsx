import { Button, LinkButton } from '@/components/ui/Button';

export default function ButtonsPage() {
    return (
        <div className="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Button</h3>

                <div className="space-y-3">
                    <div className="flex flex-wrap items-center gap-3">
                        <Button variant="primary">Primary</Button>
                        <Button variant="secondary">Secondary</Button>
                        <Button variant="danger">Danger</Button>
                        <Button variant="outline">Outline</Button>
                        <Button variant="primary" disabled>
                            Disabled
                        </Button>
                        <LinkButton variant="primary" href="#">
                            As link
                        </LinkButton>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button variant="primary" size="sm">
                            Small
                        </Button>
                        <Button variant="primary" size="md">
                            Medium
                        </Button>
                        <Button variant="primary" size="lg">
                            Large
                        </Button>
                    </div>
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Backlink</h3>
                <p className="text-body-sm text-txt-black-500">
                    A lightweight link back to the previous page or listing — used above a page title instead of a
                    full breadcrumb trail.
                </p>

                <a
                    href="#"
                    className="inline-flex items-center gap-1.5 text-body-sm font-medium text-primary-600 hover:text-primary-700 hover:underline"
                >
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    Back to Users
                </a>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Skip Link</h3>
                <p className="text-body-sm text-txt-black-500">
                    An accessibility link, hidden until keyboard-focused, that jumps straight to the main content —
                    try pressing Tab from inside this card.
                </p>

                <div tabIndex={-1} className="relative rounded-md border border-dashed border-otl-gray-300 p-4">
                    <a
                        href="#ks-skip-target"
                        className="absolute left-4 top-4 -translate-y-16 rounded-md bg-primary-600 px-4 py-2 text-body-sm font-medium text-white transition-transform focus:translate-y-0 focus:outline-none focus:ring-2 focus:ring-fr-primary"
                    >
                        Skip to main content
                    </a>
                    <p className="text-body-sm text-txt-black-500">Tab to reveal the skip link above.</p>
                    <p id="ks-skip-target" tabIndex={-1} className="mt-3 text-body-sm font-medium text-txt-black-900">
                        Main content target
                    </p>
                </div>
            </section>
        </div>
    );
}
