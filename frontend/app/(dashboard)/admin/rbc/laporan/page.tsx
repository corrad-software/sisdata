'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { StatCard } from '@/components/ui/StatCard';
import { AnchorButton } from '@/components/ui/Button';
import { STATUS_LABEL, exportUrl, rbc } from '@/lib/rbc';
import type { AccountStatus } from '@/lib/types';
import { Card, EmptyRow, ScrollX, Select, StatusBadge } from '../_components/ui';

/** SRS-RBC-26: access reports. */
export default function ReportPage() {
    const [days, setDays] = useState(30);
    const { data, isLoading } = useQuery({ queryKey: ['rbc', 'laporan', days], queryFn: () => rbc.report(days) });
    const report = data?.data;

    return (
        <PageContainer>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <Select id="tempoh" label="Tempoh log masuk gagal" value={days} onChange={(e) => setDays(Number(e.target.value))} className="sm:w-56">
                    <option value={7}>7 hari</option>
                    <option value={30}>30 hari</option>
                    <option value={90}>90 hari</option>
                    <option value={365}>365 hari</option>
                </Select>
                <div className="flex gap-2">
                    <AnchorButton variant="secondary" href={exportUrl('laporan/eksport', { hari: days, format: 'xlsx' })}>
                        Eksport Excel
                    </AnchorButton>
                    <AnchorButton variant="secondary" href={exportUrl('laporan/eksport', { hari: days, format: 'pdf' })}>
                        Eksport PDF
                    </AnchorButton>
                </div>
            </div>

            {isLoading || !report ? (
                <p className="text-body-sm text-txt-black-500">Memuatkan…</p>
            ) : (
                <>
                    <div className="grid gap-4 grid-cols-2 lg:grid-cols-4">
                        {(Object.keys(STATUS_LABEL) as AccountStatus[]).map((s) => (
                            <StatCard key={s} label={`Pengguna ${STATUS_LABEL[s]}`} value={String(report.mengikut_status[s] ?? 0)} />
                        ))}
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <Card title="Pengguna mengikut peranan">
                            <ScrollX>
                                <Table head={<><th>Peranan</th><th className="text-right">Aktif</th><th className="text-right">Jumlah</th></>}>
                                    {report.mengikut_peranan.map((r) => (
                                        <tr key={r.kod}>
                                            <td>{r.nama}</td>
                                            <td className="text-right tabular-nums">{r.aktif}</td>
                                            <td className="text-right tabular-nums">{r.jumlah}</td>
                                        </tr>
                                    ))}
                                </Table>
                            </ScrollX>
                        </Card>
                        <Card title="Pengguna mengikut bahagian">
                            <ScrollX>
                                <Table head={<><th>Bahagian</th><th className="text-right">Aktif</th><th className="text-right">Jumlah</th></>}>
                                    {report.mengikut_bahagian.length === 0 ? (
                                        <EmptyRow colSpan={3} />
                                    ) : (
                                        report.mengikut_bahagian.map((r) => (
                                            <tr key={r.bahagian ?? '-'}>
                                                <td>{r.bahagian ?? 'Tiada bahagian'}</td>
                                                <td className="text-right tabular-nums">{r.aktif}</td>
                                                <td className="text-right tabular-nums">{r.jumlah}</td>
                                            </tr>
                                        ))
                                    )}
                                </Table>
                            </ScrollX>
                        </Card>
                    </div>

                    <Card title={`Pengguna tidak aktif (> ${report.had_tidak_aktif_hari} hari) atau bukan Aktif`}>
                        <ScrollX>
                            <Table head={<><th>Pengguna</th><th>Status</th><th>Log masuk terakhir</th><th>Tamat capaian</th></>}>
                                {report.pengguna_tidak_aktif.length === 0 ? (
                                    <EmptyRow colSpan={4} />
                                ) : (
                                    report.pengguna_tidak_aktif.map((u) => (
                                        <tr key={u.id}>
                                            <td>
                                                <Link href={`/admin/users/${u.id}`} className="font-medium text-primary-600 hover:text-primary-700">
                                                    {u.name}
                                                </Link>
                                                <div className="text-body-xs text-txt-black-500">{u.email}</div>
                                            </td>
                                            <td>
                                                <StatusBadge status={u.status_akaun} />
                                            </td>
                                            <td>{u.tarikh_log_masuk_akhir ?? '—'}</td>
                                            <td>{u.tarikh_tamat_capaian ?? '—'}</td>
                                        </tr>
                                    ))
                                )}
                            </Table>
                        </ScrollX>
                    </Card>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <Card title={`Log masuk gagal mengikut pengguna (${report.tempoh_hari} hari)`}>
                            <ScrollX>
                                <Table head={<><th>Pengecam</th><th className="text-right">Bilangan</th><th>Terakhir</th></>}>
                                    {report.gagal_mengikut_pengguna.length === 0 ? (
                                        <EmptyRow colSpan={3} />
                                    ) : (
                                        report.gagal_mengikut_pengguna.map((r) => (
                                            <tr key={r.pengecam}>
                                                <td className="break-all">{r.pengecam}</td>
                                                <td className="text-right tabular-nums">{r.bilangan}</td>
                                                <td className="whitespace-nowrap">{r.terakhir}</td>
                                            </tr>
                                        ))
                                    )}
                                </Table>
                            </ScrollX>
                        </Card>
                        <Card title={`Log masuk gagal mengikut alamat IP (${report.tempoh_hari} hari)`}>
                            <ScrollX>
                                <Table head={<><th>Alamat IP</th><th className="text-right">Bilangan</th><th>Terakhir</th></>}>
                                    {report.gagal_mengikut_ip.length === 0 ? (
                                        <EmptyRow colSpan={3} />
                                    ) : (
                                        report.gagal_mengikut_ip.map((r) => (
                                            <tr key={r.alamat_ip}>
                                                <td>{r.alamat_ip}</td>
                                                <td className="text-right tabular-nums">{r.bilangan}</td>
                                                <td className="whitespace-nowrap">{r.terakhir}</td>
                                            </tr>
                                        ))
                                    )}
                                </Table>
                            </ScrollX>
                        </Card>
                    </div>

                    <Card title="Kebenaran setiap peranan" actions={<Link href="/admin/rbc/matriks" className="text-primary-600 hover:text-primary-700 text-body-sm font-medium">Lihat matriks penuh</Link>}>
                        <div className="space-y-3">
                            {report.kebenaran_peranan.map((r) => (
                                <details key={r.kod} className="border border-otl-gray-200 rounded-md px-4 py-2">
                                    <summary className="cursor-pointer text-body-sm font-medium">
                                        {r.nama} — {r.kebenaran.length} kebenaran
                                    </summary>
                                    <p className="mt-2 text-body-xs text-txt-black-700 break-words">{r.kebenaran.join(', ') || 'Tiada kebenaran.'}</p>
                                </details>
                            ))}
                        </div>
                    </Card>
                </>
            )}
        </PageContainer>
    );
}
