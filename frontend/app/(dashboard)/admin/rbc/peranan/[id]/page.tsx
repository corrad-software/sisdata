'use client';

import { useMemo, useState } from 'react';
import { useParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { FieldInput } from '@/components/ui/FieldInput';
import { Badge } from '@/components/ui/Badge';
import { ApiError } from '@/lib/api';
import { ACTION_LABEL, rbc } from '@/lib/rbc';
import type { Catalogue, Role } from '@/lib/rbc';
import { Card, ScopeEditor } from '../../_components/ui';

function onError(error: unknown) {
    toast.error(error instanceof ApiError && error.errors ? Object.values(error.errors).flat().join(' ') : error instanceof Error ? error.message : 'Tindakan gagal.');
}

/** Permission matrix editor for one module: rows = fungsi, columns = tindakan. */
function ModuleMatrix({ module, actions, selected, onToggle }: { module: Catalogue['modul'][number]; actions: string[]; selected: Set<string>; onToggle: (keys: string[], on: boolean) => void }) {
    const functions = useMemo(() => {
        const map = new Map<string, Map<string, { kunci: string; label: string }>>();
        for (const p of module.kebenaran) {
            const [, fungsi, tindakan] = p.kunci.split('.');
            if (!map.has(fungsi)) map.set(fungsi, new Map());
            map.get(fungsi)!.set(tindakan, p);
        }
        return map;
    }, [module]);
    const columns = actions.filter((a) => module.kebenaran.some((p) => p.kunci.endsWith(`.${a}`)));
    const allKeys = module.kebenaran.map((p) => p.kunci);
    const allOn = allKeys.every((k) => selected.has(k));

    return (
        <fieldset className="min-w-0 border border-otl-gray-200 rounded-lg">
            <legend className="sr-only">{module.label}</legend>
            <div className="flex items-center justify-between gap-3 px-4 py-3 bg-bg-washed rounded-t-lg">
                <div>
                    <div className="font-medium text-txt-black-900">{module.label}</div>
                    <div className="text-body-xs text-txt-black-500">{module.modul}</div>
                </div>
                <label className="flex items-center gap-2 text-body-sm">
                    <input type="checkbox" checked={allOn} onChange={(e) => onToggle(allKeys, e.target.checked)} className="rounded border-otl-gray-300 text-primary-600" />
                    Semua
                </label>
            </div>
            <div className="overflow-x-auto">
                <table className="min-w-full text-body-sm">
                    <thead>
                        <tr className="text-txt-black-500">
                            <th scope="col" className="px-4 py-2 text-start font-medium">Fungsi</th>
                            {columns.map((a) => (
                                <th key={a} scope="col" className="px-3 py-2 text-center font-medium">
                                    {ACTION_LABEL[a] ?? a}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {[...functions.entries()].map(([fungsi, perms]) => (
                            <tr key={fungsi} className="border-t border-otl-divider">
                                <th scope="row" className="px-4 py-2 text-start font-normal text-txt-black-900">
                                    {fungsi.replaceAll('_', ' ')}
                                </th>
                                {columns.map((a) => {
                                    const p = perms.get(a);
                                    return (
                                        <td key={a} className="px-3 py-2 text-center">
                                            {p ? (
                                                <input
                                                    type="checkbox"
                                                    title={`${p.label} (${p.kunci})`}
                                                    aria-label={p.label}
                                                    checked={selected.has(p.kunci)}
                                                    onChange={(e) => onToggle([p.kunci], e.target.checked)}
                                                    className="rounded border-otl-gray-300 text-primary-600"
                                                />
                                            ) : (
                                                <span className="text-txt-black-300">—</span>
                                            )}
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </fieldset>
    );
}

function RoleEditor({ role, catalogue }: { role: Role; catalogue: Catalogue }) {
    const queryClient = useQueryClient();
    const [nama, setNama] = useState(role.nama);
    const [keterangan, setKeterangan] = useState(role.keterangan ?? '');
    const [selected, setSelected] = useState<Set<string>>(() => new Set(role.kebenaran ?? []));
    const dirty = useMemo(() => {
        const original = new Set(role.kebenaran ?? []);
        return original.size !== selected.size || [...selected].some((k) => !original.has(k));
    }, [role.kebenaran, selected]);

    const refresh = (message: string) => {
        queryClient.invalidateQueries({ queryKey: ['rbc', 'role', String(role.id)] });
        queryClient.invalidateQueries({ queryKey: ['rbc', 'roles'] });
        toast.success(message);
    };
    const saveDetails = useMutation({ mutationFn: () => rbc.updateRole(role.id, { nama, keterangan: keterangan || null }), onSuccess: () => refresh('Butiran peranan disimpan.'), onError });
    const savePermissions = useMutation({ mutationFn: () => rbc.setRolePermissions(role.id, [...selected]), onSuccess: () => refresh('Kebenaran disimpan dan berkuat kuasa serta-merta.'), onError });
    const saveScopes = useMutation({ mutationFn: (skop: Record<string, string[]>) => rbc.setRoleScopes(role.id, skop), onSuccess: () => refresh('Skop peranan disimpan.'), onError });

    function toggle(keys: string[], on: boolean) {
        setSelected((prev) => {
            const next = new Set(prev);
            keys.forEach((k) => (on ? next.add(k) : next.delete(k)));
            return next;
        });
    }

    return (
        <>
            <Card title="Butiran peranan" actions={role.kategori_rfp ? <Badge variant="info">Peranan asas RFP</Badge> : undefined}>
                <div className="grid gap-4 sm:grid-cols-2">
                    <FieldInput label="Nama" name="nama" value={nama} onChange={setNama} required />
                    <FieldInput label="Kod" name="kod" value={role.kod} onChange={() => undefined} help="Kod tidak boleh diubah." />
                    <div className="sm:col-span-2">
                        <FieldInput label="Keterangan" name="keterangan" value={keterangan} onChange={setKeterangan} />
                    </div>
                </div>
                <div className="mt-4 flex justify-end">
                    <Button onClick={() => saveDetails.mutate()} disabled={saveDetails.isPending}>
                        Simpan Butiran
                    </Button>
                </div>
            </Card>

            <Card
                title={`Kebenaran (${selected.size})`}
                actions={
                    <>
                        <Button variant="secondary" onClick={() => setSelected(new Set(role.kebenaran ?? []))} disabled={!dirty}>
                            Set Semula
                        </Button>
                        <Button onClick={() => savePermissions.mutate()} disabled={!dirty || savePermissions.isPending}>
                            Simpan Kebenaran
                        </Button>
                    </>
                }
            >
                <p className="mb-4 text-body-sm text-txt-black-500">Modul × fungsi × tindakan. Setiap kebenaran mesti diberikan secara eksplisit; anda hanya boleh memberi kebenaran yang anda sendiri miliki.</p>
                <div className="space-y-4">
                    {catalogue.modul.map((m) => (
                        <ModuleMatrix key={m.modul} module={m} actions={catalogue.tindakan.concat(['akses'])} selected={selected} onToggle={toggle} />
                    ))}
                </div>
            </Card>

            <Card title="Skop capaian peranan">
                <ScopeEditor types={catalogue.jenis_skop} value={(role.skop as Record<string, string[]>) ?? {}} onSave={(s) => saveScopes.mutate(s)} saving={saveScopes.isPending} />
            </Card>
        </>
    );
}

export default function RoleDetailPage() {
    const { id } = useParams<{ id: string }>();
    const role = useQuery({ queryKey: ['rbc', 'role', id], queryFn: () => rbc.role(id) });
    const catalogue = useQuery({ queryKey: ['rbc', 'catalogue'], queryFn: rbc.catalogue });

    return (
        <PageContainer>
            {role.data && catalogue.data ? (
                <RoleEditor key={`${role.data.data.id}-${role.dataUpdatedAt}`} role={role.data.data} catalogue={catalogue.data.data} />
            ) : (
                <p className="text-body-sm text-txt-black-500">{role.error || catalogue.error ? 'Peranan tidak dapat dimuatkan.' : 'Memuatkan…'}</p>
            )}
        </PageContainer>
    );
}
