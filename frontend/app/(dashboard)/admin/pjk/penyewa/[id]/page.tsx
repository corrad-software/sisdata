'use client';

import { use } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { pjkApi, ralat, tarikh } from '@/lib/pjk';
import { Card, InfoGrid, Kosong, Sintetik, Status } from '../../_components/common';

/** Tenant detail — full identity only with pjk.sulit.lihat (audited by the API). */
export default function PenyewaDetailPage({ params }: { params: Promise<{ id: string }> }) {
    const { id } = use(params);
    const { data: p, isLoading, error } = useQuery({ queryKey: ['pjk', 'penyewa', id], queryFn: () => pjkApi.penyewaSatu(id) });

    if (isLoading) return <PageContainer>Memuatkan…</PageContainer>;
    if (error || !p) return <PageContainer><p className="text-txt-danger">{error ? ralat(error) : 'Tidak dijumpai.'}</p></PageContainer>;

    return (
        <PageContainer>
            <Card title={<span className="flex flex-wrap items-center gap-2">{p.nama} <Sintetik show={p.sintetik} /></span>}>
                {!p.boleh_lihat_penuh && <p className="text-body-xs text-txt-black-500">Nombor pengenalan dan hubungan ditutup sebahagian — peranan anda tidak dibenarkan melihat maklumat Sulit penuh.</p>}
                <InfoGrid
                    items={[
                        ['Jenis entiti', p.jenis_entiti_nama],
                        ['No. kad pengenalan', p.no_pengenalan ?? '—'],
                        ['No. pendaftaran syarikat', p.no_pendaftaran_syarikat ?? '—'],
                        ['Alamat', p.alamat ? `${p.alamat} ${p.poskod ?? ''}` : '—'],
                        ['Telefon', p.telefon ?? '—'],
                        ['E-mel', p.emel ?? '—'],
                    ]}
                />
            </Card>
            <Card title="Perjanjian">
                <Table head={<><th>No. perjanjian</th><th className="hidden sm:table-cell">Tempoh</th><th className="hidden sm:table-cell">Kadar</th><th>Status</th></>}>
                    {(p.kontrak ?? []).length === 0 ? (
                        <Kosong colSpan={4}>Tiada perjanjian dalam skop anda.</Kosong>
                    ) : (
                        p.kontrak?.map((k) => (
                            <tr key={k.id}>
                                <td>
                                    <Link href={`/admin/pjk/kontrak/${k.id}`} className="text-txt-primary hover:underline">
                                        {k.no_kontrak}
                                    </Link>
                                </td>
                                <td className="hidden sm:table-cell">
                                    {tarikh(k.tarikh_mula)} – {tarikh(k.tarikh_tamat)}
                                </td>
                                <td className="hidden sm:table-cell">{k.kadar.rm}</td>
                                <td>
                                    <Status kod={k.status} nama={k.status_nama} />
                                </td>
                            </tr>
                        ))
                    )}
                </Table>
            </Card>
        </PageContainer>
    );
}
