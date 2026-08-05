'use client';

import { useState } from 'react';
import { PageContainer } from '@/components/PageContainer';
import { Stepper } from '@/components/ui/Stepper';
import { Timeline } from '@/components/ui/Timeline';
import { Button } from '@/components/ui/Button';
import { FieldInput } from '@/components/ui/FieldInput';

const STEPS = ['Account', 'Profile', 'Preferences', 'Confirm'];

function InteractiveStepperDemo() {
    const [step, setStep] = useState(1);
    const total = 4;

    return (
        <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <div>
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Interactive Example</h3>
                <p className="text-body-sm text-txt-black-500">
                    Click through the steps — this drives the stepper live, no page reload.
                </p>
            </div>

            <ol className="flex items-start w-full">
                {Array.from({ length: total }, (_, i) => i + 1).map((i) => (
                    <li key={i} className={`flex items-center ${i < total ? 'flex-1' : ''}`}>
                        <div className="flex flex-col items-center gap-1.5 sm:flex-row sm:gap-2">
                            <span
                                className={`flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-body-sm font-medium ${
                                    i < step
                                        ? 'bg-primary-600 text-white'
                                        : i === step
                                          ? 'border-2 border-primary-600 text-primary-600'
                                          : 'border-2 border-otl-gray-300 text-txt-black-500'
                                }`}
                            >
                                {i < step ? (
                                    <svg className="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            fillRule="evenodd"
                                            clipRule="evenodd"
                                            d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                        />
                                    </svg>
                                ) : (
                                    i
                                )}
                            </span>
                            <span
                                className={`text-body-xs sm:text-body-sm font-medium ${i <= step ? 'text-txt-black-900' : 'text-txt-black-500'}`}
                            >
                                Step {i}
                            </span>
                        </div>
                        {i < total && (
                            <div
                                className={`mx-3 flex-1 ${i < step ? 'h-1 rounded-full bg-primary-600' : 'border-t-2 border-dashed border-otl-gray-300'}`}
                            />
                        )}
                    </li>
                ))}
            </ol>

            <div className="flex justify-between pt-2">
                <Button variant="secondary" type="button" onClick={() => setStep((s) => Math.max(1, s - 1))} disabled={step === 1}>
                    Back
                </Button>
                <Button variant="primary" type="button" onClick={() => setStep((s) => Math.min(total, s + 1))} disabled={step === total}>
                    Next
                </Button>
            </div>
        </section>
    );
}

function DataDrivenProgressDemo() {
    const [fields, setFields] = useState({ name: '', email: '', phone: '', address: '' });
    const entries = Object.entries(fields);
    const filledCount = entries.filter(([, v]) => v.trim() !== '').length;
    const total = entries.length;
    const percent = Math.round((filledCount / total) * 100);

    function update(key: keyof typeof fields, value: string) {
        setFields((f) => ({ ...f, [key]: value }));
    }

    return (
        <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <div>
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">
                    Data-Driven Progress Stepper
                </h3>
                <p className="text-body-sm text-txt-black-500">
                    Instead of clicking Next/Back, this one measures real data as you type — fill in the fields below
                    and watch it update live.
                </p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                <FieldInput label="Name" name="ks_progress_name" value={fields.name} onChange={(v) => update('name', v)} />
                <FieldInput
                    label="Email"
                    type="email"
                    name="ks_progress_email"
                    value={fields.email}
                    onChange={(v) => update('email', v)}
                />
                <FieldInput label="Phone" name="ks_progress_phone" value={fields.phone} onChange={(v) => update('phone', v)} />
                <FieldInput
                    label="Address"
                    name="ks_progress_address"
                    value={fields.address}
                    onChange={(v) => update('address', v)}
                />
            </div>

            <div className="max-w-2xl space-y-2">
                <div className="flex items-center justify-between text-body-sm">
                    <span className="font-medium text-txt-black-900">
                        {filledCount} of {total} fields completed
                    </span>
                    <span className="text-txt-black-500">{percent}%</span>
                </div>

                <div className="h-2 w-full overflow-hidden rounded-full bg-bg-washed">
                    <div className="h-full rounded-full bg-primary-600 transition-all duration-300" style={{ width: `${percent}%` }} />
                </div>

                <div className="flex items-center gap-2 pt-1">
                    {entries.map(([key, value]) => (
                        <span
                            key={key}
                            className={`h-2.5 w-2.5 rounded-full transition-colors ${value.trim() !== '' ? 'bg-primary-600' : 'bg-otl-gray-300'}`}
                        />
                    ))}
                </div>
            </div>
        </section>
    );
}

const TIMELINE_ITEMS = [
    {
        title: 'User account created',
        time: '28 Jul 2026, 9:02 AM',
        description: 'Account was created by Admin.',
        color: 'success' as const,
    },
    {
        title: 'Role changed to Admin',
        time: '28 Jul 2026, 9:15 AM',
        description: 'Updated by Admin.',
        color: 'primary' as const,
    },
    {
        title: 'Password reset requested',
        time: '28 Jul 2026, 11:40 AM',
        description: 'Requested from an unrecognised device.',
        color: 'warning' as const,
    },
    {
        title: 'Account deactivated',
        time: '29 Jul 2026, 4:20 PM',
        description: 'Deactivated by Admin.',
        color: 'danger' as const,
    },
];

export default function ProgressPage() {
    return (
        <PageContainer className="space-y-8">
            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-6">
                <div>
                    <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Stepper</h3>
                    <p className="text-body-sm text-txt-black-500">
                        A multi-step progress indicator — completed steps show a checkmark, the current step is
                        outlined, upcoming steps are muted.
                    </p>
                </div>

                <div>
                    <p className="mb-3 text-body-xs font-medium text-txt-black-500">Step 1 — just started</p>
                    <Stepper current={1} steps={STEPS} />
                </div>

                <div>
                    <p className="mb-3 text-body-xs font-medium text-txt-black-500">Step 3 — in progress</p>
                    <Stepper current={3} steps={STEPS} />
                </div>

                <div>
                    <p className="mb-3 text-body-xs font-medium text-txt-black-500">All steps completed</p>
                    <Stepper current={5} steps={STEPS} />
                </div>
            </section>

            <InteractiveStepperDemo />
            <DataDrivenProgressDemo />

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <div>
                    <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">History Timeline</h3>
                    <p className="text-body-sm text-txt-black-500">
                        A chronological activity log — the same pattern you&apos;d use for an audit trail or a
                        record&apos;s change history.
                    </p>
                </div>

                <Timeline items={TIMELINE_ITEMS} />
            </section>
        </PageContainer>
    );
}
