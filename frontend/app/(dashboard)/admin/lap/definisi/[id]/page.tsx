'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Callout } from '@/components/ui/Callout';
import { lapApi, ralat, useCan } from '@/lib/lap';
import { Viewer } from '../../_components/Viewer';

/** A saved / shared ad hoc report — always run with the reader's own scope (SRS-LAP-03/09). */
export default function DefinisiPage() {
    const { id } = useParams<{ id: string }>();
    const can = useCan();
    const d = useQuery({ queryKey: ['lap', 'definisi', id], queryFn: () => lapApi.definisiSatu(id).then((r) => r.data) });

    return (
        <PageContainer>
            {d.isError && <Callout type="danger">{ralat(d.error)}</Callout>}
            {d.data && (
                <div className="flex flex-wrap items-center justify-between gap-2 text-body-sm text-txt-black-500">
                    <p>
                        {d.data.set_label} · dicipta oleh {d.data.pencipta ?? '—'} · {d.data.status_kongsi === 'peribadi' ? 'Peribadi' : d.data.status_kongsi === 'semua' ? 'Dikongsi dengan semua' : `Dikongsi dengan peranan: ${d.data.kongsi_peranan.join(', ')}`}
                    </p>
                    {can('lap.adhoc.cipta') && (
                        <Link href={`/admin/lap/pembina?definisi=${id}`} className="text-txt-primary hover:underline">
                            {d.data.milik_saya ? 'Ubah suai' : 'Salin & ubah suai'}
                        </Link>
                    )}
                </div>
            )}
            {d.data && <Viewer sumber={{ definisi: id }} />}
        </PageContainer>
    );
}
