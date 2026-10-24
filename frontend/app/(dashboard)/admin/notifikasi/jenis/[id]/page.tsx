'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { Alert } from '@/components/ui/Alert';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { FieldInput } from '@/components/ui/FieldInput';
import { InputLabel } from '@/components/ui/InputLabel';
import { Textarea } from '@/components/ui/Textarea';
import { Toggle } from '@/components/ui/Toggle';
import { ApiError } from '@/lib/api';
import {
    activateTemplate,
    addRule,
    deleteRule,
    fetchType,
    KEUTAMAAN_LABEL,
    previewTemplate,
    SALURAN_LABEL,
    SASARAN_LABEL,
    saveTemplate,
    tarikhMasa,
    updateType,
    type JenisDetail,
    type Keutamaan,
    type Peraturan,
    type Saluran,
} from '@/lib/ntf';

const selectClass =
    'block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm';

function firstError(error: unknown, fallback: string) {
    return error instanceof ApiError && error.errors ? (Object.values(error.errors)[0]?.[0] ?? fallback) : error instanceof ApiError ? error.message : fallback;
}

function Card({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-4 sm:p-6 space-y-4">
            <h3 className="font-heading text-body-lg font-semibold text-txt-black-900">{title}</h3>
            {children}
        </section>
    );
}

function SettingsForm({ jenis }: { jenis: JenisDetail }) {
    const queryClient = useQueryClient();
    const [form, setForm] = useState({
        nama: jenis.nama,
        keutamaan: jenis.keutamaan,
        wajib: jenis.wajib,
        aktif: jenis.aktif,
        saluran_lalai: jenis.saluran_lalai,
        kekerapan: jenis.kekerapan,
        tempoh_eskalasi_minit: jenis.tempoh_eskalasi_minit?.toString() ?? '',
        tempoh_pendiaman_minit: jenis.tempoh_pendiaman_minit.toString(),
        hari_peringatan: (jenis.hari_peringatan ?? []).join(', '),
        medan_sensitif: jenis.medan_sensitif,
    });

    const save = useMutation({
        mutationFn: () =>
            updateType(jenis.id, {
                nama: form.nama,
                keutamaan: form.keutamaan,
                wajib: form.wajib,
                aktif: form.aktif,
                saluran_lalai: form.saluran_lalai,
                kekerapan: form.kekerapan,
                tempoh_eskalasi_minit: form.tempoh_eskalasi_minit === '' ? null : Number(form.tempoh_eskalasi_minit),
                tempoh_pendiaman_minit: Number(form.tempoh_pendiaman_minit || 0),
                hari_peringatan: form.hari_peringatan.trim()
                    ? form.hari_peringatan
                          .split(',')
                          .map((d) => Number(d.trim()))
                          .filter((d) => !Number.isNaN(d))
                    : null,
                medan_sensitif: form.medan_sensitif,
            }),
        onSuccess: (res) => {
            toast.success(res.message);
            queryClient.invalidateQueries({ queryKey: ['ntf', 'jenis'] });
        },
        onError: (e) => toast.error(firstError(e, 'Tidak dapat disimpan.')),
    });

    function toggle<T extends string>(list: T[], value: T) {
        return list.includes(value) ? list.filter((v) => v !== value) : [...list, value];
    }

    return (
        <form
            onSubmit={(e: FormEvent) => {
                e.preventDefault();
                save.mutate();
            }}
            className="space-y-4"
        >
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <FieldInput label="Nama" name="nama" value={form.nama} onChange={(v) => setForm((f) => ({ ...f, nama: v }))} required />
                <div>
                    <InputLabel htmlFor="keutamaan" className="mb-1.5">
                        Keutamaan
                    </InputLabel>
                    <select id="keutamaan" className={selectClass} value={form.keutamaan} onChange={(e) => setForm((f) => ({ ...f, keutamaan: e.target.value as Keutamaan }))}>
                        {(Object.keys(KEUTAMAAN_LABEL) as Keutamaan[]).map((k) => (
                            <option key={k} value={k}>
                                {KEUTAMAAN_LABEL[k]}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <InputLabel htmlFor="kekerapan" className="mb-1.5">
                        Kekerapan penghantaran e-mel
                    </InputLabel>
                    <select id="kekerapan" className={selectClass} value={form.kekerapan} onChange={(e) => setForm((f) => ({ ...f, kekerapan: e.target.value as 'serta_merta' | 'ringkasan' }))}>
                        <option value="serta_merta">Serta-merta</option>
                        <option value="ringkasan">Ringkasan harian (Biasa/Maklumat sahaja)</option>
                    </select>
                </div>
                <FieldInput
                    label="Jarak hari peringatan (dipisah koma)"
                    name="hari_peringatan"
                    value={form.hari_peringatan}
                    onChange={(v) => setForm((f) => ({ ...f, hari_peringatan: v }))}
                    placeholder="cth. 30, 14, 7, 1"
                    help="Untuk peringatan berjadual sahaja."
                />
                <FieldInput
                    label="Tempoh eskalasi jika belum dibaca (minit)"
                    name="tempoh_eskalasi_minit"
                    type="number"
                    value={form.tempoh_eskalasi_minit}
                    onChange={(v) => setForm((f) => ({ ...f, tempoh_eskalasi_minit: v }))}
                    help="Terpakai kepada keutamaan Kritikal."
                />
                <FieldInput
                    label="Tempoh pendiaman peristiwa berulang (minit)"
                    name="tempoh_pendiaman_minit"
                    type="number"
                    value={form.tempoh_pendiaman_minit}
                    onChange={(v) => setForm((f) => ({ ...f, tempoh_pendiaman_minit: v }))}
                />
            </div>

            <fieldset className="space-y-2">
                <legend className="text-body-sm font-medium text-txt-black-900">Saluran lalai</legend>
                <div className="flex flex-wrap gap-4">
                    {(Object.keys(SALURAN_LABEL) as Saluran[]).map((s) => (
                        <label key={s} className="inline-flex items-center gap-2 text-body-sm">
                            <input
                                type="checkbox"
                                className="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary"
                                checked={form.saluran_lalai.includes(s)}
                                onChange={() => setForm((f) => ({ ...f, saluran_lalai: toggle(f.saluran_lalai, s) }))}
                            />
                            {SALURAN_LABEL[s]}
                            {(s === 'sms' || s === 'whatsapp') && <span className="text-body-xs text-txt-black-500">(antara muka sahaja)</span>}
                        </label>
                    ))}
                </div>
            </fieldset>

            <fieldset className="space-y-2">
                <legend className="text-body-sm font-medium text-txt-black-900">Medan Sulit/Rahsia (ditolak jika dihantar)</legend>
                <div className="flex flex-wrap gap-4">
                    {jenis.medan.length === 0 && <span className="text-body-xs text-txt-black-500">Tiada medan diisytihar.</span>}
                    {jenis.medan.map((m) => (
                        <label key={m} className="inline-flex items-center gap-2 text-body-sm">
                            <input
                                type="checkbox"
                                className="rounded border-otl-gray-300 text-danger-600 focus:ring-fr-danger"
                                checked={form.medan_sensitif.includes(m)}
                                onChange={() => setForm((f) => ({ ...f, medan_sensitif: toggle(f.medan_sensitif, m) }))}
                            />
                            <code className="text-body-xs">{m}</code>
                        </label>
                    ))}
                </div>
                <p className="text-body-xs text-txt-black-500">Senarai global (parameter ADM): {jenis.medan_sensitif_global.join(', ')}</p>
            </fieldset>

            <div className="flex flex-wrap gap-6">
                <Toggle name="wajib" label="Wajib (pengguna tidak boleh mematikan)" checked={form.wajib} onChange={(v) => setForm((f) => ({ ...f, wajib: v }))} />
                <Toggle name="aktif" label="Aktif" checked={form.aktif} onChange={(v) => setForm((f) => ({ ...f, aktif: v }))} />
            </div>

            <div className="flex justify-end">
                <Button type="submit" disabled={save.isPending}>
                    Simpan tetapan jenis
                </Button>
            </div>
        </form>
    );
}

function TemplateEditor({ jenis }: { jenis: JenisDetail }) {
    const queryClient = useQueryClient();
    const active = jenis.templat.find((t) => t.aktif) ?? jenis.templat[0];
    const [subjek, setSubjek] = useState(active?.subjek ?? '');
    const [kandungan, setKandungan] = useState(active?.kandungan ?? '');
    const [catatan, setCatatan] = useState('');
    const [sample, setSample] = useState<Record<string, string>>({});
    const [preview, setPreview] = useState<{ subjek: string; kandungan: string; medan_tidak_dikenali: string[] } | null>(null);
    const bodyRef = useRef<HTMLDivElement>(null);
    const fields = [...jenis.medan, ...jenis.medan_sistem];

    const doPreview = useMutation({
        mutationFn: () => previewTemplate(jenis.id, { subjek, kandungan, data: sample }),
        onSuccess: (res) => setPreview(res.data),
        onError: (e) => toast.error(firstError(e, 'Pratonton gagal.')),
    });

    const save = useMutation({
        mutationFn: () => saveTemplate(jenis.id, { subjek, kandungan, catatan: catatan || undefined }),
        onSuccess: (res) => {
            toast.success(res.message);
            setCatatan('');
            queryClient.invalidateQueries({ queryKey: ['ntf', 'jenis'] });
        },
        onError: (e) => toast.error(firstError(e, 'Templat tidak dapat disimpan.')),
    });

    const activate = useMutation({
        mutationFn: (templatId: number) => activateTemplate(jenis.id, templatId),
        onSuccess: (res) => {
            toast.success(res.message);
            queryClient.invalidateQueries({ queryKey: ['ntf', 'jenis'] });
        },
    });

    function insert(field: string) {
        const textarea = bodyRef.current?.querySelector('textarea');
        const token = `{{${field}}}`;
        if (!textarea) return setKandungan((k) => k + token);
        const start = textarea.selectionStart ?? kandungan.length;
        const end = textarea.selectionEnd ?? kandungan.length;
        setKandungan(kandungan.slice(0, start) + token + kandungan.slice(end));
    }

    return (
        <div className="space-y-4">
            <div className="space-y-2">
                <p className="text-body-sm text-txt-black-700">Medan dinamik (klik untuk sisip ke kandungan):</p>
                <div className="flex flex-wrap gap-2">
                    {fields.map((f) => (
                        <button
                            key={f}
                            type="button"
                            onClick={() => insert(f)}
                            className={`rounded-md border px-2 py-1 font-mono text-body-xs hover:bg-bg-washed ${
                                jenis.medan_sensitif.includes(f) ? 'border-otl-danger-200 text-txt-danger line-through' : 'border-otl-gray-300 text-txt-black-700'
                            }`}
                            disabled={jenis.medan_sensitif.includes(f)}
                            title={jenis.medan_sensitif.includes(f) ? 'Medan Sulit/Rahsia — tidak dibenarkan' : undefined}
                        >
                            {`{{${f}}}`}
                        </button>
                    ))}
                </div>
            </div>

            <FieldInput label="Subjek" name="subjek" value={subjek} onChange={setSubjek} required />
            <div ref={bodyRef}>
                <Textarea label="Kandungan" name="kandungan" value={kandungan} onChange={setKandungan} rows={6} maxLength={5000} required />
            </div>
            <FieldInput label="Catatan versi" name="catatan" value={catatan} onChange={setCatatan} placeholder="cth. Semakan selepas mesyuarat" />

            {jenis.medan.length > 0 && (
                <details className="rounded-md border border-otl-gray-200 p-3">
                    <summary className="cursor-pointer text-body-sm font-medium text-txt-black-900">Nilai contoh untuk pratonton</summary>
                    <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        {jenis.medan.map((f) => (
                            <FieldInput key={f} label={f} name={`contoh-${f}`} value={sample[f] ?? ''} onChange={(v) => setSample((s) => ({ ...s, [f]: v }))} />
                        ))}
                    </div>
                </details>
            )}

            <div className="flex flex-wrap justify-end gap-3">
                <Button variant="secondary" onClick={() => doPreview.mutate()} disabled={doPreview.isPending}>
                    Pratonton
                </Button>
                <Button onClick={() => save.mutate()} disabled={save.isPending || !subjek || !kandungan}>
                    Simpan sebagai versi baharu
                </Button>
            </div>

            {preview && (
                <div className="rounded-lg border border-otl-gray-200 bg-bg-washed p-4 space-y-2" data-testid="ntf-pratonton">
                    <p className="text-body-xs font-medium uppercase tracking-wide text-txt-black-500">Pratonton</p>
                    <p className="text-body-md font-semibold text-txt-black-900">{preview.subjek}</p>
                    <p className="whitespace-pre-line text-body-sm text-txt-black-700">{preview.kandungan}</p>
                    {preview.medan_tidak_dikenali.length > 0 && (
                        <Alert type="warning">Medan tidak dikenali: {preview.medan_tidak_dikenali.map((m) => `{{${m}}}`).join(', ')}</Alert>
                    )}
                </div>
            )}

            <div className="space-y-2">
                <h4 className="text-body-sm font-semibold text-txt-black-900">Sejarah versi</h4>
                <ul className="divide-y divide-otl-divider rounded-lg border border-otl-gray-200">
                    {jenis.templat.map((t) => (
                        <li key={t.id} className="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="min-w-0">
                                <div className="flex items-center gap-2">
                                    <span className="text-body-sm font-medium">Versi {t.versi}</span>
                                    {t.aktif && <Badge variant="success">Aktif</Badge>}
                                </div>
                                <p className="truncate text-body-xs text-txt-black-500">
                                    {t.subjek} · {tarikhMasa(t.created_at)}
                                    {t.catatan ? ` · ${t.catatan}` : ''}
                                </p>
                            </div>
                            <div className="flex gap-3">
                                <button
                                    type="button"
                                    className="text-body-sm font-medium text-primary-600 hover:underline"
                                    onClick={() => {
                                        setSubjek(t.subjek);
                                        setKandungan(t.kandungan);
                                    }}
                                >
                                    Muat ke editor
                                </button>
                                {!t.aktif && (
                                    <button type="button" className="text-body-sm font-medium text-primary-600 hover:underline" onClick={() => activate.mutate(t.id)}>
                                        Aktifkan
                                    </button>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}

function RulesEditor({ jenis }: { jenis: JenisDetail }) {
    const queryClient = useQueryClient();
    const [sasaran, setSasaran] = useState<Peraturan['sasaran']>('peranan');
    const [nilai, setNilai] = useState('');

    const add = useMutation({
        mutationFn: () => addRule(jenis.id, { sasaran, nilai }),
        onSuccess: (res) => {
            toast.success(res.message);
            setNilai('');
            queryClient.invalidateQueries({ queryKey: ['ntf', 'jenis'] });
        },
        onError: (e) => toast.error(firstError(e, 'Peraturan tidak dapat ditambah.')),
    });
    const remove = useMutation({
        mutationFn: (ruleId: number) => deleteRule(jenis.id, ruleId),
        onSuccess: (res) => {
            toast.success(res.message);
            queryClient.invalidateQueries({ queryKey: ['ntf', 'jenis'] });
        },
    });

    return (
        <div className="space-y-4">
            <p className="text-body-sm text-txt-black-700">
                Penerima tambahan bagi setiap notifikasi jenis ini, selain penerima yang ditentukan oleh modul pencetus.
            </p>
            <ul className="divide-y divide-otl-divider rounded-lg border border-otl-gray-200">
                {jenis.peraturan.length === 0 && <li className="p-3 text-body-sm text-txt-black-500">Tiada peraturan penerima.</li>}
                {jenis.peraturan.map((p) => (
                    <li key={p.id} className="flex items-center justify-between gap-3 p-3 text-body-sm">
                        <span>
                            <span className="text-txt-black-500">{SASARAN_LABEL[p.sasaran]}:</span> <code>{p.nilai}</code>
                        </span>
                        <button type="button" className="font-medium text-danger-600 hover:underline" onClick={() => remove.mutate(p.id)}>
                            Buang
                        </button>
                    </li>
                ))}
            </ul>
            <form
                className="flex flex-col gap-3 sm:flex-row sm:items-end"
                onSubmit={(e) => {
                    e.preventDefault();
                    add.mutate();
                }}
            >
                <div className="sm:w-48">
                    <InputLabel htmlFor="sasaran" className="mb-1.5">
                        Sasaran
                    </InputLabel>
                    <select id="sasaran" className={selectClass} value={sasaran} onChange={(e) => setSasaran(e.target.value as Peraturan['sasaran'])}>
                        {(Object.keys(SASARAN_LABEL) as Peraturan['sasaran'][]).map((s) => (
                            <option key={s} value={s}>
                                {SASARAN_LABEL[s]}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="flex-1">
                    <FieldInput label="Nilai (kod)" name="nilai" value={nilai} onChange={setNilai} placeholder="cth. pentadbir_aplikasi atau 10" required />
                </div>
                <Button type="submit" disabled={add.isPending || !nilai}>
                    Tambah
                </Button>
            </form>
        </div>
    );
}

export default function TypeDetailPage() {
    const { id } = useParams<{ id: string }>();
    const { data, isLoading, error } = useQuery({ queryKey: ['ntf', 'jenis', id], queryFn: () => fetchType(id) });
    const jenis = data?.data;

    return (
        <PageContainer>
            <Link href="/admin/notifikasi/jenis" className="text-body-sm font-medium text-primary-600 hover:underline">
                ← Senarai jenis notifikasi
            </Link>

            {isLoading && <p className="text-body-sm text-txt-black-500">Memuatkan…</p>}
            {error instanceof ApiError && <Alert type="error">{error.status === 403 ? 'Anda tidak mempunyai kebenaran.' : error.message}</Alert>}

            {jenis && (
                <>
                    <div>
                        <h3 className="font-heading text-heading-3xs font-semibold text-txt-black-900">{jenis.nama}</h3>
                        <p className="text-body-xs text-txt-black-500 break-all">
                            {jenis.kod} · modul {jenis.modul.toUpperCase()}
                        </p>
                    </div>
                    {/* key: remount the template editor when versions change */}
                    <Card title="Tetapan jenis">
                        <SettingsForm key={jenis.id} jenis={jenis} />
                    </Card>
                    <Card title="Templat mesej">
                        <TemplateEditor key={`t-${jenis.templat.map((t) => `${t.id}${t.aktif}`).join()}`} jenis={jenis} />
                    </Card>
                    <Card title="Peraturan penerima">
                        <RulesEditor jenis={jenis} />
                    </Card>
                </>
            )}
        </PageContainer>
    );
}
