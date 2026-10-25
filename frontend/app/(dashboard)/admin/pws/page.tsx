'use client';

import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { StatCard } from '@/components/ui/StatCard';
import { Table } from '@/components/ui/Table';
import { JENIS_PERMOHONAN_LABEL, NAMA_RASMI, PENGHANTARAN_LABEL, pwsApi, ralat, type PermohonanPortal } from '@/lib/pws';
import { Card, Kosong, Memuat } from './_components/common';

/** Overview: submissions from the portal, payments by channel, integration health (24 h). */
export default function PwsRingkasanPage() {
    const { data, error, isLoading } = useQuery({ queryKey: ['pws', 'ringkasan'], queryFn: () => pwsApi.ringkasan().then((r) => r.data) });

    if (error) {
        return (
            <PageContainer>
                <Alert type="error">{ralat(error)}</Alert>
            </PageContainer>
        );
    }
    if (isLoading || !data) {
        return (
            <PageContainer>
                <Memuat />
            </PageContainer>
        );
    }
    const total = (jenis: string) => data.permohonan.filter((p) => p.jenis === jenis).reduce((a, p) => a + p.bilangan, 0);
    const gagal = data.permohonan.filter((p) => p.status_penghantaran === 'gagal').reduce((a, p) => a + p.bilangan, 0);
    const masuk = data.integrasi_24j.find((r) => r.arah === 'masuk');
    const keluar = data.integrasi_24j.find((r) => r.arah === 'keluar');

    return (
        <PageContainer>
            <p className="text-body-sm text-txt-black-700">
                {NAMA_RASMI} ialah sistem awam berasingan yang berhubung dengan SISDATA melalui API awam <code>/api/v1/pws/awam</code> sahaja. Permohonan diproses sebagai kes MOD-PER
                (hasrat wakaf) dan permohonan MOD-PJK (sewaan/pajakan).
            </p>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Permohonan hasrat wakaf" value={String(total('wakaf'))} />
                <StatCard label="Permohonan sewaan / pajakan" value={String(total('sewaan'))} />
                <StatCard label="Penghantaran gagal" value={String(gagal)} trend={gagal > 0 ? 'down' : 'neutral'} change={gagal > 0 ? 'perlu tindakan' : undefined} />
                <StatCard
                    label="Panggilan API (24 jam)"
                    value={String((masuk?.bilangan ?? 0) + (keluar?.bilangan ?? 0))}
                    change={`${(masuk?.gagal ?? 0) + (keluar?.gagal ?? 0)} ralat`}
                    trend={(masuk?.gagal ?? 0) + (keluar?.gagal ?? 0) > 0 ? 'down' : 'neutral'}
                />
            </div>
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card title="Permohonan mengikut status penghantaran" actions={<Link href="/admin/pws/pemantauan" className="text-body-sm font-medium text-txt-primary hover:underline">Lihat senarai</Link>}>
                    {data.permohonan.length === 0 ? (
                        <Kosong>Tiada permohonan daripada portal.</Kosong>
                    ) : (
                        <Table head={<><th>Jenis</th><th>Penghantaran</th><th className="text-right">Bilangan</th></>}>
                            {data.permohonan.map((p) => (
                                <tr key={`${p.jenis}-${p.status_penghantaran}`}>
                                    <td>{JENIS_PERMOHONAN_LABEL[p.jenis] ?? p.jenis}</td>
                                    <td>{PENGHANTARAN_LABEL[p.status_penghantaran as PermohonanPortal['status_penghantaran']] ?? p.status_penghantaran}</td>
                                    <td className="text-right">{p.bilangan}</td>
                                </tr>
                            ))}
                        </Table>
                    )}
                </Card>
                <Card title="Bayaran dalam talian mengikut saluran">
                    {data.bayaran.length === 0 ? (
                        <Kosong>Tiada bayaran dimulakan daripada portal.</Kosong>
                    ) : (
                        <Table head={<><th>Saluran</th><th>Status</th><th className="text-right">Bilangan</th><th className="text-right">Amaun (RM)</th></>}>
                            {data.bayaran.map((p) => (
                                <tr key={`${p.saluran}-${p.status}`}>
                                    <td className="uppercase">{p.saluran}</td>
                                    <td>{p.status}</td>
                                    <td className="text-right">{p.bilangan}</td>
                                    <td className="text-right">{p.amaun}</td>
                                </tr>
                            ))}
                        </Table>
                    )}
                </Card>
            </div>
        </PageContainer>
    );
}
