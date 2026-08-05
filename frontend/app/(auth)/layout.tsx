import Link from 'next/link';
import { redirect } from 'next/navigation';
import type { ReactNode } from 'react';
import { Masthead } from '@/components/Masthead';
import { Footer } from '@/components/Footer';
import { ApplicationLogo } from '@/components/ApplicationLogo';
import { getServerSettings } from '@/lib/settings-server';
import { getServerUser } from '@/lib/auth-server';

export default async function AuthLayout({ children }: { children: ReactNode }) {
    // Authoritative check (unlike proxy.ts's cheap cookie-presence check) —
    // an already-logged-in visitor shouldn't see the login/register forms.
    const user = await getServerUser();

    if (user) {
        redirect('/admin');
    }

    // Deduped with the root layout's own call via getServerSettings' cache()
    // wrapper — this doesn't cost a second network round-trip.
    const settings = await getServerSettings();

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

            <Footer siteTitle={settings.site_title} footerText={settings.footer_text} />
        </div>
    );
}
