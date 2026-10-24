'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Pagination } from '@/components/ui/Pagination';
import { hkmApi, ralat, tarikh, useRujukan } from '@/lib/hkm';
import { Card, Field, inputClass, Select } from '../_components/common';

/** SRS-HKM-16: permanent-title applications by stage, land office and processing time. */
export default function PermohonanListPage() {
    const { data: ref } = useRujukan();
    const [f, setF] = useState<Record<string, string>>({});
    const [page, setPage] = useState(1);
    const p = { ...f, page };
    const { data, isLoading, error } = useQuery({ queryKey: ['hkm', 'permohonan', p], queryFn: () => hkmApi.permohonan(p) });
    const set = (k: string) => (v: string) => {
        setF((x) => ({ ...x, [k]: v }));
        setPage(1);
    };

    return (
        <PageContainer>
            <Card title="Permohonan hak milik kekal">
                <p className="text-body-sm text-txt-black-500">Daftar permohonan baharu dari halaman hak milik sementara (HS(D)/HS(M)).</p>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <Field label="Cari no. permohonan / hak milik" htmlFor="pm-q">
                        <input id="pm-q" type="search" className={inputClass} value={f.q ?? ''} onChange={(e) => set('q')(e.target.value)} />
                    </Field>
                    <Field label="Peringkat" htmlFor="pm-p">
                        <Select id="pm-p" value={f.peringkat ?? ''} onChange={set('peringkat')} options={ref?.peringkat_hmk ?? []} empty="Semua" />
                    </Field>
                    <Field label="Status" htmlFor="pm-s">
                        <Select id="pm-s" value={f.status ?? ''} onChange={set('status')} options={ref?.status_hmk ?? []} empty="Semua" />
                    </Field>
                </div>
                <p className="text-body-sm text-txt-black-500" aria-live="polite">
                    {error ? <span className="text-txt-danger">{ralat(error)}</span> : data ? `${data.meta.total} permohonan` : 'Memuatkan…'}
                </p>
                <div className="overflow-x-auto">
                    <Table
                        head={
                            <>
                                <th>Permohonan</th>
                                <th className="hidden sm:table-cell">Hak milik sementara</th>
                                <th>Peringkat</th>
                                <th className="hidden md:table-cell">Tempoh</th>
                            </>
                        }
                    >
                        {isLoading ? (
                            <tr>
                                <td colSpan={4} className="py-6 text-center text-txt-black-500">
                                    Memuatkan…
                                </td>
                            </tr>
                        ) : (data?.data ?? []).length === 0 ? (
                            <tr>
                                <td colSpan={4} className="py-6 text-center text-txt-black-500">
                                    Tiada permohonan.
                                </td>
                            </tr>
                        ) : (
                            data?.data.map((a) => (
                                <tr key={a.id}>
                                    <td>
                                        <Link href={`/admin/hkm/permohonan/${a.id}`} className="font-medium text-txt-primary hover:underline">
                                            {a.no_permohonan}
                                        </Link>
                                        <p className="text-body-xs text-txt-black-500">
                                            {tarikh(a.tarikh_permohonan)} · {a.pejabat_tanah}
                                        </p>
                                    </td>
                                    <td className="hidden sm:table-cell">
                                        {a.no_hak_milik_sementara}
                                        {a.no_hak_milik_baharu && <p className="text-body-xs text-txt-black-500">→ {a.no_hak_milik_baharu}</p>}
                                    </td>
                                    <td>
                                        {a.peringkat_semasa_nama}
                                        <div>
                                            <Badge variant={a.status_kelulusan === 'selesai' || a.status_kelulusan === 'lulus' ? 'success' : a.status_kelulusan === 'dalam_proses' ? 'info' : 'danger'}>{a.status_kelulusan_nama}</Badge>
                                        </div>
                                    </td>
                                    <td className="hidden md:table-cell">{a.tempoh_proses_hari} hari</td>
                                </tr>
                            ))
                        )}
                    </Table>
                </div>
                {data && data.meta.last_page > 1 && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />}
            </Card>
        </PageContainer>
    );
}
