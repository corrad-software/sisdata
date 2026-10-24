'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { AnchorButton } from '@/components/ui/Button';
import { ralat, rm, tarikh, tsuApi, useCan, useRujukan } from '@/lib/tsu';
import { Card, Field, inputClass, Kosong, Ralat, Select, Status } from '../_components/common';

/** SRS-TSU-26/27: works report (status, district, surveyor, cost, duration) and management report. */
export default function Laporan() {
    const can = useCan();
    const { data: ref } = useRujukan();
    const [f, setF] = useState<Record<string, string>>({ jenis_urusan: '', status: '', kod_daerah: '', tahun: '', ukur: '' });
    const set = (k: string) => (v: string) => setF((x) => ({ ...x, [k]: v }));
    const { data: kerja, error } = useQuery({ queryKey: ['tsu', 'kerja', f], queryFn: () => tsuApi.kerja(f) });
    const { data: r } = useQuery({ queryKey: ['tsu', 'ringkasan', f], queryFn: () => tsuApi.ringkasan({ jenis_urusan: f.jenis_urusan, kod_daerah: f.kod_daerah, tahun: f.tahun }) });
    const kategori = ref?.kategori_kos ?? [];

    return (
        <PageContainer>
            <Card title="Penapis">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <Field label="Jenis urusan" htmlFor="l-j"><Select id="l-j" value={f.jenis_urusan} onChange={set('jenis_urusan')} options={ref?.jenis_urusan ?? []} empty="Semua" /></Field>
                    <Field label="Status" htmlFor="l-s"><Select id="l-s" value={f.status} onChange={set('status')} options={ref?.status_urusan ?? []} empty="Semua" /></Field>
                    <Field label="Kod daerah" htmlFor="l-d"><input id="l-d" maxLength={4} className={inputClass} value={f.kod_daerah} onChange={(e) => set('kod_daerah')(e.target.value)} placeholder="cth. 1008" /></Field>
                    <Field label="Tahun mula" htmlFor="l-t"><input id="l-t" type="number" className={inputClass} value={f.tahun} onChange={(e) => set('tahun')(e.target.value)} /></Field>
                    <Field label="Kerja ukur / demarkasi" htmlFor="l-u"><Select id="l-u" value={f.ukur} onChange={set('ukur')} options={[{ kod: '1', nama: 'Hanya urusan dengan lantikan jurukur' }]} empty="Semua" /></Field>
                </div>
            </Card>

            <Card title="Laporan kerja pelupusan, tukar syarat, ukur dan demarkasi" actions={can('tsu.laporan.eksport') && <AnchorButton size="sm" variant="secondary" href={tsuApi.eksportKerjaUrl(f)}>Eksport CSV</AnchorButton>}>
                {error && <Ralat>{ralat(error)}</Ralat>}
                {!kerja ? (
                    <Kosong>Memuatkan…</Kosong>
                ) : kerja.length === 0 ? (
                    <Kosong>Tiada data.</Kosong>
                ) : (
                    <Table head={<><th>No. rujukan</th><th>Jenis</th><th>Daerah</th><th>Status</th><th>Peringkat</th><th>Mula</th><th>Tempoh</th><th>Jurukur</th><th>Kos</th></>}>
                        {kerja.slice(0, 200).map((k) => (
                            <tr key={k.id}>
                                <td><Link className="text-txt-primary hover:underline" href={`/admin/tsu/urusan/${k.id}`}>{k.no_rujukan_mais}</Link></td>
                                <td>{k.jenis_nama}</td>
                                <td>{k.kod_daerah}</td>
                                <td><Status kod={k.status} nama={k.status_nama} /></td>
                                <td>{k.peringkat_semasa ?? '—'}</td>
                                <td>{tarikh(k.tarikh_mula)}</td>
                                <td>{k.tempoh_hari} hari {k.lewat_sasaran && <Badge variant="warning">Lewat</Badge>}</td>
                                <td>{k.jurukur ?? '—'}</td>
                                <td className="whitespace-nowrap">{rm(k.jumlah_kos)}</td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>

            {r && (
                <>
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <Card title="Urusan aktif mengikut daerah">
                            {r.aktif_mengikut_daerah.length === 0 ? <Kosong>Tiada.</Kosong> : (
                                <ul className="space-y-1 text-body-sm">{r.aktif_mengikut_daerah.map((d) => <li key={d.kod} className="flex justify-between"><span>{d.nama}</span><strong>{d.nilai}</strong></li>)}</ul>
                            )}
                        </Card>
                        <Card title="Urusan aktif mengikut peringkat semasa">
                            {r.aktif_mengikut_peringkat.length === 0 ? <Kosong>Tiada.</Kosong> : (
                                <ul className="space-y-1 text-body-sm">{r.aktif_mengikut_peringkat.map((d) => <li key={`${d.jenis_urusan}-${d.peringkat}`} className="flex justify-between gap-3"><span>{d.jenis_nama} — {d.peringkat}</span><strong>{d.nilai}</strong></li>)}</ul>
                            )}
                        </Card>
                    </div>
                    <Card title="Jumlah kos urusan tahunan mengikut kategori">
                        {r.kos_tahunan.length === 0 ? <Kosong>Tiada kos.</Kosong> : (
                            <Table head={<><th>Tahun</th>{kategori.map((k) => <th key={k.kod}>{k.nama}</th>)}<th>Jumlah</th></>}>
                                {r.kos_tahunan.map((y) => (
                                    <tr key={String(y.tahun)}>
                                        <td>{y.tahun}</td>
                                        {kategori.map((k) => <td key={k.kod} className="whitespace-nowrap">{rm(String(y[k.kod] ?? '0.00'))}</td>)}
                                        <td className="whitespace-nowrap font-semibold">{rm(String(y.jumlah))}</td>
                                    </tr>
                                ))}
                            </Table>
                        )}
                    </Card>
                    <Card title="Prestasi jurukur (tempoh siap dan kos)">
                        {r.prestasi_jurukur.length === 0 ? <Kosong>Tiada lantikan.</Kosong> : (
                            <Table head={<><th>Firma</th><th>Lantikan</th><th>Siap</th><th>Purata hari sebenar</th><th>Purata hari sasaran</th><th>Lewat</th><th>Nilai kontrak</th></>}>
                                {r.prestasi_jurukur.map((j) => (
                                    <tr key={j.id}><td>{j.nama_firma}</td><td>{j.bilangan}</td><td>{j.siap}</td><td>{j.purata_hari_sebenar ?? '—'}</td><td>{j.purata_hari_sasaran}</td><td>{j.lewat}</td><td className="whitespace-nowrap">{rm(j.jumlah_kontrak)}</td></tr>
                                ))}
                            </Table>
                        )}
                    </Card>
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <Card title="Sejarah pecahan dan penyatuan lot">
                            {r.sejarah_pecahan.length === 0 ? <Kosong>Tiada.</Kosong> : (
                                <ul className="space-y-1 text-body-sm">{r.sejarah_pecahan.map((s) => <li key={s.id}><Link className="text-txt-primary hover:underline" href={`/admin/pro/lot/${s.id_lot_asal}`}>{s.lot_asal}</Link> → <Link className="text-txt-primary hover:underline" href={`/admin/pro/lot/${s.id_lot_baharu}`}>{s.lot_baharu}</Link> ({s.jenis_perubahan}, {tarikh(s.tarikh)})</li>)}</ul>
                            )}
                        </Card>
                        <Card title="Rekod dipindahkan antara lot">
                            {r.rekod_dipindah.length === 0 ? <Kosong>Tiada.</Kosong> : (
                                <ul className="space-y-1 text-body-sm">{r.rekod_dipindah.map((s) => <li key={s.id}>{s.label_rekod ?? s.jenis_rekod}: {s.lot_asal} → {s.lot_baharu}</li>)}</ul>
                            )}
                        </Card>
                    </div>
                </>
            )}
        </PageContainer>
    );
}
