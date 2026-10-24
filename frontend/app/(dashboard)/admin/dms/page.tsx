'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { PageHeader } from '@/components/PageHeader';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button, LinkButton } from '@/components/ui/Button';
import { InputLabel } from '@/components/ui/InputLabel';
import { UploadDialog } from '@/components/dms/UploadDialog';
import { cariDokumen, dmsApi, KLASIFIKASI_LABEL, saizMesra, STATUS_LABEL, tarikhMesra, type CarianDokumen, type Klasifikasi, type StatusDokumen } from '@/lib/dms';

const inputClass =
    'block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm';

const statusVariant: Record<StatusDokumen, 'success' | 'warning' | 'neutral' | 'danger'> = {
    aktif: 'success',
    tamat: 'warning',
    diarkib: 'neutral',
    dilupus: 'danger',
};

/** Render ts_headline output («…» marks the match) without injecting HTML. */
function Petikan({ text }: { text: string }) {
    const parts = text.split(/(«[^»]*»)/g);
    return (
        <p className="mt-1 text-body-xs text-txt-black-500 line-clamp-2">
            {parts.map((p, i) =>
                p.startsWith('«') ? (
                    <mark key={i} className="bg-bg-warning-50 text-txt-black-900 rounded px-0.5">
                        {p.slice(1, -1)}
                    </mark>
                ) : (
                    <span key={i}>{p}</span>
                ),
            )}
        </p>
    );
}

export default function DmsLibraryPage() {
    const [q, setQ] = useState('');
    const [filters, setFilters] = useState<CarianDokumen>({});
    const [debouncedQ, setDebouncedQ] = useState('');
    const [page, setPage] = useState(1);
    const [uploadOpen, setUploadOpen] = useState(false);
    const [showFilters, setShowFilters] = useState(false);
    const queryClient = useQueryClient();

    useEffect(() => {
        const t = setTimeout(() => {
            setDebouncedQ(q);
            setPage(1);
        }, 350);
        return () => clearTimeout(t);
    }, [q]);

    const params = { ...filters, q: debouncedQ, page };
    const { data, isLoading, error } = useQuery({ queryKey: ['dms', 'dokumen', params], queryFn: () => cariDokumen(params) });
    const { data: kategori } = useQuery({ queryKey: ['dms', 'kategori'], queryFn: () => dmsApi.kategori() });

    const docs = data?.data ?? [];
    const meta = data?.meta;

    function setFilter(key: keyof CarianDokumen, value: string) {
        setFilters((f) => ({ ...f, [key]: value || undefined }));
        setPage(1);
    }

    return (
        <>
            <PageHeader title="Dokumen Digital" breadcrumbs={[{ label: 'Dokumen Digital' }]} />

            <PageContainer>
                <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-4 sm:p-6 space-y-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <label htmlFor="dms-q" className="sr-only">
                            Carian dokumen
                        </label>
                        <input
                            id="dms-q"
                            type="search"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Cari tajuk, no. rujukan, kata kunci atau kandungan dokumen…"
                            className={`${inputClass} sm:flex-1`}
                        />
                        <div className="flex gap-2">
                            <Button variant="secondary" onClick={() => setShowFilters((s) => !s)} aria-expanded={showFilters}>
                                Penapis
                            </Button>
                            <Button onClick={() => setUploadOpen(true)}>Muat naik</Button>
                            <LinkButton variant="outline" href="/admin/dms/tetapan">
                                Tetapan
                            </LinkButton>
                        </div>
                    </div>

                    {showFilters && (
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div>
                                <InputLabel htmlFor="f-kategori" className="mb-1.5">
                                    Kategori
                                </InputLabel>
                                <select id="f-kategori" className={inputClass} value={filters.kategori ?? ''} onChange={(e) => setFilter('kategori', e.target.value)}>
                                    <option value="">Semua</option>
                                    {kategori?.data.map((k) => (
                                        <option key={k.kod} value={k.kod}>
                                            {k.nama}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <InputLabel htmlFor="f-klasifikasi" className="mb-1.5">
                                    Klasifikasi
                                </InputLabel>
                                <select id="f-klasifikasi" className={inputClass} value={filters.klasifikasi ?? ''} onChange={(e) => setFilter('klasifikasi', e.target.value)}>
                                    <option value="">Semua</option>
                                    {(Object.keys(KLASIFIKASI_LABEL) as Klasifikasi[]).map((k) => (
                                        <option key={k} value={k}>
                                            {KLASIFIKASI_LABEL[k]}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <InputLabel htmlFor="f-status" className="mb-1.5">
                                    Status
                                </InputLabel>
                                <select id="f-status" className={inputClass} value={filters.status ?? ''} onChange={(e) => setFilter('status', e.target.value)}>
                                    <option value="">Semua (kecuali dilupus)</option>
                                    {(Object.keys(STATUS_LABEL) as StatusDokumen[]).map((s) => (
                                        <option key={s} value={s}>
                                            {STATUS_LABEL[s]}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            {(
                                [
                                    ['tahun', 'Tahun', 'number'],
                                    ['kod_daerah', 'Kod daerah', 'text'],
                                    ['kod_mukim', 'Kod mukim', 'text'],
                                    ['modul_sumber', 'Modul sumber', 'text'],
                                    ['tarikh_dari', 'Tarikh dokumen dari', 'date'],
                                    ['tarikh_hingga', 'Tarikh dokumen hingga', 'date'],
                                ] as const
                            ).map(([key, label, type]) => (
                                <div key={key}>
                                    <InputLabel htmlFor={`f-${key}`} className="mb-1.5">
                                        {label}
                                    </InputLabel>
                                    <input id={`f-${key}`} type={type} className={inputClass} value={String(filters[key] ?? '')} onChange={(e) => setFilter(key, e.target.value)} />
                                </div>
                            ))}
                        </div>
                    )}

                    {error ? (
                        <p className="text-body-sm text-txt-danger">{error.message}</p>
                    ) : (
                        <>
                            <p className="text-body-sm text-txt-black-500" aria-live="polite">
                                {meta ? `${meta.total} dokumen ditemui` : 'Memuatkan…'}
                            </p>
                            <div className="overflow-x-auto">
                                <Table
                                    head={
                                        <>
                                            <th>Dokumen</th>
                                            <th className="hidden md:table-cell">Kategori</th>
                                            <th>Klasifikasi</th>
                                            <th className="hidden sm:table-cell">Status</th>
                                            <th className="hidden lg:table-cell">Saiz</th>
                                            <th className="hidden lg:table-cell">Dimuat naik</th>
                                        </>
                                    }
                                >
                                    {isLoading ? (
                                        <tr>
                                            <td colSpan={6} className="text-center text-txt-black-500 py-6">
                                                Memuatkan…
                                            </td>
                                        </tr>
                                    ) : docs.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="text-center text-txt-black-500 py-6">
                                                Tiada dokumen ditemui.
                                            </td>
                                        </tr>
                                    ) : (
                                        docs.map((d) => (
                                            <tr key={d.id}>
                                                <td className="max-w-[16rem] sm:max-w-md">
                                                    <Link href={`/admin/dms/${d.id}`} className="font-medium text-txt-primary hover:underline break-words">
                                                        {d.tajuk}
                                                    </Link>
                                                    <p className="text-body-xs text-txt-black-500 break-all">
                                                        {d.no_rujukan} · {d.nama_fail} · v{d.versi_semasa}
                                                    </p>
                                                    {d.petikan && <Petikan text={d.petikan} />}
                                                </td>
                                                <td className="hidden md:table-cell">{d.kategori.nama}</td>
                                                <td>
                                                    <Badge variant={d.klasifikasi === 'rahsia' ? 'danger' : d.klasifikasi === 'sulit' ? 'warning' : 'info'}>
                                                        {KLASIFIKASI_LABEL[d.klasifikasi]}
                                                    </Badge>
                                                </td>
                                                <td className="hidden sm:table-cell">
                                                    <Badge variant={statusVariant[d.status]}>{STATUS_LABEL[d.status]}</Badge>
                                                </td>
                                                <td className="hidden lg:table-cell whitespace-nowrap">{saizMesra(d.saiz_bait)}</td>
                                                <td className="hidden lg:table-cell whitespace-nowrap">{tarikhMesra(d.dicipta_pada)}</td>
                                            </tr>
                                        ))
                                    )}
                                </Table>
                            </div>
                        </>
                    )}

                    {meta && meta.last_page > 1 && (
                        <div className="flex flex-col sm:flex-row items-center justify-between gap-2 text-body-sm text-txt-black-500">
                            <span>
                                Memaparkan {meta.from} hingga {meta.to} daripada {meta.total}
                            </span>
                            <div className="flex gap-2">
                                <Button variant="secondary" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                                    Sebelumnya
                                </Button>
                                <Button variant="secondary" size="sm" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>
                                    Seterusnya
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </PageContainer>

            <UploadDialog open={uploadOpen} onClose={() => setUploadOpen(false)} onUploaded={() => queryClient.invalidateQueries({ queryKey: ['dms', 'dokumen'] })} />
        </>
    );
}
