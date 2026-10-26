'use client';

import Link from 'next/link';
import { Suspense, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { fmtDateTime, type Paged, type WfeTask } from '@/lib/wfe';
import { Card, DecisionDialog, SlaBadge, StatusBadge } from '../_components/common';
import { MfaResult } from '../_components/MfaResult';

type Action = 'lulus' | 'tolak' | 'kembali';

/** SRS-WFE-14: my task board with SLA remaining and quick approve / reject / return. */
export default function TugasanPage() {
    const [tab, setTab] = useState<'menunggu' | 'selesai'>('menunggu');
    const [selected, setSelected] = useState<{ task: WfeTask; action: Action } | null>(null);

    const { data, isLoading } = useQuery({
        queryKey: ['wfe', 'tugasan', tab],
        queryFn: () => api.get<Paged<WfeTask>>(`/wfe/tugasan${tab === 'selesai' ? '?status=selesai' : ''}`),
    });
    const tasks = data?.data ?? [];

    return (
        <PageContainer>
            <Suspense>
                <MfaResult />
            </Suspense>

            <Card
                actions={
                    <div className="flex gap-2" role="tablist" aria-label="Penapis tugasan" data-panduan="wfe-penapis">
                        <Button size="sm" variant={tab === 'menunggu' ? 'primary' : 'secondary'} onClick={() => setTab('menunggu')}>
                            Menunggu tindakan{tab === 'menunggu' && data ? ` (${data.meta.total})` : ''}
                        </Button>
                        <Button size="sm" variant={tab === 'selesai' ? 'primary' : 'secondary'} onClick={() => setTab('selesai')}>
                            Telah diambil tindakan
                        </Button>
                    </div>
                }
                title={tab === 'menunggu' ? 'Tugasan menunggu tindakan' : 'Tindakan saya'}
            >
                {/* Mobile: cards */}
                <ul className="space-y-3 md:hidden" data-panduan="wfe-senarai">
                    {isLoading && <li className="text-body-sm text-txt-black-500">Memuatkan…</li>}
                    {!isLoading && tasks.length === 0 && <li className="text-body-sm text-txt-black-500">Tiada tugasan.</li>}
                    {tasks.map((t) => (
                        <li key={t.id} className="rounded-lg border border-otl-gray-200 p-4 space-y-2">
                            <Link href={`/admin/wfe/tugasan/${t.id}`} className="font-medium text-primary-600 hover:underline">
                                {t.tajuk}
                            </Link>
                            <p className="text-body-xs text-txt-black-500">
                                {t.peringkat?.nama ?? 'Tugasan susulan'} · {t.instans.pemohon}
                            </p>
                            <div className="flex flex-wrap gap-2">
                                {tab === 'menunggu' ? <SlaBadge task={t} /> : <StatusBadge status={t.keputusan ?? t.status} />}
                                {t.status === 'ditangguh' && <StatusBadge status="ditangguh" />}
                                {t.peringkat?.perlu_mfa && <Badge variant="warning">MFA</Badge>}
                                {t.aras_eskalasi > 0 && <Badge variant="danger">Eskalasi aras {t.aras_eskalasi}</Badge>}
                            </div>
                            {t.boleh_bertindak && t.jenis === 'kelulusan' && <QuickActions task={t} onPick={(action) => setSelected({ task: t, action })} />}
                        </li>
                    ))}
                </ul>

                {/* Desktop: table */}
                <div className="hidden md:block" data-panduan="wfe-senarai">
                    <Table
                        head={
                            <>
                                <th>Perkara</th>
                                <th>Peringkat</th>
                                <th>Pemohon</th>
                                <th>{tab === 'menunggu' ? 'Baki SLA' : 'Keputusan'}</th>
                                <th>Diterima</th>
                                <th className="text-right">Tindakan pantas</th>
                            </>
                        }
                    >
                        {isLoading ? (
                            <tr>
                                <td colSpan={6} className="text-center text-txt-black-500 py-6">
                                    Memuatkan…
                                </td>
                            </tr>
                        ) : tasks.length === 0 ? (
                            <tr>
                                <td colSpan={6} className="text-center text-txt-black-500 py-6">
                                    Tiada tugasan.
                                </td>
                            </tr>
                        ) : (
                            tasks.map((t) => (
                                <tr key={t.id}>
                                    <td>
                                        <Link href={`/admin/wfe/tugasan/${t.id}`} className="font-medium text-primary-600 hover:underline">
                                            {t.tajuk}
                                        </Link>
                                        <div className="text-body-xs text-txt-black-500">
                                            {t.instans.kod_aliran}
                                            {t.bagi_pihak ? ` · bagi pihak ${t.bagi_pihak}` : ''}
                                        </div>
                                    </td>
                                    <td>
                                        {t.peringkat?.nama ?? 'Tugasan susulan'}
                                        <div className="flex gap-1 mt-1">
                                            {t.peringkat?.perlu_mfa && <Badge variant="warning">MFA</Badge>}
                                            {t.status === 'ditangguh' && <StatusBadge status="ditangguh" />}
                                            {t.aras_eskalasi > 0 && <Badge variant="danger">Eskalasi {t.aras_eskalasi}</Badge>}
                                        </div>
                                    </td>
                                    <td>{t.instans.pemohon}</td>
                                    <td>{tab === 'menunggu' ? <SlaBadge task={t} /> : <StatusBadge status={t.keputusan ?? t.status} />}</td>
                                    <td className="whitespace-nowrap">{fmtDateTime(tab === 'menunggu' ? t.tarikh_tugasan : t.diselesaikan_pada)}</td>
                                    <td className="text-right">
                                        {t.boleh_bertindak && t.jenis === 'kelulusan' ? (
                                            <QuickActions task={t} onPick={(action) => setSelected({ task: t, action })} />
                                        ) : t.boleh_bertindak ? (
                                            <Link href={`/admin/wfe/tugasan/${t.id}`} className="text-primary-600 text-body-sm font-medium">
                                                Buka
                                            </Link>
                                        ) : null}
                                    </td>
                                </tr>
                            ))
                        )}
                    </Table>
                </div>
            </Card>

            <DecisionDialog task={selected?.task ?? null} action={selected?.action ?? null} onClose={() => setSelected(null)} returnPath="/admin/wfe/tugasan" />
        </PageContainer>
    );
}

function QuickActions({ task, onPick }: { task: WfeTask; onPick: (a: Action) => void }) {
    return (
        <div className="flex flex-wrap gap-2 md:justify-end" data-panduan="wfe-tindakan">
            <Button size="sm" onClick={() => onPick('lulus')} aria-label={`Lulus ${task.tajuk}`}>
                Lulus
            </Button>
            <Button size="sm" variant="danger" onClick={() => onPick('tolak')} aria-label={`Tolak ${task.tajuk}`}>
                Tolak
            </Button>
            <Button size="sm" variant="secondary" onClick={() => onPick('kembali')} aria-label={`Kembalikan ${task.tajuk}`}>
                Kembalikan
            </Button>
        </div>
    );
}
