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
                <Link href="/" className="flex flex-col items-center gap-2 text-center px-6">
                    <ApplicationLogo className="w-16 h-16 fill-current text-primary-600" />
                    <span className="font-heading font-semibold text-heading-2xs text-txt-black-900">{settings.site_title}</span>
                    <span className="text-body-sm text-txt-black-500">Sistem Pengurusan Data Hartanah MAIS</span>
                </Link>

                <div className="w-full sm:max-w-md mt-6 px-6 py-4 bg-bg-white shadow-card overflow-hidden sm:rounded-lg border border-otl-gray-200">
                    {children}
                </div>
            </div>

            <Footer siteTitle={settings.site_title} footerText={settings.footer_text} />
        </div>
    );
}
