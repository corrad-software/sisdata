import { Suspense } from 'react';
import { getServerUser } from '@/lib/auth-server';
import { PageHeader } from '@/components/PageHeader';
import { PageContainer } from '@/components/PageContainer';
import { Spinner } from '@/components/ui/Spinner';
import { DashboardEksekutif } from './dsh/_components/DashboardEksekutif';
import { PortalSelamatDatang } from './dsh/_components/PortalSelamatDatang';

/**
 * /admin — the SISDATA home. Users holding a MOD-DSH permission get the
 * executive dashboard (level chosen from their role, SRS-DSH-01); everyone
 * else gets a simple welcome with the internal-portal links they may use
 * (SRS-DSH-19). The API enforces access either way.
 */
export default async function AdminDashboardPage() {
    const user = await getServerUser();
    const canDashboard = (user?.permissions ?? []).includes('dsh.dashboard.lihat');

    return (
        <>
            <PageHeader title={canDashboard ? 'Dashboard Eksekutif' : 'Utama'} breadcrumbs={[{ label: canDashboard ? 'Dashboard Eksekutif' : 'Utama' }]} />
            {canDashboard ? (
                <Suspense
                    fallback={
                        <PageContainer>
                            <div className="flex justify-center py-16">
                                <Spinner size="lg" />
                            </div>
                        </PageContainer>
                    }
                >
                    <DashboardEksekutif />
                </Suspense>
            ) : (
                <PortalSelamatDatang name={user?.name ?? ''} />
            )}
        </>
    );
}
