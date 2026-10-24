'use client';

import { useState } from 'react';
import { useParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { FieldInput } from '@/components/ui/FieldInput';
import { formatDateTime, rbc } from '@/lib/rbc';
import type { RecertCycle } from '@/lib/rbc';
import { Card, EmptyRow, ScrollX, Select } from '../../_components/ui';

type Decision = NonNullable<RecertCycle['keputusan']>[number];

const OUTCOME: Record<Decision['keputusan'], { label: string; variant: 'neutral' | 'success' | 'danger' }> = {
    belum: { label: 'Belum disemak', variant: 'neutral' },
    disahkan: { label: 'Disahkan', variant: 'success' },
    ditarik: { label: 'Ditarik', variant: 'danger' },
};

/** SRS-RBC-27: a data owner reviews each user × role assignment in the cycle. */
export default function RecertCyclePage() {
    const { id } = useParams<{ id: string }>();
    const queryClient = useQueryClient();
    const [filter, setFilter] = useState('');
    const [withdrawing, setWithdrawing] = useState<Decision | null>(null);
    const [note, setNote] = useState('');
    const [closing, setClosing] = useState(false);

    const { data, isLoading } = useQuery({ queryKey: ['rbc', 'recert', id, filter], queryFn: () => rbc.recertCycle(id, filter || undefined) });
    const cycle = data?.data;
    const refresh = () => queryClient.invalidateQueries({ queryKey: ['rbc', 'recert'] });
    const fail = (error: unknown) => toast.error(error instanceof Error ? error.message : 'Gagal.');

    const decide = useMutation({
        mutationFn: ({ d, outcome, catatan }: { d: Decision; outcome: 'disahkan' | 'ditarik'; catatan?: string }) => rbc.decideRecert(Number(id), d.id, outcome, catatan),
        onSuccess: (_r, v) => {
            refresh();
            toast.success(v.outcome === 'disahkan' ? 'Capaian disahkan.' : 'Capaian ditarik serta-merta.');
            setWithdrawing(null);
        },
        onError: fail,
    });
    const close = useMutation({
        mutationFn: () => rbc.closeRecert(Number(id)),
        onSuccess: () => {
            refresh();
            toast.success('Kitaran ditutup.');
            setClosing(false);
        },
        onError: fail,
    });

    const decisions = cycle?.keputusan ?? [];
    const canDecide = cycle?.status === 'dibuka' && cycle.boleh_semak;

    return (
        <PageContainer>
            {cycle && (
                <Card
                    title={cycle.nama}
                    actions={
                        cycle.status === 'dibuka' ? (
                            <Button variant="secondary" onClick={() => setClosing(true)}>
                                Tutup Kitaran
                            </Button>
                        ) : (
                            <Badge>Ditutup {formatDateTime(cycle.ditutup_pada)}</Badge>
                        )
                    }
                >
                    <dl className="grid gap-3 sm:grid-cols-4 text-body-sm">
                        <div>
                            <dt className="text-txt-black-500">Bahagian</dt>
                            <dd className="font-medium">{cycle.bahagian ?? 'Semua bahagian'}</dd>
                        </div>
                        <div>
                            <dt className="text-txt-black-500">Tempoh</dt>
                            <dd className="font-medium">
                                {cycle.tarikh_mula} → {cycle.tarikh_akhir}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-txt-black-500">Belum disemak</dt>
                            <dd className="font-medium tabular-nums">
                                {cycle.belum} / {cycle.jumlah}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-txt-black-500">Ditarik</dt>
                            <dd className="font-medium tabular-nums">{cycle.ditarik}</dd>
                        </div>
                    </dl>
                    {cycle.status === 'dibuka' && !cycle.boleh_semak && (
                        <p className="mt-4 text-body-sm text-txt-warning">Anda bukan pemilik data bagi bahagian kitaran ini — paparan sahaja.</p>
                    )}
                </Card>
            )}

            <Card title="Capaian untuk disemak" actions={<Select id="tapis" aria-label="Tapis keputusan" value={filter} onChange={(e) => setFilter(e.target.value)}>
                <option value="">Semua</option>
                <option value="belum">Belum disemak</option>
                <option value="disahkan">Disahkan</option>
                <option value="ditarik">Ditarik</option>
            </Select>}>
                <ScrollX>
                    <Table
                        head={
                            <>
                                <th>Pengguna</th>
                                <th>Peranan</th>
                                <th>Keputusan</th>
                                <th className="text-right">Tindakan</th>
                            </>
                        }
                    >
                        {isLoading ? (
                            <EmptyRow colSpan={4}>Memuatkan…</EmptyRow>
                        ) : decisions.length === 0 ? (
                            <EmptyRow colSpan={4} />
                        ) : (
                            decisions.map((d) => (
                                <tr key={d.id}>
                                    <td>
                                        <div className="font-medium">{d.pengguna.name}</div>
                                        <div className="text-body-xs text-txt-black-500">
                                            {d.pengguna.email}
                                            {d.pengguna.jawatan ? ` · ${d.pengguna.jawatan}` : ''}
                                        </div>
                                    </td>
                                    <td>
                                        <div>{d.peranan.nama}</div>
                                        <details className="text-body-xs text-txt-black-500">
                                            <summary className="cursor-pointer">{d.kebenaran.length} kebenaran</summary>
                                            <div className="mt-1 max-w-xs break-words">{d.kebenaran.join(', ')}</div>
                                        </details>
                                    </td>
                                    <td>
                                        <Badge variant={OUTCOME[d.keputusan].variant}>{OUTCOME[d.keputusan].label}</Badge>
                                        {d.catatan && <div className="text-body-xs text-txt-black-500 mt-1">{d.catatan}</div>}
                                        {d.penyemak && <div className="text-body-xs text-txt-black-500">oleh {d.penyemak}</div>}
                                    </td>
                                    <td className="text-right whitespace-nowrap space-x-3">
                                        {canDecide && d.keputusan === 'belum' && (
                                            <>
                                                <button type="button" onClick={() => decide.mutate({ d, outcome: 'disahkan' })} className="text-primary-600 hover:text-primary-700 text-body-sm font-medium">
                                                    Sahkan
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setNote('');
                                                        setWithdrawing(d);
                                                    }}
                                                    className="text-danger-600 hover:text-danger-700 text-body-sm font-medium"
                                                >
                                                    Tarik
                                                </button>
                                            </>
                                        )}
                                    </td>
                                </tr>
                            ))
                        )}
                    </Table>
                </ScrollX>
            </Card>

            <Modal open={withdrawing !== null} onClose={() => setWithdrawing(null)}>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (withdrawing) decide.mutate({ d: withdrawing, outcome: 'ditarik', catatan: note });
                    }}
                    className="p-6 space-y-4"
                >
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Tarik capaian?</h2>
                    <p className="text-body-sm text-txt-black-500">
                        Peranan {withdrawing?.peranan.nama} akan dikeluarkan daripada {withdrawing?.pengguna.name} serta-merta.
                    </p>
                    <FieldInput label="Sebab" name="catatan" value={note} onChange={setNote} required />
                    <div className="flex justify-end gap-3">
                        <Button variant="secondary" onClick={() => setWithdrawing(null)}>
                            Batal
                        </Button>
                        <Button variant="danger" type="submit" disabled={decide.isPending}>
                            Tarik Capaian
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal open={closing} onClose={() => setClosing(false)}>
                <div className="p-6">
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Tutup kitaran?</h2>
                    <p className="mt-1 text-body-sm text-txt-black-500">Capaian yang belum disemak akan ditarik jika polisi menetapkan demikian.</p>
                    <div className="mt-6 flex justify-end gap-3">
                        <Button variant="secondary" onClick={() => setClosing(false)}>
                            Batal
                        </Button>
                        <Button disabled={close.isPending} onClick={() => close.mutate()}>
                            Tutup Kitaran
                        </Button>
                    </div>
                </div>
            </Modal>
        </PageContainer>
    );
}
