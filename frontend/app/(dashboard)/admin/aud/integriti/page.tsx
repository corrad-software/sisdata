'use client';

import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Alert } from '@/components/ui/Alert';
import { StatCard } from '@/components/ui/StatCard';
import { FieldInput } from '@/components/ui/FieldInput';
import { ApiError } from '@/lib/api';
import { fetchIntegrity, fetchRetention, runIntegrityCheck, updateRetention } from '@/lib/aud';

function formatTime(value: string): string {
    return new Date(value).toLocaleString('ms-MY', { timeZone: 'Asia/Kuala_Lumpur', hour12: false });
}

function RetentionPanel() {
    const queryClient = useQueryClient();
    const { data, error } = useQuery({ queryKey: ['aud', 'retensi'], queryFn: fetchRetention, retry: false });
    const policy = data?.data;
    const [form, setForm] = useState({ bulan: '', tahun: '', catatan: '' });
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    useEffect(() => {
        if (policy) {
            // eslint-disable-next-line react-hooks/set-state-in-effect -- sync form with the loaded policy once
            setForm({ bulan: String(policy.tempoh_dalam_talian_bulan), tahun: policy.tempoh_arkib_tahun ? String(policy.tempoh_arkib_tahun) : '', catatan: policy.catatan ?? '' });
        }
    }, [policy]);

    const save = useMutation({
        mutationFn: () =>
            updateRetention({
                tempoh_dalam_talian_bulan: Number(form.bulan),
                tempoh_arkib_tahun: form.tahun ? Number(form.tahun) : null,
                catatan: form.catatan || null,
            }),
        onSuccess: (res) => {
            setErrors({});
            toast.success(res.message);
            queryClient.invalidateQueries({ queryKey: ['aud', 'retensi'] });
        },
        onError: (err) => {
            if (err instanceof ApiError && err.errors) setErrors(err.errors);
            else toast.error(err instanceof ApiError && err.status === 403 ? 'Anda tiada kebenaran untuk mengubah polisi retensi.' : 'Polisi tidak dapat disimpan.');
        },
    });

    if (error instanceof ApiError && error.status === 403) return null;

    function submit(e: FormEvent) {
        e.preventDefault();
        save.mutate();
    }

    return (
        <form onSubmit={submit} className="bg-bg-white shadow-card rounded-lg border border-otl-gray-200 p-4 sm:p-6 space-y-4">
            <div>
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Polisi retensi</h2>
                <p className="text-body-sm text-txt-black-500">
                    Rekod audit kekal dalam talian sekurang-kurangnya {policy?.minimum_bulan ?? 24} bulan, kemudian diarkibkan oleh job berjadual dan kekal
                    boleh dicari.
                </p>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <FieldInput
                    label="Tempoh dalam talian (bulan)"
                    name="tempoh_dalam_talian_bulan"
                    type="number"
                    value={form.bulan}
                    onChange={(v) => setForm((f) => ({ ...f, bulan: v }))}
                    required
                    error={errors.tempoh_dalam_talian_bulan?.[0]}
                />
                <FieldInput
                    label="Tempoh arkib (tahun)"
                    name="tempoh_arkib_tahun"
                    type="number"
                    value={form.tahun}
                    onChange={(v) => setForm((f) => ({ ...f, tahun: v }))}
                    help="Kosongkan untuk simpan selamanya."
                    error={errors.tempoh_arkib_tahun?.[0]}
                />
            </div>
            <FieldInput label="Catatan" name="catatan" value={form.catatan} onChange={(v) => setForm((f) => ({ ...f, catatan: v }))} error={errors.catatan?.[0]} />
            <Button type="submit" disabled={save.isPending || !policy}>
                {save.isPending ? 'Menyimpan…' : 'Simpan polisi'}
            </Button>
        </form>
    );
}

export default function IntegrityPage() {
    const queryClient = useQueryClient();
    const { data, isLoading, error } = useQuery({ queryKey: ['aud', 'integriti'], queryFn: fetchIntegrity });

    const run = useMutation({
        mutationFn: runIntegrityCheck,
        onSuccess: (res) => {
            (res.data.status === 'lulus' ? toast.success : toast.error)(res.message);
            queryClient.invalidateQueries({ queryKey: ['aud', 'integriti'] });
        },
        onError: (err) => toast.error(err instanceof ApiError && err.status === 403 ? 'Anda tiada kebenaran untuk menjalankan semakan.' : 'Semakan gagal dijalankan.'),
    });

    const status = data?.data;
    const latest = status?.semakan[0];

    return (
        <PageContainer>
            {error instanceof ApiError && <Alert type="error">{error.status === 403 ? 'Anda tiada kebenaran untuk melihat status integriti.' : error.message}</Alert>}

            {latest?.status === 'gagal' && (
                <Alert type="error">
                    Semakan terakhir mengesan percanggahan pada no. urutan {latest.no_urutan_rosak}: {latest.butiran}
                </Alert>
            )}
            {latest?.status === 'lulus' && <Alert type="success">Rantaian cincangan audit trail sah pada semakan terakhir ({formatTime(latest.selesai_pada)}).</Alert>}

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <StatCard label="Rekod dalam talian" value={isLoading ? '…' : String(status?.rantaian.dalam_talian ?? 0)} />
                <StatCard label="Rekod diarkibkan" value={isLoading ? '…' : String(status?.rantaian.arkib ?? 0)} />
                <StatCard label="No. urutan terakhir" value={isLoading ? '…' : String(status?.rantaian.no_urutan_terakhir ?? '—')} />
            </div>

            <div className="bg-bg-white shadow-card rounded-lg border border-otl-gray-200 p-4 sm:p-6 space-y-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Semakan integriti</h2>
                        <p className="text-body-sm text-txt-black-500">
                            Dijalankan secara berjadual (cron <span className="font-mono">{status?.jadual_cron ?? '—'}</span>) dan boleh dijalankan serta-merta.
                        </p>
                    </div>
                    <Button onClick={() => run.mutate()} disabled={run.isPending}>
                        {run.isPending ? 'Menyemak…' : 'Jalankan semakan sekarang'}
                    </Button>
                </div>

                <Table
                    head={
                        <>
                            <th>Selesai</th>
                            <th>Status</th>
                            <th>Disemak</th>
                            <th>No. urutan terakhir</th>
                            <th>Dicetuskan</th>
                            <th>Butiran</th>
                        </>
                    }
                >
                    {(status?.semakan ?? []).length === 0 ? (
                        <tr>
                            <td colSpan={6} className="text-center text-txt-black-500 py-6">
                                {isLoading ? 'Memuatkan…' : 'Belum ada semakan.'}
                            </td>
                        </tr>
                    ) : (
                        status?.semakan.map((c) => (
                            <tr key={c.id}>
                                <td className="whitespace-nowrap">{formatTime(c.selesai_pada)}</td>
                                <td>{c.status === 'lulus' ? <Badge variant="success">Lulus</Badge> : <Badge variant="danger">Gagal</Badge>}</td>
                                <td>{c.bilangan_disemak}</td>
                                <td>{c.no_urutan_terakhir ?? '—'}</td>
                                <td>{c.dicetuskan_oleh === 'jadual' ? 'Jadual' : 'Manual'}</td>
                                <td className="text-body-xs">{c.butiran ?? '—'}</td>
                            </tr>
                        ))
                    )}
                </Table>
            </div>

            <RetentionPanel />
        </PageContainer>
    );
}
