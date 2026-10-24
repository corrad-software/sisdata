'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { Table } from '@/components/ui/Table';
import { pjkApi, ralat, tarikh, useCan } from '@/lib/pjk';
import { Card, Field, inputClass, Kosong, Select, Status } from '../_components/common';

const SUMBER = [
    { kod: 'gateway', nama: 'Payment gateway' },
    { kod: 'grp', nama: 'GRP MAIS' },
    { kod: 'esewa', nama: 'e-Sewa MAIS' },
];

/** SRS-PJK-18/21: daily reconciliation runs, statement upload and the discrepancy list for Finance. */
export default function RekonsiliasiPage() {
    const can = useCan();
    const qc = useQueryClient();
    const [sumber, setSumber] = useState('gateway');
    const [tarikhR, setTarikhR] = useState(() => new Date(Date.now() - 86_400_000).toISOString().slice(0, 10));
    const [fail, setFail] = useState<File | null>(null);
    const [larian, setLarian] = useState('');
    const { data, isLoading, error } = useQuery({ queryKey: ['pjk', 'rekonsiliasi', larian], queryFn: () => pjkApi.rekonsiliasi({ larian }) });
    const refresh = () => qc.invalidateQueries({ queryKey: ['pjk', 'rekonsiliasi'] });

    async function jalan() {
        try {
            const r = fail
                ? await pjkApi.muatNaikPenyelesaian((() => {
                      const fd = new FormData();
                      fd.append('sumber', sumber);
                      fd.append('tarikh', tarikhR);
                      fd.append('fail', fail);
                      return fd;
                  })())
                : await pjkApi.jalanRekonsiliasi(sumber, tarikhR);
            toast.success(`Padan ${r.data.bil_padan}, sisihan ${r.data.bil_sisihan}.`);
            refresh();
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    async function tindakan(id: number, status: string) {
        const catatan = window.prompt('Catatan tindakan');
        if (!catatan) return;
        try {
            await pjkApi.tindakanSisihan(id, status, catatan);
            refresh();
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    return (
        <PageContainer>
            {can('pjk.rekonsiliasi.cipta') && (
                <Card title="Jalankan rekonsiliasi">
                    <div className="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                        <Field label="Sumber" htmlFor="r-sumber">
                            <Select id="r-sumber" value={sumber} onChange={setSumber} options={SUMBER} />
                        </Field>
                        <Field label="Tarikh" htmlFor="r-tarikh">
                            <input id="r-tarikh" type="date" className={inputClass} value={tarikhR} onChange={(e) => setTarikhR(e.target.value)} />
                        </Field>
                        <Field label="Fail penyelesaian (CSV, pilihan)" htmlFor="r-fail" help="rujukan,amaun,status,tarikh">
                            <input id="r-fail" type="file" accept=".csv,text/csv" className="block w-full text-body-sm" onChange={(e) => setFail(e.target.files?.[0] ?? null)} />
                        </Field>
                        <Button onClick={jalan}>{fail ? 'Muat naik & rekonsiliasi' : 'Tarik penyata & rekonsiliasi'}</Button>
                    </div>
                </Card>
            )}

            <Card title="Larian">
                {error && <p className="text-body-sm text-txt-danger">{ralat(error)}</p>}
                <Table head={<><th>Tarikh</th><th>Sumber</th><th>Padan</th><th>Sisihan</th><th>Status</th></>}>
                    {isLoading ? (
                        <Kosong colSpan={5}>Memuatkan…</Kosong>
                    ) : (data?.larian ?? []).length === 0 ? (
                        <Kosong colSpan={5}>Belum ada larian.</Kosong>
                    ) : (
                        data?.larian.map((r) => (
                            <tr key={r.id}>
                                <td>
                                    <button type="button" className="text-txt-primary hover:underline" onClick={() => setLarian(String(r.id))}>
                                        {tarikh(r.tarikh)}
                                    </button>
                                </td>
                                <td>{SUMBER.find((s) => s.kod === r.sumber)?.nama ?? r.sumber}</td>
                                <td>{r.bil_padan}</td>
                                <td>{r.bil_sisihan}</td>
                                <td>
                                    <Status kod={r.status === 'gagal' ? 'gagal' : 'selesai'} nama={r.status} />
                                    {r.mesej && <p className="text-body-xs text-txt-danger">{r.mesej}</p>}
                                </td>
                            </tr>
                        ))
                    )}
                </Table>
            </Card>

            <Card title={larian ? `Sisihan larian #${larian}` : 'Sisihan belum selesai'} actions={larian ? <Button size="sm" variant="outline" onClick={() => setLarian('')}>Semua belum selesai</Button> : undefined}>
                <Table head={<><th>Jenis</th><th className="hidden sm:table-cell">Rujukan</th><th>SISDATA / luar</th><th>Tindakan</th></>}>
                    {(data?.sisihan ?? []).length === 0 ? (
                        <Kosong colSpan={4}>Tiada sisihan.</Kosong>
                    ) : (
                        data?.sisihan.map((s) => (
                            <tr key={s.id}>
                                <td className="max-w-[12rem]">
                                    {s.jenis_nama}
                                    <p className="text-body-xs text-txt-black-500">
                                        {s.sumber.toUpperCase()} · {tarikh(s.tarikh)}
                                    </p>
                                    <p className="text-body-xs sm:hidden break-all">{s.rujukan}</p>
                                </td>
                                <td className="hidden sm:table-cell text-body-xs break-all">
                                    {s.kontrak_id ? (
                                        <Link href={`/admin/pjk/kontrak/${s.kontrak_id}?tab=bayaran`} className="text-txt-primary hover:underline">
                                            {s.rujukan}
                                        </Link>
                                    ) : (
                                        s.rujukan
                                    )}
                                </td>
                                <td className="text-body-xs whitespace-nowrap">
                                    {s.amaun_sistem ?? '—'}
                                    <br />
                                    {s.amaun_luar ?? '—'}
                                </td>
                                <td>
                                    <Status kod={s.status_tindakan} nama={s.status_tindakan.replace('_', ' ')} />
                                    {s.catatan && <p className="text-body-xs text-txt-black-500 break-words">{s.catatan}</p>}
                                    {can('pjk.rekonsiliasi.kemas_kini') && s.status_tindakan !== 'selesai' && (
                                        <div className="mt-1 flex flex-wrap gap-1">
                                            <Button size="sm" variant="outline" onClick={() => tindakan(s.id, 'dalam_tindakan')}>
                                                Dalam tindakan
                                            </Button>
                                            <Button size="sm" onClick={() => tindakan(s.id, 'selesai')}>
                                                Selesai
                                            </Button>
                                        </div>
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
