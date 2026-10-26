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
import type { Role } from '@/lib/rbc';
import { Card, EmptyRow, ScrollX } from '../_components/ui';

type Dialog = { kind: 'create' } | { kind: 'clone'; role: Role } | { kind: 'delete'; role: Role } | null;

export default function RolesPage() {
    const queryClient = useQueryClient();
    const { data, isLoading } = useQuery({ queryKey: ['rbc', 'roles'], queryFn: rbc.roles });
    const [dialog, setDialog] = useState<Dialog>(null);
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    const done = (message: string) => {
        queryClient.invalidateQueries({ queryKey: ['rbc', 'roles'] });
        toast.success(message);
        setDialog(null);
    };
    const failed = (error: unknown) => {
        if (error instanceof ApiError && error.errors) setErrors(error.errors);
        else toast.error(error instanceof Error ? error.message : 'Tindakan gagal.');
    };

    const create = useMutation({ mutationFn: () => rbc.createRole({ nama: name, keterangan: description || undefined }), onSuccess: () => done('Peranan dicipta tanpa sebarang kebenaran. Tetapkan kebenaran sekarang.'), onError: failed });
    const clone = useMutation({ mutationFn: (role: Role) => rbc.cloneRole(role.id, name), onSuccess: () => done('Peranan diklon.'), onError: failed });
    const remove = useMutation({ mutationFn: (role: Role) => rbc.deleteRole(role.id), onSuccess: () => done('Peranan dipadam.'), onError: failed });
    const toggle = useMutation({
        mutationFn: (role: Role) => rbc.setRoleStatus(role.id, role.status === 'aktif' ? 'tidak_aktif' : 'aktif'),
        onSuccess: (res) => done(res.data.status === 'aktif' ? 'Peranan diaktifkan.' : 'Peranan dinyahaktifkan — pemegangnya hilang kebenaran ini serta-merta.'),
        onError: failed,
    });

    function open(next: Dialog) {
        setErrors({});
        setName(next?.kind === 'clone' ? `${next.role.nama} (salinan)` : '');
        setDescription('');
        setDialog(next);
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        if (dialog?.kind === 'create') create.mutate();
        if (dialog?.kind === 'clone') clone.mutate(dialog.role);
    }

    const roles = data?.data ?? [];

    return (
        <PageContainer>
            <Card
                title="Senarai peranan"
                actions={
                    <Button onClick={() => open({ kind: 'create' })} data-panduan="peranan-tambah">Tambah Peranan</Button>
                }
            >
                <p className="mb-4 text-body-sm text-txt-black-500">
                    Peranan baharu bermula tanpa kebenaran (capaian minimum). Tujuh peranan asas RFP 11.2.3 tidak boleh dipadam tetapi boleh diubah atau dinyahaktifkan.
                </p>
                <ScrollX>
                    <Table
                        panduan="peranan-senarai"
                        head={
                            <>
                                <th>Peranan</th>
                                <th>Kategori</th>
                                <th>Status</th>
                                <th className="text-right">Kebenaran</th>
                                <th className="text-right">Pengguna</th>
                                <th className="text-right">Tindakan</th>
                            </>
                        }
                    >
                        {isLoading ? (
                            <EmptyRow colSpan={6}>Memuatkan…</EmptyRow>
                        ) : roles.length === 0 ? (
                            <EmptyRow colSpan={6} />
                        ) : (
                            roles.map((role) => (
                                <tr key={role.id}>
                                    <td>
                                        <Link href={`/admin/rbc/peranan/${role.id}`} className="font-medium text-primary-600 hover:text-primary-700">
                                            {role.nama}
                                        </Link>
                                        <div className="text-body-xs text-txt-black-500">{role.kod}</div>
                                    </td>
                                    <td>{role.kategori_rfp ? <Badge variant="info">Asas RFP</Badge> : <Badge>Tersuai</Badge>}</td>
                                    <td>{role.status === 'aktif' ? <Badge variant="success">Aktif</Badge> : <Badge variant="neutral">Tidak aktif</Badge>}</td>
                                    <td className="text-right tabular-nums">{role.bilangan_kebenaran}</td>
                                    <td className="text-right tabular-nums">{role.bilangan_pengguna}</td>
                                    <td className="text-right whitespace-nowrap space-x-3">
                                        <Link href={`/admin/rbc/peranan/${role.id}`} className="text-primary-600 hover:text-primary-700 text-body-sm font-medium">
                                            Urus
                                        </Link>
                                        <button type="button" onClick={() => open({ kind: 'clone', role })} className="text-primary-600 hover:text-primary-700 text-body-sm font-medium">
                                            Klon
                                        </button>
                                        <button type="button" onClick={() => toggle.mutate(role)} className="text-txt-black-700 hover:text-txt-black-900 text-body-sm font-medium">
                                            {role.status === 'aktif' ? 'Nyahaktif' : 'Aktifkan'}
                                        </button>
                                        {role.boleh_padam && (
                                            <button type="button" onClick={() => open({ kind: 'delete', role })} className="text-danger-600 hover:text-danger-700 text-body-sm font-medium">
                                                Padam
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))
                        )}
                    </Table>
                </ScrollX>
            </Card>

            <Modal open={dialog?.kind === 'create' || dialog?.kind === 'clone'} onClose={() => setDialog(null)}>
                <form onSubmit={submit} className="p-6 space-y-4">
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">
                        {dialog?.kind === 'clone' ? `Klon “${dialog.role.nama}”` : 'Tambah Peranan'}
                    </h2>
                    <FieldInput label="Nama peranan" name="nama" value={name} onChange={setName} required error={errors.nama?.[0] ?? errors.kod?.[0]} />
                    {dialog?.kind === 'create' && <FieldInput label="Keterangan" name="keterangan" value={description} onChange={setDescription} />}
                    {dialog?.kind === 'clone' && <p className="text-body-sm text-txt-black-500">Kebenaran dan skop akan disalin.</p>}
                    <div className="flex justify-end gap-3 pt-2">
                        <Button variant="secondary" onClick={() => setDialog(null)}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={create.isPending || clone.isPending}>
                            Simpan
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal open={dialog?.kind === 'delete'} onClose={() => setDialog(null)}>
                <div className="p-6">
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Padam peranan ini?</h2>
                    <p className="mt-1 text-body-sm text-txt-black-500">{errors.peranan?.[0] ?? 'Tindakan ini tidak boleh dibatalkan.'}</p>
                    <div className="mt-6 flex justify-end gap-3">
                        <Button variant="secondary" onClick={() => setDialog(null)}>
                            Batal
                        </Button>
                        <Button variant="danger" disabled={remove.isPending} onClick={() => dialog?.kind === 'delete' && remove.mutate(dialog.role)}>
                            Padam
                        </Button>
                    </div>
                </div>
            </Modal>
        </PageContainer>
    );
}
