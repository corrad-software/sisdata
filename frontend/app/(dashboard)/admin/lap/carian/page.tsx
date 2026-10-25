'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Callout } from '@/components/ui/Callout';
import { Pagination } from '@/components/ui/Pagination';
import { lapApi, papar, ralat } from '@/lib/lap';
import { Card, inputClass, Kosong } from '../_components/common';

/** SRS-LAP-14 — smart property search with natural keywords (no field names or codes needed). */
export default function CarianPage() {
    const [teks, setTeks] = useState('');
    const [q, setQ] = useState('');
    const [halaman, setHalaman] = useState(1);
    const r = useQuery({ queryKey: ['lap', 'carian', q, halaman], queryFn: () => lapApi.carian(q, halaman).then((x) => x.data), enabled: q.length >= 2 });
    const h = r.data?.hasil;

    return (
        <PageContainer>
            <Card title="Carian pintar hartanah">
                <form
                    className="flex flex-col sm:flex-row gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        setQ(teks.trim());
                        setHalaman(1);
                    }}
                >
                    <label htmlFor="cq" className="sr-only">
                        Kata kunci
                    </label>
                    <input id="cq" className={inputClass} value={teks} onChange={(e) => setTeks(e.target.value)} placeholder='cth. "tanah wakaf pertanian tiada pajakan di Sepang" atau "lot 1234"' />
                    <Button type="submit">Cari</Button>
                </form>
                {r.data && r.data.tafsiran.length > 0 && (
                    <div className="flex flex-wrap gap-2" aria-label="Tafsiran carian">
                        {r.data.tafsiran.map((t) => (
                            <Badge key={t} variant="info">
                                {t}
                            </Badge>
                        ))}
                    </div>
                )}
                {r.data?.mesej && <Callout type="info">{r.data.mesej}</Callout>}
                {r.isError && <Callout type="danger">{ralat(r.error)}</Callout>}
            </Card>
            {h && (
                <Card title={`${h.bilangan_rekod.toLocaleString('ms-MY')} lot ditemui`}>
                    {h.baris.length === 0 ? (
                        <Kosong>Tiada lot sepadan dalam skop anda.</Kosong>
                    ) : (
                        <>
                            <Table head={<>{h.lajur.map((l) => <th key={l.kunci}>{l.label}</th>)}<th /></>}>
                                {h.baris.map((b, i) => (
                                    <tr key={b._id ?? i}>
                                        {h.lajur.map((l) => (
                                            <td key={l.kunci}>{papar(l.jenis, b[l.kunci], b._label?.[l.kunci])}</td>
                                        ))}
                                        <td>
                                            {b._pautan && (
                                                <Link href={b._pautan} className="text-txt-primary hover:underline whitespace-nowrap">
                                                    Profil 360°
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </Table>
                            {h.bilangan_rekod > 25 && <Pagination currentPage={halaman} lastPage={Math.ceil(h.bilangan_rekod / 25)} onPageChange={setHalaman} total={h.bilangan_rekod} />}
                        </>
                    )}
                </Card>
            )}
        </PageContainer>
    );
}
