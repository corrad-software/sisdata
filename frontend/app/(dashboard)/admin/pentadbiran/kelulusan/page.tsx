'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { Modal } from '@/components/ui/Modal';
import { Textarea } from '@/components/ui/Textarea';
import { api } from '@/lib/api';
import { errorText, fetchKelulusan, formatDateTime, STATUS_LABEL, statusVariant, type Kelulusan } from '@/lib/adm';

const JENIS: Record<string, string> = {
    parameter: 'Parameter',
    parameter_reset: 'Parameter (set semula)',
    integrasi: 'Tetapan integrasi',
    rahsia: 'Rahsia sistem',
};

function show(v: unknown): string {
    if (v === null || v === undefined) return '—';
    return typeof v === 'object' ? JSON.stringify(v, null, 2) : String(v);
}

export default function KelulusanPage() {
    const [status, setStatus] = useState('menunggu');
    const [page, setPage] = useState(1);
    const [deciding, setDeciding] = useState<{ item: Kelulusan; action: 'lulus' | 'tolak' } | null>(null);
    const queryClient = useQueryClient();
    const { data, isLoading, error } = useQuery({ queryKey: ['adm', 'kelulusan', status, page], queryFn: () => fetchKelulusan({ status, page }) });

    const cancel = useMutation({
        mutationFn: (id: number) => api.post<{ message: string }>(`/adm/kelulusan/${id}/batal`, {}),
        onSuccess: (res) => {
            toast.success(res.message);
            queryClient.invalidateQueries({ queryKey: ['adm'] });
        },
        onError: (e) => toast.error(errorText(e)),
    });

    return (
        <PageContainer>
            {data?.mod === 'terbina' && (
                <Alert type="info">
                    Kelulusan dua orang terbina dalam MOD-ADM sedang digunakan (aliran Workflow <code>adm.konfigurasi_kritikal</code> belum aktif). Pemohon
                    tidak boleh meluluskan permohonannya sendiri.
                </Alert>
            )}
            {data?.mod === 'wfe' && <Alert type="info">Permohonan diproses melalui Workflow; keputusan dibuat dalam senarai tugasan Workflow.</Alert>}

            <div className="flex flex-wrap gap-2" role="group" aria-label="Tapis status">
                {['menunggu', 'lulus', 'tolak', 'dibatalkan', ''].map((s) => (
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

            {isLoading && <p className="text-body-sm text-txt-black-500">Memuatkan…</p>}
            {error && <Alert type="error">{errorText(error)}</Alert>}
            {data && data.data.length === 0 && <p className="text-body-sm text-txt-black-500">Tiada permohonan.</p>}

            <ul className="space-y-4">
                {data?.data.map((k) => (
                    <li key={k.id} className="rounded-lg border border-otl-gray-200 bg-bg-white p-4 shadow-card space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <p className="text-body-sm font-semibold text-txt-black-900 break-all">
                                {JENIS[k.jenis_konfig] ?? k.jenis_konfig}: <code>{k.kunci}</code>
                            </p>
                            <Badge variant={statusVariant(k.status)}>{STATUS_LABEL[k.status] ?? k.status}</Badge>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <p className="text-body-xs font-medium text-txt-black-500">Nilai sebelum</p>
                                <pre className="whitespace-pre-wrap break-all rounded-md bg-bg-washed p-2 text-body-xs">{show(k.nilai_sebelum)}</pre>
                            </div>
                            <div>
                                <p className="text-body-xs font-medium text-txt-black-500">Nilai selepas</p>
                                <pre className="whitespace-pre-wrap break-all rounded-md bg-bg-primary-50 p-2 text-body-xs">{show(k.nilai_selepas)}</pre>
                            </div>
                        </div>
                        <p className="text-body-sm">
                            <span className="text-txt-black-500">Justifikasi:</span> {k.justifikasi}
                        </p>
                        <p className="text-body-xs text-txt-black-500">
                            Dimohon oleh {k.pemohon?.name ?? `#${k.pemohon?.id}`} ({k.alamat_ip_pemohon ?? '—'}) pada {formatDateTime(k.dimohon_pada)}
                            {k.pelulus && ` · diputuskan oleh ${k.pelulus.name ?? `#${k.pelulus.id}`} pada ${formatDateTime(k.diputuskan_pada)}`}
                            {k.catatan_pelulus && ` · catatan: ${k.catatan_pelulus}`}
                        </p>
                        {(k.boleh_putus || k.boleh_batal) && (
                            <div className="flex flex-wrap gap-2">
                                {k.boleh_putus && (
                                    <>
                                        <Button size="sm" onClick={() => setDeciding({ item: k, action: 'lulus' })}>
                                            Luluskan
                                        </Button>
                                        <Button size="sm" variant="danger" onClick={() => setDeciding({ item: k, action: 'tolak' })}>
                                            Tolak
                                        </Button>
                                    </>
                                )}
                                {k.boleh_batal && (
                                    <Button size="sm" variant="secondary" onClick={() => cancel.mutate(k.id)}>
                                        Batalkan permohonan
                                    </Button>
                                )}
                            </div>
                        )}
                    </li>
                ))}
            </ul>

            {data && data.last_page > 1 && (
                <div className="flex gap-2">
                    <Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                        Sebelum
                    </Button>
                    <Button size="sm" variant="secondary" disabled={page >= data.last_page} onClick={() => setPage((p) => p + 1)}>
                        Seterusnya
                    </Button>
                </div>
            )}

            {deciding && <DecideModal {...deciding} onClose={() => setDeciding(null)} />}
        </PageContainer>
    );
}

function DecideModal({ item, action, onClose }: { item: Kelulusan; action: 'lulus' | 'tolak'; onClose: () => void }) {
    const queryClient = useQueryClient();
    const [catatan, setCatatan] = useState('');
    const decide = useMutation({
        mutationFn: () => api.post<{ message: string }>(`/adm/kelulusan/${item.id}/${action}`, { catatan: catatan || null }),
        onSuccess: (res) => {
            toast.success(res.message);
            queryClient.invalidateQueries({ queryKey: ['adm'] });
            onClose();
        },
        onError: (e) => toast.error(errorText(e)),
    });

    return (
        <Modal open onClose={onClose}>
            <div className="p-6 space-y-4">
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">
                    {action === 'lulus' ? 'Luluskan' : 'Tolak'} perubahan <code className="break-all">{item.kunci}</code>?
                </h2>
                <Textarea label="Catatan" name="catatan" value={catatan} onChange={setCatatan} rows={3} required={action === 'tolak'} />
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    <Button variant={action === 'lulus' ? 'primary' : 'danger'} onClick={() => decide.mutate()} disabled={decide.isPending || (action === 'tolak' && !catatan)}>
                        {action === 'lulus' ? 'Luluskan' : 'Tolak'}
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
