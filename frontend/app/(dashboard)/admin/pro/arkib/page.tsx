'use client';

import { useState } from 'react';
import type { FormEvent } from 'react';
import Link from 'next/link';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Tabs, TabPanel } from '@/components/ui/Tabs';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Table } from '@/components/ui/Table';
import { Toggle } from '@/components/ui/Toggle';
import { InputLabel } from '@/components/ui/InputLabel';
import { Card, EmptyRow, FieldError, Pager, SelectField, inputClass } from '@/components/pro/common';
import { STATUS_ARKIB_LABEL, STATUS_LOT_LABEL, errorMessage, fieldErrors, fmtNombor, fmtTarikh, namaRuj, proApi, useCan, useRujukan, type JadualRetensi } from '@/lib/pro';

function toneArkib(s: string): 'success' | 'warning' | 'danger' | 'info' | 'neutral' {
    return s === 'diarkib' ? 'neutral' : s === 'menunggu_kelulusan' ? 'warning' : s === 'ditolak' ? 'danger' : s === 'dipulih' ? 'success' : 'info';
}

function RekodArkib() {
    const can = useCan();
    const queryClient = useQueryClient();
    const { data: ruj } = useRujukan();
    const [status, setStatus] = useState('');
    const [page, setPage] = useState(1);
    const [busy, setBusy] = useState<string | null>(null);
    const f = { status, page };
    const { data, isLoading, error } = useQuery({ queryKey: ['pro', 'arkib', f], queryFn: () => proApi.arkib(f) });
    const rows = data?.data ?? [];

    async function jana() {
        setBusy('jana');
        try {
            const res = await proApi.janaArkib();
            toast.success(`${fmtNombor(res.data.cadangan_baharu, 0)} cadangan arkib baharu dijana mengikut jadual retensi.`);
            queryClient.invalidateQueries({ queryKey: ['pro', 'arkib'] });
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setBusy(null);
        }
    }

    async function hantar(id: string) {
        setBusy(id);
        try {
            const res = await proApi.hantarArkib(id);
            toast.success(res.message);
            queryClient.invalidateQueries({ queryKey: ['pro', 'arkib'] });
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setBusy(null);
        }
    }

    return (
        <div className="space-y-4">
            <div className="flex flex-col sm:flex-row sm:items-end gap-3 sm:justify-between">
                <div className="sm:w-64">
                    <SelectField
                        id="ar-status"
                        label="Status arkib"
                        value={status}
                        onChange={(v) => {
                            setStatus(v);
                            setPage(1);
                        }}
                        options={Object.entries(STATUS_ARKIB_LABEL).map(([kod, nama]) => ({ kod, nama }))}
                    />
                </div>
                {can('pro.arkib.cipta') && (
                    <Button onClick={jana} disabled={busy === 'jana'}>
                        {busy === 'jana' ? 'Menjana…' : 'Jana cadangan arkib'}
                    </Button>
                )}
            </div>
            {error ? (
                <Alert type="error">{errorMessage(error)}</Alert>
            ) : (
                <div className="overflow-x-auto">
                    <Table
                        head={
                            <>
                                <th>Lot</th>
                                <th>Status arkib</th>
                                <th className="hidden md:table-cell">Sebab</th>
                                <th className="hidden lg:table-cell">Tarikh</th>
                                <th className="hidden lg:table-cell">Layak lupus</th>
                                <th>
                                    <span className="sr-only">Tindakan</span>
                                </th>
                            </>
                        }
                    >
                        {isLoading ? (
                            <EmptyRow colSpan={6}>Memuatkan…</EmptyRow>
                        ) : rows.length === 0 ? (
                            <EmptyRow colSpan={6}>Tiada rekod arkib.</EmptyRow>
                        ) : (
                            rows.map((r) => (
                                <tr key={r.id}>
                                    <td className="min-w-[9rem]">
                                        {r.lot ? (
                                            <>
                                                <Link href={`/admin/pro/lot/${r.lot.id}`} className="font-medium text-txt-primary hover:underline">
                                                    {r.lot.no_lot}
                                                </Link>
                                                <span className="block text-body-xs text-txt-black-500">
                                                    {namaRuj(ruj?.mukim, r.lot.kod_mukim)} · {STATUS_LOT_LABEL[r.lot.status_lot] ?? r.lot.status_lot}
                                                </span>
                                            </>
                                        ) : (
                                            <span className="font-mono text-body-xs">{r.id_rekod}</span>
                                        )}
                                    </td>
                                    <td>
                                        <Badge variant={toneArkib(r.status_arkib)}>{STATUS_ARKIB_LABEL[r.status_arkib] ?? r.status_arkib}</Badge>
                                    </td>
                                    <td className="hidden md:table-cell min-w-[12rem]">{r.sebab ?? '—'}</td>
                                    <td className="hidden lg:table-cell whitespace-nowrap">
                                        <span className="block">Dicadang {fmtTarikh(r.created_at)}</span>
                                        {r.tarikh_arkib && <span className="block text-body-xs text-txt-black-500">Diarkib {fmtTarikh(r.tarikh_arkib)}</span>}
                                        {r.tarikh_pulih && <span className="block text-body-xs text-txt-black-500">Dipulih {fmtTarikh(r.tarikh_pulih)}</span>}
                                    </td>
                                    <td className="hidden lg:table-cell whitespace-nowrap">{fmtTarikh(r.tarikh_layak_lupus)}</td>
                                    <td className="whitespace-nowrap">
                                        {r.status_arkib === 'dicadang' && can('pro.arkib.cipta') && (
                                            <Button size="sm" variant="secondary" onClick={() => hantar(r.id)} disabled={busy === r.id}>
                                                Hantar untuk kelulusan
                                            </Button>
                                        )}
                                        {r.status_arkib === 'menunggu_kelulusan' && r.id_aliran_kerja && (
                                            <Link href={`/admin/wfe/instans/${r.id_aliran_kerja}`} className="text-body-sm text-txt-primary hover:underline">
                                                Lihat aliran kerja
                                            </Link>
                                        )}
                                    </td>
                                </tr>
                            ))
                        )}
                    </Table>
                </div>
            )}
            <Pager meta={data?.meta} page={page} onPage={setPage} />
        </div>
    );
}

const STATUS_SYARAT = ['draf', 'aktif', 'tidak_aktif', 'digabung'];

function JadualModal({ row, onClose }: { row: JadualRetensi | null; onClose: () => void }) {
    const queryClient = useQueryClient();
    const [keterangan, setKeterangan] = useState(row?.keterangan ?? '');
    const [tempoh, setTempoh] = useState(String(row?.tempoh_retensi_tahun ?? ''));
    const [status, setStatus] = useState<string[]>(row?.syarat?.status_lot ?? []);
    const [rujukan, setRujukan] = useState(row?.rujukan_dasar_mais ?? '');
    const [kelulusan, setKelulusan] = useState(row?.memerlukan_kelulusan ?? true);
    const [aktif, setAktif] = useState(row?.aktif ?? true);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    async function submit(e: FormEvent) {
        e.preventDefault();
        if (!row) return;
        setBusy(true);
        setErrors({});
        setError(null);
        try {
            const res = await proApi.kemasKiniJadual(row.id, {
                keterangan,
                tempoh_retensi_tahun: Number(tempoh),
                syarat: { ...(row.syarat ?? {}), status_lot: status },
                rujukan_dasar_mais: rujukan.trim() || null,
                memerlukan_kelulusan: kelulusan,
                aktif,
            });
            toast.success(res.message);
            queryClient.invalidateQueries({ queryKey: ['pro', 'arkib', 'jadual'] });
            onClose();
        } catch (err) {
            setErrors(fieldErrors(err));
            setError(errorMessage(err));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Modal open={!!row} onClose={onClose}>
            {row && (
                <form onSubmit={submit} className="p-6 space-y-4" noValidate>
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Jadual retensi: {row.kategori_rekod}</h2>
                    <div>
                        <InputLabel htmlFor="jr-ket" className="mb-1.5">
                            Keterangan
                        </InputLabel>
                        <input id="jr-ket" className={inputClass} value={keterangan} maxLength={255} onChange={(e) => setKeterangan(e.target.value)} />
                        <FieldError message={errors.keterangan} />
                    </div>
                    <div>
                        <InputLabel htmlFor="jr-tempoh" className="mb-1.5">
                            Tempoh retensi (tahun)
                        </InputLabel>
                        <input id="jr-tempoh" type="number" min={1} max={100} className={inputClass} value={tempoh} onChange={(e) => setTempoh(e.target.value)} />
                        <FieldError message={errors.tempoh_retensi_tahun} />
                    </div>
                    <fieldset>
                        <legend className="text-body-sm font-medium text-txt-black-900 mb-1.5">Syarat: status lot</legend>
                        <div className="flex flex-wrap gap-4">
                            {STATUS_SYARAT.map((s) => (
                                <label key={s} className="inline-flex items-center gap-2 text-body-sm">
                                    <input
                                        type="checkbox"
                                        className="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary"
                                        checked={status.includes(s)}
                                        onChange={(e) => setStatus((cur) => (e.target.checked ? [...cur, s] : cur.filter((x) => x !== s)))}
                                    />
                                    {STATUS_LOT_LABEL[s] ?? s}
                                </label>
                            ))}
                        </div>
                        <FieldError message={errors['syarat.status_lot'] ?? errors.syarat} />
                    </fieldset>
                    <div>
                        <InputLabel htmlFor="jr-ruj" className="mb-1.5">
                            Rujukan dasar MAIS
                        </InputLabel>
                        <input id="jr-ruj" className={inputClass} value={rujukan} maxLength={255} onChange={(e) => setRujukan(e.target.value)} />
                        <FieldError message={errors.rujukan_dasar_mais} />
                    </div>
                    <div className="flex flex-col gap-3">
                        <Toggle name="jr-kelulusan" label="Memerlukan kelulusan (Workflow)" checked={kelulusan} onChange={setKelulusan} />
                        <Toggle name="jr-aktif" label="Aktif" checked={aktif} onChange={setAktif} />
                    </div>
                    {error && <Alert type="error">{error}</Alert>}
                    <div className="flex justify-end gap-3">
                        <Button variant="secondary" onClick={onClose}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={busy}>
                            {busy ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                    </div>
                </form>
            )}
        </Modal>
    );
}

function JadualRetensiTab() {
    const can = useCan();
    const { data, isLoading, error } = useQuery({ queryKey: ['pro', 'arkib', 'jadual'], queryFn: () => proApi.jadualRetensi().then((r) => r.data) });
    const [row, setRow] = useState<JadualRetensi | null>(null);

    if (error) return <Alert type="error">{errorMessage(error)}</Alert>;
    return (
        <>
            <div className="overflow-x-auto">
                <Table
                    head={
                        <>
                            <th>Kategori rekod</th>
                            <th className="hidden md:table-cell">Syarat</th>
                            <th>Tempoh</th>
                            <th className="hidden lg:table-cell">Rujukan dasar</th>
                            <th className="hidden sm:table-cell">Status</th>
                            <th>
                                <span className="sr-only">Tindakan</span>
                            </th>
                        </>
                    }
                >
                    {isLoading ? (
                        <EmptyRow colSpan={6}>Memuatkan…</EmptyRow>
                    ) : (data ?? []).length === 0 ? (
                        <EmptyRow colSpan={6}>Tiada jadual retensi.</EmptyRow>
                    ) : (
                        data?.map((j) => (
                            <tr key={j.id}>
                                <td className="min-w-[12rem]">
                                    <span className="font-medium">{j.keterangan}</span>
                                    <span className="block text-body-xs font-mono text-txt-black-500">{j.kategori_rekod}</span>
                                </td>
                                <td className="hidden md:table-cell">{(j.syarat?.status_lot ?? []).map((s) => STATUS_LOT_LABEL[s] ?? s).join(', ') || '—'}</td>
                                <td className="whitespace-nowrap">
                                    {j.tempoh_retensi_tahun} tahun
                                    <span className="block text-body-xs text-txt-black-500">→ {j.tindakan_selepas_retensi}</span>
                                </td>
                                <td className="hidden lg:table-cell">{j.rujukan_dasar_mais ?? '—'}</td>
                                <td className="hidden sm:table-cell">
                                    <span className="flex flex-wrap gap-1">
                                        <Badge variant={j.aktif ? 'success' : 'neutral'}>{j.aktif ? 'Aktif' : 'Tidak aktif'}</Badge>
                                        {j.memerlukan_kelulusan && (
                                            <Badge variant="info" shape="tag">
                                                Perlu kelulusan
                                            </Badge>
                                        )}
                                    </span>
                                </td>
                                <td>
                                    {can('pro.arkib.kemas_kini') && (
                                        <Button size="sm" variant="secondary" onClick={() => setRow(j)}>
                                            Kemas kini
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        ))
                    )}
                </Table>
            </div>
            <JadualModal key={row?.id ?? 0} row={row} onClose={() => setRow(null)} />
        </>
    );
}

export default function ArkibPage() {
    return (
        <PageContainer>
            <Card>
                <Tabs tabs={{ rekod: 'Rekod arkib', jadual: 'Jadual retensi' }}>
                    <TabPanel value="rekod">
                        <RekodArkib />
                    </TabPanel>
                    <TabPanel value="jadual">
                        <JadualRetensiTab />
                    </TabPanel>
                </Tabs>
            </Card>
        </PageContainer>
    );
}
