'use client';

import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { Suspense, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button, LinkButton } from '@/components/ui/Button';
import { Pagination } from '@/components/ui/Pagination';
import { astApi, cetakLabel, ralat, rm, tarikh, useCan, useRujukan } from '@/lib/ast';
import { Card, Field, inputClass, KondisiBadge, Kosong, Penapis, Select } from '../_components/common';

/** SRS-AST-01/11/24: the asset register — filters (category, condition, district, tag), batch QR label printing (SRS-AST-07). */
function Senarai() {
    const sp = useSearchParams();
    const can = useCan();
    const { data: ref } = useRujukan();
    const { data: tags } = useQuery({ queryKey: ['ast', 'tag'], queryFn: astApi.tag });
    const [f, setF] = useState({
        q: '', kod_daerah: '', id_kategori: sp.get('id_kategori') ?? '', status_kondisi: sp.get('status_kondisi') ?? '', tag: sp.get('tag') ?? '',
        lot_id: sp.get('lot_id') ?? '', tanpa_pemeriksaan: sp.get('tanpa_pemeriksaan') === '1', arkib_sahaja: false,
    });
    const [page, setPage] = useState(1);
    const [pilih, setPilih] = useState<Set<string>>(new Set());
    const [templat, setTemplat] = useState('sederhana');
    const [cetak, setCetak] = useState(false);
    const set = (k: keyof typeof f) => (v: string | boolean) => { setF((x) => ({ ...x, [k]: v })); setPage(1); };
    const { data, error } = useQuery({
        queryKey: ['ast', 'aset', f, page],
        queryFn: () => astApi.aset({ ...f, termasuk_arkib: f.arkib_sahaja, page, per_page: 25 }),
    });
    const toggle = (id: string) => setPilih((s) => { const n = new Set(s); if (n.has(id)) n.delete(id); else n.add(id); return n; });
    const semua = (data?.data ?? []).filter((a) => !a.status_arkib);
    const cetakSekarang = async () => {
        setCetak(true);
        try {
            await cetakLabel([...pilih], templat);
            toast.success(`${pilih.size} label dijana.`);
        } catch (e) {
            toast.error(ralat(e));
        } finally {
            setCetak(false);
        }
    };

    return (
        <PageContainer>
            <Penapis>
                <Field label="Carian" htmlFor="a-q"><input id="a-q" className={inputClass} value={f.q} onChange={(e) => set('q')(e.target.value)} placeholder="Nama, no. rujukan atau kod QR" /></Field>
                <Field label="Daerah" htmlFor="a-d"><Select id="a-d" value={f.kod_daerah} onChange={set('kod_daerah')} options={ref?.daerah ?? []} empty="Semua daerah" /></Field>
                <Field label="Kategori" htmlFor="a-k"><Select id="a-k" value={f.id_kategori} onChange={set('id_kategori')} options={(ref?.kategori ?? []).map((k) => ({ kod: String(k.id), nama: k.nama }))} empty="Semua kategori" /></Field>
                <Field label="Status kondisi" htmlFor="a-s"><Select id="a-s" value={f.status_kondisi} onChange={set('status_kondisi')} options={ref?.kondisi ?? []} empty="Semua" /></Field>
                <Field label="Tag" htmlFor="a-t"><Select id="a-t" value={f.tag} onChange={set('tag')} options={(tags ?? []).map((t) => ({ kod: t.nama, nama: `${t.nama} (${t.bilangan})${t.jenis === 'berstruktur' ? ' ★' : ''}` }))} empty="Semua tag" /></Field>
                <label className="flex items-end gap-2 text-body-sm pb-2"><input type="checkbox" checked={f.tanpa_pemeriksaan} onChange={(e) => set('tanpa_pemeriksaan')(e.target.checked)} /> Tanpa pemeriksaan melebihi tempoh</label>
                <label className="flex items-end gap-2 text-body-sm pb-2"><input type="checkbox" checked={f.arkib_sahaja} onChange={(e) => set('arkib_sahaja')(e.target.checked)} /> Aset diarkib sahaja</label>
                {f.lot_id && <p className="text-body-sm self-end pb-2">Ditapis mengikut lot. <button type="button" className="text-txt-primary underline" onClick={() => set('lot_id')('')}>Buang</button></p>}
            </Penapis>
            <Card
                title={`Aset fizikal (${data?.meta.total ?? 0})`}
                actions={
                    <>
                        {can('ast.qr.eksport') && (
                            <>
                                <label htmlFor="a-tl" className="sr-only">Templat label</label>
                                <select id="a-tl" className={`${inputClass} w-auto`} value={templat} onChange={(e) => setTemplat(e.target.value)}>
                                    {(ref?.templat_label ?? []).map((t) => <option key={t.kod} value={t.kod}>{t.nama}</option>)}
                                </select>
                                <Button size="sm" variant="secondary" disabled={pilih.size === 0 || cetak} onClick={cetakSekarang}>{cetak ? 'Menjana…' : `Cetak label QR (${pilih.size})`}</Button>
                            </>
                        )}
                        {can('ast.aset.cipta') && <LinkButton size="sm" href="/admin/ast/aset/baru">Daftar aset</LinkButton>}
                    </>
                }
            >
                {error && <p className="text-body-sm text-txt-danger">{ralat(error)}</p>}
                {data && data.data.length === 0 ? <Kosong>Tiada aset.</Kosong> : (
                    <Table head={<>
                        {can('ast.qr.eksport') && <th><input type="checkbox" aria-label="Pilih semua di halaman ini" checked={semua.length > 0 && semua.every((a) => pilih.has(a.id))} onChange={(e) => setPilih((s) => { const n = new Set(s); semua.forEach((a) => (e.target.checked ? n.add(a.id) : n.delete(a.id))); return n; })} /></th>}
                        <th>No. rujukan</th><th>Nama aset</th><th>Kategori</th><th>Lot</th><th>Daerah</th><th>Kondisi</th><th>Nilai semasa</th><th>Pemeriksaan terakhir</th><th>Status</th>
                    </>}>
                        {(data?.data ?? []).map((a) => (
                            <tr key={a.id}>
                                {can('ast.qr.eksport') && <td><input type="checkbox" aria-label={`Pilih ${a.nama_aset}`} disabled={a.status_arkib} checked={pilih.has(a.id)} onChange={() => toggle(a.id)} /></td>}
                                <td className="whitespace-nowrap"><Link className="text-txt-primary hover:underline" href={`/admin/ast/aset/${a.id}`}>{a.no_rujukan_aset}</Link></td>
                                <td>{a.nama_aset}{a.id_aset_induk && <span className="ml-1 text-body-xs text-txt-black-500">(subaset)</span>}</td>
                                <td>{a.kategori}</td>
                                <td className="whitespace-nowrap">{a.no_lot ?? '—'}</td>
                                <td>{a.daerah}</td>
                                <td><KondisiBadge kondisi={a.status_kondisi} label={a.kondisi_nama} /></td>
                                <td className="whitespace-nowrap">{rm(a.nilai_semasa)}</td>
                                <td className="whitespace-nowrap">{tarikh(a.tarikh_pemeriksaan_terakhir)}</td>
                                <td>
                                    <Badge variant={a.status_arkib ? 'neutral' : a.status_kelulusan === 'menunggu' ? 'warning' : 'success'}>{a.status_kelulusan === 'menunggu' ? a.kelulusan_nama : a.status_nama}</Badge>
                                    {a.sintetik && <span className="ml-1"><Badge>Sintetik</Badge></span>}
                                </td>
                            </tr>
                        ))}
                    </Table>
                )}
                {data && data.meta.last_page > 1 && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />}
            </Card>
        </PageContainer>
    );
}

export default function SenaraiAset() {
    return (
        <Suspense fallback={<PageContainer><Kosong>Memuatkan…</Kosong></PageContainer>}>
            <Senarai />
        </Suspense>
    );
}
