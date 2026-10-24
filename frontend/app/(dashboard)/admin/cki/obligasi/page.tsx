'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { Pagination } from '@/components/ui/Pagination';
import { ckiApi, ralat, useCan, useRujukan, type LotRingkas } from '@/lib/cki';
import { Card, Field, inputClass, Kosong, Select, Sintetik, Status } from '../_components/common';
import { Penapis } from '../_components/Penapis';

function DaftarObligasi({ onDone }: { onDone: () => void }) {
    const { data: ref } = useRujukan();
    const [q, setQ] = useState('');
    const [lot, setLot] = useState<LotRingkas | null>(null);
    const [f, setF] = useState<Record<string, string>>({ jenis_cukai: 'tanah' });
    const { data: lots } = useQuery({ queryKey: ['cki', 'lot', q], queryFn: () => ckiApi.lot(q), enabled: q.length >= 2 && !lot });

    async function simpan(e: React.FormEvent) {
        e.preventDefault();
        if (!lot) return;
        try {
            const r = await ckiApi.obligasiCipta({ ...f, lot_id: lot.id });
            toast.success(r.message ?? 'Didaftarkan.');
            setLot(null);
            setQ('');
            setF({ jenis_cukai: 'tanah' });
            onDone();
        } catch (err) {
            toast.error(ralat(err));
        }
    }

    return (
        <form onSubmit={simpan} className="space-y-3">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <Field label="Lot" htmlFor="o-lot">
                    {lot ? (
                        <p className="text-body-sm py-2">
                            {lot.no_lot} ({lot.kod_mukim}){' '}
                            <button type="button" className="text-txt-primary underline" onClick={() => setLot(null)}>
                                tukar
                            </button>
                        </p>
                    ) : (
                        <>
                            <input id="o-lot" className={inputClass} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari no. lot" />
                            <ul className="mt-1 max-h-40 overflow-y-auto">
                                {(lots ?? []).map((l) => (
                                    <li key={l.id}>
                                        <button type="button" className="w-full text-left px-2 py-1 text-body-sm hover:bg-bg-washed" onClick={() => setLot(l)}>
                                            {l.no_lot} · {l.kod_mukim}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                </Field>
                <Field label="Jenis cukai" htmlFor="o-jenis">
                    <Select id="o-jenis" value={f.jenis_cukai} onChange={(v) => setF((p) => ({ ...p, jenis_cukai: v }))} options={ref?.jenis_cukai ?? []} />
                </Field>
                <Field label="No. akaun cukai" htmlFor="o-akaun">
                    <input id="o-akaun" className={inputClass} value={f.no_akaun_cukai ?? ''} onChange={(e) => setF((p) => ({ ...p, no_akaun_cukai: e.target.value }))} required />
                </Field>
                {f.jenis_cukai === 'taksiran_pbt' && (
                    <Field label="PBT" htmlFor="o-pbt" help="Lalai: PBT bagi daerah lot">
                        <Select id="o-pbt" value={f.kod_pbt ?? ''} onChange={(v) => setF((p) => ({ ...p, kod_pbt: v }))} options={ref?.pbt ?? []} empty="Lalai" />
                    </Field>
                )}
            </div>
            <Button type="submit" disabled={!lot}>
                Daftar obligasi
            </Button>
        </form>
    );
}

/** SRS-CKI-01/06: tax register — every obligation linked to a lot, with its issuing agency and API coverage. */
export default function ObligasiPage() {
    const can = useCan();
    const qc = useQueryClient();
    const [f, setF] = useState<Record<string, string>>({});
    const [page, setPage] = useState(1);
    const params = { ...f, page };
    const { data, isLoading, error } = useQuery({ queryKey: ['cki', 'obligasi', params], queryFn: () => ckiApi.obligasi(params) });
    const rows = data?.data ?? [];

    return (
        <PageContainer>
            {can('cki.obligasi.cipta') && (
                <Card title="Daftar obligasi baharu">
                    <DaftarObligasi onDone={() => qc.invalidateQueries({ queryKey: ['cki', 'obligasi'] })} />
                </Card>
            )}
            <Card>
                <input type="search" aria-label="Cari" className={inputClass} placeholder="Cari no. akaun atau no. lot…" value={f.q ?? ''} onChange={(e) => setF((p) => ({ ...p, q: e.target.value }))} />
                <Penapis value={f} onChange={(k, v) => { setF((p) => ({ ...p, [k]: v })); setPage(1); }} show={['kod_daerah', 'kod_agensi', 'jenis_cukai']} />
                <p className="text-body-sm text-txt-black-500">{error ? <span className="text-txt-danger">{ralat(error)}</span> : data ? `${data.meta.total} obligasi` : 'Memuatkan…'}</p>
                <Table head={<><th>Lot</th><th>Jenis / akaun</th><th className="hidden md:table-cell">Agensi</th><th>Liputan</th></>}>
                    {isLoading ? (
                        <Kosong colSpan={4}>Memuatkan…</Kosong>
                    ) : rows.length === 0 ? (
                        <Kosong colSpan={4}>Tiada obligasi.</Kosong>
                    ) : (
                        rows.map((o) => (
                            <tr key={o.id}>
                                <td>
                                    <Link href={`/admin/cki/lot/${o.lot_id}`} className="text-txt-primary hover:underline">
                                        {o.no_lot}
                                    </Link>{' '}
                                    <Sintetik show={o.sintetik} />
                                    <p className="text-body-xs text-txt-black-500">{o.kod_mukim}</p>
                                </td>
                                <td className="max-w-[10rem] sm:max-w-xs break-words">
                                    {o.jenis_cukai_nama}
                                    <p className="text-body-xs text-txt-black-500">{o.no_akaun_cukai}</p>
                                </td>
                                <td className="hidden md:table-cell">{o.nama_agensi ?? o.kod_agensi}</td>
                                <td>
                                    <Status kod={o.status_liputan_api} nama={o.status_liputan_api === 'dalam' ? (o.mod_agensi === 'sandaran' ? 'Mod sandaran' : 'Dalam liputan') : 'Luar liputan'} />
                                </td>
                            </tr>
                        ))
                    )}
                </Table>
                {data && data.meta.last_page > 1 && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />}
            </Card>
        </PageContainer>
    );
}
