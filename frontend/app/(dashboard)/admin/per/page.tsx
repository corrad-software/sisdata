'use client';

import { Suspense, useEffect, useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button, LinkButton } from '@/components/ui/Button';
import { InputLabel } from '@/components/ui/InputLabel';
import { perApi, ringgit, STATUS_LABEL, tarikh, useDaerah, usePerCan, useSaluran, type PenapisKes, type StatusKes } from '@/lib/per';
import { cardClass, inputClass, SaluranChip, StatusBadge } from './_components/common';

/** Case list with channel / stage / district / status filters and channel colour chips. */
export default function SenaraiKesPage() {
    return (
        <Suspense>
            <SenaraiKes />
        </Suspense>
    );
}

function SenaraiKes() {
    const params = useSearchParams();
    const can = usePerCan();
    const [filters, setFilters] = useState<PenapisKes>(() => ({
        saluran: params.get('saluran') ?? undefined,
        status: params.get('status') ?? undefined,
        lot: params.get('lot') ?? undefined,
        lewat: params.get('lewat') ?? undefined,
    }));
    const [q, setQ] = useState('');
    const [page, setPage] = useState(1);
    const { data: saluran } = useSaluran();
    const { data: daerah } = useDaerah();

    useEffect(() => {
        const t = setTimeout(() => {
            setFilters((f) => ({ ...f, q: q || undefined }));
            setPage(1);
        }, 350);
        return () => clearTimeout(t);
    }, [q]);

    const query = { ...filters, page };
    const { data, isLoading, error } = useQuery({ queryKey: ['per', 'kes', query], queryFn: () => perApi.senarai(query) });
    const selected = saluran?.find((s) => s.kod === filters.saluran);

    function set(key: keyof PenapisKes, value: string) {
        setFilters((f) => ({ ...f, [key]: value || undefined, ...(key === 'saluran' ? { peringkat: undefined } : {}) }));
        setPage(1);
    }

    return (
        <PageContainer>
            {/* Channel chips double as the channel filter (SRS-PER-28 visual identity). */}
            <div className="flex flex-wrap gap-2" role="group" aria-label="Tapis mengikut saluran">
                <button type="button" onClick={() => set('saluran', '')} className={`rounded-full border px-3 py-1 text-body-xs font-medium ${!filters.saluran ? 'border-primary-600 bg-bg-primary-50 text-txt-primary' : 'border-otl-gray-300 text-txt-black-500'}`}>
                    Semua saluran
                </button>
                {saluran?.map((s) => (
                    <button key={s.kod} type="button" onClick={() => set('saluran', filters.saluran === s.kod ? '' : s.kod)} aria-pressed={filters.saluran === s.kod} className={`rounded-full ${filters.saluran && filters.saluran !== s.kod ? 'opacity-50' : ''}`}>
                        <SaluranChip kod={s.kod} nama={s.nama} warna={s.warna} ikon={s.ikon} />
                    </button>
                ))}
            </div>

            <div className={`${cardClass} space-y-4`}>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="sm:flex-1">
                        <label htmlFor="per-q" className="sr-only">
                            Carian
                        </label>
                        <input id="per-q" type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari no. kes, tajuk atau no. lot…" className={inputClass} />
                    </div>
                    {can('per.kes.cipta') && <LinkButton href="/admin/per/baharu">Daftar kes baharu</LinkButton>}
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <InputLabel htmlFor="f-peringkat" className="mb-1.5">
                            Peringkat semasa
                        </InputLabel>
                        <select id="f-peringkat" className={inputClass} value={filters.peringkat ?? ''} onChange={(e) => set('peringkat', e.target.value)} disabled={!selected}>
                            <option value="">{selected ? 'Semua peringkat' : 'Pilih saluran dahulu'}</option>
                            {selected?.peringkat.map((p) => (
                                <option key={p.kod} value={p.kod}>
                                    {p.nama}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <InputLabel htmlFor="f-daerah" className="mb-1.5">
                            Daerah
                        </InputLabel>
                        <select id="f-daerah" className={inputClass} value={filters.daerah ?? ''} onChange={(e) => set('daerah', e.target.value)}>
                            <option value="">Semua daerah</option>
                            {daerah?.map((d) => (
                                <option key={d.kod} value={d.kod}>
                                    {d.nama}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <InputLabel htmlFor="f-status" className="mb-1.5">
                            Status
                        </InputLabel>
                        <select id="f-status" className={inputClass} value={filters.status ?? ''} onChange={(e) => set('status', e.target.value)}>
                            <option value="">Semua status</option>
                            {(Object.keys(STATUS_LABEL) as StatusKes[]).map((s) => (
                                <option key={s} value={s}>
                                    {STATUS_LABEL[s]}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <InputLabel htmlFor="f-dokumen" className="mb-1.5">
                            Dokumen / tempoh
                        </InputLabel>
                        <select
                            id="f-dokumen"
                            className={inputClass}
                            value={filters.lewat ? 'lewat' : (filters.dokumen ?? '')}
                            onChange={(e) => {
                                const v = e.target.value;
                                setFilters((f) => ({ ...f, dokumen: v === 'tidak_lengkap' || v === 'lengkap' ? v : undefined, lewat: v === 'lewat' ? '1' : undefined }));
                                setPage(1);
                            }}
                        >
                            <option value="">Semua</option>
                            <option value="tidak_lengkap">Dokumen belum lengkap</option>
                            <option value="lengkap">Dokumen lengkap</option>
                            <option value="lewat">Melebihi tempoh sasaran</option>
                        </select>
                    </div>
                </div>

                {error ? (
                    <p className="text-body-sm text-txt-danger">{error.message}</p>
                ) : (
                    <>
                        <p className="text-body-sm text-txt-black-500" aria-live="polite">
                            {data ? `${data.meta.total} kes ditemui` : 'Memuatkan…'}
                        </p>
                        <div className="overflow-x-auto">
                            <Table
                                head={
                                    <>
                                        <th>Kes</th>
                                        <th>Saluran</th>
                                        <th className="hidden md:table-cell">Lot / Daerah</th>
                                        <th className="hidden sm:table-cell">Peringkat</th>
                                        <th>Status</th>
                                        <th className="hidden lg:table-cell text-right">Nilai</th>
                                    </>
                                }
                            >
                                {isLoading ? (
                                    <tr>
                                        <td colSpan={6} className="py-6 text-center text-txt-black-500">
                                            Memuatkan…
                                        </td>
                                    </tr>
                                ) : data?.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="py-6 text-center text-txt-black-500">
                                            Tiada kes ditemui.
                                        </td>
                                    </tr>
                                ) : (
                                    data?.data.map((k) => (
                                        <tr key={k.id}>
                                            <td className="max-w-[12rem] sm:max-w-xs">
                                                <Link href={`/admin/per/kes/${k.id}`} className="font-medium text-txt-primary hover:underline break-words">
                                                    {k.no_kes}
                                                </Link>
                                                <p className="text-body-xs text-txt-black-500 break-words line-clamp-2">{k.tajuk}</p>
                                                <p className="text-body-xs text-txt-black-500">Mula {tarikh(k.tarikh_mula_kes)}</p>
                                            </td>
                                            <td>
                                                <SaluranChip kod={k.saluran.kod} nama={k.saluran.nama} warna={k.saluran.warna} ikon={k.saluran.ikon} ringkas />
                                            </td>
                                            <td className="hidden md:table-cell">
                                                <p className="text-body-sm">
                                                    {k.lot.no_lot ?? '—'} {!k.lot.berdaftar && k.lot.no_lot && <Badge variant="warning">Dijangka</Badge>}
                                                </p>
                                                <p className="text-body-xs text-txt-black-500">{k.nama_daerah ?? '—'}</p>
                                            </td>
                                            <td className="hidden sm:table-cell">
                                                {k.peringkat_semasa ? (
                                                    <>
                                                        <p className="text-body-sm">{k.peringkat_semasa.nama}</p>
                                                        <p className={`text-body-xs ${k.peringkat_semasa.lewat ? 'text-txt-danger' : 'text-txt-black-500'}`}>
                                                            {k.peringkat_semasa.hari ?? 0}/{k.peringkat_semasa.tempoh_sasaran_hari} hari{k.peringkat_semasa.dieskalasi ? ' · dieskalasi' : ''}
                                                        </p>
                                                    </>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td>
                                                <div className="flex flex-col items-start gap-1">
                                                    <StatusBadge status={k.status} />
                                                    {k.status_kelengkapan_dokumen === 'tidak_lengkap' && k.status !== 'selesai' && k.status !== 'dibatalkan' && <Badge variant="danger">Dokumen kurang</Badge>}
                                                </div>
                                            </td>
                                            <td className="hidden lg:table-cell whitespace-nowrap text-right">{ringgit(k.nilai_pemerolehan)}</td>
                                        </tr>
                                    ))
                                )}
                            </Table>
                        </div>
                    </>
                )}

                {data && data.meta.last_page > 1 && (
                    <div className="flex flex-col sm:flex-row items-center justify-between gap-2 text-body-sm text-txt-black-500">
                        <span>
                            Memaparkan {data.meta.from} hingga {data.meta.to} daripada {data.meta.total}
                        </span>
                        <div className="flex gap-2">
                            <Button variant="secondary" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                                Sebelumnya
                            </Button>
                            <Button variant="secondary" size="sm" disabled={page >= data.meta.last_page} onClick={() => setPage((p) => p + 1)}>
                                Seterusnya
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </PageContainer>
    );
}
