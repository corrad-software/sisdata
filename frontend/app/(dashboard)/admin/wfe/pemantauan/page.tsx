'use client';

import Link from 'next/link';
import { Suspense, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { PageContainer } from '@/components/PageContainer';
import { StatCard } from '@/components/ui/StatCard';
import { Table } from '@/components/ui/Table';
import { Button, AnchorButton } from '@/components/ui/Button';
import { errorMessage, fmtDateTime, EVENT_LABEL, STATUS_LABEL, stepUpUrl, useCan, useMfa, type Paged, type WfeInstance } from '@/lib/wfe';
import { Card, StatusBadge } from '../_components/common';
import { MfaResult } from '../_components/MfaResult';

interface Dashboard {
    aktif: number;
    ikut_status: Record<string, number>;
    tugasan_tertangguh: number;
    tugasan_lewat: number;
    ikut_peringkat: Array<{ kod_aliran: string; peringkat: string; nama_peringkat: string; jumlah: number; lewat: number }>;
    ikut_pelulus: Array<{ penerima: string; label: string; jumlah: number; lewat: number }>;
    purata_penyelesaian: Array<{ kod_aliran: string; jumlah: number; purata_jam: number }>;
    tindakan_terkini: Array<{ id_instans: string; peristiwa: string; peringkat: string | null; pengguna: string | null; bagi_pihak: string | null; justifikasi: string | null; tarikh_masa: string }>;
}

interface Migration {
    id: number;
    id_instans: string;
    tajuk: string;
    kod_aliran: string;
    dari_versi: number;
    ke_versi: number;
    sebab: string;
    created_at: string;
}

/** SRS-WFE-13: monitoring dashboard with drill-down (SRS-WFE-15). */
export default function PemantauanPage() {
    const can = useCan();
    const [filter, setFilter] = useState<{ status?: string; kod_aliran?: string; peringkat?: string }>({ status: 'berjalan' });

    const dash = useQuery({
        queryKey: ['wfe', 'pemantauan'],
        queryFn: () => api.get<{ data: Dashboard }>('/wfe/pemantauan').then((r) => r.data),
        enabled: can('wfe.pemantauan.lihat'),
    });
    const qs = new URLSearchParams(Object.entries(filter).filter(([, v]) => v) as [string, string][]).toString();
    const list = useQuery({
        queryKey: ['wfe', 'instans', 'senarai', qs],
        queryFn: () => api.get<Paged<WfeInstance>>(`/wfe/instans?${qs}`),
        enabled: can('wfe.pemantauan.lihat'),
    });
    const d = dash.data;

    return (
        <PageContainer>
            <Suspense>
                <MfaResult />
            </Suspense>

            {can('wfe.definisi.lulus') && <PendingMigrations />}

            {d && (
                <>
                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <StatCard label="Aliran aktif" value={String(d.aktif)} />
                        <StatCard label="Tugasan tertangguh" value={String(d.tugasan_tertangguh)} />
                        <StatCard label="Melepasi SLA" value={String(d.tugasan_lewat)} trend={d.tugasan_lewat ? 'down' : 'neutral'} change={d.tugasan_lewat ? 'perlu perhatian' : undefined} />
                        <StatCard label="Selesai (lulus/tolak)" value={String((d.ikut_status.lulus ?? 0) + (d.ikut_status.tolak ?? 0))} />
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <Card title="Tertangguh mengikut peringkat">
                            <Table head={<><th>Aliran / peringkat</th><th className="text-right">Jumlah</th><th className="text-right">Lewat</th></>}>
                                {d.ikut_peringkat.length === 0 ? (
                                    <tr><td colSpan={3} className="text-center text-txt-black-500">Tiada.</td></tr>
                                ) : (
                                    d.ikut_peringkat.map((r) => (
                                        <tr key={r.kod_aliran + r.peringkat}>
                                            <td>
                                                <button
                                                    type="button"
                                                    className="text-left text-primary-600 hover:underline"
                                                    onClick={() => setFilter({ status: 'berjalan', kod_aliran: r.kod_aliran, peringkat: r.peringkat })}
                                                >
                                                    {r.nama_peringkat}
                                                </button>
                                                <div className="text-body-xs text-txt-black-500">{r.kod_aliran}</div>
                                            </td>
                                            <td className="text-right">{r.jumlah}</td>
                                            <td className="text-right">{r.lewat}</td>
                                        </tr>
                                    ))
                                )}
                            </Table>
                        </Card>

                        <Card title="Tertangguh mengikut pelulus">
                            <Table head={<><th>Pelulus</th><th className="text-right">Jumlah</th><th className="text-right">Lewat</th></>}>
                                {d.ikut_pelulus.length === 0 ? (
                                    <tr><td colSpan={3} className="text-center text-txt-black-500">Tiada.</td></tr>
                                ) : (
                                    d.ikut_pelulus.map((r) => (
                                        <tr key={r.penerima}>
                                            <td>
                                                {r.label}
                                                <div className="text-body-xs text-txt-black-500">{r.penerima.split(':')[0]}</div>
                                            </td>
                                            <td className="text-right">{r.jumlah}</td>
                                            <td className="text-right">{r.lewat}</td>
                                        </tr>
                                    ))
                                )}
                            </Table>
                        </Card>

                        <Card title="Purata masa penyelesaian mengikut jenis permohonan">
                            <Table head={<><th>Aliran</th><th className="text-right">Selesai</th><th className="text-right">Purata (jam)</th></>}>
                                {d.purata_penyelesaian.length === 0 ? (
                                    <tr><td colSpan={3} className="text-center text-txt-black-500">Tiada data.</td></tr>
                                ) : (
                                    d.purata_penyelesaian.map((r) => (
                                        <tr key={r.kod_aliran}>
                                            <td>
                                                <button type="button" className="text-primary-600 hover:underline" onClick={() => setFilter({ kod_aliran: r.kod_aliran })}>
                                                    {r.kod_aliran}
                                                </button>
                                            </td>
                                            <td className="text-right">{r.jumlah}</td>
                                            <td className="text-right">{r.purata_jam.toLocaleString('ms-MY')}</td>
                                        </tr>
                                    ))
                                )}
                            </Table>
                        </Card>

                        <Card title="Tindakan terkini">
                            <ul className="divide-y divide-otl-gray-200">
                                {d.tindakan_terkini.map((t, idx) => (
                                    <li key={idx} className="py-2 text-body-sm">
                                        <Link href={`/admin/wfe/instans/${t.id_instans}`} className="font-medium text-primary-600 hover:underline">
                                            {EVENT_LABEL[t.peristiwa] ?? t.peristiwa}
                                        </Link>
                                        <span className="text-txt-black-500">
                                            {' '}
                                            · {t.peringkat ?? '-'} · {t.pengguna ?? 'sistem'}
                                            {t.bagi_pihak ? ` (bagi pihak ${t.bagi_pihak})` : ''} · {fmtDateTime(t.tarikh_masa)}
                                        </span>
                                        {t.justifikasi && <p className="text-body-xs text-txt-black-700">“{t.justifikasi}”</p>}
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    </div>
                </>
            )}

            {can('wfe.pemantauan.lihat') && (
                <Card
                    title="Instans aliran kerja"
                    actions={
                        <div className="flex flex-wrap gap-2 items-center">
                            <select
                                aria-label="Status"
                                value={filter.status ?? ''}
                                onChange={(e) => setFilter((f) => ({ ...f, status: e.target.value || undefined }))}
                                className="rounded-md border-otl-gray-300 bg-bg-white text-body-sm"
                            >
                                <option value="">Semua status</option>
                                {['berjalan', 'lulus', 'tolak', 'ditarik_balik', 'dibatalkan'].map((s) => (
                                    <option key={s} value={s}>
                                        {STATUS_LABEL[s]}
                                    </option>
                                ))}
                            </select>
                            {(filter.kod_aliran || filter.peringkat) && (
                                <Button size="sm" variant="secondary" onClick={() => setFilter({ status: filter.status })}>
                                    Kosongkan penapis {filter.kod_aliran ?? ''} {filter.peringkat ?? ''}
                                </Button>
                            )}
                        </div>
                    }
                >
                    <Table head={<><th>Perkara</th><th>Aliran</th><th>Status</th><th>Peringkat</th><th>Daerah</th><th>Dimulakan</th><th>Rekod sumber</th></>}>
                        {(list.data?.data ?? []).map((i) => (
                            <tr key={i.id}>
                                <td>
                                    <Link href={`/admin/wfe/instans/${i.id}`} className="font-medium text-primary-600 hover:underline">
                                        {i.tajuk}
                                    </Link>
                                </td>
                                <td>{i.kod_aliran} v{i.no_versi}</td>
                                <td><StatusBadge status={i.keadaan ?? i.status} /></td>
                                <td>{i.peringkat_semasa ?? '-'}</td>
                                <td>{i.kod_daerah ?? '-'}</td>
                                <td className="whitespace-nowrap">{fmtDateTime(i.tarikh_mula)}</td>
                                <td>{i.pautan ? <Link href={i.pautan} className="text-primary-600 hover:underline">Buka</Link> : '-'}</td>
                            </tr>
                        ))}
                    </Table>
                </Card>
            )}
        </PageContainer>
    );
}

function PendingMigrations() {
    const queryClient = useQueryClient();
    const { data: mfa } = useMfa();
    const { data } = useQuery({
        queryKey: ['wfe', 'pindah-versi'],
        queryFn: () => api.get<{ data: Migration[] }>('/wfe/pindah-versi').then((r) => r.data),
    });
    const decide = useMutation({
        mutationFn: ({ id, lulus }: { id: number; lulus: boolean }) => api.post<{ message: string }>(`/wfe/pindah-versi/${id}/keputusan`, { lulus }),
        onSuccess: (r) => {
            toast.success(r.message);
            queryClient.invalidateQueries({ queryKey: ['wfe'] });
        },
        onError: (e) => toast.error(errorMessage(e)),
    });

    if (!data?.length) return null;

    return (
        <Card title="Permohonan pindah versi menunggu kelulusan" actions={!mfa?.sah && <AnchorButton size="sm" href={stepUpUrl('/admin/wfe/pemantauan')}>Sahkan dengan MFA</AnchorButton>}>
            <Table head={<><th>Instans</th><th>Versi</th><th>Sebab</th><th>Dimohon</th><th className="text-right">Keputusan</th></>}>
                {data.map((m) => (
                    <tr key={m.id}>
                        <td><Link href={`/admin/wfe/instans/${m.id_instans}`} className="text-primary-600 hover:underline">{m.tajuk}</Link></td>
                        <td>v{m.dari_versi} → v{m.ke_versi}</td>
                        <td>{m.sebab}</td>
                        <td className="whitespace-nowrap">{fmtDateTime(m.created_at)}</td>
                        <td className="text-right whitespace-nowrap space-x-2">
                            <Button size="sm" disabled={!mfa?.sah || decide.isPending} onClick={() => decide.mutate({ id: m.id, lulus: true })}>Lulus</Button>
                            <Button size="sm" variant="danger" disabled={decide.isPending} onClick={() => decide.mutate({ id: m.id, lulus: false })}>Tolak</Button>
                        </td>
                    </tr>
                ))}
            </Table>
        </Card>
    );
}
