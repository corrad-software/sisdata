'use client';

import Link from 'next/link';
import { Suspense, useState } from 'react';
import { useParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Textarea } from '@/components/ui/Textarea';
import { Alert } from '@/components/ui/Alert';
import { errorMessage, fmtDateTime, type MfaState, type WfeInstance, type WfeTask } from '@/lib/wfe';
import { Card, DecisionDialog, HistoryList, InfoList, MfaNotice, SlaBadge, StatusBadge } from '../../_components/common';
import { MfaResult } from '../../_components/MfaResult';

type Detail = WfeTask & { butiran_instans: WfeInstance; mfa: MfaState };
type Action = 'lulus' | 'tolak' | 'kembali' | 'tangguh';

export default function TugasanDetailPage() {
    const { id } = useParams<{ id: string }>();
    const queryClient = useQueryClient();
    const [action, setAction] = useState<Action | null>(null);
    const [note, setNote] = useState('');

    const { data: task, isLoading, error } = useQuery({
        queryKey: ['wfe', 'tugasan', 'detail', id],
        queryFn: () => api.get<{ data: Detail }>(`/wfe/tugasan/${id}`).then((r) => r.data),
    });

    const complete = useMutation({
        mutationFn: () => api.post<{ message: string }>(`/wfe/tugasan/${id}/selesai`, { catatan: note || undefined }),
        onSuccess: (r) => {
            toast.success(r.message);
            queryClient.invalidateQueries({ queryKey: ['wfe'] });
        },
        onError: (e) => toast.error(errorMessage(e)),
    });

    const returnPath = `/admin/wfe/tugasan/${id}`;

    if (isLoading) return <PageContainer>Memuatkan…</PageContainer>;
    if (error || !task) {
        return (
            <PageContainer>
                <Alert type="error">{errorMessage(error, 'Tugasan tidak dapat dipaparkan.')}</Alert>
            </PageContainer>
        );
    }

    const instance = task.butiran_instans;

    return (
        <PageContainer>
            <Suspense>
                <MfaResult />
            </Suspense>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2 space-y-6">
                    <Card title={task.tajuk} actions={<StatusBadge status={task.status} />}>
                        <InfoList
                            items={[
                                { label: 'Aliran', value: `${instance.nama_aliran} (v${instance.no_versi})` },
                                { label: 'Peringkat', value: task.peringkat?.nama ?? 'Tugasan susulan' },
                                { label: 'Ditugaskan kepada', value: task.penerima.label + (task.bagi_pihak ? ` — bagi pihak ${task.bagi_pihak}` : '') },
                                { label: 'Pemohon', value: instance.pemohon.nama },
                                { label: 'Diterima', value: fmtDateTime(task.tarikh_tugasan) },
                                { label: 'Tarikh akhir SLA', value: fmtDateTime(task.tarikh_akhir_sla) },
                                ...(task.ditangguh_hingga ? [{ label: 'Ditangguh hingga', value: fmtDateTime(task.ditangguh_hingga) }] : []),
                            ]}
                        />
                        <div className="flex flex-wrap gap-2">
                            {task.status !== 'selesai' && <SlaBadge task={task} />}
                            {task.peringkat?.perlu_mfa && <Badge variant="warning">Peringkat kritikal — MFA</Badge>}
                            {task.aras_eskalasi > 0 && <Badge variant="danger">Eskalasi aras {task.aras_eskalasi}</Badge>}
                        </div>
                        {instance.pautan && (
                            <Link href={instance.pautan} className="inline-block text-primary-600 text-body-sm font-medium hover:underline">
                                Buka rekod sumber →
                            </Link>
                        )}
                    </Card>

                    {task.boleh_bertindak && task.jenis === 'kelulusan' && (
                        <Card title="Keputusan">
                            {task.peringkat?.perlu_mfa && <MfaNotice returnPath={returnPath} />}
                            <div className="flex flex-wrap gap-2">
                                <Button onClick={() => setAction('lulus')}>Lulus</Button>
                                <Button variant="danger" onClick={() => setAction('tolak')}>
                                    Tolak
                                </Button>
                                <Button variant="secondary" onClick={() => setAction('kembali')}>
                                    Kembalikan
                                </Button>
                                <Button variant="outline" onClick={() => setAction('tangguh')}>
                                    Tangguh
                                </Button>
                            </div>
                            <p className="text-body-xs text-txt-black-500">Justifikasi bertulis wajib bagi tolak, kembalikan dan tangguh.</p>
                        </Card>
                    )}

                    {task.boleh_bertindak && task.jenis === 'susulan' && (
                        <Card title="Tugasan susulan">
                            <Textarea label="Catatan" name="catatan" value={note} onChange={setNote} rows={3} maxLength={2000} />
                            <Button onClick={() => complete.mutate()} disabled={complete.isPending}>
                                Tandakan selesai
                            </Button>
                        </Card>
                    )}

                    <Card title="Sejarah aliran kerja">
                        <HistoryList items={instance.sejarah ?? []} />
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card title="Maklumat permohonan">
                        <InfoList stacked
                            items={[
                                { label: 'Status', value: <StatusBadge status={instance.status} /> },
                                { label: 'Peringkat semasa', value: instance.peringkat_semasa ?? '-' },
                                { label: 'Jenis rekod', value: `${instance.jenis_subjek} #${instance.id_rekod}` },
                                ...Object.entries(instance.konteks ?? {})
                                    .filter(([k]) => k !== 'pautan')
                                    .map(([k, v]) => ({ label: k, value: String(v ?? '-') })),
                            ]}
                        />
                        <Link href={`/admin/wfe/instans/${instance.id}`} className="text-primary-600 text-body-sm font-medium hover:underline">
                            Lihat butiran penuh instans
                        </Link>
                    </Card>
                </div>
            </div>

            <DecisionDialog task={task} action={action} onClose={() => setAction(null)} returnPath={returnPath} />
        </PageContainer>
    );
}
