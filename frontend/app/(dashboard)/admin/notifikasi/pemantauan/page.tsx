'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Pagination } from '@/components/ui/Pagination';
import { StatCard } from '@/components/ui/StatCard';
import { Table } from '@/components/ui/Table';
import { ApiError } from '@/lib/api';
import {
    fetchAbilities,
    fetchDeliveries,
    fetchMonitoring,
    retryDelivery,
    SALURAN_LABEL,
    STATUS_LABEL,
    tarikhMasa,
    type Penghantaran,
    type Saluran,
} from '@/lib/ntf';

const selectClass =
    'rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm';

function statusBadge(status: string) {
    const variant = status === 'gagal_muktamad' ? 'danger' : status === 'gagal' ? 'warning' : ['dihantar', 'diterima', 'dibaca'].includes(status) ? 'success' : 'neutral';
    return <Badge variant={variant}>{STATUS_LABEL[status] ?? status}</Badge>;
}

export default function MonitoringPage() {
    const [hari, setHari] = useState(7);
    const [log, setLog] = useState<{ saluran?: string; status?: string; page: number }>({ page: 1 });
    const queryClient = useQueryClient();

    const { data, error } = useQuery({ queryKey: ['ntf', 'pemantauan', hari], queryFn: () => fetchMonitoring(hari), refetchInterval: 30_000 });
    const { data: deliveries } = useQuery({ queryKey: ['ntf', 'penghantaran', log], queryFn: () => fetchDeliveries(log), enabled: !error });
    const { data: abilities } = useQuery({ queryKey: ['ntf', 'kebenaran'], queryFn: fetchAbilities, staleTime: 60_000 });
    const canRetry = abilities?.data['ntf.saluran.kemas_kini'] ?? false;

    const retry = useMutation({
        mutationFn: retryDelivery,
        onSuccess: (res) => {
            toast.success(res.message);
            queryClient.invalidateQueries({ queryKey: ['ntf'] });
        },
        onError: (e) => toast.error(e instanceof ApiError ? e.message : 'Gagal.'),
    });

    if (error instanceof ApiError && error.status === 403) {
        return (
            <PageContainer>
                <Alert type="error">Anda tidak mempunyai kebenaran untuk melihat pemantauan notifikasi.</Alert>
            </PageContainer>
        );
    }

    const m = data?.data;

    function row(p: Penghantaran) {
        return (
            <tr key={p.id}>
                <td className="whitespace-nowrap">#{p.id}</td>
                <td>
                    <div className="font-medium">{p.jenis.nama}</div>
                    <div className="max-w-xs truncate text-body-xs text-txt-black-500">{p.subjek}</div>
                </td>
                <td>{p.penerima?.nama ?? '—'}</td>
                <td className="whitespace-nowrap">
                    {SALURAN_LABEL[p.saluran]}
                    {p.ringkasan && <span className="ms-1 text-body-xs text-txt-black-500">(ringkasan)</span>}
                </td>
                <td>{statusBadge(p.status)}</td>
                <td className="text-center">{p.bilangan_cubaan}</td>
                <td>
                    {p.kod_ralat ? (
                        <code className="text-body-xs text-txt-danger" title={p.mesej_ralat ?? undefined}>
                            {p.kod_ralat}
                        </code>
                    ) : (
                        '—'
                    )}
                </td>
                <td className="whitespace-nowrap text-body-xs">
                    <div>Hantar: {tarikhMasa(p.tarikh_hantar)}</div>
                    <div>Terima: {tarikhMasa(p.tarikh_terima)}</div>
                    <div>Baca: {tarikhMasa(p.tarikh_baca)}</div>
                </td>
                <td className="text-right">
                    {canRetry && (p.status === 'gagal' || p.status === 'gagal_muktamad') && (
                        <button type="button" className="text-body-sm font-medium text-primary-600 hover:underline" onClick={() => retry.mutate(p.id)}>
                            Cuba semula
                        </button>
                    )}
                </td>
            </tr>
        );
    }

    const head = (
        <>
            <th>ID</th>
            <th>Jenis</th>
            <th>Penerima</th>
            <th>Saluran</th>
            <th>Status</th>
            <th>Cubaan</th>
            <th>Kod ralat</th>
            <th>Masa</th>
            <th />
        </>
    );

    return (
        <PageContainer>
            <div className="flex items-center justify-between gap-3">
                <p className="text-body-sm text-txt-black-700">Dikemas kini setiap 30 saat.</p>
                <select className={selectClass} value={hari} onChange={(e) => setHari(Number(e.target.value))} aria-label="Tempoh">
                    <option value={1}>24 jam</option>
                    <option value={7}>7 hari</option>
                    <option value={30}>30 hari</option>
                </select>
            </div>

            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard label="Dalam baris gilir" value={String(m?.baris_gilir.beratur ?? '—')} />
                <StatCard label="Menunggu cuba semula" value={String(m?.baris_gilir.menunggu_cuba_semula ?? '—')} />
                <StatCard label="Ditahan untuk ringkasan" value={String(m?.baris_gilir.ditahan_ringkasan ?? '—')} />
                <StatCard label="Kerja dalam Redis" value={m ? (m.baris_gilir.redis === null ? 'Tiada sambungan' : String(m.baris_gilir.redis)) : '—'} />
            </div>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-4 sm:p-6 space-y-4">
                <h3 className="font-heading text-body-lg font-semibold text-txt-black-900">Kadar kejayaan mengikut saluran</h3>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {m?.saluran.map((s) => (
                        <div key={s.saluran} className="rounded-lg border border-otl-gray-200 p-4 space-y-2">
                            <div className="flex items-center justify-between">
                                <span className="text-body-sm font-medium text-txt-black-900">{s.label}</span>
                                <span className="text-body-sm font-semibold text-txt-black-900">{s.kadar_kejayaan === null ? '—' : `${s.kadar_kejayaan}%`}</span>
                            </div>
                            <div className="h-2 rounded-full bg-bg-washed" role="img" aria-label={`Kadar kejayaan ${s.label}: ${s.kadar_kejayaan ?? 0}%`}>
                                <div className="h-2 rounded-full bg-success-600" style={{ width: `${s.kadar_kejayaan ?? 0}%` }} />
                            </div>
                            <p className="text-body-xs text-txt-black-500">
                                {s.jumlah} jumlah · {s.berjaya} berjaya · {s.gagal_muktamad} gagal · {s.dalam_proses} dalam proses
                            </p>
                        </div>
                    ))}
                </div>
                <p className="text-body-xs text-txt-black-500">Eskalasi dalam tempoh ini: {m?.eskalasi ?? '—'}</p>
            </section>

            <section className="space-y-3">
                <h3 className="font-heading text-body-lg font-semibold text-txt-black-900">Kegagalan terkini</h3>
                <Table head={head}>
                    {m && m.kegagalan_terkini.length === 0 ? (
                        <tr>
                            <td colSpan={9} className="py-6 text-center text-txt-black-500">
                                Tiada kegagalan.
                            </td>
                        </tr>
                    ) : (
                        m?.kegagalan_terkini.map(row)
                    )}
                </Table>
            </section>

            <section className="space-y-3">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h3 className="font-heading text-body-lg font-semibold text-txt-black-900">Log penghantaran</h3>
                    <div className="flex gap-2">
                        <select className={selectClass} aria-label="Saluran" value={log.saluran ?? ''} onChange={(e) => setLog({ ...log, saluran: e.target.value || undefined, page: 1 })}>
                            <option value="">Semua saluran</option>
                            {(Object.keys(SALURAN_LABEL) as Saluran[]).map((s) => (
                                <option key={s} value={s}>
                                    {SALURAN_LABEL[s]}
                                </option>
                            ))}
                        </select>
                        <select className={selectClass} aria-label="Status" value={log.status ?? ''} onChange={(e) => setLog({ ...log, status: e.target.value || undefined, page: 1 })}>
                            <option value="">Semua status</option>
                            {Object.entries(STATUS_LABEL).map(([k, v]) => (
                                <option key={k} value={k}>
                                    {v}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>
                <Table head={head}>{deliveries?.data.map(row)}</Table>
                {deliveries && deliveries.meta.last_page > 1 && (
                    <Pagination
                        currentPage={deliveries.meta.current_page}
                        lastPage={deliveries.meta.last_page}
                        onPageChange={(page) => setLog({ ...log, page })}
                        firstItem={deliveries.meta.from ?? 0}
                        lastItem={deliveries.meta.to ?? 0}
                        total={deliveries.meta.total}
                    />
                )}
            </section>
        </PageContainer>
    );
}
