'use client';

import { useState } from 'react';
import type { FormEvent } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { FieldInput } from '@/components/ui/FieldInput';
import { errorMessage, useCan } from '@/lib/wfe';
import { Card } from '../_components/common';
import { UserPicker, type PickedUser } from '../_components/UserPicker';

interface Group {
    id: number;
    kod: string;
    nama: string;
    ahli: Array<{ id: number; nama: string; emel: string }>;
}

/** SRS-WFE-05: approver groups — any member may act on a group task. */
export default function KumpulanPage() {
    const can = useCan();
    const queryClient = useQueryClient();
    const [editing, setEditing] = useState<{ id?: number; kod: string; nama: string; ahli: PickedUser[] } | null>(null);
    const [adding, setAdding] = useState<PickedUser | null>(null);

    const { data } = useQuery({ queryKey: ['wfe', 'kumpulan'], queryFn: () => api.get<{ data: Group[] }>('/wfe/kumpulan').then((r) => r.data) });
    const save = useMutation({
        mutationFn: () => {
            const body = { kod: editing?.kod, nama: editing?.nama, ahli: editing?.ahli.map((u) => u.id) };
            return editing?.id ? api.put<{ message: string }>(`/wfe/kumpulan/${editing.id}`, body) : api.post<{ message: string }>('/wfe/kumpulan', body);
        },
        onSuccess: (r) => {
            toast.success(r.message);
            setEditing(null);
            queryClient.invalidateQueries({ queryKey: ['wfe', 'kumpulan'] });
            queryClient.invalidateQueries({ queryKey: ['wfe', 'rujukan'] });
        },
        onError: (e) => toast.error(errorMessage(e)),
    });
    const editable = can('wfe.kumpulan.kemas_kini');

    function submit(e: FormEvent) {
        e.preventDefault();
        save.mutate();
    }

    return (
        <PageContainer>
            <Card title="Kumpulan pelulus" actions={editable && <Button size="sm" onClick={() => setEditing({ kod: '', nama: '', ahli: [] })}>Kumpulan baharu</Button>}>
                <div className="grid gap-4 md:grid-cols-2">
                    {(data ?? []).map((g) => (
                        <div key={g.id} className="rounded-lg border border-otl-gray-200 p-4 space-y-2">
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <p className="font-medium text-txt-black-900">{g.nama}</p>
                                    <p className="text-body-xs text-txt-black-500">kod: {g.kod}</p>
                                </div>
                                {editable && (
                                    <button
                                        type="button"
                                        className="text-primary-600 text-body-sm font-medium"
                                        onClick={() => setEditing({ id: g.id, kod: g.kod, nama: g.nama, ahli: g.ahli.map((a) => ({ id: a.id, name: a.nama, email: a.emel })) })}
                                    >
                                        Ubah
                                    </button>
                                )}
                            </div>
                            <ul className="text-body-sm text-txt-black-700 list-disc ms-5">
                                {g.ahli.length === 0 ? <li>Tiada ahli</li> : g.ahli.map((a) => <li key={a.id}>{a.nama}</li>)}
                            </ul>
                        </div>
                    ))}
                    {(data ?? []).length === 0 && <p className="text-body-sm text-txt-black-500">Tiada kumpulan.</p>}
                </div>
            </Card>

            <Modal open={!!editing} onClose={() => setEditing(null)}>
                {editing && (
                    <form onSubmit={submit} className="p-6 space-y-4">
                        <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">{editing.id ? 'Ubah kumpulan' : 'Kumpulan baharu'}</h2>
                        {!editing.id && <FieldInput label="Kod" name="kod" value={editing.kod} onChange={(v) => setEditing({ ...editing, kod: v })} required help="huruf kecil, nombor, _" />}
                        <FieldInput label="Nama" name="nama" value={editing.nama} onChange={(v) => setEditing({ ...editing, nama: v })} required />
                        <div className="space-y-2">
                            <p className="text-body-sm font-medium text-txt-black-700">Ahli</p>
                            {editing.ahli.map((u) => (
                                <div key={u.id} className="flex justify-between text-body-sm">
                                    <span>{u.name}</span>
                                    <button type="button" className="text-danger-600" onClick={() => setEditing({ ...editing, ahli: editing.ahli.filter((x) => x.id !== u.id) })}>
                                        Buang
                                    </button>
                                </div>
                            ))}
                            <UserPicker
                                id="ahli_baharu"
                                label="Tambah ahli"
                                value={adding}
                                onChange={(u) => {
                                    if (u && !editing.ahli.some((x) => x.id === u.id)) setEditing({ ...editing, ahli: [...editing.ahli, u] });
                                    setAdding(null);
                                }}
                            />
                        </div>
                        <div className="flex justify-end gap-3">
                            <Button variant="secondary" onClick={() => setEditing(null)}>Batal</Button>
                            <Button type="submit" disabled={save.isPending}>Simpan</Button>
                        </div>
                    </form>
                )}
            </Modal>
        </PageContainer>
    );
}
