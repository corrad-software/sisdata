'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Button, LinkButton } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Pagination } from '@/components/ui/Pagination';
import { Table } from '@/components/ui/Table';
import { pjkApi, ralat, tarikh, useCan, useRujukan, type Premis } from '@/lib/pjk';
import { Card, Field, InfoGrid, inputClass, Kosong, ModalBody, Select, Sintetik, Status } from '../_components/common';

/** SRS-PJK-03/04: premise register and offer catalogue (filters: daerah, mukim, kategori, keluasan, kadar). */
export default function PremisPage() {
    const can = useCan();
    const qc = useQueryClient();
    const search = useSearchParams();
    const { data: ref } = useRujukan();
    const [f, setF] = useState<Record<string, string>>({});
    const [page, setPage] = useState(1);
    const [selected, setSelected] = useState<number | null>(search.get('premis') ? Number(search.get('premis')) : null);
    const [open, setOpen] = useState(false);
    const [n, setN] = useState<Record<string, string>>({ jenis_aset: 'bangunan', kategori_hartanah: 'premis' });
    const [lotQ, setLotQ] = useState('');

    const params = { ...f, page };
    const { data, isLoading, error } = useQuery({ queryKey: ['pjk', 'premis', params], queryFn: () => pjkApi.premis(params) });
    const detail = useQuery({ queryKey: ['pjk', 'premis', 'satu', selected], queryFn: () => pjkApi.premisSatu(selected as number), enabled: selected !== null });
    const lots = useQuery({ queryKey: ['pjk', 'lot', lotQ], queryFn: () => pjkApi.lot(lotQ), enabled: open && can('pjk.premis.cipta') });
    const set = (k: string, v: string) => {
        setF((p) => ({ ...p, [k]: v }));
        setPage(1);
    };

    async function save() {
        try {
            const r = await pjkApi.premisCipta(n);
            toast.success(r.message ?? 'Didaftarkan.');
            setOpen(false);
            qc.invalidateQueries({ queryKey: ['pjk', 'premis'] });
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    async function status(p: Premis, s: string) {
        const sebab = window.prompt('Sebab perubahan status');
        if (!sebab) return;
        try {
            await pjkApi.premisKemasKini(p.id, { status_premis: s, sebab });
            toast.success('Status dikemas kini.');
            qc.invalidateQueries({ queryKey: ['pjk', 'premis'] });
        } catch (e) {
            toast.error(ralat(e));
        }
    }

    const mukim = (ref?.mukim ?? []).filter((m) => !f.kod_daerah || m.daerah_kod === f.kod_daerah);
    const d = detail.data;

    return (
        <PageContainer>
            <Card actions={can('pjk.premis.cipta') ? <Button onClick={() => setOpen(true)}>Daftar premis</Button> : undefined}>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <Field label="Carian" htmlFor="pm-q">
                        <input id="pm-q" type="search" className={inputClass} value={f.q ?? ''} onChange={(e) => set('q', e.target.value)} />
                    </Field>
                    <Field label="Status" htmlFor="pm-status">
                        <Select id="pm-status" value={f.status_premis ?? ''} onChange={(v) => set('status_premis', v)} options={ref?.status_premis ?? []} empty="Semua" />
                    </Field>
                    <Field label="Daerah" htmlFor="pm-daerah">
                        <Select id="pm-daerah" value={f.kod_daerah ?? ''} onChange={(v) => setF((p) => ({ ...p, kod_daerah: v, kod_mukim: '' }))} options={ref?.daerah ?? []} empty="Semua" />
                    </Field>
                    <Field label="Mukim" htmlFor="pm-mukim">
                        <Select id="pm-mukim" value={f.kod_mukim ?? ''} onChange={(v) => set('kod_mukim', v)} options={mukim} empty="Semua" />
                    </Field>
                    <Field label="Kategori" htmlFor="pm-kat">
                        <Select id="pm-kat" value={f.kategori_hartanah ?? ''} onChange={(v) => set('kategori_hartanah', v)} options={ref?.kategori_hartanah ?? []} empty="Semua" />
                    </Field>
                    <Field label="Keluasan min / maks (m²)" htmlFor="pm-kmin">
                        <div className="flex gap-2">
                            <input id="pm-kmin" type="number" className={inputClass} value={f.keluasan_min ?? ''} onChange={(e) => set('keluasan_min', e.target.value)} />
                            <input aria-label="Keluasan maksimum" type="number" className={inputClass} value={f.keluasan_max ?? ''} onChange={(e) => set('keluasan_max', e.target.value)} />
                        </div>
                    </Field>
                    <Field label="Kadar min / maks (RM)" htmlFor="pm-rmin">
                        <div className="flex gap-2">
                            <input id="pm-rmin" type="number" className={inputClass} value={f.kadar_min ?? ''} onChange={(e) => set('kadar_min', e.target.value)} />
                            <input aria-label="Kadar maksimum" type="number" className={inputClass} value={f.kadar_max ?? ''} onChange={(e) => set('kadar_max', e.target.value)} />
                        </div>
                    </Field>
                </div>
                <p className="text-body-sm text-txt-black-500">{error ? ralat(error) : data ? `${data.meta.total} premis` : 'Memuatkan…'}</p>
                <Table head={<><th>Premis</th><th className="hidden md:table-cell">Kategori</th><th className="hidden sm:table-cell">Kadar tawaran</th><th>Status</th></>}>
                    {isLoading ? (
                        <Kosong colSpan={4}>Memuatkan…</Kosong>
                    ) : (data?.data ?? []).length === 0 ? (
                        <Kosong colSpan={4}>Tiada premis.</Kosong>
                    ) : (
                        data?.data.map((p) => (
                            <tr key={p.id}>
                                <td className="max-w-[13rem] sm:max-w-xs">
                                    <button type="button" className="text-left font-medium text-txt-primary hover:underline break-words" onClick={() => setSelected(p.id)}>
                                        {p.kod_premis} · {p.nama_premis}
                                    </button>{' '}
                                    <Sintetik show={p.sintetik} />
                                    <p className="text-body-xs text-txt-black-500">
                                        Lot {p.no_lot ?? '—'} · {p.kod_mukim} · {p.keluasan ?? '—'} m²
                                    </p>
                                </td>
                                <td className="hidden md:table-cell">
                                    {p.kategori_hartanah_nama}
                                    <p className="text-body-xs text-txt-black-500">{p.bahagian_nama}</p>
                                </td>
                                <td className="hidden sm:table-cell whitespace-nowrap">{p.kadar_tawaran.rm}</td>
                                <td>
                                    <Status kod={p.status_premis} nama={p.status_premis_nama} />
                                    {p.status_premis === 'kosong' && p.hari_status !== null && <p className="text-body-xs text-txt-black-500">{p.hari_status} hari</p>}
                                </td>
                            </tr>
                        ))
                    )}
                </Table>
                {data && data.meta.last_page > 1 && <Pagination currentPage={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} onPageChange={setPage} />}
            </Card>

            {d && (
                <Card
                    title={`${d.kod_premis} · ${d.nama_premis}`}
                    actions={
                        <>
                            {d.status_premis === 'kosong' && can('pjk.kontrak.cipta') && (
                                <LinkButton size="sm" href={`/admin/pjk/kontrak/baharu?premis=${d.id}`}>
                                    Daftar perjanjian
                                </LinkButton>
                            )}
                            {can('pjk.premis.kemas_kini') && d.status_premis !== 'disewa' && (
                                <select aria-label="Tukar status" className={`${inputClass} w-auto`} value="" onChange={(e) => e.target.value && status(d, e.target.value)}>
                                    <option value="">Tukar status…</option>
                                    {(ref?.status_premis ?? []).filter((s) => s.kod !== 'disewa' && s.kod !== d.status_premis).map((s) => (
                                        <option key={s.kod} value={s.kod}>
                                            {s.nama}
                                        </option>
                                    ))}
                                </select>
                            )}
                        </>
                    }
                >
                    <InfoGrid
                        items={[
                            ['Status', <Status key="s" kod={d.status_premis} nama={d.status_premis_nama} />],
                            ['Kategori / jenis', `${d.kategori_hartanah_nama} · ${d.jenis_aset_nama}`],
                            ['Status perolehan (agihan manfaat)', d.status_perolehan === 'wakaf' ? 'Diwakafkan' : 'Dibeli'],
                            ['Lot', d.no_lot ? <Link key="l" href={`/admin/pro/lot/${d.lot_id}`} className="text-txt-primary hover:underline">{d.no_lot}</Link> : '—'],
                            ['Kadar tawaran', d.kadar_tawaran.rm],
                            ['Dipaparkan di portal awam', d.papar_portal ? 'Ya' : 'Tidak'],
                        ]}
                    />
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div>
                            <h4 className="font-semibold text-body-md mb-2">Sejarah status</h4>
                            <ul className="text-body-sm space-y-1">
                                {d.sejarah_status?.map((h, i) => (
                                    <li key={i}>
                                        {tarikh(h.tarikh)} — {h.status_nama} {h.sebab ? `· ${h.sebab}` : ''}
                                    </li>
                                ))}
                            </ul>
                        </div>
                        <div>
                            <h4 className="font-semibold text-body-md mb-2">Perjanjian</h4>
                            <ul className="text-body-sm space-y-1">
                                {d.kontrak?.map((k) => (
                                    <li key={k.id}>
                                        <Link href={`/admin/pjk/kontrak/${k.id}`} className="text-txt-primary hover:underline">
                                            {k.no_kontrak}
                                        </Link>{' '}
                                        {k.penyewa} · {k.status_nama}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </Card>
            )}

            <Modal open={open} onClose={() => setOpen(false)}>
                <ModalBody
                    title="Daftar premis / tanah untuk disewa"
                    footer={
                        <>
                            <Button variant="secondary" onClick={() => setOpen(false)}>
                                Batal
                            </Button>
                            <Button onClick={save}>Simpan</Button>
                        </>
                    }
                >
                    <Field label="Cari lot" htmlFor="n-lotq">
                        <input id="n-lotq" type="search" className={inputClass} value={lotQ} onChange={(e) => setLotQ(e.target.value)} placeholder="No. lot" />
                    </Field>
                    <Field label="Lot" htmlFor="n-lot">
                        <select id="n-lot" className={inputClass} value={n.lot_id ?? ''} onChange={(e) => setN((p) => ({ ...p, lot_id: e.target.value }))}>
                            <option value="">— Pilih lot —</option>
                            {(lots.data ?? []).map((l) => (
                                <option key={l.id} value={l.id}>
                                    {l.no_lot} · {l.kod_mukim} {l.bahagian_pemilik ? `(${l.bahagian_pemilik})` : ''}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Nama premis" htmlFor="n-nama">
                        <input id="n-nama" className={inputClass} value={n.nama_premis ?? ''} onChange={(e) => setN((p) => ({ ...p, nama_premis: e.target.value }))} />
                    </Field>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <Field label="Jenis aset" htmlFor="n-jenis">
                            <Select id="n-jenis" value={n.jenis_aset} onChange={(v) => setN((p) => ({ ...p, jenis_aset: v }))} options={ref?.jenis_aset ?? []} />
                        </Field>
                        <Field label="Kategori hartanah" htmlFor="n-kat">
                            <Select id="n-kat" value={n.kategori_hartanah} onChange={(v) => setN((p) => ({ ...p, kategori_hartanah: v }))} options={ref?.kategori_hartanah ?? []} />
                        </Field>
                        <Field label="Keluasan (m²)" htmlFor="n-luas">
                            <input id="n-luas" inputMode="decimal" className={inputClass} value={n.keluasan ?? ''} onChange={(e) => setN((p) => ({ ...p, keluasan: e.target.value }))} />
                        </Field>
                        <Field label="Kadar tawaran sebulan (RM)" htmlFor="n-kadar">
                            <input id="n-kadar" inputMode="decimal" className={inputClass} value={n.kadar_tawaran ?? ''} onChange={(e) => setN((p) => ({ ...p, kadar_tawaran: e.target.value }))} />
                        </Field>
                    </div>
                    <Field label="Alamat" htmlFor="n-alamat">
                        <textarea id="n-alamat" rows={2} className={inputClass} value={n.alamat ?? ''} onChange={(e) => setN((p) => ({ ...p, alamat: e.target.value }))} />
                    </Field>
                </ModalBody>
            </Modal>
        </PageContainer>
    );
}
