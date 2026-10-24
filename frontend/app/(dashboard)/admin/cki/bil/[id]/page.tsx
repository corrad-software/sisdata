'use client';

import { use, useState } from 'react';
import Link from 'next/link';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { DocumentAttachments } from '@/components/dms/DocumentAttachments';
import { ckiApi, MEDAN_LABEL, ralat, tarikh, useCan } from '@/lib/cki';
import { Card, Field, InfoGrid, inputClass, Kosong, Sintetik, Status } from '../../_components/common';
import { MohonBayaran, TransaksiKad } from './Transaksi';

const MEDAN_PELARASAN = ['no_bil', 'amaun_pokok', 'amaun_denda', 'tarikh_bil', 'tarikh_akhir'];

/** One tax bill: details, payments (request → approval → execution → receipt → completion), ledger, receipts, corrections, conflicts, documents. */
export default function BilPage({ params }: { params: Promise<{ id: string }> }) {
    const { id } = use(params);
    const can = useCan();
    const qc = useQueryClient();
    const { data: b, isLoading, error } = useQuery({ queryKey: ['cki', 'bil', id], queryFn: () => ckiApi.bilSatu(id) });
    const [adj, setAdj] = useState({ medan: 'amaun_pokok', nilai: '', justifikasi: '' });

    if (isLoading) return <PageContainer>Memuatkan…</PageContainer>;
    if (error || !b)
        return (
            <PageContainer>
                <p className="text-txt-danger">{error ? ralat(error) : 'Tidak dijumpai.'}</p>
            </PageContainer>
        );

    async function laras(e: React.FormEvent) {
        e.preventDefault();
        try {
            const r = await ckiApi.pelarasan(id, adj);
            toast.success(r.message ?? 'Disimpan.');
            setAdj({ medan: 'amaun_pokok', nilai: '', justifikasi: '' });
            qc.invalidateQueries({ queryKey: ['cki', 'bil', id] });
        } catch (err) {
            toast.error(ralat(err));
        }
    }

    const konflikMenunggu = b.konflik.filter((k) => k.status_resolusi === 'menunggu');

    return (
        <PageContainer>
            <Card
                title={
                    <span className="flex flex-wrap items-center gap-2">
                        {b.no_bil} <Status kod={b.status_bayaran} nama={b.status_bayaran_nama} /> <Sintetik show={b.sintetik} />
                    </span>
                }
                actions={
                    <Link href={`/admin/cki/lot/${b.lot_id}`} className="text-body-sm text-txt-primary hover:underline">
                        Garis masa lot
                    </Link>
                }
            >
                <InfoGrid
                    items={[
                        ['Lot', <Link key="lot" href={`/admin/pro/lot/${b.lot_id}?tab=kewangan`} className="text-txt-primary hover:underline">{b.no_lot ?? b.lot_id}</Link>],
                        ['Jenis cukai', b.jenis_cukai_nama],
                        ['Pihak pengeluar', b.pihak_pengeluar_nama],
                        ['Agensi', b.obligasi?.nama_agensi ?? b.kod_agensi],
                        ['No. akaun cukai', b.obligasi?.no_akaun_cukai],
                        ['Tahun taksiran', b.tahun_taksiran],
                        ['Amaun pokok', b.amaun_pokok.rm],
                        ['Denda', b.amaun_denda.rm],
                        ['Jumlah', b.jumlah.rm],
                        ['Telah dibayar', b.amaun_dibayar.rm],
                        ['Baki', b.baki.rm],
                        ['Tarikh bil', tarikh(b.tarikh_bil)],
                        ['Tarikh akhir', tarikh(b.tarikh_akhir)],
                        ['Tarikh bayar', tarikh(b.tarikh_bayar)],
                        ['No. resit', b.no_resit],
                        ['Sumber rekod', b.sumber_rekod_nama],
                        ['Liputan API', b.obligasi ? (b.obligasi.status_liputan_api === 'dalam' ? `Dalam liputan (${b.obligasi.mod_agensi === 'sandaran' ? 'mod sandaran' : 'API'})` : 'Luar liputan') : '—'],
                        ['Versi', b.versi],
                    ]}
                />
                {konflikMenunggu.length > 0 && (
                    <Alert type="warning">
                        Rekod ini berbeza dengan API berpusat.{' '}
                        <Link href="/admin/cki/konflik" className="underline">
                            Sahkan konflik penyegerakan
                        </Link>
                        .
                    </Alert>
                )}
            </Card>

            <Card title="Bayaran">
                {can('cki.bayaran.cipta') && <MohonBayaran bil={b} />}
                {b.transaksi.length === 0 ? (
                    <Kosong>Belum ada permohonan bayaran.</Kosong>
                ) : (
                    b.transaksi.map((t) => <TransaksiKad key={t.id} t={t} bilId={b.id} resitAda={b.resit.some((r) => r.id_transaksi === t.id)} />)
                )}
            </Card>

            <Card title="Resit">
                <Table head={<><th>No. resit</th><th>Amaun</th><th className="hidden sm:table-cell">Tarikh</th><th>Sumber</th></>}>
                    {b.resit.length === 0 ? (
                        <Kosong colSpan={4}>Tiada resit.</Kosong>
                    ) : (
                        b.resit.map((r) => (
                            <tr key={r.id}>
                                <td className="break-words">{r.no_resit}</td>
                                <td className="whitespace-nowrap">{r.amaun.rm}</td>
                                <td className="hidden sm:table-cell">{tarikh(r.tarikh_resit)}</td>
                                <td>{r.sumber === 'api' ? 'Elektronik (API)' : 'Muat naik manual'}</td>
                            </tr>
                        ))
                    )}
                </Table>
            </Card>

            <Card title="Lejar cukai">
                <Table head={<><th>Tarikh</th><th>Jenis</th><th>Debit</th><th>Kredit</th><th className="hidden md:table-cell">Keterangan</th></>}>
                    {b.lejar.map((l) => (
                        <tr key={l.id}>
                            <td className="whitespace-nowrap">{tarikh(l.tarikh)}</td>
                            <td>{l.jenis}</td>
                            <td className="whitespace-nowrap">{l.debit.nilai !== '0.00' ? l.debit.rm : ''}</td>
                            <td className="whitespace-nowrap">{l.kredit.nilai !== '0.00' ? l.kredit.rm : ''}</td>
                            <td className="hidden md:table-cell break-words">{l.keterangan}</td>
                        </tr>
                    ))}
                </Table>
            </Card>

            <Card title="Pelarasan (rekod tidak boleh dipadam — pembetulan berjustifikasi)">
                {can('cki.bil.kemas_kini') && (
                    <form onSubmit={laras} className="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                        <Field label="Medan" htmlFor="a-medan">
                            <select id="a-medan" className={inputClass} value={adj.medan} onChange={(e) => setAdj((p) => ({ ...p, medan: e.target.value }))}>
                                {MEDAN_PELARASAN.map((m) => (
                                    <option key={m} value={m}>
                                        {MEDAN_LABEL[m]}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Nilai baharu" htmlFor="a-nilai">
                            <input id="a-nilai" className={inputClass} type={adj.medan.startsWith('tarikh') ? 'date' : 'text'} value={adj.nilai} onChange={(e) => setAdj((p) => ({ ...p, nilai: e.target.value }))} required />
                        </Field>
                        <Field label="Justifikasi" htmlFor="a-just">
                            <input id="a-just" className={inputClass} value={adj.justifikasi} onChange={(e) => setAdj((p) => ({ ...p, justifikasi: e.target.value }))} minLength={10} required />
                        </Field>
                        <Button type="submit" variant="outline">
                            Simpan pelarasan
                        </Button>
                    </form>
                )}
                {b.pelarasan.length > 0 && (
                    <ul className="space-y-1 text-body-sm">
                        {b.pelarasan.map((p) => (
                            <li key={p.id}>
                                {MEDAN_LABEL[p.medan] ?? p.medan}: {p.nilai_lama ?? '—'} → {p.nilai_baharu} — <span className="text-txt-black-500">{p.justifikasi}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            <DocumentAttachments ownerType="cukai" ownerId={b.id} sourceModule="cki" ownerLink={`/admin/cki/bil/${b.id}`} title="Bil, baucar, resit dan dokumen kelulusan" readOnly={!can('cki.bil.kemas_kini')} />
        </PageContainer>
    );
}
