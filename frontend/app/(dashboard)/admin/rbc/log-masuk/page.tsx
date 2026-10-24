'use client';

import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { AnchorButton } from '@/components/ui/Button';
import { Pagination } from '@/components/ui/Pagination';
import { Tabs, TabPanel } from '@/components/ui/Tabs';
import { exportUrl, formatDateTime, rbc } from '@/lib/rbc';
import { Card, EmptyRow, ScrollX, Select, TextInput } from '../_components/ui';

function LoginLogTab() {
    const [search, setSearch] = useState('');
    const [debounced, setDebounced] = useState('');
    const [ip, setIp] = useState('');
    const [result, setResult] = useState('');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [page, setPage] = useState(1);

    useEffect(() => {
        const t = setTimeout(() => {
            setDebounced(search);
            setPage(1);
        }, 400);
        return () => clearTimeout(t);
    }, [search]);

    const filters = { search: debounced, alamat_ip: ip, berjaya: result, dari: from, hingga: to };
    const { data, isLoading } = useQuery({ queryKey: ['rbc', 'log-masuk', filters, page], queryFn: () => rbc.loginLog({ ...filters, page }) });
    const rows = data?.data ?? [];

    return (
        <Card
            actions={
                <>
                    <AnchorButton variant="secondary" href={exportUrl('log-masuk/eksport', { ...filters, format: 'xlsx' })}>
                        Eksport Excel
                    </AnchorButton>
                    <AnchorButton variant="secondary" href={exportUrl('log-masuk/eksport', { ...filters, format: 'pdf' })}>
                        Eksport PDF
                    </AnchorButton>
                </>
            }
        >
            <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <TextInput id="carian" label="Cari pengguna / e-mel" value={search} onChange={setSearch} />
                <TextInput id="ip" label="Alamat IP" value={ip} onChange={(v) => { setIp(v); setPage(1); }} />
                <Select id="keputusan" label="Keputusan" value={result} onChange={(e) => { setResult(e.target.value); setPage(1); }}>
                    <option value="">Semua</option>
                    <option value="1">Berjaya</option>
                    <option value="0">Gagal</option>
                </Select>
                <TextInput id="dari" type="date" label="Dari" value={from} onChange={(v) => { setFrom(v); setPage(1); }} />
                <TextInput id="hingga" type="date" label="Hingga" value={to} onChange={(v) => { setTo(v); setPage(1); }} />
            </div>
            <ScrollX>
                <Table
                    head={
                        <>
                            <th>Tarikh & masa</th>
                            <th>Pengguna</th>
                            <th>Keputusan</th>
                            <th>Alamat IP / lokasi</th>
                            <th>Peranti</th>
                        </>
                    }
                >
                    {isLoading ? (
                        <EmptyRow colSpan={5}>Memuatkan…</EmptyRow>
                    ) : rows.length === 0 ? (
                        <EmptyRow colSpan={5} />
                    ) : (
                        rows.map((log) => (
                            <tr key={log.id}>
                                <td className="whitespace-nowrap">{formatDateTime(log.created_at)}</td>
                                <td>
                                    <div className="font-medium">{log.pengguna?.name ?? '—'}</div>
                                    <div className="text-body-xs text-txt-black-500">{log.pengecam}</div>
                                </td>
                                <td>
                                    <div className="flex flex-wrap gap-1">
                                        {log.berjaya ? <Badge variant="success">Berjaya</Badge> : <Badge variant="danger">Gagal</Badge>}
                                        {log.mencurigakan && <Badge variant="warning">Mencurigakan</Badge>}
                                    </div>
                                    {log.sebab_label && <div className="text-body-xs text-txt-black-500 mt-1">{log.sebab_label}</div>}
                                    <div className="text-body-xs text-txt-black-500">{log.kaedah === 'sso' ? 'SSO MAIS' : 'Kata laluan'}</div>
                                </td>
                                <td>
                                    <div>{log.alamat_ip}</div>
                                    <div className="text-body-xs text-txt-black-500">{log.lokasi}</div>
                                </td>
                                <td className="text-body-xs">
                                    {log.pelayar} · {log.platform} · {log.peranti}
                                </td>
                            </tr>
                        ))
                    )}
                </Table>
            </ScrollX>
            {data && (
                <div className="mt-4">
                    <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />
                </div>
            )}
        </Card>
    );
}

function AlertsTab() {
    const queryClient = useQueryClient();
    const [pendingOnly, setPendingOnly] = useState(true);
    const [page, setPage] = useState(1);
    const { data, isLoading } = useQuery({ queryKey: ['rbc', 'amaran', pendingOnly, page], queryFn: () => rbc.alerts({ belum_disemak: pendingOnly, page }) });
    const ack = useMutation({
        mutationFn: rbc.acknowledgeAlert,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['rbc', 'amaran'] });
            toast.success('Amaran ditanda sebagai disemak.');
        },
        onError: () => toast.error('Tidak dibenarkan atau gagal.'),
    });
    const rows = data?.data ?? [];

    return (
        <Card>
            <div className="mb-4 flex items-center justify-between gap-3 text-body-sm">
                <span className="text-txt-black-500">Belum disemak: {data?.meta.belum_disemak ?? 0}</span>
                <label className="flex items-center gap-2">
                    <input type="checkbox" checked={pendingOnly} onChange={(e) => { setPendingOnly(e.target.checked); setPage(1); }} className="rounded border-otl-gray-300 text-primary-600" />
                    Belum disemak sahaja
                </label>
            </div>
            <ScrollX>
                <Table
                    head={
                        <>
                            <th>Tarikh</th>
                            <th>Jenis</th>
                            <th>Pengguna / IP</th>
                            <th>Butiran</th>
                            <th className="text-right">Tindakan</th>
                        </>
                    }
                >
                    {isLoading ? (
                        <EmptyRow colSpan={5}>Memuatkan…</EmptyRow>
                    ) : rows.length === 0 ? (
                        <EmptyRow colSpan={5}>Tiada amaran.</EmptyRow>
                    ) : (
                        rows.map((a) => (
                            <tr key={a.id}>
                                <td className="whitespace-nowrap">{formatDateTime(a.created_at)}</td>
                                <td>
                                    <Badge variant="warning">{a.jenis_label}</Badge>
                                </td>
                                <td>
                                    <div>{a.pengguna?.name ?? '—'}</div>
                                    <div className="text-body-xs text-txt-black-500">{a.alamat_ip}</div>
                                </td>
                                <td className="text-body-xs text-txt-black-700 max-w-xs">
                                    {Object.entries(a.butiran)
                                        .filter(([, v]) => v !== null && typeof v !== 'object')
                                        .map(([k, v]) => `${k.replaceAll('_', ' ')}: ${String(v)}`)
                                        .join(' · ')}
                                </td>
                                <td className="text-right whitespace-nowrap">
                                    {a.disemak_pada ? (
                                        <span className="text-body-xs text-txt-black-500">Disemak {formatDateTime(a.disemak_pada)}</span>
                                    ) : (
                                        <button type="button" onClick={() => ack.mutate(a.id)} className="text-primary-600 hover:text-primary-700 text-body-sm font-medium">
                                            Tanda disemak
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))
                    )}
                </Table>
            </ScrollX>
            {data && (
                <div className="mt-4">
                    <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />
                </div>
            )}
        </Card>
    );
}

/** SRS-RBC-21/22: login attempts and suspicious-activity alerts. */
export default function LoginLogPage() {
    return (
        <PageContainer>
            <Tabs tabs={{ log: 'Log log masuk', amaran: 'Amaran keselamatan' }}>
                <TabPanel value="log">
                    <LoginLogTab />
                </TabPanel>
                <TabPanel value="amaran">
                    <AlertsTab />
                </TabPanel>
            </Tabs>
        </PageContainer>
    );
}
