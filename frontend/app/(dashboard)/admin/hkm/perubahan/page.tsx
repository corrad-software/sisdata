'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Pagination } from '@/components/ui/Pagination';
import { hkmApi, ralat, tarikhMasa, useRujukan } from '@/lib/hkm';
import { Card, Field, Select } from '../_components/common';

const statusVariant = (s: string) => (s === 'diluluskan' ? 'success' : s === 'menunggu_kelulusan' ? 'warning' : s === 'gagal' || s === 'ditolak' ? 'danger' : 'neutral');

/** SRS-HKM-19: change requests and their approval outcome. */
export default function PerubahanListPage() {
    const { data: ref } = useRujukan();
    const [status, setStatus] = useState('');
    const [saya, setSaya] = useState(false);
    const [page, setPage] = useState(1);
    const p = { status, saya: saya ? 1 : '', page };
    const { data, isLoading, error } = useQuery({ queryKey: ['hkm', 'perubahan', p], queryFn: () => hkmApi.perubahan(p) });

    return (
        <PageContainer>
            <Card title="Permohonan perubahan hak milik">
                <p className="text-body-sm text-txt-black-500">
                    Setiap perubahan diluluskan melalui dua peringkat dengan MFA dalam modul Workflow. Pelulus mengambil tindakan di{' '}
                    <Link href="/admin/wfe/tugasan" className="text-txt-primary hover:underline">
                        Tugasan Saya
                    </Link>
                    .
                </p>
                <div className="flex flex-wrap items-end gap-4">
                    <div className="w-full sm:w-64">
                        <Field label="Status" htmlFor="pr-s">
                            <Select
                                id="pr-s"
                                value={status}
                                onChange={(v) => {
                                    setStatus(v);
                                    setPage(1);
                                }}
                                options={ref?.status_perubahan ?? []}
                                empty="Semua"
                            />
                        </Field>
                    </div>
                    <label className="inline-flex items-center gap-2 text-body-sm pb-2">
                        <input type="checkbox" checked={saya} onChange={(e) => setSaya(e.target.checked)} /> Permohonan saya sahaja
                    </label>
                </div>
                <p className="text-body-sm text-txt-black-500" aria-live="polite">
                    {error ? <span className="text-txt-danger">{ralat(error)}</span> : data ? `${data.meta.total} permohonan` : 'Memuatkan…'}
                </p>
                <div className="overflow-x-auto">
                    <Table head={<><th>Perubahan</th><th className="hidden sm:table-cell">Hak milik</th><th>Status</th><th className="hidden md:table-cell">Dimohon</th></>}>
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
                            data?.data.map((c) => (
                                <tr key={c.id}>
                                    <td>
                                        <Link href={`/admin/hkm/perubahan/${c.id}`} className="font-medium text-txt-primary hover:underline">
                                            {c.jenis_perubahan_nama}
                                        </Link>
                                        <p className="text-body-xs text-txt-black-500 line-clamp-2 max-w-xs">{c.justifikasi}</p>
                                        <p className="sm:hidden text-body-xs">{c.no_hak_milik}</p>
                                    </td>
                                    <td className="hidden sm:table-cell">{c.no_hak_milik}</td>
                                    <td>
                                        <Badge variant={statusVariant(c.status)}>{c.status_nama}</Badge>
                                    </td>
                                    <td className="hidden md:table-cell">
                                        {tarikhMasa(c.dicipta)}
                                        <p className="text-body-xs text-txt-black-500">{c.pemohon}</p>
                                    </td>
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
