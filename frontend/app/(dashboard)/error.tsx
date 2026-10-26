'use client';

import { useEffect } from 'react';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';

/** SRS-GEN-09: Malay error boundary for dashboard pages. */
export default function DashboardError({ error, retry }: { error: unknown; retry: () => void }) {
    useEffect(() => {
        console.error(error);
    }, [error]);

    return (
        <PageContainer>
            <div className="rounded-lg border border-otl-gray-200 bg-bg-white p-6 shadow-card">
                <h2 className="font-heading text-heading-2xs font-semibold text-txt-black-900">Ralat tidak dijangka berlaku</h2>
                <p className="mt-1 text-body-sm text-txt-black-500">
                    Halaman ini tidak dapat dipaparkan. Sila cuba lagi atau hubungi pentadbir sistem jika masalah berterusan.
                </p>
                <Button className="mt-4" onClick={() => retry()}>
                    Cuba Lagi
                </Button>
            </div>
        </PageContainer>
    );
}
