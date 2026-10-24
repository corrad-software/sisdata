'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { PageContainer } from '@/components/PageContainer';
import { PageHeader } from '@/components/PageHeader';
import { Tabs, TabPanel } from '@/components/ui/Tabs';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Alert } from '@/components/ui/Alert';
import { InputLabel } from '@/components/ui/InputLabel';
import { ApiError } from '@/lib/api';
import { dmsApi, KLASIFIKASI_LABEL, saizMesra, tarikhMesra, type Kategori, type Klasifikasi, type MedanMetadata } from '@/lib/dms';

const inputClass =
    'block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm';

const TABS = { kategori: 'Kategori & retensi', medan: 'Medan metadata', parameter: 'Senarai putih & had', kuota: 'Kuota storan', kuarantin: 'Kuarantin', pelupusan: 'Pelupusan' };
const ALL_TYPES = ['pdf', 'jpg', 'jpeg', 'png', 'tiff', 'docx', 'xlsx', 'pptx', 'dwg', 'shp', 'kml', 'kmz', 'geojson'];
const TINDAKAN: Record<string, string> = { lupus: 'Lupus (perlu kelulusan)', semak: 'Semak semula', kekal: 'Simpan kekal' };

function Forbidden({ error }: { error: unknown }) {
    return <Alert type="error">{error instanceof ApiError && error.status === 403 ? 'Anda tiada kebenaran untuk bahagian ini.' : String((error as Error)?.message)}</Alert>;
}

export default function DmsSettingsPage() {
    const [tab, setTab] = useState<string | null>(null);
    useEffect(() => {
        const requested = new URLSearchParams(window.location.search).get('tab');
        // eslint-disable-next-line react-hooks/set-state-in-effect -- read the deep-linked tab once on mount
        setTab(requested && requested in TABS ? requested : 'kategori');
    }, []);

    return (
        <>
            <PageHeader title="Tetapan Dokumen Digital" breadcrumbs={[{ label: 'Dokumen Digital', href: '/admin/dms' }, { label: 'Tetapan' }]} />
            <PageContainer>
                <div className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-4 sm:p-6">
                    {tab && (
                        <Tabs tabs={TABS} defaultTab={tab}>
                            <TabPanel value="kategori">
                                <CategoriesTab />
                            </TabPanel>
                            <TabPanel value="medan">
                                <FieldsTab />
                            </TabPanel>
                            <TabPanel value="parameter">
                                <ParametersTab />
                            </TabPanel>
                            <TabPanel value="kuota">
                                <QuotaTab />
                            </TabPanel>
                            <TabPanel value="kuarantin">
                                <QuarantineTab />
                            </TabPanel>
                            <TabPanel value="pelupusan">
                                <DisposalsTab />
                            </TabPanel>
                        </Tabs>
                    )}
                </div>
            </PageContainer>
        </>
    );
}

function CategoriesTab() {
    const { data, error } = useQuery({ queryKey: ['dms', 'kategori', 'semua'], queryFn: () => dmsApi.kategori(true) });
    const [editing, setEditing] = useState<Partial<Kategori> | null>(null);
    if (error) return <Forbidden error={error} />;

    return (
        <div className="space-y-4">
            <div className="flex justify-between items-center gap-2">
                <p className="text-body-sm text-txt-black-500">Had saiz, jenis fail dan polisi retensi bagi setiap kategori (SRS-DMS-05, 21).</p>
                <Button size="sm" onClick={() => setEditing({ aktif: true, tindakan_selepas_arkib: 'semak', klasifikasi_lalai: 'dalaman' })}>
                    Tambah kategori
                </Button>
            </div>
            <div className="overflow-x-auto">
                <Table
                    head={
                        <>
                            <th>Kategori</th>
                            <th className="hidden md:table-cell">Jenis fail</th>
                            <th>Had saiz</th>
                            <th className="hidden sm:table-cell">Retensi / arkib</th>
                            <th>Status</th>
                            <th className="text-right">Tindakan</th>
                        </>
                    }
                >
                    {(data?.data ?? []).map((k) => (
                        <tr key={k.id}>
                            <td>
                                <span className="font-medium">{k.nama}</span>
                                <p className="text-body-xs text-txt-black-500">
                                    {k.kod} · lalai {KLASIFIKASI_LABEL[k.klasifikasi_lalai]}
                                </p>
                            </td>
                            <td className="hidden md:table-cell">{k.jenis_fail_dibenarkan?.map((t) => t.toUpperCase()).join(', ') ?? 'Semua dalam senarai putih'}</td>
                            <td className="whitespace-nowrap">{k.had_saiz_mb ? `${k.had_saiz_mb} MB` : 'Lalai'}</td>
                            <td className="hidden sm:table-cell">
                                {k.tempoh_retensi_bulan ? `${k.tempoh_retensi_bulan} bln aktif` : 'Tiada auto-arkib'}
                                {k.tempoh_arkib_bulan ? ` · ${k.tempoh_arkib_bulan} bln arkib` : ''}
                                <p className="text-body-xs text-txt-black-500">{TINDAKAN[k.tindakan_selepas_arkib]}</p>
                            </td>
                            <td>{k.aktif ? <Badge variant="success">Aktif</Badge> : <Badge variant="neutral">Tidak aktif</Badge>}</td>
                            <td className="text-right">
                                <button type="button" className="text-body-sm text-txt-primary hover:underline" onClick={() => setEditing(k)}>
                                    Ubah
                                </button>
                            </td>
                        </tr>
                    ))}
                </Table>
            </div>
            {editing && <CategoryModal value={editing} onClose={() => setEditing(null)} />}
        </div>
    );
}

function numberOrNull(v: string): number | null {
    return v === '' ? null : Number(v);
}

function CategoryModal({ value, onClose }: { value: Partial<Kategori>; onClose: () => void }) {
    const queryClient = useQueryClient();
    const [form, setForm] = useState<Partial<Kategori>>(value);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const save = useMutation({
        mutationFn: () => {
            const body = { ...form };
            delete body.medan;
            if (value.id) delete body.id;
            return dmsApi.simpanKategori(body, value.id);
        },
        onSuccess: () => {
            toast.success('Kategori disimpan.');
            queryClient.invalidateQueries({ queryKey: ['dms', 'kategori'] });
            onClose();
        },
        onError: (e: Error) => (e instanceof ApiError && e.errors ? setErrors(e.errors) : toast.error(e.message)),
    });
    const err = (k: string) => errors[k]?.[0] && <p className="mt-1 text-body-xs text-txt-danger">{errors[k][0]}</p>;
    const types = form.jenis_fail_dibenarkan ?? [];

    return (
        <Modal open onClose={onClose}>
            <div className="p-6 space-y-3 max-h-[85vh] overflow-y-auto">
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">{value.id ? 'Ubah kategori' : 'Tambah kategori'}</h2>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <InputLabel htmlFor="k-kod" className="mb-1.5">
                            Kod
                        </InputLabel>
                        <input id="k-kod" className={inputClass} value={form.kod ?? ''} disabled={!!value.id} onChange={(e) => setForm((f) => ({ ...f, kod: e.target.value }))} />
                        {err('kod')}
                    </div>
                    <div>
                        <InputLabel htmlFor="k-nama" className="mb-1.5">
                            Nama
                        </InputLabel>
                        <input id="k-nama" className={inputClass} value={form.nama ?? ''} onChange={(e) => setForm((f) => ({ ...f, nama: e.target.value }))} />
                        {err('nama')}
                    </div>
                    <div>
                        <InputLabel htmlFor="k-had" className="mb-1.5">
                            Had saiz (MB, kosong = lalai)
                        </InputLabel>
                        <input id="k-had" type="number" min={1} className={inputClass} value={form.had_saiz_mb ?? ''} onChange={(e) => setForm((f) => ({ ...f, had_saiz_mb: numberOrNull(e.target.value) }))} />
                        {err('had_saiz_mb')}
                    </div>
                    <div>
                        <InputLabel htmlFor="k-klas" className="mb-1.5">
                            Klasifikasi lalai
                        </InputLabel>
                        <select id="k-klas" className={inputClass} value={form.klasifikasi_lalai} onChange={(e) => setForm((f) => ({ ...f, klasifikasi_lalai: e.target.value as Klasifikasi }))}>
                            {(Object.keys(KLASIFIKASI_LABEL) as Klasifikasi[]).map((k) => (
                                <option key={k} value={k}>
                                    {KLASIFIKASI_LABEL[k]}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <InputLabel htmlFor="k-ret" className="mb-1.5">
                            Tempoh retensi aktif (bulan)
                        </InputLabel>
                        <input id="k-ret" type="number" min={1} className={inputClass} value={form.tempoh_retensi_bulan ?? ''} onChange={(e) => setForm((f) => ({ ...f, tempoh_retensi_bulan: numberOrNull(e.target.value) }))} />
                    </div>
                    <div>
                        <InputLabel htmlFor="k-ark" className="mb-1.5">
                            Tempoh arkib (bulan)
                        </InputLabel>
                        <input id="k-ark" type="number" min={1} className={inputClass} value={form.tempoh_arkib_bulan ?? ''} onChange={(e) => setForm((f) => ({ ...f, tempoh_arkib_bulan: numberOrNull(e.target.value) }))} />
                    </div>
                    <div>
                        <InputLabel htmlFor="k-tin" className="mb-1.5">
                            Tindakan selepas arkib
                        </InputLabel>
                        <select id="k-tin" className={inputClass} value={form.tindakan_selepas_arkib} onChange={(e) => setForm((f) => ({ ...f, tindakan_selepas_arkib: e.target.value as Kategori['tindakan_selepas_arkib'] }))}>
                            {Object.entries(TINDAKAN).map(([k, l]) => (
                                <option key={k} value={k}>
                                    {l}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <InputLabel htmlFor="k-amaran" className="mb-1.5">
                            Amaran luput (hari, kosong = lalai)
                        </InputLabel>
                        <input id="k-amaran" type="number" min={1} className={inputClass} value={form.tempoh_amaran_luput_hari ?? ''} onChange={(e) => setForm((f) => ({ ...f, tempoh_amaran_luput_hari: numberOrNull(e.target.value) }))} />
                    </div>
                </div>
                <fieldset>
                    <legend className="text-body-sm font-medium text-txt-black-900 mb-1.5">Jenis fail dibenarkan (tiada pilihan = semua dalam senarai putih)</legend>
                    <div className="flex flex-wrap gap-3">
                        {ALL_TYPES.map((t) => (
                            <label key={t} className="flex items-center gap-1.5 text-body-sm">
                                <input
                                    type="checkbox"
                                    className="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary"
                                    checked={types.includes(t)}
                                    onChange={(e) => {
                                        const next = e.target.checked ? [...types, t] : types.filter((x) => x !== t);
                                        setForm((f) => ({ ...f, jenis_fail_dibenarkan: next.length ? next : null }));
                                    }}
                                />
                                {t.toUpperCase()}
                            </label>
                        ))}
                    </div>
                </fieldset>
                <label className="flex items-center gap-2 text-body-sm">
                    <input type="checkbox" className="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary" checked={!!form.aktif} onChange={(e) => setForm((f) => ({ ...f, aktif: e.target.checked }))} />
                    Aktif
                </label>
                <div className="flex justify-end gap-3 pt-2">
                    <Button variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    <Button disabled={save.isPending} onClick={() => save.mutate()}>
                        Simpan
                    </Button>
                </div>
            </div>
        </Modal>
    );
}

function FieldsTab() {
    const { data, error } = useQuery({ queryKey: ['dms', 'kategori', 'semua'], queryFn: () => dmsApi.kategori(true) });
    const [editing, setEditing] = useState<Partial<MedanMetadata> | null>(null);
    if (error) return <Forbidden error={error} />;
    const categories = data?.data ?? [];
    const fields = new Map<number, MedanMetadata>();
    categories.forEach((k) => k.medan.forEach((m) => fields.set(m.id, m)));
    const name = (id: number | null) => (id === null ? 'Semua kategori' : (categories.find((k) => k.id === id)?.nama ?? id));

    return (
        <div className="space-y-4">
            <div className="flex justify-between items-center gap-2">
                <p className="text-body-sm text-txt-black-500">Medan metadata tambahan mengikut kategori — tanpa pengubahsuaian kod (SRS-DMS-11).</p>
                <Button size="sm" onClick={() => setEditing({ jenis: 'teks', wajib: false, aktif: true, kategori_id: null, susunan: 0 })}>
                    Tambah medan
                </Button>
            </div>
            <div className="overflow-x-auto">
                <Table
                    head={
                        <>
                            <th>Medan</th>
                            <th>Kategori</th>
                            <th className="hidden sm:table-cell">Jenis</th>
                            <th>Status</th>
                            <th className="text-right">Tindakan</th>
                        </>
                    }
                >
                    {[...fields.values()].map((m) => (
                        <tr key={m.id}>
                            <td>
                                <span className="font-medium">{m.label}</span>
                                {m.wajib && <span className="text-txt-danger"> *</span>}
                                <p className="text-body-xs text-txt-black-500">{m.kod}</p>
                            </td>
                            <td>{name(m.kategori_id)}</td>
                            <td className="hidden sm:table-cell">
                                {m.jenis}
                                {m.pilihan && <p className="text-body-xs text-txt-black-500">{m.pilihan.join(', ')}</p>}
                            </td>
                            <td>{m.aktif ? <Badge variant="success">Aktif</Badge> : <Badge variant="neutral">Tidak aktif</Badge>}</td>
                            <td className="text-right">
                                <button type="button" className="text-body-sm text-txt-primary hover:underline" onClick={() => setEditing(m)}>
                                    Ubah
                                </button>
                            </td>
                        </tr>
                    ))}
                    {fields.size === 0 && (
                        <tr>
                            <td colSpan={5} className="text-center text-txt-black-500 py-6">
                                Tiada medan tambahan.
                            </td>
                        </tr>
                    )}
                </Table>
            </div>
            {editing && <FieldModal value={editing} categories={categories} onClose={() => setEditing(null)} />}
        </div>
    );
}

function FieldModal({ value, categories, onClose }: { value: Partial<MedanMetadata>; categories: Kategori[]; onClose: () => void }) {
    const queryClient = useQueryClient();
    const [form, setForm] = useState<Partial<MedanMetadata>>(value);
    const [pilihan, setPilihan] = useState((value.pilihan ?? []).join(', '));
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const save = useMutation({
        mutationFn: () => {
            const body: Partial<MedanMetadata> = { ...form, pilihan: form.jenis === 'senarai' ? pilihan.split(',').map((p) => p.trim()).filter(Boolean) : null };
            if (value.id) {
                delete body.id;
                delete body.kod;
            }
            return dmsApi.simpanMedan(body, value.id);
        },
        onSuccess: () => {
            toast.success('Medan disimpan.');
            queryClient.invalidateQueries({ queryKey: ['dms', 'kategori'] });
            onClose();
        },
        onError: (e: Error) => (e instanceof ApiError && e.errors ? setErrors(e.errors) : toast.error(e.message)),
    });
    const err = (k: string) => errors[k]?.[0] && <p className="mt-1 text-body-xs text-txt-danger">{errors[k][0]}</p>;

    return (
        <Modal open onClose={onClose}>
            <div className="p-6 space-y-3">
                <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900">{value.id ? 'Ubah medan' : 'Tambah medan'}</h2>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <InputLabel htmlFor="m-kod" className="mb-1.5">
                            Kod
                        </InputLabel>
                        <input id="m-kod" className={inputClass} value={form.kod ?? ''} disabled={!!value.id} onChange={(e) => setForm((f) => ({ ...f, kod: e.target.value }))} />
                        {err('kod')}
                    </div>
                    <div>
                        <InputLabel htmlFor="m-label" className="mb-1.5">
                            Label
                        </InputLabel>
                        <input id="m-label" className={inputClass} value={form.label ?? ''} onChange={(e) => setForm((f) => ({ ...f, label: e.target.value }))} />
                        {err('label')}
                    </div>
                    <div>
                        <InputLabel htmlFor="m-kat" className="mb-1.5">
                            Kategori
                        </InputLabel>
                        <select
                            id="m-kat"
                            className={inputClass}
                            value={form.kategori_id ?? ''}
                            disabled={!!value.id}
                            onChange={(e) => setForm((f) => ({ ...f, kategori_id: e.target.value === '' ? null : Number(e.target.value) }))}
                        >
                            <option value="">Semua kategori</option>
                            {categories.map((k) => (
                                <option key={k.id} value={k.id}>
                                    {k.nama}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <InputLabel htmlFor="m-jenis" className="mb-1.5">
                            Jenis
                        </InputLabel>
                        <select id="m-jenis" className={inputClass} value={form.jenis} onChange={(e) => setForm((f) => ({ ...f, jenis: e.target.value as MedanMetadata['jenis'] }))}>
                            <option value="teks">Teks</option>
                            <option value="nombor">Nombor</option>
                            <option value="tarikh">Tarikh</option>
                            <option value="senarai">Senarai pilihan</option>
                            <option value="ya_tidak">Ya / Tidak</option>
                        </select>
                    </div>
                </div>
                {form.jenis === 'senarai' && (
                    <div>
                        <InputLabel htmlFor="m-pilihan" className="mb-1.5">
                            Pilihan (pisah dengan koma)
                        </InputLabel>
                        <input id="m-pilihan" className={inputClass} value={pilihan} onChange={(e) => setPilihan(e.target.value)} />
                        {err('pilihan')}
                    </div>
                )}
                <div className="flex gap-6">
                    <label className="flex items-center gap-2 text-body-sm">
                        <input type="checkbox" className="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary" checked={!!form.wajib} onChange={(e) => setForm((f) => ({ ...f, wajib: e.target.checked }))} />
                        Wajib
                    </label>
                    <label className="flex items-center gap-2 text-body-sm">
                        <input type="checkbox" className="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary" checked={!!form.aktif} onChange={(e) => setForm((f) => ({ ...f, aktif: e.target.checked }))} />
                        Aktif
                    </label>
                </div>
                <div className="flex justify-end gap-3 pt-2">
                    <Button variant="secondary" onClick={onClose}>
                        Batal
                    </Button>
                    <Button disabled={save.isPending} onClick={() => save.mutate()}>
                        Simpan
                    </Button>
                </div>
            </div>
        </Modal>
    );
}

function ParametersTab() {
    const { data, error } = useQuery({ queryKey: ['dms', 'parameter'], queryFn: dmsApi.parameter });
    if (error) return <Forbidden error={error} />;

    return (
        <div className="space-y-4">
            <Alert type="info">
                Senarai putih jenis fail, had saiz lalai, kuota dan tempoh pautan ialah parameter sistem. Ubah di{' '}
                <Link href="/admin/adm/parameter" className="underline">
                    Pentadbiran Sistem › Parameter
                </Link>{' '}
                — perubahan parameter kritikal memerlukan kelulusan pentadbir kedua. Jenis fail boleh laksana, skrip dan dokumen bermakro sentiasa disekat.
            </Alert>
            <div className="overflow-x-auto">
                <Table
                    head={
                        <>
                            <th>Parameter</th>
                            <th>Nilai semasa</th>
                            <th className="hidden sm:table-cell">Lalai</th>
                        </>
                    }
                >
                    {(data?.data ?? []).map((p) => (
                        <tr key={p.kunci}>
                            <td>
                                {p.label}
                                {p.kritikal && (
                                    <span className="ms-2">
                                        <Badge variant="warning">Kritikal</Badge>
                                    </span>
                                )}
                                <p className="text-body-xs text-txt-black-500">{p.kunci}</p>
                            </td>
                            <td className="break-words">{Array.isArray(p.nilai) ? p.nilai.join(', ').toUpperCase() : String(p.nilai)}</td>
                            <td className="hidden sm:table-cell break-words text-txt-black-500">{Array.isArray(p.lalai) ? p.lalai.join(', ').toUpperCase() : String(p.lalai)}</td>
                        </tr>
                    ))}
                </Table>
            </div>
        </div>
    );
}

function QuotaTab() {
    const { data, error } = useQuery({ queryKey: ['dms', 'kuota'], queryFn: dmsApi.kuota });
    if (error) return <Forbidden error={error} />;
    const q = data?.data;
    if (!q) return null;
    const pct = Math.min(100, q.peratus);

    return (
        <div className="space-y-4">
            {q.amaran && <Alert type="warning">Penggunaan storan melebihi {q.amaran_peratus}% kuota. Amaran telah dihantar kepada pentadbir.</Alert>}
            <div>
                <div className="flex justify-between text-body-sm mb-1">
                    <span>
                        {saizMesra(q.guna_bait)} daripada {saizMesra(q.kuota_bait)}
                    </span>
                    <span className="font-medium">{q.peratus}%</span>
                </div>
                <div className="h-3 rounded-full bg-bg-washed overflow-hidden" role="progressbar" aria-valuenow={pct} aria-valuemin={0} aria-valuemax={100} aria-label="Penggunaan kuota storan">
                    <div className={`h-full ${q.amaran ? 'bg-danger-600' : 'bg-primary-600'}`} style={{ width: `${pct}%` }} />
                </div>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="overflow-x-auto">
                    <Table
                        head={
                            <>
                                <th>Modul</th>
                                <th>Dokumen</th>
                                <th>Saiz</th>
                            </>
                        }
                    >
                        {q.ikut_modul.map((m) => (
                            <tr key={m.kod}>
                                <td>{m.kod.toUpperCase()}</td>
                                <td>{m.bilangan}</td>
                                <td>{saizMesra(m.bait)}</td>
                            </tr>
                        ))}
                    </Table>
                </div>
                <div className="overflow-x-auto">
                    <Table
                        head={
                            <>
                                <th>Kategori</th>
                                <th>Dokumen</th>
                                <th>Saiz</th>
                            </>
                        }
                    >
                        {q.ikut_kategori.map((k) => (
                            <tr key={k.kod}>
                                <td>{k.nama}</td>
                                <td>{k.bilangan}</td>
                                <td>{saizMesra(k.bait)}</td>
                            </tr>
                        ))}
                    </Table>
                </div>
            </div>
        </div>
    );
}

function QuarantineTab() {
    const queryClient = useQueryClient();
    const { data, error } = useQuery({ queryKey: ['dms', 'kuarantin'], queryFn: dmsApi.kuarantin });
    const destroy = useMutation({
        mutationFn: dmsApi.musnahKuarantin,
        onSuccess: (r) => {
            toast.success(r.message);
            queryClient.invalidateQueries({ queryKey: ['dms', 'kuarantin'] });
        },
        onError: (e: Error) => toast.error(e.message),
    });
    if (error) return <Forbidden error={error} />;

    return (
        <div className="overflow-x-auto">
            <Table
                head={
                    <>
                        <th>Fail</th>
                        <th>Ancaman</th>
                        <th className="hidden sm:table-cell">Pemuat naik</th>
                        <th className="hidden md:table-cell">Tarikh</th>
                        <th className="text-right">Tindakan</th>
                    </>
                }
            >
                {(data?.data ?? []).map((k) => (
                    <tr key={k.id}>
                        <td className="break-all">
                            {k.nama_fail}
                            <p className="text-body-xs text-txt-black-500">{saizMesra(k.saiz_bait)}</p>
                        </td>
                        <td>
                            <Badge variant="danger">{k.nama_ancaman ?? '—'}</Badge>
                        </td>
                        <td className="hidden sm:table-cell">{k.pemuat_naik ?? '—'}</td>
                        <td className="hidden md:table-cell whitespace-nowrap">{tarikhMesra(k.dikuarantin_pada, true)}</td>
                        <td className="text-right">
                            {k.dimusnahkan_pada ? (
                                <span className="text-body-xs text-txt-black-500">Dimusnahkan</span>
                            ) : (
                                <button type="button" className="text-body-sm text-danger-600 hover:underline" onClick={() => destroy.mutate(k.id)}>
                                    Musnahkan
                                </button>
                            )}
                        </td>
                    </tr>
                ))}
                {data?.data.length === 0 && (
                    <tr>
                        <td colSpan={5} className="text-center text-txt-black-500 py-6">
                            Tiada fail dikuarantin.
                        </td>
                    </tr>
                )}
            </Table>
        </div>
    );
}

function DisposalsTab() {
    const { data, error } = useQuery({ queryKey: ['dms', 'pelupusan'], queryFn: dmsApi.pelupusan });
    if (error) return <Forbidden error={error} />;

    return (
        <div className="space-y-3">
            <p className="text-body-sm text-txt-black-500">
                Permohonan pelupusan diluluskan melalui aliran kerja <code>dms.pelupusan</code> (dua peringkat) dalam modul Workflow.
            </p>
            <div className="overflow-x-auto">
                <Table
                    head={
                        <>
                            <th>Dokumen</th>
                            <th className="hidden sm:table-cell">Justifikasi</th>
                            <th>Status</th>
                            <th className="hidden md:table-cell">Dimohon</th>
                        </>
                    }
                >
                    {(data?.data ?? []).map((p) => (
                        <tr key={p.id}>
                            <td>
                                <Link href={`/admin/dms/${p.dokumen.id}`} className="text-txt-primary hover:underline">
                                    {p.dokumen.no_rujukan}
                                </Link>
                                <p className="text-body-xs text-txt-black-500 break-words">{p.dokumen.tajuk}</p>
                            </td>
                            <td className="hidden sm:table-cell max-w-xs break-words">{p.justifikasi}</td>
                            <td>
                                <Badge variant={p.status === 'lulus' ? 'success' : p.status === 'menunggu' ? 'warning' : 'neutral'}>{p.status}</Badge>
                            </td>
                            <td className="hidden md:table-cell whitespace-nowrap">
                                {p.pemohon ?? '—'}
                                <p className="text-body-xs text-txt-black-500">{tarikhMesra(p.dimohon_pada, true)}</p>
                            </td>
                        </tr>
                    ))}
                    {data?.data.length === 0 && (
                        <tr>
                            <td colSpan={4} className="text-center text-txt-black-500 py-6">
                                Tiada permohonan pelupusan.
                            </td>
                        </tr>
                    )}
                </Table>
            </div>
        </div>
    );
}
