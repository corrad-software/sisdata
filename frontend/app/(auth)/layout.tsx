import Link from 'next/link';
import type { ReactNode } from 'react';
import { Masthead } from '@/components/Masthead';
import { Footer } from '@/components/Footer';
import { ApplicationLogo } from '@/components/ApplicationLogo';

export default function AuthLayout({ children }: { children: ReactNode }) {
    return (
        <div className="min-h-screen flex flex-col bg-bg-washed">
            <Masthead />

            <div className="flex-1 flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
                <div>
                    <Link href="/">
                        <ApplicationLogo className="w-16 h-16 fill-current text-primary-600" />
                    </Link>
                </div>

                <div className="w-full sm:max-w-md mt-6 px-6 py-4 bg-bg-white shadow-card overflow-hidden sm:rounded-lg border border-otl-gray-200">
                    {children}
                </div>
            </div>

            <Footer />
        </div>
    );
}
