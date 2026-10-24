'use client';

import { use, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { ckiApi, ralat } from '@/lib/cki';

/** Drill-down target of the WFE dashboard (SubjectLinks "transaksi_bayaran_cukai"): opens the bill the payment belongs to. */
export default function TransaksiRedirect({ params }: { params: Promise<{ id: string }> }) {
    const { id } = use(params);
    const router = useRouter();
    const { data, error } = useQuery({ queryKey: ['cki', 'transaksi', id], queryFn: () => ckiApi.transaksiSatu(id) });

    useEffect(() => {
        if (data) router.replace(`/admin/cki/bil/${data.id_bil}`);
    }, [data, router]);

    return <PageContainer>{error ? <p className="text-txt-danger">{ralat(error)}</p> : 'Membuka bil…'}</PageContainer>;
}
