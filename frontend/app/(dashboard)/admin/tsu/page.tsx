'use client';

import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { StatCard } from '@/components/ui/StatCard';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { ralat, tarikh, tsuApi } from '@/lib/tsu';
import { Card, Kosong, Ralat } from './_components/common';

/** SRS-TSU-27: active matters by type, average completion time, external-party backlog. */
export default function TsuDashboard() {
    const { data: d, error } = useQuery({ queryKey: ['tsu', 'ringkasan', {}], queryFn: () => tsuApi.ringkasan({}) });

    if (error) return <PageContainer><Ralat>{ralat(error)}</Ralat></PageContainer>;
    if (!d) return <PageContainer><Kosong>Memuatkan…</Kosong></PageContainer>;
    const aktif = d.aktif_mengikut_jenis.reduce((s, r) => s + r.nilai, 0);

    return (
        <PageContainer>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <StatCard label="Urusan aktif" value={String(aktif)} />
                <StatCard label="Tertunggak di pihak luar" value={String(d.tertunggak_pihak_luar.length)} trend={d.tertunggak_pihak_luar.length ? 'down' : 'neutral'} />
                <StatCard label="Lot baharu daripada pecahan / penyatuan" value={String(new Set(d.sejarah_pecahan.map((s) => s.id_lot_baharu)).size)} />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card title="Urusan aktif mengikut jenis">
                    <ul className="space-y-2">
                        {d.aktif_mengikut_jenis.map((r) => (
                            <li key={r.kod} className="flex items-center justify-between gap-3 text-body-sm">
                                <Link href={`/admin/tsu/urusan?jenis_urusan=${r.kod}&terbuka=1`} className="text-txt-primary hover:underline">
                                    {r.nama}
                                </Link>
                                <span className="font-semibold">{r.nilai}</span>
                            </li>
                        ))}
                    </ul>
                </Card>
                <Card title="Purata tempoh penyempurnaan">
                    <ul className="space-y-2">
                        {d.purata_tempoh.map((r) => (
                            <li key={r.kod} className="flex items-center justify-between gap-3 text-body-sm">
                                <span>{r.nama}</span>
                                <span className="font-semibold">{r.purata_hari === null ? '—' : `${r.purata_hari} hari`} <span className="font-normal text-txt-black-500">({r.bilangan_selesai} selesai)</span></span>
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>

            <Card title="Urusan tertunggak di pihak luar">
                {d.tertunggak_pihak_luar.length === 0 ? (
                    <Kosong>Tiada urusan tertunggak di pihak luar.</Kosong>
                ) : (
                    <Table head={<><th>No. rujukan</th><th>Jenis</th><th>Peringkat</th><th>Pihak</th><th>Sasaran</th><th>Lewat</th></>}>
                        {d.tertunggak_pihak_luar.slice(0, 20).map((r) => (
                            <tr key={r.id}>
                                <td><Link href={`/admin/tsu/urusan/${r.id}`} className="text-txt-primary hover:underline">{r.no_rujukan_mais}</Link></td>
                                <td>{r.jenis_nama}</td>
                                <td>{r.peringkat}</td>
                                <td>{r.pihak}</td>
                                <td>{tarikh(r.tarikh_sasaran)}</td>
                                <td><Badge variant="danger">{r.hari_lewat} hari</Badge></td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>
        </PageContainer>
    );
}
