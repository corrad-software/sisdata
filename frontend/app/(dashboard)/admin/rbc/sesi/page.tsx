'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Pagination } from '@/components/ui/Pagination';
import { SESSION_END_LABEL, formatDateTime, rbc } from '@/lib/rbc';
import type { Session } from '@/lib/rbc';
import { Card, EmptyRow, ScrollX, TextInput } from '../_components/ui';

/** SRS-RBC-19: active sessions with force-terminate, and termination history. */
export default function SessionsPage() {
    const queryClient = useQueryClient();
    const [history, setHistory] = useState(false);
    const [search, setSearch] = useState('');
    const [debounced, setDebounced] = useState('');
    const [page, setPage] = useState(1);
    const [target, setTarget] = useState<Session | null>(null);

    useEffect(() => {
        const t = setTimeout(() => {
            setDebounced(search);
            setPage(1);
        }, 400);
        return () => clearTimeout(t);
    }, [search]);

    const { data, isLoading } = useQuery({
        queryKey: ['rbc', 'sesi', history, debounced, page],
        queryFn: () => rbc.sessions({ sejarah: history, search: debounced, page }),
    });
    const end = useMutation({
        mutationFn: (s: Session) => rbc.endSession(s.id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['rbc', 'sesi'] });
            toast.success('Sesi ditamatkan.');
            setTarget(null);
        },
        onError: () => toast.error('Sesi tidak dapat ditamatkan.'),
    });
    const rows = data?.data ?? [];

    return (
        <PageContainer>
            <Card>
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <TextInput id="cari-sesi" label="Cari pengguna" value={search} onChange={setSearch} className="sm:w-72" />
                    <div className="flex gap-2" role="group" aria-label="Paparan">
                        <Button variant={history ? 'secondary' : 'primary'} size="sm" onClick={() => { setHistory(false); setPage(1); }}>
                            Sesi aktif
                        </Button>
                        <Button variant={history ? 'primary' : 'secondary'} size="sm" onClick={() => { setHistory(true); setPage(1); }}>
                            Sejarah penamatan
                        </Button>
                    </div>
                </div>
                <ScrollX>
                    <Table
                        head={
                            <>
                                <th>Pengguna</th>
                                <th>Peranti / IP</th>
                                <th>Mula</th>
                                <th>{history ? 'Tamat' : 'Aktiviti terakhir'}</th>
                                <th className="text-right">{history ? 'Sebab' : 'Tindakan'}</th>
                            </>
                        }
                    >
                        {isLoading ? (
                            <EmptyRow colSpan={5}>Memuatkan…</EmptyRow>
                        ) : rows.length === 0 ? (
                            <EmptyRow colSpan={5}>{history ? 'Tiada sejarah.' : 'Tiada sesi aktif.'}</EmptyRow>
                        ) : (
                            rows.map((s) => (
                                <tr key={s.id}>
                                    <td>
                                        {s.pengguna ? (
                                            <Link href={`/admin/users/${s.pengguna.id}`} className="font-medium text-primary-600 hover:text-primary-700">
                                                {s.pengguna.name}
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                        <div className="text-body-xs text-txt-black-500">{s.pengguna?.email}</div>
                                        {s.semasa && <Badge variant="info">Sesi anda</Badge>}
                                    </td>
                                    <td>
                                        <div>
                                            {s.pelayar} · {s.platform}
                                        </div>
                                        <div className="text-body-xs text-txt-black-500">
                                            {s.alamat_ip} · {s.kaedah_log_masuk === 'sso' ? 'SSO MAIS' : 'Kata laluan'}
                                        </div>
                                    </td>
                                    <td className="whitespace-nowrap">{formatDateTime(s.tarikh_mula)}</td>
                                    <td className="whitespace-nowrap">{formatDateTime(history ? s.tarikh_tamat : s.tarikh_aktiviti_akhir)}</td>
                                    <td className="text-right">
                                        {history ? (
                                            <span className="text-body-sm">{SESSION_END_LABEL[s.kaedah_tamat ?? ''] ?? s.kaedah_tamat}</span>
                                        ) : (
                                            <button type="button" onClick={() => setTarget(s)} className="text-danger-600 hover:text-danger-700 text-body-sm font-medium">
                                                Tamatkan
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

            <Modal open={target !== null} onClose={() => setTarget(null)}>
                <div className="p-6">
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Tamatkan sesi ini?</h2>
                    <p className="mt-1 text-body-sm text-txt-black-500">
                        {target?.pengguna?.name} akan dilog keluar serta-merta{target?.semasa ? ' — ini sesi anda sendiri' : ''}.
                    </p>
                    <div className="mt-6 flex justify-end gap-3">
                        <Button variant="secondary" onClick={() => setTarget(null)}>
                            Batal
                        </Button>
                        <Button variant="danger" disabled={end.isPending} onClick={() => target && end.mutate(target)}>
                            Tamatkan Sesi
                        </Button>
                    </div>
                </div>
            </Modal>
        </PageContainer>
    );
}
