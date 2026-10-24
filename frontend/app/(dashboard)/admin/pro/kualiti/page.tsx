'use client';

import { Suspense, useState } from 'react';
import type { FormEvent } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
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
import { Textarea } from '@/components/ui/Textarea';
import { InputLabel } from '@/components/ui/InputLabel';
import { Card, EmptyRow, ExportMenu, FieldError, Loading, Pager, SelectField, UserPicker, inputClass } from '@/components/pro/common';
import {
    BAHAGIAN_LABEL,
    errorMessage,
    fieldErrors,
    fmtNombor,
    fmtTarikh,
    proApi,
    useCan,
    useRujukan,
    type KategoriKualiti,
    type Pengguna,
    type PeraturanKualiti,
    type TugasanKualiti,
} from '@/lib/pro';

const ENTITI_OPTIONS = [
    { kod: 'lot_tanah', nama: 'Lot tanah' },
    { kod: 'data_spatial', nama: 'Data spatial' },
    { kod: 'dokumen', nama: 'Dokumen' },
    { kod: 'cukai', nama: 'Cukai' },
];

// ---------------------------------------------------------------- Laporan

function Laporan() {
    const can = useCan();
    const queryClient = useQueryClient();
    const { data: ruj } = useRujukan();
    const [f, setF] = useState<Record<string, string>>({});
    const [steward, setSteward] = useState<Pengguna | null>(null);
    const [open, setOpen] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const filters = { ...f, ...(steward ? { id_data_steward: String(steward.id) } : {}) };
    const { data, isLoading, error } = useQuery({ queryKey: ['pro', 'kualiti', 'laporan', filters], queryFn: () => proApi.laporanKualiti(filters).then((r) => r.data) });

    const set = (k: string) => (v: string) => setF((cur) => ({ ...cur, [k]: v, ...(k === 'kod_daerah' ? { kod_mukim: '' } : {}) }));
    const mukim = (ruj?.mukim ?? []).filter((m) => !f.kod_daerah || m.daerah_kod === f.kod_daerah);

    async function jalankan() {
        setBusy(true);
        try {
            const res = await proApi.jalankanKualiti();
            toast.success(`${fmtNombor(res.data.tugasan_baharu, 0)} tugasan baharu dihalakan kepada data steward.`);
            queryClient.invalidateQueries({ queryKey: ['pro', 'kualiti'] });
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setBusy(false);
        }
    }

    const tone = (k: KategoriKualiti) => (!k.tersedia ? 'neutral' : k.bilangan === 0 ? 'success' : k.entiti === 'lot_tanah' ? 'danger' : 'warning');

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <SelectField id="kq-daerah" label="Daerah" value={f.kod_daerah ?? ''} onChange={set('kod_daerah')} options={ruj?.daerah ?? []} />
                <SelectField id="kq-mukim" label="Mukim" value={f.kod_mukim ?? ''} onChange={set('kod_mukim')} options={mukim} />
                <SelectField id="kq-bahagian" label="Bahagian" value={f.bahagian_pemilik ?? ''} onChange={set('bahagian_pemilik')} options={ruj?.bahagian_pemilik ?? []} />
                <SelectField id="kq-entiti" label="Entiti" value={f.entiti ?? ''} onChange={set('entiti')} options={ENTITI_OPTIONS} />
                <UserPicker id="kq-steward" label="Data steward" value={steward} onChange={setSteward} />
            </div>
            <div className="flex flex-wrap justify-end gap-2">
                <ExportMenu onExport={(fmt) => proApi.eksportKualiti(filters, fmt)} />
                {can('pro.kualiti.kemas_kini') && (
                    <Button onClick={jalankan} disabled={busy}>
                        {busy ? 'Menjalankan…' : 'Halakan isu kepada steward'}
                    </Button>
                )}
            </div>

            {error ? (
                <Alert type="error">{errorMessage(error)}</Alert>
            ) : isLoading ? (
                <Loading />
            ) : (
                <div className="space-y-3">
                    {(data?.ringkasan ?? []).map((k) => {
                        const items = data?.butiran[k.kod] ?? [];
                        const expanded = open === k.kod;
                        return (
                            <div key={k.kod} className={`rounded-lg border ${k.tersedia ? 'border-otl-gray-200 bg-bg-white' : 'border-dashed border-otl-gray-300 bg-bg-washed'}`}>
                                <button
                                    type="button"
                                    className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left disabled:cursor-default"
                                    onClick={() => setOpen(expanded ? null : k.kod)}
                                    disabled={!k.tersedia || k.bilangan === 0}
                                    aria-expanded={expanded}
                                >
                                    <span className="min-w-0">
                                        <span className={`block text-body-sm font-medium ${k.tersedia ? 'text-txt-black-900' : 'text-txt-black-500'}`}>{k.label}</span>
                                        <span className="block text-body-xs text-txt-black-500">{k.tersedia ? `Entiti: ${k.entiti}` : 'Belum tersedia — modul sumber belum diaktifkan.'}</span>
                                    </span>
                                    <Badge variant={tone(k)}>{k.tersedia ? fmtNombor(k.bilangan, 0) : '—'}</Badge>
                                </button>
                                {expanded && (
                                    <div className="border-t border-otl-gray-200 p-3 overflow-x-auto">
                                        <Table
                                            head={
                                                <>
                                                    <th>No. Lot</th>
                                                    <th className="hidden sm:table-cell">Mukim / Daerah</th>
                                                    <th className="hidden md:table-cell">Bahagian</th>
                                                    <th>Keterangan</th>
                                                </>
                                            }
                                        >
                                            {items.map((it, i) => (
                                                <tr key={`${it.lot_id ?? i}-${i}`}>
                                                    <td className="whitespace-nowrap">
                                                        {it.pautan ? (
                                                            <Link href={it.pautan} className="text-txt-primary hover:underline">
                                                                {it.no_lot ?? '—'}
                                                            </Link>
                                                        ) : (
                                                            (it.no_lot ?? '—')
                                                        )}
                                                    </td>
                                                    <td className="hidden sm:table-cell">
                                                        {it.nama_mukim ?? '—'}
                                                        <span className="block text-body-xs text-txt-black-500">{it.nama_daerah ?? ''}</span>
                                                    </td>
                                                    <td className="hidden md:table-cell">{it.bahagian_pemilik ? (BAHAGIAN_LABEL[it.bahagian_pemilik] ?? it.bahagian_pemilik) : '—'}</td>
                                                    <td className="min-w-[12rem]">{it.keterangan ?? '—'}</td>
                                                </tr>
                                            ))}
                                        </Table>
                                        {k.bilangan > items.length && <p className="mt-2 text-body-xs text-txt-black-500">Memaparkan {items.length} daripada {k.bilangan}. Eksport untuk senarai penuh.</p>}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

// ---------------------------------------------------------------- Tugasan

function ResolveModal({ task, onClose }: { task: TugasanKualiti | null; onClose: () => void }) {
    const queryClient = useQueryClient();
    const [tindakan, setTindakan] = useState('');
    const [status, setStatus] = useState('selesai');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    function close() {
        setTindakan('');
        setStatus('selesai');
        setErrors({});
        setError(null);
        onClose();
    }

    async function submit(e: FormEvent) {
        e.preventDefault();
        if (!task) return;
        if (!tindakan.trim()) {
            setErrors({ tindakan_pembetulan: 'Nyatakan tindakan pembetulan yang diambil.' });
            return;
        }
        setBusy(true);
        try {
            const res = await proApi.selesaiTugasan(task.id, tindakan.trim(), status);
            toast.success(res.message);
            queryClient.invalidateQueries({ queryKey: ['pro'] });
            close();
        } catch (err) {
            setErrors(fieldErrors(err));
            setError(errorMessage(err));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Modal open={!!task} onClose={close}>
            {task && (
                <form onSubmit={submit} className="p-6 space-y-4">
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Selesaikan tugasan</h2>
                    <p className="text-body-sm text-txt-black-700">
                        {task.no_lot ? `${task.no_lot}: ` : ''}
                        {task.keterangan}
                    </p>
                    <SelectField
                        id="tg-status"
                        label="Keputusan"
                        value={status}
                        onChange={setStatus}
                        options={[
                            { kod: 'selesai', nama: 'Selesai — isu telah dibetulkan' },
                            { kod: 'ditolak', nama: 'Ditolak — bukan isu sebenar' },
                        ]}
                        placeholder={null}
                    />
                    <Textarea label="Tindakan pembetulan" name="tindakan_pembetulan" value={tindakan} onChange={setTindakan} required maxLength={2000} rows={4} error={errors.tindakan_pembetulan} />
                    {error && <Alert type="error">{error}</Alert>}
                    <div className="flex justify-end gap-3">
                        <Button variant="secondary" onClick={close}>
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

function Tugasan() {
    const can = useCan();
    const [status, setStatus] = useState('terbuka');
    const [saya, setSaya] = useState(true);
    const [page, setPage] = useState(1);
    const [task, setTask] = useState<TugasanKualiti | null>(null);
    const f = { status, saya: saya ? '1' : undefined, page };
    const { data, isLoading, error } = useQuery({ queryKey: ['pro', 'kualiti', 'tugasan', f], queryFn: () => proApi.tugasan(f) });
    const rows = data?.data ?? [];

    return (
        <div className="space-y-4">
            <div className="flex flex-col sm:flex-row sm:items-end gap-3 sm:justify-between">
                <div className="sm:w-56">
                    <SelectField
                        id="tg-f-status"
                        label="Status"
                        value={status}
                        onChange={(v) => {
                            setStatus(v);
                            setPage(1);
                        }}
                        options={[
                            { kod: 'terbuka', nama: 'Terbuka' },
                            { kod: 'selesai', nama: 'Selesai' },
                            { kod: 'ditolak', nama: 'Ditolak' },
                        ]}
                        placeholder="Semua"
                    />
                </div>
                <Toggle
                    name="tg-saya"
                    label="Tugasan saya sahaja"
                    checked={saya}
                    onChange={(v) => {
                        setSaya(v);
                        setPage(1);
                    }}
                />
            </div>
            {error ? (
                <Alert type="error">{errorMessage(error)}</Alert>
            ) : (
                <div className="overflow-x-auto">
                    <Table
                        head={
                            <>
                                <th>Isu</th>
                                <th className="hidden sm:table-cell">Rekod</th>
                                <th>Tahap</th>
                                <th className="hidden md:table-cell">Steward</th>
                                <th className="hidden lg:table-cell">Dicipta</th>
                                <th>
                                    <span className="sr-only">Tindakan</span>
                                </th>
                            </>
                        }
                    >
                        {isLoading ? (
                            <EmptyRow colSpan={6}>Memuatkan…</EmptyRow>
                        ) : rows.length === 0 ? (
                            <EmptyRow colSpan={6}>Tiada tugasan.</EmptyRow>
                        ) : (
                            rows.map((t) => (
                                <tr key={t.id}>
                                    <td className="min-w-[14rem]">
                                        {t.keterangan}
                                        <span className="block text-body-xs text-txt-black-500 font-mono">{t.kod_isu}</span>
                                        {t.tindakan_pembetulan && <span className="block text-body-xs text-txt-success">Tindakan: {t.tindakan_pembetulan}</span>}
                                    </td>
                                    <td className="hidden sm:table-cell whitespace-nowrap">
                                        {t.pautan ? (
                                            <Link href={t.pautan} className="text-txt-primary hover:underline">
                                                {t.no_lot ?? t.entiti}
                                            </Link>
                                        ) : (
                                            (t.no_lot ?? t.entiti)
                                        )}
                                    </td>
                                    <td>
                                        <Badge variant={t.tahap_keterukan === 'ralat' ? 'danger' : 'warning'}>{t.tahap_keterukan === 'ralat' ? 'Ralat' : 'Amaran'}</Badge>
                                        {t.status !== 'terbuka' && (
                                            <span className="block mt-1">
                                                <Badge variant={t.status === 'selesai' ? 'success' : 'neutral'}>{t.status === 'selesai' ? 'Selesai' : 'Ditolak'}</Badge>
                                            </span>
                                        )}
                                    </td>
                                    <td className="hidden md:table-cell">{t.nama_data_steward ?? '—'}</td>
                                    <td className="hidden lg:table-cell whitespace-nowrap">{fmtTarikh(t.created_at)}</td>
                                    <td>
                                        {t.status === 'terbuka' && can('pro.kualiti.kemas_kini') && (
                                            <Button size="sm" variant="secondary" onClick={() => setTask(t)}>
                                                Selesaikan
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))
                        )}
                    </Table>
                </div>
            )}
            <Pager meta={data?.meta} page={page} onPage={setPage} />
            <ResolveModal task={task} onClose={() => setTask(null)} />
        </div>
    );
}

// ---------------------------------------------------------------- Peraturan

const JENIS_PERATURAN: Record<string, string> = {
    wajib: 'Wajib',
    format: 'Format (regex)',
    julat: 'Julat',
    senarai: 'Senarai nilai',
    panjang: 'Panjang maksimum',
    rujukan: 'Rujukan',
    unik: 'Unik',
    lengkap: 'Kelengkapan',
};

function RuleModal({ rule, onClose }: { rule: PeraturanKualiti | null; onClose: () => void }) {
    const queryClient = useQueryClient();
    const [mesej, setMesej] = useState(rule?.mesej_ralat ?? '');
    const [tahap, setTahap] = useState<string>(rule?.tahap_keterukan ?? 'ralat');
    const [aktif, setAktif] = useState(rule?.status_aktif ?? true);
    const [ungkapan, setUngkapan] = useState(rule?.ungkapan_peraturan ? JSON.stringify(rule.ungkapan_peraturan, null, 2) : '{}');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    async function submit(e: FormEvent) {
        e.preventDefault();
        if (!rule) return;
        let parsed: Record<string, unknown>;
        try {
            parsed = JSON.parse(ungkapan || '{}');
            if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) throw new Error();
        } catch {
            setErrors({ ungkapan_peraturan: 'Ungkapan mesti objek JSON yang sah, cth. {"regex": "^[A-Z]"}' });
            return;
        }
        setBusy(true);
        setErrors({});
        setError(null);
        try {
            const res = await proApi.kemasKiniPeraturan(rule.id, {
                mesej_ralat: mesej,
                tahap_keterukan: tahap as PeraturanKualiti['tahap_keterukan'],
                status_aktif: aktif,
                ungkapan_peraturan: parsed,
            });
            toast.success(res.message);
            queryClient.invalidateQueries({ queryKey: ['pro', 'kualiti', 'peraturan'] });
            onClose();
        } catch (err) {
            setErrors(fieldErrors(err));
            setError(errorMessage(err));
        } finally {
            setBusy(false);
        }
    }

    return (
        <Modal open={!!rule} onClose={onClose}>
            {rule && (
                <form onSubmit={submit} className="p-6 space-y-4">
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">
                        Peraturan: {rule.entiti}.{rule.medan}
                    </h2>
                    <p className="text-body-sm text-txt-black-500">Jenis: {JENIS_PERATURAN[rule.jenis_peraturan] ?? rule.jenis_peraturan}</p>
                    <div>
                        <InputLabel htmlFor="pr-mesej" className="mb-1.5">
                            Mesej ralat <span className="text-danger-600">*</span>
                        </InputLabel>
                        <input id="pr-mesej" className={inputClass} value={mesej} maxLength={255} onChange={(e) => setMesej(e.target.value)} />
                        <FieldError message={errors.mesej_ralat} />
                    </div>
                    <SelectField
                        id="pr-tahap"
                        label="Tahap keterukan"
                        value={tahap}
                        onChange={setTahap}
                        options={[
                            { kod: 'ralat', nama: 'Ralat (menghalang import / simpanan)' },
                            { kod: 'amaran', nama: 'Amaran (dilaporkan sahaja)' },
                        ]}
                        placeholder={null}
                        error={errors.tahap_keterukan}
                    />
                    <div>
                        <InputLabel htmlFor="pr-ungkapan" className="mb-1.5">
                            Ungkapan peraturan (JSON)
                        </InputLabel>
                        <textarea id="pr-ungkapan" rows={4} className={`${inputClass} font-mono text-body-xs`} value={ungkapan} onChange={(e) => setUngkapan(e.target.value)} />
                        <FieldError message={errors.ungkapan_peraturan} />
                    </div>
                    <Toggle name="pr-aktif" label="Peraturan aktif" checked={aktif} onChange={setAktif} />
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

function Peraturan() {
    const can = useCan();
    const { data, isLoading, error } = useQuery({ queryKey: ['pro', 'kualiti', 'peraturan'], queryFn: () => proApi.peraturan().then((r) => r.data) });
    const [rule, setRule] = useState<PeraturanKualiti | null>(null);

    return (
        <div className="space-y-4">
            {error ? (
                <Alert type="error">{errorMessage(error)}</Alert>
            ) : (
                <div className="overflow-x-auto">
                    <Table
                        head={
                            <>
                                <th>Medan</th>
                                <th className="hidden sm:table-cell">Jenis</th>
                                <th>Mesej ralat</th>
                                <th className="hidden md:table-cell">Tahap</th>
                                <th className="hidden md:table-cell">Status</th>
                                <th>
                                    <span className="sr-only">Tindakan</span>
                                </th>
                            </>
                        }
                    >
                        {isLoading ? (
                            <EmptyRow colSpan={6}>Memuatkan…</EmptyRow>
                        ) : (data ?? []).length === 0 ? (
                            <EmptyRow colSpan={6}>Tiada peraturan.</EmptyRow>
                        ) : (
                            data?.map((r) => (
                                <tr key={r.id}>
                                    <td className="whitespace-nowrap font-mono text-body-xs">
                                        {r.entiti}.{r.medan}
                                    </td>
                                    <td className="hidden sm:table-cell">
                                        {JENIS_PERATURAN[r.jenis_peraturan] ?? r.jenis_peraturan}
                                        {r.ungkapan_peraturan && Object.keys(r.ungkapan_peraturan).length > 0 && (
                                            <code className="block text-body-xs text-txt-black-500 break-all max-w-xs">{JSON.stringify(r.ungkapan_peraturan)}</code>
                                        )}
                                    </td>
                                    <td className="min-w-[12rem]">{r.mesej_ralat}</td>
                                    <td className="hidden md:table-cell">
                                        <Badge variant={r.tahap_keterukan === 'ralat' ? 'danger' : 'warning'}>{r.tahap_keterukan === 'ralat' ? 'Ralat' : 'Amaran'}</Badge>
                                    </td>
                                    <td className="hidden md:table-cell">
                                        <Badge variant={r.status_aktif ? 'success' : 'neutral'}>{r.status_aktif ? 'Aktif' : 'Tidak aktif'}</Badge>
                                    </td>
                                    <td>
                                        {can('pro.kualiti.kemas_kini') && (
                                            <Button size="sm" variant="secondary" onClick={() => setRule(r)}>
                                                Kemas kini
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))
                        )}
                    </Table>
                </div>
            )}
            <RuleModal key={rule?.id ?? 0} rule={rule} onClose={() => setRule(null)} />
        </div>
    );
}

// ---------------------------------------------------------------- page

function KualitiPage() {
    const can = useCan();
    const params = useSearchParams();
    const tabs: Record<string, string> = {};
    if (can('pro.kualiti.lihat')) tabs.laporan = 'Laporan';
    tabs.tugasan = 'Tugasan steward';
    if (can('pro.kualiti.lihat')) tabs.peraturan = 'Peraturan';
    const requested = params.get('tab') ?? '';
    const initial = requested in tabs ? requested : Object.keys(tabs)[0];

    return (
        <PageContainer>
            <Card>
                <Tabs key={`${initial}-${Object.keys(tabs).join()}`} tabs={tabs} defaultTab={initial}>
                    <TabPanel value="laporan">
                        <Laporan />
                    </TabPanel>
                    <TabPanel value="tugasan">
                        <Tugasan />
                    </TabPanel>
                    <TabPanel value="peraturan">
                        <Peraturan />
                    </TabPanel>
                </Tabs>
            </Card>
        </PageContainer>
    );
}

export default function Page() {
    return (
        <Suspense>
            <KualitiPage />
        </Suspense>
    );
}
