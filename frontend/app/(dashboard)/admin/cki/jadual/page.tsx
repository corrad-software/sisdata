'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { ckiApi, ralat, rm, tarikh } from '@/lib/cki';
import { Card, Field, inputClass, Kosong, Status } from '../_components/common';
import { Penapis } from '../_components/Penapis';

/** SRS-CKI-03: payment schedule for the planning period, grouped by due month, district or agency (PBT / PTD). */
export default function JadualPage() {
    const [f, setF] = useState<Record<string, string>>({ kumpulan: 'tarikh_akhir' });
    const { data, isLoading, error } = useQuery({ queryKey: ['cki', 'jadual', f], queryFn: () => ckiApi.jadual(f) });

    return (
        <PageContainer>
            <Card>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <Field label="Kumpulkan mengikut" htmlFor="j-kump">
                        <select id="j-kump" className={inputClass} value={f.kumpulan} onChange={(e) => setF((p) => ({ ...p, kumpulan: e.target.value }))}>
                            <option value="tarikh_akhir">Bulan tarikh akhir</option>
                            <option value="daerah">Daerah</option>
                            <option value="pbt">Agensi (PBT / PTD)</option>
                        </select>
                    </Field>
                    <Field label="Tempoh perancangan (hari)" htmlFor="j-hari">
                        <input id="j-hari" type="number" min={1} className={inputClass} value={f.hari ?? ''} placeholder="Lalai parameter" onChange={(e) => setF((p) => ({ ...p, hari: e.target.value }))} />
                    </Field>
                </div>
                <Penapis value={f} onChange={(k, v) => setF((p) => ({ ...p, [k]: v }))} show={['kod_daerah', 'kod_agensi', 'jenis_cukai']} />
            </Card>
            {error && <p className="text-txt-danger">{ralat(error)}</p>}
            {isLoading || !data ? (
                <p className="text-body-sm text-txt-black-500">Memuatkan…</p>
            ) : (
                <>
                    <p className="text-body-sm text-txt-black-700">
                        Bil belum dibayar yang matang hingga {tarikh(data.hingga)} ({data.hari} hari): <strong>{rm(data.jumlah)}</strong>
                    </p>
                    {data.kumpulan.length === 0 && <Kosong>Tiada bil dalam tempoh perancangan.</Kosong>}
                    {data.kumpulan.map((g) => (
                        <Card key={g.kod} title={`${g.nama} — ${rm(g.jumlah)}`}>
                            <Table head={<><th>Bil</th><th className="hidden sm:table-cell">Lot</th><th>Tarikh akhir</th><th>Baki</th><th className="hidden md:table-cell">Status</th></>}>
                                {g.bil.map((b) => (
                                    <tr key={b.id}>
                                        <td className="max-w-[10rem] sm:max-w-xs break-words">
                                            <Link href={`/admin/cki/bil/${b.id}`} className="text-txt-primary hover:underline">
                                                {b.no_bil}
                                            </Link>
                                            <p className="text-body-xs text-txt-black-500">{b.jenis_nama}</p>
                                        </td>
                                        <td className="hidden sm:table-cell">{b.no_lot}</td>
                                        <td className="whitespace-nowrap">{tarikh(b.tarikh_akhir)}</td>
                                        <td className="whitespace-nowrap">{rm(b.baki)}</td>
                                        <td className="hidden md:table-cell">
                                            <Status kod={b.status_bayaran} nama={b.status_nama} />
                                        </td>
                                    </tr>
                                ))}
                            </Table>
                        </Card>
                    ))}
                </>
            )}
        </PageContainer>
    );
}
