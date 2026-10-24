'use client';

import { use } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { Timeline } from '@/components/ui/Timeline';
import { ckiApi, muatTurun, ralat, tarikh, useCan } from '@/lib/cki';
import { Card, Kosong, Status } from '../../_components/common';

/** SRS-CKI-19: per-lot tax payment timeline across years — visual timeline + exportable table. */
export default function GarisMasaPage({ params }: { params: Promise<{ id: string }> }) {
    const { id } = use(params);
    const can = useCan();
    const { data, isLoading, error } = useQuery({ queryKey: ['cki', 'garis-masa', id], queryFn: () => ckiApi.garisMasa(id) });

    if (isLoading) return <PageContainer>Memuatkan…</PageContainer>;
    if (error || !data)
        return (
            <PageContainer>
                <p className="text-txt-danger">{error ? ralat(error) : 'Tidak dijumpai.'}</p>
            </PageContainer>
        );

    const items = data.bil.map((b) => ({
        title: `${b.jenis_cukai_nama} ${b.tahun_taksiran} — ${b.jumlah.rm}`,
        time: `Tarikh akhir ${tarikh(b.tarikh_akhir)}${b.tarikh_bayar ? ` · dibayar ${tarikh(b.tarikh_bayar)}` : ''}`,
        description: [b.status_bayaran_nama, b.no_resit && `resit ${b.no_resit}`, b.amaun_denda.nilai !== '0.00' && `denda ${b.amaun_denda.rm}`].filter(Boolean).join(' · '),
        color: (b.status_bayaran === 'dibayar' ? (b.tarikh_bayar && b.tarikh_akhir && b.tarikh_bayar <= b.tarikh_akhir ? 'success' : 'warning') : b.status_bayaran === 'tertunggak' ? 'danger' : 'primary') as 'success' | 'warning' | 'danger' | 'primary',
    }));
    const eksport = (format: 'pdf' | 'xlsx') => muatTurun(`/cki/lot/${id}/garis-masa/eksport`, { format }, `garis-masa.${format}`).catch((e) => toast.error(ralat(e)));

    return (
        <PageContainer>
            <Card
                title={
                    <span>
                        Lot {data.lot.no_lot}{' '}
                        <Link href={data.pautan_profil} className="text-body-sm font-normal text-txt-primary hover:underline">
                            (Profil 360°)
                        </Link>
                    </span>
                }
                actions={
                    can('cki.bil.eksport') ? (
                        <>
                            <Button size="sm" variant="outline" onClick={() => eksport('pdf')}>
                                PDF
                            </Button>
                            <Button size="sm" variant="outline" onClick={() => eksport('xlsx')}>
                                Excel
                            </Button>
                        </>
                    ) : undefined
                }
            >
                {items.length === 0 ? <Kosong>Tiada bil cukai bagi lot ini.</Kosong> : <Timeline items={items} />}
            </Card>
            <Card title="Jadual">
                <Table head={<><th>Tahun</th><th>Bil</th><th>Amaun</th><th className="hidden sm:table-cell">Denda</th><th className="hidden md:table-cell">Tarikh akhir</th><th className="hidden md:table-cell">Tarikh bayar</th><th className="hidden lg:table-cell">No. resit</th><th>Status</th></>}>
                    {data.bil.map((b) => (
                        <tr key={b.id}>
                            <td>{b.tahun_taksiran}</td>
                            <td className="max-w-[9rem] sm:max-w-xs break-words">
                                <Link href={`/admin/cki/bil/${b.id}`} className="text-txt-primary hover:underline">
                                    {b.no_bil}
                                </Link>
                                <p className="text-body-xs text-txt-black-500">{b.jenis_cukai_nama}</p>
                            </td>
                            <td className="whitespace-nowrap">{b.amaun_pokok.rm}</td>
                            <td className="hidden sm:table-cell whitespace-nowrap">{b.amaun_denda.rm}</td>
                            <td className="hidden md:table-cell">{tarikh(b.tarikh_akhir)}</td>
                            <td className="hidden md:table-cell">{tarikh(b.tarikh_bayar)}</td>
                            <td className="hidden lg:table-cell">{b.no_resit ?? '—'}</td>
                            <td>
                                <Status kod={b.status_bayaran} nama={b.status_bayaran_nama} />
                            </td>
                        </tr>
                    ))}
                </Table>
            </Card>
        </PageContainer>
    );
}
