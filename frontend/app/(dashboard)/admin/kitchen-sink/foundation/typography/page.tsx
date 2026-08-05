import { PageContainer } from '@/components/PageContainer';

const HEADINGS = [
    { className: 'text-heading-xl', name: 'heading-xl', size: '60px / 72px' },
    { className: 'text-heading-lg', name: 'heading-lg', size: '48px / 60px' },
    { className: 'text-heading-md', name: 'heading-md', size: '36px / 44px' },
    { className: 'text-heading-sm', name: 'heading-sm', size: '30px / 38px' },
    { className: 'text-heading-xs', name: 'heading-xs', size: '24px / 32px' },
    { className: 'text-heading-2xs', name: 'heading-2xs', size: '20px / 28px' },
];

const BODY = [
    { className: 'text-body-xl', name: 'body-xl', size: '20px / 30px' },
    { className: 'text-body-lg', name: 'body-lg', size: '18px / 26px' },
    { className: 'text-body-md', name: 'body-md', size: '16px / 24px' },
    { className: 'text-body-sm', name: 'body-sm', size: '14px / 20px' },
    { className: 'text-body-xs', name: 'body-xs', size: '12px / 18px' },
];

export default function TypographyPage() {
    return (
        <PageContainer className="space-y-8">
            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-6">
                <div>
                    <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Font Families</h3>
                    <p className="text-body-sm text-txt-black-500">
                        Three families cover every use case: headings, body copy, and monospace/code.
                    </p>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div className="rounded-md border border-otl-gray-200 p-4">
                        <p className="mb-2 text-body-xs font-medium uppercase tracking-wide text-txt-black-500">
                            font-heading — Poppins
                        </p>
                        <p className="font-heading text-heading-xs text-txt-black-900">Aa Bb Cc 123</p>
                    </div>
                    <div className="rounded-md border border-otl-gray-200 p-4">
                        <p className="mb-2 text-body-xs font-medium uppercase tracking-wide text-txt-black-500">
                            font-body — Inter
                        </p>
                        <p className="font-body text-heading-xs text-txt-black-900">Aa Bb Cc 123</p>
                    </div>
                    <div className="rounded-md border border-otl-gray-200 p-4">
                        <p className="mb-2 text-body-xs font-medium uppercase tracking-wide text-txt-black-500">
                            font-mono — Roboto Mono
                        </p>
                        <p className="font-mono text-heading-xs text-txt-black-900">Aa Bb Cc 123</p>
                    </div>
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <div>
                    <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Heading Scale</h3>
                    <p className="text-body-sm text-txt-black-500">
                        Used with font-heading. Every page title and section heading in this app uses one of these.
                    </p>
                </div>

                <div className="divide-y divide-otl-gray-200">
                    {HEADINGS.map((item) => (
                        <div key={item.name} className="flex flex-col gap-1 py-3 sm:flex-row sm:items-baseline sm:gap-4">
                            <p className="w-32 flex-shrink-0 font-mono text-body-xs text-txt-black-500">{item.name}</p>
                            <p className={`${item.className} font-heading font-semibold text-txt-black-900`}>
                                The quick brown fox
                            </p>
                            <p className="ms-auto flex-shrink-0 font-mono text-body-xs text-txt-black-500">{item.size}</p>
                        </div>
                    ))}
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <div>
                    <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Body Scale</h3>
                    <p className="text-body-sm text-txt-black-500">
                        Used with font-body. body-sm is the default size for most UI text in this app.
                    </p>
                </div>

                <div className="divide-y divide-otl-gray-200">
                    {BODY.map((item) => (
                        <div key={item.name} className="flex flex-col gap-1 py-3 sm:flex-row sm:items-baseline sm:gap-4">
                            <p className="w-32 flex-shrink-0 font-mono text-body-xs text-txt-black-500">{item.name}</p>
                            <p className={`${item.className} font-body text-txt-black-900`}>
                                The quick brown fox jumps over the lazy dog.
                            </p>
                            <p className="ms-auto flex-shrink-0 font-mono text-body-xs text-txt-black-500">{item.size}</p>
                        </div>
                    ))}
                </div>
            </section>
        </PageContainer>
    );
}
