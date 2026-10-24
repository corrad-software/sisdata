'use client';

import { useState } from 'react';
import type { FormEvent } from 'react';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { InputLabel } from '@/components/ui/InputLabel';
import { Alert } from '@/components/ui/Alert';
import { ApiError } from '@/lib/api';
import { ACTION_LABELS, fetchReport, label, REPORT_TYPES, type AuditRecord, type BulkActivityRow, type ReportType } from '@/lib/aud';
import { ClassificationBadge, StatusBadge } from '../_components/AuditBits';

const inputClass =
    'block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm';

function isoDate(d: Date): string {
    return d.toLocaleDateString('en-CA', { timeZone: 'Asia/Kuala_Lumpur' });
}

export default function AuditReportsPage() {
    const today = new Date();
    const monthAgo = new Date(today.getTime() - 30 * 86400000);
    const [draft, setDraft] = useState<{ jenis: ReportType; dari: string; hingga: string }>({ jenis: 'luar_waktu', dari: isoDate(monthAgo), hingga: isoDate(today) });
    const [params, setParams] = useState(draft);

    const { data, isLoading, error } = useQuery({
        queryKey: ['aud', 'laporan', params],
        queryFn: () => fetchReport(params.jenis, params.dari, params.hingga),
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        setParams(draft);
    }

    const report = data?.data;
    const bulk = report?.jenis === 'kelompok';

    return (
        <PageContainer>
            <form onSubmit={submit} className="bg-bg-white shadow-card rounded-lg border border-otl-gray-200 p-4 sm:p-6 grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                <div className="sm:col-span-2">
                    <InputLabel htmlFor="l-jenis" className="mb-1.5">
                        Jenis laporan
                    </InputLabel>
                    <select id="l-jenis" value={draft.jenis} onChange={(e) => setDraft((d) => ({ ...d, jenis: e.target.value as ReportType }))} className={inputClass}>
                        {Object.entries(REPORT_TYPES).map(([k, v]) => (
                            <option key={k} value={k}>
                                {v}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <InputLabel htmlFor="l-dari" className="mb-1.5">
                        Dari
                    </InputLabel>
                    <input id="l-dari" type="date" value={draft.dari} onChange={(e) => setDraft((d) => ({ ...d, dari: e.target.value }))} className={inputClass} />
                </div>
                <div>
                    <InputLabel htmlFor="l-hingga" className="mb-1.5">
                        Hingga
                    </InputLabel>
                    <input id="l-hingga" type="date" value={draft.hingga} onChange={(e) => setDraft((d) => ({ ...d, hingga: e.target.value }))} className={inputClass} />
                </div>
                <div className="sm:col-span-4">
                    <Button type="submit">Jana laporan</Button>
                </div>
            </form>

            {error instanceof ApiError && <Alert type="error">{error.status === 403 ? 'Anda tiada kebenaran untuk melihat laporan audit.' : error.message}</Alert>}

            <div className="bg-bg-white shadow-card rounded-lg border border-otl-gray-200 p-4 sm:p-6 space-y-4">
                <div>
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">{REPORT_TYPES[params.jenis]}</h2>
                    <p className="text-body-sm text-txt-black-500">
                        {isLoading ? 'Memuatkan…' : `${report?.jumlah ?? 0} ${bulk ? 'kumpulan aktiviti' : 'rekod'} · ${params.dari} hingga ${params.hingga}`}
                        {report && Object.keys(report.parameter).length > 0 && (
                            <span className="block text-body-xs">Parameter: {JSON.stringify(report.parameter)}</span>
                        )}
                    </p>
                </div>

                {bulk ? (
                    <Table
                        head={
                            <>
                                <th>Jam</th>
                                <th>Pengguna</th>
                                <th>Bilangan</th>
                                <th>Tindakan</th>
                            </>
                        }
                    >
                        {(report?.rekod as BulkActivityRow[]).length === 0 ? (
                            <tr>
                                <td colSpan={4} className="text-center text-txt-black-500 py-6">
                                    Tiada aktiviti luar biasa.
                                </td>
                            </tr>
                        ) : (
                            (report?.rekod as BulkActivityRow[]).map((g) => (
                                <tr key={`${g.id_pengguna}-${g.jam}`}>
                                    <td className="whitespace-nowrap">{g.jam}</td>
                                    <td>{g.pengecam_pengguna ?? g.id_pengguna}</td>
                                    <td>{g.bilangan}</td>
                                    <td>{g.tindakan}</td>
                                </tr>
                            ))
                        )}
                    </Table>
                ) : (
                    <Table
                        head={
                            <>
                                <th>Tarikh & masa</th>
                                <th>Pengguna</th>
                                <th>Modul</th>
                                <th>Tindakan</th>
                                <th>Entiti</th>
                                <th>Status</th>
                                <th>IP</th>
                            </>
                        }
                    >
                        {((report?.rekod ?? []) as AuditRecord[]).length === 0 ? (
                            <tr>
                                <td colSpan={7} className="text-center text-txt-black-500 py-6">
                                    {isLoading ? 'Memuatkan…' : 'Tiada rekod.'}
                                </td>
                            </tr>
                        ) : (
                            ((report?.rekod ?? []) as AuditRecord[]).map((r) => (
                                <tr key={`${r.sumber}-${r.id}`}>
                                    <td className="whitespace-nowrap">{r.tarikh_masa_tempatan}</td>
                                    <td>{r.pengecam_pengguna ?? 'Sistem'}</td>
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
                                    <td>{r.alamat_ip ?? '—'}</td>
                                </tr>
                            ))
                        )}
                    </Table>
                )}
            </div>
        </PageContainer>
    );
}
