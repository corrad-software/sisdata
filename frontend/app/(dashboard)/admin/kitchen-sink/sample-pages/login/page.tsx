'use client';

import { useState } from 'react';
import Link from 'next/link';
import { BrowserFrame } from '@/components/BrowserFrame';
import { Masthead } from '@/components/Masthead';
import { Footer } from '@/components/Footer';
import { ApplicationLogo } from '@/components/ApplicationLogo';
import { PageContainer } from '@/components/PageContainer';
import { FieldInput } from '@/components/ui/FieldInput';
import { Button } from '@/components/ui/Button';

export default function SampleLoginPage() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');

    return (
        <PageContainer className="space-y-4">
            <div>
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Login Page</h3>
                <p className="text-body-sm text-txt-black-500">
                    A full-page reference template — the MYDS-styled version of a login screen, built entirely from
                    existing kitchen sink components.
                </p>
            </div>

            <BrowserFrame url="yourapp.test/login" className="max-w-2xl mx-auto">
                <div className="flex min-h-[640px] flex-col bg-bg-washed">
                    <Masthead />

                    <div className="flex flex-1 flex-col items-center justify-center px-4 py-10">
                        <div className="mb-6 flex items-center gap-2">
                            <ApplicationLogo className="h-10 w-auto fill-current text-primary-600" />
                            <span className="text-body-md font-semibold text-txt-black-900">MYDS-Laravel</span>
                        </div>

                        <div className="w-full max-w-sm rounded-lg border border-otl-gray-200 bg-bg-white p-6 shadow-card">
                            <h2 className="mb-1 font-heading text-heading-xs font-semibold text-txt-black-900">
                                Log in to your account
                            </h2>
                            <p className="mb-6 text-body-sm text-txt-black-500">
                                Welcome back! Please enter your details.
                            </p>

                            <form className="space-y-4" onSubmit={(e) => e.preventDefault()}>
                                <FieldInput
                                    label="Email"
                                    type="email"
                                    name="ks_login_email"
                                    value={email}
                                    onChange={setEmail}
                                    placeholder="jane@example.com"
                                />
                                <FieldInput
                                    label="Password"
                                    type="password"
                                    name="ks_login_password"
                                    value={password}
                                    onChange={setPassword}
                                    placeholder="••••••••"
                                />

                                <div className="flex items-center justify-between">
                                    <label className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            className="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary"
                                        />
                                        <span className="text-body-sm text-txt-black-900">Remember me</span>
                                    </label>
                                    <a href="#" className="text-body-sm font-medium text-primary-600 hover:text-primary-700">
                                        Forgot password?
                                    </a>
                                </div>

                                <Button variant="primary" type="submit" className="w-full">
                                    Log in
                                </Button>
                            </form>

                            <p className="mt-6 text-center text-body-sm text-txt-black-500">
                                {"Don't have an account? "}
                                <Link
                                    href="/admin/kitchen-sink/sample-pages/register"
                                    className="font-medium text-primary-600 hover:text-primary-700"
                                >
                                    Register
                                </Link>
                            </p>
                        </div>
                    </div>

                    <Footer />
                </div>
            </BrowserFrame>
        </PageContainer>
    );
}
