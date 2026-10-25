'use client';

import { Suspense, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Callout } from '@/components/ui/Callout';
import { Pagination } from '@/components/ui/Pagination';
import { lapApi, muatTurunLog, ralat, tarikhMasa, useCan } from '@/lib/lap';
import { Card, Field, inputClass, Kosong } from '../_components/common';

const STATUS_VARIANT: Record<string, 'success' | 'warning' | 'danger' | 'info' | 'neutral'> = { berjaya: 'success', beratur: 'info', dalam_proses: 'info', gagal: 'danger', ditolak: 'warning' };

/** SRS-LAP-07/10 — generation & export log; background / scheduled files download here (owner only). */
function Log() {
    const can = useCan();
    const params = useSearchParams();
    const sorot = params.get('id');
    const [semua, setSemua] = useState(false);
    const [format, setFormat] = useState('');
    const [halaman, setHalaman] = useState(1);
    const q = useQuery({
        queryKey: ['lap', 'log', semua, format, halaman],
        queryFn: () => lapApi.log({ semua: semua ? 1 : undefined, format, halaman }),
        refetchInterval: (query) => (query.state.data?.data.some((l) => ['beratur', 'dalam_proses'].includes(l.status)) ? 3000 : false),
    });

    return (
        <PageContainer>
            <Card title="Log penjanaan dan eksport">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <Field label="Format" htmlFor="lformat">
                        <select id="lformat" className={inputClass} value={format} onChange={(e) => { setFormat(e.target.value); setHalaman(1); }}>
                            <option value="">Semua</option>
                            {['paparan', 'pdf', 'xlsx', 'csv', 'png'].map((f) => (
                                <option key={f} value={f}>
                                    {f === 'paparan' ? 'Paparan skrin' : f.toUpperCase()}
                                </option>
                            ))}
                        </select>
                    </Field>
                    {can('lap.log.lihat') && (
                        <label className="flex items-center gap-2 text-body-sm sm:mt-7">
                            <input type="checkbox" checked={semua} onChange={(e) => { setSemua(e.target.checked); setHalaman(1); }} /> Semua pengguna (Audit)
                        </label>
                    )}
                </div>
                {q.isError && <Callout type="danger">{ralat(q.error)}</Callout>}
                {q.data && q.data.data.length === 0 && <Kosong>Tiada rekod.</Kosong>}
                {q.data && q.data.data.length > 0 && (
                    <>
                        <Table head={<><th>Masa</th><th>Laporan</th>{semua && <th>Pengguna</th>}<th>Format</th><th>Mod</th><th>Rekod</th><th>Status</th><th>Fail</th></>}>
                            {q.data.data.map((l) => (
                                <tr key={l.id} className={l.id === sorot ? 'bg-bg-primary-50' : ''}>
                                    <td className="whitespace-nowrap">{tarikhMasa(l.cap_masa)}</td>
                                    <td>
                                        {l.tajuk}
                                        {(l.klasifikasi === 'sulit' || l.klasifikasi === 'rahsia') && (
                                            <span className="ml-2">
                                                <Badge variant="danger">{l.klasifikasi.toUpperCase()}</Badge>
                                            </span>
                                        )}
                                    </td>
                                    {semua && <td>{l.pengguna ?? '—'}</td>}
                                    <td>{l.format === 'paparan' ? 'Skrin' : l.format.toUpperCase()}</td>
                                    <td>{l.mod_nama}</td>
                                    <td className="text-right tabular-nums">{l.bilangan_rekod.toLocaleString('ms-MY')}</td>
                                    <td>
                                        <Badge variant={STATUS_VARIANT[l.status] ?? 'neutral'}>{l.status_nama}</Badge>
                                    </td>
                                    <td>
                                        {l.boleh_muat_turun ? (
                                            <Button size="sm" variant="outline" onClick={() => muatTurunLog(l.id).catch((e) => toast.error(ralat(e)))}>
                                                Muat turun
                                            </Button>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </Table>
                        {q.data.meta.halaman_akhir > 1 && <Pagination currentPage={q.data.meta.halaman} lastPage={q.data.meta.halaman_akhir} onPageChange={setHalaman} total={q.data.meta.jumlah} />}
                    </>
                )}
            </Card>
        </PageContainer>
    );
}

export default function LogPage() {
    return (
        <Suspense>
            <Log />
        </Suspense>
    );
}
