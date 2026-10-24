'use client';

import Link from 'next/link';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { FieldInput } from '@/components/ui/FieldInput';
import { Modal } from '@/components/ui/Modal';
import { Textarea } from '@/components/ui/Textarea';
import { errorMessage, fmtDateTime, useCan, type Paged, type WfeInstance } from '@/lib/wfe';
import { Card, StatusBadge } from '../_components/common';

/** SRS-WFE-22: my requests — status, withdraw, resubmit; plus the demo starter. */
export default function PermohonanPage() {
    const can = useCan();
    const queryClient = useQueryClient();
    const [withdrawing, setWithdrawing] = useState<WfeInstance | null>(null);
    const [reason, setReason] = useState('');

    const { data, isLoading } = useQuery({
        queryKey: ['wfe', 'permohonan'],
        queryFn: () => api.get<Paged<WfeInstance>>('/wfe/permohonan'),
    });

    const withdraw = useMutation({
        mutationFn: () => api.post<{ message: string }>(`/wfe/instans/${withdrawing?.id}/tarik-balik`, { justifikasi: reason }),
        onSuccess: (r) => {
            toast.success(r.message);
            setWithdrawing(null);
            setReason('');
            queryClient.invalidateQueries({ queryKey: ['wfe'] });
        },
        onError: (e) => toast.error(errorMessage(e)),
    });

    const resubmit = useMutation({
        mutationFn: (id: string) => api.post<{ message: string }>(`/wfe/instans/${id}/hantar-semula`, {}),
        onSuccess: (r) => {
            toast.success(r.message);
            queryClient.invalidateQueries({ queryKey: ['wfe'] });
        },
        onError: (e) => toast.error(errorMessage(e)),
    });

    const rows = data?.data ?? [];

    return (
        <PageContainer>
            {can('wfe.contoh.cipta') && <DemoStarter />}

            <Card title="Permohonan saya">
                <Table
                    head={
                        <>
                            <th>Perkara</th>
                            <th>Aliran</th>
                            <th>Status</th>
                            <th>Peringkat semasa</th>
                            <th>Dimulakan</th>
                            <th className="text-right">Tindakan</th>
                        </>
                    }
                >
                    {isLoading ? (
                        <tr>
                            <td colSpan={6} className="text-center text-txt-black-500 py-6">
                                Memuatkan…
                            </td>
                        </tr>
                    ) : rows.length === 0 ? (
                        <tr>
                            <td colSpan={6} className="text-center text-txt-black-500 py-6">
                                Tiada permohonan.
                            </td>
                        </tr>
                    ) : (
                        rows.map((i) => (
                            <tr key={i.id}>
                                <td>
                                    <Link href={`/admin/wfe/instans/${i.id}`} className="font-medium text-primary-600 hover:underline">
                                        {i.tajuk}
                                    </Link>
                                </td>
                                <td>
                                    {i.nama_aliran} <span className="text-txt-black-500">v{i.no_versi}</span>
                                </td>
                                <td>
                                    <StatusBadge status={i.keadaan ?? i.status} />
                                </td>
                                <td>{i.peringkat_semasa ?? '-'}</td>
                                <td className="whitespace-nowrap">{fmtDateTime(i.tarikh_mula)}</td>
                                <td className="text-right whitespace-nowrap space-x-2">
                                    {i.status === 'berjalan' && i.keadaan === 'menunggu_pemohon' && (
                                        <Button size="sm" onClick={() => resubmit.mutate(i.id)} disabled={resubmit.isPending}>
                                            Hantar semula
                                        </Button>
                                    )}
                                    {i.status === 'berjalan' && (
                                        <Button size="sm" variant="secondary" onClick={() => setWithdrawing(i)}>
                                            Tarik balik
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        ))
                    )}
                </Table>
            </Card>

            <Modal open={!!withdrawing} onClose={() => setWithdrawing(null)}>
                <form
                    className="p-6 space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        withdraw.mutate();
                    }}
                >
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Tarik balik permohonan?</h2>
                    <p className="text-body-sm text-txt-black-500">{withdrawing?.tajuk}</p>
                    <Textarea label="Sebab" name="sebab" value={reason} onChange={setReason} required rows={3} maxLength={2000} />
                    <div className="flex justify-end gap-3">
                        <Button variant="secondary" onClick={() => setWithdrawing(null)}>
                            Batal
                        </Button>
                        <Button type="submit" variant="danger" disabled={!reason.trim() || withdraw.isPending}>
                            Tarik balik
                        </Button>
                    </div>
                </form>
            </Modal>
        </PageContainer>
    );
}

function DemoStarter() {
    const queryClient = useQueryClient();
    const [form, setForm] = useState({ tajuk: 'Permohonan sewa tapak gerai', nilai: '25000', kod_daerah: '10' });
    const start = useMutation({
        mutationFn: () => api.post<{ message: string }>('/wfe/contoh', { ...form, nilai: Number(form.nilai) }),
        onSuccess: (r) => {
            toast.success(r.message);
            queryClient.invalidateQueries({ queryKey: ['wfe'] });
        },
        onError: (e) => toast.error(errorMessage(e)),
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        start.mutate();
    }

    return (
        <Card title="Demo: mulakan aliran contoh.kelulusan">
            <form onSubmit={submit} className="grid gap-4 sm:grid-cols-4 sm:items-end">
                <FieldInput label="Tajuk" name="tajuk" value={form.tajuk} onChange={(v) => setForm((f) => ({ ...f, tajuk: v }))} required />
                <FieldInput label="Nilai (RM)" name="nilai" type="number" value={form.nilai} onChange={(v) => setForm((f) => ({ ...f, nilai: v }))} required />
                <FieldInput label="Kod daerah" name="kod_daerah" value={form.kod_daerah} onChange={(v) => setForm((f) => ({ ...f, kod_daerah: v }))} />
                <Button type="submit" disabled={start.isPending}>
                    Mulakan
                </Button>
            </form>
        </Card>
    );
}
