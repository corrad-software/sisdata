'use client';

import { useState } from 'react';
import type { FormEvent } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { login } from '@/lib/auth';
import { ApiError } from '@/lib/api';
import { InputLabel } from '@/components/ui/InputLabel';
import { TextInput } from '@/components/ui/TextInput';
import { InputError } from '@/components/ui/InputError';
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
        <form onSubmit={handleSubmit}>
            <div>
                <InputLabel htmlFor="email">Email</InputLabel>
                <TextInput
                    id="email"
                    type="email"
                    className="block mt-1 w-full"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
                    autoFocus
                    autoComplete="username"
                />
                <InputError messages={errors.email} className="mt-2" />
            </div>

            <div className="mt-4">
                <InputLabel htmlFor="password">Password</InputLabel>
                <TextInput
                    id="password"
                    type="password"
                    className="block mt-1 w-full"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    required
                    autoComplete="current-password"
                />
                <InputError messages={errors.password} className="mt-2" />
            </div>

            <div className="block mt-4">
                <label htmlFor="remember" className="inline-flex items-center">
                    <input
                        id="remember"
                        type="checkbox"
                        className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        checked={remember}
                        onChange={(e) => setRemember(e.target.checked)}
                    />
                    <span className="ms-2 text-sm text-gray-600">Remember me</span>
                </label>
            </div>

            <div className="flex items-center justify-end mt-4">
                <Button type="submit" className="ms-3" disabled={submitting}>
                    Log in
                </Button>
            </div>
        </form>
    );
}
