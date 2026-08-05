'use client';

import { useState } from 'react';
import type { FormEvent } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { toast } from 'sonner';
import { login } from '@/lib/auth';
import { ApiError } from '@/lib/api';
import { FieldInput } from '@/components/ui/FieldInput';
import { Button } from '@/components/ui/Button';

function getRedirectTarget(): string {
    if (typeof window === 'undefined') return '/admin';
    return new URLSearchParams(window.location.search).get('redirect') ?? '/admin';
}

export default function LoginPage() {
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
            } else {
                toast.error('Something went wrong. Please try again.');
            }
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <>
            <h2 className="mb-1 font-heading text-heading-xs font-semibold text-txt-black-900">Log in to your account</h2>
            <p className="mb-6 text-body-sm text-txt-black-500">Welcome back! Please enter your details.</p>

            <form className="space-y-4" onSubmit={handleSubmit}>
                <FieldInput
                    label="Email"
                    type="email"
                    name="email"
                    value={email}
                    onChange={setEmail}
                    required
                    autoFocus
                    autoComplete="username"
                    error={errors.email?.[0]}
                />
                <FieldInput
                    label="Password"
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
                    <span className="text-body-sm text-txt-black-900">Remember me</span>
                </label>

                <Button variant="primary" type="submit" className="w-full" disabled={submitting}>
                    Log in
                </Button>
            </form>

            <p className="mt-6 text-center text-body-sm text-txt-black-500">
                {"Don't have an account? "}
                <Link href="/register" className="font-medium text-primary-600 hover:text-primary-700">
                    Register
                </Link>
            </p>
        </>
    );
}
