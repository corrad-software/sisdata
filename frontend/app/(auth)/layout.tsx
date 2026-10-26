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

            <div className="relative flex-1 flex flex-col sm:justify-center items-center py-8 overflow-hidden">
                {/* Bangunan MAIS, blurred and darkened so the form stays readable. */}
                <div
                    aria-hidden="true"
                    className="absolute inset-0 scale-110 bg-cover bg-center blur-md"
                    style={{ backgroundImage: "url('/branding/latar-log-masuk.jpg')" }}
                />
                <div aria-hidden="true" className="absolute inset-0 bg-gradient-to-b from-[#0b1f3a]/70 via-[#0b1f3a]/55 to-[#0b1f3a]/75" />

                <Link href="/" className="relative flex flex-col items-center gap-2 text-center px-6">
                    <span className="flex h-24 w-24 items-center justify-center rounded-2xl bg-white/95 p-2 shadow-card">
                        <ApplicationLogo className="h-full w-full" />
                    </span>
                    <span className="font-heading font-semibold text-heading-xs text-white drop-shadow">{settings.site_title}</span>
                    <span className="text-body-sm text-white/90 drop-shadow">Sistem Pengurusan Data Hartanah MAIS</span>
                </Link>

                <div className="relative w-full sm:max-w-md mt-6 px-6 py-4 bg-bg-white shadow-card overflow-hidden sm:rounded-lg border border-otl-gray-200">
                    {children}
                </div>
            </div>

            <Footer siteTitle={settings.site_title} footerText={settings.footer_text} />
        </div>
    );
}
