'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Pagination } from '@/components/ui/Pagination';
import {
    actOnNotifications,
    fetchInbox,
    fetchInboxOptions,
    KEUTAMAAN_BADGE,
    KEUTAMAAN_LABEL,
    masaRelatif,
    tarikhMasa,
    type InboxFilters,
    type Tindakan,
} from '@/lib/ntf';

const selectClass =
    'block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm';

export default function InboxPage() {
    const [filters, setFilters] = useState<InboxFilters>({ folder: 'peti', status: '', page: 1 });
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState<number[]>([]);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const queryClient = useQueryClient();

    useEffect(() => {
        const t = setTimeout(() => setFilters((f) => (f.q === search ? f : { ...f, q: search, page: 1 })), 400);
        return () => clearTimeout(t);
    }, [search]);

    const { data, isLoading, isError } = useQuery({ queryKey: ['ntf', 'peti', filters], queryFn: () => fetchInbox(filters) });
    const { data: options } = useQuery({ queryKey: ['ntf', 'pilihan'], queryFn: fetchInboxOptions, staleTime: 60_000 });

    const items = data?.data ?? [];
    const meta = data?.meta;
    const archive = filters.folder === 'arkib';

    const action = useMutation({
        mutationFn: ({ tindakan, ids }: { tindakan: Tindakan; ids: number[] | 'semua' }) => actOnNotifications(tindakan, ids),
        onSuccess: (res) => {
            queryClient.invalidateQueries({ queryKey: ['ntf'] });
            setSelected([]);
            setConfirmDelete(false);
            toast.success(`${res.message} (${res.data.dikemas_kini})`);
        },
        onError: () => toast.error('Tindakan tidak berjaya. Sila cuba lagi.'),
    });

    function update(patch: Partial<InboxFilters>) {
        setSelected([]);
        setFilters((f) => ({ ...f, ...patch, page: 1 }));
    }

    function toggle(id: number) {
        setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));
    }

    const allSelected = items.length > 0 && items.every((i) => selected.includes(i.id));

    return (
        <PageContainer>
            <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-4 sm:p-6 space-y-4">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="inline-flex rounded-md border border-otl-gray-300 p-0.5 self-start" role="group" aria-label="Folder">
                        {(['peti', 'arkib'] as const).map((folder) => (
                            <button
                                key={folder}
                                type="button"
                                onClick={() => update({ folder })}
                                aria-pressed={filters.folder === folder}
                                className={`rounded px-3 py-1.5 text-body-sm font-medium ${
                                    filters.folder === folder ? 'bg-primary-600 text-white' : 'text-txt-black-700 hover:bg-bg-washed'
                                }`}
                            >
                                {folder === 'peti' ? 'Peti masuk' : 'Arkib'}
                            </button>
                        ))}
                    </div>

                    <input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari subjek atau kandungan…"
                        aria-label="Cari notifikasi"
                        className={`${selectClass} lg:w-72`}
                    />
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    <label className="text-body-xs text-txt-black-500 space-y-1">
                        <span>Status bacaan</span>
                        <select className={selectClass} value={filters.status ?? ''} onChange={(e) => update({ status: e.target.value as InboxFilters['status'] })}>
                            <option value="">Semua</option>
                            <option value="belum_baca">Belum dibaca</option>
                            <option value="dibaca">Sudah dibaca</option>
                        </select>
                    </label>
                    <label className="text-body-xs text-txt-black-500 space-y-1">
                        <span>Jenis</span>
                        <select className={selectClass} value={filters.jenis ?? ''} onChange={(e) => update({ jenis: e.target.value })}>
                            <option value="">Semua jenis</option>
                            {options?.data.jenis.map((j) => (
                                <option key={j.value} value={j.value}>
                                    {j.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="text-body-xs text-txt-black-500 space-y-1">
                        <span>Keutamaan</span>
                        <select className={selectClass} value={filters.keutamaan ?? ''} onChange={(e) => update({ keutamaan: e.target.value })}>
                            <option value="">Semua</option>
                            {options?.data.keutamaan.map((k) => (
                                <option key={k.value} value={k.value}>
                                    {k.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="text-body-xs text-txt-black-500 space-y-1">
                        <span>Modul</span>
                        <select className={selectClass} value={filters.modul ?? ''} onChange={(e) => update({ modul: e.target.value })}>
                            <option value="">Semua modul</option>
                            {options?.data.modul.map((m) => (
                                <option key={m} value={m}>
                                    {m.toUpperCase()}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="text-body-xs text-txt-black-500 space-y-1">
                        <span>Dari tarikh</span>
                        <input type="date" className={selectClass} value={filters.dari ?? ''} onChange={(e) => update({ dari: e.target.value })} />
                    </label>
                    <label className="text-body-xs text-txt-black-500 space-y-1">
                        <span>Hingga tarikh</span>
                        <input type="date" className={selectClass} value={filters.hingga ?? ''} onChange={(e) => update({ hingga: e.target.value })} />
                    </label>
                </div>

                <div className="flex flex-wrap items-center gap-2 border-t border-otl-divider pt-4">
                    <label className="me-2 inline-flex items-center gap-2 text-body-sm text-txt-black-700">
                        <input
                            type="checkbox"
                            className="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary"
                            checked={allSelected}
                            onChange={() => setSelected(allSelected ? [] : items.map((i) => i.id))}
                        />
                        Pilih semua
                    </label>
                    <Button size="sm" variant="secondary" disabled={!selected.length || action.isPending} onClick={() => action.mutate({ tindakan: 'baca', ids: selected })}>
                        Tanda dibaca
                    </Button>
                    <Button size="sm" variant="secondary" disabled={!selected.length || action.isPending} onClick={() => action.mutate({ tindakan: 'belum_baca', ids: selected })}>
                        Tanda belum dibaca
                    </Button>
                    <Button
                        size="sm"
                        variant="secondary"
                        disabled={!selected.length || action.isPending}
                        onClick={() => action.mutate({ tindakan: archive ? 'nyaharkib' : 'arkib', ids: selected })}
                    >
                        {archive ? 'Keluarkan dari arkib' : 'Arkibkan'}
                    </Button>
                    <Button size="sm" variant="danger" disabled={!selected.length || action.isPending} onClick={() => setConfirmDelete(true)}>
                        Padam
                    </Button>
                    {!archive && (
                        <button
                            type="button"
                            className="ms-auto text-body-sm font-medium text-primary-600 hover:underline"
                            onClick={() => action.mutate({ tindakan: 'baca', ids: 'semua' })}
                        >
                            Tanda semua dibaca
                        </button>
                    )}
                </div>

                <ul className="divide-y divide-otl-divider rounded-lg border border-otl-gray-200" aria-label="Senarai notifikasi">
                    {isLoading ? (
                        <li className="p-6 text-center text-body-sm text-txt-black-500">Memuatkan…</li>
                    ) : isError ? (
                        <li className="p-6 text-center text-body-sm text-txt-danger">Senarai notifikasi tidak dapat dimuatkan.</li>
                    ) : items.length === 0 ? (
                        <li className="p-6 text-center text-body-sm text-txt-black-500">
                            {archive ? 'Tiada notifikasi dalam arkib.' : 'Tiada notifikasi.'}
                        </li>
                    ) : (
                        items.map((item) => (
                            <li key={item.id} className={`flex gap-3 p-4 ${item.dibaca ? '' : 'bg-bg-primary-50'}`}>
                                <input
                                    type="checkbox"
                                    className="mt-1 rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary"
                                    checked={selected.includes(item.id)}
                                    onChange={() => toggle(item.id)}
                                    aria-label={`Pilih: ${item.subjek}`}
                                />
                                <div className="min-w-0 flex-1 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant={KEUTAMAAN_BADGE[item.keutamaan]}>{KEUTAMAAN_LABEL[item.keutamaan]}</Badge>
                                        <span className="text-body-xs text-txt-black-500">{item.jenis.nama}</span>
                                        {!item.dibaca && <Badge variant="info" shape="tag">Belum dibaca</Badge>}
                                        {item.dieskalasi && <Badge variant="warning" shape="tag">Dieskalasi</Badge>}
                                    </div>
                                    <p className={`text-body-sm text-txt-black-900 break-words ${item.dibaca ? '' : 'font-semibold'}`}>{item.subjek}</p>
                                    <p className="text-body-sm text-txt-black-700 whitespace-pre-line break-words line-clamp-3">{item.kandungan}</p>
                                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 pt-1 text-body-xs">
                                        <time dateTime={item.created_at} title={tarikhMasa(item.created_at)} className="text-txt-black-500">
                                            {masaRelatif(item.created_at)}
                                        </time>
                                        {item.pautan && (
                                            <Link
                                                href={item.pautan}
                                                onClick={() => !item.dibaca && action.mutate({ tindakan: 'baca', ids: [item.id] })}
                                                className="font-medium text-primary-600 hover:underline"
                                            >
                                                Buka rekod
                                            </Link>
                                        )}
                                        <button
                                            type="button"
                                            className="font-medium text-primary-600 hover:underline"
                                            onClick={() => action.mutate({ tindakan: item.dibaca ? 'belum_baca' : 'baca', ids: [item.id] })}
                                        >
                                            {item.dibaca ? 'Tanda belum dibaca' : 'Tanda dibaca'}
                                        </button>
                                    </div>
                                </div>
                            </li>
                        ))
                    )}
                </ul>

                {meta && meta.last_page > 1 && (
                    <Pagination
                        currentPage={meta.current_page}
                        lastPage={meta.last_page}
                        onPageChange={(page) => setFilters((f) => ({ ...f, page }))}
                        firstItem={meta.from ?? 0}
                        lastItem={meta.to ?? 0}
                        total={meta.total}
                    />
                )}
            </div>

            <Modal open={confirmDelete} onClose={() => setConfirmDelete(false)}>
                <div className="p-6">
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Padam {selected.length} notifikasi?</h2>
                    <p className="mt-1 text-body-sm text-txt-black-500">
                        Notifikasi dibuang daripada senarai anda sahaja. Rekod penghantaran kekal dalam log audit.
                    </p>
                    <div className="mt-6 flex justify-end gap-3">
                        <Button variant="secondary" onClick={() => setConfirmDelete(false)}>
                            Batal
                        </Button>
                        <Button variant="danger" disabled={action.isPending} onClick={() => action.mutate({ tindakan: 'padam', ids: selected })}>
                            Padam
                        </Button>
                    </div>
                </div>
            </Modal>
        </PageContainer>
    );
}
