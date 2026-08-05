'use client';

import { useState } from 'react';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Spinner } from '@/components/ui/Spinner';
import { Modal } from '@/components/ui/Modal';
import { Callout } from '@/components/ui/Callout';
import { PhaseBanner } from '@/components/ui/PhaseBanner';
import { CookiesBanner } from '@/components/ui/CookiesBanner';
import { Tooltip } from '@/components/ui/Tooltip';

export default function FeedbackPage() {
    const [dialogOpen, setDialogOpen] = useState(false);

    return (
        <PageContainer className="space-y-8">
            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Alert</h3>

                <div className="space-y-3 max-w-2xl">
                    <Alert type="success">This is a success alert — the action completed.</Alert>
                    <Alert type="error">This is an error alert — something went wrong.</Alert>
                    <Alert type="warning">This is a warning alert — proceed with caution.</Alert>
                    <Alert type="info">This is an info alert — just for your reference.</Alert>
                    <Alert type="info" dismissible>
                        This one is dismissible — try the close button.
                    </Alert>
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Badge</h3>

                <div className="flex flex-wrap gap-3">
                    <Badge variant="success">Active</Badge>
                    <Badge variant="neutral">Inactive</Badge>
                    <Badge variant="danger">Error</Badge>
                    <Badge variant="warning">Warning</Badge>
                    <Badge variant="info">Info</Badge>
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Toast</h3>
                <p className="text-body-sm text-txt-black-500">
                    Transient, auto-dismissing notifications — unlike Alert, which is a persistent inline banner.
                </p>

                <div className="flex flex-wrap gap-3">
                    <Button variant="secondary" size="sm" onClick={() => toast.success('Saved successfully.')}>
                        Show success toast
                    </Button>
                    <Button variant="secondary" size="sm" onClick={() => toast.error('Something went wrong.')}>
                        Show error toast
                    </Button>
                    <Button variant="secondary" size="sm" onClick={() => toast.warning('Please double-check your input.')}>
                        Show warning toast
                    </Button>
                    <Button variant="secondary" size="sm" onClick={() => toast.info('Heads up — just for your reference.')}>
                        Show info toast
                    </Button>
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Spinner</h3>

                <div className="flex flex-wrap items-center gap-6">
                    <Spinner size="sm" className="text-primary-600" />
                    <Spinner size="md" className="text-primary-600" />
                    <Spinner size="lg" className="text-primary-600" />

                    <div className="flex items-center gap-2 rounded-md border border-otl-gray-200 px-3 py-2">
                        <Spinner size="sm" className="text-txt-black-500" />
                        <span className="text-body-sm text-txt-black-500">Loading...</span>
                    </div>
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Alert Dialog</h3>
                <p className="text-body-sm text-txt-black-500">
                    A confirmation-style modal — reuses the shared Modal with a focused, single-decision layout.
                </p>

                <Button variant="danger" onClick={() => setDialogOpen(true)}>
                    Deactivate account
                </Button>

                <Modal open={dialogOpen} onClose={() => setDialogOpen(false)}>
                    <div className="p-6">
                        <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">
                            Deactivate this account?
                        </h2>
                        <p className="mt-1 text-body-sm text-txt-black-500">
                            The user will immediately lose access. You can reactivate the account later.
                        </p>
                        <div className="mt-6 flex justify-end gap-3">
                            <Button variant="secondary" onClick={() => setDialogOpen(false)}>
                                Cancel
                            </Button>
                            <Button variant="danger" onClick={() => setDialogOpen(false)}>
                                Deactivate
                            </Button>
                        </div>
                    </div>
                </Modal>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Callout</h3>
                <p className="text-body-sm text-txt-black-500">
                    A boxed message with an optional title — more prominent than Alert, used for a single important
                    note rather than a page-level status.
                </p>

                <div className="space-y-3 max-w-2xl">
                    <Callout type="info" title="Did you know?">
                        You can bulk-select rows in any table to perform actions on multiple records at once.
                    </Callout>
                    <Callout type="warning" title="Before you continue">
                        This form auto-saves every 30 seconds while you type.
                    </Callout>
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Phase Banner</h3>
                <p className="text-body-sm text-txt-black-500">
                    A small status tag with a note, typically shown at the very top of a page to flag its maturity.
                </p>

                <PhaseBanner phase="Beta">This is a new feature. Your feedback helps us improve it.</PhaseBanner>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Cookies Banner</h3>
                <p className="text-body-sm text-txt-black-500">
                    Shown inline here for the demo (fixed=false) — in real usage it stays fixed to the bottom of the
                    viewport. Choosing Accept or Reject remembers your choice in localStorage.
                </p>

                <CookiesBanner fixed={false} />
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Tooltip</h3>
                <p className="text-body-sm text-txt-black-500">Hover or focus the trigger to reveal a short hint.</p>

                <div className="flex flex-wrap gap-6">
                    <Tooltip text="This action cannot be undone">
                        <Button variant="secondary" size="sm" type="button">
                            Hover me
                        </Button>
                    </Tooltip>

                    <Tooltip text="Read-only field">
                        <svg
                            className="h-5 w-5 cursor-help text-txt-black-500"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            strokeWidth={1.5}
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"
                            />
                        </svg>
                    </Tooltip>
                </div>
            </section>
        </PageContainer>
    );
}
