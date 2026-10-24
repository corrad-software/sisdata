'use client';

import { Suspense, useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { Table } from '@/components/ui/Table';
import { kesApi, muatTurunFail, ralat, rm, tarikh, useCan, useRujukan, type BarisRingkasan, type Kes } from '@/lib/kes';
import { Card, Field, inputClass, Kosong, RalatAkses, Select } from '../_components/common';

const JENIS = [
    { kod: 'ringkasan', nama: 'Ringkasan berkumpulan' },
    { kod: 'senarai', nama: 'Senarai kes' },
    { kod: 'tanpa_tarikh', nama: 'Kes tanpa tarikh prosiding berikutnya' },
    { kod: 'kos', nama: 'Kos guaman terkumpul' },
    { kod: 'keputusan', nama: 'Keputusan mengikut jenis kes' },
    { kod: 'pendedahan', nama: 'Pendedahan perundangan portfolio' },
    { kod: 'beban', nama: 'Beban kes pegawai' },
];

const KUMPUL = [
    { kod: 'status', nama: 'Status' },
    { kod: 'jenis', nama: 'Jenis kes' },
    { kod: 'mahkamah', nama: 'Mahkamah' },
    { kod: 'peringkat', nama: 'Peringkat mahkamah' },
    { kod: 'daerah', nama: 'Daerah' },
    { kod: 'mukim', nama: 'Mukim' },
    { kod: 'kategori_tanah', nama: 'Kategori tanah' },
    { kod: 'pegawai', nama: 'Pegawai bertanggungjawab' },
    { kod: 'peguam', nama: 'Peguam / firma' },
    { kod: 'tahun', nama: 'Tahun pemfailan' },
];

type Data = BarisRingkasan[] | Kes[] | Record<string, unknown> | Array<Record<string, unknown>>;

function Kandungan({ jenis, data }: { jenis: string; data: Data }) {
    if (jenis === 'ringkasan') {
        const rows = data as BarisRingkasan[];
        return (
            <Table head={<><th>Kumpulan</th><th className="text-right">Kes</th><th className="text-right">Aktif</th><th className="hidden sm:table-cell text-right">Nilai tuntutan</th><th className="hidden sm:table-cell text-right">Kos guaman</th></>}>
                {rows.map((r) => (
                    <tr key={r.kod || r.label}>
                        <td>{r.label}</td>
                        <td className="text-right">{r.bil}</td>
                        <td className="text-right">{r.aktif}</td>
                        <td className="hidden sm:table-cell text-right whitespace-nowrap">{rm(r.nilai_tuntutan)}</td>
                        <td className="hidden sm:table-cell text-right whitespace-nowrap">{rm(r.kos)}</td>
                    </tr>
                ))}
            </Table>
        );
    }
    if (jenis === 'senarai' || jenis === 'tanpa_tarikh') {
        const rows = data as Kes[];
        return (
            <Table head={<><th>Kes</th><th className="hidden md:table-cell">Mahkamah</th><th>Status</th><th className="hidden sm:table-cell">Prosiding berikutnya</th><th className="hidden lg:table-cell">Pegawai</th></>}>
                {rows.map((k) => (
                    <tr key={k.id}>
                        <td>
                            <Link href={`/admin/kes/kes/${k.id}`} className="text-txt-primary hover:underline">
                                {k.no_rujukan}
                            </Link>
                            <p className="text-body-xs text-txt-black-500">{k.jenis_nama} · {k.no_kes}</p>
                        </td>
                        <td className="hidden md:table-cell">{k.mahkamah}</td>
                        <td>{k.status_nama}</td>
                        <td className="hidden sm:table-cell">{tarikh(k.tarikh_prosiding_berikutnya)}</td>
                        <td className="hidden lg:table-cell">{k.pegawai?.nama ?? '—'}</td>
                    </tr>
                ))}
            </Table>
        );
    }
    if (jenis === 'kos') {
        const d = data as { mengikut_tahun: Array<Record<string, string>>; mengikut_kes: Array<Record<string, string>>; jumlah: string };
        return (
            <div className="space-y-4">
                <p className="text-body-sm">Jumlah keseluruhan: <strong>{rm(d.jumlah)}</strong></p>
                <Table head={<><th>Tahun</th><th className="text-right">Fi peguam</th><th className="hidden sm:table-cell text-right">Kos mahkamah</th><th className="hidden sm:table-cell text-right">Kos berkaitan</th><th className="text-right">Jumlah</th></>}>
                    {d.mengikut_tahun.map((r) => (
                        <tr key={r.tahun}>
                            <td>{r.tahun}</td>
                            <td className="text-right">{rm(r.fi_peguam)}</td>
                            <td className="hidden sm:table-cell text-right">{rm(r.kos_mahkamah)}</td>
                            <td className="hidden sm:table-cell text-right">{rm(r.kos_berkaitan)}</td>
                            <td className="text-right font-medium">{rm(r.jumlah)}</td>
                        </tr>
                    ))}
                </Table>
            </div>
        );
    }
    if (jenis === 'pendedahan') {
        const d = data as { bil_kes_aktif: number; bil_lot_terlibat: number; nilai_lot: { boleh_lihat: boolean; nilai: string | null }; nilai_tuntutan_aktif: string; kos_mengikut_tahun: Array<Record<string, string>>; trend_keputusan: Array<Record<string, string | number>> };
        return (
            <div className="space-y-4">
                <div className="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    {[
                        ['Kes aktif', String(d.bil_kes_aktif)],
                        ['Lot terlibat', String(d.bil_lot_terlibat)],
                        ['Nilai lot terlibat', d.nilai_lot.boleh_lihat ? rm(d.nilai_lot.nilai) : 'Rahsia penilaian'],
                        ['Nilai tuntutan aktif', rm(d.nilai_tuntutan_aktif)],
                    ].map(([l, v]) => (
                        <div key={l} className="rounded-md border border-otl-gray-200 p-3">
                            <p className="text-body-xs text-txt-black-500">{l}</p>
                            <p className="font-semibold text-txt-black-900">{v}</p>
                        </div>
                    ))}
                </div>
                <Table head={<><th>Tahun</th><th>Jenis kes</th><th>Keputusan</th><th className="text-right">Bil.</th></>}>
                    {d.trend_keputusan.map((r, i) => (
                        <tr key={i}>
                            <td>{r.tahun}</td>
                            <td>{r.jenis_kes_nama}</td>
                            <td>{r.jenis_keputusan_nama}</td>
                            <td className="text-right">{r.bil}</td>
                        </tr>
                    ))}
                </Table>
            </div>
        );
    }
    const rows = data as Array<Record<string, string | number | null>>;
    if (!rows.length) return <Kosong>Tiada data.</Kosong>;
    const cols = Object.keys(rows[0]).filter((c) => !['id', 'jenis_kes', 'jenis_keputusan'].includes(c));
    return (
        <Table head={<>{cols.map((c) => <th key={c}>{c.replaceAll('_', ' ')}</th>)}</>}>
            {rows.map((r, i) => (
                <tr key={i}>
                    {cols.map((c) => (
                        <td key={c}>{r[c] ?? '—'}</td>
                    ))}
                </tr>
            ))}
        </Table>
    );
}

/** SRS-KES-21..24: filterable / groupable reports with PDF & Excel export (every generation and export audited). */
function Laporan() {
    const params = useSearchParams();
    const can = useCan();
    const { data: ref } = useRujukan();
    const [jenis, setJenis] = useState(params.get('jenis') ?? 'ringkasan');
    const [f, setF] = useState<Record<string, string>>({ kumpul: params.get('kumpul') ?? 'status', status: '', jenis: '', mahkamah: '', kod_daerah: '', kategori_tanah: '', id_pegawai: '', peguam: '', dari: '', hingga: '' });
    const set = (k: string) => (v: string) => setF((x) => ({ ...x, [k]: v }));
    const { data, error, isLoading } = useQuery({ queryKey: ['kes', 'laporan', jenis, f], queryFn: () => kesApi.laporan<Data>(jenis, f).then((r) => r.data) });
    const eksport = (format: string) => muatTurunFail(`/kes/laporan/${jenis}/eksport`, { ...f, format }, `laporan-kes.${format}`).catch((e) => toast.error(ralat(e)));

    return (
        <PageContainer>
            <Card title="Laporan kes">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Field label="Laporan" htmlFor="l-j">
                        <Select id="l-j" value={jenis} onChange={setJenis} options={JENIS} />
                    </Field>
                    {jenis === 'ringkasan' && (
                        <Field label="Kumpulkan mengikut" htmlFor="l-k">
                            <Select id="l-k" value={f.kumpul} onChange={set('kumpul')} options={KUMPUL} />
                        </Field>
                    )}
                    <Field label="Status" htmlFor="l-s">
                        <Select id="l-s" value={f.status} onChange={set('status')} options={ref?.status ?? []} empty="Semua" />
                    </Field>
                    <Field label="Jenis kes" htmlFor="l-je">
                        <Select id="l-je" value={f.jenis} onChange={set('jenis')} options={ref?.jenis ?? []} empty="Semua" />
                    </Field>
                    <Field label="Mahkamah" htmlFor="l-m">
                        <input id="l-m" className={inputClass} value={f.mahkamah} onChange={(e) => set('mahkamah')(e.target.value)} />
                    </Field>
                    <Field label="Daerah" htmlFor="l-d">
                        <Select id="l-d" value={f.kod_daerah} onChange={set('kod_daerah')} options={ref?.daerah ?? []} empty="Semua" />
                    </Field>
                    <Field label="Kategori tanah" htmlFor="l-kt">
                        <input id="l-kt" className={inputClass} value={f.kategori_tanah} onChange={(e) => set('kategori_tanah')(e.target.value.toUpperCase())} placeholder="cth. BANGUNAN" />
                    </Field>
                    <Field label="Pegawai" htmlFor="l-p">
                        <Select id="l-p" value={f.id_pegawai} onChange={set('id_pegawai')} options={(ref?.pegawai ?? []).map((u) => ({ kod: String(u.id), nama: u.nama }))} empty="Semua" />
                    </Field>
                    <Field label="Peguam / firma" htmlFor="l-g">
                        <input id="l-g" className={inputClass} value={f.peguam} onChange={(e) => set('peguam')(e.target.value)} />
                    </Field>
                    <Field label="Tarikh pemfailan dari" htmlFor="l-dr">
                        <input id="l-dr" type="date" className={inputClass} value={f.dari} onChange={(e) => set('dari')(e.target.value)} />
                    </Field>
                    <Field label="Hingga" htmlFor="l-hg">
                        <input id="l-hg" type="date" className={inputClass} value={f.hingga} onChange={(e) => set('hingga')(e.target.value)} />
                    </Field>
                </div>
                {can('kes.laporan.eksport') && (
                    <div className="flex flex-wrap gap-2">
                        <Button size="sm" variant="secondary" onClick={() => eksport('pdf')}>
                            Eksport PDF
                        </Button>
                        <Button size="sm" variant="secondary" onClick={() => eksport('xlsx')}>
                            Eksport Excel
                        </Button>
                        <Button size="sm" variant="secondary" onClick={() => eksport('csv')}>
                            CSV
                        </Button>
                    </div>
                )}
            </Card>
            <Card>
                <div className="overflow-x-auto">{error ? <RalatAkses error={error} /> : isLoading || !data ? <p className="text-body-sm text-txt-black-500">Memuatkan…</p> : <Kandungan jenis={jenis} data={data} />}</div>
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
