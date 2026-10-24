'use client';

import { Suspense, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { HorizontalBarChart } from '@/components/ui/charts/HorizontalBarChart';
import { StackedBarChart } from '@/components/ui/charts/StackedBarChart';
import { muatTurun, ptnApi, ralat, useCan, useRujukan, type KodWarna } from '@/lib/ptn';
import { Card, Field, inputClass, KodWarnaBadge, Kosong, Penapis, Select, WARNA_BG } from '../_components/common';

type Row = Record<string, string | number | null>;
type Kump = { kod: string; label: string; jumlah: number; terbuka?: number; purata_hari_selesai?: number | null };

const LAPORAN: Array<{ kod: string; nama: string }> = [
    { kod: 'isu', nama: 'Isu pemantauan' },
    { kod: 'prestasi', nama: 'Prestasi pemantauan' },
    { kod: 'tindakan', nama: 'Tindakan tertunggak' },
    { kod: 'risiko', nama: 'Risk rating portfolio' },
    { kod: 'pencerobohan', nama: 'Penemuan pencerobohan' },
    { kod: 'penyelenggaraan', nama: 'Keperluan penyelenggaraan' },
];

function Kumpulan({ title, rows }: { title: string; rows: Kump[] }) {
    return (
        <Card title={title}>
            {rows.length === 0 ? <Kosong>Tiada data.</Kosong> : <HorizontalBarChart data={rows.map((r) => ({ label: `${r.label ?? r.kod} (${r.terbuka ?? 0} terbuka)`, value: r.jumlah }))} />}
        </Card>
    );
}

function Senarai({ rows, cols }: { rows: Row[]; cols: Array<[string, string]> }) {
    if (rows.length === 0) return <Kosong>Tiada rekod.</Kosong>;
    return (
        <Table head={<>{cols.map(([, h]) => <th key={h}>{h}</th>)}</>}>
            {rows.slice(0, 200).map((r, i) => (
                <tr key={i}>{cols.map(([k]) => <td key={k}>{k === 'kod_warna' ? <KodWarnaBadge warna={r[k] as KodWarna} /> : (r[k] ?? '—')}</td>)}</tr>
            ))}
        </Table>
    );
}

/** SRS-PTN-22/23 and the module's report list, with CSV/PDF export (audited). */
function LaporanPage() {
    const sp = useSearchParams();
    const can = useCan();
    const { data: ref } = useRujukan();
    const [kod, setKod] = useState(sp.get('laporan') ?? 'isu');
    const [f, setF] = useState({ kod_daerah: '', dari: '', hingga: '', tahun: String(new Date().getFullYear()) });
    const set = (k: keyof typeof f) => (v: string) => setF((x) => ({ ...x, [k]: v }));
    // eslint-disable-next-line @typescript-eslint/no-explicit-any -- each report has its own shape
    const { data, error, isLoading } = useQuery({ queryKey: ['ptn', 'laporan', kod, f], queryFn: () => ptnApi.laporan<any>(kod, f) });
    const eksport = (format: 'csv' | 'pdf') => muatTurun(ptnApi.eksportUrl(kod, f, format), `ptn-${kod}.${format}`).catch((e) => toast.error(ralat(e)));

    return (
        <PageContainer>
            <div className="flex flex-wrap gap-2" role="tablist" aria-label="Jenis laporan">
                {LAPORAN.map((l) => (
                    <Button key={l.kod} role="tab" aria-selected={kod === l.kod} size="sm" variant={kod === l.kod ? 'primary' : 'secondary'} onClick={() => setKod(l.kod)}>{l.nama}</Button>
                ))}
            </div>
            <Penapis>
                <Field label="Daerah" htmlFor="r-d"><Select id="r-d" value={f.kod_daerah} onChange={set('kod_daerah')} options={ref?.daerah ?? []} empty="Semua daerah" /></Field>
                {kod === 'prestasi' ? (
                    <Field label="Tahun" htmlFor="r-t"><input id="r-t" type="number" className={inputClass} value={f.tahun} onChange={(e) => set('tahun')(e.target.value)} /></Field>
                ) : (
                    <>
                        <Field label="Dari" htmlFor="r-dari"><input id="r-dari" type="date" className={inputClass} value={f.dari} onChange={(e) => set('dari')(e.target.value)} /></Field>
                        <Field label="Hingga" htmlFor="r-hingga"><input id="r-hingga" type="date" className={inputClass} value={f.hingga} onChange={(e) => set('hingga')(e.target.value)} /></Field>
                    </>
                )}
                {can('ptn.laporan.eksport') && (
                    <div className="flex items-end gap-2"><Button size="sm" variant="secondary" onClick={() => eksport('csv')}>Eksport CSV</Button><Button size="sm" variant="secondary" onClick={() => eksport('pdf')}>Eksport PDF</Button></div>
                )}
            </Penapis>
            {error && <p className="text-body-sm text-txt-danger">{ralat(error)}</p>}
            {isLoading && <Kosong>Memuatkan…</Kosong>}
            {data && kod === 'isu' && (
                <>
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <Kumpulan title="Mengikut daerah" rows={data.mengikut_daerah} />
                        <Kumpulan title="Mengikut kategori" rows={data.mengikut_kategori} />
                        <Kumpulan title="Mengikut status" rows={data.mengikut_status} />
                        <Kumpulan title="Mengikut pegawai bertanggungjawab" rows={data.mengikut_pegawai} />
                    </div>
                    <Card title="Tempoh penyelesaian mengikut kategori">
                        <Senarai rows={data.mengikut_kategori} cols={[['label', 'Kategori'], ['jumlah', 'Jumlah'], ['terbuka', 'Terbuka'], ['purata_hari_selesai', 'Purata hari selesai']]} />
                    </Card>
                    <Card title="Senarai isu"><Senarai rows={data.senarai} cols={[['no_rujukan', 'No.'], ['no_lot', 'Lot'], ['daerah', 'Daerah'], ['kategori', 'Kategori'], ['tahap', 'Tahap'], ['status', 'Status'], ['tarikh_dibuka', 'Dibuka'], ['tempoh_hari', 'Tempoh (hari)']]} /></Card>
                </>
            )}
            {data && kod === 'prestasi' && (
                <>
                    <Card title={`Prestasi ${data.tahun}`}>
                        <Senarai rows={[
                            { p: 'Lawatan ikut jadual', n: data.peratus_ikut_jadual === null ? '—' : `${data.peratus_ikut_jadual}% (${data.bil_ikut_jadual}/${data.bil_perlu_setakat_ini})` },
                            { p: 'Lot dilawat', n: data.peratus_lot_dilawat === null ? '—' : `${data.peratus_lot_dilawat}% (${data.bil_lot_dilawat}/${data.bil_lot_dijadual})` },
                            { p: 'Purata hari antara lawatan', n: data.purata_hari_antara_lawatan ?? '—' },
                            { p: 'Lawatan tertunggak', n: data.bil_tertunggak },
                            { p: 'Jumlah jadual', n: data.bil_jadual },
                        ]} cols={[['p', 'Petunjuk'], ['n', 'Nilai']]} />
                    </Card>
                    <Kumpulan title="Taburan penemuan mengikut kategori" rows={data.penemuan_mengikut_kategori} />
                    <Card title="Lawatan tertunggak"><Senarai rows={data.tertunggak} cols={[['no_lot', 'Lot'], ['daerah', 'Daerah'], ['tarikh_sasaran', 'Tarikh sasaran'], ['hari_lewat', 'Hari lewat'], ['keutamaan', 'Keutamaan'], ['pegawai', 'Pegawai']]} /></Card>
                </>
            )}
            {data && kod === 'tindakan' && (
                <Card title="Tindakan pembetulan belum selesai">
                    <div className="flex flex-wrap gap-2">{(['merah', 'kuning', 'hijau'] as KodWarna[]).map((w) => <KodWarnaBadge key={w} warna={w} label={`${data.mengikut_warna[w]} tindakan`} />)}</div>
                    <Senarai rows={data.senarai} cols={[['no_rujukan_isu', 'Isu'], ['langkah_tindakan', 'Langkah'], ['pegawai', 'Pegawai'], ['tarikh_sasaran', 'Sasaran'], ['hari_berbaki', 'Hari berbaki'], ['kod_warna', 'Kod warna']]} />
                </Card>
            )}
            {data && kod === 'risiko' && (
                <>
                    <Card title="Risk rating mengikut daerah">
                        <StackedBarChart data={data.mengikut_daerah.map((r: Row) => ({ label: String(r.label), values: { Hijau: Number(r.hijau), Kuning: Number(r.kuning), Merah: Number(r.merah) } }))} colors={{ Hijau: WARNA_BG.hijau, Kuning: WARNA_BG.kuning, Merah: WARNA_BG.merah }} />
                        <Senarai rows={data.mengikut_daerah} cols={[['label', 'Daerah'], ['jumlah', 'Lot'], ['hijau', 'Hijau'], ['kuning', 'Kuning'], ['merah', 'Merah'], ['purata', 'Purata skor']]} />
                    </Card>
                    <Card title="Mengikut kategori guna tanah"><Senarai rows={data.mengikut_kategori_lot} cols={[['label', 'Kategori'], ['jumlah', 'Lot'], ['hijau', 'Hijau'], ['kuning', 'Kuning'], ['merah', 'Merah'], ['purata', 'Purata skor']]} /></Card>
                    <Card title="Purata skor kategori risiko"><HorizontalBarChart data={data.purata_kategori_risiko.map((r: Row) => ({ label: String(r.label), value: Number(r.purata) }))} /></Card>
                </>
            )}
            {data && kod === 'pencerobohan' && (
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <Kumpulan title="Pencerobohan mengikut daerah" rows={data.mengikut_daerah} />
                    <Card title="Mengikut bulan"><HorizontalBarChart data={data.mengikut_bulan.map((r: Row) => ({ label: String(r.bulan), value: Number(r.jumlah) }))} /></Card>
                </div>
            )}
            {data && kod === 'penyelenggaraan' && (
                <Card title="Keperluan penyelenggaraan"><Senarai rows={data.senarai} cols={[['tarikh', 'Tarikh'], ['no_lot', 'Lot'], ['daerah', 'Daerah'], ['jenis', 'Jenis'], ['keterangan', 'Keterangan'], ['keutamaan', 'Keutamaan'], ['status', 'Status'], ['id_aset_fizikal', 'Aset (MOD-AST)']]} /></Card>
            )}
        </PageContainer>
    );
}

export default function Page() {
    return <Suspense><LaporanPage /></Suspense>;
}
