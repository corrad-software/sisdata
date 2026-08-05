import { redirect } from 'next/navigation';
import type { ReactNode } from 'react';
import { getServerUser } from '@/lib/auth-server';
import { getServerSettings } from '@/lib/settings-server';
import { Masthead } from '@/components/Masthead';
import { DashboardShell } from '@/components/DashboardShell';

export default async function DashboardLayout({ children }: { children: ReactNode }) {
    const user = await getServerUser();

    if (!user) {
        redirect('/login');
    }

    // Deduped with the root layout's own call via getServerSettings' cache()
    // wrapper — this doesn't cost a second network round-trip.
    const settings = await getServerSettings();

    return (
        <div className="min-h-screen flex flex-col bg-bg-washed">
            <Masthead />
            <DashboardShell user={user} siteTitle={settings.site_title} footerText={settings.footer_text}>
                {children}
            </DashboardShell>
        </div>
    );
}
