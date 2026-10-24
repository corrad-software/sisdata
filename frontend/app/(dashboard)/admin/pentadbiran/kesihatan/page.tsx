'use client';

import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { StatCard } from '@/components/ui/StatCard';
import { Table } from '@/components/ui/Table';
import { Alert } from '@/components/ui/Alert';
import { errorText, fetchKesihatan, formatBytes, formatDateTime, STATUS_LABEL, statusVariant } from '@/lib/adm';

/* SRS-ADM-16: single health screen. Auto-refreshes every 30 s. */
export default function KesihatanPage() {
    const { data, isFetching, refetch, error } = useQuery({
        queryKey: ['adm', 'kesihatan'],
        queryFn: () => fetchKesihatan(),
        refetchInterval: 30000,
    });
    const h = data?.data;

    return (
        <PageContainer>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <span className="text-body-sm text-txt-black-500">Status keseluruhan</span>
                    {h && (
                        <Badge variant={statusVariant(h.status_keseluruhan)}>{STATUS_LABEL[h.status_keseluruhan] ?? h.status_keseluruhan}</Badge>
                    )}
                </div>
                <div className="flex items-center gap-3 text-body-xs text-txt-black-500">
                    <span>
                        Dijana {formatDateTime(h?.dijana_pada)} · semakan berjadual terakhir {formatDateTime(h?.semakan_terakhir)}
                    </span>
                    <Button size="sm" variant="secondary" onClick={() => fetchKesihatan(true).then(() => refetch())} disabled={isFetching}>
                        Muat semula
                    </Button>
                </div>
            </div>

            {error && <Alert type="error">{errorText(error)}</Alert>}

            {h && (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard label="Baris gilir (kerja menunggu)" value={String(h.baris_gilir?.saiz ?? '—')}>
                            <p className="text-body-xs text-txt-black-500">
                                Ambang {h.baris_gilir?.ambang ?? '—'} · gagal {h.baris_gilir?.gagal ?? 0} · Horizon{' '}
                                {h.baris_gilir?.horizon_aktif === null || h.baris_gilir?.horizon_aktif === undefined ? 'n/a' : h.baris_gilir.horizon_aktif ? 'aktif' : 'tidak aktif'}
                            </p>
                        </StatCard>
                        <StatCard label="Storan pelayan digunakan" value={h.storan?.guna_peratus !== undefined ? `${h.storan.guna_peratus}%` : '—'}>
                            <p className="text-body-xs text-txt-black-500">
                                Bebas {formatBytes(h.storan?.bebas_bait)} daripada {formatBytes(h.storan?.jumlah_bait)} · ambang {h.storan?.ambang ?? '—'}%
                            </p>
                        </StatCard>
                        <StatCard label="Saiz pangkalan data" value={formatBytes(h.pangkalan_data?.saiz_bait)}>
                            <p className="text-body-xs text-txt-black-500">
                                Sambungan {h.pangkalan_data?.sambungan ?? '—'} / {h.pangkalan_data?.sambungan_maks ?? '—'}
                            </p>
                        </StatCard>
                        <StatCard label="Integrasi bermasalah" value={String(h.integrasi.filter((i) => i.status_kesihatan === 'gagal' || i.status_kesihatan === 'amaran').length)}>
                            <p className="text-body-xs text-txt-black-500">daripada {h.integrasi.length} integrasi</p>
                        </StatCard>
                    </div>

                    <section aria-labelledby="perkhidmatan" className="space-y-2">
                        <h3 id="perkhidmatan" className="font-heading font-semibold text-body-lg text-txt-black-900">
                            Perkhidmatan
                        </h3>
                        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {h.perkhidmatan.map((s) => (
                                <li key={s.kod} className="flex items-start justify-between gap-3 rounded-lg border border-otl-gray-200 bg-bg-white p-3">
                                    <div className="min-w-0">
                                        <p className="text-body-sm font-medium text-txt-black-900">{s.label}</p>
                                        <p className="text-body-xs text-txt-black-500 break-words">
                                            {s.masa_ms} ms{s.mesej ? ` · ${s.mesej}` : ''}
                                        </p>
                                    </div>
                                    <Badge variant={statusVariant(s.status)}>{STATUS_LABEL[s.status] ?? s.status}</Badge>
                                </li>
                            ))}
                        </ul>
                    </section>

                    <section aria-labelledby="integrasi" className="space-y-2">
                        <h3 id="integrasi" className="font-heading font-semibold text-body-lg text-txt-black-900">
                            Integrasi
                        </h3>
                        <Table
                            head={
                                <>
                                    <th>Integrasi</th>
                                    <th>Status</th>
                                    <th>Gagal berturut</th>
                                    <th>Berjaya terakhir</th>
                                    <th>Gagal terakhir</th>
                                </>
                            }
                        >
                            {h.integrasi.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="text-center text-txt-black-500">Tiada integrasi didaftarkan.</td>
                                </tr>
                            ) : (
                                h.integrasi.map((i) => (
                                    <tr key={i.kod}>
                                        <td>{i.nama}</td>
                                        <td>
                                            <Badge variant={statusVariant(i.status_kesihatan)}>{STATUS_LABEL[i.status_kesihatan] ?? i.status_kesihatan}</Badge>
                                        </td>
                                        <td>{i.kegagalan_berturut}</td>
                                        <td className="whitespace-nowrap">{formatDateTime(i.terakhir_berjaya_pada)}</td>
                                        <td className="whitespace-nowrap">{formatDateTime(i.terakhir_gagal_pada)}</td>
                                    </tr>
                                ))
                            )}
                        </Table>
                    </section>

                    <section aria-labelledby="ralat" className="space-y-2">
                        <div className="flex items-center justify-between">
                            <h3 id="ralat" className="font-heading font-semibold text-body-lg text-txt-black-900">
                                Ralat terkini
                            </h3>
                            <Link href="/admin/pentadbiran/log-ralat" className="text-body-sm text-primary-600 hover:underline">
                                Lihat semua log
                            </Link>
                        </div>
                        <Table
                            head={
                                <>
                                    <th>Masa</th>
                                    <th>Modul</th>
                                    <th>Kod</th>
                                    <th>Mesej</th>
                                </>
                            }
                        >
                            {h.ralat_terkini.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="text-center text-txt-black-500">Tiada ralat.</td>
                                </tr>
                            ) : (
                                h.ralat_terkini.map((l) => (
                                    <tr key={l.id}>
                                        <td className="whitespace-nowrap">{formatDateTime(l.tarikh_masa)}</td>
                                        <td>{l.modul ?? '—'}</td>
                                        <td className="font-mono text-body-xs">{l.kod_ralat ?? '—'}</td>
                                        <td className="max-w-md truncate">
                                            <Link href={`/admin/pentadbiran/log-ralat?id_korelasi=${l.id_korelasi ?? ''}`} className="hover:underline">
                                                {l.mesej}
                                            </Link>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </Table>
                    </section>
                </>
            )}
        </PageContainer>
    );
}
