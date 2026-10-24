'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { api, ApiError } from '@/lib/api';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { FieldInput } from '@/components/ui/FieldInput';
import { InputLabel } from '@/components/ui/InputLabel';
import { emptyStage, errorMessage, fmtDateTime, serialTransitions, useCan, useReference, type WfeDefinitionRow } from '@/lib/wfe';
import { Card, StatusBadge } from '../_components/common';

/** SRS-WFE-01..04: flow definitions and the template library. */
export default function DefinisiPage() {
    const can = useCan();
    const router = useRouter();
    const [templatesOnly, setTemplatesOnly] = useState(false);
    const [creating, setCreating] = useState<{ kod_aliran: string; nama: string; modul: string; jenis_transaksi: string; templat: boolean } | null>(null);
    const [copying, setCopying] = useState<{ source: WfeDefinitionRow; kod_aliran: string; nama: string; templat: boolean } | null>(null);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const { data: ref } = useReference();

    const { data, isLoading } = useQuery({
        queryKey: ['wfe', 'definisi', templatesOnly],
        queryFn: () => api.get<{ data: WfeDefinitionRow[] }>(`/wfe/definisi${templatesOnly ? '?templat=1' : ''}`).then((r) => r.data),
    });

    const create = useMutation({
        mutationFn: () => {
            const stages = [emptyStage(1)];
            return api.post<{ data: { id: number } }>('/wfe/definisi', {
                ...creating,
                keterangan: null,
                peringkat: stages,
                peralihan: serialTransitions(stages),
                susulan: [],
                kaedah: 'borang',
            });
        },
        onSuccess: (r) => router.push(`/admin/wfe/definisi/${r.data.id}`),
        onError: (e) => (e instanceof ApiError && e.errors ? setErrors(e.errors) : toast.error(errorMessage(e))),
    });

    const copy = useMutation({
        mutationFn: () =>
            api.post<{ data: { id: number } }>(`/wfe/definisi/${copying?.source.id}/salin`, { kod_aliran: copying?.kod_aliran, nama: copying?.nama, templat: copying?.templat }),
        onSuccess: (r) => router.push(`/admin/wfe/definisi/${r.data.id}`),
        onError: (e) => (e instanceof ApiError && e.errors ? setErrors(e.errors) : toast.error(errorMessage(e))),
    });

    function openCreate(flow?: { kod_aliran: string; nama: string; modul: string; jenis_subjek: string }) {
        setErrors({});
        setCreating({ kod_aliran: flow?.kod_aliran ?? '', nama: flow?.nama ?? '', modul: flow?.modul ?? '', jenis_transaksi: flow?.jenis_subjek ?? '', templat: false });
    }

    const defined = new Set((data ?? []).map((d) => d.kod_aliran));
    const undefinedFlows = (ref?.aliran ?? []).filter((f) => !defined.has(f.kod_aliran));

    return (
        <PageContainer>
            {can('wfe.definisi.cipta') && undefinedFlows.length > 0 && (
                <Card title="Aliran diisytiharkan modul tetapi belum ditakrifkan">
                    <ul className="space-y-2">
                        {undefinedFlows.map((f) => (
                            <li key={f.kod_aliran} className="flex flex-wrap items-center justify-between gap-2 text-body-sm">
                                <span>
                                    <span className="font-medium">{f.nama}</span> <span className="text-txt-black-500">({f.kod_aliran} · {f.modul})</span>
                                </span>
                                <Button size="sm" variant="outline" onClick={() => openCreate(f)}>
                                    Takrifkan
                                </Button>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}

            <Card
                title={templatesOnly ? 'Pustaka templat' : 'Definisi aliran kerja'}
                actions={
                    <div className="flex flex-wrap gap-2">
                        <Button size="sm" variant={templatesOnly ? 'secondary' : 'primary'} onClick={() => setTemplatesOnly(false)}>
                            Semua aliran
                        </Button>
                        <Button size="sm" variant={templatesOnly ? 'primary' : 'secondary'} onClick={() => setTemplatesOnly(true)}>
                            Templat
                        </Button>
                        {can('wfe.definisi.cipta') && (
                            <Button size="sm" variant="outline" onClick={() => openCreate()}>
                                Aliran baharu
                            </Button>
                        )}
                    </div>
                }
            >
                <Table head={<><th>Aliran</th><th>Modul / transaksi</th><th>Versi terkini</th><th>Versi aktif</th><th>Peringkat</th><th>Dikemas kini</th><th className="text-right">Tindakan</th></>}>
                    {isLoading ? (
                        <tr><td colSpan={7} className="text-center text-txt-black-500 py-6">Memuatkan…</td></tr>
                    ) : (data ?? []).length === 0 ? (
                        <tr><td colSpan={7} className="text-center text-txt-black-500 py-6">Tiada definisi.</td></tr>
                    ) : (
                        data?.map((d) => (
                            <tr key={d.id}>
                                <td>
                                    <Link href={`/admin/wfe/definisi/${d.id}`} className="font-medium text-primary-600 hover:underline">{d.nama}</Link>
                                    <div className="text-body-xs text-txt-black-500">
                                        {d.kod_aliran} {d.templat && <Badge variant="info">Templat</Badge>}
                                    </div>
                                </td>
                                <td>{d.modul} · {d.jenis_transaksi}</td>
                                <td>v{d.no_versi} <StatusBadge status={d.status} /></td>
                                <td>{d.versi_aktif ? `v${d.versi_aktif}` : '-'}</td>
                                <td>{d.bil_peringkat}</td>
                                <td className="whitespace-nowrap">{fmtDateTime(d.updated_at)}</td>
                                <td className="text-right">
                                    {can('wfe.definisi.cipta') && (
                                        <button
                                            type="button"
                                            className="text-primary-600 text-body-sm font-medium"
                                            onClick={() => {
                                                setErrors({});
                                                setCopying({ source: d, kod_aliran: '', nama: `${d.nama} (salinan)`, templat: false });
                                            }}
                                        >
                                            Salin
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))
                    )}
                </Table>
            </Card>

            <Modal open={!!creating} onClose={() => setCreating(null)}>
                {creating && (
                    <form
                        className="p-6 space-y-4"
                        onSubmit={(e: FormEvent) => {
                            e.preventDefault();
                            create.mutate();
                        }}
                    >
                        <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Aliran baharu</h2>
                        <FieldInput label="Kod aliran" name="kod_aliran" value={creating.kod_aliran} onChange={(v) => setCreating({ ...creating, kod_aliran: v })} required help="cth. pjk.lanjutan_pajakan" error={errors.kod_aliran?.[0]} />
                        <FieldInput label="Nama" name="nama" value={creating.nama} onChange={(v) => setCreating({ ...creating, nama: v })} required error={errors.nama?.[0]} />
                        <div className="grid grid-cols-2 gap-4">
                            <FieldInput label="Modul" name="modul" value={creating.modul} onChange={(v) => setCreating({ ...creating, modul: v })} required error={errors.modul?.[0]} />
                            <FieldInput label="Jenis transaksi" name="jenis_transaksi" value={creating.jenis_transaksi} onChange={(v) => setCreating({ ...creating, jenis_transaksi: v })} required error={errors.jenis_transaksi?.[0]} />
                        </div>
                        <label className="flex items-center gap-2 text-body-sm">
                            <input type="checkbox" checked={creating.templat} onChange={(e) => setCreating({ ...creating, templat: e.target.checked })} className="rounded border-otl-gray-300 text-primary-600" />
                            Simpan sebagai templat
                        </label>
                        <div className="flex justify-end gap-3">
                            <Button variant="secondary" onClick={() => setCreating(null)}>Batal</Button>
                            <Button type="submit" disabled={create.isPending}>Cipta draf</Button>
                        </div>
                    </form>
                )}
            </Modal>

            <Modal open={!!copying} onClose={() => setCopying(null)}>
                {copying && (
                    <form
                        className="p-6 space-y-4"
                        onSubmit={(e: FormEvent) => {
                            e.preventDefault();
                            copy.mutate();
                        }}
                    >
                        <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Salin “{copying.source.nama}”</h2>
                        <FieldInput label="Kod aliran baharu" name="kod_salin" value={copying.kod_aliran} onChange={(v) => setCopying({ ...copying, kod_aliran: v })} required error={errors.kod_aliran?.[0]} />
                        <FieldInput label="Nama" name="nama_salin" value={copying.nama} onChange={(v) => setCopying({ ...copying, nama: v })} required error={errors.nama?.[0]} />
                        <div>
                            <InputLabel htmlFor="jenis_salin" className="mb-1.5">Jenis</InputLabel>
                            <select id="jenis_salin" value={copying.templat ? '1' : '0'} onChange={(e) => setCopying({ ...copying, templat: e.target.value === '1' })} className="block w-full rounded-md border-otl-gray-300 bg-bg-white text-body-sm">
                                <option value="0">Aliran</option>
                                <option value="1">Templat</option>
                            </select>
                        </div>
                        <div className="flex justify-end gap-3">
                            <Button variant="secondary" onClick={() => setCopying(null)}>Batal</Button>
                            <Button type="submit" disabled={copy.isPending}>Salin</Button>
                        </div>
                    </form>
                )}
            </Modal>
        </PageContainer>
    );
}
