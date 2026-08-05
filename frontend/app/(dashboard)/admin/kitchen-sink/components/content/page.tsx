import { PageContainer } from '@/components/PageContainer';
import { Accordion } from '@/components/ui/Accordion';
import { Panel } from '@/components/ui/Panel';

const ACCORDION_ITEMS = [
    {
        title: 'What is MYDS?',
        content:
            'The Malaysian Government Design System — a shared set of design tokens, components, and patterns for building consistent government digital services.',
    },
    {
        title: 'Can I use this outside government projects?',
        content:
            'Yes — the design tokens and components in this starter are plain Tailwind/React and have no dependency on any government-specific service.',
    },
    {
        title: 'Where do I find more components?',
        content:
            'See the Components tabs above, and the design.digital.gov.my documentation site for the full official reference.',
    },
];

export default function ContentPage() {
    return (
        <PageContainer className="space-y-8">
            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Accordion</h3>
                <p className="text-body-sm text-txt-black-500">One panel open at a time — click a heading to expand it.</p>

                <Accordion items={ACCORDION_ITEMS} className="max-w-2xl" />
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Details</h3>
                <p className="text-body-sm text-txt-black-500">
                    The native HTML details/summary element, styled to match — works without any JavaScript at all.
                </p>

                <details className="max-w-2xl rounded-md border border-otl-gray-200 bg-bg-white p-4 [&_summary]:cursor-pointer">
                    <summary className="text-body-sm font-medium text-txt-black-900">
                        What data do we store about you?
                    </summary>
                    <p className="mt-2 text-body-sm text-txt-black-500">
                        Your name, email address, and role — nothing else. See our privacy policy for full details.
                    </p>
                </details>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Panel</h3>
                <p className="text-body-sm text-txt-black-500">
                    A simple bordered, optionally titled container — for grouping related content without the weight
                    of a full card section.
                </p>

                <Panel title="Account Summary" className="max-w-2xl">
                    <dl className="grid grid-cols-2 gap-3 text-body-sm">
                        <dt className="text-txt-black-500">Plan</dt>
                        <dd className="text-txt-black-900">Government Standard</dd>
                        <dt className="text-txt-black-500">Seats used</dt>
                        <dd className="text-txt-black-900">12 / 20</dd>
                    </dl>
                </Panel>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Inset Text</h3>
                <p className="text-body-sm text-txt-black-500">
                    A left-border-highlighted block for quoting or setting apart a passage of text within body copy.
                </p>

                <blockquote className="max-w-2xl border-s-4 border-otl-gray-300 ps-4 text-body-sm text-txt-black-700">
                    It will take up to 8 weeks to process your application. Contact us only if you have not heard back
                    after this time.
                </blockquote>
            </section>
        </PageContainer>
    );
}
