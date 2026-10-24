'use client';

import { useState } from 'react';
import type { FormEvent } from 'react';
import Link from 'next/link';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Stepper } from '@/components/ui/Stepper';
import { Button, LinkButton } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { Callout } from '@/components/ui/Callout';
import { Badge } from '@/components/ui/Badge';
import { Table } from '@/components/ui/Table';
import { InputLabel } from '@/components/ui/InputLabel';
import { Card, EmptyRow, ExportMenu, FieldError, Loading, Pager, SelectField, inputClass } from '@/components/pro/common';
import { BarisTable, DitolakTable, ENTITI_LABEL, RingkasanStats, SesiBadge, StatusFilter } from '@/components/pro/ImportBits';
import { errorMessage, fieldErrors, fmtTarikh, proApi, useCan, useMe, type EntitiImport, type SesiImport } from '@/lib/pro';

const STEPS = ['Pilih fail', 'Pemetaan lajur', 'Pratonton', 'Laporan'];

export default function ImportWizardPage() {
    const can = useCan();
    const { data: me } = useMe();
    const queryClient = useQueryClient();
    const [step, setStep] = useState(1);
    const [entiti, setEntiti] = useState<EntitiImport>('lot_tanah');
    const [fail, setFail] = useState<File | null>(null);
    const [idPemetaan, setIdPemetaan] = useState('');
    const [sesi, setSesi] = useState<SesiImport | null>(null);
    const [pemetaan, setPemetaan] = useState<Record<string, string>>({});
    const [namaPemetaan, setNamaPemetaan] = useState('');
    const [status, setStatus] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const saved = useQuery({ queryKey: ['pro', 'import', 'pemetaan', entiti], queryFn: () => proApi.pemetaanTersimpan(entiti).then((r) => r.data), enabled: step === 1 });
    const filtered = useQuery({
        queryKey: ['pro', 'import', sesi?.ringkasan.id, 'baris', status],
        queryFn: () => proApi.sesiImport(sesi!.ringkasan.id, status).then((r) => r.data),
        enabled: !!sesi && step >= 3 && status !== '',
    });

    if (me && !can('pro.import.cipta')) {
        return (
            <PageContainer>
                <Alert type="error">Anda tidak mempunyai kebenaran untuk mengimport data.</Alert>
                <SesiList />
            </PageContainer>
        );
    }

    async function run<T>(fn: () => Promise<T>): Promise<T | undefined> {
        setBusy(true);
        setError(null);
        setErrors({});
        try {
            return await fn();
        } catch (e) {
            setErrors(fieldErrors(e));
            setError(errorMessage(e));
            return undefined;
        } finally {
            setBusy(false);
        }
    }

    async function upload(e: FormEvent) {
        e.preventDefault();
        if (!fail) {
            setErrors({ fail: 'Sila pilih fail CSV atau Excel.' });
            return;
        }
        const form = new FormData();
        form.append('fail', fail);
        form.append('entiti', entiti);
        if (idPemetaan) form.append('id_pemetaan', idPemetaan);
        const res = await run(() => proApi.muatNaikImport(form));
        if (res) {
            setSesi(res.data);
            setPemetaan(res.data.ringkasan.pemetaan ?? {});
            setStatus('');
            setStep(2);
        }
    }

    async function validate() {
        if (!sesi) return;
        const res = await run(() => proApi.petaSemula(sesi.ringkasan.id, pemetaan));
        if (res) {
            setSesi(res.data);
            setStatus('');
            setStep(3);
        }
    }

    async function saveMapping() {
        if (!namaPemetaan.trim()) {
            setErrors({ nama: 'Beri nama pemetaan.' });
            return;
        }
        const res = await run(() => proApi.simpanPemetaan(entiti, namaPemetaan.trim(), pemetaan));
        if (res) {
            toast.success(res.message);
            queryClient.invalidateQueries({ queryKey: ['pro', 'import', 'pemetaan'] });
        }
    }

    async function commit() {
        if (!sesi) return;
        if (!window.confirm(`Komit ${sesi.ringkasan.bil_sah} baris sah ke pangkalan data induk? Baris ditolak tidak akan diimport.`)) return;
        const res = await run(() => proApi.komitImport(sesi.ringkasan.id));
        if (res) {
            toast.success(res.message);
            setSesi(res.data);
            setStatus('');
            setStep(4);
            queryClient.invalidateQueries({ queryKey: ['pro'] });
        }
    }

    async function cancel() {
        if (!sesi) return;
        if (!window.confirm('Batalkan sesi import ini? Tiada data akan diimport.')) return;
        const res = await run(() => proApi.batalImport(sesi.ringkasan.id));
        if (res) {
            toast.success(res.message);
            reset();
            queryClient.invalidateQueries({ queryKey: ['pro', 'import'] });
        }
    }

    function reset() {
        setStep(1);
        setSesi(null);
        setFail(null);
        setPemetaan({});
        setStatus('');
        setError(null);
        setErrors({});
    }

    const r = sesi?.ringkasan;
    const medan = sesi?.medan_sasaran ?? [];
    const lajur = r?.lajur_fail ?? [];
    const mappedTargets = new Set(Object.values(pemetaan).filter(Boolean));
    const missing = medan.filter((m) => m.wajib && !mappedTargets.has(m.medan));
    const dupTargets = Object.values(pemetaan).filter((v, i, a) => v && a.indexOf(v) !== i);
    const rows = status && filtered.data ? filtered.data.baris : (sesi?.baris ?? []);

    return (
        <PageContainer>
            <Card>
                <div className="overflow-x-auto">
                    <div className="min-w-[28rem]">
                        <Stepper steps={STEPS} current={step} />
                    </div>
                </div>
            </Card>

            {error && <Alert type="error">{error}</Alert>}

            {step === 1 && (
                <form onSubmit={upload} className="space-y-6" noValidate>
                    <Card title="1. Pilih entiti dan fail">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <SelectField
                                id="imp-entiti"
                                label="Entiti sasaran"
                                value={entiti}
                                onChange={(v) => {
                                    setEntiti(v as EntitiImport);
                                    setIdPemetaan('');
                                }}
                                options={Object.entries(ENTITI_LABEL).map(([kod, nama]) => ({ kod, nama }))}
                                placeholder={null}
                                error={errors.entiti}
                            />
                            <div>
                                <p className="block font-medium text-body-sm text-txt-black-900 mb-1.5">Templat</p>
                                <div className="flex flex-wrap gap-2">
                                    {(['xlsx', 'csv'] as const).map((f) => (
                                        <Button
                                            key={f}
                                            variant="secondary"
                                            size="sm"
                                            onClick={() => proApi.templat(entiti, f).catch((e) => toast.error(errorMessage(e, 'Templat tidak dapat dimuat turun.')))}
                                        >
                                            Muat turun templat {f.toUpperCase()}
                                        </Button>
                                    ))}
                                </div>
                                <p className="mt-1 text-body-xs text-txt-black-500">Templat Excel mengandungi helaian &quot;Panduan&quot; dengan senarai medan wajib.</p>
                            </div>
                            <div>
                                <InputLabel htmlFor="imp-fail" className="mb-1.5">
                                    Fail data <span className="text-danger-600">*</span>
                                </InputLabel>
                                <input
                                    id="imp-fail"
                                    type="file"
                                    accept=".csv,.xlsx,.xls"
                                    onChange={(e) => setFail(e.target.files?.[0] ?? null)}
                                    className="block w-full text-body-sm text-txt-black-700 file:me-3 file:rounded-md file:border file:border-otl-gray-300 file:bg-bg-white file:px-3 file:py-1.5 file:text-body-sm file:font-medium hover:file:bg-bg-washed"
                                />
                                <p className="mt-1 text-body-xs text-txt-black-500">CSV, XLSX atau XLS, maksimum 20 MB. Baris pertama ialah tajuk lajur.</p>
                                <FieldError message={errors.fail} />
                            </div>
                            <SelectField
                                id="imp-pemetaan"
                                label="Pemetaan tersimpan (pilihan)"
                                value={idPemetaan}
                                onChange={setIdPemetaan}
                                options={(saved.data ?? []).map((m) => ({ kod: String(m.id), nama: m.nama }))}
                                placeholder="Padanan automatik ikut tajuk lajur"
                                error={errors.id_pemetaan}
                            />
                        </div>
                        <div className="flex justify-end">
                            <Button type="submit" disabled={busy}>
                                {busy ? 'Membaca fail…' : 'Muat naik & teruskan'}
                            </Button>
                        </div>
                    </Card>
                    <SesiList />
                </form>
            )}

            {step === 2 && r && (
                <Card title="2. Pemetaan lajur">
                    <p className="text-body-sm text-txt-black-700">
                        Fail <strong>{r.nama_fail}</strong> · {r.bil_baris_dibaca} baris · {lajur.length} lajur. Padankan setiap lajur fail dengan medan sasaran, atau pilih
                        &quot;Abaikan&quot;.
                    </p>
                    {missing.length > 0 && (
                        <Alert type="warning">
                            Medan wajib belum dipetakan: <strong>{missing.map((m) => m.label).join(', ')}</strong>. Baris tanpa medan ini akan ditolak.
                        </Alert>
                    )}
                    {dupTargets.length > 0 && <Alert type="error">Satu medan sasaran dipetakan kepada lebih daripada satu lajur.</Alert>}
                    <div className="overflow-x-auto">
                        <Table
                            head={
                                <>
                                    <th>Lajur dalam fail</th>
                                    <th>Medan sasaran</th>
                                </>
                            }
                        >
                            {lajur.map((col) => (
                                <tr key={col}>
                                    <td className="font-mono text-body-xs break-all">{col}</td>
                                    <td className="min-w-[14rem]">
                                        <label htmlFor={`map-${col}`} className="sr-only">
                                            Medan sasaran untuk {col}
                                        </label>
                                        <select
                                            id={`map-${col}`}
                                            className={inputClass}
                                            value={pemetaan[col] ?? ''}
                                            onChange={(e) => setPemetaan((m) => ({ ...m, [col]: e.target.value }))}
                                        >
                                            <option value="">— Abaikan —</option>
                                            {medan.map((m) => (
                                                <option key={m.medan} value={m.medan}>
                                                    {m.label}
                                                    {m.wajib ? ' *' : ''}
                                                </option>
                                            ))}
                                        </select>
                                    </td>
                                </tr>
                            ))}
                        </Table>
                    </div>
                    <p className="text-body-xs text-txt-black-500">* medan wajib</p>

                    <div className="flex flex-col sm:flex-row gap-3 sm:items-end">
                        <div className="sm:flex-1">
                            <InputLabel htmlFor="map-nama" className="mb-1.5">
                                Simpan pemetaan ini untuk kegunaan semula
                            </InputLabel>
                            <input id="map-nama" className={inputClass} value={namaPemetaan} maxLength={100} onChange={(e) => setNamaPemetaan(e.target.value)} placeholder="cth. Eksport e-Tanah 2026" />
                            <FieldError message={errors.nama} />
                        </div>
                        <Button variant="secondary" onClick={saveMapping} disabled={busy}>
                            Simpan pemetaan
                        </Button>
                    </div>

                    <div className="flex flex-wrap justify-between gap-2 border-t border-otl-gray-200 pt-4">
                        <Button variant="secondary" onClick={cancel} disabled={busy}>
                            Batal sesi
                        </Button>
                        <Button onClick={validate} disabled={busy || dupTargets.length > 0}>
                            {busy ? 'Mengesahkan…' : 'Sahkan & pratonton'}
                        </Button>
                    </div>
                </Card>
            )}

            {step === 3 && r && (
                <>
                    <RingkasanStats r={r} />
                    <Card title="3. Pratonton">
                        {r.bil_ditolak > 0 ? (
                            <Callout type="warning" title={`${r.bil_ditolak} baris ditolak`}>
                                Baris ditolak tidak akan diimport. Betulkan fail dan muat naik semula, atau komit baris sah sahaja.
                            </Callout>
                        ) : (
                            <Callout type="success" title="Semua baris lulus semakan">
                                Tiada ralat ditemui.
                            </Callout>
                        )}
                        <StatusFilter value={status} onChange={setStatus} />
                        {status && filtered.isLoading ? <Loading /> : <BarisTable rows={rows} medan={medan} />}
                        <p className="text-body-xs text-txt-black-500">Pratonton memaparkan sehingga 500 baris pertama.</p>
                        <div className="flex flex-wrap justify-between gap-2 border-t border-otl-gray-200 pt-4">
                            <div className="flex flex-wrap gap-2">
                                <Button variant="secondary" onClick={() => setStep(2)} disabled={busy}>
                                    Kembali ke pemetaan
                                </Button>
                                <Button variant="secondary" onClick={cancel} disabled={busy}>
                                    Batal sesi
                                </Button>
                            </div>
                            <Button onClick={commit} disabled={busy || r.bil_sah === 0}>
                                {busy ? 'Mengkomit…' : `Komit baris sah (${r.bil_sah})`}
                            </Button>
                        </div>
                    </Card>
                </>
            )}

            {step === 4 && r && sesi && (
                <>
                    <RingkasanStats r={r} committed />
                    <Card
                        title="4. Laporan import"
                        actions={
                            <>
                                <ExportMenu label="Eksport laporan" onExport={(f) => proApi.laporanImport(r.id, f)} />
                                <LinkButton variant="secondary" href={`/admin/pro/import/${r.id}`}>
                                    Butiran sesi
                                </LinkButton>
                                <Button onClick={reset}>Import baharu</Button>
                            </>
                        }
                    >
                        <p className="text-body-sm text-txt-black-700">
                            Fail <strong>{r.nama_fail}</strong> dikomit pada {fmtTarikh(r.tarikh_komit, true)} oleh {r.pengguna ?? '—'}.
                        </p>
                        <h4 className="font-semibold text-body-md text-txt-black-900">Baris ditolak</h4>
                        <DitolakTable rows={sesi.ditolak} />
                    </Card>
                </>
            )}
        </PageContainer>
    );
}

function SesiList() {
    const [page, setPage] = useState(1);
    const { data, isLoading, error } = useQuery({ queryKey: ['pro', 'import', 'senarai', page], queryFn: () => proApi.senaraiImport(page) });

    return (
        <Card title="Sesi import terdahulu">
            {error ? (
                <Alert type="error">{errorMessage(error)}</Alert>
            ) : (
                <>
                    <div className="overflow-x-auto">
                        <Table
                            head={
                                <>
                                    <th>Fail</th>
                                    <th className="hidden sm:table-cell">Entiti</th>
                                    <th>Status</th>
                                    <th className="hidden md:table-cell text-right">Dibaca</th>
                                    <th className="hidden md:table-cell text-right">Berjaya</th>
                                    <th className="hidden md:table-cell text-right">Ditolak</th>
                                    <th className="hidden lg:table-cell">Tarikh</th>
                                </>
                            }
                        >
                            {isLoading ? (
                                <EmptyRow colSpan={7}>Memuatkan…</EmptyRow>
                            ) : (data?.data ?? []).length === 0 ? (
                                <EmptyRow colSpan={7}>Tiada sesi import.</EmptyRow>
                            ) : (
                                data?.data.map((s) => (
                                    <tr key={s.id}>
                                        <td className="max-w-[14rem] break-all">
                                            <Link href={`/admin/pro/import/${s.id}`} className="text-txt-primary hover:underline font-medium">
                                                {s.nama_fail}
                                            </Link>
                                            {s.pengguna && <span className="block text-body-xs text-txt-black-500">{s.pengguna}</span>}
                                        </td>
                                        <td className="hidden sm:table-cell">
                                            <Badge variant="neutral" shape="tag">
                                                {ENTITI_LABEL[s.entiti_sasaran] ?? s.entiti_sasaran}
                                            </Badge>
                                        </td>
                                        <td>
                                            <SesiBadge status={s.status_sesi} />
                                        </td>
                                        <td className="hidden md:table-cell text-right">{s.bil_baris_dibaca}</td>
                                        <td className="hidden md:table-cell text-right">{s.bil_berjaya}</td>
                                        <td className="hidden md:table-cell text-right">{s.bil_ditolak}</td>
                                        <td className="hidden lg:table-cell whitespace-nowrap">{fmtTarikh(s.tarikh_masa, true)}</td>
                                    </tr>
                                ))
                            )}
                        </Table>
                    </div>
                    <Pager meta={data?.meta} page={page} onPage={setPage} />
                </>
            )}
        </Card>
    );
}
