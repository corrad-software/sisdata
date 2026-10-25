'use client';

import { Suspense, useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { AnchorButton, Button } from '@/components/ui/Button';
import { Table } from '@/components/ui/Table';
import { EKSPORT_BULANAN, ralat, tarikhMasa, tktApi, useCan, useRujukan } from '@/lib/tkt';
import { Card, Field, inputClass, Kosong, Select } from '../_components/common';

function bulanIni(): string {
    return new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Kuala_Lumpur' }).slice(0, 7);
}

/** SRS-TKT-19 / §18.6: monthly support report (generated from ticket data), PDF / Excel, §18.5 escalations, support activities. */
function Laporan() {
    const params = useSearchParams();
    const can = useCan();
    const qc = useQueryClient();
    const { data: ref } = useRujukan();
    const [bulan, setBulan] = useState(params.get('bulan') ?? bulanIni());
    const [mesej, setMesej] = useState<{ ok: boolean; teks: string } | null>(null);
    const [akt, setAkt] = useState({ jenis: 'tampalan_keselamatan', tarikh: '', tajuk: '', keterangan: '', tempoh_minit: '', punca_akar: '', rujukan: '' });
    const { data, error, isLoading } = useQuery({ queryKey: ['tkt', 'bulanan', bulan], queryFn: () => tktApi.bulanan(bulan).then((r) => r.data) });
    const aktiviti = useQuery({ queryKey: ['tkt', 'aktiviti', bulan], queryFn: () => tktApi.aktiviti({ bulan }).then((r) => r.data) });
    const jana = useMutation({
        mutationFn: () => tktApi.jana(bulan),
        onSuccess: (r) => {
            setMesej({ ok: !r.data.ralat, teks: r.data.ralat ? `${r.message} Amaran: ${r.data.ralat}` : r.message });
            qc.invalidateQueries({ queryKey: ['tkt', 'bulanan', bulan] });
        },
        onError: (e) => setMesej({ ok: false, teks: ralat(e) }),
    });
    const simpan = useMutation({
        mutationFn: () => tktApi.simpanAktiviti({ ...akt, tempoh_minit: akt.tempoh_minit ? Number(akt.tempoh_minit) : null }),
        onSuccess: (r) => {
            setMesej({ ok: true, teks: r.message });
            setAkt((x) => ({ ...x, tajuk: '', keterangan: '', tempoh_minit: '', punca_akar: '', rujukan: '' }));
            qc.invalidateQueries({ queryKey: ['tkt', 'aktiviti'] });
            qc.invalidateQueries({ queryKey: ['tkt', 'bulanan'] });
        },
        onError: (e) => setMesej({ ok: false, teks: ralat(e) }),
    });
    const months = Array.from(new Set([bulanIni(), ...(data?.senarai_bulan ?? [])])).sort().reverse();

    return (
        <PageContainer>
            {mesej && <Alert type={mesej.ok ? 'success' : 'error'}>{mesej.teks}</Alert>}
            <Card
                title="Laporan sokongan bulanan"
                actions={
                    can('tkt.laporan.eksport') && (
                        <>
                            <AnchorButton href={EKSPORT_BULANAN(bulan, 'pdf')} size="sm" variant="secondary">Eksport PDF</AnchorButton>
                            <AnchorButton href={EKSPORT_BULANAN(bulan, 'xlsx')} size="sm" variant="secondary">Eksport Excel</AnchorButton>
                            <Button size="sm" onClick={() => jana.mutate()} disabled={jana.isPending}>Jana &amp; fail ke DMS</Button>
                        </>
                    )
                }
            >
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <Field label="Bulan" htmlFor="l-bulan">
                        <Select id="l-bulan" value={bulan} onChange={setBulan} options={months.map((m) => ({ kod: m, nama: m }))} />
                    </Field>
                </div>
                <p className="text-body-xs text-txt-black-500">
                    Laporan dijana secara automatik pada 1 haribulan setiap bulan daripada data tiket sebenar.{' '}
                    {data?.arkib ? `Salinan terakhir difailkan ${tarikhMasa(data.arkib.dijana_pada)}.` : 'Belum difailkan bagi bulan ini.'}
                </p>
            </Card>

            {error && <Alert type="error">{ralat(error)}</Alert>}
            {isLoading && <Kosong>Memuatkan…</Kosong>}
            {data?.seksyen.map((s) => (
                <Card key={s.tajuk} title={s.tajuk}>
                    {s.baris.length === 0 ? (
                        <Kosong>Tiada rekod.</Kosong>
                    ) : (
                        <div className="overflow-x-auto">
                            <Table head={<>{s.lajur.map((l) => <th key={l}>{l}</th>)}</>}>
                                {s.baris.map((row, i) => (
                                    <tr key={i}>
                                        {row.map((c, j) => (
                                            <td key={j} className="break-words">
                                                {s.tajuk.startsWith('5.') && j === 0 ? <Link href={`/admin/tkt/tiket?q=${encodeURIComponent(String(c))}`} className="text-txt-primary hover:underline">{c}</Link> : c ?? '—'}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </Table>
                        </div>
                    )}
                </Card>
            ))}

            <Card title="Aktiviti sokongan (tampalan, penyelenggaraan, masa henti, pemindahan teknologi)">
                {(aktiviti.data ?? []).length === 0 ? (
                    <Kosong>Tiada aktiviti direkodkan bagi bulan ini.</Kosong>
                ) : (
                    <ul className="divide-y divide-otl-divider text-body-sm">
                        {(aktiviti.data ?? []).map((a) => (
                            <li key={a.id} className="py-2 break-words">
                                <span className="font-medium">{a.tarikh}</span> · {a.jenis_nama} · {a.tajuk}
                                {a.tempoh_minit !== null && ` · ${a.tempoh_minit} minit`}
                                {a.keterangan && <p className="text-body-xs text-txt-black-500">{a.keterangan}</p>}
                            </li>
                        ))}
                    </ul>
                )}
                {can('tkt.aktiviti.cipta') && (
                    <form className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3" onSubmit={(e) => { e.preventDefault(); simpan.mutate(); }}>
                        <Field label="Jenis" htmlFor="ak-j"><Select id="ak-j" value={akt.jenis} onChange={(v) => setAkt((x) => ({ ...x, jenis: v }))} options={ref?.jenis_aktiviti ?? []} /></Field>
                        <Field label="Tarikh" htmlFor="ak-t"><input id="ak-t" type="date" required className={inputClass} value={akt.tarikh} onChange={(e) => setAkt((x) => ({ ...x, tarikh: e.target.value }))} /></Field>
                        <Field label="Tajuk" htmlFor="ak-tj"><input id="ak-tj" required className={inputClass} value={akt.tajuk} onChange={(e) => setAkt((x) => ({ ...x, tajuk: e.target.value }))} /></Field>
                        <Field label="Keterangan" htmlFor="ak-k" className="sm:col-span-2"><input id="ak-k" className={inputClass} value={akt.keterangan} onChange={(e) => setAkt((x) => ({ ...x, keterangan: e.target.value }))} /></Field>
                        <Field label="Tempoh (minit)" htmlFor="ak-m"><input id="ak-m" type="number" min={0} className={inputClass} value={akt.tempoh_minit} onChange={(e) => setAkt((x) => ({ ...x, tempoh_minit: e.target.value }))} /></Field>
                        <Field label="Punca akar (masa henti)" htmlFor="ak-p" className="sm:col-span-2"><input id="ak-p" className={inputClass} value={akt.punca_akar} onChange={(e) => setAkt((x) => ({ ...x, punca_akar: e.target.value }))} /></Field>
                        <Field label="Rujukan" htmlFor="ak-r"><input id="ak-r" className={inputClass} value={akt.rujukan} onChange={(e) => setAkt((x) => ({ ...x, rujukan: e.target.value }))} /></Field>
                        <div className="sm:col-span-2 lg:col-span-3 flex justify-end">
                            <Button type="submit" size="sm" disabled={simpan.isPending}>Rekod aktiviti</Button>
                        </div>
                    </form>
                )}
            </Card>
        </PageContainer>
    );
}

export default function LaporanPage() {
    return (
        <Suspense>
            <Laporan />
        </Suspense>
    );
}
