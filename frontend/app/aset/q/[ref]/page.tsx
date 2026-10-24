'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/Badge';
import { ApiError } from '@/lib/api';
import { astApi, KONDISI_VARIANT, tarikh, type ImbasAwam } from '@/lib/ast';

/*
 * SRS-AST-08/09/10 public QR landing page — what a standard phone camera
 * opens (no app). Scanners without an account (or without permission / out
 * of scope) see minimum identification only; a logged-in officer in scope
 * gets the profile link and actions. The scan is logged by the API with the
 * device location when the user allows it.
 */
export default function ImbasQr() {
    const { ref } = useParams<{ ref: string }>();
    const [d, setD] = useState<ImbasAwam | null>(null);
    const [err, setErr] = useState<string | null>(null);

    useEffect(() => {
        let batal = false;
        const hantar = (pos: GeolocationPosition | null) =>
            astApi.imbasAwam(ref, pos ? { latitud: pos.coords.latitude, longitud: pos.coords.longitude, ketepatan_m: Math.round(pos.coords.accuracy) } : {})
                .then((r) => { if (!batal) setD(r); })
                .catch((e) => { if (!batal) setErr(e instanceof ApiError ? e.message : 'Ralat rangkaian.'); });
        if ('geolocation' in navigator) navigator.geolocation.getCurrentPosition((p) => hantar(p), () => hantar(null), { timeout: 6000, maximumAge: 60_000 });
        else void hantar(null);
        return () => { batal = true; };
    }, [ref]);

    return (
        <main className="min-h-screen bg-bg-washed px-4 py-8">
            <div className="mx-auto max-w-md space-y-4">
                <header className="text-center space-y-1">
                    <p className="text-body-xs font-semibold tracking-wide text-txt-black-500">MAJLIS AGAMA ISLAM SELANGOR</p>
                    <h1 className="font-heading text-heading-2xs font-semibold text-txt-black-900">Maklumat Aset</h1>
                </header>
                <section className="rounded-lg border border-otl-gray-200 bg-bg-white p-5 shadow-card space-y-3" aria-live="polite">
                    {!d && !err && <p className="text-body-sm text-txt-black-500">Memuatkan maklumat aset…</p>}
                    {err && <p role="alert" className="text-body-sm text-txt-danger">{err}</p>}
                    {d && (
                        <>
                            {d.paparan === 'tidak_aktif' && <p className="rounded-md bg-bg-warning-50 p-2 text-body-sm text-txt-warning">{d.mesej}</p>}
                            <div>
                                <h2 className="font-heading text-body-lg font-semibold text-txt-black-900">{d.nama_aset}</h2>
                                <p className="font-mono text-body-sm text-txt-black-700">{d.no_rujukan_aset}</p>
                            </div>
                            <dl className="grid grid-cols-2 gap-3 text-body-sm">
                                <div><dt className="text-body-xs text-txt-black-500">Kategori</dt><dd>{d.kategori ?? '—'}</dd></div>
                                <div><dt className="text-body-xs text-txt-black-500">Jenis</dt><dd>{d.jenis}</dd></div>
                                <div><dt className="text-body-xs text-txt-black-500">Lokasi</dt><dd>{d.mukim}, {d.daerah}</dd></div>
                                <div><dt className="text-body-xs text-txt-black-500">Status</dt><dd>{d.status}</dd></div>
                                <div className="col-span-2"><dt className="text-body-xs text-txt-black-500">Pemilik</dt><dd>{d.pemilik}</dd></div>
                                {d.paparan === 'penuh' && (
                                    <>
                                        <div><dt className="text-body-xs text-txt-black-500">Kondisi</dt><dd><Badge variant={KONDISI_VARIANT[d.status_kondisi ?? ''] ?? 'neutral'}>{d.kondisi_nama}</Badge></dd></div>
                                        <div><dt className="text-body-xs text-txt-black-500">Pemeriksaan terakhir</dt><dd>{tarikh(d.tarikh_pemeriksaan_terakhir)}</dd></div>
                                    </>
                                )}
                            </dl>
                            {d.paparan !== 'penuh' && d.paparan !== 'tidak_aktif' && <p className="text-body-xs text-txt-black-500">{d.mesej}</p>}
                            {d.paparan === 'penuh' ? (
                                <div className="flex flex-col gap-2 pt-2">
                                    {d.pautan && <Link href={d.pautan} className="rounded-md bg-primary-600 px-4 py-2 text-center text-body-sm font-medium text-white">Buka profil aset</Link>}
                                    {d.tindakan?.includes('periksa_lapangan') && <Link href={`/admin/ast/lapangan?qr=${ref}`} className="rounded-md border border-otl-gray-300 px-4 py-2 text-center text-body-sm font-medium">Periksa dalam aplikasi lapangan</Link>}
                                    {d.tindakan?.includes('rekod_pemeriksaan') && d.pautan && <Link href={d.pautan} className="rounded-md border border-otl-gray-300 px-4 py-2 text-center text-body-sm font-medium">Rekod pemeriksaan / lapor kerosakan</Link>}
                                </div>
                            ) : !d.log_masuk && (
                                <p className="text-body-sm">Pegawai MAIS? <Link href="/login" className="text-txt-primary underline">Log masuk</Link> untuk maklumat penuh.</p>
                            )}
                        </>
                    )}
                </section>
                <p className="text-center text-body-xs text-txt-black-500">Imbasan ini direkodkan bagi tujuan keselamatan aset.</p>
            </div>
        </main>
    );
}
