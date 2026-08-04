import { BrowserFrame } from '@/components/BrowserFrame';
import { Masthead } from '@/components/Masthead';
import { Footer } from '@/components/Footer';
import { LinkButton } from '@/components/ui/Button';

export default function SampleEmptyStatePage() {
    return (
        <div className="py-8 px-4 sm:px-6 lg:px-8 space-y-4">
            <div>
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Empty State Page</h3>
                <p className="text-body-sm text-txt-black-500">
                    Shown within a realistic page shell — the pattern to reuse whenever a list or table has zero
                    results.
                </p>
            </div>

            <BrowserFrame url="yourapp.test/admin/users" className="max-w-2xl mx-auto">
                <div className="flex min-h-[640px] flex-col bg-bg-washed">
                    <Masthead />

                    <div className="border-b border-otl-divider bg-bg-white px-4 py-4 sm:px-6">
                        <h1 className="font-heading text-heading-xs font-semibold text-txt-black-900">Users</h1>
                    </div>

                    <div className="flex flex-1 items-center justify-center px-4 py-10 sm:px-6">
                        <div className="mx-auto max-w-sm text-center">
                            <svg
                                className="mx-auto h-16 w-16 text-primary-200"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                strokeWidth={1}
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.24A2.25 2.25 0 0017.15 3.75H6.85a2.25 2.25 0 00-2.09 1.49L2.35 13.177a2.25 2.25 0 00-.1.661z"
                                />
                            </svg>

                            <h2 className="mt-4 font-heading text-heading-2xs font-semibold text-txt-black-900">
                                No users yet
                            </h2>
                            <p className="mt-2 text-body-sm text-txt-black-500">
                                Get started by adding your first user.
                            </p>

                            <LinkButton variant="primary" href="#" className="mt-6">
                                + Add User
                            </LinkButton>
                        </div>
                    </div>

                    <Footer />
                </div>
            </BrowserFrame>
        </div>
    );
}
