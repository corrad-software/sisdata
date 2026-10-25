'use client';

import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { BorangTiket } from '@/components/tkt/BorangTiket';
import { api } from '@/lib/api';
import type { User } from '@/lib/types';
import { Card } from '../../_components/common';

/** SRS-TKT-01: full-page ticket form (the same form as the help panel on every screen). */
export default function BukaTiketPage() {
    const { data: me } = useQuery({ queryKey: ['me'], queryFn: () => api.get<{ data: User }>('/user').then((r) => r.data), staleTime: 60_000 });

    return (
        <PageContainer>
            <Card title="Buka tiket sokongan">
                <p className="text-body-sm text-txt-black-500">
                    Tip: butang <strong>Bantuan</strong> di bahagian atas setiap skrin membuka borang ini tanpa meninggalkan skrin semasa, dan menangkap konteks skrin tersebut secara automatik.
                </p>
                <BorangTiket peranan={me?.peranan} />
            </Card>
        </PageContainer>
    );
}
