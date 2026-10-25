'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Pagination } from '@/components/ui/Pagination';
import { Table } from '@/components/ui/Table';
import { ralat, tarikhMasa, tktApi } from '@/lib/tkt';
import { Card, Field, Kosong, Select } from '../_components/common';

const VARIANT: Record<string, 'success' | 'danger' | 'warning' | 'neutral'> = { berjaya: 'success', gagal: 'danger', menunggu: 'warning', diabaikan: 'neutral' };

/** SRS-TKT-14..16: two-way sync with the Sistem Aduan berpusat MAIS — reconciliation, retry, transaction log. */
export default function IntegrasiPage() {
    const qc = useQueryClient();
    const [mesej, setMesej] = useState<{ ok: boolean; teks: string } | null>(null);
    const [f, setF] = useState({ arah: '', status: '' });
    const [page, setPage] = useState(1);
    const rek = useQuery({ queryKey: ['tkt', 'rekonsiliasi'], queryFn: () => tktApi.rekonsiliasi().then((r) => r.data) });
    const log = useQuery({ queryKey: ['tkt', 'log', f, page], queryFn: () => tktApi.logIntegrasi({ ...f, page }) });
    const ok = (r: { message: string }) => {
        setMesej({ ok: true, teks: r.message });
        qc.invalidateQueries({ queryKey: ['tkt', 'rekonsiliasi'] });
        qc.invalidateQueries({ queryKey: ['tkt', 'log'] });
    };
    const ulang = useMutation({ mutationFn: tktApi.ulangCuba, onSuccess: ok, onError: (e) => setMesej({ ok: false, teks: ralat(e) }) });
    const selaras = useMutation({ mutationFn: tktApi.selaras, onSuccess: ok, onError: (e) => setMesej({ ok: false, teks: ralat(e) }) });

    return (
        <PageContainer>
            {mesej && <Alert type={mesej.ok ? 'success' : 'error'}>{mesej.teks}</Alert>}
            <Card
                title="Rekonsiliasi penyegerakan"
                actions={
                    <>
                        <Button size="sm" variant="secondary" onClick={() => ulang.mutate()} disabled={ulang.isPending}>Cuba semula yang gagal</Button>
                        <Button size="sm" onClick={() => selaras.mutate()} disabled={selaras.isPending}>Selaraskan semua</Button>
                    </>
                }
            >
                <p className="text-body-sm text-txt-black-500">Tiket daripada Sistem Aduan berpusat yang status terakhirnya belum disampaikan atau gagal disegerakkan. Adapter semasa ialah mock (akses MAIS belum diberi).</p>
                {rek.error && <Alert type="error">{ralat(rek.error)}</Alert>}
                {(rek.data ?? []).length === 0 ? (
                    <Kosong>Semua tiket berpusat telah disegerakkan.</Kosong>
                ) : (
                    <div className="overflow-x-auto">
                        <Table head={<><th>Tiket</th><th>No. aduan</th><th>Status semasa</th><th className="hidden sm:table-cell">Disegerak</th><th>Penyegerakan</th></>}>
                            {(rek.data ?? []).map((r) => (
                                <tr key={r.id_tiket}>
                                    <td><Link href={`/admin/tkt/tiket/${r.id_tiket}`} className="text-txt-primary hover:underline">{r.nombor_rujukan}</Link></td>
                                    <td className="break-all">{r.nombor_aduan}</td>
                                    <td>{r.status_semasa_nama}</td>
                                    <td className="hidden sm:table-cell">{r.status_disegerak_nama ?? '—'}</td>
                                    <td>
                                        <Badge variant={VARIANT[r.status_penyegerakan] ?? 'neutral'}>{r.status_penyegerakan}</Badge>
                                        {r.ralat && <p className="text-body-xs text-txt-danger break-words">{r.cubaan} cubaan · {r.ralat}</p>}
                                    </td>
                                </tr>
                            ))}
                        </Table>
                    </div>
                )}
            </Card>

            <Card title="Log transaksi integrasi">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <Field label="Arah" htmlFor="li-a"><Select id="li-a" value={f.arah} onChange={(v) => { setF((x) => ({ ...x, arah: v })); setPage(1); }} empty="Semua" options={[{ kod: 'masuk', nama: 'Masuk' }, { kod: 'keluar', nama: 'Keluar' }]} /></Field>
                    <Field label="Status" htmlFor="li-s"><Select id="li-s" value={f.status} onChange={(v) => { setF((x) => ({ ...x, status: v })); setPage(1); }} empty="Semua" options={['berjaya', 'gagal', 'menunggu', 'diabaikan'].map((s) => ({ kod: s, nama: s }))} /></Field>
                </div>
                <div className="overflow-x-auto">
                    <Table head={<><th>Masa</th><th>Arah</th><th>Ringkasan</th><th>Status</th><th className="hidden md:table-cell">Kunci idempotensi</th></>}>
                        {(log.data?.data ?? []).map((l) => (
                            <tr key={l.id}>
                                <td className="whitespace-nowrap">{tarikhMasa(l.cap_masa)}</td>
                                <td>{l.arah}</td>
                                <td className="break-words">{l.ringkasan ?? l.endpoint}{l.ralat && <p className="text-body-xs text-txt-danger">{l.ralat}</p>}</td>
                                <td><Badge variant={VARIANT[l.status] ?? 'neutral'}>{l.status}</Badge> <span className="text-body-xs text-txt-black-500">{l.cubaan}×</span></td>
                                <td className="hidden md:table-cell font-mono text-body-xs break-all">{l.kunci_idempotensi ?? '—'}</td>
                            </tr>
                        ))}
                    </Table>
                </div>
                {log.data && log.data.meta.last_page > 1 && <Pagination currentPage={log.data.meta.current_page} lastPage={log.data.meta.last_page} total={log.data.meta.total} onPageChange={setPage} />}
            </Card>
        </PageContainer>
    );
}
