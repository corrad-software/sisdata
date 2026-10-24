'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Pagination } from '@/components/ui/Pagination';
import { Table } from '@/components/ui/Table';
import { kesApi, muatTurunFail, ralat, tarikhMasa, useCan, useRujukan } from '@/lib/kes';
import { Card, Field, inputClass, RalatAkses, Select } from '../_components/common';

/** SRS-KES-13: access log of case records and documents (view, download, print, export, refusals) with IP and time. */
export default function LogCapaianPage() {
    const can = useCan();
    const { data: ref } = useRujukan();
    const [f, setF] = useState<Record<string, string>>({ dari: '', hingga: '', jenis_tindakan: '' });
    const [page, setPage] = useState(1);
    const set = (k: string) => (v: string) => {
        setF((x) => ({ ...x, [k]: v }));
        setPage(1);
    };
    const { data, error, isLoading } = useQuery({ queryKey: ['kes', 'log', f, page], queryFn: () => kesApi.logCapaian({ ...f, page }) });

    return (
        <PageContainer>
            <Card
                title="Log capaian rekod & dokumen kes"
                actions={
                    can('kes.laporan.eksport') && (
                        <Button size="sm" variant="secondary" onClick={() => muatTurunFail('/kes/log-capaian/eksport', { ...f, format: 'xlsx' }, 'log-capaian.xlsx').catch((e) => toast.error(ralat(e)))}>
                            Eksport Excel
                        </Button>
                    )
                }
            >
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <Field label="Dari" htmlFor="lg-d">
                        <input id="lg-d" type="date" className={inputClass} value={f.dari} onChange={(e) => set('dari')(e.target.value)} />
                    </Field>
                    <Field label="Hingga" htmlFor="lg-h">
                        <input id="lg-h" type="date" className={inputClass} value={f.hingga} onChange={(e) => set('hingga')(e.target.value)} />
                    </Field>
                    <Field label="Tindakan" htmlFor="lg-t">
                        <Select id="lg-t" value={f.jenis_tindakan} onChange={set('jenis_tindakan')} options={ref?.jenis_log ?? []} empty="Semua" />
                    </Field>
                </div>
                {error && <RalatAkses error={error} />}
                <div className="overflow-x-auto">
                    <Table head={<><th>Cap masa</th><th>Pengguna</th><th>Tindakan</th><th className="hidden sm:table-cell">Kes</th><th className="hidden md:table-cell">Alamat IP</th><th className="hidden lg:table-cell">Keterangan</th></>}>
                        {isLoading ? (
                            <tr>
                                <td colSpan={6} className="py-6 text-center text-txt-black-500">
                                    Memuatkan…
                                </td>
                            </tr>
                        ) : (
                            (data?.data ?? []).map((l) => (
                                <tr key={l.id}>
                                    <td className="whitespace-nowrap">{tarikhMasa(l.cap_masa)}</td>
                                    <td>{l.pengguna ?? '—'}</td>
                                    <td>
                                        <Badge variant={l.jenis_tindakan === 'ditolak' ? 'danger' : l.jenis_tindakan === 'lihat' || l.jenis_tindakan === 'lihat_senarai' ? 'neutral' : 'warning'}>{l.jenis_nama}</Badge>
                                    </td>
                                    <td className="hidden sm:table-cell">
                                        {l.kes_id ? (
                                            <Link href={`/admin/kes/kes/${l.kes_id}`} className="text-txt-primary hover:underline">
                                                {l.no_rujukan ?? l.kes_id}
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="hidden md:table-cell font-mono text-body-xs">{l.alamat_ip ?? '—'}</td>
                                    <td className="hidden lg:table-cell text-body-xs max-w-xs break-words">{l.keterangan ?? '—'}</td>
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
