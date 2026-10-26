'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Callout } from '@/components/ui/Callout';
import { KATEGORI_LABEL, lapApi, ralat } from '@/lib/lap';
import { Card, Field, inputClass } from '../_components/common';

/** SRS-LAP-23 — reporting data dictionary: metric definition, source, owner; dataset fields with MOD-PRO's kamus data. */
export default function KamusPage() {
    const q = useQuery({ queryKey: ['lap', 'kamus'], queryFn: () => lapApi.kamus().then((r) => r.data) });
    const [set, setSet] = useState('');
    const pilihan = q.data?.set_data.find((s) => s.kunci === set) ?? q.data?.set_data[0];

    return (
        <PageContainer>
            {q.isError && <Callout type="danger">{ralat(q.error)}</Callout>}
            <Card title="Katalog laporan — definisi metrik">
                <p className="text-body-sm text-txt-black-500">
                    Definisi medan sumber diurus dalam{' '}
                    <Link href={q.data?.pautan_kamus_pro ?? '/admin/pro/kamus'} className="text-txt-primary hover:underline">
                        Kamus Data MOD-PRO
                    </Link>{' '}
                    (dokumen: <span className="font-mono">{q.data?.dokumen}</span>).
                </p>
                {q.data && (
                    <Table head={<><th>Kod</th><th>Laporan</th><th>Kategori</th><th>Definisi metrik</th><th>Sumber data</th><th>Pemilik</th></>}>
                        {q.data.laporan.map((l) => (
                            <tr key={l.kod}>
                                <td className="font-mono text-body-xs">{l.kod}</td>
                                <td>
                                    {l.nama} {l.status_pengesahan === 'disahkan' && <Badge variant="success">Disahkan</Badge>}
                                </td>
                                <td>{KATEGORI_LABEL[l.kategori] ?? l.kategori}</td>
                                <td className="text-body-xs max-w-md">{l.definisi_metrik}</td>
                                <td className="text-body-xs font-mono">{l.sumber_data}</td>
                                <td className="text-body-xs">{l.pemilik}</td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>
            {pilihan && (
                <Card title={`Set data — ${pilihan.label}`}>
                    <Field label="Set data" htmlFor="kset">
                        <select id="kset" className={inputClass} value={pilihan.kunci} onChange={(e) => setSet(e.target.value)}>
                            {q.data?.set_data.map((s) => (
                                <option key={s.kunci} value={s.kunci}>
                                    {s.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <p className="text-body-xs text-txt-black-500 font-mono">
                        {pilihan.paparan} ← {pilihan.jadual_sumber} (MOD-{pilihan.modul.toUpperCase()})
                    </p>
                    <Table head={<><th>Medan</th><th>Jenis</th><th>Sumber</th><th>Definisi</th><th>Nilai dibenarkan</th><th>Pemilik data</th><th>Klasifikasi</th></>}>
                        {pilihan.medan.map((m) => (
                            <tr key={m.kunci}>
                                <td>
                                    {m.label}
                                    <div className="text-body-xs font-mono text-txt-black-500">{m.kunci}</div>
                                </td>
                                <td>{m.jenis}</td>
                                <td className="text-body-xs font-mono">{m.sumber}</td>
                                <td className="text-body-xs max-w-sm">{m.definisi ?? '—'}</td>
                                <td className="text-body-xs">{m.nilai_dibenarkan ?? '—'}</td>
                                <td className="text-body-xs">{m.data_owner ?? '—'}</td>
                                <td className="text-body-xs">{m.klasifikasi ?? '—'}</td>
                            </tr>
                        ))}
                    </Table>
                </Card>
            )}
        </PageContainer>
    );
}
