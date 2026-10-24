'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { Pagination } from '@/components/ui/Pagination';
import { ckiApi, ralat, type Obligasi } from '@/lib/cki';
import { Card, Field, inputClass, Kosong, Status } from '../_components/common';
import { Penapis } from '../_components/Penapis';
import { BilManual } from '../_components/BilManual';

/** Aliran proses langkah 3 / SRS-CKI-07: lots outside API coverage (or in fallback mode) still needing a manual bill for the year. */
export default function TugasanPage() {
    const [f, setF] = useState<Record<string, string>>({ tahun: String(new Date().getFullYear()) });
    const [page, setPage] = useState(1);
    const [pilih, setPilih] = useState<Obligasi | null>(null);
    const params = { ...f, page };
    const { data, isLoading, error } = useQuery({ queryKey: ['cki', 'tugasan', params], queryFn: () => ckiApi.tugasan(params) });
    const rows = data?.data ?? [];

    return (
        <PageContainer>
            <Card>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <Field label="Tahun taksiran" htmlFor="t-tahun">
                        <input id="t-tahun" type="number" className={inputClass} value={f.tahun} onChange={(e) => setF((p) => ({ ...p, tahun: e.target.value }))} />
                    </Field>
                </div>
                <Penapis value={f} onChange={(k, v) => setF((p) => ({ ...p, [k]: v }))} show={['kod_daerah']} />
                <p className="text-body-sm text-txt-black-500">{error ? <span className="text-txt-danger">{ralat(error)}</span> : data ? `${data.meta.total} obligasi memerlukan rekod manual` : 'Memuatkan…'}</p>
                <Table head={<><th>Lot</th><th>Jenis / akaun</th><th className="hidden md:table-cell">Agensi</th><th>Sebab</th><th><span className="sr-only">Tindakan</span></th></>}>
                    {isLoading ? (
                        <Kosong colSpan={5}>Memuatkan…</Kosong>
                    ) : rows.length === 0 ? (
                        <Kosong colSpan={5}>Tiada tugasan rekod manual.</Kosong>
                    ) : (
                        rows.map((o) => (
                            <tr key={o.id}>
                                <td>{o.no_lot}</td>
                                <td className="max-w-[9rem] sm:max-w-xs break-words">
                                    {o.jenis_cukai_nama}
                                    <p className="text-body-xs text-txt-black-500">{o.no_akaun_cukai}</p>
                                </td>
                                <td className="hidden md:table-cell">{o.nama_agensi ?? o.kod_agensi}</td>
                                <td>
                                    <Status kod={o.mod_agensi === 'sandaran' ? 'sandaran' : 'luar'} nama={o.mod_agensi === 'sandaran' ? 'API terganggu' : 'Luar liputan'} />
                                </td>
                                <td>
                                    <Button size="sm" onClick={() => setPilih(o)}>
                                        Rekod bil
                                    </Button>
                                </td>
                            </tr>
                        ))
                    )}
                </Table>
                {data && data.meta.last_page > 1 && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />}
            </Card>
            {pilih && <BilManual open onClose={() => setPilih(null)} obligasi={pilih} />}
        </PageContainer>
    );
}
