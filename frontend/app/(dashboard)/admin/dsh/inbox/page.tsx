'use client';

import Link from 'next/link';
import { Suspense } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useRouter, useSearchParams } from 'next/navigation';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Spinner } from '@/components/ui/Spinner';
import { Table } from '@/components/ui/Table';
import { dshApi, errorMessage, fmtMasa } from '@/lib/dsh';
import { SlaBadge } from '../_components/PanelWidgets';

const TABS = { tugasan: 'Tugasan', notifikasi: 'Notifikasi', draf: 'Draf transaksi' } as const;
type Tab = keyof typeof TABS;

/** Task inbox (SRS-DSH-23) and transaction drafts (SRS-DSH-22). */
function Inbox() {
    const params = useSearchParams();
    const router = useRouter();
    const tab = ((params.get('tab') as Tab | null) ?? 'tugasan') in TABS ? ((params.get('tab') as Tab | null) ?? 'tugasan') : 'tugasan';
    const qc = useQueryClient();
    const inbox = useQuery({ queryKey: ['dsh', 'inbox'], queryFn: dshApi.inbox });
    const draf = useQuery({ queryKey: ['dsh', 'draf'], queryFn: dshApi.draf, enabled: tab === 'draf' });
    const buang = useMutation({
        mutationFn: (id: string) => dshApi.padamDraf(id),
        onSuccess: (r) => {
            toast.success(r.message);
            qc.invalidateQueries({ queryKey: ['dsh', 'draf'] });
        },
        onError: (e) => toast.error(errorMessage(e)),
    });
    const r = inbox.data?.ringkasan;

    return (
        <>
            {r && (
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    {[
                        ['Tugasan menunggu', r.tugasan],
                        ['Melepasi SLA', r.lewat],
                        ['Hampir tamat SLA', r.hampir],
                        ['Notifikasi belum dibaca', r.notifikasi],
                    ].map(([l, v]) => (
                        <div key={l as string} className="rounded-lg border border-otl-gray-200 bg-bg-white p-4 shadow-card">
                            <p className="text-body-xs text-txt-black-500">{l}</p>
                            <p className="font-heading text-heading-xs font-semibold text-txt-black-900">{v}</p>
                        </div>
                    ))}
                </div>
            )}
            <div className="border-b border-otl-divider">
                <nav className="-mb-px flex flex-wrap gap-6" aria-label="Bahagian inbox">
                    {(Object.keys(TABS) as Tab[]).map((k) => (
                        <button
                            key={k}
                            type="button"
                            onClick={() => router.replace(`/admin/dsh/inbox?tab=${k}`, { scroll: false })}
                            aria-current={tab === k ? 'page' : undefined}
                            className={`whitespace-nowrap border-b-2 py-3 text-body-sm font-medium ${tab === k ? 'border-primary-600 text-primary-600' : 'border-transparent text-txt-black-500 hover:text-txt-black-900'}`}
                        >
                            {TABS[k]}
                        </button>
                    ))}
                </nav>
            </div>
            {inbox.isError && <Alert type="error">{errorMessage(inbox.error)}</Alert>}
            {inbox.isLoading ? (
                <div className="flex justify-center py-10">
                    <Spinner />
                </div>
            ) : tab === 'tugasan' ? (
                <>
                    <Table head={<><th>Tugasan</th><th>Modul</th><th>Tarikh akhir</th><th>Status SLA</th><th>Tindakan</th></>}>
                        {(inbox.data?.tugasan ?? []).length === 0 ? (
                            <tr>
                                <td colSpan={5} className="text-center text-txt-black-500">Tiada tugasan menunggu tindakan anda.</td>
                            </tr>
                        ) : (
                            inbox.data!.tugasan.map((t) => (
                                <tr key={t.id}>
                                    <td>
                                        <p className="font-medium">{t.tajuk}</p>
                                        <p className="text-body-xs text-txt-black-500">{t.langkah} · diterima {fmtMasa(t.diterima_pada)}</p>
                                    </td>
                                    <td>{t.modul.toUpperCase()}</td>
                                    <td className="whitespace-nowrap">{fmtMasa(t.tarikh_akhir)}</td>
                                    <td><SlaBadge status={t.status_sla} /></td>
                                    <td className="whitespace-nowrap space-x-3">
                                        <Link href={t.pautan} className="text-txt-primary hover:underline">Ambil tindakan</Link>
                                        {t.pautan_rekod && <Link href={t.pautan_rekod} className="text-txt-primary hover:underline">Rekod</Link>}
                                    </td>
                                </tr>
                            ))
                        )}
                    </Table>
                    <p className="text-body-xs text-txt-black-500">
                        Tindakan kelulusan dibuat di papan tugasan Workflow (MFA dikuatkuasakan). <Link href="/admin/wfe/tugasan" className="text-txt-primary hover:underline">Papan tugasan penuh</Link>
                    </p>
                </>
            ) : tab === 'notifikasi' ? (
                <ul className="divide-y divide-otl-divider rounded-lg border border-otl-gray-200 bg-bg-white">
                    {(inbox.data?.notifikasi ?? []).length === 0 && <li className="p-4 text-body-sm text-txt-black-500">Tiada notifikasi belum dibaca.</li>}
                    {(inbox.data?.notifikasi ?? []).map((n) => (
                        <li key={n.id} className="flex flex-wrap items-center justify-between gap-2 p-4">
                            <span className="flex items-center gap-2">
                                <Badge variant={n.keutamaan === 'kritikal' ? 'danger' : n.keutamaan === 'tinggi' ? 'warning' : 'neutral'}>{n.keutamaan}</Badge>
                                {n.pautan ? <Link href={n.pautan} className="text-body-sm text-txt-primary hover:underline">{n.subjek}</Link> : <span className="text-body-sm">{n.subjek}</span>}
                            </span>
                            <span className="text-body-xs text-txt-black-500">{fmtMasa(n.tarikh)}</span>
                        </li>
                    ))}
                    <li className="p-4 text-body-xs"><Link href="/admin/notifikasi" className="text-txt-primary hover:underline">Semua notifikasi</Link></li>
                </ul>
            ) : draf.isLoading ? (
                <Spinner />
            ) : (
                <Table head={<><th>Draf</th><th>Modul</th><th>Disimpan</th><th>Tindakan</th></>}>
                    {(draf.data ?? []).length === 0 ? (
                        <tr>
                            <td colSpan={4} className="text-center text-txt-black-500">Tiada draf disimpan.</td>
                        </tr>
                    ) : (
                        draf.data!.map((d) => (
                            <tr key={d.id} className={params.get('draf') === d.id ? 'bg-bg-primary-50' : ''}>
                                <td>
                                    <p className="font-medium">{d.tajuk}</p>
                                    <p className="text-body-xs text-txt-black-500">{d.jenis}</p>
                                </td>
                                <td>{d.modul.toUpperCase()}</td>
                                <td className="whitespace-nowrap">{fmtMasa(d.tarikh_simpan)}</td>
                                <td className="whitespace-nowrap space-x-2">
                                    {d.pautan && <Link href={`${d.pautan}${d.pautan.includes('?') ? '&' : '?'}draf=${d.id}`} className="text-txt-primary hover:underline">Sambung</Link>}
                                    <Button size="sm" variant="secondary" onClick={() => buang.mutate(d.id)} disabled={buang.isPending}>
                                        Buang
                                    </Button>
                                </td>
                            </tr>
                        ))
                    )}
                </Table>
            )}
        </>
    );
}

export default function InboxPage() {
    return (
        <PageContainer>
            <Suspense fallback={<Spinner />}>
                <Inbox />
            </Suspense>
        </PageContainer>
    );
}
