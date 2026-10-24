'use client';

import { useState } from 'react';
import type { FormEvent } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { FieldInput } from '@/components/ui/FieldInput';
import { errorMessage, fmtDate, useCan, useMe } from '@/lib/wfe';
import { Card } from '../_components/common';
import { UserPicker, type PickedUser } from '../_components/UserPicker';

interface Delegation {
    id: number;
    pengguna: { id: number; nama: string };
    pemangku: { id: number; nama: string };
    tarikh_mula: string;
    tarikh_tamat: string;
    sebab: string | null;
    dibatalkan: boolean;
    berkuat_kuasa: boolean;
}

/** SRS-WFE-21: set an acting approver (pemangku) for a date range. */
export default function PemangkuPage() {
    const can = useCan();
    const { data: me } = useMe();
    const queryClient = useQueryClient();
    const admin = can('wfe.pemangku.kemas_kini');
    const [all, setAll] = useState(false);
    const [principal, setPrincipal] = useState<PickedUser | null>(null);
    const [delegate, setDelegate] = useState<PickedUser | null>(null);
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [reason, setReason] = useState('');

    const { data, isLoading } = useQuery({
        queryKey: ['wfe', 'pemangku', all],
        queryFn: () => api.get<{ data: Delegation[] }>(`/wfe/pemangku${all ? '?semua=1' : ''}`).then((r) => r.data),
    });
    const create = useMutation({
        mutationFn: () =>
            api.post<{ message: string }>('/wfe/pemangku', {
                id_pengguna: principal?.id,
                id_pemangku: delegate?.id,
                tarikh_mula: from,
                tarikh_tamat: to,
                sebab: reason || undefined,
            }),
        onSuccess: (r) => {
            toast.success(r.message);
            setDelegate(null);
            setReason('');
            queryClient.invalidateQueries({ queryKey: ['wfe', 'pemangku'] });
        },
        onError: (e) => toast.error(errorMessage(e)),
    });
    const cancel = useMutation({
        mutationFn: (id: number) => api.delete<{ message: string }>(`/wfe/pemangku/${id}`),
        onSuccess: (r) => {
            toast.success(r.message);
            queryClient.invalidateQueries({ queryKey: ['wfe', 'pemangku'] });
        },
        onError: (e) => toast.error(errorMessage(e)),
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        create.mutate();
    }

    return (
        <PageContainer>
            <Card title="Tetapkan pemangku">
                <p className="text-body-sm text-txt-black-500">
                    Sepanjang tempoh ini, tugasan baharu yang ditugaskan kepada {admin ? 'pelulus' : 'anda'} disalurkan kepada pemangku. Pemangku juga boleh
                    bertindak ke atas tugasan peranan/kumpulan pelulus. Identiti kedua-duanya direkodkan dalam sejarah dan audit trail.
                </p>
                <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
                    {admin && <UserPicker id="pelulus" label="Pelulus (kosongkan untuk diri sendiri)" value={principal} onChange={setPrincipal} />}
                    <UserPicker id="pemangku" label="Pemangku" value={delegate} onChange={setDelegate} />
                    <FieldInput label="Tarikh mula" name="tarikh_mula" type="date" value={from} onChange={setFrom} required />
                    <FieldInput label="Tarikh tamat" name="tarikh_tamat" type="date" value={to} onChange={setTo} required />
                    <FieldInput label="Sebab" name="sebab" value={reason} onChange={setReason} placeholder="cth. Cuti tahunan" />
                    <div className="flex items-end">
                        <Button type="submit" disabled={!delegate || !from || !to || create.isPending}>
                            Simpan pemangku
                        </Button>
                    </div>
                </form>
            </Card>

            <Card
                title="Senarai pemangku"
                actions={
                    admin && (
                        <label className="flex items-center gap-2 text-body-sm">
                            <input type="checkbox" checked={all} onChange={(e) => setAll(e.target.checked)} className="rounded border-otl-gray-300 text-primary-600" />
                            Papar semua pelulus
                        </label>
                    )
                }
            >
                <Table head={<><th>Pelulus</th><th>Pemangku</th><th>Tempoh</th><th>Sebab</th><th>Status</th><th className="text-right">Tindakan</th></>}>
                    {isLoading ? (
                        <tr><td colSpan={6} className="text-center text-txt-black-500 py-6">Memuatkan…</td></tr>
                    ) : (data ?? []).length === 0 ? (
                        <tr><td colSpan={6} className="text-center text-txt-black-500 py-6">Tiada pemangku.</td></tr>
                    ) : (
                        data?.map((d) => (
                            <tr key={d.id}>
                                <td>{d.pengguna.nama}</td>
                                <td>{d.pemangku.nama}</td>
                                <td className="whitespace-nowrap">{fmtDate(`${d.tarikh_mula}T00:00:00+08:00`)} – {fmtDate(`${d.tarikh_tamat}T00:00:00+08:00`)}</td>
                                <td>{d.sebab ?? '-'}</td>
                                <td>{d.dibatalkan ? <Badge>Dibatalkan</Badge> : d.berkuat_kuasa ? <Badge variant="success">Berkuat kuasa</Badge> : <Badge variant="info">Dijadualkan</Badge>}</td>
                                <td className="text-right">
                                    {!d.dibatalkan && (admin || d.pengguna.id === me?.id) && (
                                        <button type="button" className="text-danger-600 text-body-sm font-medium" onClick={() => cancel.mutate(d.id)}>
                                            Batal
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))
                    )}
                </Table>
            </Card>
        </PageContainer>
    );
}
