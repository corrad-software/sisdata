'use client';

import Link from 'next/link';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Callout } from '@/components/ui/Callout';
import { lapApi, ralat, tarikhMasa } from '@/lib/lap';
import { Card, Kosong } from '../_components/common';

/** Saved and shared ad hoc report definitions (SRS-LAP-03). */
export default function DefinisiListPage() {
    const qc = useQueryClient();
    const q = useQuery({ queryKey: ['lap', 'definisi'], queryFn: () => lapApi.definisi().then((r) => r.data) });
    const padam = useMutation({
        mutationFn: (id: string) => lapApi.padamDefinisi(id),
        onSuccess: () => {
            toast.success('Dipadam.');
            qc.invalidateQueries({ queryKey: ['lap', 'definisi'] });
        },
        onError: (e) => toast.error(ralat(e)),
    });

    return (
        <PageContainer>
            <Card title="Laporan tersimpan dan dikongsi">
                {q.isError && <Callout type="danger">{ralat(q.error)}</Callout>}
                {q.data && q.data.length === 0 && <Kosong>Belum ada laporan tersimpan. Bina satu di Pembina Ad Hoc.</Kosong>}
                {q.data && q.data.length > 0 && (
                    <Table head={<><th>Nama</th><th>Set data</th><th>Perkongsian</th><th>Pencipta</th><th>Dikemas kini</th><th>Tindakan</th></>}>
                        {q.data.map((d) => (
                            <tr key={d.id}>
                                <td>
                                    <Link href={`/admin/lap/definisi/${d.id}`} className="text-txt-primary hover:underline font-medium">
                                        {d.nama}
                                    </Link>
                                    {d.sumber === 'ai_prompt' && (
                                        <span className="ml-2">
                                            <Badge variant="info">AI prompt</Badge>
                                        </span>
                                    )}
                                </td>
                                <td>{d.set_label}</td>
                                <td>{d.status_kongsi === 'peribadi' ? 'Peribadi' : d.status_kongsi === 'semua' ? 'Semua' : d.kongsi_peranan.join(', ')}</td>
                                <td>{d.pencipta ?? '—'}</td>
                                <td className="whitespace-nowrap">{tarikhMasa(d.dikemas_kini)}</td>
                                <td className="whitespace-nowrap space-x-3">
                                    <Link href={`/admin/lap/definisi/${d.id}`} className="text-txt-primary hover:underline">
                                        Jana
                                    </Link>
                                    {d.milik_saya && (
                                        <Button size="sm" variant="outline" onClick={() => confirm('Padam definisi ini?') && padam.mutate(d.id)}>
                                            Padam
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>
        </PageContainer>
    );
}
