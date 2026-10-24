'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Spinner } from '@/components/ui/Spinner';
import { UploadDialog } from './UploadDialog';
import { dmsApi, KLASIFIKASI_LABEL, saizMesra, STATUS_LABEL, tarikhMesra } from '@/lib/dms';

interface DocumentAttachmentsProps {
    /** Parent entity — use the table name, e.g. "lot_tanah" (same as DocumentStore::store's ownerType). */
    ownerType: string;
    ownerId: string | number;
    title?: string;
    defaultCategory?: string;
    /** Module code recorded as modul_sumber, e.g. "pro". */
    sourceModule?: string;
    /** Frontend path of this record, stored as the document's deep link back here. */
    ownerLink?: string;
    readOnly?: boolean;
}

/**
 * Reusable attachments panel for any module's record page (SRS-DMS-16/17):
 *
 *     <DocumentAttachments ownerType="lot_tanah" ownerId={lot.id} sourceModule="pro" ownerLink={`/admin/pro/lot/${lot.id}`} />
 *
 * Lists the record's documents the user may see (API filters by access),
 * links each to its DMS detail page, and uploads new ones linked to the record.
 */
export function DocumentAttachments({ ownerType, ownerId, title = 'Dokumen berkaitan', defaultCategory, sourceModule, ownerLink, readOnly }: DocumentAttachmentsProps) {
    const [open, setOpen] = useState(false);
    const queryClient = useQueryClient();
    const key = ['dms', 'rekod', ownerType, String(ownerId)];
    const { data, isLoading, error } = useQuery({ queryKey: key, queryFn: () => dmsApi.rekod(ownerType, ownerId) });
    const docs = data?.data ?? [];

    async function download(id: string) {
        try {
            const { data: link } = await dmsApi.pautan(id, 'muat_turun');
            window.location.assign(link.url);
        } catch (e) {
            toast.error(e instanceof Error ? e.message : 'Muat turun gagal.');
        }
    }

    return (
        <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-4 sm:p-6 space-y-4" aria-labelledby={`dms-att-${ownerType}-${ownerId}`}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h3 id={`dms-att-${ownerType}-${ownerId}`} className="font-heading font-semibold text-body-lg text-txt-black-900">
                    {title} {docs.length > 0 && <span className="text-txt-black-500 font-normal">({docs.length})</span>}
                </h3>
                {!readOnly && (
                    <Button size="sm" onClick={() => setOpen(true)}>
                        Lampirkan dokumen
                    </Button>
                )}
            </div>

            {isLoading ? (
                <Spinner />
            ) : error ? (
                <p className="text-body-sm text-txt-black-500">Tiada akses kepada dokumen rekod ini.</p>
            ) : docs.length === 0 ? (
                <p className="text-body-sm text-txt-black-500">Tiada dokumen dilampirkan.</p>
            ) : (
                <ul className="divide-y divide-otl-divider">
                    {docs.map((d) => (
                        <li key={d.id} className="py-3 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
                            <div className="min-w-0 flex-1">
                                <Link href={`/admin/dms/${d.id}`} className="text-body-sm font-medium text-txt-primary hover:underline break-words">
                                    {d.tajuk}
                                </Link>
                                <p className="text-body-xs text-txt-black-500 break-all">
                                    {d.no_rujukan} · {d.kategori.nama} · v{d.versi_semasa} · {saizMesra(d.saiz_bait)} · {tarikhMesra(d.dicipta_pada)}
                                </p>
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
                                {(d.klasifikasi === 'sulit' || d.klasifikasi === 'rahsia') && <Badge variant="warning">{KLASIFIKASI_LABEL[d.klasifikasi]}</Badge>}
                                {d.status !== 'aktif' && <Badge variant="neutral">{STATUS_LABEL[d.status]}</Badge>}
                                <Button size="sm" variant="secondary" onClick={() => download(d.id)}>
                                    Muat turun
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {!readOnly && (
                <UploadDialog
                    open={open}
                    onClose={() => setOpen(false)}
                    onUploaded={() => queryClient.invalidateQueries({ queryKey: key })}
                    ownerType={ownerType}
                    ownerId={ownerId}
                    defaultCategory={defaultCategory}
                    sourceModule={sourceModule}
                    ownerLink={ownerLink}
                />
            )}
        </section>
    );
}
