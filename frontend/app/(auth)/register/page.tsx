'use client';

import { useState } from 'react';
import type { FormEvent } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { toast } from 'sonner';
import { register } from '@/lib/auth';
import { ApiError } from '@/lib/api';
import { FieldInput } from '@/components/ui/FieldInput';
import { Button } from '@/components/ui/Button';

export default function RegisterPage() {
    const router = useRouter();
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [submitting, setSubmitting] = useState(false);

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setSubmitting(true);
        setErrors({});

        try {
            await register({ name, email, password, password_confirmation: passwordConfirmation });
            router.push('/admin');
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
            <h2 className="mb-1 font-heading text-heading-xs font-semibold text-txt-black-900">Create your account</h2>
            <p className="mb-6 text-body-sm text-txt-black-500">Fill in your details to get started.</p>

            <form className="space-y-4" onSubmit={handleSubmit}>
                <FieldInput
                    label="Name"
                    name="name"
                    value={name}
                    onChange={setName}
                    required
                    autoFocus
                    autoComplete="name"
                    error={errors.name?.[0]}
                />
                <FieldInput
                    label="Email"
                    type="email"
                    name="email"
                    value={email}
                    onChange={setEmail}
                    required
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
                    autoComplete="new-password"
                    error={errors.password?.[0]}
                />
                <FieldInput
                    label="Confirm Password"
                    type="password"
                    name="password_confirmation"
                    value={passwordConfirmation}
                    onChange={setPasswordConfirmation}
                    required
                    autoComplete="new-password"
                    error={errors.password_confirmation?.[0]}
                />

                <Button variant="primary" type="submit" className="w-full" disabled={submitting}>
                    Register
                </Button>
            </form>

            <p className="mt-6 text-center text-body-sm text-txt-black-500">
                Already have an account?{' '}
                <Link href="/login" className="font-medium text-primary-600 hover:text-primary-700">
                    Log in
                </Link>
            </p>
        </>
    );
}
