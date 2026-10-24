'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Button, LinkButton } from '@/components/ui/Button';
import { Pagination } from '@/components/ui/Pagination';
import { hkmApi, keluasan, muatTurun, ralat, tarikh, useCan, useRujukan } from '@/lib/hkm';
import { BahagianChip, BenderaList, Card, Field, inputClass, JenisChip, KategoriChip, Legenda, Select } from '../_components/common';

type Penapis = Record<string, string>;

const BENDERA = [
    { kod: 'sekatan_aktif', nama: 'Sekatan / bebanan aktif' },
    { kod: 'dokumen_asal_tiada', nama: 'Dokumen asal tiada dalam simpanan' },
    { kod: 'pemilikan_belum_selesai', nama: 'Pemilikan belum selesai' },
    { kod: 'belum_disahkan', nama: 'Belum disahkan' },
];

const JENIS_TANAH = [
    { kod: 'hartanah', nama: 'Hartanah MAIS' },
    { kod: 'wakaf', nama: 'Wakaf' },
    { kod: 'baitulmal', nama: 'Baitulmal' },
];

/** SRS-HKM-14: title search by the nine A-5.06 criteria (combined with AND) + export. */
export default function HakMilikListPage() {
    const can = useCan();
    const { data: ref } = useRujukan();
    const [q, setQ] = useState('');
    const [debounced, setDebounced] = useState('');
    const [f, setF] = useState<Penapis>({});
    const [page, setPage] = useState(1);
    const [showFilters, setShowFilters] = useState(false);
    const [exporting, setExporting] = useState(false);

    useEffect(() => {
        const t = setTimeout(() => {
            setDebounced(q);
            setPage(1);
        }, 350);
        return () => clearTimeout(t);
    }, [q]);

    const params = { ...f, q: debounced, page };
    const { data, isLoading, error } = useQuery({ queryKey: ['hkm', 'hak-milik', params], queryFn: () => hkmApi.cari(params) });
    const rows = data?.data ?? [];
    const meta = data?.meta;

    function set(key: string, value: string) {
        setF((prev) => ({ ...prev, [key]: value }));
        setPage(1);
    }

    async function exportAs(format: 'xlsx' | 'csv' | 'pdf') {
        setExporting(true);
        try {
            await muatTurun('/hkm/eksport/hak-milik', { ...f, q: debounced, format }, `carian-hak-milik.${format}`);
        } catch (e) {
            toast.error(ralat(e));
        } finally {
            setExporting(false);
        }
    }

    const mukim = (ref?.mukim ?? []).filter((m) => !f.kod_daerah || m.daerah_kod === f.kod_daerah);
    const activeFilters = Object.values(f).filter(Boolean).length;

    return (
        <PageContainer>
            <Card>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <label htmlFor="hkm-q" className="sr-only">
                        Kata kunci
                    </label>
                    <input
                        id="hkm-q"
                        type="search"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Cari no. hak milik, no. lot, pemilik, syarat nyata, no. perserahan…"
                        className={`${inputClass} sm:flex-1`}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button variant="secondary" onClick={() => setShowFilters((s) => !s)} aria-expanded={showFilters}>
                            Penapis{activeFilters > 0 ? ` (${activeFilters})` : ''}
                        </Button>
                        {can('hkm.hak_milik.cipta') && <LinkButton href="/admin/hkm/hak-milik/baharu">Daftar hak milik</LinkButton>}
                    </div>
                </div>

                {showFilters && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <Field label="No. hak milik" htmlFor="f-no">
                            <input id="f-no" className={inputClass} value={f.no_hak_milik ?? ''} onChange={(e) => set('no_hak_milik', e.target.value)} />
                        </Field>
                        <Field label="No. lot" htmlFor="f-lot">
                            <input id="f-lot" className={inputClass} value={f.no_lot ?? ''} onChange={(e) => set('no_lot', e.target.value)} />
                        </Field>
                        <Field label="Daerah" htmlFor="f-daerah">
                            <Select id="f-daerah" value={f.kod_daerah ?? ''} onChange={(v) => setF((p) => ({ ...p, kod_daerah: v, kod_mukim: '' }))} options={ref?.daerah ?? []} empty="Semua" />
                        </Field>
                        <Field label="Mukim" htmlFor="f-mukim">
                            <Select id="f-mukim" value={f.kod_mukim ?? ''} onChange={(v) => set('kod_mukim', v)} options={mukim} empty="Semua" />
                        </Field>
                        <Field label="Jenis tanah" htmlFor="f-jt">
                            <Select id="f-jt" value={f.jenis_tanah ?? ''} onChange={(v) => set('jenis_tanah', v)} options={JENIS_TANAH} empty="Semua" />
                        </Field>
                        <Field label="Jenis hak milik" htmlFor="f-jenis">
                            <Select id="f-jenis" value={f.jenis_hak_milik ?? ''} onChange={(v) => set('jenis_hak_milik', v)} options={ref?.jenis_hak_milik ?? []} empty="Semua" />
                        </Field>
                        <Field label="Kategori kegunaan" htmlFor="f-kat">
                            <Select id="f-kat" value={f.kategori_tanah ?? ''} onChange={(v) => set('kategori_tanah', v)} options={ref?.kategori_tanah ?? []} empty="Semua" />
                        </Field>
                        <Field label="Status" htmlFor="f-status">
                            <Select id="f-status" value={f.status ?? ''} onChange={(v) => set('status', v)} options={ref?.status_tanah ?? []} empty="Semua" />
                        </Field>
                        <Field label="Keluasan minimum" htmlFor="f-kmin">
                            <input id="f-kmin" type="number" min={0} className={inputClass} value={f.keluasan_min ?? ''} onChange={(e) => set('keluasan_min', e.target.value)} />
                        </Field>
                        <Field label="Keluasan maksimum" htmlFor="f-kmax">
                            <input id="f-kmax" type="number" min={0} className={inputClass} value={f.keluasan_max ?? ''} onChange={(e) => set('keluasan_max', e.target.value)} />
                        </Field>
                        <Field label="Bendera" htmlFor="f-bendera">
                            <Select id="f-bendera" value={f.bendera ?? ''} onChange={(v) => set('bendera', v)} options={BENDERA} empty="Semua" />
                        </Field>
                        <Field label="Tamat tempoh dalam (hari)" htmlFor="f-tamat">
                            <input id="f-tamat" type="number" min={1} className={inputClass} value={f.tamat_dalam_hari ?? ''} onChange={(e) => set('tamat_dalam_hari', e.target.value)} />
                        </Field>
                        <div className="sm:col-span-2 lg:col-span-4 flex justify-end">
                            <Button variant="outline" size="sm" onClick={() => setF({})}>
                                Kosongkan penapis
                            </Button>
                        </div>
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-body-sm text-txt-black-500" aria-live="polite">
                        {error ? <span className="text-txt-danger">{ralat(error)}</span> : meta ? `${meta.total} hak milik ditemui` : 'Memuatkan…'}
                    </p>
                    {can('hkm.hak_milik.eksport') && (
                        <div className="flex gap-2">
                            {(['xlsx', 'csv', 'pdf'] as const).map((fmt) => (
                                <Button key={fmt} size="sm" variant="outline" disabled={exporting} onClick={() => exportAs(fmt)}>
                                    Eksport {fmt.toUpperCase()}
                                </Button>
                            ))}
                        </div>
                    )}
                </div>

                <Legenda />

                <div className="overflow-x-auto">
                    <Table
                        head={
                            <>
                                <th>Hak milik</th>
                                <th className="hidden md:table-cell">Lot / mukim</th>
                                <th className="hidden lg:table-cell">Kategori</th>
                                <th className="hidden sm:table-cell">Status</th>
                                <th className="hidden xl:table-cell">Tamat tempoh</th>
                                <th className="hidden md:table-cell">Bendera</th>
                            </>
                        }
                    >
                        {isLoading ? (
                            <tr>
                                <td colSpan={6} className="py-6 text-center text-txt-black-500">
                                    Memuatkan…
                                </td>
                            </tr>
                        ) : rows.length === 0 ? (
                            <tr>
                                <td colSpan={6} className="py-6 text-center text-txt-black-500">
                                    Tiada hak milik sepadan dengan kriteria.
                                </td>
                            </tr>
                        ) : (
                            rows.map((h) => (
                                <tr key={h.id}>
                                    <td className="max-w-[14rem] sm:max-w-xs">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <JenisChip kod={h.jenis_hak_milik} nama={h.jenis_hak_milik_nama} />
                                            <Link href={`/admin/hkm/hak-milik/${h.id}`} className="font-medium text-txt-primary hover:underline break-words">
                                                {h.no_hak_milik}
                                            </Link>
                                        </div>
                                        <p className="mt-1 text-body-xs text-txt-black-500 break-words">{h.pemilik_berdaftar}</p>
                                        <p className="mt-1 text-body-xs text-txt-black-500 md:hidden">
                                            {h.no_lot ?? '—'} · {h.mukim}
                                        </p>
                                    </td>
                                    <td className="hidden md:table-cell">
                                        <p>{h.no_lot ?? '—'}</p>
                                        <p className="text-body-xs text-txt-black-500">
                                            {h.mukim}, {h.daerah}
                                        </p>
                                        <BahagianChip kod={h.bahagian_pemilik} />
                                    </td>
                                    <td className="hidden lg:table-cell">
                                        <KategoriChip kod={h.kategori_tanah} nama={h.kategori_tanah_nama} />
                                        <p className="mt-1 text-body-xs text-txt-black-500">{keluasan(h)}</p>
                                    </td>
                                    <td className="hidden sm:table-cell">{h.status_semasa_nama}</td>
                                    <td className="hidden xl:table-cell">{tarikh(h.tarikh_tamat_tempoh)}</td>
                                    <td className="hidden md:table-cell max-w-xs">
                                        <BenderaList bendera={h.bendera.filter((b) => b.level !== 'info')} compact />
                                    </td>
                                </tr>
                            ))
                        )}
                    </Table>
                </div>

                {meta && meta.last_page > 1 && <Pagination currentPage={meta.current_page} lastPage={meta.last_page} total={meta.total} onPageChange={setPage} />}
            </Card>
        </PageContainer>
    );
}
