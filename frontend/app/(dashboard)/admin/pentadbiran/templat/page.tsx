'use client';

import Link from 'next/link';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { FieldInput } from '@/components/ui/FieldInput';
import { Textarea } from '@/components/ui/Textarea';
import { InputLabel } from '@/components/ui/InputLabel';
import { api, ApiError } from '@/lib/api';
import { errorText, formatDateTime, TEMPLAT_FORMATS, type Templat } from '@/lib/adm';

type TemplatDetail = Templat & { versi_list: { versi: number; format_output: string; created_at: string }[] };

export default function TemplatPage() {
    const { data, isLoading } = useQuery({ queryKey: ['adm', 'templat'], queryFn: () => api.get<{ data: Templat[] }>('/adm/templat') });
    const [editing, setEditing] = useState<Templat | 'baru' | null>(null);

    return (
        <PageContainer>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-body-sm text-txt-black-500">
                    Templat laporan dengan medan dinamik <code>{'{{medan}}'}</code>. Templat notifikasi diurus dalam{' '}
                    <Link href="/admin/notifikasi/jenis" className="text-primary-600 underline">
                        modul Notifikasi
                    </Link>
                    .
                </p>
                <Button onClick={() => setEditing('baru')}>Tambah Templat</Button>
            </div>

            <Table
                head={
                    <>
                        <th>Kod</th>
                        <th>Nama</th>
                        <th>Format</th>
                        <th>Versi</th>
                        <th>Status</th>
                        <th>Dikemas kini</th>
                        <th className="text-right">Tindakan</th>
                    </>
                }
            >
                {isLoading ? (
                    <tr>
                        <td colSpan={7} className="text-center text-txt-black-500">Memuatkan…</td>
                    </tr>
                ) : (data?.data ?? []).length === 0 ? (
                    <tr>
                        <td colSpan={7} className="text-center text-txt-black-500">Tiada templat.</td>
                    </tr>
                ) : (
                    data?.data.map((t) => (
                        <tr key={t.id}>
                            <td className="font-mono text-body-xs">{t.kod}</td>
                            <td>{t.nama}</td>
                            <td className="uppercase">{t.format_output}</td>
                            <td>v{t.versi}</td>
                            <td>
                                <Badge variant={t.status === 'aktif' ? 'success' : 'neutral'}>{t.status}</Badge>
                            </td>
                            <td className="whitespace-nowrap">{formatDateTime(t.updated_at)}</td>
                            <td className="text-right">
                                <button type="button" className="text-primary-600 text-body-sm font-medium" onClick={() => setEditing(t)}>
                                    Buka
                                </button>
                            </td>
                        </tr>
                    ))
                )}
            </Table>

            {editing && <TemplatModal templat={editing === 'baru' ? null : editing} onClose={() => setEditing(null)} />}
        </PageContainer>
    );
}

function TemplatModal({ templat, onClose }: { templat: Templat | null; onClose: () => void }) {
    const queryClient = useQueryClient();
    const detail = useQuery({
        queryKey: ['adm', 'templat', templat?.id],
        queryFn: () => api.get<{ data: TemplatDetail }>(`/adm/templat/${templat?.id}`),
        enabled: templat !== null,
    });
    const [kod, setKod] = useState(templat?.kod ?? '');
    const [nama, setNama] = useState(templat?.nama ?? '');
    const [format, setFormat] = useState(templat?.format_output ?? 'pdf');
    const [medan, setMedan] = useState((templat?.medan ?? []).join(', '));
    const [kandungan, setKandungan] = useState(templat?.kandungan ?? '');
    const [status, setStatus] = useState(templat?.status ?? 'aktif');
    const [justifikasi, setJustifikasi] = useState('');
    const [preview, setPreview] = useState<string | null>(null);
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    const fields = medan
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);

    const done = (res: { message: string }) => {
        toast.success(res.message);
        queryClient.invalidateQueries({ queryKey: ['adm', 'templat'] });
    };

    const save = useMutation({
        mutationFn: () => {
            const body = { nama, format_output: format, medan: fields, kandungan, status, justifikasi: justifikasi || null };
            return templat ? api.put<{ message: string }>(`/adm/templat/${templat.id}`, body) : api.post<{ message: string }>('/adm/templat', { ...body, kod });
        },
        onSuccess: (res) => {
            done(res);
            onClose();
        },
        onError: (e) => (e instanceof ApiError && e.errors ? setErrors(e.errors) : toast.error(errorText(e))),
    });

    const doPreview = useMutation({
        mutationFn: () => api.post<{ data: { kandungan: string } }>(`/adm/templat/${templat?.id}/pratonton`, {}),
        onSuccess: (res) => setPreview(res.data.kandungan),
    });

    const restore = useMutation({
        mutationFn: (versi: number) => api.post<{ message: string }>(`/adm/templat/${templat?.id}/versi/${versi}/pulih`, {}),
        onSuccess: (res) => {
            done(res);
            onClose();
        },
        onError: (e) => toast.error(errorText(e)),
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        save.mutate();
    }

    return (
        <Modal open onClose={onClose}>
            <form onSubmit={submit} className="p-6 space-y-4 max-h-[85vh] overflow-y-auto">
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">{templat ? `Templat ${templat.kod} (v${templat.versi})` : 'Tambah Templat'}</h2>
                {!templat && <FieldInput label="Kod" name="kod" value={kod} onChange={setKod} required error={errors.kod?.[0]} />}
                <FieldInput label="Nama" name="nama" value={nama} onChange={setNama} required error={errors.nama?.[0]} />
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel htmlFor="format" className="mb-1.5">
                            Format output
                        </InputLabel>
                        <select id="format" value={format} onChange={(e) => setFormat(e.target.value)} className="w-full rounded-md border-otl-gray-300 text-body-sm">
                            {TEMPLAT_FORMATS.map((f) => (
                                <option key={f} value={f}>
                                    {f.toUpperCase()}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <InputLabel htmlFor="status" className="mb-1.5">
                            Status
                        </InputLabel>
                        <select id="status" value={status} onChange={(e) => setStatus(e.target.value)} className="w-full rounded-md border-otl-gray-300 text-body-sm">
                            <option value="aktif">Aktif</option>
                            <option value="draf">Draf</option>
                            <option value="tidak_aktif">Tidak aktif</option>
                        </select>
                    </div>
                </div>
                <FieldInput label="Medan dinamik (pisah dengan koma)" name="medan" value={medan} onChange={setMedan} error={errors.medan?.[0]} />
                <Textarea label="Kandungan" name="kandungan" value={kandungan} onChange={setKandungan} rows={8} required error={errors.kandungan?.[0]} />
                <FieldInput label="Justifikasi" name="justifikasi" value={justifikasi} onChange={setJustifikasi} />

                {templat && (
                    <div className="space-y-2">
                        <Button size="sm" variant="secondary" onClick={() => doPreview.mutate()}>
                            Pratonton
                        </Button>
                        {preview !== null && (
                            <pre className="whitespace-pre-wrap rounded-md border border-otl-gray-200 bg-bg-washed p-3 text-body-xs">{preview}</pre>
                        )}
                        <h3 className="text-body-sm font-semibold">Versi</h3>
                        <ul className="space-y-1 text-body-sm">
                            {detail.data?.data.versi_list.map((v) => (
                                <li key={v.versi} className="flex items-center justify-between">
                                    <span>
                                        v{v.versi} · {v.format_output.toUpperCase()} · {formatDateTime(v.created_at)}
                                    </span>
                                    {v.versi !== templat.versi && (
                                        <button type="button" className="text-primary-600 text-body-xs font-medium" onClick={() => restore.mutate(v.versi)}>
                                            Pulihkan
                                        </button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    <Button type="submit" disabled={save.isPending}>
                        Simpan
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
