'use client';

import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Spinner } from '@/components/ui/Spinner';
import { Alert } from '@/components/ui/Alert';
import { dmsApi, type DokumenPenuh } from '@/lib/dms';

/**
 * SRS-DMS-02 in-browser preview: PDF and images inline via a short-lived
 * signed link (watermarked server-side for Sulit/Rahsia, SRS-DMS-26);
 * office / TIFF / spatial text files show the extracted (or OCR) text.
 */
export function DocumentPreview({ doc, version }: { doc: DokumenPenuh; version?: number }) {
    const [url, setUrl] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const inline = doc.pratonton === 'pdf' || doc.pratonton === 'imej';

    useEffect(() => {
        if (!inline || doc.status === 'dilupus') return;
        let cancelled = false;
        dmsApi
            .pautan(doc.id, 'pratonton', version)
            .then((r) => !cancelled && setUrl(r.data.url))
            .catch((e: Error) => !cancelled && setError(e.message));
        return () => {
            cancelled = true;
        };
    }, [doc.id, doc.status, inline, version]);

    const text = useQuery({
        queryKey: ['dms', 'teks', doc.id, doc.versi_semasa],
        queryFn: () => dmsApi.teks(doc.id),
        enabled: doc.pratonton === 'teks' && doc.status !== 'dilupus',
    });

    if (doc.status === 'dilupus') {
        return <Alert type="warning">Dokumen ini telah dilupuskan. Hanya metadata yang dikekalkan.</Alert>;
    }
    if (error) return <Alert type="error">{error}</Alert>;

    if (inline) {
        if (!url) {
            return (
                <div className="flex items-center justify-center h-64">
                    <Spinner />
                </div>
            );
        }
        return (
            <div className="space-y-2">
                {doc.tanda_air && (
                    <p className="text-body-xs text-txt-warning">
                        Dokumen {doc.klasifikasi.toUpperCase()}: pratonton dibubuh tanda air dengan ID anda serta tarikh dan masa.
                    </p>
                )}
                {doc.pratonton === 'pdf' ? (
                    <iframe src={url} title={`Pratonton ${doc.nama_fail}`} className="w-full h-[70vh] rounded-md border border-otl-gray-200 bg-bg-washed" />
                ) : (
                    // eslint-disable-next-line @next/next/no-img-element -- signed, short-lived API URL; next/image can't optimise it
                    <img src={url} alt={doc.tajuk} className="max-w-full max-h-[70vh] mx-auto rounded-md border border-otl-gray-200" />
                )}
            </div>
        );
    }

    if (doc.pratonton === 'teks') {
        if (text.isLoading) return <Spinner />;
        const body = text.data?.data;
        if (!body?.teks) {
            return (
                <Alert type="info">
                    {body?.status_indeks === 'menunggu'
                        ? 'Teks dokumen sedang diekstrak. Sila muat semula sebentar lagi.'
                        : 'Pratonton tidak tersedia bagi jenis fail ini. Sila muat turun untuk melihat.'}
                </Alert>
            );
        }
        return (
            <div className="space-y-2">
                <p className="text-body-xs text-txt-black-500">
                    Pratonton teks {body.kaedah === 'ocr' ? '(OCR)' : '(diekstrak)'} — format asal hanya tersedia melalui muat turun.
                </p>
                <pre className="whitespace-pre-wrap break-words text-body-sm text-txt-black-900 bg-bg-washed rounded-md p-4 max-h-[70vh] overflow-y-auto">
                    {body.teks}
                </pre>
            </div>
        );
    }

    return <Alert type="info">Pratonton tidak disokong bagi jenis fail ini (.{doc.sambungan}). Sila muat turun.</Alert>;
}
