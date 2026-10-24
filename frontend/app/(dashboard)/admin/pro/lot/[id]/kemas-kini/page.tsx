'use client';

import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Loading } from '@/components/pro/common';
import { LotForm } from '@/components/pro/LotForm';
import { errorMessage, proApi, useCan, useMe } from '@/lib/pro';

export default function LotKemasKiniPage() {
    const { id } = useParams<{ id: string }>();
    const can = useCan();
    const { data: me } = useMe();
    const { data, isLoading, error } = useQuery({ queryKey: ['pro', 'lot', id, 'butiran'], queryFn: () => proApi.lot(id).then((r) => r.data) });

    return (
        <PageContainer>
            {me && !can('pro.lot.kemas_kini') ? (
                <Alert type="error">Anda tidak mempunyai kebenaran untuk mengemas kini lot.</Alert>
            ) : isLoading ? (
                <Loading />
            ) : error ? (
                <Alert type="error">{errorMessage(error)}</Alert>
            ) : data ? (
                <>
                    <p className="text-body-sm text-txt-black-500">
                        {data.no_lot} · {data.nama_mukim ?? data.kod_mukim}, {data.nama_daerah ?? data.kod_daerah} · versi rekod {data.versi_rekod}
                    </p>
                    <LotForm key={`${data.id}-${data.versi_rekod}`} lot={data} />
                </>
            ) : null}
        </PageContainer>
    );
}
