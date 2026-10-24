'use client';

import { use, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { LinkButton } from '@/components/ui/Button';
import { api } from '@/lib/api';
import { formatDateTime, KATEGORI_TUGASAN, STATUS_LABEL, statusVariant, type Paginated, type Tugasan, type TugasanLarian } from '@/lib/adm';

/* SRS-ADM-08: run history of one job. */
export default function SejarahTugasanPage({ params }: { params: Promise<{ id: string }> }) {
    const { id } = use(params);
    const [status, setStatus] = useState('');
    const [page, setPage] = useState(1);

    const job = useQuery({ queryKey: ['adm', 'tugasan', id], queryFn: () => api.get<{ data: Tugasan }>(`/adm/tugasan/${id}`) });
    const runs = useQuery({
        queryKey: ['adm', 'tugasan', id, 'larian', status, page],
        queryFn: () => api.get<Paginated<TugasanLarian>>(`/adm/tugasan/${id}/larian?page=${page}${status ? `&status=${status}` : ''}`),
        refetchInterval: 10000,
    });
    const t = job.data?.data;

    return (
        <PageContainer>
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="font-heading font-semibold text-body-lg text-txt-black-900">{t?.nama ?? '…'}</h3>
                    {t && (
                        <p className="text-body-sm text-txt-black-500">
                            {KATEGORI_TUGASAN[t.kategori] ?? t.kategori} · <code>{t.arahan}</code> ·{' '}
                            {t.jenis_jadual === 'cron' ? <code>{t.ungkapan_cron}</code> : `setiap ${t.selang_minit} min`} · cubaan semula {t.cubaan_semula} ·
                            seterusnya {formatDateTime(t.seterusnya_pada)}
                        </p>
                    )}
                </div>
                <LinkButton href="/admin/pentadbiran/tugasan-berjadual" variant="secondary" size="sm">
                    Kembali
                </LinkButton>
            </div>

            <div className="flex flex-wrap gap-2" role="group" aria-label="Tapis status">
                {['', 'berjaya', 'gagal', 'dilangkau', 'berjalan', 'beratur'].map((s) => (
                    <Button
                        key={s || 'semua'}
                        size="sm"
                        variant={status === s ? 'primary' : 'secondary'}
                        onClick={() => {
                            setStatus(s);
                            setPage(1);
                        }}
                    >
                        {s ? STATUS_LABEL[s] : 'Semua'}
                    </Button>
                ))}
            </div>

            <Table
                head={
                    <>
                        <th>Mula</th>
                        <th>Tamat</th>
                        <th>Tempoh</th>
                        <th>Status</th>
                        <th>Rekod diproses</th>
                        <th>Pencetus</th>
                        <th>Mesej ralat</th>
                    </>
                }
            >
                {(runs.data?.data ?? []).length === 0 ? (
                    <tr>
                        <td colSpan={7} className="text-center text-txt-black-500">{runs.isLoading ? 'Memuatkan…' : 'Tiada larian.'}</td>
                    </tr>
                ) : (
                    runs.data?.data.map((r) => (
                        <tr key={r.id}>
                            <td className="whitespace-nowrap">{formatDateTime(r.tarikh_mula)}</td>
                            <td className="whitespace-nowrap">{formatDateTime(r.tarikh_tamat)}</td>
                            <td>{r.tempoh_saat === null ? '—' : `${r.tempoh_saat.toFixed(1)} s`}</td>
                            <td>
                                <Badge variant={statusVariant(r.status)}>{STATUS_LABEL[r.status] ?? r.status}</Badge>
                                {r.cubaan > 1 && <span className="ms-1 text-body-xs text-txt-black-500">cubaan {r.cubaan}</span>}
                            </td>
                            <td>{r.bilangan_rekod ?? '—'}</td>
                            <td>{r.pencetus === 'manual' ? 'Manual' : 'Jadual'}</td>
                            <td className="max-w-md text-body-xs break-words">{r.mesej_ralat ?? ''}</td>
                        </tr>
                    ))
                )}
            </Table>

            {runs.data && runs.data.last_page > 1 && (
                <div className="flex gap-2">
                    <Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                        Sebelum
                    </Button>
                    <Button size="sm" variant="secondary" disabled={page >= runs.data.last_page} onClick={() => setPage((p) => p + 1)}>
                        Seterusnya
                    </Button>
                </div>
            )}
        </PageContainer>
    );
}
