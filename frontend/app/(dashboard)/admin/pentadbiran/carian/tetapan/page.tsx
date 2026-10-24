'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Toggle } from '@/components/ui/Toggle';
import { FieldInput } from '@/components/ui/FieldInput';
import { api } from '@/lib/api';
import { errorText, formatDateTime, type IndeksCarian, type SinonimCarian } from '@/lib/adm';

/* SRS-ADM-20: searchable fields per entity, synonyms, weights, reindex. */
export default function TetapanCarianPage() {
    const queryClient = useQueryClient();
    const { data } = useQuery({
        queryKey: ['adm', 'carian-tetapan'],
        queryFn: () => api.get<{ data: IndeksCarian[]; sinonim: SinonimCarian[] }>('/adm/carian/tetapan'),
    });
    const refresh = () => queryClient.invalidateQueries({ queryKey: ['adm', 'carian-tetapan'] });
    const onErr = (e: unknown) => toast.error(errorText(e));

    const update = useMutation({
        mutationFn: ({ id, body }: { id: number; body: Record<string, unknown> }) => api.put<{ message: string }>(`/adm/carian/tetapan/${id}`, body),
        onSuccess: (r) => {
            toast.success(r.message);
            refresh();
        },
        onError: onErr,
    });
    const reindex = useMutation({
        mutationFn: (id: number) => api.post<{ message: string }>(`/adm/carian/tetapan/${id}/indeks-semula`, {}),
        onSuccess: (r) => {
            toast.success(r.message);
            refresh();
        },
        onError: onErr,
    });

    const [istilah, setIstilah] = useState('');
    const [sinonim, setSinonim] = useState('');
    const addSyn = useMutation({
        mutationFn: () =>
            api.post<{ message: string }>('/adm/carian/sinonim', {
                istilah,
                sinonim: sinonim
                    .split(',')
                    .map((s) => s.trim())
                    .filter(Boolean),
            }),
        onSuccess: (r) => {
            toast.success(r.message);
            setIstilah('');
            setSinonim('');
            refresh();
        },
        onError: onErr,
    });
    const delSyn = useMutation({ mutationFn: (id: number) => api.delete<void>(`/adm/carian/sinonim/${id}`), onSuccess: refresh, onError: onErr });

    return (
        <PageContainer>
            <section className="space-y-3">
                <h3 className="font-heading font-semibold text-body-lg text-txt-black-900">Entiti boleh dicari</h3>
                <Table
                    head={
                        <>
                            <th>Entiti</th>
                            <th>Medan boleh dicari</th>
                            <th>Pemberat</th>
                            <th>Aktif</th>
                            <th>Indeks</th>
                            <th className="text-right">Tindakan</th>
                        </>
                    }
                >
                    {(data?.data ?? []).map((i) => (
                        <EntityRow key={i.id} item={i} onSave={(body) => update.mutate({ id: i.id, body })} onReindex={() => reindex.mutate(i.id)} />
                    ))}
                </Table>
            </section>

            <section className="space-y-3">
                <h3 className="font-heading font-semibold text-body-lg text-txt-black-900">Sinonim istilah</h3>
                <form
                    className="grid gap-3 sm:grid-cols-[1fr_2fr_auto] items-end"
                    onSubmit={(e) => {
                        e.preventDefault();
                        addSyn.mutate();
                    }}
                >
                    <FieldInput label="Istilah" name="istilah" value={istilah} onChange={setIstilah} required />
                    <FieldInput label="Sinonim (pisah dengan koma)" name="sinonim" value={sinonim} onChange={setSinonim} required />
                    <Button type="submit" disabled={addSyn.isPending}>
                        Tambah
                    </Button>
                </form>
                <ul className="divide-y divide-otl-gray-200 rounded-lg border border-otl-gray-200 bg-bg-white">
                    {(data?.sinonim ?? []).length === 0 && <li className="p-3 text-body-sm text-txt-black-500">Tiada sinonim.</li>}
                    {data?.sinonim.map((s) => (
                        <li key={s.id} className="flex items-center justify-between gap-3 p-3 text-body-sm">
                            <span>
                                <strong>{s.istilah}</strong> → {s.sinonim.join(', ')}
                            </span>
                            <button type="button" className="text-danger-600 text-body-sm font-medium" onClick={() => delSyn.mutate(s.id)}>
                                Padam
                            </button>
                        </li>
                    ))}
                </ul>
            </section>
        </PageContainer>
    );
}

function EntityRow({ item, onSave, onReindex }: { item: IndeksCarian; onSave: (b: Record<string, unknown>) => void; onReindex: () => void }) {
    const [medan, setMedan] = useState((item.medan ?? []).join(', '));
    const [pemberat, setPemberat] = useState(String(item.pemberat));

    return (
        <tr>
            <td>
                <p className="font-medium">{item.label ?? item.entiti}</p>
                <p className="font-mono text-body-xs text-txt-black-500">{item.entiti}</p>
                {!item.berdaftar && <Badge variant="warning">Tiada penyedia</Badge>}
            </td>
            <td>
                <input
                    aria-label={`Medan ${item.entiti}`}
                    className="w-full min-w-[10rem] rounded-md border-otl-gray-300 text-body-sm"
                    value={medan}
                    placeholder="lalai penyedia"
                    onChange={(e) => setMedan(e.target.value)}
                />
            </td>
            <td>
                <input aria-label={`Pemberat ${item.entiti}`} type="number" step="0.5" min="0" className="w-20 rounded-md border-otl-gray-300 text-body-sm" value={pemberat} onChange={(e) => setPemberat(e.target.value)} />
            </td>
            <td>
                <Toggle name={`aktif-${item.id}`} checked={item.aktif} onChange={(v) => onSave({ aktif: v })} />
            </td>
            <td className="text-body-xs whitespace-nowrap">
                {item.status_indeks ?? '—'}
                <br />
                {formatDateTime(item.diindeks_semula_pada)}
            </td>
            <td className="text-right whitespace-nowrap space-x-3">
                <button
                    type="button"
                    className="text-primary-600 text-body-sm font-medium"
                    onClick={() =>
                        onSave({
                            medan: medan
                                .split(',')
                                .map((s) => s.trim())
                                .filter(Boolean),
                            pemberat: Number(pemberat),
                        })
                    }
                >
                    Simpan
                </button>
                <button type="button" className="text-primary-600 text-body-sm font-medium" onClick={onReindex}>
                    Indeks semula
                </button>
            </td>
        </tr>
    );
}
