'use client';

import { Suspense, useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { useSearchParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { FieldInput } from '@/components/ui/FieldInput';
import { Alert } from '@/components/ui/Alert';
import { api, ApiError } from '@/lib/api';
import {
    errorText,
    fetchJenisKod,
    fetchKodRujukan,
    fetchKodSejarah,
    formatDateTime,
    type ImportReport,
    type KodRujukan,
} from '@/lib/adm';

interface Form {
    kod: string;
    keterangan: string;
    kod_induk: string;
    susunan: string;
    tarikh_mula: string;
    justifikasi: string;
}

const empty: Form = { kod: '', keterangan: '', kod_induk: '', susunan: '0', tarikh_mula: '', justifikasi: '' };

export default function KodRujukanPage() {
    return (
        <Suspense fallback={<PageContainer>Memuatkan…</PageContainer>}>
            <KodRujukanContent />
        </Suspense>
    );
}

function KodRujukanContent() {
    const params = useSearchParams();
    const queryClient = useQueryClient();
    const [jenis, setJenis] = useState(params.get('jenis') ?? 'daerah');
    const [search, setSearch] = useState(params.get('q') ?? '');
    const [q, setQ] = useState(search);
    const [status, setStatus] = useState('');
    const [page, setPage] = useState(1);
    const [editing, setEditing] = useState<KodRujukan | 'baru' | null>(null);
    const [history, setHistory] = useState<KodRujukan | null>(null);
    const [importOpen, setImportOpen] = useState(false);

    useEffect(() => {
        const t = setTimeout(() => {
            setQ(search);
            setPage(1);
        }, 350);
        return () => clearTimeout(t);
    }, [search]);

    const types = useQuery({ queryKey: ['adm', 'kod-jenis'], queryFn: fetchJenisKod });
    const list = useQuery({ queryKey: ['adm', 'kod', jenis, q, status, page], queryFn: () => fetchKodRujukan({ jenis, q, status, page }) });
    const jenisInfo = types.data?.data.find((t) => t.jenis_kod === jenis);

    const toggle = useMutation({
        mutationFn: (k: KodRujukan) => api.post<{ message: string }>(`/adm/kod-rujukan/${k.id}/${k.status === 'aktif' ? 'nyahaktif' : 'aktif'}`, {}),
        onSuccess: (res) => {
            toast.success(res.message);
            queryClient.invalidateQueries({ queryKey: ['adm'] });
        },
        onError: (e) => toast.error(errorText(e)),
    });

    const rows = list.data?.data ?? [];

    return (
        <PageContainer>
            <div className="grid gap-6 lg:grid-cols-[16rem_1fr]">
                <nav aria-label="Jenis kod" className="min-w-0 rounded-lg border border-otl-gray-200 bg-bg-white p-2 h-fit">
                    <ul className="flex lg:flex-col gap-1 overflow-x-auto">
                        {(types.data?.data ?? []).map((t) => (
                            <li key={t.jenis_kod}>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setJenis(t.jenis_kod);
                                        setPage(1);
                                    }}
                                    className={`w-full whitespace-nowrap rounded-md px-3 py-2 text-left text-body-sm ${
                                        t.jenis_kod === jenis ? 'bg-primary-50 text-primary-700 font-medium' : 'text-txt-black-700 hover:bg-bg-washed'
                                    }`}
                                >
                                    {t.label} <span className="text-txt-black-500">({t.aktif}/{t.jumlah})</span>
                                </button>
                            </li>
                        ))}
                    </ul>
                </nav>

                <div className="rounded-lg border border-otl-gray-200 bg-bg-white p-4 sm:p-6 shadow-card space-y-4 min-w-0">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h3 className="font-heading font-semibold text-body-lg text-txt-black-900">{jenisInfo?.label ?? jenis}</h3>
                        <div className="flex flex-wrap gap-2">
                            <input
                                type="search"
                                aria-label="Cari kod"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari kod / keterangan…"
                                className="rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 text-body-sm w-full sm:w-56"
                            />
                            <select
                                aria-label="Status"
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="rounded-md border-otl-gray-300 bg-bg-white text-body-sm"
                            >
                                <option value="">Semua status</option>
                                <option value="aktif">Aktif</option>
                                <option value="tidak_aktif">Tidak aktif</option>
                            </select>
                            <Button variant="secondary" onClick={() => setImportOpen(true)}>
                                Import
                            </Button>
                            <Button onClick={() => setEditing('baru')}>Tambah Kod</Button>
                        </div>
                    </div>

                    <Table
                        head={
                            <>
                                <th>Kod</th>
                                <th>Keterangan</th>
                                {jenisInfo?.induk && <th>Induk</th>}
                                <th>Sah</th>
                                <th>Status</th>
                                <th>Versi</th>
                                <th className="text-right">Tindakan</th>
                            </>
                        }
                    >
                        {list.isLoading ? (
                            <tr>
                                <td colSpan={7} className="text-center text-txt-black-500">Memuatkan…</td>
                            </tr>
                        ) : rows.length === 0 ? (
                            <tr>
                                <td colSpan={7} className="text-center text-txt-black-500">Tiada kod.</td>
                            </tr>
                        ) : (
                            rows.map((k) => (
                                <tr key={k.id}>
                                    <td className="font-mono">{k.kod}</td>
                                    <td>{k.keterangan}</td>
                                    {jenisInfo?.induk && <td>{k.kod_induk ?? '—'}</td>}
                                    <td className="whitespace-nowrap text-body-xs">
                                        {k.tarikh_mula} – {k.tarikh_tamat ?? '…'}
                                    </td>
                                    <td>
                                        <Badge variant={k.status === 'aktif' ? 'success' : 'neutral'}>{k.status === 'aktif' ? 'Aktif' : 'Tidak aktif'}</Badge>
                                    </td>
                                    <td>v{k.versi}</td>
                                    <td className="text-right whitespace-nowrap space-x-3">
                                        <button type="button" className="text-primary-600 text-body-sm font-medium" onClick={() => setEditing(k)}>
                                            Pinda
                                        </button>
                                        <button type="button" className="text-primary-600 text-body-sm font-medium" onClick={() => setHistory(k)}>
                                            Sejarah
                                        </button>
                                        <button
                                            type="button"
                                            className={`${k.status === 'aktif' ? 'text-danger-600' : 'text-primary-600'} text-body-sm font-medium`}
                                            onClick={() => toggle.mutate(k)}
                                        >
                                            {k.status === 'aktif' ? 'Nyahaktif' : 'Aktifkan'}
                                        </button>
                                    </td>
                                </tr>
                            ))
                        )}
                    </Table>

                    {list.data && list.data.last_page > 1 && (
                        <div className="flex items-center justify-between text-body-sm text-txt-black-500">
                            <span>
                                {list.data.from}–{list.data.to} daripada {list.data.total}
                            </span>
                            <div className="flex gap-2">
                                <Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                                    Sebelum
                                </Button>
                                <Button size="sm" variant="secondary" disabled={page >= list.data.last_page} onClick={() => setPage((p) => p + 1)}>
                                    Seterusnya
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {editing && <KodModal jenis={jenis} kod={editing === 'baru' ? null : editing} induk={jenisInfo?.induk ?? null} onClose={() => setEditing(null)} />}
            {history && <HistoryModal kod={history} onClose={() => setHistory(null)} />}
            {importOpen && <ImportModal jenis={jenis} onClose={() => setImportOpen(false)} />}
        </PageContainer>
    );
}

function KodModal({ jenis, kod, induk, onClose }: { jenis: string; kod: KodRujukan | null; induk: string | null; onClose: () => void }) {
    const queryClient = useQueryClient();
    const [form, setForm] = useState<Form>(
        kod
            ? { kod: kod.kod, keterangan: kod.keterangan, kod_induk: kod.kod_induk ?? '', susunan: String(kod.susunan), tarikh_mula: kod.tarikh_mula, justifikasi: '' }
            : empty,
    );
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const set = (k: keyof Form) => (v: string) => setForm((f) => ({ ...f, [k]: v }));

    const save = useMutation({
        mutationFn: () => {
            const body = {
                ...(kod ? {} : { jenis_kod: jenis }),
                kod: form.kod,
                keterangan: form.keterangan,
                kod_induk: form.kod_induk || null,
                susunan: Number(form.susunan || 0),
                tarikh_mula: form.tarikh_mula || null,
                justifikasi: form.justifikasi || null,
            };
            return kod ? api.put<{ message: string }>(`/adm/kod-rujukan/${kod.id}`, body) : api.post<{ message: string }>('/adm/kod-rujukan', body);
        },
        onSuccess: (res) => {
            toast.success(res.message);
            queryClient.invalidateQueries({ queryKey: ['adm'] });
            onClose();
        },
        onError: (e) => (e instanceof ApiError && e.errors ? setErrors(e.errors) : toast.error(errorText(e))),
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        save.mutate();
    }

    return (
        <Modal open onClose={onClose}>
            <form onSubmit={submit} className="p-6 space-y-4">
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">{kod ? 'Pinda Kod' : 'Tambah Kod'}</h2>
                <FieldInput label="Kod" name="kod" value={form.kod} onChange={set('kod')} required error={errors.kod?.[0]} />
                <FieldInput label="Keterangan" name="keterangan" value={form.keterangan} onChange={set('keterangan')} required error={errors.keterangan?.[0]} />
                {induk && <FieldInput label={`Kod induk (${induk})`} name="kod_induk" value={form.kod_induk} onChange={set('kod_induk')} error={errors.kod_induk?.[0]} />}
                <div className="grid grid-cols-2 gap-3">
                    <FieldInput label="Susunan" name="susunan" type="number" value={form.susunan} onChange={set('susunan')} error={errors.susunan?.[0]} />
                    <FieldInput label="Tarikh mula" name="tarikh_mula" type="date" value={form.tarikh_mula} onChange={set('tarikh_mula')} error={errors.tarikh_mula?.[0]} />
                </div>
                <FieldInput label="Justifikasi" name="justifikasi" value={form.justifikasi} onChange={set('justifikasi')} error={errors.justifikasi?.[0]} />
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

function HistoryModal({ kod, onClose }: { kod: KodRujukan; onClose: () => void }) {
    const { data } = useQuery({ queryKey: ['adm', 'kod-sejarah', kod.id], queryFn: () => fetchKodSejarah(kod.id) });

    return (
        <Modal open onClose={onClose}>
            <div className="p-6 space-y-4">
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">
                    Sejarah {kod.kod} (kini v{kod.versi})
                </h2>
                {data?.data.versi.length === 0 && <p className="text-body-sm text-txt-black-500">Belum pernah dipinda.</p>}
                <ol className="space-y-3">
                    {data?.data.versi.map((v) => (
                        <li key={v.versi} className="rounded-md border border-otl-gray-200 p-3 text-body-sm">
                            <p className="font-medium">
                                v{v.versi} · {formatDateTime(v.created_at)}
                            </p>
                            <p className="text-txt-black-500">
                                {String(v.data.keterangan)} · {String(v.data.status)} · {String(v.data.tarikh_mula)} – {String(v.data.tarikh_tamat ?? '…')}
                            </p>
                        </li>
                    ))}
                </ol>
                <div className="flex justify-end">
                    <Button variant="secondary" onClick={onClose}>
                        Tutup
                    </Button>
                </div>
            </div>
        </Modal>
    );
}

function ImportModal({ jenis, onClose }: { jenis: string; onClose: () => void }) {
    const queryClient = useQueryClient();
    const [file, setFile] = useState<File | null>(null);
    const [upsert, setUpsert] = useState(false);
    const [report, setReport] = useState<ImportReport | null>(null);

    const run = useMutation({
        mutationFn: () => {
            const fd = new FormData();
            fd.append('jenis_kod', jenis);
            fd.append('kemas_kini_sedia_ada', upsert ? '1' : '0');
            if (file) fd.append('fail', file);
            return api.postForm<{ message: string; data: ImportReport }>('/adm/kod-rujukan/import', fd);
        },
        onSuccess: (res) => {
            toast.success(res.message);
            setReport(res.data);
            queryClient.invalidateQueries({ queryKey: ['adm'] });
        },
        onError: (e) => toast.error(errorText(e)),
    });

    return (
        <Modal open onClose={onClose}>
            <div className="p-6 space-y-4">
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Import kod ({jenis})</h2>
                <p className="text-body-sm text-txt-black-500">
                    Fail CSV dengan pengepala <code>kod,keterangan[,kod_induk,susunan,tarikh_mula,tarikh_tamat]</code>. Setiap baris disahkan berasingan.
                </p>
                <input type="file" accept=".csv,text/csv" aria-label="Fail CSV" onChange={(e) => setFile(e.target.files?.[0] ?? null)} className="text-body-sm" />
                <label className="flex items-center gap-2 text-body-sm">
                    <input type="checkbox" checked={upsert} onChange={(e) => setUpsert(e.target.checked)} /> Kemas kini kod sedia ada
                </label>
                {report && (
                    <Alert type={report.ditolak ? 'warning' : 'success'}>
                        Diterima {report.diterima}, dikemas kini {report.dikemas_kini}, ditolak {report.ditolak}.
                        {report.ralat.length > 0 && (
                            <ul className="mt-2 list-disc ps-5">
                                {report.ralat.slice(0, 20).map((r) => (
                                    <li key={r.baris}>
                                        Baris {r.baris} ({r.kod ?? '—'}): {r.mesej.join(' ')}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Alert>
                )}
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        Tutup
                    </Button>
                    <Button onClick={() => run.mutate()} disabled={!file || run.isPending}>
                        Import
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
