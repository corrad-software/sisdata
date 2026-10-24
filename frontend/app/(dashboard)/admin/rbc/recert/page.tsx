'use client';

import { useState } from 'react';
import type { FormEvent } from 'react';
import Link from 'next/link';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { FieldInput } from '@/components/ui/FieldInput';
import { ApiError } from '@/lib/api';
import { rbc } from '@/lib/rbc';
import { Card, EmptyRow, ScrollX, TextInput } from '../_components/ui';

/** SRS-RBC-27: recertification cycles. */
export default function RecertPage() {
    const queryClient = useQueryClient();
    const { data, isLoading } = useQuery({ queryKey: ['rbc', 'recert'], queryFn: rbc.recertCycles });
    const [open, setOpen] = useState(false);
    const [nama, setNama] = useState('');
    const [bahagian, setBahagian] = useState('');
    const [until, setUntil] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    const create = useMutation({
        mutationFn: () => rbc.openRecert({ nama, bahagian: bahagian || undefined, tarikh_akhir: until || undefined }),
        onSuccess: (res) => {
            queryClient.invalidateQueries({ queryKey: ['rbc', 'recert'] });
            toast.success(`Kitaran dibuka dengan ${res.data.jumlah} capaian untuk disemak.`);
            setOpen(false);
        },
        onError: (error) => {
            if (error instanceof ApiError && error.errors) setErrors(error.errors);
            else toast.error(error instanceof Error ? error.message : 'Gagal.');
        },
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        create.mutate();
    }

    const cycles = data?.data ?? [];

    return (
        <PageContainer>
            <Card
                title="Kitaran pengesahan semula capaian"
                actions={
                    <Button
                        onClick={() => {
                            setNama('');
                            setBahagian('');
                            setUntil('');
                            setErrors({});
                            setOpen(true);
                        }}
                    >
                        Buka Kitaran
                    </Button>
                }
            >
                <p className="mb-4 text-body-sm text-txt-black-500">
                    Pemilik data bahagian mengesahkan atau menarik setiap capaian pengguna dalam skopnya. Capaian yang tidak disahkan apabila kitaran ditutup ditarik mengikut polisi.
                </p>
                <ScrollX>
                    <Table
                        head={
                            <>
                                <th>Kitaran</th>
                                <th>Bahagian</th>
                                <th>Tempoh</th>
                                <th>Status</th>
                                <th className="text-right">Belum / Jumlah</th>
                            </>
                        }
                    >
                        {isLoading ? (
                            <EmptyRow colSpan={5}>Memuatkan…</EmptyRow>
                        ) : cycles.length === 0 ? (
                            <EmptyRow colSpan={5}>Tiada kitaran.</EmptyRow>
                        ) : (
                            cycles.map((c) => (
                                <tr key={c.id}>
                                    <td>
                                        <Link href={`/admin/rbc/recert/${c.id}`} className="font-medium text-primary-600 hover:text-primary-700">
                                            {c.nama}
                                        </Link>
                                    </td>
                                    <td>{c.bahagian ?? 'Semua bahagian'}</td>
                                    <td className="whitespace-nowrap">
                                        {c.tarikh_mula} → {c.tarikh_akhir}
                                    </td>
                                    <td>{c.status === 'dibuka' ? <Badge variant="info">Dibuka</Badge> : <Badge>Ditutup</Badge>}</td>
                                    <td className="text-right tabular-nums">
                                        {c.belum} / {c.jumlah}
                                        {c.ditarik > 0 && <div className="text-body-xs text-txt-danger">{c.ditarik} ditarik</div>}
                                    </td>
                                </tr>
                            ))
                        )}
                    </Table>
                </ScrollX>
            </Card>

            <Modal open={open} onClose={() => setOpen(false)}>
                <form onSubmit={submit} className="p-6 space-y-4">
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Buka kitaran pengesahan semula</h2>
                    <FieldInput label="Nama kitaran" name="nama" value={nama} onChange={setNama} required error={errors.nama?.[0]} />
                    <FieldInput label="Bahagian (kosongkan untuk semua)" name="bahagian" value={bahagian} onChange={setBahagian} error={errors.bahagian?.[0]} />
                    <TextInput id="tarikh_akhir" type="date" label="Tarikh akhir semakan (lalai mengikut polisi)" value={until} onChange={setUntil} />
                    <div className="flex justify-end gap-3 pt-2">
                        <Button variant="secondary" onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={create.isPending}>
                            Buka Kitaran
                        </Button>
                    </div>
                </form>
            </Modal>
        </PageContainer>
    );
}
