'use client';

import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import Link from 'next/link';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { PageHeader } from '@/components/PageHeader';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { FieldInput } from '@/components/ui/FieldInput';
import { Pagination } from '@/components/ui/Pagination';
import { ApiError } from '@/lib/api';
import { STATUS_LABEL, formatDateTime, rbc } from '@/lib/rbc';
import { createUser, deleteUser, fetchUsers, type UserFormPayload } from '@/lib/users';
import type { AccountStatus, User } from '@/lib/types';
import { Card, EmptyRow, ScrollX, Select, StatusBadge } from '../rbc/_components/ui';

const emptyForm: UserFormPayload = { name: '', email: '', password: '', jenis_pengesahan: 'dalaman', peranan: [], bahagian: '', jawatan: '' };

export default function UsersPage() {
    const [search, setSearch] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [status, setStatus] = useState<AccountStatus | ''>('');
    const [role, setRole] = useState('');
    const [page, setPage] = useState(1);
    const [formOpen, setFormOpen] = useState(false);
    const [form, setForm] = useState<UserFormPayload>(emptyForm);
    const [formErrors, setFormErrors] = useState<Record<string, string[]>>({});
    const [deleteTarget, setDeleteTarget] = useState<User | null>(null);
    const queryClient = useQueryClient();

    useEffect(() => {
        const timeout = setTimeout(() => {
            setDebouncedSearch(search);
            setPage(1);
        }, 400);
        return () => clearTimeout(timeout);
    }, [search]);

    const { data, isLoading } = useQuery({
        queryKey: ['users', debouncedSearch, status, role, page],
        queryFn: () => fetchUsers({ search: debouncedSearch, status, peranan: role, page }),
    });
    const roles = useQuery({ queryKey: ['rbc', 'roles'], queryFn: rbc.roles });
    const roleName = (kod: string) => roles.data?.data.find((r) => r.kod === kod)?.nama ?? kod;

    const createMutation = useMutation({
        mutationFn: createUser,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['users'] });
            toast.success('Pengguna dicipta.');
            setFormOpen(false);
        },
        onError: (error) => {
            if (error instanceof ApiError && error.errors) setFormErrors(error.errors);
            else toast.error(error instanceof Error ? error.message : 'Pengguna tidak dapat dicipta.');
        },
    });

    const deleteMutation = useMutation({
        mutationFn: deleteUser,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['users'] });
            toast.success('Pengguna dipadam.');
            setDeleteTarget(null);
        },
        onError: (error) => toast.error(error instanceof ApiError && error.errors ? Object.values(error.errors).flat().join(' ') : 'Pengguna tidak dapat dipadam.'),
    });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        const payload: UserFormPayload = { ...form, bahagian: form.bahagian || null, jawatan: form.jawatan || null };
        if (payload.jenis_pengesahan === 'sso') delete payload.password;
        createMutation.mutate(payload);
    }

    function toggleRole(id: number) {
        setForm((f) => ({ ...f, peranan: f.peranan?.includes(id) ? f.peranan.filter((r) => r !== id) : [...(f.peranan ?? []), id] }));
    }

    const users = data?.data ?? [];
    const meta = data?.meta;

    return (
        <>
            <PageHeader title="Pengguna" breadcrumbs={[{ label: 'Pengguna' }]} />

            <PageContainer>
                <Card>
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between mb-6">
                        <div className="grid gap-3 sm:grid-cols-3 lg:flex-1 lg:max-w-3xl">
                            <div>
                                <label htmlFor="cari" className="block mb-1.5 text-body-sm font-medium text-txt-black-700">
                                    Cari
                                </label>
                                <input
                                    id="cari"
                                    type="search"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Nama atau e-mel…"
                                    className="block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm"
                                />
                            </div>
                            <Select id="status" label="Status akaun" value={status} onChange={(e) => { setStatus(e.target.value as AccountStatus | ''); setPage(1); }}>
                                <option value="">Semua</option>
                                {(Object.keys(STATUS_LABEL) as AccountStatus[]).map((s) => (
                                    <option key={s} value={s}>
                                        {STATUS_LABEL[s]}
                                    </option>
                                ))}
                            </Select>
                            <Select id="peranan" label="Peranan" value={role} onChange={(e) => { setRole(e.target.value); setPage(1); }}>
                                <option value="">Semua</option>
                                {(roles.data?.data ?? []).map((r) => (
                                    <option key={r.id} value={r.kod}>
                                        {r.nama}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <Button
                            onClick={() => {
                                setForm(emptyForm);
                                setFormErrors({});
                                setFormOpen(true);
                            }}
                        >
                            Tambah Pengguna
                        </Button>
                    </div>

                    <ScrollX>
                        <Table
                            head={
                                <>
                                    <th>Nama</th>
                                    <th>Peranan</th>
                                    <th>Status</th>
                                    <th>Log masuk terakhir</th>
                                    <th className="text-right">Tindakan</th>
                                </>
                            }
                        >
                            {isLoading ? (
                                <EmptyRow colSpan={5}>Memuatkan…</EmptyRow>
                            ) : users.length === 0 ? (
                                <EmptyRow colSpan={5}>Tiada pengguna ditemui.</EmptyRow>
                            ) : (
                                users.map((user) => (
                                    <tr key={user.id}>
                                        <td>
                                            <Link href={`/admin/users/${user.id}`} className="font-medium text-primary-600 hover:text-primary-700">
                                                {user.name}
                                            </Link>
                                            <div className="text-body-xs text-txt-black-500">
                                                {user.email}
                                                {user.jenis_pengesahan === 'sso' ? ' · SSO MAIS' : ''}
                                            </div>
                                        </td>
                                        <td>
                                            <div className="flex flex-wrap gap-1">
                                                {user.peranan.length === 0 ? <span className="text-body-xs text-txt-black-500">Tiada peranan</span> : user.peranan.map((p) => <Badge key={p}>{roleName(p)}</Badge>)}
                                            </div>
                                        </td>
                                        <td>
                                            <div className="flex flex-wrap gap-1">
                                                <StatusBadge status={user.status_akaun} />
                                                {user.dikunci && <Badge variant="danger">Dikunci</Badge>}
                                            </div>
                                        </td>
                                        <td className="whitespace-nowrap">{formatDateTime(user.tarikh_log_masuk_akhir)}</td>
                                        <td className="text-right whitespace-nowrap space-x-3">
                                            <Link href={`/admin/users/${user.id}`} className="text-primary-600 hover:text-primary-700 text-body-sm font-medium">
                                                Urus
                                            </Link>
                                            <button type="button" onClick={() => setDeleteTarget(user)} className="text-danger-600 hover:text-danger-700 text-body-sm font-medium">
                                                Padam
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </Table>
                    </ScrollX>

                    {meta && (
                        <div className="mt-4">
                            <Pagination currentPage={meta.current_page} lastPage={meta.last_page} total={meta.total} firstItem={meta.from ?? undefined} lastItem={meta.to ?? undefined} onPageChange={setPage} />
                        </div>
                    )}
                </Card>
            </PageContainer>

            <Modal open={formOpen} onClose={() => setFormOpen(false)}>
                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Tambah Pengguna</h2>
                    <FieldInput label="Nama" name="name" value={form.name} onChange={(value) => setForm((f) => ({ ...f, name: value }))} required error={formErrors.name?.[0]} />
                    <FieldInput label="E-mel" name="email" type="email" value={form.email} onChange={(value) => setForm((f) => ({ ...f, email: value }))} required error={formErrors.email?.[0]} />
                    <Select id="jenis" label="Jenis pengesahan" value={form.jenis_pengesahan} onChange={(e) => setForm((f) => ({ ...f, jenis_pengesahan: e.target.value as 'dalaman' | 'sso' }))}>
                        <option value="dalaman">Akaun dalaman (kata laluan SISDATA)</option>
                        <option value="sso">SSO MAIS (tiada kata laluan disimpan)</option>
                    </Select>
                    {form.jenis_pengesahan === 'dalaman' && (
                        <FieldInput
                            label="Kata laluan sementara"
                            name="password"
                            type="password"
                            value={form.password ?? ''}
                            onChange={(value) => setForm((f) => ({ ...f, password: value }))}
                            required
                            help="Min. 12 aksara, 3 daripada 4 jenis aksara. Pengguna wajib menukarnya pada log masuk pertama."
                            error={formErrors.password?.join(' ')}
                        />
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FieldInput label="Bahagian" name="bahagian" value={form.bahagian ?? ''} onChange={(value) => setForm((f) => ({ ...f, bahagian: value }))} />
                        <FieldInput label="Jawatan" name="jawatan" value={form.jawatan ?? ''} onChange={(value) => setForm((f) => ({ ...f, jawatan: value }))} />
                    </div>
                    <fieldset>
                        <legend className="mb-1.5 text-body-sm font-medium text-txt-black-700">Peranan</legend>
                        <div className="max-h-40 overflow-y-auto space-y-1 rounded-md border border-otl-gray-200 p-3">
                            {(roles.data?.data ?? []).filter((r) => r.status === 'aktif').map((r) => (
                                <label key={r.id} className="flex items-center gap-2 text-body-sm">
                                    <input type="checkbox" checked={form.peranan?.includes(r.id) ?? false} onChange={() => toggleRole(r.id)} className="rounded border-otl-gray-300 text-primary-600" />
                                    {r.nama}
                                </label>
                            ))}
                        </div>
                        {formErrors.peranan && <p className="mt-1 text-body-xs text-txt-danger">{formErrors.peranan[0]}</p>}
                    </fieldset>
                    <div className="flex justify-end gap-3 pt-2">
                        <Button variant="secondary" onClick={() => setFormOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={createMutation.isPending}>
                            Simpan
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal open={!!deleteTarget} onClose={() => setDeleteTarget(null)}>
                <div className="p-6">
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Padam pengguna ini?</h2>
                    <p className="mt-1 text-body-sm text-txt-black-500">
                        Tindakan ini tidak boleh dibatalkan. Pertimbangkan menukar status kepada Nyahaktif untuk mengekalkan jejak.
                    </p>
                    <div className="mt-6 flex justify-end gap-3">
                        <Button variant="secondary" onClick={() => setDeleteTarget(null)}>
                            Batal
                        </Button>
                        <Button variant="danger" onClick={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)} disabled={deleteMutation.isPending}>
                            Padam
                        </Button>
                    </div>
                </div>
            </Modal>
        </>
    );
}
