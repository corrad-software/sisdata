'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { Textarea } from '@/components/ui/Textarea';
import { InputLabel } from '@/components/ui/InputLabel';
import { errorMessage, fmtDateTime, useCan, type WfeInstance } from '@/lib/wfe';
import { Card, HistoryList, InfoList, StatusBadge } from '../../_components/common';

/** SRS-WFE-15/16: instance drill-down — source record link, tasks, full history; version migration request. */
export default function InstansPage() {
    const { id } = useParams<{ id: string }>();
    const can = useCan();
    const queryClient = useQueryClient();
    const [target, setTarget] = useState('');
    const [reason, setReason] = useState('');

    const { data: i, isLoading, error } = useQuery({
        queryKey: ['wfe', 'instans', id],
        queryFn: () => api.get<{ data: WfeInstance }>(`/wfe/instans/${id}`).then((r) => r.data),
    });

    const migrate = useMutation({
        mutationFn: () => api.post<{ message: string }>(`/wfe/instans/${id}/pindah-versi`, { id_definisi: Number(target), sebab: reason }),
        onSuccess: (r) => {
            toast.success(r.message);
            setReason('');
            queryClient.invalidateQueries({ queryKey: ['wfe', 'instans', id] });
        },
        onError: (e) => toast.error(errorMessage(e)),
    });

    if (isLoading) return <PageContainer>Memuatkan…</PageContainer>;
    if (error || !i) {
        return (
            <PageContainer>
                <Alert type="error">{errorMessage(error, 'Instans tidak dapat dipaparkan.')}</Alert>
            </PageContainer>
        );
    }

    return (
        <PageContainer>
            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2 space-y-6">
                    <Card title={i.tajuk} actions={<StatusBadge status={i.keadaan ?? i.status} />}>
                        <InfoList
                            items={[
                                { label: 'Aliran', value: `${i.nama_aliran} (${i.kod_aliran} v${i.no_versi})` },
                                { label: 'Rekod sumber', value: i.pautan ? <Link className="text-primary-600 hover:underline" href={i.pautan}>{`${i.jenis_subjek} #${i.id_rekod}`}</Link> : `${i.jenis_subjek} #${i.id_rekod}` },
                                { label: 'Pemohon', value: i.pemohon.nama },
                                { label: 'Peringkat semasa', value: i.peringkat_semasa ?? '-' },
                                { label: 'Daerah', value: i.kod_daerah ?? '-' },
                                { label: 'Dimulakan', value: fmtDateTime(i.tarikh_mula) },
                                { label: 'Tamat', value: fmtDateTime(i.tarikh_tamat) },
                                ...(i.ulasan_keputusan ? [{ label: 'Ulasan keputusan', value: i.ulasan_keputusan }] : []),
                            ]}
                        />
                    </Card>

                    <Card title="Tugasan">
                        <Table
                            head={
                                <>
                                    <th>Peringkat</th>
                                    <th>Penerima</th>
                                    <th>Status</th>
                                    <th>Tarikh akhir SLA</th>
                                    <th>Oleh</th>
                                </>
                            }
                        >
                            {(i.tugasan ?? []).map((t) => (
                                <tr key={t.id}>
                                    <td>{t.peringkat ?? t.tajuk ?? 'Susulan'}</td>
                                    <td>{t.penerima}</td>
                                    <td>
                                        <StatusBadge status={t.keputusan ?? t.status} />
                                        {t.aras_eskalasi > 0 && <span className="ms-2 text-body-xs text-txt-danger">eskalasi {t.aras_eskalasi}</span>}
                                    </td>
                                    <td className="whitespace-nowrap">{fmtDateTime(t.tarikh_akhir_sla)}</td>
                                    <td>{t.diselesaikan_oleh ?? '-'}</td>
                                </tr>
                            ))}
                        </Table>
                    </Card>

                    <Card title="Sejarah aliran kerja">
                        <HistoryList items={i.sejarah ?? []} />
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card title="Konteks">
                        <InfoList stacked items={Object.entries(i.konteks ?? {}).map(([k, v]) => ({ label: k, value: String(v ?? '-') }))} />
                    </Card>

                    {can('wfe.definisi.kemas_kini') && i.status === 'berjalan' && (i.versi_lain?.length ?? 0) > 0 && (
                        <Card title="Pindah ke versi lain">
                            <p className="text-body-xs text-txt-black-500">
                                Kes berjalan kekal pada versi asal. Pemindahan memerlukan kelulusan pentadbir kedua dengan MFA.
                            </p>
                            <div>
                                <InputLabel htmlFor="versi" className="mb-1.5">
                                    Versi sasaran
                                </InputLabel>
                                <select
                                    id="versi"
                                    value={target}
                                    onChange={(e) => setTarget(e.target.value)}
                                    className="block w-full rounded-md border-otl-gray-300 bg-bg-white text-body-sm"
                                >
                                    <option value="">Pilih…</option>
                                    {i.versi_lain?.map((v) => (
                                        <option key={v.id} value={v.id}>
                                            Versi {v.no_versi}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <Textarea label="Sebab" name="sebab_pindah" value={reason} onChange={setReason} required rows={3} />
                            <Button onClick={() => migrate.mutate()} disabled={!target || !reason.trim() || migrate.isPending}>
                                Mohon pindah versi
                            </Button>
                            {(i.pindah_versi ?? []).map((p) => (
                                <p key={p.id} className="text-body-xs text-txt-black-500">
                                    Permohonan #{p.id}: <StatusBadge status={p.status} /> — {p.sebab}
                                </p>
                            ))}
                        </Card>
                    )}
                </div>
            </div>
        </PageContainer>
    );
}
