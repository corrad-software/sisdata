'use client';

import { useState } from 'react';
import type { FormEvent } from 'react';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { InputLabel } from '@/components/ui/InputLabel';
import { Alert } from '@/components/ui/Alert';
import { ApiError } from '@/lib/api';
import {
    ACTION_LABELS,
    CHANNEL_LABELS,
    downloadExport,
    fetchAuditOptions,
    label,
    searchAudit,
    STATUS_LABELS,
    type AuditFilters,
    type AuditRecord,
    type ExportFormat,
} from '@/lib/aud';
import { ClassificationBadge, RecordDetail, StatusBadge } from './_components/AuditBits';

const inputClass =
    'block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm';

const EMPTY: AuditFilters = { sumber: 'dalam_talian' };

const EXPORTS: Array<{ format: ExportFormat; label: string }> = [
    { format: 'csv', label: 'CSV' },
    { format: 'xlsx', label: 'Excel' },
    { format: 'json', label: 'JSON' },
    { format: 'pdf', label: 'PDF' },
];

export default function AuditSearchPage() {
    const [draft, setDraft] = useState<AuditFilters>(EMPTY);
    const [filters, setFilters] = useState<AuditFilters>(EMPTY);
    const [page, setPage] = useState(1);
    const [selected, setSelected] = useState<AuditRecord | null>(null);
    const [exporting, setExporting] = useState<ExportFormat | null>(null);
    const [lastChecksum, setLastChecksum] = useState<{ format: string; checksum: string; count: number } | null>(null);

    const { data, isLoading, error } = useQuery({
        queryKey: ['aud', 'rekod', filters, page],
        queryFn: () => searchAudit(filters, page),
    });
    const { data: options } = useQuery({ queryKey: ['aud', 'pilihan'], queryFn: fetchAuditOptions });

    function set(key: keyof AuditFilters, value: string) {
        setDraft((d) => ({ ...d, [key]: value }));
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        setFilters(draft);
        setPage(1);
    }

    function reset() {
        setDraft(EMPTY);
        setFilters(EMPTY);
        setPage(1);
    }

    async function exportAs(format: ExportFormat) {
        setExporting(format);
        try {
            const result = await downloadExport(filters, format);
            setLastChecksum({ format, ...result });
            toast.success(`Pakej eksport ${format.toUpperCase()} dimuat turun (${result.count} rekod).`);
        } catch (err) {
            toast.error(err instanceof ApiError && err.status === 403 ? 'Anda tiada kebenaran untuk mengeksport.' : 'Eksport gagal.');
        } finally {
            setExporting(null);
        }
    }

    const records = data?.data ?? [];
    const meta = data?.meta;
    const opts = options?.data;

    const select = (key: keyof AuditFilters, text: string, values: string[] | undefined, labels?: Record<string, string>) => (
        <div>
            <InputLabel htmlFor={`f-${key}`} className="mb-1.5">
                {text}
            </InputLabel>
            <select id={`f-${key}`} value={draft[key] ?? ''} onChange={(e) => set(key, e.target.value)} className={inputClass}>
                <option value="">Semua</option>
                {(values ?? []).map((v) => (
                    <option key={v} value={v}>
                        {labels ? label(labels, v) : v}
                    </option>
                ))}
            </select>
        </div>
    );

    const text = (key: keyof AuditFilters, labelText: string, type = 'text', placeholder?: string) => (
        <div>
            <InputLabel htmlFor={`f-${key}`} className="mb-1.5">
                {labelText}
            </InputLabel>
            <input
                id={`f-${key}`}
                type={type}
                value={draft[key] ?? ''}
                placeholder={placeholder}
                onChange={(e) => set(key, e.target.value)}
                className={inputClass}
            />
        </div>
    );

    return (
        <>
            <PageContainer>
                <form onSubmit={submit} data-panduan="aud-penapis" className="bg-bg-white shadow-card rounded-lg border border-otl-gray-200 p-4 sm:p-6 space-y-4" aria-label="Penapis carian audit">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        {text('pengguna', 'Pengguna (emel)', 'text', 'cth. pegawai@mais.gov.my')}
                        {text('id_pengguna', 'ID pengguna', 'number')}
                        {text('peranan', 'Peranan', 'text', 'cth. juruaudit')}
                        {select('modul', 'Modul', opts?.modul)}
                        {select('nama_entiti', 'Entiti', opts?.nama_entiti)}
                        {text('id_rekod', 'ID rekod')}
                        {select('jenis_tindakan', 'Jenis tindakan', opts?.jenis_tindakan, ACTION_LABELS)}
                        {select('status_transaksi', 'Status', opts?.status_transaksi, STATUS_LABELS)}
                        {text('alamat_ip', 'Alamat IP', 'text', 'cth. 10.1.2.3')}
                        {select('saluran_capaian', 'Saluran', opts?.saluran_capaian, CHANNEL_LABELS)}
                        {text('dari', 'Dari tarikh', 'date')}
                        {text('hingga', 'Hingga tarikh', 'date')}
                        <div>
                            <InputLabel htmlFor="f-sumber" className="mb-1.5">
                                Sumber
                            </InputLabel>
                            <select id="f-sumber" value={draft.sumber ?? 'dalam_talian'} onChange={(e) => set('sumber', e.target.value)} className={inputClass}>
                                <option value="dalam_talian">Dalam talian</option>
                                <option value="arkib">Arkib</option>
                                <option value="semua">Dalam talian + arkib</option>
                            </select>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <Button type="submit">Cari</Button>
                        <Button variant="secondary" onClick={reset}>
                            Set semula
                        </Button>
                    </div>
                </form>

                <div className="bg-bg-white shadow-card rounded-lg border border-otl-gray-200 p-4 sm:p-6 space-y-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-body-sm text-txt-black-500" aria-live="polite">
                            {meta ? `${meta.total} rekod dijumpai` : 'Memuatkan…'}
                        </p>
                        <div className="flex flex-wrap gap-2" role="group" aria-label="Eksport hasil carian" data-panduan="aud-eksport">
                            {EXPORTS.map((x) => (
                                <Button key={x.format} variant="outline" size="sm" disabled={exporting !== null} onClick={() => exportAs(x.format)}>
                                    {exporting === x.format ? 'Menjana…' : `Eksport ${x.label}`}
                                </Button>
                            ))}
                        </div>
                    </div>

                    {lastChecksum && (
                        <Alert type="info">
                            Pakej {lastChecksum.format.toUpperCase()} ({lastChecksum.count} rekod) — SHA-256:{' '}
                            <span className="font-mono break-all">{lastChecksum.checksum}</span>
                        </Alert>
                    )}

                    {error instanceof ApiError && (
                        <Alert type="error">{error.status === 403 ? 'Anda tiada kebenaran untuk mencapai audit trail ini.' : error.message}</Alert>
                    )}

                    <Table
                        panduan="aud-senarai"
                        head={
                            <>
                                <th>Tarikh & masa</th>
                                <th>Pengguna</th>
                                <th>Modul</th>
                                <th>Tindakan</th>
                                <th>Entiti</th>
                                <th>Status</th>
                                <th>IP / Saluran</th>
                                <th className="text-right">Butiran</th>
                            </>
                        }
                    >
                        {isLoading ? (
                            <tr>
                                <td colSpan={8} className="text-center text-txt-black-500 py-6">
                                    Memuatkan…
                                </td>
                            </tr>
                        ) : records.length === 0 ? (
                            <tr>
                                <td colSpan={8} className="text-center text-txt-black-500 py-6">
                                    Tiada rekod audit sepadan dengan kriteria.
                                </td>
                            </tr>
                        ) : (
                            records.map((r) => (
                                <tr key={`${r.sumber}-${r.id}`}>
                                    <td className="whitespace-nowrap">{r.tarikh_masa_tempatan}</td>
                                    <td>
                                        <div>{r.pengecam_pengguna ?? 'Sistem'}</div>
                                        <div className="text-body-xs text-txt-black-500">{r.peranan_semasa.join(', ')}</div>
                                    </td>
                                    <td className="uppercase">{r.modul}</td>
                                    <td className="whitespace-nowrap">
                                        {label(ACTION_LABELS, r.jenis_tindakan)} <ClassificationBadge value={r.klasifikasi} />
                                    </td>
                                    <td>
                                        {r.nama_entiti ?? '—'}
                                        {r.id_rekod && <span className="text-txt-black-500"> #{r.id_rekod}</span>}
                                    </td>
                                    <td>
                                        <StatusBadge status={r.status_transaksi} />
                                    </td>
                                    <td className="whitespace-nowrap">
                                        <div>{r.alamat_ip ?? '—'}</div>
                                        <div className="text-body-xs text-txt-black-500">{label(CHANNEL_LABELS, r.saluran_capaian)}</div>
                                    </td>
                                    <td className="text-right">
                                        <button
                                            type="button"
                                            onClick={() => setSelected(r)}
                                            className="text-primary-600 hover:text-primary-700 text-body-sm font-medium"
                                        >
                                            Lihat
                                        </button>
                                    </td>
                                </tr>
                            ))
                        )}
                    </Table>

                    {meta && meta.last_page > 1 && (
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between text-body-sm text-txt-black-500">
                            <span>
                                Memaparkan {meta.from} hingga {meta.to} daripada {meta.total}
                            </span>
                            <div className="flex gap-2">
                                <Button variant="secondary" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                                    Sebelumnya
                                </Button>
                                <Button variant="secondary" size="sm" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>
                                    Seterusnya
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </PageContainer>

            <Modal open={selected !== null} onClose={() => setSelected(null)}>
                {selected && (
                    <div className="p-4 sm:p-6 space-y-4 max-h-[85vh] overflow-y-auto">
                        <div className="flex items-start justify-between gap-4">
                            <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Rekod audit #{selected.no_urutan}</h2>
                            <Button variant="secondary" size="sm" onClick={() => setSelected(null)}>
                                Tutup
                            </Button>
                        </div>
                        <RecordDetail record={selected} />
                    </div>
                )}
            </Modal>
        </>
    );
}
