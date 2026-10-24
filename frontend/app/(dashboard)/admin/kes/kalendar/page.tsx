'use client';

import { useMemo, useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { kesApi, useRujukan, type EntriKalendar } from '@/lib/kes';
import { Card, Field, inputClass, Kosong, RalatAkses, Select } from '../_components/common';

const HARI = ['Isn', 'Sel', 'Rab', 'Kha', 'Jum', 'Sab', 'Ahd'];

function ymd(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/** SRS-KES-09: division-level legal calendar, filterable by officer, case type, court and district. */
export default function KalendarPage() {
    const { data: ref } = useRujukan();
    const [bulan, setBulan] = useState(() => {
        const d = new Date();
        return new Date(d.getFullYear(), d.getMonth(), 1);
    });
    const [f, setF] = useState<Record<string, string>>({ id_pegawai: '', jenis: '', mahkamah: '', kod_daerah: '', jenis_prosiding: '' });
    const set = (k: string) => (v: string) => setF((x) => ({ ...x, [k]: v }));
    const dari = ymd(bulan);
    const hingga = ymd(new Date(bulan.getFullYear(), bulan.getMonth() + 1, 0));
    const { data, error, isLoading } = useQuery({ queryKey: ['kes', 'kalendar', dari, f], queryFn: () => kesApi.kalendar({ ...f, dari, hingga }).then((r) => r.data) });

    const ikutHari = useMemo(() => {
        const m: Record<string, EntriKalendar[]> = {};
        for (const e of data ?? []) (m[e.tarikh.slice(0, 10)] ??= []).push(e);
        return m;
    }, [data]);

    const sel: Array<Date | null> = [];
    const offset = (bulan.getDay() + 6) % 7;
    for (let i = 0; i < offset; i++) sel.push(null);
    const last = new Date(bulan.getFullYear(), bulan.getMonth() + 1, 0).getDate();
    for (let d = 1; d <= last; d++) sel.push(new Date(bulan.getFullYear(), bulan.getMonth(), d));
    const tajuk = bulan.toLocaleDateString('ms-MY', { month: 'long', year: 'numeric' });
    const today = ymd(new Date());

    return (
        <PageContainer>
            <Card title="Penapis">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <Field label="Pegawai kes" htmlFor="c-pg">
                        <Select id="c-pg" value={f.id_pegawai} onChange={set('id_pegawai')} options={(ref?.pegawai ?? []).map((u) => ({ kod: String(u.id), nama: u.nama }))} empty="Semua" />
                    </Field>
                    <Field label="Jenis kes" htmlFor="c-je">
                        <Select id="c-je" value={f.jenis} onChange={set('jenis')} options={ref?.jenis ?? []} empty="Semua" />
                    </Field>
                    <Field label="Mahkamah" htmlFor="c-ma">
                        <input id="c-ma" className={inputClass} value={f.mahkamah} onChange={(e) => set('mahkamah')(e.target.value)} placeholder="cth. Shah Alam" />
                    </Field>
                    <Field label="Daerah" htmlFor="c-da">
                        <Select id="c-da" value={f.kod_daerah} onChange={set('kod_daerah')} options={ref?.daerah ?? []} empty="Semua" />
                    </Field>
                    <Field label="Jenis prosiding" htmlFor="c-jp">
                        <Select id="c-jp" value={f.jenis_prosiding} onChange={set('jenis_prosiding')} options={ref?.jenis_prosiding ?? []} empty="Semua (termasuk tarikh penting)" />
                    </Field>
                </div>
            </Card>
            <Card
                title={<span className="capitalize">{tajuk}</span>}
                actions={
                    <>
                        <Button size="sm" variant="secondary" onClick={() => setBulan(new Date(bulan.getFullYear(), bulan.getMonth() - 1, 1))}>
                            ‹ Sebelum
                        </Button>
                        <Button size="sm" variant="secondary" onClick={() => setBulan(new Date(new Date().getFullYear(), new Date().getMonth(), 1))}>
                            Bulan ini
                        </Button>
                        <Button size="sm" variant="secondary" onClick={() => setBulan(new Date(bulan.getFullYear(), bulan.getMonth() + 1, 1))}>
                            Seterusnya ›
                        </Button>
                    </>
                }
            >
                {error ? (
                    <RalatAkses error={error} />
                ) : isLoading ? (
                    <p className="text-body-sm text-txt-black-500">Memuatkan…</p>
                ) : (
                    <>
                        <div className="hidden md:grid grid-cols-7 gap-px rounded-md border border-otl-gray-200 bg-otl-gray-200 overflow-hidden">
                            {HARI.map((h) => (
                                <div key={h} className="bg-bg-washed px-2 py-1 text-body-xs font-medium text-txt-black-500">
                                    {h}
                                </div>
                            ))}
                            {sel.map((d, i) => (
                                <div key={i} className={`min-h-[6rem] bg-bg-white p-1.5 ${d && ymd(d) === today ? 'ring-2 ring-inset ring-primary-600' : ''}`}>
                                    {d && (
                                        <>
                                            <p className="text-body-xs text-txt-black-500">{d.getDate()}</p>
                                            <ul className="mt-1 space-y-1">
                                                {(ikutHari[ymd(d)] ?? []).slice(0, 4).map((e) => (
                                                    <li key={e.id}>
                                                        <Link
                                                            href={`/admin/kes/kes/${e.kes_id}?tab=prosiding`}
                                                            className={`block truncate rounded px-1 text-[11px] leading-4 ${e.jenis_entri === 'tarikh_penting' ? 'bg-bg-warning-50 text-txt-warning' : 'bg-bg-primary-50 text-txt-primary'}`}
                                                            title={`${e.no_rujukan} · ${e.jenis_nama}`}
                                                        >
                                                            {e.jenis_nama} · {e.no_rujukan}
                                                        </Link>
                                                    </li>
                                                ))}
                                                {(ikutHari[ymd(d)] ?? []).length > 4 && <li className="text-[11px] leading-4 text-txt-black-500">+{(ikutHari[ymd(d)] ?? []).length - 4} lagi</li>}
                                            </ul>
                                        </>
                                    )}
                                </div>
                            ))}
                        </div>
                        <ul className="md:hidden divide-y divide-otl-divider">
                            {(data ?? []).map((e) => (
                                <li key={e.id} className="py-2">
                                    <Link href={`/admin/kes/kes/${e.kes_id}?tab=prosiding`} className="font-medium text-txt-primary hover:underline">
                                        {e.no_rujukan}
                                    </Link>
                                    <p className="text-body-xs text-txt-black-500">
                                        {new Date(e.tarikh).toLocaleString('ms-MY', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })} · {e.jenis_nama}
                                    </p>
                                    {e.jenis_entri === 'tarikh_penting' && <Badge variant="warning">Tarikh penting</Badge>}
                                </li>
                            ))}
                        </ul>
                        {(data ?? []).length === 0 && <Kosong>Tiada prosiding atau tarikh penting bagi bulan ini.</Kosong>}
                    </>
                )}
            </Card>
        </PageContainer>
    );
}
