'use client';

import { Suspense, useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { aduApi, muatTurun, ralat, tarikhMasa, useCan, useRujukan } from '@/lib/adu';
import { Card, Field, inputClass, Select } from '../_components/common';

type Row = Record<string, unknown>;

/** SRS-ADU-25/27 reports — columns per report (key, label, optional render). */
const LAPORAN: Record<string, { label: string; lajur: Array<[string, string]>; ikut?: boolean; perm?: string }> = {
    prestasi_sla: {
        label: 'Prestasi SLA',
        ikut: true,
        lajur: [
            ['label', 'Kumpulan'],
            ['jumlah', 'Jumlah'],
            ['dipenuhi', 'Dipenuhi'],
            ['tidak_dipenuhi', 'Tidak dipenuhi'],
            ['melepasi_terbuka', 'Terbuka melepasi SLA'],
            ['peratus_dipenuhi', '% dipenuhi'],
            ['peratus_respons_dalam_sla', '% respons dalam SLA'],
            ['purata_hari_selesai', 'Purata hari selesai'],
        ],
    },
    tertangguh: {
        label: 'Tertangguh & melepasi SLA',
        lajur: [
            ['no_rujukan_aduan', 'No. rujukan'],
            ['kategori', 'Kategori'],
            ['daerah', 'Daerah'],
            ['status', 'Status'],
            ['pegawai', 'Pegawai'],
            ['masa_sasaran_selesai', 'Sasaran selesai'],
            ['peringkat_eskalasi', 'Peringkat eskalasi'],
        ],
    },
    berulang: { label: 'Aduan berulang (lot)', lajur: [['no_lot', 'No. lot'], ['bilangan', 'Bilangan'], ['terbuka', 'Terbuka'], ['kategori', 'Kategori utama'], ['terakhir', 'Terakhir']] },
    kawasan_panas: { label: 'Kawasan panas (mukim)', lajur: [['mukim', 'Mukim'], ['daerah', 'Daerah'], ['jumlah', 'Jumlah'], ['terbuka', 'Terbuka'], ['kategori_utama', 'Kategori utama']] },
    trend: { label: 'Trend bulanan', lajur: [['bulan', 'Bulan'], ['diterima', 'Diterima'], ['ditutup', 'Ditutup']] },
    saluran: { label: 'Saluran penerimaan', lajur: [['label', 'Saluran'], ['jumlah', 'Jumlah'], ['aktif', 'Aktif'], ['ditutup', 'Ditutup']] },
    bukan_mais: { label: 'Bukan tanah MAIS', lajur: [['no_rujukan_aduan', 'No. rujukan'], ['tarikh_terima', 'Tarikh'], ['daerah', 'Daerah'], ['kategori', 'Kategori'], ['status', 'Status'], ['agensi', 'Agensi disyorkan'], ['jawapan_dihantar', 'Jawapan dihantar']] },
    resolusi: { label: 'Resolusi', lajur: [['label', 'Kategori resolusi'], ['jumlah', 'Jumlah']] },
    audit_capaian: { label: 'Audit capaian pengadu', perm: 'adu.audit_capaian.lihat', lajur: [['no_rujukan_aduan', 'No. rujukan'], ['tarikh_masa', 'Tarikh/masa'], ['pegawai', 'Pegawai'], ['medan', 'Medan'], ['tujuan', 'Tujuan'], ['asas', 'Asas'], ['alamat_ip', 'Alamat IP']] },
};

function sel(key: string, v: unknown, row: Row) {
    if (v === null || v === undefined || v === '') return '—';
    if (typeof v === 'boolean') return v ? 'Ya' : 'Tidak';
    if (key === 'no_rujukan_aduan' && typeof row.id === 'string' && typeof v === 'string' && !('tujuan' in row)) {
        return (
            <Link href={`/admin/adu/aduan/${row.id}`} className="text-txt-primary hover:underline">
                {v}
            </Link>
        );
    }
    if (key === 'no_lot' && typeof row.pautan_lot === 'string') {
        return (
            <Link href={row.pautan_lot} className="text-txt-primary hover:underline">
                {String(v)}
            </Link>
        );
    }
    if (['tarikh_terima', 'tarikh_masa', 'terakhir', 'masa_sasaran_selesai'].includes(key)) return tarikhMasa(String(v));
    return typeof v === 'number' ? v.toLocaleString('ms-MY') : String(v);
}

function Laporan() {
    const params = useSearchParams();
    const can = useCan();
    const { data: ref } = useRujukan();
    const [jenis, setJenis] = useState(params.get('tab') === 'sla' ? 'prestasi_sla' : (params.get('tab') ?? 'prestasi_sla'));
    const [f, setF] = useState({ dari: '', hingga: '', kod_daerah: '', kategori: '', saluran: '', ikut: 'kategori' });
    const set = (k: keyof typeof f) => (v: string) => setF((x) => ({ ...x, [k]: v }));
    const def = LAPORAN[jenis] ?? LAPORAN.prestasi_sla;
    const q = { ...f, ikut: def.ikut ? f.ikut : '' };
    const { data, isLoading, error } = useQuery({ queryKey: ['adu', 'laporan', jenis, q], queryFn: () => aduApi.laporan<Row>(jenis, q).then((r) => r.data) });
    const pilihan = Object.entries(LAPORAN)
        .filter(([, d]) => !d.perm || can(d.perm))
        .map(([kod, d]) => ({ kod, nama: d.label }));

    return (
        <PageContainer>
            <Card>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Field label="Laporan" htmlFor="l-j">
                        <Select id="l-j" value={jenis} onChange={setJenis} options={pilihan} />
                    </Field>
                    {def.ikut && (
                        <Field label="Mengikut" htmlFor="l-ikut">
                            <Select
                                id="l-ikut"
                                value={f.ikut}
                                onChange={set('ikut')}
                                options={[
                                    { kod: 'kategori', nama: 'Kategori' },
                                    { kod: 'daerah', nama: 'Daerah' },
                                    { kod: 'pegawai', nama: 'Pegawai' },
                                ]}
                            />
                        </Field>
                    )}
                    <Field label="Dari" htmlFor="l-dari">
                        <input id="l-dari" type="date" className={inputClass} value={f.dari} onChange={(e) => set('dari')(e.target.value)} />
                    </Field>
                    <Field label="Hingga" htmlFor="l-hingga">
                        <input id="l-hingga" type="date" className={inputClass} value={f.hingga} onChange={(e) => set('hingga')(e.target.value)} />
                    </Field>
                    <Field label="Daerah" htmlFor="l-d">
                        <Select id="l-d" value={f.kod_daerah} onChange={set('kod_daerah')} options={ref?.daerah ?? []} empty="Semua" />
                    </Field>
                    <Field label="Kategori" htmlFor="l-k">
                        <Select id="l-k" value={f.kategori} onChange={set('kategori')} options={ref?.kategori ?? []} empty="Semua" />
                    </Field>
                    <Field label="Saluran" htmlFor="l-s">
                        <Select id="l-s" value={f.saluran} onChange={set('saluran')} options={ref?.saluran ?? []} empty="Semua" />
                    </Field>
                </div>
            </Card>

            <Card
                title={def.label}
                actions={
                    can('adu.aduan.eksport') &&
                    (['xlsx', 'csv', 'pdf'] as const).map((fmt) => (
                        <Button key={fmt} size="sm" variant="outline" onClick={() => muatTurun(`/adu/laporan/${jenis}/eksport`, { ...q, format: fmt }, `aduan-${jenis}.${fmt}`).catch((e) => toast.error(ralat(e)))}>
                            {fmt === 'xlsx' ? 'Excel' : fmt.toUpperCase()}
                        </Button>
                    ))
                }
            >
                <p className="text-body-sm text-txt-black-500" aria-live="polite">
                    {error ? <span className="text-txt-danger">{ralat(error)}</span> : data ? `${data.length} baris` : 'Memuatkan…'}
                </p>
                <div className="overflow-x-auto">
                    <Table
                        head={
                            <>
                                {def.lajur.map(([k, l]) => (
                                    <th key={k}>{l}</th>
                                ))}
                            </>
                        }
                    >
                        {isLoading ? (
                            <tr>
                                <td colSpan={def.lajur.length} className="py-6 text-center text-txt-black-500">
                                    Memuatkan…
                                </td>
                            </tr>
                        ) : (data ?? []).length === 0 ? (
                            <tr>
                                <td colSpan={def.lajur.length} className="py-6 text-center text-txt-black-500">
                                    Tiada data.
                                </td>
                            </tr>
                        ) : (
                            data?.map((row, i) => (
                                <tr key={String(row.id ?? row.kod ?? row.lot_id ?? row.kod_mukim ?? row.bulan ?? i)}>
                                    {def.lajur.map(([k]) => (
                                        <td key={k} className="max-w-xs break-words">
                                            {sel(k, row[k], row)}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </Table>
                </div>
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
