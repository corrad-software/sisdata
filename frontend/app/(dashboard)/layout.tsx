import { redirect } from 'next/navigation';
import type { ReactNode } from 'react';
import { getServerUser } from '@/lib/auth-server';
import { Masthead } from '@/components/Masthead';
import { DashboardShell } from '@/components/DashboardShell';

export default async function DashboardLayout({ children }: { children: ReactNode }) {
    const user = await getServerUser();

    if (!user) {
        redirect('/login');
    }

    return (
        <div className="min-h-screen flex flex-col bg-bg-washed">
            <Masthead />
            <DashboardShell user={user}>{children}</DashboardShell>
        </div>
    );
}
