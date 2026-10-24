'use client';

import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { Pagination } from '@/components/ui/Pagination';
import { ckiApi, masa, ralat, useCan, type Liputan } from '@/lib/cki';
import { Card, Kosong, Status } from '../_components/common';

/** SRS-CKI-04/06/08/10: coverage register per PTD / PBT, operating mode, on-demand check, integration log. */
export default function IntegrasiPage() {
    const can = useCan();
    const qc = useQueryClient();
    const [page, setPage] = useState(1);
    const [gagal, setGagal] = useState(false);
    const [busy, setBusy] = useState(false);
    const { data, error } = useQuery({ queryKey: ['cki', 'integrasi'], queryFn: ckiApi.integrasi });
    const { data: log } = useQuery({ queryKey: ['cki', 'integrasi', 'log', page, gagal], queryFn: () => ckiApi.log({ page, berjaya: gagal ? '0' : '' }) });
    const boleh = can('cki.integrasi.kemas_kini');
    const refresh = () => qc.invalidateQueries({ queryKey: ['cki', 'integrasi'] });

    async function toggle(a: Liputan, field: 'perkhidmatan_semakan' | 'perkhidmatan_bayaran') {
        try {
            await ckiApi.liputanKemasKini(a.id, { [field]: !a[field] });
            refresh();
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    async function semak(kod?: string) {
        setBusy(true);
        try {
            const r = await ckiApi.semak(kod ? { kod_agensi: kod } : {});
            toast.success(`${r.obligasi} obligasi disemak: ${r.baharu} baharu, ${r.dikemas_kini} dikemas kini, ${r.konflik} konflik${r.gagal.length ? `; gagal: ${r.gagal.join(', ')}` : ''}`);
            refresh();
        } catch (e) {
            toast.error(ralat(e));
        } finally {
            setBusy(false);
        }
    }

    async function simulasi(turun: boolean) {
        try {
            await ckiApi.simulasi({ sasaran: 'mbi', turun });
            refresh();
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    return (
        <PageContainer>
            {error && <p className="text-txt-danger">{ralat(error)}</p>}
            {data?.mock && (
                <Alert type="info">
                    Penyedia integrasi berpusat dalam mod simulasi ({data.penyedia}). {data.mock_turun ? 'Gangguan sedang disimulasikan.' : ''}
                </Alert>
            )}
            {(data?.mod_aktif.length ?? 0) > 0 && (
                <Alert type="warning">
                    {data?.mod_aktif.length} agensi dalam mod sandaran — bil dan resit direkod secara manual sehingga API pulih.
                </Alert>
            )}
            <Card
                title="Daftar liputan integrasi"
                actions={
                    boleh ? (
                        <>
                            <Button size="sm" onClick={() => semak()} disabled={busy}>
                                {busy ? 'Menyemak…' : 'Semak semua sekarang'}
                            </Button>
                            {data?.mock && (
                                <Button size="sm" variant="outline" onClick={() => simulasi(!data.mock_turun)}>
                                    {data.mock_turun ? 'Pulihkan API (simulasi)' : 'Simulasi gangguan API'}
                                </Button>
                            )}
                        </>
                    ) : undefined
                }
            >
                <Table head={<><th>Agensi</th><th>Semakan</th><th>Bayaran</th><th>Mod</th><th className="hidden md:table-cell">Semakan terakhir</th><th className="hidden sm:table-cell">Obligasi</th></>}>
                    {!data ? (
                        <Kosong colSpan={6}>Memuatkan…</Kosong>
                    ) : (
                        data.agensi.map((a) => (
                            <tr key={a.id}>
                                <td className="max-w-[10rem] sm:max-w-xs break-words">
                                    {a.nama_agensi}
                                    <p className="text-body-xs text-txt-black-500">{a.kod_agensi}</p>
                                </td>
                                <td>
                                    <label className="inline-flex items-center gap-2">
                                        <input type="checkbox" className="rounded border-otl-gray-300" checked={a.perkhidmatan_semakan} disabled={!boleh} onChange={() => toggle(a, 'perkhidmatan_semakan')} />
                                        <span className="sr-only">Perkhidmatan semakan {a.nama_agensi}</span>
                                    </label>
                                </td>
                                <td>
                                    <label className="inline-flex items-center gap-2">
                                        <input type="checkbox" className="rounded border-otl-gray-300" checked={a.perkhidmatan_bayaran} disabled={!boleh} onChange={() => toggle(a, 'perkhidmatan_bayaran')} />
                                        <span className="sr-only">Perkhidmatan bayaran {a.nama_agensi}</span>
                                    </label>
                                </td>
                                <td>
                                    <Status kod={a.mod_semasa} nama={a.mod_semasa === 'api' ? 'API' : `Sandaran (${a.kegagalan_berturut} gagal)`} />
                                </td>
                                <td className="hidden md:table-cell whitespace-nowrap">{masa(a.semakan_terakhir)}</td>
                                <td className="hidden sm:table-cell">{a.bil_obligasi}</td>
                            </tr>
                        ))
                    )}
                </Table>
            </Card>
            <Card
                title="Log integrasi"
                actions={
                    <label className="inline-flex items-center gap-2 text-body-sm">
                        <input type="checkbox" className="rounded border-otl-gray-300" checked={gagal} onChange={(e) => { setGagal(e.target.checked); setPage(1); }} />
                        Gagal sahaja
                    </label>
                }
            >
                <Table head={<><th>Masa</th><th>Panggilan</th><th className="hidden sm:table-cell">Agensi</th><th>Status</th><th className="hidden md:table-cell">Tindak balas</th><th className="hidden lg:table-cell">Cuba semula</th></>}>
                    {(log?.data ?? []).length === 0 ? (
                        <Kosong colSpan={6}>Tiada rekod.</Kosong>
                    ) : (
                        log?.data.map((l) => (
                            <tr key={l.id}>
                                <td className="whitespace-nowrap">{masa(l.masa_panggilan)}</td>
                                <td>{l.jenis_panggilan}</td>
                                <td className="hidden sm:table-cell">{l.kod_agensi ?? '—'}</td>
                                <td>
                                    <Status kod={l.berjaya ? 'selesai' : 'tertunggak'} nama={l.berjaya ? `OK ${l.status_http ?? ''}` : `${l.kod_ralat ?? 'Gagal'} ${l.status_http ?? ''}`} />
                                </td>
                                <td className="hidden md:table-cell">{l.masa_tindak_balas ?? '—'} ms</td>
                                <td className="hidden lg:table-cell">{l.bilangan_cuba_semula}</td>
                            </tr>
                        ))
                    )}
                </Table>
                {log && log.meta.last_page > 1 && <Pagination currentPage={log.meta.current_page} lastPage={log.meta.last_page} total={log.meta.total} onPageChange={setPage} />}
            </Card>
        </PageContainer>
    );
}
