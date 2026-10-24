'use client';

import { Suspense, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Tabs, TabPanel } from '@/components/ui/Tabs';
import { Alert } from '@/components/ui/Alert';
import { api } from '@/lib/api';
import { errorText, formatDateTime, qs, type LogIntegrasi, type LogRalat, type Paginated } from '@/lib/adm';

const ARAS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
const input = 'rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 text-body-sm w-full';

function arasVariant(a: string) {
    return ['emergency', 'alert', 'critical', 'error'].includes(a) ? 'danger' : a === 'warning' ? 'warning' : 'neutral';
}

export default function LogPage() {
    return (
        <Suspense fallback={<PageContainer>Memuatkan…</PageContainer>}>
            <PageContainer>
                <Tabs tabs={{ ralat: 'Log ralat aplikasi', integrasi: 'Log integrasi' }}>
                    <TabPanel value="ralat">
                        <ErrorLog />
                    </TabPanel>
                    <TabPanel value="integrasi">
                        <IntegrationLog />
                    </TabPanel>
                </Tabs>
            </PageContainer>
        </Suspense>
    );
}

function ErrorLog() {
    const params = useSearchParams();
    const [f, setF] = useState({ dari: '', hingga: '', modul: '', aras: '', kod_ralat: '', id_pengguna: '', id_korelasi: params.get('id_korelasi') ?? '', q: '' });
    const [applied, setApplied] = useState(f);
    const [page, setPage] = useState(1);
    const [open, setOpen] = useState<number | null>(null);
    const set = (k: keyof typeof f) => (e: { target: { value: string } }) => setF((s) => ({ ...s, [k]: e.target.value }));

    const filters = { ...applied, aras: applied.aras ? [applied.aras] : undefined, page };
    const { data, isLoading, error } = useQuery({
        queryKey: ['adm', 'log-ralat', filters],
        queryFn: () => api.get<Paginated<LogRalat>>(`/adm/log/ralat${qs(filters)}`),
    });

    return (
        <div className="space-y-4 pt-4">
            <form
                className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    setApplied(f);
                    setPage(1);
                }}
            >
                <label className="text-body-xs text-txt-black-500">
                    Dari
                    <input type="date" className={input} value={f.dari} onChange={set('dari')} />
                </label>
                <label className="text-body-xs text-txt-black-500">
                    Hingga
                    <input type="date" className={input} value={f.hingga} onChange={set('hingga')} />
                </label>
                <label className="text-body-xs text-txt-black-500">
                    Modul
                    <input className={input} value={f.modul} onChange={set('modul')} placeholder="cth. dms" />
                </label>
                <label className="text-body-xs text-txt-black-500">
                    Aras keterukan
                    <select className={input} value={f.aras} onChange={set('aras')}>
                        <option value="">Semua</option>
                        {ARAS.map((a) => (
                            <option key={a} value={a}>
                                {a}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="text-body-xs text-txt-black-500">
                    Kod ralat
                    <input className={input} value={f.kod_ralat} onChange={set('kod_ralat')} />
                </label>
                <label className="text-body-xs text-txt-black-500">
                    ID pengguna
                    <input className={input} inputMode="numeric" value={f.id_pengguna} onChange={set('id_pengguna')} />
                </label>
                <label className="text-body-xs text-txt-black-500">
                    ID korelasi (rujukan)
                    <input className={input} value={f.id_korelasi} onChange={set('id_korelasi')} />
                </label>
                <label className="text-body-xs text-txt-black-500">
                    Kata kunci mesej
                    <input className={input} value={f.q} onChange={set('q')} />
                </label>
                <div className="flex gap-2 sm:col-span-2 lg:col-span-4">
                    <Button type="submit">Cari</Button>
                    <a
                        className="inline-flex items-center rounded-md border border-otl-gray-300 px-4 py-2 text-body-sm font-medium text-txt-black-900 hover:bg-bg-washed"
                        href={`/api/v1/adm/log/ralat/eksport${qs({ ...applied, aras: applied.aras ? [applied.aras] : undefined })}`}
                    >
                        Eksport CSV
                    </a>
                </div>
            </form>

            {error && <Alert type="error">{errorText(error)}</Alert>}

            <Table
                head={
                    <>
                        <th>Masa</th>
                        <th>Aras</th>
                        <th>Modul</th>
                        <th>Kod ralat</th>
                        <th>Mesej</th>
                        <th>Pengguna</th>
                        <th>ID korelasi</th>
                    </>
                }
            >
                {(data?.data ?? []).length === 0 ? (
                    <tr>
                        <td colSpan={7} className="text-center text-txt-black-500">{isLoading ? 'Memuatkan…' : 'Tiada rekod.'}</td>
                    </tr>
                ) : (
                    data?.data.map((l) => (
                        <tr key={l.id} className="cursor-pointer" onClick={() => setOpen(l.id)}>
                            <td className="whitespace-nowrap">{formatDateTime(l.tarikh_masa)}</td>
                            <td>
                                <Badge variant={arasVariant(l.aras_keterukan)}>{l.aras_keterukan}</Badge>
                            </td>
                            <td>{l.modul ?? '—'}</td>
                            <td className="font-mono text-body-xs">{l.kod_ralat ?? '—'}</td>
                            <td className="max-w-md truncate">{l.mesej}</td>
                            <td>{l.id_pengguna ?? '—'}</td>
                            <td className="font-mono text-body-xs whitespace-nowrap">{l.id_korelasi ?? '—'}</td>
                        </tr>
                    ))
                )}
            </Table>

            {data && data.last_page > 1 && (
                <div className="flex items-center gap-2 text-body-sm text-txt-black-500">
                    <Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                        Sebelum
                    </Button>
                    <span>
                        Halaman {data.current_page} / {data.last_page} ({data.total} rekod)
                    </span>
                    <Button size="sm" variant="secondary" disabled={page >= data.last_page} onClick={() => setPage((p) => p + 1)}>
                        Seterusnya
                    </Button>
                </div>
            )}

            {open !== null && <ErrorDetail id={open} onClose={() => setOpen(null)} />}
        </div>
    );
}

function ErrorDetail({ id, onClose }: { id: number; onClose: () => void }) {
    const { data } = useQuery({ queryKey: ['adm', 'log-ralat', id], queryFn: () => api.get<{ data: LogRalat }>(`/adm/log/ralat/${id}`) });
    const l = data?.data;

    return (
        <Modal open onClose={onClose}>
            <div className="p-6 space-y-3 max-h-[85vh] overflow-y-auto">
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Butiran log #{id}</h2>
                {l && (
                    <>
                        <p className="text-body-sm break-words">{l.mesej}</p>
                        <dl className="grid grid-cols-[8rem_1fr] gap-1 text-body-xs">
                            <dt className="text-txt-black-500">Masa</dt>
                            <dd>{formatDateTime(l.tarikh_masa)}</dd>
                            <dt className="text-txt-black-500">ID korelasi</dt>
                            <dd className="font-mono break-all">{l.id_korelasi ?? '—'}</dd>
                            <dt className="text-txt-black-500">Alamat IP</dt>
                            <dd>{l.alamat_ip ?? '—'}</dd>
                            <dt className="text-txt-black-500">Saluran</dt>
                            <dd>{l.saluran}</dd>
                        </dl>
                        {l.jejak_tindanan && <pre className="whitespace-pre-wrap break-all rounded-md bg-bg-washed p-2 text-[11px] leading-tight">{l.jejak_tindanan}</pre>}
                        {l.konteks && <pre className="whitespace-pre-wrap break-all rounded-md bg-bg-washed p-2 text-[11px]">{JSON.stringify(l.konteks, null, 2)}</pre>}
                    </>
                )}
                <div className="flex justify-end">
                    <Button variant="secondary" onClick={onClose}>
                        Tutup
                    </Button>
                </div>
            </div>
        </Modal>
    );
}

function IntegrationLog() {
    const [kod, setKod] = useState('');
    const [berjaya, setBerjaya] = useState('');
    const [page, setPage] = useState(1);
    const filters = { kod_integrasi: kod, berjaya, page };
    const { data, isLoading } = useQuery({
        queryKey: ['adm', 'log-integrasi', filters],
        queryFn: () => api.get<Paginated<LogIntegrasi>>(`/adm/log/integrasi${qs(filters)}`),
    });

    return (
        <div className="space-y-4 pt-4">
            <div className="flex flex-wrap gap-3 items-end">
                <label className="text-body-xs text-txt-black-500">
                    Kod integrasi
                    <input className={input} value={kod} onChange={(e) => setKod(e.target.value)} />
                </label>
                <label className="text-body-xs text-txt-black-500">
                    Keputusan
                    <select className={input} value={berjaya} onChange={(e) => setBerjaya(e.target.value)}>
                        <option value="">Semua</option>
                        <option value="1">Berjaya</option>
                        <option value="0">Gagal</option>
                    </select>
                </label>
                <a
                    className="inline-flex items-center rounded-md border border-otl-gray-300 px-4 py-2 text-body-sm font-medium text-txt-black-900 hover:bg-bg-washed"
                    href={`/api/v1/adm/log/integrasi/eksport${qs({ kod_integrasi: kod, berjaya })}`}
                >
                    Eksport CSV
                </a>
            </div>
            <Table
                head={
                    <>
                        <th>Masa</th>
                        <th>Integrasi</th>
                        <th>Operasi</th>
                        <th>Keputusan</th>
                        <th>HTTP</th>
                        <th>Tempoh</th>
                        <th>Mesej</th>
                    </>
                }
            >
                {(data?.data ?? []).length === 0 ? (
                    <tr>
                        <td colSpan={7} className="text-center text-txt-black-500">{isLoading ? 'Memuatkan…' : 'Tiada rekod.'}</td>
                    </tr>
                ) : (
                    data?.data.map((l) => (
                        <tr key={l.id}>
                            <td className="whitespace-nowrap">{formatDateTime(l.tarikh_masa)}</td>
                            <td className="font-mono text-body-xs">{l.kod_integrasi}</td>
                            <td>{l.operasi ?? '—'}</td>
                            <td>
                                <Badge variant={l.berjaya ? 'success' : 'danger'}>{l.berjaya ? 'Berjaya' : 'Gagal'}</Badge>
                            </td>
                            <td>{l.status_http ?? '—'}</td>
                            <td>{l.tempoh_ms === null ? '—' : `${l.tempoh_ms} ms`}</td>
                            <td className="max-w-md truncate">{l.mesej ?? ''}</td>
                        </tr>
                    ))
                )}
            </Table>
            {data && data.last_page > 1 && (
                <div className="flex gap-2">
                    <Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                        Sebelum
                    </Button>
                    <Button size="sm" variant="secondary" disabled={page >= data.last_page} onClick={() => setPage((p) => p + 1)}>
                        Seterusnya
                    </Button>
                </div>
            )}
        </div>
    );
}
