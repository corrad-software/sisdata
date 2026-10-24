'use client';

import { useState } from 'react';
import type { FormEvent } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { ApiError } from '@/lib/api';
import { rbc } from '@/lib/rbc';
import type { ClaimRule } from '@/lib/rbc';
import { Card, EmptyRow, ScrollX, Select, TextInput } from '../_components/ui';

const CLAIM_LABEL: Record<ClaimRule['jenis_tuntutan'], string> = { roles: 'Peranan SSO (roles)', bahagian: 'Bahagian', unit: 'Unit', jawatan: 'Jawatan' };

interface Draft {
    id?: number;
    jenis_tuntutan: ClaimRule['jenis_tuntutan'];
    nilai: string;
    peranan_id: string;
    skop_jenis: string;
    skop_nilai: string;
    keutamaan: string;
    keterangan: string;
    aktif: boolean;
}

const EMPTY: Draft = { jenis_tuntutan: 'roles', nilai: '', peranan_id: '', skop_jenis: '', skop_nilai: '', keutamaan: '100', keterangan: '', aktif: true };

/** SRS-RBC-02: SSO MAIS claim → SISDATA role / scope rules. */
export default function ClaimMappingPage() {
    const queryClient = useQueryClient();
    const rules = useQuery({ queryKey: ['rbc', 'pemetaan'], queryFn: rbc.claimRules });
    const roles = useQuery({ queryKey: ['rbc', 'roles'], queryFn: rbc.roles });
    const catalogue = useQuery({ queryKey: ['rbc', 'catalogue'], queryFn: rbc.catalogue });
    const [draft, setDraft] = useState<Draft | null>(null);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [test, setTest] = useState({ roles: '', bahagian: '', unit: '', jawatan: '' });
    const [preview, setPreview] = useState<string | null>(null);

    const save = useMutation({
        mutationFn: (d: Draft) => {
            const payload = {
                jenis_tuntutan: d.jenis_tuntutan,
                nilai: d.nilai,
                peranan_id: d.peranan_id ? Number(d.peranan_id) : null,
                skop_jenis: d.skop_jenis || null,
                skop_nilai: d.skop_nilai || null,
                keutamaan: Number(d.keutamaan || 100),
                keterangan: d.keterangan || null,
                aktif: d.aktif,
            };
            return d.id ? rbc.updateClaimRule(d.id, payload) : rbc.createClaimRule(payload);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['rbc', 'pemetaan'] });
            toast.success('Peraturan disimpan. Berkuat kuasa pada log masuk SSO seterusnya.');
            setDraft(null);
        },
        onError: (error) => {
            if (error instanceof ApiError && error.errors) setErrors(error.errors);
            else toast.error(error instanceof Error ? error.message : 'Gagal.');
        },
    });
    const remove = useMutation({
        mutationFn: (id: number) => rbc.deleteClaimRule(id),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['rbc', 'pemetaan'] }),
    });
    const runPreview = useMutation({
        mutationFn: () =>
            rbc.previewClaims({
                roles: test.roles.split(',').map((r) => r.trim()).filter(Boolean),
                bahagian: test.bahagian || undefined,
                unit: test.unit || undefined,
                jawatan: test.jawatan || undefined,
            }),
        onSuccess: (res) => {
            const r = res.data.peranan.map((p) => p.nama).join(', ') || 'tiada';
            const s = Object.entries(res.data.skop).map(([t, v]) => `${t}: ${v.join(', ')}`).join('; ') || 'tiada had';
            setPreview(`Peranan: ${r}. Skop: ${s}.`);
        },
    });

    function edit(rule: ClaimRule) {
        setErrors({});
        setDraft({
            id: rule.id,
            jenis_tuntutan: rule.jenis_tuntutan,
            nilai: rule.nilai,
            peranan_id: rule.peranan ? String(rule.peranan.id) : '',
            skop_jenis: rule.skop_jenis ?? '',
            skop_nilai: rule.skop_nilai ?? '',
            keutamaan: String(rule.keutamaan),
            keterangan: rule.keterangan ?? '',
            aktif: rule.aktif,
        });
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        if (draft) save.mutate(draft);
    }

    const list = rules.data?.data ?? [];
    const scopeTypes = catalogue.data?.data.jenis_skop ?? {};

    return (
        <PageContainer>
            <Card
                title="Peraturan pemetaan"
                actions={
                    <Button
                        onClick={() => {
                            setErrors({});
                            setDraft(EMPTY);
                        }}
                    >
                        Tambah Peraturan
                    </Button>
                }
            >
                <p className="mb-4 text-body-sm text-txt-black-500">
                    Pada setiap log masuk SSO MAIS, tuntutan identiti (roles, bahagian, unit, jawatan) dipadankan dengan peraturan ini. Nilai boleh menggunakan * sebagai kad liar; skop nilai {'{nilai}'} menyalin nilai tuntutan. Peranan yang diberi secara manual oleh pentadbir tidak terjejas.
                </p>
                <ScrollX>
                    <Table
                        head={
                            <>
                                <th>Jika tuntutan</th>
                                <th>Berikan peranan</th>
                                <th>Berikan skop</th>
                                <th>Status</th>
                                <th className="text-right">Tindakan</th>
                            </>
                        }
                    >
                        {rules.isLoading ? (
                            <EmptyRow colSpan={5}>Memuatkan…</EmptyRow>
                        ) : list.length === 0 ? (
                            <EmptyRow colSpan={5}>Tiada peraturan.</EmptyRow>
                        ) : (
                            list.map((rule) => (
                                <tr key={rule.id}>
                                    <td>
                                        <div className="text-body-xs text-txt-black-500">{CLAIM_LABEL[rule.jenis_tuntutan]}</div>
                                        <div className="font-medium break-words">{rule.nilai}</div>
                                        {rule.keterangan && <div className="text-body-xs text-txt-black-500">{rule.keterangan}</div>}
                                    </td>
                                    <td>{rule.peranan?.nama ?? '—'}</td>
                                    <td>{rule.skop_jenis ? `${scopeTypes[rule.skop_jenis] ?? rule.skop_jenis}: ${rule.skop_nilai}` : '—'}</td>
                                    <td>{rule.aktif ? <Badge variant="success">Aktif</Badge> : <Badge>Tidak aktif</Badge>}</td>
                                    <td className="text-right whitespace-nowrap space-x-3">
                                        <button type="button" onClick={() => edit(rule)} className="text-primary-600 hover:text-primary-700 text-body-sm font-medium">
                                            Ubah
                                        </button>
                                        <button type="button" onClick={() => remove.mutate(rule.id)} className="text-danger-600 hover:text-danger-700 text-body-sm font-medium">
                                            Padam
                                        </button>
                                    </td>
                                </tr>
                            ))
                        )}
                    </Table>
                </ScrollX>
            </Card>

            <Card title="Uji pemetaan">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <TextInput id="uji-roles" label="Roles (pisah dengan koma)" value={test.roles} onChange={(v) => setTest((t) => ({ ...t, roles: v }))} />
                    <TextInput id="uji-bahagian" label="Bahagian" value={test.bahagian} onChange={(v) => setTest((t) => ({ ...t, bahagian: v }))} />
                    <TextInput id="uji-unit" label="Unit" value={test.unit} onChange={(v) => setTest((t) => ({ ...t, unit: v }))} />
                    <TextInput id="uji-jawatan" label="Jawatan" value={test.jawatan} onChange={(v) => setTest((t) => ({ ...t, jawatan: v }))} />
                </div>
                <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Button variant="secondary" onClick={() => runPreview.mutate()} disabled={runPreview.isPending}>
                        Uji
                    </Button>
                    {preview && <p className="text-body-sm text-txt-black-700" aria-live="polite">{preview}</p>}
                </div>
            </Card>

            <Modal open={draft !== null} onClose={() => setDraft(null)}>
                {draft && (
                    <form onSubmit={submit} className="p-6 space-y-4">
                        <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">{draft.id ? 'Ubah peraturan' : 'Tambah peraturan'}</h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Select id="jenis" label="Jenis tuntutan" value={draft.jenis_tuntutan} onChange={(e) => setDraft({ ...draft, jenis_tuntutan: e.target.value as Draft['jenis_tuntutan'] })}>
                                {Object.entries(CLAIM_LABEL).map(([k, v]) => (
                                    <option key={k} value={k}>
                                        {v}
                                    </option>
                                ))}
                            </Select>
                            <TextInput id="nilai" label="Nilai tuntutan" value={draft.nilai} onChange={(v) => setDraft({ ...draft, nilai: v })} />
                            <Select id="peranan" label="Peranan SISDATA" value={draft.peranan_id} onChange={(e) => setDraft({ ...draft, peranan_id: e.target.value })} className="sm:col-span-2">
                                <option value="">— Tiada —</option>
                                {(roles.data?.data ?? []).map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {r.nama}
                                    </option>
                                ))}
                            </Select>
                            <Select id="skop-jenis" label="Jenis skop" value={draft.skop_jenis} onChange={(e) => setDraft({ ...draft, skop_jenis: e.target.value })}>
                                <option value="">— Tiada —</option>
                                {Object.entries(scopeTypes).map(([k, v]) => (
                                    <option key={k} value={k}>
                                        {v}
                                    </option>
                                ))}
                            </Select>
                            <TextInput id="skop-nilai" label="Nilai skop" placeholder="{nilai}" value={draft.skop_nilai} onChange={(v) => setDraft({ ...draft, skop_nilai: v })} />
                            <TextInput id="keutamaan" type="number" label="Keutamaan" value={draft.keutamaan} onChange={(v) => setDraft({ ...draft, keutamaan: v })} />
                            <TextInput id="keterangan" label="Keterangan" value={draft.keterangan} onChange={(v) => setDraft({ ...draft, keterangan: v })} />
                        </div>
                        <label className="flex items-center gap-2 text-body-sm">
                            <input type="checkbox" checked={draft.aktif} onChange={(e) => setDraft({ ...draft, aktif: e.target.checked })} className="rounded border-otl-gray-300 text-primary-600" />
                            Aktif
                        </label>
                        {Object.keys(errors).length > 0 && <p className="text-body-sm text-txt-danger">{Object.values(errors).flat().join(' ')}</p>}
                        <div className="flex justify-end gap-3">
                            <Button variant="secondary" onClick={() => setDraft(null)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={save.isPending}>
                                Simpan
                            </Button>
                        </div>
                    </form>
                )}
            </Modal>
        </PageContainer>
    );
}
