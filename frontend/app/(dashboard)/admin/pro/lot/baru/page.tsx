'use client';

import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { LotForm } from '@/components/pro/LotForm';
import { useCan, useMe } from '@/lib/pro';

export default function LotBaruPage() {
    const can = useCan();
    const { data: me } = useMe();

    return (
        <PageContainer>
            {me && !can('pro.lot.cipta') ? <Alert type="error">Anda tidak mempunyai kebenaran untuk menambah lot.</Alert> : <LotForm />}
        </PageContainer>
    );
}
