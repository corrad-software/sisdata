'use client';

import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { lapApi } from '@/lib/lap';
import { Viewer } from '../../_components/Viewer';

/** A standard / composite report from the catalogue (SRS-LAP-02). */
export default function LaporanPage() {
    const { kod } = useParams<{ kod: string }>();
    const meta = useQuery({ queryKey: ['lap', 'katalog', kod], queryFn: () => lapApi.katalogSatu(kod).then((r) => r.data) });

    return (
        <PageContainer>
            {meta.data && (
                <div className="text-body-sm text-txt-black-500 space-y-1">
                    <p>
                        <span className="font-mono">{meta.data.kod}</span> · {meta.data.kategori_nama} · Pemilik: {meta.data.pemilik ?? '—'}
                    </p>
                    {meta.data.definisi_metrik && <p>{meta.data.definisi_metrik}</p>}
                </div>
            )}
            <Viewer sumber={{ katalog: kod }} />
        </PageContainer>
    );
}
