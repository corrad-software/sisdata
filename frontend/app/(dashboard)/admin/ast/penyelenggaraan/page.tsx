'use client';

import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { Suspense, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Pagination } from '@/components/ui/Pagination';
import { Table } from '@/components/ui/Table';
import { astApi, ralat, tarikh, useCan, useRujukan } from '@/lib/ast';
import { Card, Field, inputClass, Kosong, Penapis, Select } from '../_components/common';

const HARI = ['Isn', 'Sel', 'Rab', 'Kha', 'Jum', 'Sab', 'Ahd'];
const WARNA: Record<string, string> = { tertunggak: 'bg-bg-danger-50 text-txt-danger', jatuh_tempoh: 'bg-bg-warning-50 text-txt-warning', unjuran: 'bg-bg-washed text-txt-black-700' };

/** SRS-AST-15 unit maintenance calendar (month grid; list on phones). */
function Kalendar({ daerah }: { daerah: string }) {
    const [bulan, setBulan] = useState(() => new Date().toISOString().slice(0, 7));
    const { data } = useQuery({ queryKey: ['ast', 'kalendar', bulan, daerah], queryFn: () => astApi.kalendar({ bulan, kod_daerah: daerah }) });
    const gerak = (n: number) => { const [y, m] = bulan.split('-').map(Number); const d = new Date(y, m - 1 + n, 1); setBulan(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`); };
    const sel = useMemo(() => {
        const [y, m] = bulan.split('-').map(Number);
        const first = new Date(y, m - 1, 1);
        const pad = (first.getDay() + 6) % 7;
        const days = new Date(y, m, 0).getDate();
        return [...Array(pad).fill(null), ...Array.from({ length: days }, (_, i) => `${bulan}-${String(i + 1).padStart(2, '0')}`)];
    }, [bulan]);
    const ikutHari = useMemo(() => {
        const m = new Map<string, NonNullable<typeof data>['acara']>();
        (data?.acara ?? []).forEach((e) => m.set(e.tarikh, [...(m.get(e.tarikh) ?? []), e]));
        return m;
    }, [data]);
    const tajuk = new Date(`${bulan}-01T00:00:00`).toLocaleDateString('ms-MY', { month: 'long', year: 'numeric' });
    const pill = (e: NonNullable<typeof data>['acara'][number]) => (
        <Link key={e.id} href={e.pautan} className={`block truncate rounded px-1 text-body-xs ${e.jenis === 'kerja' ? 'bg-bg-primary-50 text-txt-primary' : (WARNA[e.status] ?? 'bg-bg-washed')}`} title={e.tajuk}>
            {e.jenis === 'kerja' ? '🔧 ' : ''}{e.tajuk}
        </Link>
    );

    return (
        <Card title={`Kalendar penyelenggaraan — ${tajuk}`} actions={<><Button size="sm" variant="secondary" onClick={() => gerak(-1)}>‹ Sebelum</Button><Button size="sm" variant="secondary" onClick={() => gerak(1)}>Seterusnya ›</Button></>}>
            <ul className="flex flex-wrap gap-2 text-body-xs" aria-label="Petunjuk kalendar">
                <li><Badge variant="danger">Tertunggak</Badge></li><li><Badge variant="warning">Jatuh tempoh</Badge></li><li><Badge>Unjuran kitaran</Badge></li><li><Badge variant="info">🔧 Kerja dijadual</Badge></li>
            </ul>
            <div className="hidden md:grid grid-cols-7 gap-px bg-otl-gray-200 border border-otl-gray-200 rounded-lg overflow-hidden">
                {HARI.map((h) => <div key={h} className="bg-bg-washed p-2 text-body-xs font-semibold text-center">{h}</div>)}
                {sel.map((d, i) => (
                    <div key={d ?? `p${i}`} className="bg-bg-white min-h-24 p-1 space-y-0.5">
                        {d && <p className="text-body-xs text-txt-black-500">{Number(d.slice(8))}</p>}
                        {d && (ikutHari.get(d) ?? []).slice(0, 4).map(pill)}
                        {d && (ikutHari.get(d)?.length ?? 0) > 4 && <p className="text-body-xs text-txt-black-500">+{(ikutHari.get(d)?.length ?? 0) - 4} lagi</p>}
                    </div>
                ))}
            </div>
            <ol className="md:hidden space-y-2">
                {[...ikutHari.entries()].map(([d, es]) => (
                    <li key={d} className="border-b border-otl-divider pb-2"><p className="text-body-sm font-semibold">{tarikh(d)}</p><div className="space-y-0.5">{es.map(pill)}</div></li>
                ))}
                {ikutHari.size === 0 && <Kosong>Tiada acara bulan ini.</Kosong>}
            </ol>
        </Card>
    );
}

function Kandungan() {
    const sp = useSearchParams();
    const can = useCan();
    const qc = useQueryClient();
    const { data: ref } = useRujukan();
    const [f, setF] = useState({ status: sp.get('status') ?? '', kod_daerah: '', jenis: '' });
    const [page, setPage] = useState(1);
    const { data } = useQuery({ queryKey: ['ast', 'jadual', f, page], queryFn: () => astApi.jadual({ ...f, page }) });
    const { data: ptn } = useQuery({ queryKey: ['ast', 'ptn'], queryFn: astApi.keperluanPtn });
    const [padan, setPadan] = useState<Record<number, string>>({});
    const tarik = useMutation({ mutationFn: astApi.tarikPtn, onSuccess: (r) => { toast.success(r.message); qc.invalidateQueries({ queryKey: ['ast', 'ptn'] }); }, onError: (e) => toast.error(ralat(e)) });
    const paut = useMutation({ mutationFn: ({ id, aset }: { id: number; aset: string }) => astApi.pautPtn(id, aset), onSuccess: (r) => { toast.success(r.message); qc.invalidateQueries({ queryKey: ['ast', 'ptn'] }); }, onError: (e) => toast.error(ralat(e)) });
    const set = (k: keyof typeof f) => (v: string) => { setF((x) => ({ ...x, [k]: v })); setPage(1); };

    return (
        <PageContainer>
            <Kalendar daerah={f.kod_daerah} />
            <Penapis>
                <Field label="Status" htmlFor="j-s"><Select id="j-s" value={f.status} onChange={set('status')} options={[{ kod: 'tertunggak', nama: 'Tertunggak' }, { kod: 'hampir', nama: 'Dalam tempoh amaran' }, { kod: 'tidak_aktif', nama: 'Tidak aktif' }]} empty="Semua aktif" /></Field>
                <Field label="Daerah" htmlFor="j-d"><Select id="j-d" value={f.kod_daerah} onChange={set('kod_daerah')} options={ref?.daerah ?? []} empty="Semua daerah" /></Field>
                <Field label="Jenis penyelenggaraan" htmlFor="j-j"><Select id="j-j" value={f.jenis} onChange={set('jenis')} options={ref?.jenis_penyelenggaraan ?? []} empty="Semua jenis" /></Field>
            </Penapis>
            <Card title={`Jadual penyelenggaraan (${data?.meta.total ?? 0})`}>
                {data && data.data.length === 0 ? <Kosong>Tiada jadual.</Kosong> : (
                    <Table head={<><th>Jatuh tempoh</th><th>Aset</th><th>Jenis</th><th>Kitaran</th><th>Pegawai</th><th>Status</th></>}>
                        {(data?.data ?? []).map((j) => (
                            <tr key={j.id}>
                                <td className="whitespace-nowrap">{tarikh(j.tarikh_jatuh_tempoh)}</td>
                                <td>{j.aset ? <Link href={`/admin/ast/aset/${j.aset.id}`} className="text-txt-primary hover:underline">{j.aset.nama_aset}</Link> : '—'}</td>
                                <td>{j.jenis_nama}</td><td>{j.kitaran_bulan} bulan</td><td>{j.pegawai?.nama ?? '—'}</td>
                                <td>{j.status !== 'aktif' ? <Badge>Tidak aktif</Badge> : j.tertunggak ? <Badge variant="danger">Tertunggak{j.tarikh_eskalasi ? ' · dieskalasi' : ''}</Badge> : j.hampir ? <Badge variant="warning">Dalam tempoh amaran</Badge> : <Badge variant="success">Dalam jadual</Badge>}</td>
                            </tr>
                        ))}
                    </Table>
                )}
                {data && data.meta.last_page > 1 && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />}
            </Card>
            <Card title={`Keperluan penyelenggaraan daripada Pemantauan Tanah (${ptn?.length ?? 0})`} actions={can('ast.penyelenggaraan.cipta') ? <Button size="sm" variant="secondary" disabled={tarik.isPending} onClick={() => tarik.mutate()}>Tarik & jana kerja</Button> : undefined}>
                <p className="text-body-sm text-txt-black-700">Keperluan pada lot dengan satu aset dijana menjadi kerja secara automatik setiap hari. Lot dengan berbilang aset perlu dipadankan di sini.</p>
                {ptn && ptn.length === 0 ? <Kosong>Tiada keperluan menunggu.</Kosong> : (
                    <Table head={<><th>Tarikh</th><th>Jenis</th><th>Keterangan</th><th>Keutamaan</th><th>Padankan dengan aset</th></>}>
                        {(ptn ?? []).map((n) => <BarisPtn key={n.id} n={n} pilih={padan[n.id] ?? ''} onPilih={(v) => setPadan({ ...padan, [n.id]: v })} onPaut={() => paut.mutate({ id: n.id, aset: padan[n.id] })} boleh={can('ast.penyelenggaraan.cipta')} />)}
                    </Table>
                )}
            </Card>
        </PageContainer>
    );
}

function BarisPtn({ n, pilih, onPilih, onPaut, boleh }: { n: { id: number; lot_id: string; jenis_nama: string; keterangan: string; keutamaan: string; tarikh_dikenal_pasti: string }; pilih: string; onPilih: (v: string) => void; onPaut: () => void; boleh: boolean }) {
    const { data } = useQuery({ queryKey: ['ast', 'aset-lot', n.lot_id], queryFn: () => astApi.aset({ lot_id: n.lot_id, per_page: 50 }) });
    return (
        <tr>
            <td className="whitespace-nowrap">{tarikh(n.tarikh_dikenal_pasti)}</td><td>{n.jenis_nama}</td><td className="max-w-xs">{n.keterangan}</td><td>{n.keutamaan}</td>
            <td>
                {boleh ? (
                    <div className="flex gap-2">
                        <label htmlFor={`ptn-${n.id}`} className="sr-only">Aset</label>
                        <select id={`ptn-${n.id}`} className={inputClass} value={pilih} onChange={(e) => onPilih(e.target.value)}>
                            <option value="">Pilih aset</option>
                            {(data?.data ?? []).map((a) => <option key={a.id} value={a.id}>{a.nama_aset}</option>)}
                        </select>
                        <Button size="sm" disabled={!pilih} onClick={onPaut}>Paut</Button>
                    </div>
                ) : '—'}
            </td>
        </tr>
    );
}

export default function Penyelenggaraan() {
    return (
        <Suspense fallback={<PageContainer><Kosong>Memuatkan…</Kosong></PageContainer>}>
            <Kandungan />
        </Suspense>
    );
}
