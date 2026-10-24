'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Table } from '@/components/ui/Table';
import { Alert } from '@/components/ui/Alert';
import { LinkButton } from '@/components/ui/Button';
import { hkmApi, LABEL_MEDAN, ralat, tarikhMasa } from '@/lib/hkm';
import { Card, InfoGrid, Kosong } from '../../_components/common';

function papar(v: unknown): string {
    if (v === null || v === undefined || v === '') return '—';
    if (typeof v === 'boolean') return v ? 'Ya' : 'Tidak';
    if (Array.isArray(v)) return `${v.length} rekod`;
    if (typeof v === 'object') return JSON.stringify(v);
    return String(v);
}

/** SRS-HKM-19: one change request — proposed vs current values, approval outcome. */
export default function PerubahanDetailPage() {
    const { id } = useParams<{ id: string }>();
    const { data: c, isLoading, error } = useQuery({ queryKey: ['hkm', 'perubahan', id], queryFn: () => hkmApi.satuPerubahan(id) });

    if (isLoading) return <PageContainer><Kosong>Memuatkan…</Kosong></PageContainer>;
    if (error || !c) return <PageContainer><Alert type="error">{error ? ralat(error) : 'Permohonan tidak ditemui.'}</Alert></PageContainer>;

    const semasa = c.data_semasa ?? {};
    const keys = Object.keys(c.data_cadangan);

    return (
        <PageContainer>
            <Card
                title={c.jenis_perubahan_nama}
                actions={
                    <>
                        <Badge variant={c.status === 'diluluskan' ? 'success' : c.status === 'menunggu_kelulusan' ? 'warning' : 'danger'}>{c.status_nama}</Badge>
                        {c.id_instans_aliran && (
                            <LinkButton size="sm" variant="outline" href={`/admin/wfe/instans/${c.id_instans_aliran}`}>
                                Lihat aliran kelulusan
                            </LinkButton>
                        )}
                    </>
                }
            >
                <InfoGrid
                    items={[
                        ['Hak milik', <Link key="h" className="text-txt-primary hover:underline" href={`/admin/hkm/hak-milik/${c.hak_milik_id}`}>{c.no_hak_milik}</Link>],
                        ['Pemohon', c.pemohon],
                        ['Dimohon', tarikhMasa(c.dicipta)],
                        ['Justifikasi', c.justifikasi],
                        ['No. rujukan dokumen', c.no_rujukan_dokumen],
                        ['Keputusan', c.tarikh_keputusan ? `${tarikhMasa(c.tarikh_keputusan)}${c.catatan_keputusan ? ` · ${c.catatan_keputusan}` : ''}` : 'Menunggu'],
                        ['Daripada cadangan luaran', c.cadangan_perubahan_id ? 'Ya' : 'Tidak'],
                    ]}
                />
            </Card>

            <Card title="Perubahan dicadangkan">
                <div className="overflow-x-auto">
                    <Table head={<><th>Medan</th><th>Nilai semasa (semasa dimohon)</th><th>Nilai dicadangkan</th></>}>
                        {keys.map((k) => (
                            <tr key={k}>
                                <td>{LABEL_MEDAN[k] ?? k}</td>
                                <td className="break-words max-w-[14rem]">{papar(semasa[k])}</td>
                                <td className="break-words max-w-[14rem] font-medium">{papar(c.data_cadangan[k])}</td>
                            </tr>
                        ))}
                    </Table>
                </div>
            </Card>
        </PageContainer>
    );
}
