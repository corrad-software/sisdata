'use client';

import { useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button, LinkButton } from '@/components/ui/Button';
import { StatCard } from '@/components/ui/StatCard';
import { InputLabel } from '@/components/ui/InputLabel';
import { Alert } from '@/components/ui/Alert';
import { Card, EmptyRow, ExportMenu, KlasifikasiBadge, Masked, Pager, SelectField, SpatialBadge, inputClass } from '@/components/pro/common';
import { LotMap } from '@/components/pro/LotMap';
import {
    BAHAGIAN_LABEL,
    STATUS_LOT_LABEL,
    errorMessage,
    fmtNombor,
    fmtTarikh,
    proApi,
    toneStatusLot,
    useCan,
    useRujukan,
    type PenapisLot,
} from '@/lib/pro';

export default function LotListPage() {
    const router = useRouter();
    const can = useCan();
    const { data: ruj } = useRujukan();
    const [q, setQ] = useState('');
    const [debouncedQ, setDebouncedQ] = useState('');
    const [filters, setFilters] = useState<PenapisLot>({});
    const [page, setPage] = useState(1);
    const [sort, setSort] = useState('no_lot');
    const [view, setView] = useState<'jadual' | 'peta'>('jadual');
    const [showFilters, setShowFilters] = useState(false);

    useEffect(() => {
        const t = setTimeout(() => {
            setDebouncedQ(q.trim());
            setPage(1);
        }, 350);
        return () => clearTimeout(t);
    }, [q]);

    const base: PenapisLot = useMemo(() => ({ ...filters, q: debouncedQ }), [filters, debouncedQ]);
    const params: PenapisLot = { ...base, page, sort, per_page: 25 };

    const list = useQuery({ queryKey: ['pro', 'lot', params], queryFn: () => proApi.senaraiLot(params) });
    const summary = useQuery({ queryKey: ['pro', 'lot', 'ringkasan', base], queryFn: () => proApi.ringkasanLot(base).then((r) => r.data) });
    const geo = useQuery({ queryKey: ['pro', 'geojson', base], queryFn: () => proApi.geojson(base), enabled: view === 'peta' });

    function setFilter(key: keyof PenapisLot, value: string) {
        setFilters((f) => {
            const next = { ...f, [key]: value || undefined };
            if (key === 'kod_daerah') next.kod_mukim = undefined;
            return next;
        });
        setPage(1);
    }

    const mukim = (ruj?.mukim ?? []).filter((m) => !filters.kod_daerah || m.daerah_kod === filters.kod_daerah);
    const rows = list.data?.data ?? [];
    const s = summary.data;
    const disahkan = s ? Object.entries(s.status_pengesahan).filter(([k]) => k.startsWith('disahkan')).reduce((n, [, v]) => n + Number(v), 0) : 0;
    const belum = s ? Number(s.status_pengesahan.belum_disahkan ?? 0) : 0;
    const bertindih = s ? Number(s.status_pengesahan.bertindih ?? 0) : 0;
    const activeFilterCount = Object.values(filters).filter(Boolean).length;

    function sortBy(col: string) {
        setSort((cur) => (cur === col ? `-${col}` : col));
        setPage(1);
    }
    const sortMark = (col: string) => (sort === col ? ' ▲' : sort === `-${col}` ? ' ▼' : '');

    return (
        <PageContainer>
            <div className="grid grid-cols-2 lg:grid-cols-5 gap-3">
                <StatCard label="Jumlah lot" value={s ? fmtNombor(s.jumlah, 0) : '…'} />
                <StatCard label="Geometri disahkan" value={s ? fmtNombor(disahkan, 0) : '…'} />
                <StatCard label="Belum disahkan" value={s ? fmtNombor(belum, 0) : '…'} trend={belum > 0 ? 'down' : 'neutral'} change={belum > 0 ? 'perlu semakan' : undefined} />
                <StatCard label="Bertindih" value={s ? fmtNombor(bertindih, 0) : '…'} trend={bertindih > 0 ? 'down' : 'neutral'} change={bertindih > 0 ? 'perlu tindakan' : undefined} />
                <StatCard label="Tiada geometri" value={s ? fmtNombor(s.tiada_geometri, 0) : '…'} className="col-span-2 lg:col-span-1">
                    {s && Object.keys(s.bahagian).length > 0 && (
                        <p className="text-body-xs text-txt-black-500">
                            {Object.entries(s.bahagian)
                                .map(([k, v]) => `${BAHAGIAN_LABEL[k] ?? k}: ${v}`)
                                .join(' · ')}
                        </p>
                    )}
                </StatCard>
            </div>

            <Card>
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <label htmlFor="lot-q" className="sr-only">
                        Carian lot
                    </label>
                    <input
                        id="lot-q"
                        type="search"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Cari no. lot, mukim, zoning atau tag…"
                        className={`${inputClass} lg:flex-1`}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button variant="secondary" onClick={() => setShowFilters((v) => !v)} aria-expanded={showFilters}>
                            Penapis{activeFilterCount > 0 ? ` (${activeFilterCount})` : ''}
                        </Button>
                        <div className="inline-flex rounded-md shadow-button" role="group" aria-label="Paparan">
                            {(['jadual', 'peta'] as const).map((v, i) => (
                                <button
                                    key={v}
                                    type="button"
                                    onClick={() => setView(v)}
                                    aria-pressed={view === v}
                                    className={`px-3 py-2 text-body-sm font-medium border border-otl-gray-300 ${i === 0 ? 'rounded-s-md' : 'rounded-e-md -ms-px'} ${
                                        view === v ? 'bg-primary-600 text-white border-primary-600' : 'bg-bg-white text-txt-black-900 hover:bg-bg-washed'
                                    }`}
                                >
                                    {v === 'jadual' ? 'Jadual' : 'Peta'}
                                </button>
                            ))}
                        </div>
                        {can('pro.lot.eksport') && <ExportMenu onExport={(format) => proApi.eksportLot(base, format)} />}
                        {can('pro.lot.cipta') && <LinkButton href="/admin/pro/lot/baru">Tambah lot</LinkButton>}
                    </div>
                </div>

                {showFilters && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <SelectField id="f-daerah" label="Daerah" value={filters.kod_daerah ?? ''} onChange={(v) => setFilter('kod_daerah', v)} options={ruj?.daerah ?? []} />
                        <SelectField id="f-mukim" label="Mukim" value={filters.kod_mukim ?? ''} onChange={(v) => setFilter('kod_mukim', v)} options={mukim} />
                        <SelectField
                            id="f-bahagian"
                            label="Bahagian pemilik"
                            value={filters.bahagian_pemilik ?? ''}
                            onChange={(v) => setFilter('bahagian_pemilik', v)}
                            options={ruj?.bahagian_pemilik ?? []}
                        />
                        <SelectField id="f-status" label="Status lot" value={filters.status_lot ?? ''} onChange={(v) => setFilter('status_lot', v)} options={ruj?.status_lot ?? []} />
                        <SelectField
                            id="f-spatial"
                            label="Status pengesahan spatial"
                            value={filters.status_pengesahan ?? ''}
                            onChange={(v) => setFilter('status_pengesahan', v)}
                            options={ruj?.status_pengesahan ?? []}
                        />
                        <SelectField
                            id="f-kategori"
                            label="Kategori guna tanah"
                            value={filters.kategori_guna_tanah ?? ''}
                            onChange={(v) => setFilter('kategori_guna_tanah', v)}
                            options={ruj?.kategori_guna_tanah ?? []}
                        />
                        <SelectField id="f-klas" label="Klasifikasi" value={filters.klasifikasi ?? ''} onChange={(v) => setFilter('klasifikasi', v)} options={ruj?.klasifikasi ?? []} />
                        <div>
                            <InputLabel htmlFor="f-arkib" className="mb-1.5">
                                Rekod diarkib
                            </InputLabel>
                            <select id="f-arkib" className={inputClass} value={filters.arkib ?? ''} onChange={(e) => setFilter('arkib', e.target.value)}>
                                <option value="">Kecualikan arkib</option>
                                <option value="1">Termasuk arkib</option>
                                <option value="sahaja">Arkib sahaja</option>
                            </select>
                        </div>
                        {activeFilterCount > 0 && (
                            <div className="sm:col-span-2 lg:col-span-4">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        setFilters({});
                                        setPage(1);
                                    }}
                                >
                                    Kosongkan penapis
                                </Button>
                            </div>
                        )}
                    </div>
                )}

                {list.error ? (
                    <Alert type="error">{errorMessage(list.error)}</Alert>
                ) : view === 'peta' ? (
                    <div className="space-y-2">
                        {geo.error && <Alert type="error">{errorMessage(geo.error)}</Alert>}
                        <p className="text-body-sm text-txt-black-500" aria-live="polite">
                            {geo.data ? `${geo.data.features.length} lot dengan geometri (maks. 5,000). Klik poligon untuk membuka profil.` : 'Memuatkan peta…'}
                        </p>
                        <LotMap data={geo.data} height={480} label="Peta senarai lot" onSelect={(id) => router.push(`/admin/pro/lot/${id}`)} />
                    </div>
                ) : (
                    <>
                        <p className="text-body-sm text-txt-black-500" aria-live="polite">
                            {list.data ? `${list.data.meta.total} lot ditemui` : 'Memuatkan…'}
                        </p>
                        <div className="overflow-x-auto">
                            <Table
                                head={
                                    <>
                                        <th>
                                            <button type="button" onClick={() => sortBy('no_lot')} className="font-semibold">
                                                No. Lot{sortMark('no_lot')}
                                            </button>
                                        </th>
                                        <th>Mukim / Daerah</th>
                                        <th className="hidden md:table-cell text-right">
                                            <button type="button" onClick={() => sortBy('keluasan')} className="font-semibold">
                                                Keluasan{sortMark('keluasan')}
                                            </button>
                                        </th>
                                        <th className="hidden lg:table-cell">Bahagian</th>
                                        <th className="hidden sm:table-cell">Status</th>
                                        <th>Geometri</th>
                                        <th className="hidden xl:table-cell">Klasifikasi</th>
                                        <th className="hidden xl:table-cell">
                                            <button type="button" onClick={() => sortBy('dikemas_kini')} className="font-semibold">
                                                Dikemas kini{sortMark('dikemas_kini')}
                                            </button>
                                        </th>
                                    </>
                                }
                            >
                                {list.isLoading ? (
                                    <EmptyRow colSpan={8}>Memuatkan…</EmptyRow>
                                ) : rows.length === 0 ? (
                                    <EmptyRow colSpan={8}>Tiada lot ditemui.</EmptyRow>
                                ) : (
                                    rows.map((l) => (
                                        <tr key={l.id} className={!l.spatial.disahkan ? 'bg-bg-warning-50/40' : undefined}>
                                            <td className="whitespace-nowrap">
                                                <Link href={`/admin/pro/lot/${l.id}`} className="font-medium text-txt-primary hover:underline">
                                                    {l.no_lot ?? '—'}
                                                    {l.nama_hartanah && <span className="block text-body-xs font-normal text-txt-black-500">{l.nama_hartanah}</span>}
                                                </Link>
                                                {l.sintetik && (
                                                    <span className="ms-2">
                                                        <Badge variant="neutral" shape="tag">
                                                            Sintetik
                                                        </Badge>
                                                    </span>
                                                )}
                                            </td>
                                            <td className="min-w-[10rem]">
                                                <span className="block">{l.nama_mukim ?? l.kod_mukim ?? '—'}</span>
                                                <span className="block text-body-xs text-txt-black-500">{l.nama_daerah ?? l.kod_daerah ?? ''}</span>
                                            </td>
                                            <td className="hidden md:table-cell text-right whitespace-nowrap">
                                                <Masked field="keluasan" masked={l.medan_disamarkan}>
                                                    {l.keluasan !== null ? `${fmtNombor(l.keluasan, 4)} ${l.unit_keluasan ?? ''}` : '—'}
                                                </Masked>
                                            </td>
                                            <td className="hidden lg:table-cell">
                                                <Masked field="bahagian_pemilik" masked={l.medan_disamarkan}>
                                                    {l.bahagian_pemilik ? (BAHAGIAN_LABEL[l.bahagian_pemilik] ?? l.bahagian_pemilik) : '—'}
                                                </Masked>
                                            </td>
                                            <td className="hidden sm:table-cell">
                                                <Badge variant={toneStatusLot(l.status_lot)}>{STATUS_LOT_LABEL[l.status_lot] ?? l.status_lot}</Badge>
                                            </td>
                                            <td>
                                                <SpatialBadge spatial={l.spatial} />
                                            </td>
                                            <td className="hidden xl:table-cell">
                                                <KlasifikasiBadge value={l.klasifikasi} />
                                            </td>
                                            <td className="hidden xl:table-cell whitespace-nowrap">{fmtTarikh(l.dikemas_kini_pada)}</td>
                                        </tr>
                                    ))
                                )}
                            </Table>
                        </div>
                        <Pager meta={list.data?.meta} page={page} onPage={setPage} />
                    </>
                )}
            </Card>
        </PageContainer>
    );
}
