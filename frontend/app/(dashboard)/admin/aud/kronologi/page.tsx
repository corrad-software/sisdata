'use client';

import { Suspense, useState } from 'react';
import type { FormEvent } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Button } from '@/components/ui/Button';
import { InputLabel } from '@/components/ui/InputLabel';
import { Alert } from '@/components/ui/Alert';
import { ApiError } from '@/lib/api';
import { ACTION_LABELS, CHANNEL_LABELS, fetchChronology, label } from '@/lib/aud';
import { ClassificationBadge, FieldChanges, StatusBadge } from '../_components/AuditBits';

const inputClass =
    'block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm';

function Chronology() {
    const router = useRouter();
    const params = useSearchParams();
    const entity = params.get('nama_entiti') ?? '';
    const id = params.get('id_rekod') ?? '';
    const [draft, setDraft] = useState({ entity, id });

    const { data, isLoading, error } = useQuery({
        queryKey: ['aud', 'kronologi', entity, id],
        queryFn: () => fetchChronology(entity, id),
        enabled: entity !== '' && id !== '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        const qs = new URLSearchParams({ nama_entiti: draft.entity.trim(), id_rekod: draft.id.trim() });
        router.push(`/admin/aud/kronologi?${qs.toString()}`);
    }

    const items = data?.data ?? [];

    return (
        <PageContainer>
            <form onSubmit={submit} className="bg-bg-white shadow-card rounded-lg border border-otl-gray-200 p-4 sm:p-6 grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                <div>
                    <InputLabel htmlFor="k-entiti" className="mb-1.5">
                        Entiti (nama jadual)
                    </InputLabel>
                    <input
                        id="k-entiti"
                        required
                        value={draft.entity}
                        placeholder="cth. lot_tanah"
                        onChange={(e) => setDraft((d) => ({ ...d, entity: e.target.value }))}
                        className={inputClass}
                    />
                </div>
                <div>
                    <InputLabel htmlFor="k-id" className="mb-1.5">
                        ID rekod
                    </InputLabel>
                    <input id="k-id" required value={draft.id} onChange={(e) => setDraft((d) => ({ ...d, id: e.target.value }))} className={inputClass} />
                </div>
                <div>
                    <Button type="submit">Papar kronologi</Button>
                </div>
            </form>

            {error instanceof ApiError && <Alert type="error">{error.status === 403 ? 'Anda tiada kebenaran untuk melihat kronologi audit.' : error.message}</Alert>}

            {entity && id && (
                <div className="bg-bg-white shadow-card rounded-lg border border-otl-gray-200 p-4 sm:p-6 space-y-4">
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">
                        Kronologi {entity} #{id}
                    </h2>
                    <p className="text-body-sm text-txt-black-500">
                        {isLoading
                            ? 'Memuatkan…'
                            : `${items.length} aktiviti merentas modul, susunan masa${data?.meta.termasuk_arkib ? ' (termasuk arkib)' : ''}.`}
                    </p>

                    {!isLoading && items.length === 0 && <p className="text-body-sm text-txt-black-500">Tiada aktiviti direkodkan bagi rekod ini.</p>}

                    <ol className="relative border-s border-otl-gray-200 ms-3">
                        {items.map((r) => (
                            <li key={`${r.sumber}-${r.id}`} className="mb-8 ms-6 last:mb-0">
                                <span
                                    aria-hidden
                                    className={`absolute -start-1.5 mt-1.5 h-3 w-3 rounded-full ring-4 ring-bg-white ${
                                        r.status_transaksi === 'berjaya' ? 'bg-primary-600' : 'bg-danger-600'
                                    }`}
                                />
                                <div className="flex flex-wrap items-center gap-2">
                                    <h3 className="text-body-sm font-semibold text-txt-black-900">
                                        {label(ACTION_LABELS, r.jenis_tindakan)} · {r.modul.toUpperCase()}
                                        {r.nama_entiti !== entity && (
                                            <span className="font-normal text-txt-black-500">
                                                {' '}
                                                ({r.nama_entiti} #{r.id_rekod})
                                            </span>
                                        )}
                                    </h3>
                                    <StatusBadge status={r.status_transaksi} />
                                    <ClassificationBadge value={r.klasifikasi} />
                                </div>
                                <time className="block text-body-xs text-txt-black-500 mb-2">
                                    {r.tarikh_masa_tempatan} MYT · {r.pengecam_pengguna ?? 'Sistem'}
                                    {r.peranan_semasa.length > 0 && ` (${r.peranan_semasa.join(', ')})`} · {r.alamat_ip ?? '—'} ·{' '}
                                    {label(CHANNEL_LABELS, r.saluran_capaian)}
                                </time>
                                {r.sebab_perubahan && <p className="text-body-sm text-txt-black-700 mb-2">Sebab: {r.sebab_perubahan}</p>}
                                {r.perubahan.length > 0 && <FieldChanges changes={r.perubahan} />}
                            </li>
                        ))}
                    </ol>
                </div>
            )}
        </PageContainer>
    );
}

export default function ChronologyPage() {
    return (
        <Suspense fallback={null}>
            <Chronology />
        </Suspense>
    );
}
