'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { ckiApi, ralat, rm, tarikh, useCan } from '@/lib/cki';
import { Card, Field, inputClass, Kosong, Status } from '../_components/common';

/** SRS-CKI-20: reconciliation between system records, receipts and GRP MAIS, with the deviation list. */
export default function RekonsiliasiPage() {
    const can = useCan();
    const qc = useQueryClient();
    const [today] = useState(() => new Date().toISOString().slice(0, 10));
    const [dari, setDari] = useState(() => {
        const d = new Date();
        d.setDate(d.getDate() - 30);
        return d.toISOString().slice(0, 10);
    });
    const [hingga, setHingga] = useState(today);
    const [larian, setLarian] = useState<number | null>(null);
    const { data, error } = useQuery({ queryKey: ['cki', 'rekonsiliasi', larian], queryFn: () => ckiApi.rekonsiliasi({ larian: larian ?? '' }) });

    async function jalan(e: React.FormEvent) {
        e.preventDefault();
        try {
            const r = await ckiApi.jalankanRekonsiliasi({ dari, hingga });
            toast.success(`${r.message ?? 'Selesai.'} ${r.data.bil_padan} padan, ${r.data.bil_sisihan} sisihan.`);
            setLarian(r.data.id);
            qc.invalidateQueries({ queryKey: ['cki', 'rekonsiliasi'] });
        } catch (err) {
            toast.error(ralat(err));
        }
    }

    return (
        <PageContainer>
            {can('cki.rekonsiliasi.cipta') && (
                <Card title="Jalankan rekonsiliasi">
                    <form onSubmit={jalan} className="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                        <Field label="Dari" htmlFor="r-dari">
                            <input id="r-dari" type="date" className={inputClass} value={dari} onChange={(e) => setDari(e.target.value)} />
                        </Field>
                        <Field label="Hingga" htmlFor="r-hingga">
                            <input id="r-hingga" type="date" className={inputClass} value={hingga} max={today} onChange={(e) => setHingga(e.target.value)} />
                        </Field>
                        <Button type="submit">Jalankan</Button>
                    </form>
                </Card>
            )}
            {error && <p className="text-txt-danger">{ralat(error)}</p>}
            <Card title="Larian">
                <div className="flex flex-wrap gap-2">
                    {(data?.larian ?? []).map((l) => (
                        <Button key={l.id} size="sm" variant={data?.larian_dipilih === l.id ? 'primary' : 'outline'} onClick={() => setLarian(l.id)}>
                            #{l.id} {tarikh(l.dari)}–{tarikh(l.hingga)} · {l.bil_sisihan} sisihan
                        </Button>
                    ))}
                    {data?.larian.length === 0 && <Kosong>Belum ada larian.</Kosong>}
                </div>
            </Card>
            <Card title="Senarai sisihan">
                <Table head={<><th>Jenis</th><th className="hidden sm:table-cell">Sistem</th><th className="hidden sm:table-cell">Resit</th><th className="hidden sm:table-cell">GRP</th><th>Catatan</th></>}>
                    {(data?.sisihan ?? []).length === 0 ? (
                        <Kosong colSpan={5}>Tiada sisihan.</Kosong>
                    ) : (
                        data?.sisihan.map((s) => (
                            <tr key={s.id}>
                                <td>
                                    <Status kod={s.status} nama={s.jenis_nama} />
                                </td>
                                <td className="hidden sm:table-cell whitespace-nowrap">{rm(s.amaun_sistem)}</td>
                                <td className="hidden sm:table-cell whitespace-nowrap">{rm(s.amaun_resit)}</td>
                                <td className="hidden sm:table-cell whitespace-nowrap">{rm(s.amaun_grp)}</td>
                                <td className="max-w-[12rem] sm:max-w-md break-words">
                                    {s.id_bil ? (
                                        <Link href={`/admin/cki/bil/${s.id_bil}`} className="text-txt-primary hover:underline">
                                            {s.catatan}
                                        </Link>
                                    ) : (
                                        s.catatan
                                    )}
                                </td>
                            </tr>
                        ))
                    )}
                </Table>
            </Card>
        </PageContainer>
    );
}
