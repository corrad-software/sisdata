'use client';

import Link from 'next/link';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { FieldInput } from '@/components/ui/FieldInput';
import { Textarea } from '@/components/ui/Textarea';
import { Toggle } from '@/components/ui/Toggle';
import { Alert } from '@/components/ui/Alert';
import { ApiError } from '@/lib/api';
import { errorText, fetchParameters, formatDateTime, updateParameter, type ParameterItem } from '@/lib/adm';

function display(value: unknown): string {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'Ya' : 'Tidak';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
}

export default function ParameterPage() {
    const { data, isLoading, error } = useQuery({ queryKey: ['adm', 'parameter'], queryFn: fetchParameters });
    const [filter, setFilter] = useState('');
    const [editing, setEditing] = useState<ParameterItem | null>(null);

    const groups = (data?.data ?? [])
        .map((g) => ({
            ...g,
            parameter: g.parameter.filter((p) => !filter || `${p.kunci} ${p.label}`.toLowerCase().includes(filter.toLowerCase())),
        }))
        .filter((g) => g.parameter.length > 0);

    return (
        <PageContainer>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-body-sm text-txt-black-500 max-w-2xl">
                    Perubahan berkuat kuasa serta-merta tanpa pelepasan kod. Parameter bertanda <Badge variant="warning">Kritikal</Badge>{' '}
                    hanya berkuat kuasa selepas diluluskan oleh pentadbir kedua.
                </p>
                <input
                    type="search"
                    aria-label="Tapis parameter"
                    value={filter}
                    onChange={(e) => setFilter(e.target.value)}
                    placeholder="Tapis parameter…"
                    className="rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm w-full sm:w-64"
                />
            </div>

            {isLoading && <p className="text-body-sm text-txt-black-500">Memuatkan…</p>}
            {error && <Alert type="error">{errorText(error)}</Alert>}

            {groups.map((g) => (
                <section key={g.modul} className="rounded-lg border border-otl-gray-200 bg-bg-white shadow-card" aria-labelledby={`modul-${g.modul}`}>
                    <h3 id={`modul-${g.modul}`} className="border-b border-otl-gray-200 px-4 py-3 font-heading font-semibold text-body-md text-txt-black-900">
                        {g.label} <span className="text-txt-black-500 font-normal text-body-sm">({g.modul.toUpperCase()})</span>
                    </h3>
                    <ul className="divide-y divide-otl-gray-200">
                        {g.parameter.map((p) => (
                            <li key={p.kunci} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div className="min-w-0">
                                    <p className="text-body-sm font-medium text-txt-black-900">
                                        {p.label}{' '}
                                        {p.kritikal && <Badge variant="warning">Kritikal</Badge>}{' '}
                                        {p.id_kelulusan_menunggu && (
                                            <Link href="/admin/pentadbiran/kelulusan" className="text-body-xs text-primary-600 underline">
                                                Menunggu kelulusan
                                            </Link>
                                        )}
                                    </p>
                                    <p className="text-body-xs text-txt-black-500 break-all">
                                        <code>{p.kunci}</code> · {p.jenis} · lalai: {display(p.lalai)}
                                        {p.ditetapkan && ` · dikemas kini ${formatDateTime(p.dikemas_kini_pada)}`}
                                    </p>
                                </div>
                                <div className="flex items-center gap-3 sm:justify-end">
                                    <span className="text-body-sm text-txt-black-900 break-all max-w-xs">{display(p.nilai)}</span>
                                    <Button size="sm" variant="secondary" onClick={() => setEditing(p)}>
                                        Ubah
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </section>
            ))}

            {editing && <EditModal item={editing} onClose={() => setEditing(null)} />}
        </PageContainer>
    );
}

function EditModal({ item, onClose }: { item: ParameterItem; onClose: () => void }) {
    const queryClient = useQueryClient();
    const [value, setValue] = useState<string>(
        item.jenis === 'json' ? JSON.stringify(item.nilai ?? [], null, 2) : item.nilai === null || item.nilai === undefined ? '' : String(item.nilai),
    );
    const [bool, setBool] = useState<boolean>(Boolean(item.nilai));
    const [justifikasi, setJustifikasi] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    const save = useMutation({
        mutationFn: () => {
            let nilai: unknown = value;
            if (item.jenis === 'bool') nilai = bool;
            if (item.jenis === 'int') nilai = value === '' ? null : Number(value);
            if (item.jenis === 'json') {
                try {
                    nilai = JSON.parse(value);
                } catch {
                    nilai = value;
                }
            }
            return updateParameter(item.kunci, nilai, justifikasi);
        },
        onSuccess: (res) => {
            toast.success(res.message);
            queryClient.invalidateQueries({ queryKey: ['adm'] });
            onClose();
        },
        onError: (e) => {
            if (e instanceof ApiError && e.errors) setErrors(e.errors);
            else toast.error(errorText(e));
        },
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        save.mutate();
    }

    return (
        <Modal open onClose={onClose}>
            <form onSubmit={submit} className="p-6 space-y-4">
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">{item.label}</h2>
                <p className="text-body-xs text-txt-black-500 break-all">
                    <code>{item.kunci}</code>
                </p>
                {item.kritikal && (
                    <Alert type="warning">Parameter kritikal: perubahan dihantar untuk kelulusan pentadbir kedua dan belum berkuat kuasa sehingga diluluskan.</Alert>
                )}

                {item.jenis === 'bool' ? (
                    <Toggle name="nilai" label={bool ? 'Ya' : 'Tidak'} checked={bool} onChange={setBool} />
                ) : item.jenis === 'json' || (item.jenis === 'string' && value.length > 60) ? (
                    <Textarea label="Nilai" name="nilai" value={value} onChange={setValue} rows={5} error={errors.nilai?.[0]} />
                ) : (
                    <FieldInput
                        label="Nilai"
                        name="nilai"
                        type={item.jenis === 'int' ? 'number' : 'text'}
                        value={value}
                        onChange={setValue}
                        error={errors.nilai?.[0]}
                    />
                )}

                <Textarea
                    label="Justifikasi"
                    name="justifikasi"
                    value={justifikasi}
                    onChange={setJustifikasi}
                    rows={2}
                    required={item.kritikal}
                    error={errors.justifikasi?.[0]}
                    help="Direkod dalam jejak audit bersama nilai sebelum dan selepas."
                />

                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    <Button type="submit" disabled={save.isPending}>
                        {item.kritikal ? 'Hantar untuk kelulusan' : 'Simpan'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
