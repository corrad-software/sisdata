'use client';

import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Tabs, TabPanel } from '@/components/ui/Tabs';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Table } from '@/components/ui/Table';
import { InputLabel } from '@/components/ui/InputLabel';
import { Card, EmptyRow, ExportMenu, FieldError, KlasifikasiBadge, SelectField, inputClass } from '@/components/pro/common';
import { KLASIFIKASI_LABEL, errorMessage, fieldErrors, fmtNilai, fmtTarikh, proApi, useCan, type JadualKamus, type MedanKamus } from '@/lib/pro';

const KLAS_OPTIONS = Object.entries(KLASIFIKASI_LABEL).map(([kod, nama]) => ({ kod, nama }));

type Target = { jadual: JadualKamus; medan: null } | { jadual: null; medan: MedanKamus };

function MetadataModal({ target, onClose }: { target: Target | null; onClose: () => void }) {
    const queryClient = useQueryClient();
    const m = target?.medan ?? null;
    const t = target?.jadual ?? null;
    const [f, setF] = useState({
        nama_perniagaan: m?.nama_perniagaan ?? t?.nama_perniagaan ?? '',
        definisi: m?.definisi ?? t?.definisi ?? '',
        format: m?.format ?? '',
        nilai_dibenarkan: m?.nilai_dibenarkan ?? '',
        wajib: m?.wajib === null || m?.wajib === undefined ? '' : m.wajib ? '1' : '0',
        data_owner: m?.data_owner ?? '',
        data_steward: m?.data_steward ?? '',
        klasifikasi: m?.klasifikasi ?? t?.klasifikasi ?? 'dalaman',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const set = (k: keyof typeof f) => (v: string) => setF((cur) => ({ ...cur, [k]: v }));

    async function submit(e: FormEvent) {
        e.preventDefault();
        if (!target) return;
        setBusy(true);
        setErrors({});
        setError(null);
        const n = (s: string) => (s.trim() === '' ? null : s.trim());
        try {
            const res = await proApi.simpanMetadata({
                nama_jadual: m?.nama_jadual ?? t?.nama_jadual,
                nama_medan: m?.nama_medan ?? null,
                nama_perniagaan: f.nama_perniagaan.trim(),
                definisi: n(f.definisi),
                format: m ? n(f.format) : null,
                nilai_dibenarkan: m ? n(f.nilai_dibenarkan) : null,
                wajib: m && f.wajib !== '' ? f.wajib === '1' : null,
                data_owner: n(f.data_owner),
                data_steward: n(f.data_steward),
                klasifikasi: f.klasifikasi,
            });
            toast.success(res.message);
            queryClient.invalidateQueries({ queryKey: ['pro', 'kamus'] });
            onClose();
        } catch (err) {
            setErrors(fieldErrors(err));
            setError(errorMessage(err));
        } finally {
            setBusy(false);
        }
    }

    const text = (k: keyof typeof f, label: string, required = false) => (
        <div>
            <InputLabel htmlFor={`km-${k}`} className="mb-1.5">
                {label} {required && <span className="text-danger-600">*</span>}
            </InputLabel>
            <input id={`km-${k}`} className={`${inputClass} ${errors[k] ? 'border-danger-500' : ''}`} value={f[k]} onChange={(e) => set(k)(e.target.value)} />
            <FieldError message={errors[k]} />
        </div>
    );

    return (
        <Modal open={!!target} onClose={onClose}>
            {target && (
                <form onSubmit={submit} className="p-6 space-y-4 max-h-[85vh] overflow-y-auto" noValidate>
                    <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900 break-all">
                        Metadata: {m ? `${m.nama_jadual}.${m.nama_medan}` : t?.nama_jadual}
                    </h2>
                    {m && (
                        <p className="text-body-xs text-txt-black-500">
                            Jenis teknikal: {m.jenis_data ?? '—'}
                            {m.saiz ? `(${m.saiz})` : ''} · dibaca daripada katalog pangkalan data dan tidak boleh diubah di sini.
                        </p>
                    )}
                    {text('nama_perniagaan', 'Nama perniagaan', true)}
                    <div>
                        <InputLabel htmlFor="km-definisi" className="mb-1.5">
                            Definisi
                        </InputLabel>
                        <textarea id="km-definisi" rows={3} maxLength={2000} className={inputClass} value={f.definisi} onChange={(e) => set('definisi')(e.target.value)} />
                        <FieldError message={errors.definisi} />
                    </div>
                    {m && (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            {text('format', 'Format')}
                            {text('nilai_dibenarkan', 'Nilai dibenarkan')}
                            <SelectField
                                id="km-wajib"
                                label="Wajib"
                                value={f.wajib}
                                onChange={set('wajib')}
                                options={[
                                    { kod: '1', nama: 'Ya' },
                                    { kod: '0', nama: 'Tidak' },
                                ]}
                                placeholder="Ikut katalog"
                            />
                        </div>
                    )}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {text('data_owner', 'Data owner')}
                        {text('data_steward', 'Data steward')}
                    </div>
                    <SelectField id="km-klas" label="Klasifikasi" value={f.klasifikasi} onChange={set('klasifikasi')} options={KLAS_OPTIONS} placeholder={null} required error={errors.klasifikasi} />
                    {(f.klasifikasi === 'sulit' || f.klasifikasi === 'rahsia') && (
                        <Alert type="warning">Medan Sulit/Rahsia akan disamarkan (&quot;Terhad&quot;) dalam UI, API dan eksport bagi pengguna tanpa kebenaran berkaitan.</Alert>
                    )}
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

function KamusData() {
    const can = useCan();
    const editable = can('pro.kamus.kemas_kini');
    const [jadual, setJadual] = useState('lot_tanah');
    const [q, setQ] = useState('');
    const [debouncedQ, setDebouncedQ] = useState('');
    const [klasifikasi, setKlasifikasi] = useState('');
    const [target, setTarget] = useState<Target | null>(null);
    const [targetKey, setTargetKey] = useState(0);

    useEffect(() => {
        const t = setTimeout(() => setDebouncedQ(q.trim()), 350);
        return () => clearTimeout(t);
    }, [q]);

    const tables = useQuery({ queryKey: ['pro', 'kamus', 'jadual'], queryFn: () => proApi.jadualKamus().then((r) => r.data) });
    const f = { jadual, q: debouncedQ, klasifikasi };
    const cols = useQuery({ queryKey: ['pro', 'kamus', 'medan', f], queryFn: () => proApi.kamus(f).then((r) => r.data) });

    function edit(t: Target) {
        setTarget(t);
        setTargetKey((k) => k + 1);
    }

    return (
        <div className="space-y-6 min-w-0 max-w-full">
            <div className="flex flex-wrap justify-end">
                <ExportMenu label={jadual ? `Eksport ${jadual}` : 'Eksport kamus'} onExport={(fmt) => proApi.eksportKamus(fmt, jadual || undefined)} />
            </div>

            <section className="space-y-2">
                <h4 className="font-semibold text-body-md text-txt-black-900">Jadual</h4>
                {tables.error ? (
                    <Alert type="error">{errorMessage(tables.error)}</Alert>
                ) : (
                    <div className="overflow-x-auto max-h-96 overflow-y-auto rounded-lg">
                        <Table
                            head={
                                <>
                                    <th>Jadual</th>
                                    <th className="hidden md:table-cell">Definisi</th>
                                    <th>Klasifikasi</th>
                                    <th className="hidden sm:table-cell text-right">Medan</th>
                                    <th>
                                        <span className="sr-only">Tindakan</span>
                                    </th>
                                </>
                            }
                        >
                            {tables.isLoading ? (
                                <EmptyRow colSpan={5}>Memuatkan…</EmptyRow>
                            ) : (
                                (tables.data ?? []).map((t) => (
                                    <tr key={t.nama_jadual} className={jadual === t.nama_jadual ? 'bg-bg-primary-50' : undefined}>
                                        <td className="min-w-[10rem]">
                                            <button type="button" className="text-left font-mono text-body-xs text-txt-primary hover:underline break-all" onClick={() => setJadual(jadual === t.nama_jadual ? '' : t.nama_jadual)}>
                                                {t.nama_jadual}
                                            </button>
                                            {t.nama_perniagaan && <span className="block text-body-sm text-txt-black-900">{t.nama_perniagaan}</span>}
                                            {t.jenis !== 'BASE TABLE' && t.jenis !== 'table' && (
                                                <Badge variant="neutral" shape="tag">
                                                    {t.jenis}
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="hidden md:table-cell text-body-xs text-txt-black-700 min-w-[14rem]">{t.definisi ?? '—'}</td>
                                        <td>
                                            <KlasifikasiBadge value={t.klasifikasi} />
                                        </td>
                                        <td className="hidden sm:table-cell text-right">{t.bil_medan}</td>
                                        <td className="whitespace-nowrap">
                                            {editable && (
                                                <Button size="sm" variant="secondary" onClick={() => edit({ jadual: t, medan: null })}>
                                                    Sunting
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </Table>
                    </div>
                )}
            </section>

            <section className="space-y-3">
                <h4 className="font-semibold text-body-md text-txt-black-900">Medan {jadual && <span className="font-mono text-body-sm text-txt-black-500">· {jadual}</span>}</h4>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div className="sm:col-span-1">
                        <InputLabel htmlFor="km-q" className="mb-1.5">
                            Carian
                        </InputLabel>
                        <input id="km-q" type="search" className={inputClass} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Nama medan, nama perniagaan, definisi…" />
                    </div>
                    <SelectField id="km-jadual" label="Jadual" value={jadual} onChange={setJadual} options={(tables.data ?? []).map((t) => ({ kod: t.nama_jadual, nama: t.nama_jadual }))} />
                    <SelectField id="km-f-klas" label="Klasifikasi" value={klasifikasi} onChange={setKlasifikasi} options={KLAS_OPTIONS} />
                </div>
                {cols.error ? (
                    <Alert type="error">{errorMessage(cols.error)}</Alert>
                ) : (
                    <>
                        <p className="text-body-sm text-txt-black-500" aria-live="polite">
                            {cols.data ? `${cols.data.length} medan` : 'Memuatkan…'}
                        </p>
                        <div className="overflow-x-auto">
                            <Table
                                head={
                                    <>
                                        <th>Medan</th>
                                        <th className="hidden md:table-cell">Nama perniagaan / definisi</th>
                                        <th className="hidden sm:table-cell">Jenis</th>
                                        <th className="hidden lg:table-cell">Kunci</th>
                                        <th>Klasifikasi</th>
                                        <th className="hidden xl:table-cell">Owner / steward</th>
                                        <th>
                                            <span className="sr-only">Tindakan</span>
                                        </th>
                                    </>
                                }
                            >
                                {cols.isLoading ? (
                                    <EmptyRow colSpan={7}>Memuatkan…</EmptyRow>
                                ) : (cols.data ?? []).length === 0 ? (
                                    <EmptyRow colSpan={7}>Tiada medan ditemui.</EmptyRow>
                                ) : (
                                    cols.data?.map((c) => (
                                        <tr key={`${c.nama_jadual}.${c.nama_medan}`}>
                                            <td className="min-w-[10rem]">
                                                <span className="font-mono text-body-xs break-all">
                                                    {jadual ? '' : `${c.nama_jadual}.`}
                                                    <strong>{c.nama_medan}</strong>
                                                </span>
                                                <span className="flex flex-wrap gap-1 mt-1">
                                                    {c.wajib && (
                                                        <Badge variant="info" shape="tag">
                                                            Wajib
                                                        </Badge>
                                                    )}
                                                    {!c.dikurasi && (
                                                        <Badge variant="neutral" shape="tag">
                                                            Belum dikurasi
                                                        </Badge>
                                                    )}
                                                </span>
                                                <span className="md:hidden block text-body-xs text-txt-black-700 mt-1">{c.nama_perniagaan}</span>
                                            </td>
                                            <td className="hidden md:table-cell min-w-[14rem]">
                                                <span className="block text-body-sm text-txt-black-900">{c.nama_perniagaan ?? '—'}</span>
                                                {c.definisi && <span className="block text-body-xs text-txt-black-500">{c.definisi}</span>}
                                                {(c.format || c.nilai_dibenarkan) && (
                                                    <span className="block text-body-xs text-txt-black-500">
                                                        {c.format ? `Format: ${c.format}` : ''}
                                                        {c.format && c.nilai_dibenarkan ? ' · ' : ''}
                                                        {c.nilai_dibenarkan ? `Nilai: ${c.nilai_dibenarkan}` : ''}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="hidden sm:table-cell whitespace-nowrap font-mono text-body-xs">
                                                {c.jenis_data ?? '—'}
                                                {c.saiz ? `(${c.saiz})` : ''}
                                                {c.nilai_lalai && <span className="block text-txt-black-500 font-sans">Lalai: {fmtNilai(c.nilai_lalai)}</span>}
                                            </td>
                                            <td className="hidden lg:table-cell">
                                                <span className="flex flex-wrap gap-1">
                                                    {c.kunci.map((k) => (
                                                        <Badge key={k} variant={k === 'PK' ? 'warning' : 'neutral'} shape="tag">
                                                            {k}
                                                        </Badge>
                                                    ))}
                                                </span>
                                            </td>
                                            <td>
                                                <KlasifikasiBadge value={c.klasifikasi} />
                                            </td>
                                            <td className="hidden xl:table-cell text-body-xs">
                                                {c.data_owner ?? '—'}
                                                <span className="block text-txt-black-500">{c.data_steward ?? ''}</span>
                                            </td>
                                            <td>
                                                {editable && (
                                                    <Button size="sm" variant="secondary" onClick={() => edit({ jadual: null, medan: c })}>
                                                        Sunting
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </Table>
                        </div>
                    </>
                )}
            </section>
            <MetadataModal key={targetKey} target={target} onClose={() => setTarget(null)} />
        </div>
    );
}

function DaftarKlasifikasi() {
    const { data, isLoading, error } = useQuery({ queryKey: ['pro', 'kamus', 'klasifikasi'], queryFn: () => proApi.klasifikasi().then((r) => r.data) });
    if (error) return <Alert type="error">{errorMessage(error)}</Alert>;
    return (
        <div className="overflow-x-auto">
            <Table
                head={
                    <>
                        <th>Komponen data</th>
                        <th>Aras</th>
                        <th className="hidden md:table-cell">Keperluan penyulitan</th>
                        <th className="hidden md:table-cell">Peranan dibenarkan</th>
                        <th className="hidden lg:table-cell">Kebenaran</th>
                        <th className="hidden lg:table-cell">Semakan</th>
                    </>
                }
            >
                {isLoading ? (
                    <EmptyRow colSpan={6}>Memuatkan…</EmptyRow>
                ) : (data ?? []).length === 0 ? (
                    <EmptyRow colSpan={6}>Tiada rekod klasifikasi.</EmptyRow>
                ) : (
                    data?.map((k) => (
                        <tr key={k.id}>
                            <td className="min-w-[10rem]">
                                {k.komponen_data}
                                <span className="block text-body-xs font-mono text-txt-black-500">{k.entiti}</span>
                            </td>
                            <td>
                                <KlasifikasiBadge value={k.aras_kerahsiaan} />
                            </td>
                            <td className="hidden md:table-cell">{k.keperluan_encryption}</td>
                            <td className="hidden md:table-cell">{k.peranan_dibenarkan}</td>
                            <td className="hidden lg:table-cell font-mono text-body-xs">{k.kebenaran_diperlukan ?? '—'}</td>
                            <td className="hidden lg:table-cell whitespace-nowrap">{fmtTarikh(k.tarikh_semakan)}</td>
                        </tr>
                    ))
                )}
            </Table>
        </div>
    );
}

export default function KamusPage() {
    return (
        <PageContainer>
            <Card>
                <Tabs tabs={{ kamus: 'Kamus data', klasifikasi: 'Daftar klasifikasi' }}>
                    <TabPanel value="kamus">
                        <KamusData />
                    </TabPanel>
                    <TabPanel value="klasifikasi">
                        <DaftarKlasifikasi />
                    </TabPanel>
                </Tabs>
            </Card>
        </PageContainer>
    );
}
