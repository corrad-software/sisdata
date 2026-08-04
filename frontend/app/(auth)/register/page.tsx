'use client';

import { useState } from 'react';
import type { FormEvent } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { toast } from 'sonner';
import { register } from '@/lib/auth';
import { ApiError } from '@/lib/api';
import { InputLabel } from '@/components/ui/InputLabel';
import { TextInput } from '@/components/ui/TextInput';
import { InputError } from '@/components/ui/InputError';
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
        <form onSubmit={handleSubmit}>
            <div>
                <InputLabel htmlFor="name">Name</InputLabel>
                <TextInput
                    id="name"
                    type="text"
                    className="block mt-1 w-full"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    required
                    autoFocus
                    autoComplete="name"
                />
                <InputError messages={errors.name} className="mt-2" />
            </div>

            <div className="mt-4">
                <InputLabel htmlFor="email">Email</InputLabel>
                <TextInput
                    id="email"
                    type="email"
                    className="block mt-1 w-full"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
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
                    autoComplete="new-password"
                />
                <InputError messages={errors.password} className="mt-2" />
            </div>

            <div className="mt-4">
                <InputLabel htmlFor="password_confirmation">Confirm Password</InputLabel>
                <TextInput
                    id="password_confirmation"
                    type="password"
                    className="block mt-1 w-full"
                    value={passwordConfirmation}
                    onChange={(e) => setPasswordConfirmation(e.target.value)}
                    required
                    autoComplete="new-password"
                />
                <InputError messages={errors.password_confirmation} className="mt-2" />
            </div>

            <div className="flex items-center justify-end mt-4">
                <Link
                    className="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    href="/login"
                >
                    Already registered?
                </Link>

                <Button type="submit" className="ms-4" disabled={submitting}>
                    Register
                </Button>
            </div>
        </form>
    );
}
