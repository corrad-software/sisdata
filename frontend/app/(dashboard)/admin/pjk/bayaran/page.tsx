'use client';

import Link from 'next/link';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { Table } from '@/components/ui/Table';
import { pjkApi, ralat, tarikh, type Bayaran } from '@/lib/pjk';
import { Card, Kosong } from '../_components/common';

/** SRS-PJK-19: Bahagian Kewangan confirms offline payments (never the officer who recorded them). */
export default function PengesahanBayaranPage() {
    const qc = useQueryClient();
    const { data, isLoading, error } = useQuery({ queryKey: ['pjk', 'bayaran-menunggu'], queryFn: pjkApi.bayaranMenunggu });

    async function sahkan(b: Bayaran, terima: boolean) {
        const catatan = terima ? undefined : (window.prompt('Sebab penolakan') ?? undefined);
        if (!terima && !catatan) return;
        try {
            const r = await pjkApi.sahkanBayaran(b.id, terima, catatan);
            toast.success(r.message ?? 'Selesai.');
            qc.invalidateQueries({ queryKey: ['pjk', 'bayaran-menunggu'] });
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    return (
        <PageContainer>
            <Card title="Bayaran menunggu pengesahan">
                {error && <p className="text-body-sm text-txt-danger">{ralat(error)}</p>}
                <Table head={<><th>Bayaran</th><th className="hidden md:table-cell">Perjanjian</th><th className="hidden sm:table-cell">Kaedah / rujukan</th><th>Amaun</th><th /></>}>
                    {isLoading ? (
                        <Kosong colSpan={5}>Memuatkan…</Kosong>
                    ) : (data ?? []).length === 0 ? (
                        <Kosong colSpan={5}>Tiada bayaran menunggu pengesahan.</Kosong>
                    ) : (
                        data?.map((b) => (
                            <tr key={b.id}>
                                <td>
                                    {b.no_bayaran}
                                    <p className="text-body-xs text-txt-black-500">{tarikh(b.tarikh_bayaran)} · {b.nama_pembayar}</p>
                                </td>
                                <td className="hidden md:table-cell">
                                    {b.kontrak && (
                                        <Link href={`/admin/pjk/kontrak/${b.kontrak.id}?tab=bayaran`} className="text-txt-primary hover:underline">
                                            {b.kontrak.no_kontrak}
                                        </Link>
                                    )}
                                </td>
                                <td className="hidden sm:table-cell text-body-xs">
                                    {b.saluran_nama}
                                    <br />
                                    {b.rujukan_luar ?? '—'} {b.id_dokumen_bukti ? '· bukti dilampirkan' : ''}
                                </td>
                                <td className="whitespace-nowrap">{b.amaun.rm}</td>
                                <td>
                                    <div className="flex flex-wrap gap-1">
                                        <Button size="sm" onClick={() => sahkan(b, true)}>
                                            Sahkan
                                        </Button>
                                        <Button size="sm" variant="outline" onClick={() => sahkan(b, false)}>
                                            Tolak
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        ))
                    )}
                </Table>
            </Card>
        </PageContainer>
    );
}
