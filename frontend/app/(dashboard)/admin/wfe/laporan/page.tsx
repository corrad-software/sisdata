'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { PageContainer } from '@/components/PageContainer';
import { StatCard } from '@/components/ui/StatCard';
import { Table } from '@/components/ui/Table';
import { FieldInput } from '@/components/ui/FieldInput';
import { Card } from '../_components/common';

interface StageRow {
    kod_aliran: string;
    peringkat: string;
    nama_peringkat: string;
    jumlah: number;
    selesai: number;
    tertunggak: number;
    dieskalasi: number;
    purata_jam: number | null;
}

interface Report {
    ikut_peringkat: StageRow[];
    kesesakan: StageRow[];
    eskalasi: { jumlah_tugasan: number; dieskalasi: number; kadar_peratus: number; ikut_aras: Record<string, number> };
    tertunggak_ikut_pelulus: Array<{ penerima: string; label: string; jumlah: number; lewat: number }>;
}

function monthStart(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`;
}

/** SRS-WFE-23: workflow performance report. */
export default function LaporanPage() {
    const [from, setFrom] = useState(monthStart());
    const [to, setTo] = useState('');
    const [flow, setFlow] = useState('');
    const qs = new URLSearchParams(Object.entries({ dari: from, hingga: to, kod_aliran: flow }).filter(([, v]) => v)).toString();
    const { data, isLoading } = useQuery({
        queryKey: ['wfe', 'laporan', qs],
        queryFn: () => api.get<{ data: Report }>(`/wfe/laporan?${qs}`).then((r) => r.data),
    });
    const fmt = (n: number | null) => (n === null ? '-' : n.toLocaleString('ms-MY', { maximumFractionDigits: 2 }));

    return (
        <PageContainer>
            <Card title="Penapis">
                <div className="grid gap-4 sm:grid-cols-3">
                    <FieldInput label="Dari" name="dari" type="date" value={from} onChange={setFrom} />
                    <FieldInput label="Hingga" name="hingga" type="date" value={to} onChange={setTo} />
                    <FieldInput label="Kod aliran" name="kod_aliran" value={flow} onChange={setFlow} placeholder="cth. contoh.kelulusan" />
                </div>
            </Card>

            {isLoading && <p className="text-body-sm text-txt-black-500">Memuatkan…</p>}
            {data && (
                <>
                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <StatCard label="Tugasan kelulusan" value={String(data.eskalasi.jumlah_tugasan)} />
                        <StatCard label="Dieskalasi" value={String(data.eskalasi.dieskalasi)} />
                        <StatCard label="Kadar eskalasi" value={`${fmt(data.eskalasi.kadar_peratus)}%`} />
                        <StatCard
                            label="Eskalasi mengikut aras"
                            value={Object.entries(data.eskalasi.ikut_aras).map(([a, n]) => `A${a}: ${n}`).join(' · ') || '-'}
                        />
                    </div>

                    <Card title="Kesesakan (peringkat paling perlahan)">
                        <ol className="list-decimal ms-5 space-y-1 text-body-sm">
                            {data.kesesakan.map((s) => (
                                <li key={s.kod_aliran + s.peringkat}>
                                    <span className="font-medium">{s.nama_peringkat}</span> ({s.kod_aliran}) — purata {fmt(s.purata_jam)} jam, {s.tertunggak} tertunggak
                                </li>
                            ))}
                        </ol>
                    </Card>

                    <Card title="Purata tempoh mengikut peringkat">
                        <Table head={<><th>Aliran</th><th>Peringkat</th><th className="text-right">Tugasan</th><th className="text-right">Selesai</th><th className="text-right">Tertunggak</th><th className="text-right">Dieskalasi</th><th className="text-right">Purata (jam)</th></>}>
                            {data.ikut_peringkat.map((s) => (
                                <tr key={s.kod_aliran + s.peringkat}>
                                    <td>{s.kod_aliran}</td>
                                    <td>{s.nama_peringkat}</td>
                                    <td className="text-right">{s.jumlah}</td>
                                    <td className="text-right">{s.selesai}</td>
                                    <td className="text-right">{s.tertunggak}</td>
                                    <td className="text-right">{s.dieskalasi}</td>
                                    <td className="text-right">{fmt(s.purata_jam)}</td>
                                </tr>
                            ))}
                        </Table>
                    </Card>

                    <Card title="Tugasan tertunggak mengikut pelulus">
                        <Table head={<><th>Pelulus</th><th>Jenis</th><th className="text-right">Tertunggak</th><th className="text-right">Melepasi SLA</th></>}>
                            {data.tertunggak_ikut_pelulus.map((r) => (
                                <tr key={r.penerima}>
                                    <td>{r.label}</td>
                                    <td>{r.penerima.split(':')[0]}</td>
                                    <td className="text-right">{r.jumlah}</td>
                                    <td className="text-right">{r.lewat}</td>
                                </tr>
                            ))}
                        </Table>
                    </Card>
                </>
            )}
        </PageContainer>
    );
}
