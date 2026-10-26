'use client';

import { useState } from 'react';
import type { FormEvent } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { login } from '@/lib/auth';
import { ApiError } from '@/lib/api';
import { FieldInput } from '@/components/ui/FieldInput';
import { AnchorButton, Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';

function getRedirectTarget(): string {
    if (typeof window === 'undefined') return '/admin';
    return new URLSearchParams(window.location.search).get('redirect') ?? '/admin';
}

export function LoginForm({
    passwordLoginEnabled,
    ssoError,
    sessionNotice = null,
}: {
    passwordLoginEnabled: boolean;
    ssoError: string | null;
    sessionNotice?: string | null;
}) {
    const router = useRouter();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [submitting, setSubmitting] = useState(false);

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setSubmitting(true);
        setErrors({});

        try {
            await login({ email, password, remember });
            router.push(getRedirectTarget());
            router.refresh();
        } catch (error) {
            if (error instanceof ApiError && error.errors) {
                setErrors(error.errors);
            } else if (error instanceof ApiError && error.status === 403) {
                toast.error(error.message);
            } else {
                toast.error('Ralat tidak dijangka berlaku. Sila cuba lagi.');
            }
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <>
            <h2 className="mb-1 font-heading text-heading-xs font-semibold text-txt-black-900">Log Masuk ke Akaun Anda</h2>
            <p className="mb-6 text-body-sm text-txt-black-500">Selamat datang ke SISDATA. Sila log masuk untuk meneruskan.</p>

            {sessionNotice && !ssoError && (
                <Alert type="warning" className="mb-4">
                    {sessionNotice}
                </Alert>
            )}

            {ssoError && (
                <Alert type="error" className="mb-4">
                    {ssoError}
                </Alert>
            )}

            {/* SRS-RBC-01 / SRS-RBC-03: SSO MAIS (OIDC, MFA enforced by the IdP).
                A plain <a>, not next/link: /auth/* is a backend redirect. */}
            <AnchorButton href="/auth/oidc/redirect" variant="primary" size="lg" className="w-full">
                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5} aria-hidden="true">
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"
                    />
                </svg>
                Log Masuk dengan SSO MAIS
            </AnchorButton>

            {passwordLoginEnabled && (
                <>
                    <div className="my-6 flex items-center gap-3" role="separator">
                        <div className="h-px flex-1 bg-otl-gray-200" />
                        <span className="text-body-xs text-txt-black-500">atau dengan kata laluan</span>
                        <div className="h-px flex-1 bg-otl-gray-200" />
                    </div>

                    <form className="space-y-4" onSubmit={handleSubmit}>
                        <FieldInput
                            label="E-mel"
                            type="email"
                            name="email"
                            value={email}
                            onChange={setEmail}
                            required
                            autoComplete="username"
                            error={errors.email?.[0]}
                        />
                        <FieldInput
                            label="Kata Laluan"
                            type="password"
                            name="password"
                            value={password}
                            onChange={setPassword}
                            required
                            autoComplete="current-password"
                            error={errors.password?.[0]}
                        />

                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={remember}
                                onChange={(e) => setRemember(e.target.checked)}
                                className="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary"
                            />
                            <span className="text-body-sm text-txt-black-900">Ingat saya</span>
                        </label>

                        <Button variant="secondary" type="submit" className="w-full" disabled={submitting}>
                            Log Masuk
                        </Button>
                    </form>

                    {/* SRS-GEN-09: SISDATA is internal to MAIS — accounts come from SSO MAIS or an
                        administrator, so no self-registration link (the /register route still exists). */}
                    <p className="mt-6 text-center text-body-xs text-txt-black-500">
                        Belum mempunyai akaun? Sila hubungi pentadbir sistem SISDATA.
                    </p>
                </>
            )}
        </>
    );
}
