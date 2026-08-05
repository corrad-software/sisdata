'use client';

import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { PageHeader } from '@/components/PageHeader';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { FieldInput } from '@/components/ui/FieldInput';
import { InputLabel } from '@/components/ui/InputLabel';
import { InputError } from '@/components/ui/InputError';
import { ApiError } from '@/lib/api';
import { createUser, deleteUser, fetchUsers, updateUser, type UserFormPayload } from '@/lib/users';
import type { User } from '@/lib/types';

const emptyForm: UserFormPayload = { name: '', email: '', password: '', role: 'user', is_active: true };

export default function UsersPage() {
    const [search, setSearch] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [page, setPage] = useState(1);

    const [formOpen, setFormOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<User | null>(null);
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
        queryKey: ['users', debouncedSearch, page],
        queryFn: () => fetchUsers(debouncedSearch, page),
    });

    const createMutation = useMutation({
        mutationFn: createUser,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['users'] });
            toast.success('User created.');
            setFormOpen(false);
        },
        onError: (error) => {
            if (error instanceof ApiError && error.errors) {
                setFormErrors(error.errors);
            } else {
                toast.error('Could not create user.');
            }
        },
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, payload }: { id: number; payload: UserFormPayload }) => updateUser(id, payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['users'] });
            toast.success('User updated.');
            setFormOpen(false);
        },
        onError: (error) => {
            if (error instanceof ApiError && error.errors) {
                setFormErrors(error.errors);
            } else {
                toast.error('Could not update user.');
            }
        },
    });

    const deleteMutation = useMutation({
        mutationFn: deleteUser,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['users'] });
            toast.success('User deleted.');
            setDeleteTarget(null);
        },
        onError: () => toast.error('Could not delete user.'),
    });

    function openCreateModal() {
        setEditingUser(null);
        setForm(emptyForm);
        setFormErrors({});
        setFormOpen(true);
    }

    function openEditModal(user: User) {
        setEditingUser(user);
        setForm({ name: user.name, email: user.email, password: '', role: user.role, is_active: user.is_active });
        setFormErrors({});
        setFormOpen(true);
    }

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        const payload = { ...form };
        if (!payload.password) delete payload.password;

        if (editingUser) {
            updateMutation.mutate({ id: editingUser.id, payload });
        } else {
            createMutation.mutate(payload);
        }
    }

    const users = data?.data ?? [];
    const meta = data?.meta;
    const saving = createMutation.isPending || updateMutation.isPending;

    return (
        <>
            <PageHeader title="Users" breadcrumbs={[{ label: 'Users' }]} />

            <PageContainer className="space-y-6">
                    <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-end mb-6">
                            <div className="flex flex-col sm:flex-row gap-3 sm:items-center">
                                <input
                                    type="search"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search by name or email..."
                                    className="rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm w-full sm:w-64"
                                />
                                <Button variant="primary" onClick={openCreateModal}>
                                    Add User
                                </Button>
                            </div>
                        </div>

                        <Table
                            head={
                                <>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th className="text-right">Actions</th>
                                </>
                            }
                        >
                            {isLoading ? (
                                <tr>
                                    <td colSpan={5} className="text-center text-txt-black-500 py-6">
                                        Loading...
                                    </td>
                                </tr>
                            ) : users.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="text-center text-txt-black-500 py-6">
                                        No users found.
                                    </td>
                                </tr>
                            ) : (
                                users.map((user) => (
                                    <tr key={user.id}>
                                        <td className="font-medium">{user.name}</td>
                                        <td>{user.email}</td>
                                        <td className="capitalize">{user.role}</td>
                                        <td>
                                            {user.is_active ? (
                                                <Badge variant="success">Active</Badge>
                                            ) : (
                                                <Badge variant="neutral">Inactive</Badge>
                                            )}
                                        </td>
                                        <td className="text-right whitespace-nowrap">
                                            <button
                                                type="button"
                                                onClick={() => openEditModal(user)}
                                                className="text-primary-600 hover:text-primary-700 text-body-sm font-medium"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setDeleteTarget(user)}
                                                className="ms-3 text-danger-600 hover:text-danger-700 text-body-sm font-medium"
                                            >
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </Table>

                        {meta && meta.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between text-body-sm text-txt-black-500">
                                <span>
                                    Showing {meta.from} to {meta.to} of {meta.total} results
                                </span>
                                <div className="flex gap-2">
                                    <Button variant="secondary" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                                        Previous
                                    </Button>
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        disabled={page >= meta.last_page}
                                        onClick={() => setPage((p) => p + 1)}
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
            </PageContainer>

            <Modal open={formOpen} onClose={() => setFormOpen(false)}>
                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">
                        {editingUser ? 'Edit User' : 'Add User'}
                    </h2>

                    <FieldInput
                        label="Name"
                        name="name"
                        value={form.name}
                        onChange={(value) => setForm((f) => ({ ...f, name: value }))}
                        required
                        error={formErrors.name?.[0]}
                    />

                    <FieldInput
                        label="Email"
                        name="email"
                        type="email"
                        value={form.email}
                        onChange={(value) => setForm((f) => ({ ...f, email: value }))}
                        required
                        error={formErrors.email?.[0]}
                    />

                    <FieldInput
                        label="Password"
                        name="password"
                        type="password"
                        value={form.password ?? ''}
                        onChange={(value) => setForm((f) => ({ ...f, password: value }))}
                        required={!editingUser}
                        help={editingUser ? 'Leave blank to keep the current password.' : undefined}
                        error={formErrors.password?.[0]}
                    />

                    <div>
                        <InputLabel htmlFor="role" className="mb-1.5">
                            Role
                        </InputLabel>
                        <select
                            id="role"
                            value={form.role}
                            onChange={(e) => setForm((f) => ({ ...f, role: e.target.value as UserFormPayload['role'] }))}
                            className="block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm"
                        >
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                        <InputError messages={formErrors.role} className="mt-1" />
                    </div>

                    <label className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            checked={form.is_active}
                            onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.checked }))}
                            className="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary"
                        />
                        <span className="text-body-sm text-txt-black-900">Active</span>
                    </label>

                    <div className="flex justify-end gap-3 pt-2">
                        <Button variant="secondary" type="button" onClick={() => setFormOpen(false)}>
                            Cancel
                        </Button>
                        <Button variant="primary" type="submit" disabled={saving}>
                            Save
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal open={!!deleteTarget} onClose={() => setDeleteTarget(null)}>
                <div className="p-6">
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Delete this user?</h2>
                    <p className="mt-1 text-body-sm text-txt-black-500">This action cannot be undone.</p>

                    <div className="mt-6 flex justify-end gap-3">
                        <Button variant="secondary" type="button" onClick={() => setDeleteTarget(null)}>
                            Cancel
                        </Button>
                        <Button
                            variant="danger"
                            type="button"
                            onClick={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
                            disabled={deleteMutation.isPending}
                        >
                            Delete
                        </Button>
                    </div>
                </div>
            </Modal>
        </>
    );
}
