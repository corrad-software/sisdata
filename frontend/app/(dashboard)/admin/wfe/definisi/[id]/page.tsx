'use client';

import Link from 'next/link';
import { Suspense, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { api, ApiError } from '@/lib/api';
import { PageContainer } from '@/components/PageContainer';
import { AnchorButton, Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { Table } from '@/components/ui/Table';
import { Textarea } from '@/components/ui/Textarea';
import { errorMessage, fmtDateTime, stepUpUrl, useCan, useMe, useMfa, useReference, type WfeDefinitionDetail, type WfeStructure } from '@/lib/wfe';
import { Card, StatusBadge } from '../../_components/common';
import { MfaResult } from '../../_components/MfaResult';
import { FormEditor } from './FormEditor';
import { FlowEditor } from './FlowEditor';

type Tab = 'borang' | 'editor_visual' | 'simulasi' | 'versi';
const TABS: Record<Tab, string> = { borang: 'Borang', editor_visual: 'Editor visual', simulasi: 'Simulasi', versi: 'Versi & log' };
const KAEDAH: Record<string, string> = { borang: 'Borang', editor_visual: 'Editor visual', api: 'API', salin: 'Salinan', ai: 'Arahan AI', sistem: 'Sistem' };
const TINDAKAN: Record<string, string> = {
    cipta: 'Dicipta', kemas_kini: 'Dikemas kini', salin: 'Disalin', versi_baharu: 'Versi baharu', mohon_aktif: 'Mohon pengaktifan',
    aktif: 'Diaktifkan', tolak_aktif: 'Pengaktifan ditolak', arkib: 'Diarkibkan',
};

function toStructure(d: WfeDefinitionDetail): WfeStructure {
    return {
        kod_aliran: d.kod_aliran, nama: d.nama, keterangan: d.keterangan, modul: d.modul, jenis_transaksi: d.jenis_transaksi, templat: d.templat,
        peringkat: d.peringkat, peralihan: d.peralihan, susulan: d.susulan, susun_atur: d.susun_atur,
    };
}

/** SRS-WFE-03/09/12: one definition, two editors (form + React Flow), saved through the same API. */
export default function DefinisiEditorPage() {
    const { id } = useParams<{ id: string }>();
    const router = useRouter();
    const can = useCan();
    const { data: me } = useMe();
    const { data: mfa } = useMfa();
    const { data: reference } = useReference();
    const queryClient = useQueryClient();
    const [tab, setTab] = useState<Tab>('borang');
    const [draft, setDraft] = useState<WfeStructure | null>(null);
    const [editedWith, setEditedWith] = useState<'borang' | 'editor_visual'>('borang');
    const [dirty, setDirty] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [note, setNote] = useState('');

    const { data, isLoading, error } = useQuery({
        queryKey: ['wfe', 'definisi', 'detail', id],
        queryFn: () => api.get<{ data: WfeDefinitionDetail }>(`/wfe/definisi/${id}`).then((r) => r.data),
    });

    // Reset the working copy whenever a fresh definition arrives (render-time sync, no effect).
    const [synced, setSynced] = useState<WfeDefinitionDetail | undefined>(undefined);
    if (data && data !== synced) {
        setSynced(data);
        setDraft(toStructure(data));
        setDirty(false);
    }

    const refresh = () => queryClient.invalidateQueries({ queryKey: ['wfe', 'definisi'] });
    const onError = (e: unknown) => {
        if (e instanceof ApiError && e.errors) setErrors(e.errors);
        toast.error(errorMessage(e));
    };

    const save = useMutation({
        mutationFn: () => api.put<{ message: string }>(`/wfe/definisi/${id}`, { ...draft, kaedah: editedWith }),
        onSuccess: (r) => {
            toast.success(r.message);
            setErrors({});
            refresh();
        },
        onError,
    });
    const action = useMutation({
        mutationFn: ({ path, body }: { path: string; body?: unknown }) => api.post<{ message: string; data?: { id: number } }>(`/wfe/definisi/${id}/${path}`, body ?? {}),
        onSuccess: (r) => {
            toast.success(r.message);
            if (r.data?.id) router.push(`/admin/wfe/definisi/${r.data.id}`);
            refresh();
        },
        onError,
    });

    if (isLoading || !draft) return <PageContainer>{error ? <Alert type="error">{errorMessage(error)}</Alert> : 'Memuatkan…'}</PageContainer>;
    if (!data) return null;

    const readOnly = data.status !== 'draf' || !can('wfe.definisi.kemas_kini');
    const update = (s: WfeStructure, method: 'borang' | 'editor_visual') => {
        setDraft(s);
        setEditedWith(method);
        setDirty(true);
    };
    const returnPath = `/admin/wfe/definisi/${id}`;
    const isRequester = data.diminta_aktif_oleh !== null && data.diminta_aktif_oleh === me?.id;

    return (
        <PageContainer>
            <Suspense>
                <MfaResult />
            </Suspense>

            <Card
                title={`${data.nama} — versi ${data.no_versi}`}
                actions={
                    <div className="flex flex-wrap gap-2 items-center">
                        <StatusBadge status={data.status} />
                        {data.status === 'draf' && can('wfe.definisi.kemas_kini') && (
                            <>
                                <Button size="sm" onClick={() => save.mutate()} disabled={!dirty || save.isPending}>
                                    Simpan
                                </Button>
                                <Button size="sm" variant="outline" disabled={dirty || action.isPending} onClick={() => action.mutate({ path: 'mohon-aktif' })}>
                                    Mohon pengaktifan
                                </Button>
                            </>
                        )}
                        {(data.status === 'aktif' || data.status === 'arkib') && can('wfe.definisi.kemas_kini') && (
                            <Button size="sm" variant="outline" disabled={action.isPending} onClick={() => action.mutate({ path: 'versi-baharu' })}>
                                Cipta versi baharu
                            </Button>
                        )}
                    </div>
                }
            >
                <p className="text-body-xs text-txt-black-500">
                    {data.kod_aliran} · {data.modul} · {data.jenis_transaksi} · kaedah cipta: {data.kaedah_cipta}
                    {data.tarikh_berkuat_kuasa ? ` · berkuat kuasa ${fmtDateTime(data.tarikh_berkuat_kuasa)}` : ''}
                </p>
                {readOnly && data.status !== 'draf' && (
                    <Alert type="info">Versi {STATUS_TEXT[data.status] ?? data.status} tidak boleh diubah. Cipta versi baharu untuk meminda; kes yang sedang berjalan kekal pada versi asal.</Alert>
                )}
                {dirty && <Alert type="warning">Terdapat perubahan belum disimpan (kaedah: {KAEDAH[editedWith]}).</Alert>}
                {Object.keys(errors).length > 0 && (
                    <Alert type="error">
                        <ul className="list-disc ms-4">
                            {Object.entries(errors).map(([k, v]) => (
                                <li key={k}>
                                    {k}: {v[0]}
                                </li>
                            ))}
                        </ul>
                    </Alert>
                )}

                {data.status === 'menunggu_pengaktifan' && can('wfe.definisi.lulus') && (
                    <div className="rounded-md border border-otl-warning-200 bg-bg-warning-50 p-4 space-y-3">
                        <p className="text-body-sm text-txt-warning font-medium">Menunggu kelulusan pentadbir kedua.</p>
                        {isRequester ? (
                            <p className="text-body-sm">Anda memohon pengaktifan ini — pentadbir lain perlu meluluskannya.</p>
                        ) : (
                            <>
                                {!mfa?.sah && (
                                    <AnchorButton size="sm" href={stepUpUrl(returnPath)}>
                                        Sahkan dengan MFA untuk meluluskan
                                    </AnchorButton>
                                )}
                                <Textarea label="Catatan" name="catatan" value={note} onChange={setNote} rows={2} />
                                <div className="flex gap-2">
                                    <Button size="sm" disabled={!mfa?.sah || action.isPending} onClick={() => action.mutate({ path: 'lulus-aktif', body: { catatan: note || undefined } })}>
                                        Luluskan &amp; aktifkan
                                    </Button>
                                    <Button size="sm" variant="danger" disabled={!note.trim() || action.isPending} onClick={() => action.mutate({ path: 'tolak-aktif', body: { catatan: note } })}>
                                        Tolak
                                    </Button>
                                </div>
                            </>
                        )}
                    </div>
                )}
            </Card>

            <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200">
                <div className="border-b border-otl-divider px-4 sm:px-6">
                    <nav className="flex gap-6 -mb-px overflow-x-auto" role="tablist">
                        {(Object.keys(TABS) as Tab[]).map((t) => (
                            <button
                                key={t}
                                type="button"
                                role="tab"
                                aria-selected={tab === t}
                                onClick={() => setTab(t)}
                                className={`whitespace-nowrap border-b-2 py-3 text-body-sm font-medium ${
                                    tab === t ? 'border-primary-600 text-primary-600' : 'border-transparent text-txt-black-500 hover:text-txt-black-900'
                                }`}
                            >
                                {TABS[t]}
                            </button>
                        ))}
                    </nav>
                </div>
                <div className="p-4 sm:p-6">
                    {tab === 'borang' && <FormEditor value={draft} onChange={(s) => update(s, 'borang')} reference={reference} readOnly={readOnly} />}
                    {tab === 'editor_visual' && <FlowEditor value={draft} onChange={(s) => update(s, 'editor_visual')} reference={reference} readOnly={readOnly} />}
                    {tab === 'simulasi' && <Simulation structure={draft} fields={reference?.aliran.find((f) => f.kod_aliran === draft.kod_aliran)?.medan_konteks ?? []} />}
                    {tab === 'versi' && <Versions detail={data} />}
                </div>
            </div>
        </PageContainer>
    );
}

const STATUS_TEXT: Record<string, string> = { aktif: 'aktif', arkib: 'arkib', menunggu_pengaktifan: 'yang menunggu pengaktifan' };

function Simulation({ structure, fields }: { structure: WfeStructure; fields: string[] }) {
    const [context, setContext] = useState('{\n  "nilai": 1500000,\n  "kod_daerah": "10"\n}');
    const sim = useMutation({
        mutationFn: () => api.post<{ data: { laluan: Array<{ kod: string; nama: string }> } }>('/wfe/definisi/simulasi', { struktur: structure, konteks: JSON.parse(context) }),
        onError: (e) => toast.error(e instanceof SyntaxError ? 'Konteks bukan JSON yang sah.' : errorMessage(e)),
    });

    return (
        <div className="space-y-4">
            <p className="text-body-sm text-txt-black-500">
                Pratonton laluan bagi kes contoh tanpa menyimpan apa-apa. {fields.length > 0 && `Medan konteks diisytiharkan modul: ${fields.join(', ')}.`}
            </p>
            <Textarea label="Konteks contoh (JSON)" name="konteks" value={context} onChange={setContext} rows={6} />
            <Button onClick={() => sim.mutate()} disabled={sim.isPending}>
                Jalankan simulasi
            </Button>
            {sim.data && (
                <ol className="flex flex-wrap items-center gap-2 text-body-sm">
                    <li className="rounded-full bg-bg-success-50 px-3 py-1">Mula</li>
                    {sim.data.data.laluan.map((s) => (
                        <li key={s.kod} className="rounded-full bg-bg-primary-50 px-3 py-1">
                            → {s.nama}
                        </li>
                    ))}
                    <li className="rounded-full bg-bg-washed px-3 py-1">→ Tamat</li>
                </ol>
            )}
        </div>
    );
}

function Versions({ detail }: { detail: WfeDefinitionDetail }) {
    return (
        <div className="space-y-6">
            <Table head={<><th>Versi</th><th>Status</th><th>Berkuat kuasa</th><th className="text-right">Kes berjalan</th></>}>
                {detail.versi.map((v) => (
                    <tr key={v.id}>
                        <td>
                            <Link href={`/admin/wfe/definisi/${v.id}`} className="text-primary-600 hover:underline">
                                v{v.no_versi}
                            </Link>
                        </td>
                        <td><StatusBadge status={v.status} /></td>
                        <td>{fmtDateTime(v.tarikh_berkuat_kuasa)}</td>
                        <td className="text-right">{v.instans_berjalan}</td>
                    </tr>
                ))}
            </Table>
            <div>
                <h4 className="font-semibold text-body-md mb-2">Log perubahan versi ini</h4>
                <Table head={<><th>Masa</th><th>Tindakan</th><th>Kaedah</th><th>Pengguna</th><th>Catatan / arahan</th></>}>
                    {detail.log.map((l, i) => (
                        <tr key={i}>
                            <td className="whitespace-nowrap">{fmtDateTime(l.created_at)}</td>
                            <td>{TINDAKAN[l.tindakan] ?? l.tindakan}</td>
                            <td>{KAEDAH[l.kaedah] ?? l.kaedah}</td>
                            <td>{l.pengguna ?? 'Sistem'}</td>
                            <td>{l.arahan ?? '-'}</td>
                        </tr>
                    ))}
                </Table>
            </div>
        </div>
    );
}
